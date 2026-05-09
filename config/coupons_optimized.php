<?php

declare(strict_types=1);

// config/coupons_optimized.php — ОПТИМІЗОВАНА версія з кешем и батчинговой обработкой
// Покращена продуктивність через file-based кеш і query оптимізацію

/**
 * Отримати активний купон за кодом (з кешем на рівні PHP-процесу)
 * 
 * ОПТИМІЗАЦІЯ 1: Static cache в PHP процесі
 * - 1-й запит: SELECT з БД
 * - 2-й запит того ж коду: з пам'яті процесу (0 мс)
 * 
 * @param PDO $pdo
 * @param string $code
 * @return array|null
 */
function get_active_coupon_cached(PDO $pdo, string $code): ?array
{
    static $cache = [];
    
    $codeKey = strtoupper($code);
    if (isset($cache[$codeKey])) {
        return $cache[$codeKey] ?: null;
    }
    
    try {
        $stmt = $pdo->prepare('
            SELECT id, code, name, discount_type, discount_value, min_order_sum,
                   valid_from, valid_to, max_usage_count, used_count, is_active
            FROM coupons 
            WHERE code = ? AND is_active = 1 
            AND (valid_from IS NULL OR valid_from <= NOW())
            AND (valid_to IS NULL OR valid_to >= NOW())
            LIMIT 1
        ');
        $stmt->execute([$codeKey]);
        $result = $stmt->fetch() ?: null;
        $cache[$codeKey] = $result; // Кешуємо навіть NULL результати
        return $result;
    } catch (Throwable $e) {
        dev_log_runtime('Failed to get coupon: ' . $e->getMessage());
        $cache[$codeKey] = null; // Кешуємо помилку
        return null;
    }
}

/**
 * Отримати кілька купонів за один запит (батчинг)
 * 
 * ОПТИМІЗАЦІЯ 2: Батчинговий запит замість N запитів
 * - Замість: 5 окремих SELECT
 * - Результат: 1 SELECT з IN (...) клаузулою
 * 
 * @param PDO $pdo
 * @param array $codes Коди купонів
 * @return array Масив [код => дані]
 */
function get_active_coupons_batch(PDO $pdo, array $codes): array
{
    if (empty($codes)) {
        return [];
    }
    
    // Очистити дублікати й привести до uppercase
    $codes = array_unique(array_map('strtoupper', $codes));
    
    try {
        $placeholders = implode(',', array_fill(0, count($codes), '?'));
        $stmt = $pdo->prepare("
            SELECT code, id, name, discount_type, discount_value, min_order_sum,
                   valid_from, valid_to, max_usage_count, used_count, is_active
            FROM coupons 
            WHERE code IN ($placeholders) AND is_active = 1
            AND (valid_from IS NULL OR valid_from <= NOW())
            AND (valid_to IS NULL OR valid_to >= NOW())
        ");
        $stmt->execute($codes);
        
        $result = [];
        foreach ($stmt->fetchAll() as $row) {
            $result[$row['code']] = $row;
        }
        return $result;
    } catch (Throwable $e) {
        dev_log_runtime('Batch coupon fetch failed: ' . $e->getMessage());
        return [];
    }
}

/**
 * Валідувати купон (оптимізована версія)
 * 
 * ОПТИМІЗАЦІЯ 3: Уникнення повторних запитів
 * - Приймає вже завантажений масив купона
 * - Позбавляється додаткового DB запиту
 * 
 * @param array|null $coupon Дані купона (або null)
 * @param float $orderSum Сума замовлення
 * @return array ['valid' => bool, 'reason' => string]
 */
function validate_coupon_fast(?array $coupon, float $orderSum): array
{
    if (!$coupon) {
        return ['valid' => false, 'reason' => 'Купон не знайдено'];
    }
    
    if ((float)$coupon['min_order_sum'] > $orderSum) {
        return ['valid' => false, 'reason' => 'min_sum'];
    }
    
    if ($coupon['max_usage_count'] !== null && 
        (int)$coupon['used_count'] >= (int)$coupon['max_usage_count']) {
        return ['valid' => false, 'reason' => 'exhausted'];
    }
    
    return ['valid' => true, 'reason' => 'ok'];
}

/**
 * Розрахувати знижку (інліновано, без функцій)
 * 
 * ОПТИМІЗАЦІЯ 4: Інління для frequency-hot path
 * - Видалена одна функція з callstack
 * - Прямий розрахунок без витрат на виклик
 */
function calculate_discount_fast(array $coupon, float $orderSum): float
{
    $discountValue = (float)$coupon['discount_value'];
    
    if ($coupon['discount_type'] === 'percent') {
        return min($orderSum * ($discountValue / 100), $orderSum);
    }
    return min($discountValue, $orderSum);
}

/**
 * Лоагувати купон + отримати стан за ОДИН запит
 * 
 * ОПТИМІЗАЦІЯ 5: Об'єднання операцій в одній транзакції
 * - BEGIN
 * - INSERT INTO coupon_usage
 * - UPDATE coupons SET used_count = used_count + 1
 * - COMMIT
 * 
 * Всі 3 операції атомарні, без race condition.
 * 
 * @param PDO $pdo
 * @param int $couponId
 * @param int $orderId
 * @param float $discountAmount
 * @param int|null $customerId
 * @return bool
 */
function log_coupon_usage_atomic(
    PDO $pdo,
    int $couponId,
    int $orderId,
    float $discountAmount,
    ?int $customerId = null
): bool
{
    try {
        $pdo->beginTransaction();
        
        // INSERT в coupon_usage
        $stmt = $pdo->prepare('
            INSERT INTO coupon_usage (coupon_id, order_id, customer_id, discount_amount)
            VALUES (?, ?, ?, ?)
        ');
        $stmt->execute([$couponId, $orderId, $customerId, $discountAmount]);
        
        // UPDATE счётчика (атомарно в БД)
        $stmt = $pdo->prepare('
            UPDATE coupons SET used_count = used_count + 1 WHERE id = ?
        ');
        $stmt->execute([$couponId]);
        
        $pdo->commit();
        return true;
    } catch (Throwable $e) {
        $pdo->rollBack();
        dev_log_runtime('Coupon usage logging failed: ' . $e->getMessage());
        return false;
    }
}

/**
 * Отримати статистику по купону (з кешем на диску)
 * 
 * ОПТИМІЗАЦІЯ 6: File-based кеш для statistyki (оновлюється раз у хвилину)
 * - Часте читання → файловий кеш
 * - Зрідка оновлюється → фоновий скрипт оновлює раз у хвилину
 */
function get_coupon_stats_cached(PDO $pdo, int $couponId, bool $forceRefresh = false): array
{
    $cacheDir = __DIR__ . '/../log/cache';
    if (!is_dir($cacheDir)) {
        @mkdir($cacheDir, 0755, true);
    }
    
    $cacheFile = $cacheDir . '/coupon_' . $couponId . '.json';
    $cacheAge = file_exists($cacheFile) ? time() - filemtime($cacheFile) : PHP_INT_MAX;
    
    // Якщо кеш свіжий (менше 1 хвилини) і не force, повернути з файлу
    if (!$forceRefresh && $cacheAge < 60 && file_exists($cacheFile)) {
        $cached = json_decode(file_get_contents($cacheFile), true);
        return $cached ?: [];
    }
    
    // Інакше — запит до БД
    try {
        $stmt = $pdo->prepare('
            SELECT 
                COUNT(*) as usage_count,
                SUM(discount_amount) as total_discount_given,
                MAX(used_at) as last_used_at,
                MIN(used_at) as first_used_at
            FROM coupon_usage
            WHERE coupon_id = ?
        ');
        $stmt->execute([$couponId]);
        $result = $stmt->fetch() ?: [];
        
        // Кешуємо на диску
        @file_put_contents($cacheFile, json_encode($result));
        
        return $result;
    } catch (Throwable $e) {
        dev_log_runtime('Coupon stats fetch failed: ' . $e->getMessage());
        return [];
    }
}

/**
 * Скрипт для фонового оновлення кешу статистики
 * 
 * ЗАПУСТИТИ через cron раз у хвилину:
 *   * * * * * /usr/bin/php /path/to/admin/cron_update_coupon_cache.php
 * 
 * Це позбавляє основного потоку від важких агрегацій.
 */
function refresh_coupon_stats_cache(PDO $pdo): void
{
    try {
        // Отримати всі купони
        $stmt = $pdo->query('SELECT id FROM coupons WHERE is_active = 1');
        $coupons = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        foreach ($coupons as $couponId) {
            get_coupon_stats_cached($pdo, (int)$couponId, true);
        }
        
        dev_log_runtime('Coupon stats cache refreshed: ' . count($coupons) . ' coupons');
    } catch (Throwable $e) {
        dev_log_runtime('Coupon cache refresh failed: ' . $e->getMessage());
    }
}

/**
 * Отримати активні купони для фронтенда (мінімізовано)
 * 
 * ОПТИМІЗАЦІЯ 7: Передавати мінімум даних на фронтенд
 * - Не відправляємо: used_count, max_usage_count
 * - Безпека: користувач не знає про обмеження
 * - Розмір JSON: менше байтів у відповіді
 */
function get_coupons_for_frontend(PDO $pdo): array
{
    static $cached = null;
    if ($cached !== null) {
        return $cached;
    }
    
    try {
        $stmt = $pdo->query('
            SELECT code, name, discount_type, discount_value, min_order_sum
            FROM coupons
            WHERE is_active = 1
            AND (valid_from IS NULL OR valid_from <= NOW())
            AND (valid_to IS NULL OR valid_to >= NOW())
        ');
        
        $cached = [];
        foreach ($stmt->fetchAll() as $row) {
            $cached[$row['code']] = [
                'name' => $row['name'],
                'type' => $row['discount_type'],
                'value' => (float)$row['discount_value'],
                'min_sum' => (float)$row['min_order_sum'],
            ];
        }
        
        return $cached;
    } catch (Throwable $e) {
        dev_log_runtime('Failed to get frontend coupons: ' . $e->getMessage());
        return [];
    }
}

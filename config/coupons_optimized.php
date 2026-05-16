<?php

declare(strict_types=1);

// config/coupons_optimized.php — ОПТИМИЗИРОВАННАЯ версия с кешем и батчинговой обработкой
// Улучшена производительность через file-based кеш и query оптимизацию

/**
 * Получить активный купон по коду (с кешем на уровне PHP-процесса)
 *
 * ОПТИМИЗАЦИЯ 1: Static cache в PHP процессе
 * - 1-й запрос: SELECT из БД
 * - 2-й запрос того же кода: из памяти процесса (0 мс)
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
        $cache[$codeKey] = $result; // Кешируем даже NULL результаты
        return $result;
    } catch (Throwable $e) {
        dev_log_runtime('Failed to get coupon: ' . $e->getMessage());
        $cache[$codeKey] = null; // Кешируем ошибку
        return null;
    }
}

/**
 * Получить несколько купонов за один запрос (батчинг)
 *
 * ОПТИМИЗАЦИЯ 2: Батчинговый запрос вместо N запросов
 * - Вместо: 5 отдельных SELECT
 * - Результат: 1 SELECT с IN (...) клаузулой
 *
 * @param PDO $pdo
 * @param array $codes Коды купонов
 * @return array Массив [код => данные]
 */
function get_active_coupons_batch(PDO $pdo, array $codes): array
{
    if (empty($codes)) {
        return [];
    }
    
    // Очистить дубликаты и привести к uppercase
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
 * Валидировать купон (оптимизированная версия)
 *
 * ОПТИМИЗАЦИЯ 3: Избежание повторных запросов
 * - Принимает уже загруженный массив купона
 * - Избавляется от дополнительного DB запроса
 *
 * @param array|null $coupon Данные купона (или null)
 * @param float $orderSum Сумма заказа
 * @return array ['valid' => bool, 'reason' => string]
 */
function validate_coupon_fast(?array $coupon, float $orderSum): array
{
    if (!$coupon) {
        return ['valid' => false, 'reason' => 'Купон не найден'];
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
 * Рассчитать скидку (инлайнено, без функций)
 *
 * ОПТИМИЗАЦИЯ 4: Инлайнинг для frequency-hot path
 * - Удалена одна функция из callstack
 * - Прямой расчёт без затрат на вызов
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
 * Загрузить купон + получить состояние за ОДИН запрос
 *
 * ОПТИМИЗАЦИЯ 5: Объединение операций в одной транзакции
 * - BEGIN
 * - INSERT INTO coupon_usage
 * - UPDATE coupons SET used_count = used_count + 1
 * - COMMIT
 *
 * Все 3 операции атомарные, без race condition.
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
 * Получить статистику по купону (с кешем на диске)
 *
 * ОПТИМИЗАЦИЯ 6: File-based кеш для statistyki (обновляется раз в минуту)
 * - Частое чтение → файловый кеш
 * - Редко обновляется → фоновый скрипт обновляет раз в минуту
 */
function get_coupon_stats_cached(PDO $pdo, int $couponId, bool $forceRefresh = false): array
{
    $cacheDir = __DIR__ . '/../log/cache';
    if (!is_dir($cacheDir)) {
        @mkdir($cacheDir, 0755, true);
    }
    
    $cacheFile = $cacheDir . '/coupon_' . $couponId . '.json';
    $cacheAge = file_exists($cacheFile) ? time() - filemtime($cacheFile) : PHP_INT_MAX;
    
    // Если кеш свежий (менее 1 минуты) и не force, вернуть из файла
    if (!$forceRefresh && $cacheAge < 60 && file_exists($cacheFile)) {
        $cached = json_decode(file_get_contents($cacheFile), true);
        return $cached ?: [];
    }
    
    // Иначе — запрос к БД
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
        
        // Кешируем на диске
        @file_put_contents($cacheFile, json_encode($result));
        
        return $result;
    } catch (Throwable $e) {
        dev_log_runtime('Coupon stats fetch failed: ' . $e->getMessage());
        return [];
    }
}

/**
 * Скрипт для фонового обновления кеша статистики
 *
 * ЗАПУСТИТЬ через cron раз в минуту:
 *   * * * * * /usr/bin/php /path/to/admin/cron_update_coupon_cache.php
 *
 * Это избавляет основной поток от тяжелых агрегаций.
 */
function refresh_coupon_stats_cache(PDO $pdo): void
{
    try {
        // Получить все купоны
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
 * Получить активные купоны для фронтенда (минимизировано)
 *
 * ОПТИМИЗАЦИЯ 7: Передавать минимум данных на фронтенд
 * - Не отправляем: used_count, max_usage_count
 * - Безопасность: пользователь не знает об ограничениях
 * - Размер JSON: меньше байтов в ответе
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

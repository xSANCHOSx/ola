<?php

declare(strict_types=1);

// config/coupons.php — Функції для роботи з системою купонів
// Інтегрується в sendmail.php для валідації та застосування купонів

/**
 * Отримати активний купон за кодом
 * 
 * @param PDO $pdo
 * @param string $code Код купона
 * @return array|null Купон або null якщо не знайдено
 */
function get_active_coupon(PDO $pdo, string $code): ?array
{
    if (empty($code)) {
        return null;
    }
    
    try {
        $stmt = $pdo->prepare('
            SELECT * FROM coupons 
            WHERE code = ? 
            AND is_active = 1 
            AND (valid_from IS NULL OR valid_from <= NOW())
            AND (valid_to IS NULL OR valid_to >= NOW())
            LIMIT 1
        ');
        $stmt->execute([strtoupper($code)]);
        $coupon = $stmt->fetch();
        return $coupon ?: null;
    } catch (Throwable $e) {
        dev_log_runtime('Failed to get coupon: ' . $e->getMessage());
        return null;
    }
}

/**
 * Валідувати купон для замовлення
 * 
 * @param PDO $pdo
 * @param string $couponCode Код купона
 * @param float $orderSum Сума замовлення БЕЗ знижки
 * @return array ['valid' => bool, 'coupon' => array|null, 'error' => string|null]
 */
function validate_coupon_for_order(PDO $pdo, string $couponCode, float $orderSum): array
{
    $coupon = get_active_coupon($pdo, $couponCode);
    
    if (!$coupon) {
        return [
            'valid' => false,
            'coupon' => null,
            'error' => 'Купон не знайдено або закінчився'
        ];
    }
    
    // Перевірити мінімальну суму замовлення
    if ($orderSum < (float)$coupon['min_order_sum']) {
        return [
            'valid' => false,
            'coupon' => $coupon,
            'error' => sprintf(
                'Мінімальна сума замовлення: %.2f руб. (поточна: %.2f)',
                $coupon['min_order_sum'],
                $orderSum
            )
        ];
    }
    
    // Перевірити кількість використань
    if ($coupon['max_usage_count'] !== null && (int)$coupon['used_count'] >= (int)$coupon['max_usage_count']) {
        return [
            'valid' => false,
            'coupon' => $coupon,
            'error' => 'Купон вичерпано максимум використань'
        ];
    }
    
    return ['valid' => true, 'coupon' => $coupon, 'error' => null];
}

/**
 * Розрахувати величину знижки за купоном
 * 
 * @param array $coupon Дані купона з БД
 * @param float $orderSum Сума замовлення БЕЗ знижки
 * @return float Величина знижки
 */
function calculate_discount_amount(array $coupon, float $orderSum): float
{
    $discountValue = (float)$coupon['discount_value'];
    
    if ($coupon['discount_type'] === 'percent') {
        // Відсоткова знижка
        return min($orderSum * ($discountValue / 100), $orderSum);
    } else {
        // Фіксована знижка
        return min($discountValue, $orderSum);
    }
}

/**
 * Записати факт використання купона
 * 
 * @param PDO $pdo
 * @param int $couponId ID купона
 * @param int $orderId ID замовлення
 * @param float $discountAmount Величина знижки
 * @param int|null $customerId ID клієнта
 * @return bool Успіх запису
 */
function log_coupon_usage(
    PDO $pdo,
    int $couponId,
    int $orderId,
    float $discountAmount,
    ?int $customerId = null
): bool
{
    try {
        // Записати факт використання в coupon_usage
        $stmt = $pdo->prepare('
            INSERT INTO coupon_usage (coupon_id, order_id, customer_id, discount_amount)
            VALUES (?, ?, ?, ?)
        ');
        $stmt->execute([$couponId, $orderId, $customerId, $discountAmount]);
        
        // Оновити лічильник використань в coupons
        $stmt = $pdo->prepare('UPDATE coupons SET used_count = used_count + 1 WHERE id = ?');
        $stmt->execute([$couponId]);
        
        return true;
    } catch (Throwable $e) {
        dev_log_runtime('Failed to log coupon usage: ' . $e->getMessage());
        return false;
    }
}

/**
 * Отримати статистику купона
 * 
 * @param PDO $pdo
 * @param int $couponId ID купона
 * @return array Статистика або порожній масив
 */
function get_coupon_stats(PDO $pdo, int $couponId): array
{
    try {
        $stmt = $pdo->prepare('
            SELECT 
                COUNT(*) as usage_count,
                SUM(discount_amount) as total_discount_given,
                MAX(used_at) as last_used_at
            FROM coupon_usage
            WHERE coupon_id = ?
        ');
        $stmt->execute([$couponId]);
        $result = $stmt->fetch();
        return $result ?: [];
    } catch (Throwable $e) {
        dev_log_runtime('Failed to get coupon stats: ' . $e->getMessage());
        return [];
    }
}

<?php

declare(strict_types=1);

// admin/coupon_stats.php — REST endpoint для валідації купонів
// GET: /api/validate_coupon.php?code=OLA5600&sum=1000

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/coupons.php';

// Перевірити таблиці
try {
    $pdo = dev_db_connection();
    ensure_coupons_table($pdo);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error']);
    exit;
}

header('Content-Type: application/json; charset=utf-8');

// ═══ Перевірка методу ══════════════════════════════════════════════════════

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// ═══ Валідація параметрів ═════════════════════════════════════════════════

$couponCode = strtoupper(trim($_GET['code'] ?? ''));
$orderSum = (float)($_GET['sum'] ?? 0);

if (empty($couponCode)) {
    http_response_code(400);
    echo json_encode(['error' => 'Coupon code required']);
    exit;
}

if ($orderSum <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Order sum must be greater than 0']);
    exit;
}

// ═══ Валідація купона ══════════════════════════════════════════════════════

try {
    $pdo = dev_db_connection();
    if (!$pdo instanceof PDO) {
        throw new Exception('DB connection failed');
    }
    
    $validation = validate_coupon_for_order($pdo, $couponCode, $orderSum);
    
    if (!$validation['valid']) {
        http_response_code(400);
        echo json_encode([
            'valid' => false,
            'error' => $validation['error'] ?? 'Coupon is not valid',
        ]);
        exit;
    }
    
    $coupon = $validation['coupon'];
    $discount = calculate_discount_amount($coupon, $orderSum);
    $finalSum = max(0, $orderSum - $discount);
    
    // ✅ Купон валідний
    http_response_code(200);
    echo json_encode([
        'valid' => true,
        'coupon' => [
            'code' => $coupon['code'],
            'name' => $coupon['name'],
            'discount_type' => $coupon['discount_type'],
            'discount_value' => (float)$coupon['discount_value'],
        ],
        'calculation' => [
            'original_sum' => $orderSum,
            'discount_amount' => round($discount, 2),
            'final_sum' => round($finalSum, 2),
            'discount_percent' => round(($discount / $orderSum) * 100, 1),
        ],
    ]);
    exit;
    
} catch (Throwable $e) {
    dev_log_runtime('Coupon validation API error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error']);
    exit;
}

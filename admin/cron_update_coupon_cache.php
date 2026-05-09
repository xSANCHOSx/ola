<?php

declare(strict_types=1);

// admin/cron_update_coupon_cache.php — Фоновий скрипт оновлення кешу
// Запускати через cron: * * * * * /usr/bin/php /path/to/admin/cron_update_coupon_cache.php

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit('Forbidden: CLI only');
}

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/coupons_optimized.php';

$startTime = microtime(true);

try {
    $pdo = dev_db_connection();
    if (!$pdo instanceof PDO) {
        throw new Exception('Database connection failed');
    }
    
    // Отримати всі активні купони
    $stmt = $pdo->query('SELECT id FROM coupons WHERE is_active = 1 ORDER BY id');
    $coupons = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    if (empty($coupons)) {
        dev_log_runtime('Coupon cache refresh: no active coupons');
        exit(0);
    }
    
    // Оновити кеш для кожного купона
    $cacheDir = __DIR__ . '/../log/cache';
    if (!is_dir($cacheDir)) {
        @mkdir($cacheDir, 0755, true);
    }
    
    $updated = 0;
    foreach ($coupons as $couponId) {
        $stats = get_coupon_stats_cached($pdo, (int)$couponId, true);
        if (!empty($stats)) {
            $updated++;
        }
    }
    
    $duration = round((microtime(true) - $startTime) * 1000, 2);
    
    dev_log_runtime(sprintf(
        'Coupon cache refresh: %d coupons updated in %.2f ms',
        $updated,
        $duration
    ));
    
} catch (Throwable $e) {
    dev_log_runtime('Coupon cache refresh failed: ' . $e->getMessage());
    exit(1);
}

exit(0);

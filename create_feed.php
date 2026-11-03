<?php
declare(strict_types=1);

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/lib/feed_generator.php';

try {
    $pdo = dev_db_connection();
    [$yandexPath, $googlePath] = generate_product_feeds($pdo, __DIR__);
    $baseUrl = 'https://' . site_domain();
    echo 'Фиды успешно созданы: '
        . '<a href="' . htmlspecialchars($baseUrl . '/yandex_feed.xml', ENT_QUOTES, 'UTF-8') . '">yandex_feed.xml</a>, '
        . '<a href="' . htmlspecialchars($baseUrl . '/google_feed.xml', ENT_QUOTES, 'UTF-8') . '">google_feed.xml</a>';
} catch (Throwable $e) {
    http_response_code(500);
    if (function_exists('dev_log_runtime')) {
        dev_log_runtime('Manual feed generation failed: ' . $e->getMessage());
    }
    echo 'Не удалось создать фиды: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
}

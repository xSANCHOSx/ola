<?php

declare(strict_types=1);

return [
    'db_enabled' => true,
    'fallback_counter_file' => __DIR__ . '/../counter.txt',
    'runtime_log' => __DIR__ . '/../log/runtime.log',
    'security_log' => __DIR__ . '/../log/security.log',
    'admin_session_key' => 'dev_admin_auth',
    'csrf_token_key' => 'csrf_token',
    'rate_limit_window' => 60,  // seconds
    'rate_limit_max_requests' => 5,  // max orders per window
    // Токен для веб-запуска setup.php. Задайте через env: SETUP_TOKEN=<random>
    // Сгенерируйте: php -r "echo bin2hex(random_bytes(16));"
    // Значение '' — доступ закрыт (fail-closed по умолчанию).
    'setup_token' => getenv('SETUP_TOKEN') ?: '',
    // Получатели писем о новых заказах. Задайте через env: ORDER_NOTIFY_EMAILS
    'order_notification_emails' => getenv('ORDER_NOTIFY_EMAILS') ?: '',
    // Токен для веб-запуска convert_webp.php. Задайте через env: CONVERT_WEBP_TOKEN=<random>
    // Сгенерируйте: php -r "echo bin2hex(random_bytes(24));"
    // Значение '' — доступ закрыт (fail-closed по умолчанию).
    'convert_webp_token' => getenv('CONVERT_WEBP_TOKEN') ?: '',
];

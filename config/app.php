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
    // Токен для веб-запуску setup.php. Згенеруйте власний: php -r "echo bin2hex(random_bytes(16));"
    // Після ініціалізації БД рекомендується видалити або змінити.
    'setup_token' => 'r12sfdh34wrt24g3wffsd34653ghd',  // ← встановіть сюди унікальний токен
];

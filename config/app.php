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
];

<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/db.php';

$key = 'integration-rate-limit-' . bin2hex(random_bytes(8));
$cacheFile = __DIR__ . '/../../log/ratelimit_' . md5($key) . '.json';

try {
    if (!check_rate_limit($key, 2, 60)) {
        throw new RuntimeException('The first rate-limit attempt was rejected.');
    }
    if (!check_rate_limit($key, 2, 60)) {
        throw new RuntimeException('The second rate-limit attempt was rejected.');
    }
    if (check_rate_limit($key, 2, 60)) {
        throw new RuntimeException('The third rate-limit attempt was not rejected.');
    }
    fwrite(STDOUT, "PASS: rate limit\n");
} finally {
    if (is_file($cacheFile)) {
        unlink($cacheFile);
    }
}

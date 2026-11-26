<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../lib/preorder_requests.php';

$failures = 0;
$assert = static function (bool $condition, string $message) use (&$failures): void {
    if ($condition) {
        echo "PASS: {$message}\n";
        return;
    }
    $failures++;
    echo "FAIL: {$message}\n";
};

$assert(validate_phone('+7 (900) 000-00-00') === '79000000000', 'телефон нормализуется перед проверкой дубля');
$assert(validate_phone('+79000000000') === validate_phone('+7 (900) 000-00-00'), 'разные форматы одного телефона дают одинаковое значение');

$rateKeys = [
    'preorder-test-' . bin2hex(random_bytes(8)) . '-a',
    'preorder-test-' . bin2hex(random_bytes(8)) . '-b',
];
try {
    for ($i = 1; $i <= 5; $i++) {
        $assert(check_rate_limit($rateKeys[0], 5, 300) === true, "rate limit пропускает запрос {$i} из 5");
    }
    $assert(check_rate_limit($rateKeys[0], 5, 300) === false, 'rate limit блокирует шестой запрос в окне');
    $assert(check_rate_limit($rateKeys[1], 5, 300) === true, 'rate limit разделяет разные ключи/IP');
} finally {
    $runtimeLog = (string)(dev_app_config()['runtime_log'] ?? (__DIR__ . '/../log/runtime.log'));
    foreach ($rateKeys as $key) {
        @unlink(dirname($runtimeLog) . '/ratelimit_' . md5($key) . '.json');
    }
}

if (in_array('sqlite', PDO::getAvailableDrivers(), true)) {
    $pdo = new PDO('sqlite::memory:', null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    $pdo->exec('CREATE TABLE preorder_requests (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        product_id INTEGER NOT NULL,
        customer_phone VARCHAR(64) NOT NULL,
        created_at DATETIME NOT NULL
    )');
    $now = date('Y-m-d H:i:s');
    $old = date('Y-m-d H:i:s', time() - 601);
    $insert = $pdo->prepare('INSERT INTO preorder_requests (product_id, customer_phone, created_at) VALUES (?, ?, ?)');
    $insert->execute([42, validate_phone('+7 (900) 000-00-00'), $now]);
    $insert->execute([99, '79000000000', $old]);

    $assert(preorder_has_recent_duplicate($pdo, 42, validate_phone('+79000000000')) === true, 'блокируется повтор для того же товара и телефона');
    $assert(preorder_has_recent_duplicate($pdo, 42, validate_phone('+7 901 000-00-00')) === false, 'другая заявка с другим телефоном разрешается');
    $assert(preorder_has_recent_duplicate($pdo, 43, '79000000000') === false, 'заявка на другой товар разрешается');
    $assert(preorder_has_recent_duplicate($pdo, 99, '79000000000') === false, 'дубликат старше окна разрешается');
} else {
    $failures++;
    echo "FAIL: для теста дублей нужен PDO SQLite; доступные драйверы: " . implode(', ', PDO::getAvailableDrivers()) . "\n";
}

if ($failures > 0) {
    echo "\n{$failures} test(s) failed.\n";
    exit(1);
}

echo "\nAll preorder protection tests passed.\n";

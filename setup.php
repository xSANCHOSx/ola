<?php
/**
 * DB Setup / Schema Initializer
 *
 * CLI:  php setup.php
 * Web:  /setup.php?token=YOUR_SETUP_TOKEN  (токен в config/app.php → 'setup_token')
 *
 * Після першого запуску рекомендується видалити або заблокувати цей файл.
 */

declare(strict_types=1);

// ── Авторизація ──────────────────────────────────────────────────────────────
$isCli = php_sapi_name() === 'cli';

if (!$isCli) {
    // Підключаємо конфіг щоб дістати токен
    require_once __DIR__ . '/config/db.php';
    $cfg         = dev_app_config();
    $setupToken  = $cfg['setup_token'] ?? '';

    if ($setupToken === '' || ($_GET['token'] ?? '') !== $setupToken) {
        http_response_code(403);
        exit('<b>403 Forbidden.</b> Передайте правильний ?token= або запустіть через CLI.');
    }
    header('Content-Type: text/plain; charset=utf-8');
} else {
    require_once __DIR__ . '/config/db.php';
}

// ── Підключення до БД ─────────────────────────────────────────────────────────
try {
    $pdo = dev_db_connection();
} catch (Throwable $e) {
    exit('❌ DB connection failed: ' . $e->getMessage() . "\n");
}

echo "✅ DB connected.\n";

// ── Застосовуємо схему ────────────────────────────────────────────────────────
$schemaFile = __DIR__ . '/database/schema.sql';
if (!file_exists($schemaFile)) {
    exit('❌ schema.sql not found at ' . $schemaFile . "\n");
}

// Розбиваємо по ";" і виконуємо кожен statement окремо
$schema     = file_get_contents($schemaFile);
$statements = array_filter(array_map('trim', explode(';', $schema)));
$ok = 0; $fail = 0;
foreach ($statements as $sql) {
    if ($sql === '') continue;
    try {
        $pdo->exec($sql);
        $ok++;
    } catch (Throwable $e) {
        echo '⚠️  SQL warning: ' . $e->getMessage() . "\n";
        $fail++;
    }
}
echo "Schema: {$ok} statements OK, {$fail} warnings.\n";

// Застосовуємо міграцію купонів якщо є
$migFile = __DIR__ . '/database/migration_coupons.sql';
if (file_exists($migFile)) {
    $migration  = file_get_contents($migFile);
    $statements = array_filter(array_map('trim', explode(';', $migration)));
    $mOk = 0; $mFail = 0;
    foreach ($statements as $sql) {
        if ($sql === '') continue;
        try {
            $pdo->exec($sql);
            $mOk++;
        } catch (Throwable $e) {
            // Ігноруємо "Duplicate entry" для INSERT дефолтних купонів
            if (strpos($e->getMessage(), 'Duplicate') === false) {
                echo '⚠️  Migration warning: ' . $e->getMessage() . "\n";
            }
            $mFail++;
        }
    }
    echo "Migration coupons: {$mOk} OK, {$mFail} warnings.\n";
}

// ── Синхронізуємо order_sequence з counter.txt ────────────────────────────────
$counterRaw = trim((string)@file_get_contents(__DIR__ . '/counter.txt'));
$counter    = ctype_digit($counterRaw) ? (int)$counterRaw : 0;
$stmt = $pdo->prepare(
    'INSERT INTO order_sequence (id, current_value) VALUES (1, :value)
     ON DUPLICATE KEY UPDATE current_value = GREATEST(current_value, VALUES(current_value))'
);
$stmt->execute(['value' => $counter]);
echo "order_sequence synced (counter={$counter}).\n";

// ── Створюємо admin якщо нема ─────────────────────────────────────────────────
$adminCount = (int)$pdo->query('SELECT COUNT(*) FROM admin_users')->fetchColumn();
if ($adminCount === 0) {
    $pass = bin2hex(random_bytes(6));
    $hash = password_hash($pass, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare('INSERT INTO admin_users (username, password_hash) VALUES (:u, :p)');
    $stmt->execute(['u' => 'admin', 'p' => $hash]);
    echo "\n🔑 Admin created!\n";
    echo "   Login:    admin\n";
    echo "   Password: {$pass}\n";
    echo "\n⚠️  Збережіть пароль — він більше не буде показаний!\n";
} else {
    echo "Admin user already exists — пропускаємо.\n";
}

// ── Створюємо папку для логів ─────────────────────────────────────────────────
$logDir = __DIR__ . '/log';
if (!is_dir($logDir)) {
    mkdir($logDir, 0755, true);
    echo "Log dir created: {$logDir}\n";
} else {
    echo "Log dir OK: {$logDir}\n";
}

echo "\n✅ Setup complete.\n";

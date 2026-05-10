<?php
/**
 * ╔══════════════════════════════════════════════════════════════════╗
 * ║  DEBUG ORDER DIAGNOSTIC — тільки для розробки/дебагу!          ║
 * ║  ВИДАЛІТЬ або ЗАБЛОКУЙТЕ цей файл після налагодження!          ║
 * ║  Запуск: /debug_order.php?token=DEBUG_SECRET_TOKEN             ║
 * ╚══════════════════════════════════════════════════════════════════╝
 */

declare(strict_types=1);

// ─── Захист ─────────────────────────────────────────────────────────────────
const DEBUG_TOKEN = 'ЗМІНІТЬ_ЦЕЙ_ТОКЕН_НА_СЕКРЕТНИЙ'; // ← змініть на унікальний рядок

if (($_GET['token'] ?? '') !== DEBUG_TOKEN) {
    http_response_code(403);
    exit('403 Forbidden');
}

header('Content-Type: text/plain; charset=utf-8');
echo "=== ORDER DEBUG DIAGNOSTIC ===\n";
echo "Date: " . date('Y-m-d H:i:s') . "\n\n";

// ─── 1. Перевірка config/db.php ─────────────────────────────────────────────
echo "─── 1. config/db.php ───────────────────────────────────────\n";
$dbPhpPath = __DIR__ . '/config/db.php';
if (file_exists($dbPhpPath)) {
    echo "[OK] config/db.php EXISTS\n";
    require_once $dbPhpPath;
} else {
    echo "[FAIL] config/db.php НЕ ІСНУЄ!\n";
    echo "       Скопіюйте config/db.php.example у config/db.php і заповніть дані БД.\n";
    exit(1);
}

// ─── 2. Перевірка констант/env DB ───────────────────────────────────────────
echo "\n─── 2. DB credentials ──────────────────────────────────────\n";
$dbHost = getenv('DB_HOST') ?: (defined('APP_DB_HOST') ? APP_DB_HOST : 'NOT SET');
$dbName = getenv('DB_NAME') ?: (defined('APP_DB_NAME') ? APP_DB_NAME : 'NOT SET');
$dbUser = getenv('DB_USER') ?: (defined('APP_DB_USER') ? APP_DB_USER : 'NOT SET');
$dbPass = getenv('DB_PASS') ?: (defined('APP_DB_PASS') ? APP_DB_PASS : '');

echo "DB_HOST : " . $dbHost . "\n";
echo "DB_NAME : " . $dbName . "\n";
echo "DB_USER : " . $dbUser . "\n";
echo "DB_PASS : " . (strlen($dbPass) > 0 ? '[SET, len=' . strlen($dbPass) . ']' : '[EMPTY — КРИТИЧНА ПРОБЛЕМА!]') . "\n";

if (empty($dbPass)) {
    echo "\n[FAIL] DB_PASS порожній!\n";
    echo "       Заповніть APP_DB_PASS у config/db.php або встановіть змінну середовища DB_PASS.\n";
}

// ─── 3. Підключення до БД ────────────────────────────────────────────────────
echo "\n─── 3. DB connection ───────────────────────────────────────\n";
$pdo = null;
try {
    $pdo = dev_db_connection();
    echo "[OK] З'єднання з БД успішне!\n";
} catch (Throwable $e) {
    echo "[FAIL] Помилка підключення до БД:\n";
    echo "       " . $e->getMessage() . "\n";
}

// ─── 4. Перевірка таблиць ────────────────────────────────────────────────────
if ($pdo instanceof PDO) {
    echo "\n─── 4. DB tables ───────────────────────────────────────────\n";
    $requiredTables = ['orders', 'order_items', 'customers', 'order_sequence', 'admin_users'];
    foreach ($requiredTables as $table) {
        try {
            $count = $pdo->query("SELECT COUNT(*) FROM `{$table}`")->fetchColumn();
            echo "[OK] {$table} (rows: {$count})\n";
        } catch (Throwable $e) {
            echo "[FAIL] {$table}: " . $e->getMessage() . "\n";
            echo "       Запустіть /setup.php?token=YOUR_SETUP_TOKEN для ініціалізації БД.\n";
        }
    }

    // ─── 5. Перевірка order_sequence ────────────────────────────────────────
    echo "\n─── 5. order_sequence ──────────────────────────────────────\n";
    try {
        $row = $pdo->query('SELECT * FROM order_sequence WHERE id = 1')->fetch();
        if ($row) {
            echo "[OK] order_sequence: current_value = " . $row['current_value'] . "\n";
        } else {
            echo "[WARN] order_sequence порожня — перший запис буде створено при першому замовленні.\n";
        }
    } catch (Throwable $e) {
        echo "[FAIL] order_sequence: " . $e->getMessage() . "\n";
    }

    // ─── 6. Останні замовлення ──────────────────────────────────────────────
    echo "\n─── 6. Останні 5 замовлень ─────────────────────────────────\n";
    try {
        $orders = $pdo->query(
            'SELECT id, order_number, customer_name_snapshot, total, outbound_email_sent, created_at 
             FROM orders ORDER BY created_at DESC LIMIT 5'
        )->fetchAll();
        if (empty($orders)) {
            echo "[INFO] Замовлень у БД поки немає.\n";
        } else {
            foreach ($orders as $o) {
                echo sprintf(
                    "  OLA-%s | %s | %.2f руб | email=%s | %s\n",
                    $o['order_number'],
                    $o['customer_name_snapshot'] ?? '-',
                    (float)$o['total'],
                    $o['outbound_email_sent'] ? 'sent' : 'NOT sent',
                    $o['created_at']
                );
            }
        }
    } catch (Throwable $e) {
        echo "[FAIL] Не вдалося отримати замовлення: " . $e->getMessage() . "\n";
    }
}

// ─── 7. Перевірка лог-директорії ─────────────────────────────────────────────
echo "\n─── 7. Log directory ───────────────────────────────────────\n";
$logDir = __DIR__ . '/log';
if (!is_dir($logDir)) {
    echo "[FAIL] log/ директорія НЕ ІСНУЄ!\n";
    if (mkdir($logDir, 0755, true)) {
        echo "[FIX]  log/ директорію створено.\n";
    } else {
        echo "[FAIL] Не вдалося створити log/ — перевірте права на запис.\n";
    }
} else {
    echo "[OK] log/ EXISTS\n";
    // Тест запису
    $testFile = $logDir . '/debug_test_' . time() . '.tmp';
    if (file_put_contents($testFile, 'test') !== false) {
        unlink($testFile);
        echo "[OK] Запис у log/ ПРАЦЮЄ\n";
    } else {
        echo "[FAIL] Запис у log/ НЕ ПРАЦЮЄ — перевірте права (chmod 755 log/)\n";
    }

    // Список лог-файлів
    $logs = glob($logDir . '/*.log') ?: [];
    if (empty($logs)) {
        echo "[INFO] Лог-файлів поки немає.\n";
    } else {
        echo "Існуючі логи:\n";
        foreach ($logs as $log) {
            $size = filesize($log);
            echo "  " . basename($log) . " (" . number_format($size) . " bytes)\n";
        }
    }
}

// ─── 8. Тест запису в лог ────────────────────────────────────────────────────
echo "\n─── 8. Test logging ────────────────────────────────────────\n";
if (function_exists('dev_log_runtime')) {
    dev_log_runtime('[DEBUG_DIAGNOSTIC] Test log entry — якщо бачите це у runtime.log, логи ПРАЦЮЮТЬ.');
    echo "[OK] dev_log_runtime() викликано — перевірте log/runtime.log\n";
}
if (function_exists('p2log')) {
    p2log(['debug' => 'test', 'time' => date('Y-m-d H:i:s')], 'debug_test');
    echo "[OK] p2log() викликано — перевірте log/debug_test_*.log\n";
}

// ─── 9. Перевірка PHP сесій (CSRF) ───────────────────────────────────────────
echo "\n─── 9. Session / CSRF ──────────────────────────────────────\n";
session_start();
$testToken = csrf_token();
echo "Session ID  : " . session_id() . "\n";
echo "CSRF token  : " . substr($testToken, 0, 16) . "... [len=" . strlen($testToken) . "]\n";
echo "[OK] CSRF token генерується\n";

// ─── 10. PHP env ─────────────────────────────────────────────────────────────
echo "\n─── 10. PHP environment ────────────────────────────────────\n";
echo "PHP version : " . PHP_VERSION . "\n";
echo "SAPI        : " . php_sapi_name() . "\n";
echo "APP_ENV     : " . (getenv('APP_ENV') ?: 'not set') . "\n";
echo "display_errors : " . ini_get('display_errors') . "\n";
echo "error_reporting : " . ini_get('error_reporting') . "\n";
echo "session.save_path : " . (ini_get('session.save_path') ?: 'default') . "\n";

// ─── ПІДСУМОК ─────────────────────────────────────────────────────────────────
echo "\n=== DIAGNOSTIC COMPLETE ===\n";
echo "Якщо є рядки [FAIL] — виправте їх перед запуском замовлень у production.\n";
echo "\n⚠️  ВИДАЛІТЬ debug_order.php після завершення налагодження!\n";

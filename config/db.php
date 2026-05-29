<?php

declare(strict_types=1);


function load_env_file(): void
{
    static $loaded = false;
    if ($loaded) {
        return;
    }
    $loaded = true;

    // __DIR__ = <webroot>/config
    // dirname(__DIR__) = <webroot>
    // dirname(dirname(__DIR__)) = на 1 рівень вище webroot
    $envFile = dirname(dirname(__DIR__)) . '/.env';

    if (!is_readable($envFile)) {
        return;
    }

    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) {
        return;
    }

    foreach ($lines as $line) {
        $line = trim($line);

        // Пропустити коментарі та порожні рядки
        if ($line === '' || $line[0] === '#') {
            continue;
        }

        if (strpos($line, '=') === false) {
            continue;
        }

        [$key, $value] = explode('=', $line, 2);
        $key   = trim($key);
        $value = trim($value);

        // Прибрати обгортаючі лапки: "value" або 'value'
        if (strlen($value) >= 2 && preg_match('/^(["\'])(.*)\\1$/', $value, $m)) {
            $value = $m[2];
        }

        // Не перезаписувати вже встановлені env-змінні (системні мають пріоритет)
        if (getenv($key) === false) {
            putenv("{$key}={$value}");
            $_ENV[$key] = $value;
        }
    }
}

// Завантажуємо .env відразу при підключенні файлу
load_env_file();

/**
 * Get global PDO connection instance (Singleton)
 */
function dev_db_connection(): PDO
{
    static $instance = null;
    if ($instance !== null) {
        // Перевірка живості з'єднання — відновлення при MySQL gone away
        try {
            $instance->query('SELECT 1');
        } catch (PDOException $e) {
            $instance = null;
        }
    }

    if ($instance !== null) {
        return $instance;
    }

    $dbHost = getenv('DB_HOST') ?: 'localhost';
    $dbName = getenv('DB_NAME') ?: '';
    $dbUser = getenv('DB_USER') ?: '';
    $dbPass = getenv('DB_PASS');

    if ($dbPass === false || $dbPass === '') {
        if (php_sapi_name() === 'cli' || getenv('APP_ENV') === 'development') {
            $dbPass = 'dev_only_password';
        } else {
            throw new RuntimeException('DB_PASS env variable is required. Check ../.env file.');
        }
    }

    // Fallback to SQLite for local development
    if (!getenv('DB_HOST') && file_exists(__DIR__ . '/../database/dev_shop.sqlite')) {
        $dsn    = 'sqlite:' . __DIR__ . '/../database/dev_shop.sqlite';
        $dbUser = null;
        $dbPass = null;
    } else {
        $dsn = "mysql:host={$dbHost};dbname={$dbName};charset=utf8mb4";
    }

    $pdoOptions = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];

    try {
        $instance = new PDO($dsn, $dbUser, $dbPass, $pdoOptions);
    } catch (PDOException $e) {
        error_log('Database connection error: ' . $e->getMessage());
        throw new PDOException($e->getMessage(), (int)$e->getCode());
    }

    return $instance;
}

/**
 * Get application configuration array
 */
function dev_app_config(): array
{
    static $config = null;
    if ($config === null) {
        $config = require __DIR__ . '/app.php';
    }
    return $config;
}

/**
 * Log runtime messages to file
 */
function dev_log_runtime(string $message): void
{
    $cfg     = dev_app_config();
    $logFile = $cfg['runtime_log'] ?? (__DIR__ . '/../log/runtime.log');
    $dir     = dirname($logFile);
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    $timestamp = date('Y-m-d H:i:s');
    $logMsg    = "[{$timestamp}] {$message}\n";
    @file_put_contents($logFile, $logMsg, FILE_APPEND);
}

/**
 * Generate or validate CSRF token
 */
function csrf_token(): string
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Validate CSRF token from POST
 */
function validate_csrf_token(): bool
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $token = $_POST['csrf_token'] ?? '';
    if (empty($token) || empty($_SESSION['csrf_token'])) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Validate email address
 */
function validate_email(string $email): bool
{
    $email = trim($email);
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Validate and normalize phone number
 */
function validate_phone(string $phone): ?string
{
    $clean = preg_replace('/\D+/', '', $phone);
    return (strlen($clean) >= 10) ? $clean : null;
}

/**
 * Log security event
 */
function log_security_event(string $event, array $context = []): void
{
    $cfg  = dev_app_config();
    $file = $cfg['security_log'] ?? (__DIR__ . '/../log/security.log');
    $dir  = dirname($file);
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    $msg = sprintf(
        "[%s] [%s] IP=%s Context=%s\n",
        date('Y-m-d H:i:s'),
        $event,
        $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        json_encode($context, JSON_UNESCAPED_UNICODE)
    );
    @file_put_contents($file, $msg, FILE_APPEND);
}

/**
 * Alias for log_security_event (legacy naming)
 */
function dev_log_security(string $event, array $context = []): void
{
    log_security_event($event, $context);
}

/**
 * Check rate limit for action.
 * Returns true  = дозволено (ліміт не перевищено).
 * Returns false = заблоковано (ліміт перевищено).
 */
function check_rate_limit(string $key, int $limit = 5, int $window = 60): bool
{
    $cfg        = dev_app_config();
    $dir        = dirname($cfg['runtime_log'] ?? (__DIR__ . '/../log/runtime.log'));

    // Створюємо папку якщо не існує
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }

    $cache_file = $dir . '/ratelimit_' . md5($key) . '.json';
    $now        = time();

    $fp = fopen($cache_file, 'c+');
    if (!$fp) {
        // Якщо файл недоступний — не блокуємо (fail-open):
        // помилка зберігання не повинна замінювати легітимний запит.
        error_log("Rate limit storage unavailable for key: $key");
        return true;
    }
    flock($fp, LOCK_EX);

    $content = stream_get_contents($fp);
    $data    = json_decode($content, true) ?? [];

    $window_start        = $now - $window;
    $data['timestamps']  = array_filter(
        $data['timestamps'] ?? [],
        fn($ts) => $ts > $window_start
    );

    $allowed = true;
    if (count($data['timestamps']) >= $limit) {
        $allowed = false;
    } else {
        $data['timestamps'][] = $now;
        ftruncate($fp, 0);
        rewind($fp);
        fwrite($fp, json_encode($data));
    }

    flock($fp, LOCK_UN);
    fclose($fp);

    return $allowed;
}

/**
 * Safe HTML output (escape for display)
 */
function esc_html(string $text): string
{
    return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
}

/**
 * Зберегти UTM-параметри з GET у куки (7 днів)
 */
function save_utm_cookies(): void
{
    if (!isset($_GET['utm_source'])) return;
    $cookieTime = time() + 60 * 60 * 24 * 7;
    setcookie('utm_source',   (string)($_GET['utm_source']   ?? ''), $cookieTime, '/');
    setcookie('utm_medium',   (string)($_GET['utm_medium']   ?? ''), $cookieTime, '/');
    setcookie('utm_campaign', (string)($_GET['utm_campaign'] ?? ''), $cookieTime, '/');
    setcookie('utm_content',  (string)($_GET['utm_content']  ?? ''), $cookieTime, '/');
}

/**
 * Запис у лог-файл (тижневі файли)
 */
if (!function_exists('p2log')) {
    function p2log($data, string $key = 'main'): void
    {
        $cfg    = function_exists('dev_app_config') ? dev_app_config() : [];
        $logDir = dirname($cfg['runtime_log'] ?? (__DIR__ . '/../log/runtime.log'));

        if (!is_dir($logDir)) {
            @mkdir($logDir, 0755, true);
        }

        $start = (new DateTime())->modify('monday this week');
        $end   = (new DateTime())->modify('sunday this week');
        $week  = $start->format('d.m') . '-' . $end->format('d.m.y');

        $file  = $logDir . '/' . $key . '_' . $week . '.log';
        $dump  = '[' . date('Y-m-d H:i:s') . "]\n"
            . (is_array($data) || is_object($data) ? print_r($data, true) : (string)$data)
            . "\n\n";
        @file_put_contents($file, $dump, FILE_APPEND);
    }
}

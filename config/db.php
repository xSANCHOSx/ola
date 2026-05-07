<?php

// Database configuration for MariaDB (production)
$dbHost = getenv('DB_HOST') ?: 'localhost';
$dbName = getenv('DB_NAME') ?: 'olap_san'; // Default for development
$dbUser = getenv('DB_USER') ?: 'olap_adm'; // Default for development
$dbPass = getenv('DB_PASS') ?: 'dI3wW1tT3d'; // Default for development

// Fallback to SQLite for local development if DB_HOST is not set and a SQLite file exists
if (!getenv('DB_HOST') && file_exists(__DIR__ . '/../database/dev_shop.sqlite')) {
    $dsn = 'sqlite:' . __DIR__ . '/../database/dev_shop.sqlite';
    $dbUser = null;
    $dbPass = null;
} else {
    $dsn = "mysql:host=$dbHost;dbname=$dbName;charset=utf8mb4";
}

$pdoOptions = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $dbUser, $dbPass, $pdoOptions);
} catch (PDOException $e) {
    error_log('Database connection error: ' . $e->getMessage());
    // In a real application, you might want to show a user-friendly error page or message.
    // For now, we'll just re-throw or handle gracefully.
    throw new PDOException($e->getMessage(), (int)$e->getCode());
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
 * Get global PDO connection instance
 */
function dev_db_connection(): ?PDO
{
    global $pdo;
    return $pdo instanceof PDO ? $pdo : null;
}

/**
 * Log runtime messages to file
 */
function dev_log_runtime(string $message): void
{
    $cfg = dev_app_config();
    $logFile = $cfg['runtime_log'] ?? (__DIR__ . '/../log/runtime.log');
    $dir = dirname($logFile);
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    $timestamp = date('Y-m-d H:i:s');
    $logMsg = "[{$timestamp}] {$message}\n";
    @file_put_contents($logFile, $logMsg, FILE_APPEND);
}

/**
 * Generate or validate CSRF token
 */
function csrf_token(): string
{
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
    $cfg = dev_app_config();
    $file = $cfg['security_log'] ?? (__DIR__ . '/../log/security.log');
    $dir = dirname($file);
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
 * Check rate limit for action
 */
function check_rate_limit(string $key, int $limit = 5, int $window = 60): bool
{
    $cfg = dev_app_config();
    $dir = dirname($cfg['runtime_log'] ?? (__DIR__ . '/../log/runtime.log'));
    $cache_file = $dir . '/ratelimit_' . md5($key) . '.json';
    $now = time();

    $data = [];
    if (file_exists($cache_file)) {
        $data = json_decode((string)file_get_contents($cache_file), true) ?? [];
    }

    $window_start = $now - $window;
    $data['timestamps'] = array_filter(
        $data['timestamps'] ?? [],
        fn($ts) => $ts > $window_start
    );

    if (count($data['timestamps']) >= $limit) {
        return false;
    }

    $data['timestamps'][] = $now;
    @file_put_contents($cache_file, json_encode($data));

    return true;
}

/**
 * Safe HTML output (escape for display)
 */
function esc_html(string $text): string
{
    return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
}

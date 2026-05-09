<?php

declare(strict_types=1);

/**
 * Get global PDO connection instance (Singleton)
 */
function dev_db_connection(): PDO
{
    static $instance = null;
    if ($instance !== null) {
        return $instance;
    }

    $dbHost = getenv('DB_HOST') ?: 'localhost';
    $dbName = getenv('DB_NAME') ?: 'olap_san';
    $dbUser = getenv('DB_USER') ?: 'olap_adm';
    $dbPass = getenv('DB_PASS');

    if ($dbPass === false || $dbPass === '') {
        if (php_sapi_name() === 'cli' || getenv('APP_ENV') === 'development') {
            $dbPass = 'dev_only_password';
        } else {
            throw new RuntimeException('DB_PASS env variable is required');
        }
    }

    // Fallback to SQLite for local development
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

    $fp = fopen($cache_file, 'c+');
    if (!$fp) return true;
    flock($fp, LOCK_EX);

    $content = stream_get_contents($fp);
    $data = json_decode($content, true) ?? [];

    $window_start = $now - $window;
    $data['timestamps'] = array_filter(
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
 * Log security events to security.log
 */
function dev_log_security_event(string $event, array $data = []): void
{
    $logFile = __DIR__ . '/../log/security.log';
    $logDir = dirname($logFile);
    
    // Create log directory if not exists
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0755, true);
    }
    
    $timestamp = date('Y-m-d H:i:s');
    $dataJson = json_encode($data, JSON_UNESCAPED_UNICODE);
    $logEntry = "[$timestamp] $event: $dataJson\n";
    
    @file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
}

/**
 * Ensure coupons table exists (auto-create if missing)
 */
function ensure_coupons_table(PDO $pdo): bool
{
    try {
        $stmt = $pdo->query("SELECT 1 FROM coupons LIMIT 1");
        return true; // Table exists
    } catch (PDOException $e) {
        // Table doesn't exist, create it
        try {
            $pdo->exec('
                CREATE TABLE IF NOT EXISTS coupons (
                    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    code VARCHAR(50) NOT NULL UNIQUE,
                    name VARCHAR(255) NOT NULL,
                    discount_type ENUM("fixed", "percent") NOT NULL DEFAULT "fixed",
                    discount_value DECIMAL(10, 2) NOT NULL,
                    min_order_sum DECIMAL(12, 2) NOT NULL DEFAULT 0,
                    valid_from DATETIME DEFAULT NULL,
                    valid_to DATETIME DEFAULT NULL,
                    max_usage_count INT UNSIGNED DEFAULT NULL,
                    used_count INT UNSIGNED NOT NULL DEFAULT 0,
                    is_active TINYINT(1) NOT NULL DEFAULT 1,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    KEY idx_coupons_code (code),
                    KEY idx_coupons_is_active (is_active),
                    KEY idx_coupons_valid_to (valid_to),
                    KEY idx_coupons_valid_from (valid_from)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ');
            
            $pdo->exec('
                CREATE TABLE IF NOT EXISTS coupon_usage (
                    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    coupon_id INT UNSIGNED NOT NULL,
                    order_id BIGINT UNSIGNED NOT NULL,
                    customer_id BIGINT UNSIGNED DEFAULT NULL,
                    discount_amount DECIMAL(12, 2) NOT NULL,
                    used_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    KEY idx_coupon_usage_coupon_id (coupon_id),
                    KEY idx_coupon_usage_order_id (order_id),
                    KEY idx_coupon_usage_used_at (used_at)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ');
            
            // Insert default coupons
            $pdo->exec('
                INSERT IGNORE INTO coupons (code, name, discount_type, discount_value, min_order_sum, valid_to, is_active) VALUES
                ("OLA5600", "Базова знижка на Олаплекс", "fixed", 5600.00, 0, DATE_ADD(NOW(), INTERVAL 30 DAY), 1),
                ("SUMMER20", "Літня акція 20%", "percent", 20.00, 5000.00, DATE_ADD(NOW(), INTERVAL 90 DAY), 1)
            ');
            
            return true;
        } catch (PDOException $createError) {
            error_log('Failed to create coupons table: ' . $createError->getMessage());
            return false;
        }
    }
}

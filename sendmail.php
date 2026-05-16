<?php

declare(strict_types=1);

session_start();

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/rest.php';

require_once __DIR__ . '/app/Service/OlaLogger.php';
require_once __DIR__ . '/app/View/EmailView.php';
require_once __DIR__ . '/app/Service/OrderNumberService.php';
require_once __DIR__ . '/app/Service/PriceService.php';
require_once __DIR__ . '/app/Service/NotificationService.php';
require_once __DIR__ . '/app/Model/CustomerModel.php';
require_once __DIR__ . '/app/Model/OrderModel.php';
require_once __DIR__ . '/app/Controller/OrderController.php';
require_once __DIR__ . '/config/coupons.php';
require_once __DIR__ . '/config/coupons_optimized.php'; // BUG-09 fix: атомарні функції купонів

OlaLogger::info('---- SENDMAIL START ----', [
    'ip'     => $_SERVER['REMOTE_ADDR']    ?? '?',
    'method' => $_SERVER['REQUEST_METHOD'] ?? '?',
    'ua'     => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 80),
]);

// ── Метод ─────────────────────────────────────────────────────────────────────

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    OlaLogger::warn('NOT_POST', ['method' => $_SERVER['REQUEST_METHOD'] ?? '?']);
    http_response_code(405);
    echo 'Method Not Allowed';
    exit;
}

// ── CSRF ──────────────────────────────────────────────────────────────────────

if (!validate_csrf_token()) {
    OlaLogger::error('CSRF_FAIL', [
        'token_post'    => $_POST['csrf_token']    ?? 'missing',
        'token_session' => $_SESSION['csrf_token'] ?? 'missing',
    ]);
    log_security_event('CSRF_ATTEMPT', ['endpoint' => 'sendmail.php']);
    http_response_code(403);
    echo json_encode(['error' => 'Security check failed']);
    exit;
}
OlaLogger::debug('CSRF_OK');

// ── Rate limit ────────────────────────────────────────────────────────────────

$cfg          = dev_app_config();
$rateLimitKey = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

if (!check_rate_limit($rateLimitKey, $cfg['rate_limit_max_requests'] ?? 5, $cfg['rate_limit_window'] ?? 60)) {
    OlaLogger::warn('RATE_LIMIT', ['ip' => $rateLimitKey]);
    log_security_event('RATE_LIMIT_EXCEEDED', ['ip' => $rateLimitKey]);
    http_response_code(429);
    echo json_encode(['error' => 'Too many requests']);
    exit;
}

// ── Input ─────────────────────────────────────────────────────────────────────

$payload = [
    'name'             => trim((string) ($_POST['name']             ?? '')),
    'email'            => trim((string) ($_POST['email']            ?? '')),
    'phone'            => trim((string) ($_POST['phone']            ?? '')),
    'contact_username' => trim((string) ($_POST['contact_username'] ?? '')),
    'contact_method'   => trim((string) ($_POST['contact_method']   ?? '')),
    'comments'         => trim((string) ($_POST['comments']         ?? '')),
    'coupon'           => trim((string) ($_POST['coupon']           ?? '')),
    'id_product'       => trim((string) ($_POST['id_product']       ?? '')),
    'client_order_uuid'=> trim((string) ($_POST['client_order_uuid']?? '')),
];

$orderResultRaw = (string) ($_POST['order_result'] ?? '');
$orderResult    = json_decode($orderResultRaw, true);
$jsonErr        = json_last_error();

OlaLogger::debug('INPUT_PARSED', [
    'name'             => $payload['name'],
    'email'            => $payload['email'],
    'phone'            => $payload['phone'],
    'contact_method'   => $payload['contact_method'],
    'coupon'           => $payload['coupon'] ?: '(none)',
    'client_order_uuid'=> $payload['client_order_uuid'] ?: '(none)',
    'order_result_raw' => substr($orderResultRaw, 0, 300),
    'json_error'       => $jsonErr === JSON_ERROR_NONE ? 'none' : json_last_error_msg(),
    'items_count'      => is_array($orderResult) ? count($orderResult) : 'not_array',
]);

if (!is_array($orderResult)) {
    OlaLogger::error('ORDER_RESULT_NOT_ARRAY', ['raw' => substr($orderResultRaw, 0, 500)]);
    $orderResult = [];
}

// ── Validation ────────────────────────────────────────────────────────────────

if (empty($payload['name'])) {
    OlaLogger::error('VALIDATION_FAIL', ['reason' => 'empty_name']);
    log_security_event('INVALID_ORDER', ['reason' => 'empty_name']);
    echo json_encode(['error' => 'Имя требуется']);
    exit(1);
}

if (!validate_email($payload['email'])) {
    OlaLogger::error('VALIDATION_FAIL', ['reason' => 'invalid_email', 'value' => $payload['email']]);
    log_security_event('INVALID_EMAIL', ['email' => $payload['email']]);
    echo json_encode(['error' => 'Неверный email']);
    exit(1);
}

if (!validate_phone($payload['phone'])) {
    OlaLogger::error('VALIDATION_FAIL', ['reason' => 'invalid_phone', 'value' => $payload['phone']]);
    log_security_event('INVALID_PHONE', ['phone' => $payload['phone']]);
    echo json_encode(['error' => 'Неверный номер телефона']);
    exit(1);
}

if (empty($orderResult)) {
    OlaLogger::error('VALIDATION_FAIL', ['reason' => 'empty_cart', 'raw_preview' => substr($orderResultRaw, 0, 200)]);
    log_security_event('EMPTY_CART', ['ip' => $_SERVER['REMOTE_ADDR']]);
    echo json_encode(['error' => 'Корзина пуста']);
    exit(1);
}

OlaLogger::info('VALIDATION_OK', ['items' => count($orderResult)]);

// ── DB connect ────────────────────────────────────────────────────────────────

try {
    $pdo = dev_db_connection();
    OlaLogger::info('DB_CONNECT', ['status' => $pdo instanceof PDO ? 'ok' : 'null/false']);
} catch (Throwable $e) {
    OlaLogger::error('DB_CONNECT_FAIL', ['msg' => $e->getMessage()]);
    $pdo = null;
}

// ── Dispatch ──────────────────────────────────────────────────────────────────

$controller = new OrderController($pdo, $cfg);
$result     = $controller->handle($payload, $orderResult);

OlaLogger::info('SENDMAIL_DONE', ['success' => $result['success']]);

if (!$result['success']) {
    http_response_code(500);
}

echo $result['success'] ? 'ok' : 'error';

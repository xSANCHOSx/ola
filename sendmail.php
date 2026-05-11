<?php

declare(strict_types=1);

session_start();

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/rest.php';

require_once __DIR__ . '/app/View/EmailView.php';
require_once __DIR__ . '/app/Service/OrderNumberService.php';
require_once __DIR__ . '/app/Service/PriceService.php';
require_once __DIR__ . '/app/Service/NotificationService.php';
require_once __DIR__ . '/app/Model/CustomerModel.php';
require_once __DIR__ . '/app/Model/OrderModel.php';
require_once __DIR__ . '/app/Controller/OrderController.php';

// ── Метод ────────────────────────────────────────────────────────────────────

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo 'Method Not Allowed';
    exit;
}

// ── CSRF ─────────────────────────────────────────────────────────────────────

if (!validate_csrf_token()) {
    log_security_event('CSRF_ATTEMPT', ['endpoint' => 'sendmail.php']);
    http_response_code(403);
    echo json_encode(['error' => 'Security check failed']);
    exit;
}

// ── Rate limit ────────────────────────────────────────────────────────────────

$cfg = dev_app_config();
$rateLimitKey = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

if (!check_rate_limit($rateLimitKey, $cfg['rate_limit_max_requests'] ?? 5, $cfg['rate_limit_window'] ?? 60)) {
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

$orderResult = json_decode((string) ($_POST['order_result'] ?? '[]'), true);
if (!is_array($orderResult)) {
    $orderResult = [];
}

// ── Validation ────────────────────────────────────────────────────────────────

if (empty($payload['name'])) {
    log_security_event('INVALID_ORDER', ['reason' => 'empty_name']);
    echo json_encode(['error' => 'Имя требуется']);
    exit(1);
}

if (!validate_email($payload['email'])) {
    log_security_event('INVALID_EMAIL', ['email' => $payload['email']]);
    echo json_encode(['error' => 'Неверный email']);
    exit(1);
}

if (!validate_phone($payload['phone'])) {
    log_security_event('INVALID_PHONE', ['phone' => $payload['phone']]);
    echo json_encode(['error' => 'Неверный номер телефона']);
    exit(1);
}

if (empty($orderResult)) {
    log_security_event('EMPTY_CART', ['ip' => $_SERVER['REMOTE_ADDR']]);
    echo json_encode(['error' => 'Корзина пуста']);
    exit(1);
}

// ── Dispatch ──────────────────────────────────────────────────────────────────

$pdo = dev_db_connection();

$controller = new OrderController($pdo, $cfg);
$result     = $controller->handle($payload, $orderResult);

if (!$result['success']) {
    http_response_code(500);
}

echo $result['success'] ? 'ok' : 'error';

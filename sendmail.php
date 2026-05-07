<?php

declare(strict_types=1);

session_start();

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/rest.php';

function normalize_phone(string $phone): string
{
    return preg_replace('/\D+/', '', $phone) ?? '';
}

function next_order_number_fallback(string $counterFile): int
{
    $counter = 0;
    if (file_exists($counterFile)) {
        $raw = trim((string)@file_get_contents($counterFile));
        $counter = ctype_digit($raw) ? (int)$raw : 0;
    }
    $counter++;
    @file_put_contents($counterFile, (string)$counter);
    return $counter;
}

function next_order_number_db(PDO $pdo, string $counterFile): int
{
    $pdo->beginTransaction();
    try {
        $row = $pdo->query('SELECT current_value FROM order_sequence WHERE id = 1 FOR UPDATE')->fetch();
        if (!$row) {
            $seed = 0;
            if (file_exists($counterFile)) {
                $seedRaw = trim((string)@file_get_contents($counterFile));
                $seed = ctype_digit($seedRaw) ? (int)$seedRaw : 0;
            }
            $stmt = $pdo->prepare('INSERT INTO order_sequence (id, current_value) VALUES (1, :seed)');
            $stmt->execute(['seed' => $seed]);
            $current = $seed;
        } else {
            $current = (int)$row['current_value'];
        }
        $next = $current + 1;
        $stmt = $pdo->prepare('UPDATE order_sequence SET current_value = :next WHERE id = 1');
        $stmt->execute(['next' => $next]);
        $pdo->commit();
        return $next;
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

function upsert_customer(PDO $pdo, array $payload, int $orderNumber, float $total): ?int
{
    $phoneNorm = normalize_phone((string)$payload['phone']);
    $emailNorm = mb_strtolower(trim((string)$payload['email']));
    $customer = null;
    if ($phoneNorm !== '') {
        $stmt = $pdo->prepare('SELECT * FROM customers WHERE phone_normalized = :phone LIMIT 1');
        $stmt->execute(['phone' => $phoneNorm]);
        $customer = $stmt->fetch();
    }
    if (!$customer && $emailNorm !== '') {
        $stmt = $pdo->prepare('SELECT * FROM customers WHERE email_normalized = :email LIMIT 1');
        $stmt->execute(['email' => $emailNorm]);
        $customer = $stmt->fetch();
    }

    if (!$customer) {
        $stmt = $pdo->prepare('INSERT INTO customers (full_name, email, phone, contact_method, contact_username, phone_normalized, email_normalized, first_order_number, last_order_number, orders_count, total_spent, last_order_at) VALUES (:full_name, :email, :phone, :contact_method, :contact_username, :phone_norm, :email_norm, :order_no, :order_no, 1, :total_spent, NOW())');
        $stmt->execute([
            'full_name' => $payload['name'],
            'email' => $payload['email'],
            'phone' => $payload['phone'],
            'contact_method' => $payload['contact_method'],
            'contact_username' => $payload['contact_username'],
            'phone_norm' => $phoneNorm,
            'email_norm' => $emailNorm,
            'order_no' => $orderNumber,
            'total_spent' => $total,
        ]);
        return (int)$pdo->lastInsertId();
    }

    $stmt = $pdo->prepare('UPDATE customers SET full_name = :full_name, email = :email, phone = :phone, contact_method = :contact_method, contact_username = :contact_username, phone_normalized = :phone_norm, email_normalized = :email_norm, last_order_number = :order_no, orders_count = orders_count + 1, total_spent = total_spent + :total_spent, last_order_at = NOW() WHERE id = :id');
    $stmt->execute([
        'full_name' => $payload['name'],
        'email' => $payload['email'],
        'phone' => $payload['phone'],
        'contact_method' => $payload['contact_method'],
        'contact_username' => $payload['contact_username'],
        'phone_norm' => $phoneNorm,
        'email_norm' => $emailNorm,
        'order_no' => $orderNumber,
        'total_spent' => $total,
        'id' => $customer['id'],
    ]);
    return (int)$customer['id'];
}

$cfg = dev_app_config();
$counterFile = $cfg['fallback_counter_file'] ?? (__DIR__ . '/counter.txt');

// === SECURITY CHECKS ===
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo 'Method Not Allowed';
    exit;
}

// Validate CSRF token
if (!validate_csrf_token()) {
    log_security_event('CSRF_ATTEMPT', ['endpoint' => 'sendmail.php']);
    http_response_code(403);
    echo json_encode(['error' => 'Security check failed']);
    exit;
}

// Check rate limit
$rate_limit_key = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
if (!check_rate_limit($rate_limit_key, $cfg['rate_limit_max_requests'] ?? 5, $cfg['rate_limit_window'] ?? 60)) {
    log_security_event('RATE_LIMIT_EXCEEDED', ['ip' => $rate_limit_key]);
    http_response_code(429);
    echo json_encode(['error' => 'Too many requests']);
    exit;
}

// === VALIDATE INPUT ===
$payload = [
    'name' => trim((string)($_POST['name'] ?? '')),
    'email' => trim((string)($_POST['email'] ?? '')),
    'phone' => trim((string)($_POST['phone'] ?? '')),
    'contact_username' => trim((string)($_POST['contact_username'] ?? '')),
    'contact_method' => trim((string)($_POST['contact_method'] ?? '')),
    'comments' => trim((string)($_POST['comments'] ?? '')),
    'coupon' => trim((string)($_POST['coupon'] ?? '')),
    'id_product' => trim((string)($_POST['id_product'] ?? '')),
    'client_order_uuid' => trim((string)($_POST['client_order_uuid'] ?? '')),
];

$orderResult = json_decode((string)($_POST['order_result'] ?? '[]'), true);
if (!is_array($orderResult)) {
    $orderResult = [];
}

// === VALIDATE REQUIRED FIELDS ===
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

$phone = validate_phone($payload['phone']);
if (!$phone) {
    log_security_event('INVALID_PHONE', ['phone' => $payload['phone']]);
    echo json_encode(['error' => 'Неверный номер телефона']);
    exit(1);
}

if (empty($orderResult)) {
    log_security_event('EMPTY_CART', ['ip' => $_SERVER['REMOTE_ADDR']]);
    echo json_encode(['error' => 'Корзина пуста']);
    exit(1);
}
// ═══ Верифікація цін з БД ═══════════════════════════════════════════════════
$totalSum = 0.0;
$pdo = dev_db_connection();
if ($pdo instanceof PDO && !empty($orderResult)) {
    $productIds  = array_unique(array_column($orderResult, 'id'));
    $placeholders = implode(',', array_fill(0, count($productIds), '?'));
    $stmt = $pdo->prepare(
        "SELECT external_id, price FROM products WHERE external_id IN ($placeholders)"
    );
    $stmt->execute(array_values($productIds));
    $dbPrices = array_column($stmt->fetchAll(), 'price', 'external_id');

    foreach ($orderResult as $item) {
        $pid = (string)($item['id'] ?? '');
        if (!isset($dbPrices[$pid])) {
            log_security_event('UNKNOWN_PRODUCT', ['id' => $pid]);
            http_response_code(400);
            echo json_encode(['error' => 'Unknown product: ' . $pid]);
            exit;
        }
        $totalSum += (float)$dbPrices[$pid] * (int)($item['num'] ?? 0);
    }
} else {
    // Fallback якщо БД недоступна
    foreach ($orderResult as $item) {
        $totalSum += ((float)($item['price'] ?? 0) * (int)($item['num'] ?? 0));
    }
}

// ═══ Верифікація купону на сервері ═══════════════════════════════════════════
$cfg     = dev_app_config();
$coupons = $cfg['coupons'] ?? [];
$couponCode = $payload['coupon'] ?? '';
if ($couponCode !== '' && isset($coupons[$couponCode])) {
    $discount = $coupons[$couponCode]['discount'];
    $totalSum = max(0, $totalSum - $discount);
}

$orderNumber = null;
$pdo = dev_db_connection();
$dbOrderId = null;
$dbSaved = false;
if ($pdo instanceof PDO) {
    try {
        $orderNumber = next_order_number_db($pdo, $counterFile);
    } catch (Throwable $e) {
        dev_log_runtime('Order number from DB failed: ' . $e->getMessage());
    }
}
if ($orderNumber === null) {
    $orderNumber = next_order_number_fallback($counterFile);
}

$_POST['ORDER_ID'] = $orderNumber;
$_SESSION['order_id'] = $orderNumber;
$subject = 'Заказ с сайта Olaplex #OLA-' . $orderNumber . ' (' . date('d.m.Y H:i') . ')';

if ($pdo instanceof PDO) {
    try {
        $pdo->beginTransaction();
        $idempotency = $payload['client_order_uuid'] !== '' ? $payload['client_order_uuid'] : null;
        if ($idempotency) {
            $stmt = $pdo->prepare('SELECT id, order_number FROM orders WHERE idempotency_key = :key LIMIT 1');
            $stmt->execute(['key' => $idempotency]);
            $existing = $stmt->fetch();
            if ($existing) {
                $pdo->rollBack();
                echo 'ok';
                exit;
            }
        }
        $customerId = upsert_customer($pdo, $payload, $orderNumber, $totalSum);
        $stmt = $pdo->prepare('INSERT INTO orders (order_number, customer_id, customer_name_snapshot, customer_email_snapshot, customer_phone_snapshot, contact_method_snapshot, contact_username_snapshot, delivery_address_snapshot, coupon, total, idempotency_key, raw_payload, created_at) VALUES (:order_number, :customer_id, :name, :email, :phone, :contact_method, :contact_username, :delivery_address, :coupon, :total, :idempotency_key, :raw_payload, NOW())');
        $stmt->execute([
            'order_number' => $orderNumber,
            'customer_id' => $customerId,
            'name' => $payload['name'],
            'email' => $payload['email'],
            'phone' => $payload['phone'],
            'contact_method' => $payload['contact_method'],
            'contact_username' => $payload['contact_username'],
            'delivery_address' => $payload['comments'],
            'coupon' => $payload['coupon'],
            'total' => $totalSum,
            'idempotency_key' => $idempotency,
            'raw_payload' => json_encode($_POST, JSON_UNESCAPED_UNICODE),
        ]);
        $dbOrderId = (int)$pdo->lastInsertId();

        if ($orderResult) {
            $itemStmt = $pdo->prepare('INSERT INTO order_items (order_id, product_external_id, catalog_number, name, price, quantity) VALUES (:order_id, :product_external_id, :catalog_number, :name, :price, :quantity)');
            foreach ($orderResult as $item) {
                $itemStmt->execute([
                    'order_id' => $dbOrderId,
                    'product_external_id' => (string)($item['id'] ?? ''),
                    'catalog_number' => (string)($item['catalogNumber'] ?? '-'),
                    'name' => (string)($item['name'] ?? ''),
                    'price' => (float)($item['price'] ?? 0),
                    'quantity' => (int)($item['num'] ?? 0),
                ]);
            }
        }
        $pdo->commit();
        $dbSaved = true;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        dev_log_runtime('DB order save failed: ' . $e->getMessage());
    }
}

$productTable = '';
foreach ($orderResult as $item) {
    $productTable .= '<tr>'
        . '<td style="padding:8px;border:1px solid #ddd;text-align:center;width:70px;white-space:nowrap;">' . htmlspecialchars((string)($item['catalogNumber'] ?? '-')) . '</td>'
        . '<td style="padding:8px;border:1px solid #ddd;text-align:left;">' . htmlspecialchars((string)($item['name'] ?? '')) . '</td>'
        . '<td style="padding:8px;border:1px solid #ddd;text-align:center;width:90px;white-space:nowrap;">' . htmlspecialchars((string)($item['price'] ?? 0)) . ' руб.</td>'
        . '<td style="padding:8px;border:1px solid #ddd;text-align:center;width:70px;white-space:nowrap;">' . htmlspecialchars((string)($item['num'] ?? 0)) . '</td>'
        . '</tr>';
}

$template = "<html><head><style>*{font-family:Arial,sans-serif;}body{color:#333;line-height:1.6;}h1{color:#ba385c;font-size:22px;}h2{color:#ba385c;font-size:20px;}</style></head><body>"
    . "<h1>{$subject}</h1>"
    . "<table style=\"border-collapse:collapse;margin-top:20px;width:100%;\">"
    . "<tr><td style=\"padding:8px;border:1px solid #ddd;font-weight:bold;width:120px;\">Имя:</td><td style=\"padding:8px;border:1px solid #ddd;\">" . htmlspecialchars($payload['name']) . "</td></tr>"
    . "<tr><td style=\"padding:8px;border:1px solid #ddd;font-weight:bold;\">Email:</td><td style=\"padding:8px;border:1px solid #ddd;\">" . htmlspecialchars($payload['email']) . "</td></tr>"
    . "<tr><td style=\"padding:8px;border:1px solid #ddd;font-weight:bold;\">Телефон:</td><td style=\"padding:8px;border:1px solid #ddd;\">" . htmlspecialchars($payload['phone']) . "</td></tr>"
    . "<tr><td style=\"padding:8px;border:1px solid #ddd;font-weight:bold;\">Мессенджер:</td><td style=\"padding:8px;border:1px solid #ddd;\">" . htmlspecialchars($payload['contact_method']) . "</td></tr>"
    . "<tr><td style=\"padding:8px;border:1px solid #ddd;font-weight:bold;\">Аккаунт:</td><td style=\"padding:8px;border:1px solid #ddd;\">" . htmlspecialchars($payload['contact_username']) . "</td></tr>"
    . "<tr><td style=\"padding:8px;border:1px solid #ddd;font-weight:bold;\">Комментарии:</td><td style=\"padding:8px;border:1px solid #ddd;\">" . htmlspecialchars($payload['comments']) . "</td></tr>"
    . "</table>"
    . "<h2 style=\"margin-top:20px;text-align:center;\">Детали заказа</h2>"
    . "<table style=\"border-collapse:collapse;margin-top:20px;width:100%;\"><thead><tr>"
    . "<th style=\"padding:8px;border:1px solid #ddd;background:#ba385c;color:#fff;\">Код</th>"
    . "<th style=\"padding:8px;border:1px solid #ddd;background:#ba385c;color:#fff;\">Название</th>"
    . "<th style=\"padding:8px;border:1px solid #ddd;background:#ba385c;color:#fff;\">Цена</th>"
    . "<th style=\"padding:8px;border:1px solid #ddd;background:#ba385c;color:#fff;\">Кол-во</th>"
    . "</tr></thead><tbody>{$productTable}</tbody><tfoot><tr style=\"font-weight:bold;background:#f9f9f9;\"><td colspan=\"3\" style=\"padding:8px;border:1px solid #ddd;\">Итого:</td><td style=\"padding:8px;border:1px solid #ddd;\">" . number_format($totalSum, 2, '.', '') . " руб.</td></tr></tfoot></table>"
    . (!empty($payload['coupon']) ? '<p><strong>Купон:</strong> ' . htmlspecialchars($payload['coupon']) . '</p>' : '')
    . '</body></html>';

$domain = 'olaplex.ru';
$from = 'no-reply@' . $domain;
$headers = "From: {$from}\r\nReply-To: {$from}\r\nMIME-Version: 1.0\r\nContent-Type: text/html; charset=UTF-8\r\n";

$success = mail('client@macadamia-shop.ru, client@olaplex-shop.ru', $subject, $template, $headers);
$success2 = mail($payload['email'], $subject, $template, $headers);
$crmSent = dev_send_bitrix_lead($subject, $payload);

$_POST['MAIL_OUR'] = 'Result = ' . ($success ? '1' : '0');
$_POST['MAIL_USER'] = 'Result = ' . ($success2 ? '1' : '0');
$_POST['CRM_SENT'] = $crmSent ? '1' : '0';

if (!function_exists('p2log')) {
    function p2log($arr, $key = ''): void
    {
        $key = $key ?: 'main';
        $dump = print_r($arr, true) . "\r\n";
        $files = $_SERVER['DOCUMENT_ROOT'] . '/log/' . $key . '.log';
        @file_put_contents($files, $dump, FILE_APPEND);
    }
}
p2log($_POST);

require_once __DIR__ . '/amo/order.php';

if ($pdo instanceof PDO && $dbSaved && $dbOrderId) {
    try {
        $stmt = $pdo->prepare('UPDATE orders SET outbound_email_sent = :email_sent, outbound_crm_sent = :crm_sent, outbound_amo_sent = 1 WHERE id = :id');
        $stmt->execute([
            'email_sent' => ($success && $success2) ? 1 : 0,
            'crm_sent' => $crmSent ? 1 : 0,
            'id' => $dbOrderId,
        ]);
    } catch (Throwable $e) {
        dev_log_runtime('Order outbound status update failed: ' . $e->getMessage());
    }
}

echo ($success ? 'ok' : 'error');

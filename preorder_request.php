<?php

declare(strict_types=1);

require_once __DIR__ . '/config/db.php';

header('Content-Type: application/json; charset=utf-8');

function preorder_response(array $payload, int $status = 200): never
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    preorder_response(['success' => false, 'message' => 'Метод не поддерживается.'], 405);
}

if (!validate_csrf_token()) {
    preorder_response(['success' => false, 'message' => 'Сессия формы устарела. Обновите страницу.'], 403);
}

// Honeypot для простого отсечения автоматического спама.
if (trim((string)($_POST['website'] ?? '')) !== '') {
    preorder_response(['success' => true, 'message' => 'Заявка принята.']);
}

$productExternalId = trim((string)($_POST['product_id'] ?? ''));
$name = trim((string)($_POST['name'] ?? ''));
$phone = trim((string)($_POST['phone'] ?? ''));
$email = trim((string)($_POST['email'] ?? ''));

$errors = [];
if ($productExternalId === '' || mb_strlen($productExternalId) > 32) {
    $errors['product_id'] = 'Не удалось определить товар.';
}
if ($name === '' || mb_strlen($name) < 2 || mb_strlen($name) > 255) {
    $errors['name'] = 'Укажите имя.';
}
$normalizedPhone = validate_phone($phone);
if ($normalizedPhone === null) {
    $errors['phone'] = 'Укажите корректный телефон.';
}
if ($email !== '' && !validate_email($email)) {
    $errors['email'] = 'Укажите корректный email.';
}
if ($errors) {
    preorder_response(['success' => false, 'message' => 'Проверьте заполнение формы.', 'errors' => $errors], 422);
}

$clientIp = (string)($_SERVER['REMOTE_ADDR'] ?? 'unknown');
if (!check_rate_limit('preorder-ip:' . hash('sha256', $clientIp), 10, 600)) {
    preorder_response(['success' => false, 'message' => 'Слишком много заявок. Попробуйте позже.'], 429);
}
if (!check_rate_limit('preorder-phone:' . hash('sha256', $normalizedPhone), 3, 3600)) {
    preorder_response(['success' => false, 'message' => 'Для этого телефона уже отправлено много заявок. Попробуйте позже.'], 429);
}

try {
    $pdo = dev_db_connection();
    $productStmt = $pdo->prepare(
        'SELECT id, external_id, name, cat_number
         FROM products
         WHERE external_id = :external_id AND is_visible = 1 AND status = "preorder"
         LIMIT 1'
    );
    $productStmt->execute(['external_id' => $productExternalId]);
    $product = $productStmt->fetch(PDO::FETCH_ASSOC);
    if (!$product) {
        preorder_response(['success' => false, 'message' => 'Товар больше недоступен для заявки.'], 404);
    }

    $duplicateStmt = $pdo->prepare(
        'SELECT id
         FROM preorder_requests
         WHERE product_external_id = :product_external_id
           AND customer_phone = :customer_phone
           AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
         ORDER BY id DESC
         LIMIT 1'
    );
    $duplicateStmt->execute([
        'product_external_id' => (string)$product['external_id'],
        'customer_phone' => $normalizedPhone,
    ]);
    $duplicateId = $duplicateStmt->fetchColumn();
    if ($duplicateId !== false) {
        preorder_response(['success' => false, 'message' => 'Такая заявка уже была отправлена. Мы свяжемся с вами.'], 409);
    }

    $insert = $pdo->prepare(
        'INSERT INTO preorder_requests
            (product_id, product_external_id, product_name_snapshot, product_cat_number_snapshot,
             customer_name, customer_phone, customer_email)
         VALUES
            (:product_id, :product_external_id, :product_name_snapshot, :product_cat_number_snapshot,
             :customer_name, :customer_phone, :customer_email)'
    );
    $insert->execute([
        'product_id' => (int)$product['id'],
        'product_external_id' => (string)$product['external_id'],
        'product_name_snapshot' => (string)$product['name'],
        'product_cat_number_snapshot' => $product['cat_number'] !== null ? (string)$product['cat_number'] : null,
        'customer_name' => $name,
        'customer_phone' => $normalizedPhone,
        'customer_email' => $email !== '' ? $email : null,
    ]);
    $requestId = (int)$pdo->lastInsertId();

    $recipients = trim((string)(dev_app_config()['order_notification_emails'] ?? ''));
    $subject = 'Заявка «Под заказ» #' . $requestId . ' — ' . (string)$product['name'];
    $html = '<h2>Новая заявка «Под заказ»</h2>'
        . '<p><strong>Заявка:</strong> #' . $requestId . '</p>'
        . '<p><strong>Товар:</strong> ' . htmlspecialchars((string)$product['name'], ENT_QUOTES, 'UTF-8') . '</p>'
        . '<p><strong>Артикул:</strong> ' . htmlspecialchars((string)($product['cat_number'] ?? '—'), ENT_QUOTES, 'UTF-8') . '</p>'
        . '<p><strong>Имя:</strong> ' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . '</p>'
        . '<p><strong>Телефон:</strong> ' . htmlspecialchars($normalizedPhone, ENT_QUOTES, 'UTF-8') . '</p>'
        . '<p><strong>Email:</strong> ' . htmlspecialchars($email !== '' ? $email : 'не указан', ENT_QUOTES, 'UTF-8') . '</p>';
    $headers = implode("\r\n", [
        'From: no-reply@' . site_domain(),
        'Reply-To: no-reply@' . site_domain(),
        'MIME-Version: 1.0',
        'Content-Type: text/html; charset=UTF-8',
    ]) . "\r\n";
    $emailSent = false;
    if ($recipients !== '') {
        try {
            $emailSent = mail($recipients, $subject, $html, $headers);
        } catch (Throwable $e) {
            dev_log_runtime('Preorder email failed: ' . $e->getMessage());
        }
    } else {
        dev_log_runtime('Preorder email skipped: ORDER_NOTIFY_EMAILS is empty.');
    }

    dev_log_runtime('Preorder request created: #' . $requestId . '; email=' . ($emailSent ? '1' : '0'));
    preorder_response(['success' => true, 'message' => 'Заявка отправлена. Мы свяжемся с вами.', 'request_id' => $requestId]);
} catch (Throwable $e) {
    dev_log_runtime('Preorder request failed: ' . $e->getMessage());
    preorder_response(['success' => false, 'message' => 'Не удалось отправить заявку. Попробуйте ещё раз.'], 500);
}

<?php
declare(strict_types=1);

require_once __DIR__ . '/config/db.php';

header('Content-Type: application/json; charset=utf-8');

$respond = static function (array $payload, int $status = 200): never {
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
};

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $respond(['success' => false, 'message' => 'Метод не поддерживается.'], 405);
}
if (!validate_csrf_token()) {
    $respond(['success' => false, 'message' => 'Сессия формы устарела. Обновите страницу.'], 403);
}
if (trim((string)($_POST['website'] ?? '')) !== '') {
    // Не раскрываем автоматике факт срабатывания honeypot.
    $respond(['success' => true, 'message' => 'Заявка принята.']);
}

$length = static function (string $value): int {
    return function_exists('mb_strlen') ? mb_strlen($value) : strlen($value);
};
$productId = (int)($_POST['product_id'] ?? 0);
$name = trim((string)($_POST['name'] ?? ''));
$phone = trim((string)($_POST['phone'] ?? ''));
$email = trim((string)($_POST['email'] ?? ''));
$errors = [];
if ($productId <= 0) $errors['product_id'] = 'Не удалось определить товар.';
if ($name === '' || $length($name) < 2 || $length($name) > 255) $errors['name'] = 'Укажите имя.';
$normalizedPhone = validate_phone($phone);
if ($normalizedPhone === null) $errors['phone'] = 'Укажите корректный телефон.';
if ($email !== '' && !validate_email($email)) $errors['email'] = 'Укажите корректный email.';
if ($errors) {
    $respond(['success' => false, 'message' => 'Проверьте заполнение формы.', 'errors' => $errors], 422);
}

try {
    $pdo = dev_db_connection();
    $productStmt = $pdo->prepare(
        'SELECT id, external_id, name, cat_number
         FROM products
         WHERE id = :id AND is_visible = 1 AND status = :status
         LIMIT 1'
    );
    $productStmt->execute(['id' => $productId, 'status' => 'preorder']);
    $product = $productStmt->fetch(PDO::FETCH_ASSOC);
    if (!$product) {
        $respond(['success' => false, 'message' => 'Товар больше недоступен для заявки.'], 404);
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
    if ($recipients !== '') {
        try {
            mail($recipients, $subject, $html, $headers);
        } catch (Throwable $e) {
            dev_log_runtime('Preorder email failed: ' . $e->getMessage());
        }
    } else {
        dev_log_runtime('Preorder email skipped: ORDER_NOTIFY_EMAILS is empty.');
    }
    dev_log_runtime('Preorder request created: #' . $requestId);
    $respond(['success' => true, 'message' => 'Заявка отправлена. Мы свяжемся с вами.', 'request_id' => $requestId]);
} catch (Throwable $e) {
    dev_log_runtime('Preorder request failed: ' . $e->getMessage());
    $respond(['success' => false, 'message' => 'Не удалось отправить заявку. Попробуйте ещё раз.'], 500);
}

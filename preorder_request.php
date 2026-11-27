<?php

declare(strict_types=1);

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/app/DTO/PreorderRequestData.php';
require_once __DIR__ . '/app/Service/PreorderRequestService.php';

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
$length = static function (string $value): int {
    if (function_exists('mb_strlen')) {
        return mb_strlen($value, 'UTF-8');
    }
    $count = preg_match_all('/./us', $value, $characters);
    return $count === false ? strlen($value) : $count;
};
if ($productExternalId === '' || $length($productExternalId) > 32) {
    $errors['product_id'] = 'Не удалось определить товар.';
}
if ($name === '' || $length($name) < 2 || $length($name) > 255) {
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
    $service = new PreorderRequestService();
    $result = $service->create(
        dev_db_connection(),
        new PreorderRequestData($productExternalId, $name, $normalizedPhone, $email)
    );
    preorder_response([
        'success' => true,
        'message' => 'Заявка отправлена. Мы свяжемся с вами.',
        'request_id' => $result['request_id'],
    ]);
} catch (PreorderRequestException $e) {
    preorder_response(['success' => false, 'message' => $e->getMessage()], $e->httpStatus());
} catch (Throwable $e) {
    dev_log_runtime('Preorder request failed: ' . $e->getMessage());
    preorder_response(['success' => false, 'message' => 'Не удалось отправить заявку. Попробуйте ещё раз.'], 500);
}

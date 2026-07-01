<?php

declare(strict_types=1);
require __DIR__ . '/../_bootstrap.php';
admin_require_auth();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit(json_encode(['error' => 'Method not allowed']));
}

$payload = json_decode(file_get_contents('php://input'), true);
if (!is_array($payload)) {
    http_response_code(400);
    exit(json_encode(['error' => 'Invalid JSON']));
}

// CSRF: validate_csrf_token() читает из $_POST, поэтому проверяем вручную
$csrfToken = (string)($payload['csrf_token'] ?? '');
$sessionToken = (string)($_SESSION['csrf_token'] ?? '');
if (empty($csrfToken) || empty($sessionToken) || !hash_equals($sessionToken, $csrfToken)) {
    http_response_code(403);
    exit(json_encode(['error' => 'CSRF check failed']));
}

// $payload['order'] = [ ['id' => 12, 'sort_order' => 0], ['id' => 5, 'sort_order' => 1], ... ]
$order = $payload['order'] ?? [];
if (!is_array($order) || count($order) === 0) {
    http_response_code(400);
    exit(json_encode(['error' => 'Empty order']));
}

// Санитация: id и sort_order — только целые числа > 0
foreach ($order as $item) {
    if (!isset($item['id'], $item['sort_order'])
        || (int)$item['id'] <= 0
        || (int)$item['sort_order'] < 0
    ) {
        http_response_code(422);
        exit(json_encode(['error' => 'Invalid item in order array']));
    }
}

$pdo = dev_db_connection();
if (!$pdo instanceof PDO) {
    http_response_code(500);
    exit(json_encode(['error' => 'DB unavailable']));
}

$stmt = $pdo->prepare('UPDATE products SET sort_order = :sort_order WHERE id = :id');

$pdo->beginTransaction();
try {
    foreach ($order as $item) {
        $stmt->execute([
            'sort_order' => (int)$item['sort_order'],
            'id'         => (int)$item['id'],
        ]);
    }
    $pdo->commit();
    echo json_encode(['success' => true]);
} catch (Throwable $e) {
    $pdo->rollBack();
    error_log('Reorder products error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Database error']);
}

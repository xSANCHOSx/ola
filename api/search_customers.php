<?php

declare(strict_types=1);

session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../admin/_bootstrap.php';

if (!admin_is_auth()) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$searchType  = (string)($_GET['type']  ?? '');
$searchValue = (string)($_GET['value'] ?? '');
$limit       = min((int)($_GET['limit']  ?? 200), 500);
$offset      = max((int)($_GET['offset'] ?? 0),   0);

$pdo = dev_db_connection();

function build_customers_where(string $type, string $value): array
{
    $where  = ' WHERE 1=1';
    $params = [];

    $map = [
        'name'  => 'c.full_name',
        'phone' => 'c.phone',
        'email' => 'c.email',
    ];

    if (isset($map[$type]) && $value !== '') {
        $where .= " AND {$map[$type]} LIKE :$type";
        $params[$type] = '%' . $value . '%';
    }

    if ($type === 'general' && $value !== '') {
        $where .= ' AND (c.full_name LIKE :g OR c.phone LIKE :g OR c.email LIKE :g)';
        $params['g'] = '%' . $value . '%';
    }

    return ['where' => $where, 'params' => $params];
}

try {
    ['where' => $where, 'params' => $params] = build_customers_where($searchType, $searchValue);

    $countStmt = $pdo->prepare('SELECT COUNT(*) as total FROM customers c' . $where);
    foreach ($params as $k => $v) { $countStmt->bindValue(':' . $k, $v); }
    $countStmt->execute();
    $totalCount = (int)$countStmt->fetch()['total'];

    $stmt = $pdo->prepare('SELECT * FROM customers c' . $where . ' ORDER BY last_order_at DESC, id DESC LIMIT :limit OFFSET :offset');
    foreach ($params as $k => $v) { $stmt->bindValue(':' . $k, $v); }
    $stmt->bindValue(':limit',  $limit,  PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();

    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => true, 'data' => $stmt->fetchAll(), 'total' => $totalCount, 'limit' => $limit, 'offset' => $offset], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    error_log('Search customers error: ' . $e->getMessage());
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'error' => 'Database error'], JSON_UNESCAPED_UNICODE);
}

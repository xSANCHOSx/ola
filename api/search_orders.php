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

$searchType  = (string)($_GET['type']      ?? '');
$searchValue = (string)($_GET['value']     ?? '');
$dateFrom    = (string)($_GET['date_from'] ?? '');
$dateTo      = (string)($_GET['date_to']   ?? '');
$limit       = min((int)($_GET['limit']  ?? 100), 500);
$offset      = max((int)($_GET['offset'] ?? 0),   0);

$pdo = dev_db_connection();

/**
 * Строит WHERE-условие и параметры один раз — используется и для SELECT и для COUNT.
 */
function build_orders_where(string $type, string $value, string $dateFrom, string $dateTo): array
{
    $where  = ' WHERE 1=1';
    $params = [];

    $map = [
        'order_number'   => 'o.order_number',
        'customer_name'  => 'o.customer_name_snapshot',
        'customer_phone' => 'o.customer_phone_snapshot',
        'customer_email' => 'o.customer_email_snapshot',
    ];

    if (isset($map[$type]) && $value !== '') {
        $where .= " AND {$map[$type]} LIKE :$type";
        $params[$type] = '%' . $value . '%';
    }

    if ($type === 'date_exact' && $value !== '') {
        $where .= ' AND DATE(o.created_at) = :date_exact';
        $params['date_exact'] = $value;
    }

    if ($type === 'date_range') {
        if ($dateFrom !== '') { $where .= ' AND DATE(o.created_at) >= :date_from'; $params['date_from'] = $dateFrom; }
        if ($dateTo   !== '') { $where .= ' AND DATE(o.created_at) <= :date_to';   $params['date_to']   = $dateTo; }
    }

    if ($type === 'general' && $value !== '') {
        $where .= ' AND (o.order_number LIKE :g OR o.customer_name_snapshot LIKE :g OR o.customer_phone_snapshot LIKE :g OR o.customer_email_snapshot LIKE :g)';
        $params['g'] = '%' . $value . '%';
    }

    return ['where' => $where, 'params' => $params];
}

try {
    ['where' => $where, 'params' => $params] = build_orders_where($searchType, $searchValue, $dateFrom, $dateTo);

    // COUNT — те же params
    $countStmt = $pdo->prepare('SELECT COUNT(*) as total FROM orders o' . $where);
    foreach ($params as $k => $v) { $countStmt->bindValue(':' . $k, $v); }
    $countStmt->execute();
    $totalCount = (int)$countStmt->fetch()['total'];

    // SELECT — те же params + limit/offset
    $stmt = $pdo->prepare('
        SELECT o.id, o.order_number, o.customer_name_snapshot, o.customer_phone_snapshot,
               o.customer_email_snapshot, o.total, o.coupon, o.coupon_discount_amount,
               o.created_at,
               (SELECT COALESCE(SUM(quantity),0) FROM order_items oi WHERE oi.order_id = o.id) AS items_count
        FROM orders o' . $where . ' ORDER BY o.id DESC LIMIT :limit OFFSET :offset');
    foreach ($params as $k => $v) { $stmt->bindValue(':' . $k, $v); }
    $stmt->bindValue(':limit',  $limit,  PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();

    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => true, 'data' => $stmt->fetchAll(), 'total' => $totalCount, 'limit' => $limit, 'offset' => $offset], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    error_log('Search orders error: ' . $e->getMessage());
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'error' => 'Database error'], JSON_UNESCAPED_UNICODE);
}

<?php

declare(strict_types=1);

session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../admin/_bootstrap.php';

// Перевірка автентифікації
if (!admin_is_auth()) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// Отримання параметрів пошуку
$searchType = $_GET['type'] ?? '';
$searchValue = $_GET['value'] ?? '';
$dateFrom = $_GET['date_from'] ?? '';
$dateTo = $_GET['date_to'] ?? '';
$limit = (int)($_GET['limit'] ?? 100);
$offset = (int)($_GET['offset'] ?? 0);

// Валідація limit та offset
$limit = min($limit, 500);
$offset = max($offset, 0);

$pdo = dev_db_connection();
$results = [];

try {
    $query = '
        SELECT 
            o.id, 
            o.order_number, 
            o.customer_name_snapshot, 
            o.customer_phone_snapshot, 
            o.customer_email_snapshot, 
            o.total, 
            o.created_at, 
            (SELECT COALESCE(SUM(quantity),0) FROM order_items oi WHERE oi.order_id = o.id) AS items_count
        FROM orders o 
        WHERE 1=1
    ';
    
    $params = [];
    
    // Фільтр за номером замовлення
    if ($searchType === 'order_number' && !empty($searchValue)) {
        $query .= ' AND o.order_number LIKE :order_number';
        $params['order_number'] = '%' . $searchValue . '%';
    }
    
    // Фільтр за ПІБ клієнта
    if ($searchType === 'customer_name' && !empty($searchValue)) {
        $query .= ' AND o.customer_name_snapshot LIKE :customer_name';
        $params['customer_name'] = '%' . $searchValue . '%';
    }
    
    // Фільтр за телефоном клієнта
    if ($searchType === 'customer_phone' && !empty($searchValue)) {
        $query .= ' AND o.customer_phone_snapshot LIKE :customer_phone';
        $params['customer_phone'] = '%' . $searchValue . '%';
    }
    
    // Фільтр за email клієнта
    if ($searchType === 'customer_email' && !empty($searchValue)) {
        $query .= ' AND o.customer_email_snapshot LIKE :customer_email';
        $params['customer_email'] = '%' . $searchValue . '%';
    }
    
    // Фільтр за датою (конкретна дата)
    if ($searchType === 'date_exact' && !empty($searchValue)) {
        $query .= ' AND DATE(o.created_at) = :date_exact';
        $params['date_exact'] = $searchValue;
    }
    
    // Фільтр за діапазоном дат
    if ($searchType === 'date_range') {
        if (!empty($dateFrom)) {
            $query .= ' AND DATE(o.created_at) >= :date_from';
            $params['date_from'] = $dateFrom;
        }
        if (!empty($dateTo)) {
            $query .= ' AND DATE(o.created_at) <= :date_to';
            $params['date_to'] = $dateTo;
        }
    }
    
    // Загальний пошук (по всім полям)
    if ($searchType === 'general' && !empty($searchValue)) {
        $query .= ' AND (
            o.order_number LIKE :general_search OR
            o.customer_name_snapshot LIKE :general_search OR
            o.customer_phone_snapshot LIKE :general_search OR
            o.customer_email_snapshot LIKE :general_search
        )';
        $params['general_search'] = '%' . $searchValue . '%';
    }
    
    // Сортування та пагінація
    $query .= ' ORDER BY o.id DESC LIMIT :limit OFFSET :offset';
    
    $stmt = $pdo->prepare($query);
    
    // Прив'язування параметрів
    foreach ($params as $key => $value) {
        $stmt->bindValue(':' . $key, $value);
    }
    
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    
    $stmt->execute();
    $results = $stmt->fetchAll();
    
    // Отримання загальної кількості результатів
    $countQuery = 'SELECT COUNT(*) as total FROM orders o WHERE 1=1';
    $countParams = [];
    
    if ($searchType === 'order_number' && !empty($searchValue)) {
        $countQuery .= ' AND o.order_number LIKE :order_number';
        $countParams['order_number'] = '%' . $searchValue . '%';
    }
    if ($searchType === 'customer_name' && !empty($searchValue)) {
        $countQuery .= ' AND o.customer_name_snapshot LIKE :customer_name';
        $countParams['customer_name'] = '%' . $searchValue . '%';
    }
    if ($searchType === 'customer_phone' && !empty($searchValue)) {
        $countQuery .= ' AND o.customer_phone_snapshot LIKE :customer_phone';
        $countParams['customer_phone'] = '%' . $searchValue . '%';
    }
    if ($searchType === 'customer_email' && !empty($searchValue)) {
        $countQuery .= ' AND o.customer_email_snapshot LIKE :customer_email';
        $countParams['customer_email'] = '%' . $searchValue . '%';
    }
    if ($searchType === 'date_exact' && !empty($searchValue)) {
        $countQuery .= ' AND DATE(o.created_at) = :date_exact';
        $countParams['date_exact'] = $searchValue;
    }
    if ($searchType === 'date_range') {
        if (!empty($dateFrom)) {
            $countQuery .= ' AND DATE(o.created_at) >= :date_from';
            $countParams['date_from'] = $dateFrom;
        }
        if (!empty($dateTo)) {
            $countQuery .= ' AND DATE(o.created_at) <= :date_to';
            $countParams['date_to'] = $dateTo;
        }
    }
    if ($searchType === 'general' && !empty($searchValue)) {
        $countQuery .= ' AND (
            o.order_number LIKE :general_search OR
            o.customer_name_snapshot LIKE :general_search OR
            o.customer_phone_snapshot LIKE :general_search OR
            o.customer_email_snapshot LIKE :general_search
        )';
        $countParams['general_search'] = '%' . $searchValue . '%';
    }
    
    $countStmt = $pdo->prepare($countQuery);
    foreach ($countParams as $key => $value) {
        $countStmt->bindValue(':' . $key, $value);
    }
    $countStmt->execute();
    $totalCount = (int)$countStmt->fetch()['total'];
    
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => true,
        'data' => $results,
        'total' => $totalCount,
        'limit' => $limit,
        'offset' => $offset
    ], JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    error_log('Search orders error: ' . $e->getMessage());
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => false,
        'error' => 'Database error'
    ], JSON_UNESCAPED_UNICODE);
}

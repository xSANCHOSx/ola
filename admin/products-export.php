<?php

declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';
admin_require_permission('products', 'view');
$pdo = dev_db_connection();
if (!$pdo instanceof PDO) {
    http_response_code(503);
    exit('Database unavailable');
}
$search = trim((string)($_GET['q'] ?? ''));
$status = (string)($_GET['status_filter'] ?? '');
$where = [];
$params = [];
if ($search !== '') {
    $where[] = '(name LIKE :search_name OR external_id LIKE :search_external_id OR cat_number LIKE :search_cat_number OR link LIKE :search_link)';
    $searchValue = '%' . $search . '%';
    $params['search_name'] = $searchValue;
    $params['search_external_id'] = $searchValue;
    $params['search_cat_number'] = $searchValue;
    $params['search_link'] = $searchValue;
}
if (in_array($status, ['active', 'preorder', 'disabled'], true)) {
    $where[] = 'status = :status';
    $params['status'] = $status;
}
$sql = 'SELECT external_id, cat_number, name, old_price, price, link, in_stock, status, seo_title, seo_description, volume FROM products';
if ($where) $sql .= ' WHERE ' . implode(' AND ', $where);
$sql .= ' ORDER BY sort_order ASC, id ASC';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="products-' . date('Y-m-d') . '.csv"');
$out = fopen('php://output', 'wb');
fwrite($out, "\xEF\xBB\xBF");
fputcsv($out, ['external_id', 'cat_number', 'name', 'old_price', 'price', 'link', 'in_stock', 'status', 'seo_title', 'seo_description', 'volume'], ';');
function csv_safe_value(mixed $value): string
{
    $value = (string)$value;
    return $value !== '' && preg_match('/^[=+\-@]/', $value) === 1
        ? "'" . $value
        : $value;
}
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    fputcsv($out, array_map('csv_safe_value', $row), ';');
}
fclose($out);
exit;

<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';

/**
 * Получить список активных продуктов с кешированием
 * Результат кешируется на все время выполнения скрипта (static)
 */
function get_products(): array
{
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }

    $cache = [];
    $pdo = dev_db_connection();
    if ($pdo instanceof PDO) {
        try {
            $stmt = $pdo->query('SELECT external_id, cat_number, name, old_price, price, image, link, short_desc, `desc`, full_desc, in_stock, status, seo_title, seo_description FROM products WHERE status = "active" ORDER BY id ASC');
            foreach ($stmt->fetchAll() as $row) {
                $cache[] = [
                    'id' => (string)$row['external_id'],
                    'cat_number' => (string)($row['cat_number'] ?? ''),
                    'name' => (string)$row['name'],
                    'old_price' => (float)($row['old_price'] ?? 0),
                    'price' => (float)$row['price'],
                    'image' => (string)($row['image'] ?? ''),
                    'link' => (string)$row['link'],
                    'short_desc' => (string)($row['short_desc'] ?? ''),
                    'desc' => (string)($row['desc'] ?? ''),
                    'full_desc' => (string)($row['full_desc'] ?? ''),
                    'in_stock' => (bool)$row['in_stock'],
                    'status' => $row['status'] !== null ? (string)$row['status'] : null,
                    'seo_title' => $row['seo_title'] !== null ? (string)$row['seo_title'] : '',
                    'seo_description' => $row['seo_description'] !== null ? (string)$row['seo_description'] : '',
                ];
            }
        } catch (Throwable $e) {
            dev_log_runtime('Products load from DB failed: ' . $e->getMessage());
            // Если БД недоступна - пустой массив (сайт будет работать без продуктов)
            $cache = [];
        }
    }

    return $cache;
}

// Для зворотної сумісності з існуючим кодом, який використовує $products глобально
$products = get_products();

/**
 * Чи можна купити товар (в наявності або передзамовлення)
 */
function product_is_buyable(array $p): bool
{
    return !empty($p['in_stock'])
        || (!empty($p['status']) && $p['status'] === 'preorder');
}

/**
 * Текст кнопки "Купити" або "Передзамовлення"
 */
function product_button_label(array $p): string
{
    return (!empty($p['status']) && $p['status'] === 'preorder')
        ? 'Предзаказ'
        : 'Купить';
}

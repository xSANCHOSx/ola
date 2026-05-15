<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';

// save_utm_cookies() определена в config/db.php
// Если по какой-то причине её там нет — определяем здесь как fallback
if (!function_exists('save_utm_cookies')) {
    function save_utm_cookies(): void
    {
        if (!isset($_GET['utm_source'])) {
            return;
        }
        $cookieTime = time() + 60 * 60 * 24 * 7;
        setcookie('utm_source',   (string)($_GET['utm_source']   ?? ''), $cookieTime, '/');
        setcookie('utm_medium',   (string)($_GET['utm_medium']   ?? ''), $cookieTime, '/');
        setcookie('utm_campaign', (string)($_GET['utm_campaign'] ?? ''), $cookieTime, '/');
        setcookie('utm_content',  (string)($_GET['utm_content']  ?? ''), $cookieTime, '/');
    }
}

save_utm_cookies();

/**
 * Получить список активных продуктов с кешированием
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
            // ИСПРАВЛЕНО: добавлена запятая перед sort_order
            // ИСПРАВЛЕНО: ORDER BY p.sort_order — явная ссылка на колонку таблицы,
            //             а не на алиас из SELECT (MySQL иначе сортирует по алиасу)
            $stmt = $pdo->query(
                'SELECT p.external_id, p.cat_number, p.name, p.old_price, p.price,
                        p.image, p.link, p.short_desc, p.`desc`, p.full_desc,
                        p.in_stock, p.status, p.seo_title, p.seo_description, p.sort_order
                 FROM products p
                 WHERE p.status = "active"
                 ORDER BY p.sort_order ASC'
            );
            foreach ($stmt->fetchAll() as $row) {
                $cache[] = [
                    'id'              => (string)$row['external_id'],
                    'cat_number'      => (string)($row['cat_number'] ?? ''),
                    'name'            => (string)$row['name'],
                    'old_price'       => (float)($row['old_price'] ?? 0),
                    'price'           => (float)$row['price'],
                    'image'           => (string)($row['image'] ?? ''),
                    'link'            => (string)$row['link'],
                    'short_desc'      => (string)($row['short_desc'] ?? ''),
                    'desc'            => (string)($row['desc'] ?? ''),
                    'full_desc'       => (string)($row['full_desc'] ?? ''),
                    'in_stock'        => (bool)$row['in_stock'],
                    'sort_order'      => (int)$row['sort_order'],
                    'status'          => $row['status'] !== null ? (string)$row['status'] : null,
                    'seo_title'       => $row['seo_title'] !== null ? (string)$row['seo_title'] : '',
                    'seo_description' => $row['seo_description'] !== null ? (string)$row['seo_description'] : '',
                ];
            }
        } catch (Throwable $e) {
            dev_log_runtime('Products load from DB failed: ' . $e->getMessage());
            $cache = [];
        }
    }

    return $cache;
}

$products = get_products();

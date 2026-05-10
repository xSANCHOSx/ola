<?php
declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';
admin_require_auth();
$pdo = dev_db_connection();
$orders = [];
if ($pdo instanceof PDO) {
    $orders = $pdo->query('
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
        ORDER BY o.id DESC 
        LIMIT 100
    ')->fetchAll();
}

function get_order_items_with_links(PDO $pdo, int $orderId): array {
    // Пробуємо знайти товар спочатку за external_id, потім за назвою
    // Використовуємо GROUP BY oi.id щоб уникнути дублювання через JOIN, якщо дані в БД некоректні
    $stmt = $pdo->prepare('
        SELECT 
            oi.name, 
            oi.quantity, 
            oi.product_external_id,
            COALESCE(p1.id, p2.id) as product_id
        FROM order_items oi 
        LEFT JOIN products p1 ON (oi.product_external_id = p1.external_id AND oi.product_external_id != "")
        LEFT JOIN products p2 ON (oi.name = p2.name AND p1.id IS NULL)
        WHERE oi.order_id = :order_id
        GROUP BY oi.id
    ');
    $stmt->execute(['order_id' => $orderId]);
    return $stmt->fetchAll();
}
?>
<!doctype html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Админка - Заказы</title>
    <link rel="stylesheet" href="/css/bootstrap.min.css">
    <style>
        .items-list { font-size: 0.85rem; color: #555; line-height: 1.4; }
        .item-row { margin-bottom: 4px; display: block; }
        .item-link { color: #007bff; text-decoration: underline; font-weight: 500; }
    </style>
</head>
<body>
<div class="container">
    <?php require __DIR__ . '/_nav.php'; ?>
    <h3>Заказы</h3>
    <table class="table table-bordered table-striped">
        <thead>
        <tr>
            <th>#</th>
            <th>Дата</th>
            <th>Клиент</th>
            <th>Телефон</th>
            <th>Товары (кол-во)</th>
            <th>Сумма</th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($orders as $o): ?>
            <tr>
                <td><?= admin_h((string)$o['order_number']) ?></td>
                <td><?= admin_h((string)$o['created_at']) ?></td>
                <td>
                    <?= admin_h((string)$o['customer_name_snapshot']) ?><br>
                    <small class="text-muted"><?= admin_h((string)$o['customer_email_snapshot']) ?></small>
                </td>
                <td><?= admin_h((string)$o['customer_phone_snapshot']) ?></td>
                <td>
                    <div class="items-list">
                        <?php 
                        $items = get_order_items_with_links($pdo, (int)$o['id']);
                        foreach ($items as $item): 
                        ?>
                            <span class="item-row">
                                <?php if (!empty($item['product_id'])): ?>
                                    <a href="/admin/products.php?edit=<?= (int)$item['product_id'] ?>" target="_blank" class="item-link">
                                        <?= admin_h((string)$item['name']) ?>
                                    </a>
                                <?php else: ?>
                                    <span class="text-danger"><?= admin_h((string)$item['name']) ?></span>
                                    <small class="text-muted">(ID: <?= admin_h((string)$item['product_external_id']) ?>)</small>
                                <?php endif; ?>
                                <strong>x <?= (int)$item['quantity'] ?></strong>
                            </span>
                        <?php endforeach; ?>
                    </div>
                    <small class="text-muted">Всего: <?= admin_h((string)$o['items_count']) ?></small>
                </td>
                <td><?= admin_h((string)$o['total']) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
</body>
</html>

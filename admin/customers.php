<?php

declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';
admin_require_auth();
$pdo = dev_db_connection();
$customers = [];
$orders = [];
$customerId = (int)($_GET['id'] ?? 0);
if ($pdo instanceof PDO) {
    $customers = $pdo->query('SELECT * FROM customers ORDER BY last_order_at DESC, id DESC LIMIT 500')->fetchAll();
    if ($customerId > 0) {
        $stmt = $pdo->prepare('
            SELECT 
                o.id,
                o.order_number, 
                o.total, 
                o.created_at
            FROM orders o 
            WHERE o.customer_id = :id 
            ORDER BY o.id DESC
        ');
        $stmt->execute(['id' => $customerId]);
        $orders = $stmt->fetchAll();
    }
}

function get_order_items_with_links(PDO $pdo, int $orderId): array {
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
    <title>Админка - Клиенты</title>
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
        <h3>Клиенты</h3>
        <table class="table table-bordered table-striped">
            <thead>
                <tr>
                    <th>Имя</th>
                    <th>Телефон</th>
                    <th>Email</th>
                    <th>Заказов</th>
                    <th>Последний #</th>
                    <th>Последний заказ</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($customers as $c): ?>
                    <tr>
                        <td><?= admin_h((string)$c['full_name']) ?></td>
                        <td><?= admin_h((string)$c['phone']) ?></td>
                        <td><?= admin_h((string)$c['email']) ?></td>
                        <td><?= admin_h((string)$c['orders_count']) ?></td>
                        <td><?= admin_h((string)$c['last_order_number']) ?></td>
                        <td><?= admin_h((string)$c['last_order_at']) ?></td>
                        <td><a href="/admin/customers.php?id=<?= (int)$c['id'] ?>">История</a></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php if ($orders): ?>
            <hr>
            <h4>История заказов клиента</h4>
            <table class="table table-bordered">
                <thead>
                    <tr>
                        <th>Номер</th>
                        <th>Дата</th>
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
                            </td>
                            <td><?= admin_h((string)$o['total']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</body>

</html>

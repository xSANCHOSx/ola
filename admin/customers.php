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
<?php $adminPageTitle = "Клиенты"; require __DIR__ . "/_layout.php"; ?>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Админка - Клиенты</title>
    <link rel="stylesheet" href="/css/bootstrap.min.css">
</head>
<body>
<div class="container">
    <?php require __DIR__ . '/_nav.php'; ?>
    <h3>Клиенты</h3>
    <table class="table table-bordered table-striped">
        <thead><tr><th>Имя</th><th>Телефон</th><th>Email</th><th>Заказов</th><th>Последний #</th><th>Последний заказ</th><th></th></tr></thead>
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
        <h4>История заказов клиента</h4>
        <table class="table table-bordered">
            <thead><tr><th>Номер</th><th>Дата</th><th>Сумма</th></tr></thead>
            <tbody>
            <?php foreach ($orders as $o): ?>
                <tr>
                    <td><?= admin_h((string)$o['order_number']) ?></td>
                    <td><?= admin_h((string)$o['created_at']) ?></td>
                    <td><?= admin_h((string)$o['total']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
</body>
</html>

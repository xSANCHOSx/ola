<?php
declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';
admin_require_auth();
$pdo = dev_db_connection();
$orders = [];
if ($pdo instanceof PDO) {
    $orders = $pdo->query('SELECT o.id, o.order_number, o.customer_name_snapshot, o.customer_phone_snapshot, o.customer_email_snapshot, o.total, o.created_at, (SELECT COALESCE(SUM(quantity),0) FROM order_items oi WHERE oi.order_id = o.id) AS items_count FROM orders o ORDER BY o.id DESC LIMIT 100')->fetchAll();
}
?>
<?php $adminPageTitle = "Заказы"; require __DIR__ . "/_layout.php"; ?>
    <h3>Заказы</h3>
    <table class="table table-bordered table-striped">
        <thead>
        <tr><th>#</th><th>Дата</th><th>Клиент</th><th>Телефон</th><th>Email</th><th>Товаров</th><th>Сумма</th></tr>
        </thead>
        <tbody>
        <?php foreach ($orders as $o): ?>
            <tr>
                <td><?= admin_h((string)$o['order_number']) ?></td>
                <td><?= admin_h((string)$o['created_at']) ?></td>
                <td><?= admin_h((string)$o['customer_name_snapshot']) ?></td>
                <td><?= admin_h((string)$o['customer_phone_snapshot']) ?></td>
                <td><?= admin_h((string)$o['customer_email_snapshot']) ?></td>
                <td><?= admin_h((string)$o['items_count']) ?></td>
                <td><?= admin_h((string)$o['total']) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
</body>
</html>

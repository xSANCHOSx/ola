<?php

declare(strict_types=1);

// admin/coupons_archive.php — Перегляд архіву видалених купонів

require __DIR__ . '/_bootstrap.php';
admin_require_auth();

$pdo = dev_db_connection();

// Отримати список архівованих купонів
$stmt = $pdo->query('
    SELECT * FROM coupons_archived
    ORDER BY archived_at DESC
    LIMIT 200
');
$archived_coupons = $stmt->fetchAll();

?>
<!doctype html>
<html lang="uk">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Архів купонів - Админ</title>
    <link rel="stylesheet" href="/css/bootstrap.min.css">
    <link rel="stylesheet" href="/css/admin.css">
</head>

<body>
    <div class="container">
        <?php require __DIR__ . '/_nav.php'; ?>

        <div class="d-flex align-items-center justify-content-between mb-3">
            <h3 class="mb-0">Архів видалених купонів</h3>
            <a href="/admin/coupons.php" class="btn btn-secondary">← Назад до купонів</a>
        </div>

        <div class="alert alert-info">
            <strong>ℹ️ Інформація:</strong> Тут зберігаються всі видалені купони для історії.
            Історія використання купонів зберігається в таблиці <code>coupon_usage</code>.
        </div>

        <?php if (!empty($archived_coupons)): ?>
            <p class="text-muted mb-3">Всього архівованих купонів: <strong><?= count($archived_coupons) ?></strong></p>

            <div class="table-responsive">
                <table class="table table-bordered table-striped">
                    <thead class="table-secondary">
                        <tr>
                            <th>ID</th>
                            <th>Код</th>
                            <th>Назва</th>
                            <th>Знижка</th>
                            <th>Використано</th>
                            <th>Створено</th>
                            <th>Архівовано</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($archived_coupons as $coupon): ?>
                            <tr>
                                <td><?= (int)$coupon['id'] ?></td>
                                <td><code><?= admin_h((string)$coupon['code']) ?></code></td>
                                <td><?= admin_h((string)$coupon['name']) ?></td>
                                <td>
                                    <?php if ($coupon['discount_type'] === 'percent'): ?>
                                        <span class="badge bg-warning text-dark"><?= admin_h((string)$coupon['discount_value']) ?>%</span>
                                    <?php else: ?>
                                        <span class="badge bg-success"><?= admin_h((string)$coupon['discount_value']) ?> р.</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge bg-info"><?= (int)$coupon['used_count'] ?> разів</span>
                                    <?php if ($coupon['max_usage_count']): ?>
                                        / <?= (int)$coupon['max_usage_count'] ?>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?= $coupon['created_at'] ? date('d.m.Y H:i', strtotime($coupon['created_at'])) : '—' ?>
                                </td>
                                <td>
                                    <strong><?= date('d.m.Y H:i', strtotime($coupon['archived_at'])) ?></strong>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="alert alert-secondary text-center py-5">
                <p class="mb-0">📦 Архів порожній - жодного купона ще не було видалено</p>
            </div>
        <?php endif; ?>
    </div>

    <script src="/js/bootstrap.min.js"></script>
</body>

</html>

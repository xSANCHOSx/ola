<?php

declare(strict_types=1);

// admin/coupons_archive.php — Просмотр архива удаленных купонов

require __DIR__ . '/_bootstrap.php';
admin_require_auth();

$pdo = dev_db_connection();

// Получить список архивированных купонов
$stmt = $pdo->query('
    SELECT * FROM coupons_archived
    ORDER BY archived_at DESC
    LIMIT 200
');
$archived_coupons = $stmt->fetchAll();

?>
<!doctype html>
<html lang="ru">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Архив купонов - Админ</title>
    <link rel="stylesheet" href="/css/bootstrap.min.css">
    <link rel="stylesheet" href="/css/admin.css">
</head>

<body>
    <div class="container">
        <?php require __DIR__ . '/_nav.php'; ?>

        <div class="d-flex align-items-center justify-content-between mb-3">
            <h3 class="mb-0">Архив удаленных купонов</h3>
            <a href="/admin/coupons.php" class="btn btn-secondary">← Назад к купонам</a>
        </div>

        <div class="alert alert-info">
            <strong>ℹ️ Информация:</strong> Здесь хранятся все удаленные купоны для истории.
            История использования купонов хранится в таблице <code>coupon_usage</code>.
        </div>

        <?php if (!empty($archived_coupons)): ?>
            <p class="text-muted mb-3">Всего архивированных купонов: <strong><?= count($archived_coupons) ?></strong></p>

            <div class="table-responsive">
                <table class="table table-bordered table-striped">
                    <thead class="table-secondary">
                        <tr>
                            <th>ID</th>
                            <th>Код</th>
                            <th>Название</th>
                            <th>Скидка</th>
                            <th>Использовано</th>
                            <th>Создано</th>
                            <th>Архивировано</th>
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
                                    <span class="badge bg-info"><?= (int)$coupon['used_count'] ?> раз</span>
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
                <p class="mb-0">📦 Архив пуст - ни одного купона еще не было удалено</p>
            </div>
        <?php endif; ?>
    </div>

    <script src="/js/bootstrap.min.js"></script>
</body>

</html>

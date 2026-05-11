<?php

declare(strict_types=1);

// admin/coupon_stats.php — Дашбоард статистики купонов
// Показывает: топ-10 купонов, общая сумма скидок, тренды по дням

require __DIR__ . '/_bootstrap.php';
admin_require_auth();

$pdo = dev_db_connection();

// ═══ Статистика ════════════════════════════════════════════════════════════

// Общая статистика
// ИСПРАВЛЕНО: FILTER (WHERE ...) не поддерживается в MariaDB,
//             используем SUM(CASE WHEN ... THEN 1 ELSE 0 END)
$stmt = $pdo->query('
    SELECT
        COUNT(DISTINCT c.id) as total_coupons,
        SUM(CASE WHEN c.is_active = 1 THEN 1 ELSE 0 END) as active_coupons,
        COUNT(cu.id) as total_usage,
        SUM(cu.discount_amount) as total_discount_given,
        MAX(cu.used_at) as last_usage
    FROM coupons c
    LEFT JOIN coupon_usage cu ON c.id = cu.coupon_id
');
$metrics = $stmt->fetch() ?: [];

// 2. Топ-10 купонов по использованию
$stmt = $pdo->query('
    SELECT
        c.id, c.code, c.name, c.discount_type, c.discount_value,
        COUNT(cu.id) as usage_count,
        SUM(cu.discount_amount) as discount_given,
        MAX(cu.used_at) as last_used,
        c.is_active
    FROM coupons c
    LEFT JOIN coupon_usage cu ON c.id = cu.coupon_id
    GROUP BY c.id, c.code, c.name, c.discount_type, c.discount_value, c.is_active
    ORDER BY usage_count DESC
    LIMIT 10
');
$topCoupons = $stmt->fetchAll() ?: [];

// 3. Купоны, которые скоро заканчиваются
$stmt = $pdo->query('
    SELECT id, code, name, valid_to, used_count, max_usage_count
    FROM coupons
    WHERE valid_to BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 7 DAY)
      AND is_active = 1
    ORDER BY valid_to ASC
    LIMIT 5
');
$expiringSoon = $stmt->fetchAll() ?: [];

// 4. Исчерпанные купоны
$stmt = $pdo->query('
    SELECT id, code, name, used_count, max_usage_count
    FROM coupons
    WHERE max_usage_count IS NOT NULL
      AND used_count >= max_usage_count
      AND is_active = 1
    ORDER BY used_count DESC
    LIMIT 5
');
$exhaustedCoupons = $stmt->fetchAll() ?: [];

// 5. Статистика по дням (последние 7 дней)
$stmt = $pdo->query('
    SELECT
        DATE(used_at) as date,
        COUNT(*) as usage_count,
        SUM(discount_amount) as discount_sum,
        COUNT(DISTINCT coupon_id) as unique_coupons
    FROM coupon_usage
    WHERE used_at > DATE_SUB(NOW(), INTERVAL 7 DAY)
    GROUP BY DATE(used_at)
    ORDER BY date DESC
');
$dailyStats = $stmt->fetchAll() ?: [];

?>
<!doctype html>
<html lang="ru">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Статистика купонов - Админ</title>
    <link rel="stylesheet" href="/css/bootstrap.min.css">
    <style>
        .metric-card {
            background: #fff;
            border: 1px solid #dee2e6;
            border-radius: .375rem;
            padding: 1.25rem;
            text-align: center;
        }

        .metric-value {
            font-size: 2rem;
            font-weight: 700;
            color: #0d6efd;
        }

        .metric-label {
            color: #6c757d;
            font-size: .875rem;
            margin-top: .25rem;
        }

        .section-title {
            border-bottom: 2px solid #0d6efd;
            padding-bottom: .5rem;
            margin: 1.75rem 0 1rem;
        }
    </style>
</head>

<body>
    <div class="container">
        <?php require __DIR__ . '/_nav.php'; ?>

        <h3>📊 Статистика купонов</h3>

        <!-- Метрики -->
        <div class="row g-3 mb-4">
            <div class="col-sm-6 col-lg-3">
                <div class="metric-card">
                    <div class="metric-value"><?= (int)($metrics['total_coupons'] ?? 0) ?></div>
                    <div class="metric-label">Всего купонов</div>
                </div>
            </div>
            <div class="col-sm-6 col-lg-3">
                <div class="metric-card">
                    <div class="metric-value"><?= (int)($metrics['active_coupons'] ?? 0) ?></div>
                    <div class="metric-label">Активных</div>
                </div>
            </div>
            <div class="col-sm-6 col-lg-3">
                <div class="metric-card">
                    <div class="metric-value"><?= (int)($metrics['total_usage'] ?? 0) ?></div>
                    <div class="metric-label">Использовано раз</div>
                </div>
            </div>
            <div class="col-sm-6 col-lg-3">
                <div class="metric-card">
                    <div class="metric-value"><?= number_format((float)($metrics['total_discount_given'] ?? 0), 0) ?> р.</div>
                    <div class="metric-label">Скидок выдано</div>
                </div>
            </div>
        </div>

        <!-- Топ купоны -->
        <h5 class="section-title">🎆 Топ-10 купонов по использованию</h5>
        <?php if (!empty($topCoupons)): ?>
            <table class="table table-bordered table-striped">
                <thead class="table-primary">
                    <tr>
                        <th>Код</th>
                        <th>Название</th>
                        <th>Тип</th>
                        <th>Значение</th>
                        <th>Использовано</th>
                        <th>Скидок выдано</th>
                        <th>Последнее использование</th>
                        <th>Статус</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($topCoupons as $coupon): ?>
                        <tr>
                            <td><strong><?= admin_h((string)$coupon['code']) ?></strong></td>
                            <td><?= admin_h((string)$coupon['name']) ?></td>
                            <td>
                                <?php if ($coupon['discount_type'] === 'percent'): ?>
                                    <span class="badge bg-warning text-dark">%</span>
                                <?php else: ?>
                                    <span class="badge bg-success">р.</span>
                                <?php endif; ?>
                            </td>
                            <td><?= admin_h((string)$coupon['discount_value']) ?></td>
                            <td><?= (int)($coupon['usage_count'] ?? 0) ?></td>
                            <td><?= number_format((float)($coupon['discount_given'] ?? 0), 2) ?> р.</td>
                            <td><?= $coupon['last_used'] ? date('d.m.Y H:i', strtotime($coupon['last_used'])) : '—' ?></td>
                            <td>
                                <?php if ($coupon['is_active']): ?>
                                    <span class="badge bg-success">Активний</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary">Вимкнений</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p class="text-muted">Купони не використовуються.</p>
        <?php endif; ?>

        <!-- Купоны, которые скоро заканчиваются -->
        <h5 class="section-title">⏰ Купоны, которые скоро заканчиваются (7 дней)</h5>
        <?php if (!empty($expiringSoon)): ?>
            <table class="table table-bordered table-striped">
                <thead class="table-warning">
                    <tr>
                        <th>Код</th>
                        <th>Название</th>
                        <th>Действителен до</th>
                        <th>Дней осталось</th>
                        <th>Относительно</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($expiringSoon as $coupon): ?>
                        <tr>
                            <td><strong><?= admin_h((string)$coupon['code']) ?></strong></td>
                            <td><?= admin_h((string)$coupon['name']) ?></td>
                            <td><?= date('d.m.Y', strtotime($coupon['valid_to'])) ?></td>
                            <td><span class="text-warning fw-bold"><?= (int)ceil((strtotime($coupon['valid_to']) - time()) / 86400) ?>
                                    дней</span></td>
                            <td>
                                <?= (int)$coupon['used_count'] ?><?= $coupon['max_usage_count'] ? '/' . (int)$coupon['max_usage_count'] : '' ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p class="text-muted">Нет купонов, которые скоро заканчиваются.</p>
        <?php endif; ?>

        <!-- Исчерпанные купоны -->
        <h5 class="section-title">🔴 Исчерпанные купоны (максимум достигнут)</h5>
        <?php if (!empty($exhaustedCoupons)): ?>
            <table class="table table-bordered table-striped">
                <thead class="table-danger">
                    <tr>
                        <th>Код</th>
                        <th>Название</th>
                        <th>Использовано</th>
                        <th>Максимум</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($exhaustedCoupons as $coupon): ?>
                        <tr>
                            <td><strong><?= admin_h((string)$coupon['code']) ?></strong></td>
                            <td><?= admin_h((string)$coupon['name']) ?></td>
                            <td><span class="text-danger fw-bold"><?= (int)$coupon['used_count'] ?></span></td>
                            <td><?= (int)$coupon['max_usage_count'] ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p class="text-muted">Нет исчерпанных купонов.</p>
        <?php endif; ?>

        <!-- Статистика по дням -->
        <h5 class="section-title">📋 Использование по дням (последние 7 дней)</h5>
        <?php if (!empty($dailyStats)): ?>
            <table class="table table-bordered table-striped">
                <thead class="table-primary">
                    <tr>
                        <th>Дата</th>
                        <th>Использований</th>
                        <th>Сумма скидок</th>
                        <th>Недупликатных купонов</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($dailyStats as $stat): ?>
                        <tr>
                            <td><?= date('d.m.Y (D)', strtotime($stat['date'])) ?></td>
                            <td><?= (int)$stat['usage_count'] ?></td>
                            <td><?= number_format((float)$stat['discount_sum'], 2) ?> р.</td>
                            <td><?= (int)$stat['unique_coupons'] ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p class="text-muted">Нет статистики за последние 7 дней.</p>
        <?php endif; ?>

    </div>
</body>

</html>
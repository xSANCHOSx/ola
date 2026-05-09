<?php

declare(strict_types=1);

// admin/coupon_stats.php — Дашбоард статистики купонів
// Показує: топ-10 купонів, загальна сума знижок, тренди по днях

session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../admin/_bootstrap.php';

// Перевірка авторизації
if (empty($_SESSION[dev_app_config()['admin_session_key']])) {
    header('Location: /admin/login.php');
    exit;
}

$pdo = dev_db_connection();

// ═══ Статистика ════════════════════════════════════════════════════════════

// 1. Загальні метрики
$stmt = $pdo->query('
    SELECT 
        COUNT(DISTINCT c.id) as total_coupons,
        COUNT(DISTINCT c.id) FILTER (WHERE c.is_active = 1) as active_coupons,
        COUNT(cu.id) as total_usage,
        SUM(cu.discount_amount) as total_discount_given,
        MAX(cu.used_at) as last_usage
    FROM coupons c
    LEFT JOIN coupon_usage cu ON c.id = cu.coupon_id
');
$metrics = $stmt->fetch() ?: [];

// 2. Топ-10 купонів по використанню
$stmt = $pdo->query('
    SELECT 
        c.id, c.code, c.name, c.discount_type, c.discount_value,
        COUNT(cu.id) as usage_count,
        SUM(cu.discount_amount) as discount_given,
        MAX(cu.used_at) as last_used,
        c.is_active
    FROM coupons c
    LEFT JOIN coupon_usage cu ON c.id = cu.coupon_id
    GROUP BY c.id
    ORDER BY usage_count DESC
    LIMIT 10
');
$topCoupons = $stmt->fetchAll() ?: [];

// 3. Купони що скоро закінчуються
$stmt = $pdo->query('
    SELECT id, code, name, valid_to, used_count, max_usage_count
    FROM coupons
    WHERE valid_to BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 7 DAY)
    AND is_active = 1
    ORDER BY valid_to ASC
    LIMIT 5
');
$expiringSoon = $stmt->fetchAll() ?: [];

// 4. Вичерпані купони
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

// 5. Статистика по днях (останніх 7 днів)
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
<!DOCTYPE html>
<html lang="uk">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Статистика купонів - Адмін</title>
    <link rel="stylesheet" href="/css/bootstrap.min.css">
    <style>
        body { background: #f5f5f5; font-family: Arial, sans-serif; }
        .container { max-width: 1400px; margin: 20px auto; }
        .dashboard-header { margin-bottom: 30px; }
        .metric-card {
            background: white;
            padding: 20px;
            border-radius: 5px;
            margin-bottom: 20px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        .metric-value { font-size: 32px; font-weight: bold; color: #007bff; }
        .metric-label { color: #666; font-size: 14px; margin-top: 5px; }
        .metrics-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-bottom: 30px; }
        table { width: 100%; border-collapse: collapse; }
        th { background: #007bff; color: white; padding: 12px; text-align: left; }
        td { padding: 12px; border-bottom: 1px solid #ddd; }
        tr:hover { background: #f9f9f9; }
        .status-active { color: green; font-weight: bold; }
        .status-expires { color: orange; font-weight: bold; }
        .status-exhausted { color: red; font-weight: bold; }
        .badge { padding: 3px 8px; border-radius: 3px; font-size: 0.85em; }
        .badge-fixed { background: #28a745; color: white; }
        .badge-percent { background: #ffc107; color: black; }
        h2 { color: #333; margin-top: 30px; margin-bottom: 15px; border-bottom: 2px solid #007bff; padding-bottom: 10px; }
        .back-link { margin-bottom: 20px; }
        .back-link a { color: #007bff; text-decoration: none; }
        .back-link a:hover { text-decoration: underline; }
    </style>
</head>
<body>
    <div class="container">
        <div class="back-link">
            <a href="/admin/">← Повернутися в адмін</a>
        </div>
        
        <div class="dashboard-header">
            <h1>📊 Статистика купонів</h1>
            <p style="color: #666;">Огляд активності та효кування купонів</p>
        </div>
        
        <!-- Метрики -->
        <div class="metrics-grid">
            <div class="metric-card">
                <div class="metric-value"><?= $metrics['total_coupons'] ?? 0 ?></div>
                <div class="metric-label">Всього купонів</div>
            </div>
            <div class="metric-card">
                <div class="metric-value"><?= $metrics['active_coupons'] ?? 0 ?></div>
                <div class="metric-label">Активних</div>
            </div>
            <div class="metric-card">
                <div class="metric-value"><?= $metrics['total_usage'] ?? 0 ?></div>
                <div class="metric-label">Використано разів</div>
            </div>
            <div class="metric-card">
                <div class="metric-value"><?= number_format((float)($metrics['total_discount_given'] ?? 0), 0) ?> р.</div>
                <div class="metric-label">Всього знижок видано</div>
            </div>
        </div>
        
        <!-- Топ купони -->
        <h2>🏆 Топ-10 купонів по використанню</h2>
        <?php if (!empty($topCoupons)): ?>
        <div class="metric-card">
            <table>
                <thead>
                    <tr>
                        <th>Код</th>
                        <th>Назва</th>
                        <th>Тип</th>
                        <th>Значення</th>
                        <th>Використано</th>
                        <th>Знижок видано</th>
                        <th>Останнє використання</th>
                        <th>Статус</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($topCoupons as $coupon): ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($coupon['code']) ?></strong></td>
                        <td><?= htmlspecialchars($coupon['name']) ?></td>
                        <td>
                            <span class="badge <?= $coupon['discount_type'] === 'percent' ? 'badge-percent' : 'badge-fixed' ?>">
                                <?= $coupon['discount_type'] === 'percent' ? '%' : 'р.' ?>
                            </span>
                        </td>
                        <td><?= $coupon['discount_value'] ?></td>
                        <td><?= $coupon['usage_count'] ?? 0 ?></td>
                        <td><?= number_format((float)($coupon['discount_given'] ?? 0), 2) ?> р.</td>
                        <td><?= $coupon['last_used'] ? date('d.m.Y H:i', strtotime($coupon['last_used'])) : '—' ?></td>
                        <td><span class="status-active">✓ Активний</span></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
            <div class="metric-card" style="text-align: center; color: #999;">Купони не використовуються</div>
        <?php endif; ?>
        
        <!-- Купони що скоро закінчуються -->
        <h2>⏰ Купони що скоро закінчуються (7 днів)</h2>
        <?php if (!empty($expiringSoon)): ?>
        <div class="metric-card">
            <table>
                <thead>
                    <tr>
                        <th>Код</th>
                        <th>Назва</th>
                        <th>Дійсний до</th>
                        <th>Днів лишилось</th>
                        <th>Використано</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($expiringSoon as $coupon): ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($coupon['code']) ?></strong></td>
                        <td><?= htmlspecialchars($coupon['name']) ?></td>
                        <td><?= date('d.m.Y', strtotime($coupon['valid_to'])) ?></td>
                        <td><span class="status-expires"><?= ceil((strtotime($coupon['valid_to']) - time()) / 86400) ?> днів</span></td>
                        <td><?= $coupon['used_count'] ?><?= $coupon['max_usage_count'] ? '/' . $coupon['max_usage_count'] : '' ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
            <div class="metric-card" style="text-align: center; color: #999;">Немає купонів що скоро закінчуються</div>
        <?php endif; ?>
        
        <!-- Вичерпані купони -->
        <h2>🔴 Вичерпані купони (максимум досягнуто)</h2>
        <?php if (!empty($exhaustedCoupons)): ?>
        <div class="metric-card">
            <table>
                <thead>
                    <tr>
                        <th>Код</th>
                        <th>Назва</th>
                        <th>Використано</th>
                        <th>Максимум</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($exhaustedCoupons as $coupon): ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($coupon['code']) ?></strong></td>
                        <td><?= htmlspecialchars($coupon['name']) ?></td>
                        <td><span class="status-exhausted"><?= $coupon['used_count'] ?></span></td>
                        <td><?= $coupon['max_usage_count'] ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
            <div class="metric-card" style="text-align: center; color: #999;">Немає вичерпаних купонів</div>
        <?php endif; ?>
        
        <!-- Статистика по днях -->
        <h2>📈 Використання купонів по днях (останніх 7 днів)</h2>
        <?php if (!empty($dailyStats)): ?>
        <div class="metric-card">
            <table>
                <thead>
                    <tr>
                        <th>Дата</th>
                        <th>Кількість використань</th>
                        <th>Сума знижок</th>
                        <th>Унікальних купонів</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($dailyStats as $stat): ?>
                    <tr>
                        <td><?= date('d.m.Y (D)', strtotime($stat['date'])) ?></td>
                        <td><?= $stat['usage_count'] ?></td>
                        <td><?= number_format((float)$stat['discount_sum'], 2) ?> р.</td>
                        <td><?= $stat['unique_coupons'] ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
            <div class="metric-card" style="text-align: center; color: #999;">Нема статистики за останні 7 днів</div>
        <?php endif; ?>
    </div>
</body>
</html>

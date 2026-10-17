<?php

declare(strict_types=1);

// admin/stats.php — Общая статистика магазина
// Заказы по периодам, выручка, средний чек, клиенты, динамика, топ-товары,
// каналы связи, активность по дням недели и часам, отзывы, проблемные заказы.
// Статистика купонов вынесена в раздел «Купоны» (admin/coupon_stats.php).

require __DIR__ . '/_bootstrap.php';
admin_require_auth();

$pdo = dev_db_connection();
$statErrors = [];

// ═══ Хелперы ══════════════════════════════════════════════════════════════

/** Выполняет SELECT, при ошибке (нет таблицы/колонки) возвращает [] и запоминает ошибку. */
function stat_all(PDO $pdo, string $sql, array &$errors): array
{
    try {
        return $pdo->query($sql)->fetchAll() ?: [];
    } catch (Throwable $e) {
        $errors[] = $e->getMessage();
        dev_log_runtime('Admin stats query failed: ' . $e->getMessage());
        return [];
    }
}

function stat_one(PDO $pdo, string $sql, array &$errors): array
{
    return stat_all($pdo, $sql, $errors)[0] ?? [];
}

function fmt_int($v): string
{
    return number_format((float)$v, 0, ',', ' ');
}

function fmt_money($v): string
{
    return number_format((float)$v, 0, ',', ' ') . ' ₽';
}

/** Бейдж изменения к предыдущему периоду. */
function delta_badge(float $cur, float $prev): string
{
    if ($prev <= 0.0) {
        return $cur > 0.0 ? '<span class="stat-delta stat-delta--up">новое</span>' : '<span class="stat-delta">—</span>';
    }
    $pct = ($cur - $prev) / $prev * 100;
    $cls = $pct > 0.05 ? 'up' : ($pct < -0.05 ? 'down' : 'flat');
    $arrow = $pct > 0.05 ? '▲' : ($pct < -0.05 ? '▼' : '•');
    return '<span class="stat-delta stat-delta--' . $cls . '">' . $arrow . ' ' . number_format(abs($pct), 1, ',', '') . '%</span>';
}

/** Столбчатая диаграмма в виде inline-SVG (без внешних библиотек). */
function render_bar_chart(array $labels, array $values, string $color, string $unit = ''): string
{
    $n = count($values);
    if ($n === 0) {
        return '<p class="text-muted mb-0">Нет данных.</p>';
    }
    $w = 800;
    $h = 220;
    $padL = 48;
    $padR = 8;
    $padT = 10;
    $padB = 26;
    $plotW = $w - $padL - $padR;
    $plotH = $h - $padT - $padB;

    $max = max($values);
    if ($max <= 0) {
        $max = 1;
    }
    // «красивый» верх шкалы
    $mag = 10 ** (int)floor(log10($max));
    $niceMax = (int)(ceil($max / $mag * 2) / 2 * $mag);
    if ($niceMax < $max) {
        $niceMax = (int)ceil($max);
    }
    if ($niceMax <= 0) {
        $niceMax = 1;
    }

    $slot = $plotW / $n;
    $barW = max(1.0, min(28.0, $slot * 0.7));
    $labelEvery = max(1, (int)ceil($n / 12));

    $svg = '<svg class="stat-chart" viewBox="0 0 ' . $w . ' ' . $h . '" role="img" preserveAspectRatio="xMidYMid meet">';
    for ($i = 0; $i <= 4; $i++) {
        $y = $padT + $plotH - ($plotH * $i / 4);
        $val = $niceMax * $i / 4;
        $svg .= '<line x1="' . $padL . '" y1="' . round($y, 1) . '" x2="' . ($w - $padR) . '" y2="' . round($y, 1) . '" stroke="#e5e7eb" stroke-width="1"/>';
        $svg .= '<text x="' . ($padL - 6) . '" y="' . round($y + 4, 1) . '" text-anchor="end" font-size="11" fill="#6b7280">' . admin_h(fmt_int($val)) . '</text>';
    }
    foreach ($values as $i => $v) {
        $bh = $niceMax > 0 ? ($plotH * (float)$v / $niceMax) : 0;
        $x = $padL + $slot * $i + ($slot - $barW) / 2;
        $y = $padT + $plotH - $bh;
        $title = admin_h((string)$labels[$i]) . ': ' . admin_h(fmt_int($v)) . ($unit !== '' ? ' ' . admin_h($unit) : '');
        $svg .= '<g><title>' . $title . '</title>';
        $svg .= '<rect x="' . round($x, 1) . '" y="' . round($y, 1) . '" width="' . round($barW, 1) . '" height="' . round(max($bh, (float)$v > 0 ? 1.5 : 0), 1) . '" rx="2" fill="' . $color . '"/></g>';
        if ($i % $labelEvery === 0) {
            $svg .= '<text x="' . round($x + $barW / 2, 1) . '" y="' . ($h - 8) . '" text-anchor="middle" font-size="11" fill="#6b7280">' . admin_h((string)$labels[$i]) . '</text>';
        }
    }
    return $svg . '</svg>';
}

/** Горизонтальные полоски «название — значение — доля». */
function render_hbars(array $rows, string $color = '#0d6efd'): string
{
    if (!$rows) {
        return '<p class="text-muted mb-0">Нет данных.</p>';
    }
    $max = max(array_column($rows, 'value')) ?: 1;
    $out = '';
    foreach ($rows as $r) {
        $pct = round($r['value'] / $max * 100, 1);
        $out .= '<div class="stat-hbar"><div class="stat-hbar__label">' . admin_h((string)$r['label']) . '</div>'
            . '<div class="stat-hbar__track"><div class="stat-hbar__fill" style="width:' . $pct . '%;background:' . $color . '"></div></div>'
            . '<div class="stat-hbar__value">' . admin_h((string)$r['text']) . '</div></div>';
    }
    return $out;
}

// ═══ Параметры ════════════════════════════════════════════════════════════

$allowedRanges = [7 => '7 дней', 30 => '30 дней', 90 => '90 дней', 365 => '12 месяцев'];
$days = (int)($_GET['days'] ?? 30);
if (!isset($allowedRanges[$days])) {
    $days = 30;
}

$hasCouponCol = (bool)stat_all($pdo, "SHOW COLUMNS FROM orders LIKE 'coupon_discount_amount'", $statErrors);
$discExpr = $hasCouponCol ? 'COALESCE(coupon_discount_amount, 0)' : '0';

// Опорная дата — по часам БД, чтобы не расходиться с created_at из-за часовых поясов
$today = (string)(stat_one($pdo, 'SELECT CURDATE() AS d', $statErrors)['d'] ?? date('Y-m-d'));

// ═══ 1. Сводка по периодам ════════════════════════════════════════════════

$periods = [
    'today'      => ['Сегодня',          'created_at >= CURDATE()'],
    'yesterday'  => ['Вчера',            'created_at >= CURDATE() - INTERVAL 1 DAY AND created_at < CURDATE()'],
    'week'       => ['7 дней',           'created_at >= CURDATE() - INTERVAL 6 DAY'],
    'days30'     => ['30 дней',          'created_at >= CURDATE() - INTERVAL 29 DAY'],
    'cur_month'  => ['Текущий месяц',    "created_at >= DATE_FORMAT(CURDATE(), '%Y-%m-01')"],
    'prev_month' => ['Прошлый месяц',    "created_at >= DATE_FORMAT(CURDATE() - INTERVAL 1 MONTH, '%Y-%m-01') AND created_at < DATE_FORMAT(CURDATE(), '%Y-%m-01')"],
    'year'       => ['Текущий год',      'created_at >= MAKEDATE(YEAR(CURDATE()), 1)'],
    'all'        => ['За всё время',     '1 = 1'],
];

$cols = [];
foreach ($periods as $key => [, $cond]) {
    $cols[] = "SUM(CASE WHEN {$cond} THEN 1 ELSE 0 END) AS {$key}_cnt";
    $cols[] = "SUM(CASE WHEN {$cond} THEN total ELSE 0 END) AS {$key}_sum";
    $cols[] = "COUNT(DISTINCT CASE WHEN {$cond} THEN customer_id END) AS {$key}_cust";
    $cols[] = "SUM(CASE WHEN {$cond} THEN {$discExpr} ELSE 0 END) AS {$key}_disc";
}
$periodRow = stat_one($pdo, 'SELECT ' . implode(', ', $cols) . ' FROM orders', $statErrors);

// ═══ 2. Выбранный диапазон vs предыдущий такой же ═════════════════════════

$curCond  = 'created_at >= CURDATE() - INTERVAL ' . ($days - 1) . ' DAY';
$prevCond = 'created_at >= CURDATE() - INTERVAL ' . (2 * $days - 1) . ' DAY AND created_at < CURDATE() - INTERVAL ' . ($days - 1) . ' DAY';
$cmp = stat_one($pdo, "
    SELECT
        SUM(CASE WHEN {$curCond} THEN 1 ELSE 0 END) AS cur_cnt,
        SUM(CASE WHEN {$curCond} THEN total ELSE 0 END) AS cur_sum,
        COUNT(DISTINCT CASE WHEN {$curCond} THEN customer_id END) AS cur_cust,
        SUM(CASE WHEN {$curCond} AND {$discExpr} > 0 THEN 1 ELSE 0 END) AS cur_coupon_orders,
        SUM(CASE WHEN {$curCond} THEN {$discExpr} ELSE 0 END) AS cur_disc,
        SUM(CASE WHEN {$prevCond} THEN 1 ELSE 0 END) AS prev_cnt,
        SUM(CASE WHEN {$prevCond} THEN total ELSE 0 END) AS prev_sum,
        COUNT(DISTINCT CASE WHEN {$prevCond} THEN customer_id END) AS prev_cust
    FROM orders
    WHERE created_at >= CURDATE() - INTERVAL " . (2 * $days - 1) . ' DAY
', $statErrors);

$curCnt  = (int)($cmp['cur_cnt'] ?? 0);
$curSum  = (float)($cmp['cur_sum'] ?? 0);
$prevCnt = (int)($cmp['prev_cnt'] ?? 0);
$prevSum = (float)($cmp['prev_sum'] ?? 0);
$curAvg  = $curCnt > 0 ? $curSum / $curCnt : 0.0;
$prevAvg = $prevCnt > 0 ? $prevSum / $prevCnt : 0.0;

// ═══ 3. Динамика (по дням, а для 12 месяцев — по месяцам) ═════════════════

$byMonth = $days > 90;
if ($byMonth) {
    $rows = stat_all($pdo, "
        SELECT DATE_FORMAT(created_at, '%Y-%m') AS k, COUNT(*) AS cnt, SUM(total) AS sum_total
        FROM orders
        WHERE created_at >= DATE_FORMAT(CURDATE() - INTERVAL 11 MONTH, '%Y-%m-01')
        GROUP BY k
    ", $statErrors);
} else {
    $rows = stat_all($pdo, "
        SELECT DATE(created_at) AS k, COUNT(*) AS cnt, SUM(total) AS sum_total
        FROM orders
        WHERE created_at >= CURDATE() - INTERVAL " . ($days - 1) . " DAY
        GROUP BY k
    ", $statErrors);
}
$series = [];
foreach ($rows as $r) {
    $series[(string)$r['k']] = ['cnt' => (int)$r['cnt'], 'sum' => (float)$r['sum_total']];
}
$chartLabels = [];
$chartOrders = [];
$chartRevenue = [];
$base = new DateTime($today);
if ($byMonth) {
    $base->modify('first day of this month');
    $monthNames = ['01' => 'янв', '02' => 'фев', '03' => 'мар', '04' => 'апр', '05' => 'май', '06' => 'июн', '07' => 'июл', '08' => 'авг', '09' => 'сен', '10' => 'окт', '11' => 'ноя', '12' => 'дек'];
    for ($i = 11; $i >= 0; $i--) {
        $d = (clone $base)->modify("-{$i} month");
        $k = $d->format('Y-m');
        $chartLabels[]  = $monthNames[$d->format('m')] . ' ' . $d->format('y');
        $chartOrders[]  = $series[$k]['cnt'] ?? 0;
        $chartRevenue[] = (int)round($series[$k]['sum'] ?? 0);
    }
} else {
    for ($i = $days - 1; $i >= 0; $i--) {
        $d = (clone $base)->modify("-{$i} day");
        $k = $d->format('Y-m-d');
        $chartLabels[]  = $d->format('d.m');
        $chartOrders[]  = $series[$k]['cnt'] ?? 0;
        $chartRevenue[] = (int)round($series[$k]['sum'] ?? 0);
    }
}

// ═══ 4. Клиенты ═══════════════════════════════════════════════════════════

$cust = stat_one($pdo, "
    SELECT
        COUNT(*) AS total_customers,
        SUM(CASE WHEN created_at >= CURDATE() - INTERVAL " . ($days - 1) . " DAY THEN 1 ELSE 0 END) AS new_in_range,
        SUM(CASE WHEN orders_count > 1 THEN 1 ELSE 0 END) AS repeat_customers,
        COALESCE(SUM(total_spent), 0) AS lifetime_spent
    FROM customers
", $statErrors);
$totalCustomers  = (int)($cust['total_customers'] ?? 0);
$newInRange      = (int)($cust['new_in_range'] ?? 0);
$repeatCustomers = (int)($cust['repeat_customers'] ?? 0);
$repeatShare     = $totalCustomers > 0 ? $repeatCustomers / $totalCustomers * 100 : 0.0;
$allCnt          = (int)($periodRow['all_cnt'] ?? 0);
$allSum          = (float)($periodRow['all_sum'] ?? 0);
$ordersPerCust   = $totalCustomers > 0 ? $allCnt / $totalCustomers : 0.0;
$ltv             = $totalCustomers > 0 ? (float)($cust['lifetime_spent'] ?? 0) / $totalCustomers : 0.0;

$topCustomers = stat_all($pdo, '
    SELECT full_name, phone, orders_count, total_spent, last_order_at
    FROM customers
    ORDER BY total_spent DESC, orders_count DESC
    LIMIT 5
', $statErrors);

// ═══ 5. Товары ════════════════════════════════════════════════════════════

$itemsRow = stat_one($pdo, "
    SELECT COALESCE(SUM(oi.quantity), 0) AS qty
    FROM order_items oi
    INNER JOIN orders o ON o.id = oi.order_id
    WHERE o.created_at >= CURDATE() - INTERVAL " . ($days - 1) . " DAY
", $statErrors);
$itemsQty = (int)($itemsRow['qty'] ?? 0);

$topProducts = stat_all($pdo, "
    SELECT
        MAX(oi.name) AS name,
        MAX(oi.catalog_number) AS catalog_number,
        SUM(oi.quantity) AS qty,
        SUM(oi.price * oi.quantity) AS revenue,
        COUNT(DISTINCT oi.order_id) AS orders_cnt
    FROM order_items oi
    INNER JOIN orders o ON o.id = oi.order_id
    WHERE o.created_at >= CURDATE() - INTERVAL " . ($days - 1) . " DAY
    GROUP BY COALESCE(NULLIF(oi.product_external_id, ''), oi.name)
    ORDER BY revenue DESC, qty DESC
    LIMIT 10
", $statErrors);
$topProductsRevenue = array_sum(array_map(static fn($r) => (float)$r['revenue'], $topProducts));

// ═══ 6. Каналы связи, дни недели, часы ════════════════════════════════════

$methodRows = stat_all($pdo, "
    SELECT COALESCE(NULLIF(contact_method_snapshot, ''), '') AS m, COUNT(*) AS cnt
    FROM orders
    WHERE created_at >= CURDATE() - INTERVAL " . ($days - 1) . " DAY
    GROUP BY m
    ORDER BY cnt DESC
", $statErrors);
$methodNames = ['whatsapp' => 'WhatsApp', 'telegram' => 'Telegram', 'max' => 'MAX', '' => 'Не указан'];
$methodBars = [];
foreach ($methodRows as $r) {
    $cnt = (int)$r['cnt'];
    $methodBars[] = [
        'label' => $methodNames[(string)$r['m']] ?? (string)$r['m'],
        'value' => $cnt,
        'text'  => $cnt . ($curCnt > 0 ? ' (' . round($cnt / $curCnt * 100) . '%)' : ''),
    ];
}

$dowRows = stat_all($pdo, "
    SELECT DAYOFWEEK(created_at) AS dow, COUNT(*) AS cnt, SUM(total) AS sum_total
    FROM orders
    WHERE created_at >= CURDATE() - INTERVAL " . ($days - 1) . " DAY
    GROUP BY dow
", $statErrors);
$dowMap = [];
foreach ($dowRows as $r) {
    $dowMap[(int)$r['dow']] = ['cnt' => (int)$r['cnt'], 'sum' => (float)$r['sum_total']];
}
$dowNames = [2 => 'Понедельник', 3 => 'Вторник', 4 => 'Среда', 5 => 'Четверг', 6 => 'Пятница', 7 => 'Суббота', 1 => 'Воскресенье'];
$dowBars = [];
foreach ($dowNames as $n => $label) {
    $cnt = $dowMap[$n]['cnt'] ?? 0;
    $dowBars[] = ['label' => $label, 'value' => $cnt, 'text' => $cnt . ' · ' . fmt_money($dowMap[$n]['sum'] ?? 0)];
}

$hourRows = stat_all($pdo, "
    SELECT HOUR(created_at) AS h, COUNT(*) AS cnt
    FROM orders
    WHERE created_at >= CURDATE() - INTERVAL " . ($days - 1) . " DAY
    GROUP BY h
", $statErrors);
$hourMap = [];
foreach ($hourRows as $r) {
    $hourMap[(int)$r['h']] = (int)$r['cnt'];
}
$hourLabels = [];
$hourValues = [];
for ($h = 0; $h < 24; $h++) {
    $hourLabels[] = sprintf('%02d:00', $h);
    $hourValues[] = $hourMap[$h] ?? 0;
}

// ═══ 7. Отзывы и проблемные заказы ════════════════════════════════════════

$rev = stat_one($pdo, "
    SELECT
        SUM(CASE WHEN status = 'pending'  THEN 1 ELSE 0 END) AS pending,
        SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) AS approved,
        SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) AS rejected,
        AVG(CASE WHEN status = 'approved' THEN rating END) AS avg_rating
    FROM product_reviews
", $statErrors);

$attn = stat_one($pdo, "
    SELECT
        SUM(CASE WHEN outbound_email_sent = 0 THEN 1 ELSE 0 END) AS email_fail,
        SUM(CASE WHEN outbound_amo_sent = 0 THEN 1 ELSE 0 END) AS amo_fail,
        SUM(CASE WHEN price_verified = 0 THEN 1 ELSE 0 END) AS unverified
    FROM orders
    WHERE created_at >= CURDATE() - INTERVAL " . ($days - 1) . " DAY
", $statErrors);

$recentOrders = stat_all($pdo, '
    SELECT order_number, customer_name_snapshot, total, created_at
    FROM orders
    ORDER BY id DESC
    LIMIT 10
', $statErrors);

$couponShare = $curCnt > 0 ? (int)($cmp['cur_coupon_orders'] ?? 0) / $curCnt * 100 : 0.0;
$rangeLabel  = $allowedRanges[$days];
?>
<!doctype html>
<html lang="ru">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Статистика магазина — Админка</title>
    <link rel="stylesheet" href="/css/bootstrap.min.css">
    <link rel="stylesheet" href="/css/admin.css">
    <style>
        .stat-card { background: #fff; border: 1px solid #dee2e6; border-radius: .5rem; padding: 1rem 1.1rem; height: 100%; }
        .stat-card__label { color: #6c757d; font-size: .8rem; text-transform: uppercase; letter-spacing: .03em; }
        .stat-card__value { font-size: 1.7rem; font-weight: 700; line-height: 1.25; color: #212529; }
        .stat-card__sub { font-size: .8rem; color: #6c757d; margin-top: .15rem; }
        .stat-delta { font-size: .78rem; font-weight: 600; color: #6c757d; }
        .stat-delta--up { color: #198754; }
        .stat-delta--down { color: #dc3545; }
        .stat-section { border-bottom: 2px solid #0d6efd; padding-bottom: .4rem; margin: 2rem 0 1rem; }
        .stat-panel { background: #fff; border: 1px solid #dee2e6; border-radius: .5rem; padding: 1rem 1.1rem; height: 100%; }
        .stat-panel h6 { margin: 0 0 .75rem; font-weight: 600; }
        .stat-chart { width: 100%; height: auto; display: block; }
        .stat-hbar { display: grid; grid-template-columns: 110px 1fr auto; align-items: center; gap: 10px; margin-bottom: 7px; font-size: .9rem; }
        .stat-hbar__track { background: #eef1f4; border-radius: 4px; height: 12px; overflow: hidden; }
        .stat-hbar__fill { height: 100%; border-radius: 4px; }
        .stat-hbar__value { color: #495057; white-space: nowrap; font-size: .85rem; }
        .stat-table th { white-space: nowrap; }
        .stat-table td.num, .stat-table th.num { text-align: right; white-space: nowrap; }
        .stat-attn { display: flex; justify-content: space-between; padding: .4rem 0; border-bottom: 1px dashed #e5e7eb; }
        .stat-attn:last-child { border-bottom: 0; }
        .stat-attn--bad strong { color: #dc3545; }
        @media (max-width: 575px) { .stat-hbar { grid-template-columns: 90px 1fr auto; } .stat-card__value { font-size: 1.4rem; } }
    </style>
</head>

<body>
    <div class="container pb-5">
        <?php require __DIR__ . '/_nav.php'; ?>

        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
            <h3 class="mb-0">Статистика магазина</h3>
            <div class="btn-group" role="group" aria-label="Период">
                <?php foreach ($allowedRanges as $d => $label): ?>
                    <a href="?days=<?= $d ?>" class="btn btn-sm <?= $d === $days ? 'btn-primary' : 'btn-outline-primary' ?>"><?= admin_h($label) ?></a>
                <?php endforeach; ?>
            </div>
        </div>

        <?php if ($statErrors): ?>
            <div class="alert alert-warning">Часть данных недоступна (возможно, не применены SQL-миграции). Подробности в логе <code>log/runtime.log</code>.</div>
        <?php endif; ?>

        <!-- ═══ Ключевые показатели выбранного периода ═══ -->
        <h5 class="stat-section">Ключевые показатели · <?= admin_h($rangeLabel) ?> <small class="text-muted fw-normal">(в сравнении с предыдущими такими же <?= admin_h(mb_strtolower($rangeLabel)) ?>)</small></h5>
        <div class="row g-3">
            <div class="col-6 col-lg-3"><div class="stat-card">
                <div class="stat-card__label">Заказов</div>
                <div class="stat-card__value"><?= fmt_int($curCnt) ?></div>
                <div class="stat-card__sub"><?= delta_badge((float)$curCnt, (float)$prevCnt) ?> было <?= fmt_int($prevCnt) ?></div>
            </div></div>
            <div class="col-6 col-lg-3"><div class="stat-card">
                <div class="stat-card__label">Выручка</div>
                <div class="stat-card__value"><?= fmt_money($curSum) ?></div>
                <div class="stat-card__sub"><?= delta_badge($curSum, $prevSum) ?> было <?= fmt_money($prevSum) ?></div>
            </div></div>
            <div class="col-6 col-lg-3"><div class="stat-card">
                <div class="stat-card__label">Средний чек</div>
                <div class="stat-card__value"><?= fmt_money($curAvg) ?></div>
                <div class="stat-card__sub"><?= delta_badge($curAvg, $prevAvg) ?> было <?= fmt_money($prevAvg) ?></div>
            </div></div>
            <div class="col-6 col-lg-3"><div class="stat-card">
                <div class="stat-card__label">Покупателей</div>
                <div class="stat-card__value"><?= fmt_int($cmp['cur_cust'] ?? 0) ?></div>
                <div class="stat-card__sub"><?= delta_badge((float)($cmp['cur_cust'] ?? 0), (float)($cmp['prev_cust'] ?? 0)) ?> было <?= fmt_int($cmp['prev_cust'] ?? 0) ?></div>
            </div></div>
            <div class="col-6 col-lg-3"><div class="stat-card">
                <div class="stat-card__label">Товаров продано, шт.</div>
                <div class="stat-card__value"><?= fmt_int($itemsQty) ?></div>
                <div class="stat-card__sub"><?= $curCnt > 0 ? number_format($itemsQty / $curCnt, 1, ',', '') . ' шт. в заказе' : '—' ?></div>
            </div></div>
            <div class="col-6 col-lg-3"><div class="stat-card">
                <div class="stat-card__label">Новых клиентов</div>
                <div class="stat-card__value"><?= fmt_int($newInRange) ?></div>
                <div class="stat-card__sub">впервые оформили заказ</div>
            </div></div>
            <div class="col-6 col-lg-3"><div class="stat-card">
                <div class="stat-card__label">Заказы с купоном</div>
                <div class="stat-card__value"><?= number_format($couponShare, 0) ?>%</div>
                <div class="stat-card__sub"><?= $hasCouponCol ? 'скидок выдано ' . fmt_money($cmp['cur_disc'] ?? 0) : 'нет данных о скидках' ?></div>
            </div></div>
            <div class="col-6 col-lg-3"><div class="stat-card">
                <div class="stat-card__label">Заказов в день (в среднем)</div>
                <div class="stat-card__value"><?= number_format($curCnt / $days, 1, ',', '') ?></div>
                <div class="stat-card__sub">выручка в день: <?= fmt_money($curSum / $days) ?></div>
            </div></div>
        </div>

        <!-- ═══ Заказы по периодам ═══ -->
        <h5 class="stat-section">Заказы по периодам</h5>
        <div class="table-responsive">
            <table class="table table-bordered table-striped stat-table align-middle mb-1">
                <thead class="table-primary">
                    <tr>
                        <th>Период</th>
                        <th class="num">Заказов</th>
                        <th class="num">Выручка</th>
                        <th class="num">Средний чек</th>
                        <th class="num">Покупателей</th>
                        <?php if ($hasCouponCol): ?><th class="num">Скидок по купонам</th><?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($periods as $key => [$label]): ?>
                        <?php $c = (int)($periodRow["{$key}_cnt"] ?? 0); $s = (float)($periodRow["{$key}_sum"] ?? 0); ?>
                        <tr>
                            <td><strong><?= admin_h($label) ?></strong></td>
                            <td class="num"><?= fmt_int($c) ?></td>
                            <td class="num"><?= fmt_money($s) ?></td>
                            <td class="num"><?= $c > 0 ? fmt_money($s / $c) : '—' ?></td>
                            <td class="num"><?= fmt_int($periodRow["{$key}_cust"] ?? 0) ?></td>
                            <?php if ($hasCouponCol): ?><td class="num"><?= fmt_money($periodRow["{$key}_disc"] ?? 0) ?></td><?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <p class="text-muted small">Выручка — сумма заказов после скидок. В базе нет статусов оплаты и отмены, поэтому учитываются все оформленные заказы. «7 дней» и «30 дней» считаются вместе с сегодняшним днём.</p>

        <!-- ═══ Динамика ═══ -->
        <h5 class="stat-section">Динамика · <?= admin_h($rangeLabel) ?> <small class="text-muted fw-normal">(<?= $byMonth ? 'по месяцам' : 'по дням' ?>)</small></h5>
        <div class="row g-3">
            <div class="col-lg-6"><div class="stat-panel">
                <h6>Количество заказов</h6>
                <?= render_bar_chart($chartLabels, $chartOrders, '#0d6efd', 'зак.') ?>
            </div></div>
            <div class="col-lg-6"><div class="stat-panel">
                <h6>Выручка, ₽</h6>
                <?= render_bar_chart($chartLabels, $chartRevenue, '#198754', '₽') ?>
            </div></div>
        </div>

        <!-- ═══ Клиенты ═══ -->
        <h5 class="stat-section">Клиенты (за всё время)</h5>
        <div class="row g-3">
            <div class="col-6 col-lg-3"><div class="stat-card">
                <div class="stat-card__label">Всего клиентов</div>
                <div class="stat-card__value"><?= fmt_int($totalCustomers) ?></div>
                <div class="stat-card__sub">уникальные по телефону / e-mail</div>
            </div></div>
            <div class="col-6 col-lg-3"><div class="stat-card">
                <div class="stat-card__label">Повторные покупатели</div>
                <div class="stat-card__value"><?= fmt_int($repeatCustomers) ?></div>
                <div class="stat-card__sub"><?= number_format($repeatShare, 1, ',', '') ?>% от всех клиентов</div>
            </div></div>
            <div class="col-6 col-lg-3"><div class="stat-card">
                <div class="stat-card__label">Заказов на клиента</div>
                <div class="stat-card__value"><?= number_format($ordersPerCust, 2, ',', '') ?></div>
                <div class="stat-card__sub">всего заказов: <?= fmt_int($allCnt) ?></div>
            </div></div>
            <div class="col-6 col-lg-3"><div class="stat-card">
                <div class="stat-card__label">Выручка на клиента (LTV)</div>
                <div class="stat-card__value"><?= fmt_money($ltv) ?></div>
                <div class="stat-card__sub">общая выручка: <?= fmt_money($allSum) ?></div>
            </div></div>
        </div>
        <div class="stat-panel mt-3">
            <h6>Топ-5 клиентов по сумме покупок</h6>
            <?php if ($topCustomers): ?>
                <div class="table-responsive">
                    <table class="table table-sm stat-table mb-0">
                        <thead><tr><th>Клиент</th><th>Телефон</th><th class="num">Заказов</th><th class="num">Потрачено</th><th>Последний заказ</th></tr></thead>
                        <tbody>
                            <?php foreach ($topCustomers as $c): ?>
                                <tr>
                                    <td><?= admin_h((string)$c['full_name']) ?></td>
                                    <td class="text-nowrap"><?= admin_h(format_phone((string)($c['phone'] ?? ''))) ?></td>
                                    <td class="num"><?= (int)$c['orders_count'] ?></td>
                                    <td class="num"><?= fmt_money($c['total_spent']) ?></td>
                                    <td><?= $c['last_order_at'] ? admin_h(date('d.m.Y H:i', strtotime((string)$c['last_order_at']))) : '—' ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <p class="text-muted mb-0">Нет данных.</p>
            <?php endif; ?>
        </div>

        <!-- ═══ Товары ═══ -->
        <h5 class="stat-section">Топ-10 товаров · <?= admin_h($rangeLabel) ?></h5>
        <?php if ($topProducts): ?>
            <div class="table-responsive">
                <table class="table table-bordered table-striped stat-table align-middle">
                    <thead class="table-primary">
                        <tr><th>#</th><th>Товар</th><th class="num">Продано, шт.</th><th class="num">В заказах</th><th class="num">Выручка</th><th class="num">Доля топа</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($topProducts as $i => $p): ?>
                            <tr>
                                <td><?= $i + 1 ?></td>
                                <td><?= admin_h(trim((string)($p['catalog_number'] ?? '') . ' ' . (string)$p['name'])) ?></td>
                                <td class="num"><?= fmt_int($p['qty']) ?></td>
                                <td class="num"><?= fmt_int($p['orders_cnt']) ?></td>
                                <td class="num"><?= fmt_money($p['revenue']) ?></td>
                                <td class="num"><?= $topProductsRevenue > 0 ? number_format((float)$p['revenue'] / $topProductsRevenue * 100, 1, ',', '') . '%' : '—' ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <p class="text-muted">За выбранный период заказов нет.</p>
        <?php endif; ?>

        <!-- ═══ Каналы и активность ═══ -->
        <h5 class="stat-section">Каналы и активность · <?= admin_h($rangeLabel) ?></h5>
        <div class="row g-3">
            <div class="col-lg-4"><div class="stat-panel">
                <h6>Предпочитаемый способ связи</h6>
                <?= render_hbars($methodBars, '#6f42c1') ?>
            </div></div>
            <div class="col-lg-8"><div class="stat-panel">
                <h6>Заказы по дням недели</h6>
                <?= render_hbars($dowBars, '#0d6efd') ?>
            </div></div>
            <div class="col-12"><div class="stat-panel">
                <h6>Заказы по часам суток</h6>
                <?= render_bar_chart($hourLabels, $hourValues, '#fd7e14', 'зак.') ?>
            </div></div>
        </div>

        <!-- ═══ Отзывы, внимание, последние заказы ═══ -->
        <h5 class="stat-section">Отзывы и контроль</h5>
        <div class="row g-3">
            <div class="col-lg-4"><div class="stat-panel">
                <h6>Отзывы</h6>
                <div class="stat-attn"><span>На проверке</span><strong><a href="/admin/reviews.php?status=pending"><?= (int)($rev['pending'] ?? 0) ?></a></strong></div>
                <div class="stat-attn"><span>Опубликовано</span><strong><a href="/admin/reviews.php?status=approved"><?= (int)($rev['approved'] ?? 0) ?></a></strong></div>
                <div class="stat-attn"><span>Отклонено</span><strong><a href="/admin/reviews.php?status=rejected"><?= (int)($rev['rejected'] ?? 0) ?></a></strong></div>
                <div class="stat-attn"><span>Средняя оценка</span><strong><?= isset($rev['avg_rating']) && $rev['avg_rating'] !== null ? number_format((float)$rev['avg_rating'], 2, ',', '') . ' / 5' : '—' ?></strong></div>
            </div></div>
            <div class="col-lg-4"><div class="stat-panel">
                <h6>Требуют внимания · <?= admin_h($rangeLabel) ?></h6>
                <?php $ef = (int)($attn['email_fail'] ?? 0); $af = (int)($attn['amo_fail'] ?? 0); $uv = (int)($attn['unverified'] ?? 0); ?>
                <div class="stat-attn<?= $ef > 0 ? ' stat-attn--bad' : '' ?>"><span>Письмо клиенту не доставлено</span><strong><?= $ef ?></strong></div>
                <div class="stat-attn<?= $af > 0 ? ' stat-attn--bad' : '' ?>"><span>Не ушло в AmoCRM</span><strong><?= $af ?></strong></div>
                <div class="stat-attn<?= $uv > 0 ? ' stat-attn--bad' : '' ?>"><span>Цены не подтверждены базой</span><strong><?= $uv ?></strong></div>
            </div></div>
            <div class="col-lg-4"><div class="stat-panel">
                <h6>Последние заказы</h6>
                <?php if ($recentOrders): ?>
                    <table class="table table-sm stat-table mb-0">
                        <tbody>
                            <?php foreach ($recentOrders as $o): ?>
                                <tr>
                                    <td>OLA-<?= (int)$o['order_number'] ?></td>
                                    <td><?= admin_h((string)$o['customer_name_snapshot']) ?></td>
                                    <td class="num"><?= fmt_money($o['total']) ?></td>
                                    <td class="num text-muted"><?= admin_h(date('d.m H:i', strtotime((string)$o['created_at']))) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p class="text-muted mb-0">Заказов пока нет.</p>
                <?php endif; ?>
            </div></div>
        </div>

        <p class="text-muted small mt-4">Статистика купонов теперь в разделе <a href="/admin/coupons.php">Купоны</a> → <a href="/admin/coupon_stats.php">Статистика купонов</a>.</p>
    </div>
</body>

</html>
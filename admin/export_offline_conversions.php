<?php

declare(strict_types=1);

// admin/export_offline_conversions.php
//
// Экспорт заказов в формате офлайн-конверсий Яндекс.Метрики (CSV: ClientId,Target,DateTime,Price,Currency).
// Нужен как страховка на случай, когда браузерный dataLayer-пуш purchase (см. success.php)
// не долетает до Метрики — блокировщики рекламы, отключённый JS, ушедшая сессия и т.п.
// Так браузерный dataLayer-purchase остаётся основным и мгновенным способом, а этот
// экспорт — регулярной сверкой/добивкой недостающих заказов.
//
// ПЕРЕД ИСПОЛЬЗОВАНИЕМ (сделать вручную в интерфейсе Метрики, это не автоматизируется кодом):
//   1. Счётчик → Настройки → Цели → добавить цель типа «JavaScript-событие»
//      с идентификатором, который укажете ниже в OFFLINE_CONVERSION_TARGET.
//   2. Счётчик → Настройки → Загрузка данных → включить «Учёт офлайн-конверсий».
//   3. Полученный здесь CSV загрузить там же (Загрузка данных → Загрузить офлайн-конверсии)
//      — либо руками, либо через POST-запрос к
//      https://api-metrika.yandex.net/management/v1/counter/{counterId}/offline_conversions/upload
//      с заголовком Authorization: OAuth {ваш токен} (получить в https://oauth.yandex.ru).
//
// Данные обрабатываются Метрикой до 2 часов, поэтому недельного/дневного крона достаточно.

require __DIR__ . '/_bootstrap.php';
admin_require_auth();

// Идентификатор цели в Метрике, созданной по шагу 1 выше — ОБЯЗАТЕЛЬНО поменять
// на реальный после создания цели, иначе загрузка в Метрике будет отклонена.
const OFFLINE_CONVERSION_TARGET = 'purchase';

$pdo = dev_db_connection();
if (!($pdo instanceof PDO)) {
    http_response_code(500);
    echo 'Нет соединения с базой данных';
    exit;
}

// Период по умолчанию — последние 7 дней; можно переопределить через ?from=YYYY-MM-DD&to=YYYY-MM-DD
$from = isset($_GET['from']) && $_GET['from'] !== '' ? $_GET['from'] : date('Y-m-d', strtotime('-7 days'));
$to   = isset($_GET['to'])   && $_GET['to']   !== '' ? $_GET['to']   : date('Y-m-d', strtotime('+1 day'));

$stmt = $pdo->prepare(
    'SELECT order_number, total, raw_payload, created_at
     FROM orders
     WHERE created_at >= :from AND created_at < :to
     ORDER BY created_at ASC'
);
$stmt->execute(['from' => $from, 'to' => $to]);

// Декодируем raw_payload в PHP, а не через JSON_EXTRACT в SQL — проект рассчитан
// на MariaDB (см. app/admin/coupon_stats.php), где поддержка JSON-функций менее
// стандартна, чем в MySQL, так надёжнее.
$rows = [];
while ($order = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $raw = $order['raw_payload'] ? json_decode((string)$order['raw_payload'], true) : null;
    $clientId = is_array($raw) ? (string)($raw['ym_client_id'] ?? '') : '';

    if ($clientId === '') {
        // Нет ClientID — заказ оформлен без Метрики на клиенте (блокировщик, бот и т.п.),
        // сматчить с визитом нечем, пропускаем.
        continue;
    }

    $rows[] = [
        'ClientId' => $clientId,
        'Target'   => OFFLINE_CONVERSION_TARGET,
        'DateTime' => (string) strtotime((string)$order['created_at']),
        'Price'    => (string) (float)$order['total'],
        'Currency' => 'RUB',
    ];
}

$filename = 'offline_conversions_' . date('Ymd_His') . '.csv';

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

$out = fopen('php://output', 'w');
fputcsv($out, ['ClientId', 'Target', 'DateTime', 'Price', 'Currency']);
foreach ($rows as $row) {
    fputcsv($out, $row);
}
fclose($out);
exit;

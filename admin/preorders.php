<?php
declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';
admin_require_permission('preorders', 'view');
$pdo = dev_db_connection();
$message = null;
$error = null;
$allowedStatuses = [
    'new' => 'Новая',
    'in_progress' => 'В работе',
    'processed' => 'Обработана',
    'cancelled' => 'Отменена',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    admin_require_permission('preorders', 'edit');
    if (!validate_csrf_token()) {
        $error = 'Сессия формы устарела. Обновите страницу.';
    } else {
        $id = (int)($_POST['id'] ?? 0);
        $status = (string)($_POST['status'] ?? 'new');
        $comment = trim((string)($_POST['manager_comment'] ?? ''));
        if ($id <= 0 || !isset($allowedStatuses[$status])) {
            $error = 'Некорректные параметры заявки.';
        } else {
            try {
                $stmt = $pdo->prepare(
                    'UPDATE preorder_requests
                     SET status = :status,
                         manager_comment = :manager_comment,
                         processed_at = CASE WHEN :processed_status IN ("processed", "cancelled") THEN CURRENT_TIMESTAMP ELSE NULL END,
                         processed_by = CASE WHEN :processed_by_status IN ("processed", "cancelled") THEN :admin_id ELSE NULL END
                     WHERE id = :id'
                );
                $stmt->execute([
                    'status' => $status,
                    'manager_comment' => $comment !== '' ? $comment : null,
                    'processed_status' => $status,
                    'processed_by_status' => $status,
                    'admin_id' => (int)(admin_current_user()['id'] ?? 0),
                    'id' => $id,
                ]);
                $message = 'Заявка обновлена.';
            } catch (Throwable $e) {
                dev_log_runtime('Preorder admin update failed: ' . $e->getMessage());
                $error = 'Не удалось обновить заявку. Проверьте, применена ли миграция preorder_requests.';
            }
        }
    }
}

$filter = (string)($_GET['status'] ?? 'all');
if ($filter !== 'all' && !isset($allowedStatuses[$filter])) $filter = 'all';
$requests = [];
try {
    $sql = 'SELECT r.*, p.link AS product_link, u.username AS processed_by_name
            FROM preorder_requests r
            LEFT JOIN products p ON p.id = r.product_id
            LEFT JOIN admin_users u ON u.id = r.processed_by';
    $params = [];
    if ($filter !== 'all') {
        $sql .= ' WHERE r.status = :filter_status';
        $params['filter_status'] = $filter;
    }
    $sql .= ' ORDER BY r.created_at DESC, r.id DESC LIMIT 300';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $requests = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $error = $error ?: 'Таблица заявок ещё не создана. Примените database/migration_preorder_requests.sql.';
}
?>
<!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Товары под заказ</title>
    <link rel="stylesheet" href="/css/bootstrap.min.css">
    <link rel="stylesheet" href="/css/admin.css">
    <style>
        .preorder-card { background:#fff; border:1px solid #ddd; border-radius:6px; margin:18px 0; padding:18px; }
        .preorder-table td { vertical-align:top; }
        .preorder-customer { min-width:190px; }
        .preorder-actions { min-width:230px; }
        .preorder-actions textarea { min-height:60px; margin:6px 0; }
        @media (max-width:767px) { .preorder-table { font-size:12px; } .preorder-card { padding:10px; } }
    </style>
</head>
<body>
<div class="container">
    <?php require __DIR__ . '/_nav.php'; ?>
    <h3>Товары под заказ</h3>
    <?php if ($message): ?><div class="alert alert-success"><?= admin_h($message) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert alert-warning"><?= admin_h($error) ?></div><?php endif; ?>
    <div class="preorder-card">
        <p>Заявки из этого раздела не являются обычными заказами и не отправляются в AmoCRM или Bitrix CRM.</p>
        <div class="btn-group" role="group" aria-label="Фильтр заявок">
            <a class="btn btn-<?= $filter === 'all' ? 'primary' : 'default' ?> btn-sm" href="?status=all">Все</a>
            <?php foreach ($allowedStatuses as $statusValue => $statusLabel): ?>
                <a class="btn btn-<?= $filter === $statusValue ? 'primary' : 'default' ?> btn-sm" href="?status=<?= admin_h($statusValue) ?>"><?= admin_h($statusLabel) ?></a>
            <?php endforeach; ?>
        </div>
    </div>
    <div class="table-responsive preorder-card">
        <table class="table table-bordered table-striped preorder-table">
            <thead><tr><th>Дата</th><th>Товар</th><th>Клиент</th><th>Статус и комментарий</th></tr></thead>
            <tbody>
            <?php if (!$requests): ?><tr><td colspan="4">Заявок пока нет.</td></tr><?php endif; ?>
            <?php foreach ($requests as $request): ?>
                <?php $requestStatus = (string)$request['status']; ?>
                <tr>
                    <td><?= admin_h((string)$request['created_at']) ?><br><small>#<?= (int)$request['id'] ?></small></td>
                    <td><strong><?= admin_h((string)$request['product_name_snapshot']) ?></strong><br><small>Артикул: <?= admin_h((string)($request['product_cat_number_snapshot'] ?: '—')) ?></small><?php if (!empty($request['product_link'])): ?><br><a href="<?= admin_h((string)$request['product_link']) ?>" target="_blank" rel="noopener">Открыть товар</a><?php endif; ?></td>
                    <td class="preorder-customer"><strong><?= admin_h((string)$request['customer_name']) ?></strong><br><?= admin_h((string)$request['customer_phone']) ?><br><?= admin_h((string)($request['customer_email'] ?: 'email не указан')) ?></td>
                    <td class="preorder-actions">
                        <?php if (admin_can('preorders', 'edit')): ?><form method="post">
                            <input type="hidden" name="csrf_token" value="<?= admin_h(csrf_token()) ?>">
                            <input type="hidden" name="id" value="<?= (int)$request['id'] ?>">
                            <select name="status" class="form-control input-sm">
                                <?php foreach ($allowedStatuses as $statusValue => $statusLabel): ?><option value="<?= admin_h($statusValue) ?>"<?= $requestStatus === $statusValue ? ' selected' : '' ?>><?= admin_h($statusLabel) ?></option><?php endforeach; ?>
                            </select>
                            <textarea name="manager_comment" class="form-control input-sm" placeholder="Комментарий менеджера"><?= admin_h((string)($request['manager_comment'] ?? '')) ?></textarea>
                            <button type="submit" class="btn btn-primary btn-sm">Сохранить</button>
                        </form><?php else: ?><strong><?= admin_h($allowedStatuses[$requestStatus] ?? $requestStatus) ?></strong><br><?= admin_h((string)($request['manager_comment'] ?? '')) ?><?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
</body>
</html>

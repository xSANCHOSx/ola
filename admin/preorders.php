<?php

declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';
admin_require_permission('products', 'view');
$pdo = dev_db_connection();
$message = null;
$error = null;
$archiveSchemaReady = false;
try {
    $archiveSchemaStmt = $pdo->query(
        'SELECT COUNT(*)
         FROM information_schema.columns
         WHERE table_schema = DATABASE()
           AND table_name = "preorder_requests"
           AND column_name IN ("is_archived", "archived_at", "archived_by")'
    );
    $archiveSchemaReady = (int)$archiveSchemaStmt->fetchColumn() === 3;
} catch (Throwable $e) {
    $archiveSchemaReady = false;
}
$allowedStatuses = [
    'new' => 'Новая',
    'in_progress' => 'В работе',
    'processed' => 'Обработана',
    'cancelled' => 'Отменена',
];

$action = (string)($_POST['action'] ?? '');
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    admin_require_permission('products', 'edit');
    if (!validate_csrf_token()) {
        $error = 'Сессия формы устарела. Обновите страницу.';
    } else {
        $id = (int)($_POST['id'] ?? 0);
        try {
            if ($id <= 0) {
                throw new InvalidArgumentException('Некорректный ID заявки.');
            }

            if ($action === 'update') {
                $status = (string)($_POST['status'] ?? 'new');
                $comment = trim((string)($_POST['manager_comment'] ?? ''));
                if (!isset($allowedStatuses[$status])) {
                    throw new InvalidArgumentException('Некорректный статус заявки.');
                }
                $stmt = $pdo->prepare(
                    'UPDATE preorder_requests
                     SET status = :status_set,
                         manager_comment = :manager_comment,
                         processed_at = CASE WHEN :status_processed IN ("processed", "cancelled") THEN NOW() ELSE NULL END,
                         processed_by = CASE WHEN :status_user IN ("processed", "cancelled") THEN :admin_id ELSE NULL END
                     WHERE id = :id'
                );
                $stmt->execute([
                    'status_set' => $status,
                    'manager_comment' => $comment !== '' ? $comment : null,
                    'status_processed' => $status,
                    'status_user' => $status,
                    'admin_id' => (int)(admin_current_user()['id'] ?? 0),
                    'id' => $id,
                ]);
                $message = 'Заявка обновлена.';
            } elseif ($action === 'archive' || $action === 'unarchive') {
                if (!$archiveSchemaReady) {
                    throw new RuntimeException('Сначала примените database/migration_preorder_archive.sql.');
                }
                $archived = $action === 'archive' ? 1 : 0;
                $stmt = $pdo->prepare(
                    'UPDATE preorder_requests
                     SET is_archived = :is_archived,
                         archived_at = CASE WHEN :archived_flag = 1 THEN NOW() ELSE NULL END,
                         archived_by = CASE WHEN :archived_user_flag = 1 THEN :admin_id ELSE NULL END
                     WHERE id = :id'
                );
                $stmt->execute([
                    'is_archived' => $archived,
                    'archived_flag' => $archived,
                    'archived_user_flag' => $archived,
                    'admin_id' => (int)(admin_current_user()['id'] ?? 0),
                    'id' => $id,
                ]);
                $message = $archived ? 'Заявка отправлена в архив.' : 'Заявка восстановлена из архива.';
            } elseif ($action === 'delete') {
                if (!admin_is_admin()) {
                    throw new RuntimeException('Окончательное удаление доступно только администратору.');
                }
                $stmt = $pdo->prepare('DELETE FROM preorder_requests WHERE id = :id');
                $stmt->execute(['id' => $id]);
                $message = 'Заявка удалена.';
            } else {
                throw new InvalidArgumentException('Неизвестное действие.');
            }
        } catch (InvalidArgumentException | RuntimeException $e) {
            $error = $e->getMessage();
        } catch (Throwable $e) {
            dev_log_runtime('Preorder admin action failed: ' . $e->getMessage());
            $error = 'Не удалось изменить заявку. Проверьте, применены ли миграции preorder_requests.';
        }
    }
}

$filter = (string)($_GET['status'] ?? 'all');
if ($filter !== 'all' && !isset($allowedStatuses[$filter])) {
    $filter = 'all';
}
$view = (string)($_GET['view'] ?? 'active');
if (!in_array($view, ['active', 'archived', 'all'], true)) {
    $view = 'active';
}
if (!$archiveSchemaReady && $view !== 'active') {
    $view = 'active';
    $error = $error ?: 'Архив пока недоступен: примените database/migration_preorder_archive.sql. Текущие заявки продолжают отображаться.';
}
$requests = [];
$counts = ['active' => 0, 'archived' => 0];
try {
    if ($archiveSchemaReady) {
        $countStmt = $pdo->query(
            'SELECT is_archived, COUNT(*) AS total
             FROM preorder_requests
             GROUP BY is_archived'
        );
        foreach ($countStmt->fetchAll(PDO::FETCH_ASSOC) as $countRow) {
            $counts[(int)$countRow['is_archived'] === 1 ? 'archived' : 'active'] = (int)$countRow['total'];
        }
    } else {
        $counts['active'] = (int)$pdo->query('SELECT COUNT(*) FROM preorder_requests')->fetchColumn();
    }

    $conditions = [];
    $params = [];
    if ($archiveSchemaReady && $view === 'active') {
        $conditions[] = 'r.is_archived = 0';
    } elseif ($archiveSchemaReady && $view === 'archived') {
        $conditions[] = 'r.is_archived = 1';
    }
    if ($filter !== 'all') {
        $conditions[] = 'r.status = :status';
        $params['status'] = $filter;
    }
    $archiveSelect = $archiveSchemaReady
        ? ', au.username AS archived_by_name'
        : ', 0 AS is_archived, NULL AS archived_at, NULL AS archived_by, NULL AS archived_by_name';
    $archiveJoin = $archiveSchemaReady
        ? ' LEFT JOIN admin_users au ON au.id = r.archived_by'
        : '';
    $sql = 'SELECT r.*, p.link AS product_link, u.username AS processed_by_name' . $archiveSelect . '
            FROM preorder_requests r
            LEFT JOIN products p ON p.id = r.product_id
            LEFT JOIN admin_users u ON u.id = r.processed_by' . $archiveJoin;
    if ($conditions) {
        $sql .= ' WHERE ' . implode(' AND ', $conditions);
    }
    $sql .= ' ORDER BY r.created_at DESC, r.id DESC LIMIT 300';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $requests = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $error = $error ?: 'Таблица заявок ещё не создана или не обновлена. Примените database/migration_preorder_requests.sql и database/migration_preorder_archive.sql.';
}

function preorder_admin_url(string $view, string $status = 'all'): string
{
    return '?view=' . rawurlencode($view) . '&status=' . rawurlencode($status);
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
        .preorder-tabs { display:flex; flex-wrap:wrap; gap:8px; margin:0 0 14px; }
        .preorder-tabs a { border:1px solid #d5d5d5; border-radius:4px; padding:8px 12px; text-decoration:none; }
        .preorder-tabs a.active { background:#337ab7; border-color:#337ab7; color:#fff; }
        .preorder-tabs a.archive { border-color:#8a6d3b; color:#8a6d3b; }
        .preorder-tabs a.archive.active { background:#8a6d3b; color:#fff; }
        .preorder-tabs__disabled { color:#999; padding:8px 12px; }
        .preorder-filter { display:flex; flex-wrap:wrap; align-items:center; gap:8px; margin-top:12px; }
        .preorder-filter a { color:#555; padding:5px 9px; text-decoration:none; }
        .preorder-filter a.active { background:#eee; border-radius:4px; font-weight:600; }
        .preorder-count { display:inline-block; min-width:18px; padding:1px 5px; border-radius:9px; background:#eee; font-size:11px; text-align:center; }
        .preorder-table td { vertical-align:top; }
        .preorder-customer { min-width:190px; }
        .preorder-actions { min-width:260px; }
        .preorder-actions textarea { min-height:60px; margin:6px 0; }
        .preorder-actions__row { display:flex; flex-wrap:wrap; gap:6px; margin-top:6px; }
        .preorder-actions__row form { display:inline; }
        .preorder-archived { opacity:.78; }
        .preorder-help { color:#666; margin:0; }
        @media (max-width:767px) { .preorder-table { font-size:12px; } .preorder-card { padding:10px; } .preorder-actions { min-width:180px; } }
    </style>
</head>
<body>
<div class="container">
    <?php require __DIR__ . '/_nav.php'; ?>
    <h3>Товары под заказ</h3>
    <?php if ($message): ?><div class="alert alert-success"><?= admin_h($message) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert alert-warning"><?= admin_h($error) ?></div><?php endif; ?>
    <div class="preorder-card">
        <p class="preorder-help">Заявки из этого раздела не являются обычными заказами и не отправляются в AmoCRM или Bitrix CRM.</p>
        <div class="preorder-tabs" role="tablist" aria-label="Раздел заявок">
            <a class="<?= $view === 'active' ? 'active' : '' ?>" href="<?= admin_h(preorder_admin_url('active', $filter)) ?>">Активные <span class="preorder-count"><?= $counts['active'] ?></span></a>
            <?php if ($archiveSchemaReady): ?>
                <a class="archive <?= $view === 'archived' ? 'active' : '' ?>" href="<?= admin_h(preorder_admin_url('archived', $filter)) ?>">Архив <span class="preorder-count"><?= $counts['archived'] ?></span></a>
                <a class="<?= $view === 'all' ? 'active' : '' ?>" href="<?= admin_h(preorder_admin_url('all', $filter)) ?>">Все</a>
            <?php else: ?>
                <span class="preorder-tabs__disabled">Архив появится после миграции</span>
            <?php endif; ?>
        </div>
        <nav class="preorder-filter" aria-label="Фильтр по статусу">
            <strong>Статус:</strong>
            <a class="<?= $filter === 'all' ? 'active' : '' ?>" href="<?= admin_h(preorder_admin_url($view, 'all')) ?>">Все</a>
            <?php foreach ($allowedStatuses as $statusValue => $statusLabel): ?>
                <a class="<?= $filter === $statusValue ? 'active' : '' ?>" href="<?= admin_h(preorder_admin_url($view, $statusValue)) ?>"><?= admin_h($statusLabel) ?></a>
            <?php endforeach; ?>
        </nav>
    </div>
    <div class="table-responsive preorder-card">
        <table class="table table-bordered table-striped preorder-table">
            <thead><tr><th>Дата</th><th>Товар</th><th>Клиент</th><th>Статус и действия</th></tr></thead>
            <tbody>
            <?php if (!$requests): ?><tr><td colspan="4">В этом разделе заявок нет.</td></tr><?php endif; ?>
            <?php foreach ($requests as $request): ?>
                <tr class="<?= !empty($request['is_archived']) ? 'preorder-archived' : '' ?>">
                    <td><?= admin_h((string)$request['created_at']) ?><br><small>#<?= (int)$request['id'] ?></small><?php if (!empty($request['is_archived'])): ?><br><small>В архиве</small><?php endif; ?></td>
                    <td><strong><?= admin_h((string)$request['product_name_snapshot']) ?></strong><br><small>Артикул: <?= admin_h((string)($request['product_cat_number_snapshot'] ?: '—')) ?></small><?php if (!empty($request['product_link'])): ?><br><a href="<?= admin_h((string)$request['product_link']) ?>" target="_blank" rel="noopener">Открыть товар</a><?php endif; ?></td>
                    <td class="preorder-customer"><strong><?= admin_h((string)$request['customer_name']) ?></strong><br><?= admin_h((string)$request['customer_phone']) ?><br><?= admin_h((string)($request['customer_email'] ?: 'email не указан')) ?></td>
                    <td class="preorder-actions">
                        <form method="post">
                            <input type="hidden" name="csrf_token" value="<?= admin_h(csrf_token()) ?>">
                            <input type="hidden" name="action" value="update">
                            <input type="hidden" name="id" value="<?= (int)$request['id'] ?>">
                            <select name="status" class="form-control input-sm">
                                <?php foreach ($allowedStatuses as $statusValue => $statusLabel): ?><option value="<?= admin_h($statusValue) ?>"<?= $request['status'] === $statusValue ? ' selected' : '' ?>><?= admin_h($statusLabel) ?></option><?php endforeach; ?>
                            </select>
                            <textarea name="manager_comment" class="form-control input-sm" placeholder="Комментарий менеджера"><?= admin_h((string)($request['manager_comment'] ?? '')) ?></textarea>
                            <button type="submit" class="btn btn-primary btn-sm">Сохранить</button>
                        </form>
                        <div class="preorder-actions__row">
                            <?php if ($archiveSchemaReady): ?>
                                <form method="post">
                                    <input type="hidden" name="csrf_token" value="<?= admin_h(csrf_token()) ?>">
                                    <input type="hidden" name="action" value="<?= !empty($request['is_archived']) ? 'unarchive' : 'archive' ?>">
                                    <input type="hidden" name="id" value="<?= (int)$request['id'] ?>">
                                    <button type="submit" class="btn btn-default btn-sm"><?= !empty($request['is_archived']) ? 'Вернуть' : 'В архив' ?></button>
                                </form>
                            <?php endif; ?>
                            <?php if (admin_is_admin()): ?>
                                <form method="post" onsubmit="return confirm('Удалить заявку навсегда? Это действие нельзя отменить.');">
                                    <input type="hidden" name="csrf_token" value="<?= admin_h(csrf_token()) ?>">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= (int)$request['id'] ?>">
                                    <button type="submit" class="btn btn-danger btn-sm">Удалить</button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
</body>
</html>

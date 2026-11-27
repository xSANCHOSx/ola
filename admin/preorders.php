<?php

declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';
admin_require_permission('preorders', 'view');
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
    admin_require_permission('preorders', 'edit');
    if (!validate_csrf_token()) {
        $error = 'Сессия формы устарела. Обновите страницу.';
    } else {
        try {
            $bulkActions = ['bulk_archive', 'bulk_unarchive', 'bulk_delete'];
            if (in_array($action, $bulkActions, true)) {
                $ids = array_values(array_unique(array_filter(array_map('intval', (array)($_POST['ids'] ?? [])), static function (int $id): bool {
                    return $id > 0;
                })));
                if (!$ids) {
                    throw new InvalidArgumentException('Выберите хотя бы одну заявку.');
                }
                if ($action === 'bulk_delete' && !admin_is_admin()) {
                    throw new RuntimeException('Окончательное удаление доступно только администратору.');
                }
                if (in_array($action, ['bulk_archive', 'bulk_unarchive'], true) && !$archiveSchemaReady) {
                    throw new RuntimeException('Архив заявок пока не готов. Обновите страницу позже или обратитесь к администратору.');
                }
                $placeholders = [];
                $bulkParams = [];
                foreach ($ids as $index => $bulkId) {
                    $placeholder = ':bulk_id_' . $index;
                    $placeholders[] = $placeholder;
                    $bulkParams[ltrim($placeholder, ':')] = $bulkId;
                }
                $idList = implode(', ', $placeholders);
                if ($action === 'bulk_delete') {
                    $bulkStmt = $pdo->prepare('DELETE FROM preorder_requests WHERE id IN (' . $idList . ')');
                } else {
                    $archived = $action === 'bulk_archive' ? 1 : 0;
                    $bulkStmt = $pdo->prepare(
                        'UPDATE preorder_requests
                         SET is_archived = :bulk_archived,
                             archived_at = CASE WHEN :bulk_archived_at = 1 THEN NOW() ELSE NULL END,
                             archived_by = CASE WHEN :bulk_archived_by = 1 THEN :bulk_admin_id ELSE NULL END
                         WHERE id IN (' . $idList . ')'
                    );
                    $bulkParams['bulk_archived'] = $archived;
                    $bulkParams['bulk_archived_at'] = $archived;
                    $bulkParams['bulk_archived_by'] = $archived;
                    $bulkParams['bulk_admin_id'] = (int)(admin_current_user()['id'] ?? 0);
                }
                $bulkStmt->execute($bulkParams);
                $message = $action === 'bulk_delete'
                    ? 'Выбранные заявки удалены.'
                    : ($action === 'bulk_archive' ? 'Выбранные заявки отправлены в архив.' : 'Выбранные заявки восстановлены.');
            } else {
                $id = (int)($_POST['id'] ?? 0);
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
                    throw new RuntimeException('Архив заявок пока не готов. Обновите страницу позже или обратитесь к администратору.');
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
    $error = $error ?: 'Архив пока недоступен. Повторите попытку позже; текущие заявки продолжают отображаться.';
}
$search = trim((string)($_GET['q'] ?? ''));
if (mb_strlen($search) > 120) {
    $search = mb_substr($search, 0, 120);
}
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = (int)($_GET['per_page'] ?? 50);
if (!in_array($perPage, [25, 50, 100], true)) {
    $perPage = 50;
}
$requests = [];
$counts = ['active' => 0, 'archived' => 0];
$totalRequests = 0;
$totalPages = 1;
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
    if ($search !== '') {
        $conditions[] = '(r.product_name_snapshot LIKE :search
            OR r.product_external_id LIKE :search
            OR r.customer_name LIKE :search
            OR r.customer_phone LIKE :search
            OR r.customer_email LIKE :search)';
        $params['search'] = '%' . $search . '%';
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
    $countSql = 'SELECT COUNT(*) FROM preorder_requests r';
    if ($conditions) {
        $countSql .= ' WHERE ' . implode(' AND ', $conditions);
    }
    $countStmt = $pdo->prepare($countSql);
    $countStmt->execute($params);
    $totalRequests = (int)$countStmt->fetchColumn();
    $totalPages = max(1, (int)ceil($totalRequests / $perPage));
    if ($page > $totalPages) {
        $page = $totalPages;
    }
    $offset = ($page - 1) * $perPage;
    $sql .= ' ORDER BY r.created_at DESC, r.id DESC LIMIT ' . $perPage . ' OFFSET ' . $offset;
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $requests = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $error = $error ?: 'Не удалось загрузить заявки. Повторите попытку позже или обратитесь к администратору.';
}

function preorder_admin_url(string $view, string $status = 'all', string $search = '', int $page = 1, int $perPage = 50): string
{
    $query = '?view=' . rawurlencode($view) . '&status=' . rawurlencode($status)
        . '&page=' . $page . '&per_page=' . $perPage;
    if ($search !== '') {
        $query .= '&q=' . rawurlencode($search);
    }
    return $query;
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
        .preorder-search { align-items:center; display:flex; flex-wrap:wrap; gap:8px; margin:14px 0 4px; }
        .preorder-search input[type=search] { flex:1 1 280px; max-width:420px; padding:6px 9px; }
        .preorder-search select { padding:6px 9px; }
        .preorder-count { display:inline-block; min-width:18px; padding:1px 5px; border-radius:9px; background:#eee; font-size:11px; text-align:center; }
        .preorder-bulkbar { align-items:center; display:flex; flex-wrap:wrap; gap:8px; margin-bottom:10px; }
        .preorder-pagination { display:flex; flex-wrap:wrap; gap:14px; justify-content:flex-end; margin-top:14px; }
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
            <a class="<?= $view === 'active' ? 'active' : '' ?>" href="<?= admin_h(preorder_admin_url('active', $filter, $search, 1, $perPage)) ?>">Активные <span class="preorder-count"><?= $counts['active'] ?></span></a>
            <?php if ($archiveSchemaReady): ?>
                <a class="archive <?= $view === 'archived' ? 'active' : '' ?>" href="<?= admin_h(preorder_admin_url('archived', $filter, $search, 1, $perPage)) ?>">Архив <span class="preorder-count"><?= $counts['archived'] ?></span></a>
                <a class="<?= $view === 'all' ? 'active' : '' ?>" href="<?= admin_h(preorder_admin_url('all', $filter, $search, 1, $perPage)) ?>">Все</a>
            <?php else: ?>
                <span class="preorder-tabs__disabled">Архив появится после миграции</span>
            <?php endif; ?>
        </div>
        <form class="preorder-search" method="get">
            <input type="hidden" name="view" value="<?= admin_h($view) ?>">
            <input type="hidden" name="status" value="<?= admin_h($filter) ?>">
            <label for="preorderSearch">Поиск:</label>
            <input id="preorderSearch" type="search" name="q" value="<?= admin_h($search) ?>" placeholder="Имя, телефон, email, товар или артикул">
            <label for="preorderPerPage">На странице:</label>
            <select id="preorderPerPage" name="per_page">
                <?php foreach ([25, 50, 100] as $pageSize): ?><option value="<?= $pageSize ?>"<?= $perPage === $pageSize ? ' selected' : '' ?>><?= $pageSize ?></option><?php endforeach; ?>
            </select>
            <button type="submit" class="btn btn-primary btn-sm">Найти</button>
            <?php if ($search !== ''): ?><a class="btn btn-default btn-sm" href="<?= admin_h(preorder_admin_url($view, $filter, '', 1, $perPage)) ?>">Сбросить</a><?php endif; ?>
        </form>
        <nav class="preorder-filter" aria-label="Фильтр по статусу">
            <strong>Статус:</strong>
            <a class="<?= $filter === 'all' ? 'active' : '' ?>" href="<?= admin_h(preorder_admin_url($view, 'all', $search, 1, $perPage)) ?>">Все</a>
            <?php foreach ($allowedStatuses as $statusValue => $statusLabel): ?>
                <a class="<?= $filter === $statusValue ? 'active' : '' ?>" href="<?= admin_h(preorder_admin_url($view, $statusValue, $search, 1, $perPage)) ?>"><?= admin_h($statusLabel) ?></a>
            <?php endforeach; ?>
        </nav>
    </div>
    <div class="table-responsive preorder-card">
        <form method="post" id="preorderBulkForm" onsubmit="return confirmBulkAction();">
            <input type="hidden" name="csrf_token" value="<?= admin_h(csrf_token()) ?>">
            <input type="hidden" name="action" id="preorderBulkAction" value="">
            <div class="preorder-bulkbar">
                <strong>Выбранные:</strong> <span id="preorderSelectedCount">0</span>
                <?php if ($archiveSchemaReady): ?><button type="submit" class="btn btn-default btn-sm" data-bulk-action="bulk_archive">В архив</button><button type="submit" class="btn btn-default btn-sm" data-bulk-action="bulk_unarchive">Вернуть</button><?php endif; ?>
                <?php if (admin_is_admin()): ?><button type="submit" class="btn btn-danger btn-sm" data-bulk-action="bulk_delete">Удалить</button><?php endif; ?>
            </div>
        </form>
        <table class="table table-bordered table-striped preorder-table">
            <thead><tr><th><input type="checkbox" id="preorderSelectAll" aria-label="Выбрать все заявки"></th><th>Дата</th><th>Товар</th><th>Клиент</th><th>Статус и действия</th></tr></thead>
            <tbody>
            <?php if (!$requests): ?><tr><td colspan="5">В этом разделе заявок нет.</td></tr><?php endif; ?>
            <?php foreach ($requests as $request): ?>
                <tr class="<?= !empty($request['is_archived']) ? 'preorder-archived' : '' ?>">
                    <td><input type="checkbox" form="preorderBulkForm" name="ids[]" value="<?= (int)$request['id'] ?>" class="preorder-select" aria-label="Выбрать заявку #<?= (int)$request['id'] ?>"></td>
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
        <?php if ($totalPages > 1): ?><nav class="preorder-pagination" aria-label="Пагинация"><span>Заявки: <?= $totalRequests ?> · Страница <?= $page ?> из <?= $totalPages ?></span><?php if ($page > 1): ?><a href="<?= admin_h(preorder_admin_url($view, $filter, $search, $page - 1, $perPage)) ?>">← Назад</a><?php endif; ?><?php if ($page < $totalPages): ?><a href="<?= admin_h(preorder_admin_url($view, $filter, $search, $page + 1, $perPage)) ?>">Далее →</a><?php endif; ?></nav><?php endif; ?>
    </div>
</div>
<script>
(function () {
    function updateSelection() {
        var selected = document.querySelectorAll('.preorder-select:checked').length;
        var count = document.getElementById('preorderSelectedCount');
        if (count) count.textContent = selected;
    }
    var selectAll = document.getElementById('preorderSelectAll');
    if (selectAll) {
        selectAll.addEventListener('change', function () {
            document.querySelectorAll('.preorder-select').forEach(function (checkbox) {
                checkbox.checked = selectAll.checked;
            });
            updateSelection();
        });
    }
    document.querySelectorAll('.preorder-select').forEach(function (checkbox) {
        checkbox.addEventListener('change', updateSelection);
    });
    document.querySelectorAll('[data-bulk-action]').forEach(function (button) {
        button.addEventListener('click', function () {
            document.getElementById('preorderBulkAction').value = button.dataset.bulkAction;
        });
    });
    window.confirmBulkAction = function () {
        if (!document.querySelectorAll('.preorder-select:checked').length) {
            alert('Выберите хотя бы одну заявку.');
            return false;
        }
        var action = document.getElementById('preorderBulkAction').value;
        return action !== 'bulk_delete' || confirm('Удалить выбранные заявки навсегда? Это действие нельзя отменить.');
    };
})();
</script>
</body>
</html>

<?php

declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../lib/redirects.php';
admin_require_admin();
$pdo = dev_db_connection();
$message = '';
$error = '';

try {
    ensure_url_redirects_table($pdo);
} catch (Throwable $e) {
    $error = 'Не удалось автоматически создать таблицу редиректов. Проверьте права пользователя базы данных.';
    dev_log_runtime('Automatic redirects migration failed: ' . $e->getMessage());
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validate_csrf_token()) {
        $error = 'Сессия формы устарела. Обновите страницу.';
    } else {
        $action = (string)($_POST['action'] ?? '');
        try {
            if ($action === 'toggle') {
                $id = (int)($_POST['id'] ?? 0);
                $stmt = $pdo->prepare('UPDATE url_redirects SET is_active = CASE WHEN is_active = 1 THEN 0 ELSE 1 END WHERE id = :id');
                $stmt->execute(['id' => $id]);
                $message = 'Статус редиректа изменён.';
            } elseif ($action === 'delete') {
                $id = (int)($_POST['id'] ?? 0);
                $stmt = $pdo->prepare('DELETE FROM url_redirects WHERE id = :id');
                $stmt->execute(['id' => $id]);
                $message = 'Редирект удалён.';
            } elseif ($action === 'create') {
                $source = normalize_redirect_path((string)($_POST['source_path'] ?? ''));
                $target = normalize_redirect_path((string)($_POST['target_path'] ?? ''));
                if ($source === '/' || $target === '/' || $source === $target) throw new RuntimeException('Исходный и целевой URL должны отличаться.');
                save_url_redirect($pdo, $source, $target, null, 'manual');
                $message = 'Редирект сохранён.';
            } elseif ($action === 'normalize_products') {
                $pdo->beginTransaction();
                $lockClause = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql' ? ' FOR UPDATE' : '';
                $products = $pdo->query('SELECT id, link FROM products WHERE link IS NOT NULL AND link <> \'\' ORDER BY id ASC' . $lockClause)->fetchAll(PDO::FETCH_ASSOC);
                $changed = 0;
                foreach ($products as $product) {
                    $oldLink = (string)$product['link'];
                    $newLink = normalize_product_link($oldLink);
                    if ($newLink === normalize_redirect_path($oldLink)) continue;
                    $check = $pdo->prepare('SELECT id FROM products WHERE link = :link AND id <> :id LIMIT 1');
                    $check->execute(['link' => $newLink, 'id' => (int)$product['id']]);
                    if ($check->fetchColumn()) {
                        $base = rtrim($newLink, '_');
                        $suffix = 2;
                        do {
                            $candidate = $base . '_' . $suffix++;
                            $check->execute(['link' => $candidate, 'id' => (int)$product['id']]);
                        } while ($check->fetchColumn());
                        $newLink = $candidate;
                    }
                    save_url_redirect($pdo, $oldLink, $newLink, (int)$product['id'], 'product_slug_normalization');
                    $update = $pdo->prepare('UPDATE products SET link = :link WHERE id = :id');
                    $update->execute(['link' => $newLink, 'id' => (int)$product['id']]);
                    $changed++;
                }
                $pdo->commit();
                $message = 'Нормализовано товарных URL: ' . $changed . '. Старые адреса сохранены как 301-редиректы.';
            } else {
                throw new RuntimeException('Неизвестное действие.');
            }
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
            $error = $e instanceof RuntimeException ? $e->getMessage() : 'Операция не выполнена. Проверьте подключение к базе данных.';
            if (!$e instanceof RuntimeException) dev_log_runtime('Redirect admin action failed: ' . $e->getMessage());
        }
    }
}

$redirects = [];
try {
    $redirects = $pdo->query('SELECT r.*, p.name AS product_name FROM url_redirects r LEFT JOIN products p ON p.id = r.product_id ORDER BY r.updated_at DESC, r.id DESC')->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (Throwable $e) {
    $error = $error ?: 'Не удалось прочитать таблицу редиректов после автоматической миграции.';
}
$csrf = csrf_token();
?>
<!doctype html>
<html lang="ru">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Управление редиректами</title><link rel="stylesheet" href="/css/bootstrap.min.css"><link rel="stylesheet" href="/css/admin.css"><style>
.redirect-card{background:#fff;border:1px solid #ddd;border-radius:6px;padding:18px;margin-bottom:20px}.redirect-form{display:flex;gap:8px;align-items:end;flex-wrap:wrap}.redirect-form .form-group{margin:0;min-width:260px;flex:1}.redirect-status{font-weight:600}.redirect-status--on{color:#287a3d}.redirect-status--off{color:#888}.redirect-actions{display:flex;gap:6px;flex-wrap:wrap}.redirect-help{color:#666;margin-top:0}
</style></head>
<body><div class="container">
<?php require __DIR__ . '/_nav.php'; ?>
<h3>Управление 301-редиректами</h3>
<?php if ($message): ?><div class="alert alert-success"><?= admin_h($message) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-danger"><?= admin_h($error) ?></div><?php endif; ?>
<div class="redirect-card"><h4>Нормализация товарных URL</h4><p class="redirect-help">Таблица редиректов создаётся автоматически кодом при открытии этого раздела. Нажмите кнопку, чтобы заменить дефисы и пробелы в существующих ссылках товаров на нижние подчёркивания. Для каждого старого адреса автоматически создаётся постоянный 301-редирект.</p><form method="post" onsubmit="return confirm('Нормализовать URL всех товаров и создать редиректы?');"><input type="hidden" name="csrf_token" value="<?= admin_h($csrf) ?>"><input type="hidden" name="action" value="normalize_products"><button class="btn btn-primary" type="submit">Нормализовать URL товаров</button></form></div>
<div class="redirect-card"><h4>Добавить ручной редирект</h4><form method="post" class="redirect-form"><input type="hidden" name="csrf_token" value="<?= admin_h($csrf) ?>"><input type="hidden" name="action" value="create"><div class="form-group"><label for="source_path">Старый URL</label><input class="form-control" id="source_path" name="source_path" placeholder="/old_product" required></div><div class="form-group"><label for="target_path">Новый URL</label><input class="form-control" id="target_path" name="target_path" placeholder="/new_product" required></div><button class="btn btn-secondary" type="submit">Добавить 301</button></form></div>
<div class="redirect-card"><h4>Список редиректов (<?= count($redirects) ?>)</h4><div class="table-responsive"><table class="table table-bordered table-striped"><thead><tr><th>Статус</th><th>Старый URL</th><th>Новый URL</th><th>Товар</th><th>Проверка</th><th>Действия</th></tr></thead><tbody>
<?php foreach ($redirects as $redirect): $targetExists = false; try { $targetCheck = $pdo->prepare('SELECT id FROM products WHERE link = :link LIMIT 1'); $targetCheck->execute(['link' => normalize_redirect_path((string)$redirect['target_path'])]); $targetExists = (bool)$targetCheck->fetchColumn(); } catch (Throwable $e) {} ?>
<tr><td class="redirect-status <?= (int)$redirect['is_active'] === 1 ? 'redirect-status--on' : 'redirect-status--off' ?>"><?= (int)$redirect['is_active'] === 1 ? 'Включён' : 'Отключён' ?><br><small><?= (int)$redirect['status_code'] ?> Moved Permanently</small></td><td><code><?= admin_h((string)$redirect['source_path']) ?></code></td><td><code><?= admin_h((string)$redirect['target_path']) ?></code></td><td><?= admin_h((string)($redirect['product_name'] ?: 'Ручной')) ?></td><td><?= $targetExists ? '<span class="text-success">Цель найдена</span>' : '<span class="text-danger">Цель не найдена</span>' ?></td><td><div class="redirect-actions"><a class="btn btn-light btn-sm" href="<?= admin_h((string)$redirect['source_path']) ?>" target="_blank" rel="noopener">Открыть</a><form method="post"><input type="hidden" name="csrf_token" value="<?= admin_h($csrf) ?>"><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= (int)$redirect['id'] ?>"><button class="btn btn-warning btn-sm" type="submit"><?= (int)$redirect['is_active'] === 1 ? 'Отключить' : 'Включить' ?></button></form><form method="post" onsubmit="return confirm('Удалить редирект?');"><input type="hidden" name="csrf_token" value="<?= admin_h($csrf) ?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$redirect['id'] ?>"><button class="btn btn-danger btn-sm" type="submit">Удалить</button></form></div></td></tr>
<?php endforeach; ?>
<?php if (!$redirects): ?><tr><td colspan="6" class="text-muted">Редиректов пока нет.</td></tr><?php endif; ?></tbody></table></div></div>
</div></body></html>

<?php
declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';
admin_require_admin();
$pdo = dev_db_connection();
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validate_csrf_token()) {
        $error = 'Сессия формы устарела. Обновите страницу.';
    } else {
        $action = (string)($_POST['action'] ?? '');
        try {
            if ($action === 'create') {
                $username = trim((string)($_POST['username'] ?? ''));
                $password = (string)($_POST['password'] ?? '');
                $role = (string)($_POST['role'] ?? 'manager');
                if (!preg_match('/^[A-Za-z0-9_.-]{3,120}$/', $username)) throw new RuntimeException('Логин: от 3 до 120 символов, только латиница, цифры, точка, дефис и подчёркивание.');
                if (!in_array($role, ['admin', 'manager'], true)) throw new RuntimeException('Недопустимая роль.');
                if (strlen($password) < 10) throw new RuntimeException('Пароль должен содержать минимум 10 символов.');
                $check = $pdo->prepare('SELECT COUNT(*) FROM admin_users WHERE username = :username');
                $check->execute(['username' => $username]);
                if ((int)$check->fetchColumn() > 0) throw new RuntimeException('Пользователь с таким логином уже существует.');
                $stmt = $pdo->prepare('INSERT INTO admin_users (username, password_hash, role, is_active) VALUES (:username, :password_hash, :role, 1)');
                $stmt->execute(['username' => $username, 'password_hash' => password_hash($password, PASSWORD_DEFAULT), 'role' => $role]);
                dev_log_security('ADMIN_USER_CREATED', ['username' => $username, 'role' => $role, 'by' => admin_current_user()['id'] ?? null]);
                $message = 'Пользователь создан.';
            } elseif ($action === 'toggle') {
                $userId = (int)($_POST['user_id'] ?? 0);
                $current = admin_current_user();
                if ($userId <= 0 || $userId === (int)($current['id'] ?? 0)) throw new RuntimeException('Нельзя отключить текущего пользователя.');
                $stmt = $pdo->prepare('UPDATE admin_users SET is_active = CASE WHEN is_active = 1 THEN 0 ELSE 1 END WHERE id = :id');
                $stmt->execute(['id' => $userId]);
                $message = 'Статус пользователя изменён.';
            } elseif ($action === 'role') {
                $userId = (int)($_POST['user_id'] ?? 0);
                $role = (string)($_POST['role'] ?? 'manager');
                $current = admin_current_user();
                if ($userId <= 0 || $userId === (int)($current['id'] ?? 0)) throw new RuntimeException('Нельзя менять роль текущего пользователя здесь.');
                if (!in_array($role, ['admin', 'manager'], true)) throw new RuntimeException('Недопустимая роль.');
                $stmt = $pdo->prepare('UPDATE admin_users SET role = :role WHERE id = :id');
                $stmt->execute(['role' => $role, 'id' => $userId]);
                $message = 'Роль пользователя изменена.';
            } else {
                throw new RuntimeException('Неизвестное действие.');
            }
        } catch (Throwable $e) {
            $error = $e instanceof RuntimeException ? $e->getMessage() : 'Операция не выполнена.';
            if (!$e instanceof RuntimeException) dev_log_runtime('Admin users action failed: ' . $e->getMessage());
        }
    }
}
$users = $pdo->query('SELECT id, username, role, is_active, created_at, last_login FROM admin_users ORDER BY username')->fetchAll() ?: [];
function admin_role_label(string $role): string { return $role === 'admin' ? 'Администратор' : 'Менеджер'; }
?>
<!doctype html>
<html lang="ru">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Пользователи админки</title><link rel="stylesheet" href="/css/bootstrap.min.css"><link rel="stylesheet" href="/css/admin.css">
    <style>.user-form,.user-list{background:#fff;border:1px solid #ddd;border-radius:6px;padding:18px;margin-bottom:20px}.user-form{max-width:600px}.user-form .form-group{margin-bottom:14px}.status-active{color:#287a3d}.status-off{color:#9a2b2b}.role-form{display:flex;gap:6px;align-items:center}.role-form select{width:auto}.table td{vertical-align:middle}</style>
</head>
<body><div class="container"><?php require __DIR__ . '/_nav.php'; ?><h3>Пользователи админки</h3>
<?php if ($message): ?><div class="alert alert-success"><?= admin_h($message) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-danger"><?= admin_h($error) ?></div><?php endif; ?>
<div class="user-form"><h4>Создать пользователя</h4><p class="text-muted">Администратор имеет доступ к управлению пользователями. Менеджер работает с операциями магазина без доступа к этому разделу.</p><form method="post"><input type="hidden" name="csrf_token" value="<?= admin_h(csrf_token()) ?>"><input type="hidden" name="action" value="create"><div class="form-group"><label>Логин</label><input class="form-control" name="username" required maxlength="120" autocomplete="username"></div><div class="form-group"><label>Временный пароль</label><input class="form-control" type="password" name="password" required minlength="10" autocomplete="new-password"><small class="text-muted">Минимум 10 символов. Пользователь сможет сменить его в настройках.</small></div><div class="form-group"><label>Роль</label><select class="form-control" name="role"><option value="manager">Менеджер</option><option value="admin">Администратор</option></select></div><button class="btn btn-primary" type="submit">Создать пользователя</button></form></div>
<div class="user-list"><h4>Список пользователей</h4><div class="table-responsive"><table class="table table-bordered table-striped"><thead><tr><th>Логин</th><th>Роль</th><th>Статус</th><th>Создан</th><th>Последний вход</th><th>Действия</th></tr></thead><tbody><?php foreach ($users as $user): ?><tr><td><strong><?= admin_h((string)$user['username']) ?></strong></td><td><?php if ((int)$user['id'] === (int)(admin_current_user()['id'] ?? 0)): ?><?= admin_h(admin_role_label((string)$user['role'])) ?><?php else: ?><form class="role-form" method="post"><input type="hidden" name="csrf_token" value="<?= admin_h(csrf_token()) ?>"><input type="hidden" name="action" value="role"><input type="hidden" name="user_id" value="<?= (int)$user['id'] ?>"><select class="form-control input-sm" name="role"><option value="manager"<?= $user['role'] === 'manager' ? ' selected' : '' ?>>Менеджер</option><option value="admin"<?= $user['role'] === 'admin' ? ' selected' : '' ?>>Администратор</option></select><button class="btn btn-default btn-sm" type="submit">Сохранить</button></form><?php endif; ?></td><td class="<?= (int)$user['is_active'] === 1 ? 'status-active' : 'status-off' ?>"><?= (int)$user['is_active'] === 1 ? 'Активен' : 'Отключён' ?></td><td><?= admin_h((string)$user['created_at']) ?></td><td><?= admin_h((string)($user['last_login'] ?: '—')) ?></td><td><?php if ((int)$user['id'] !== (int)(admin_current_user()['id'] ?? 0)): ?><form method="post" onsubmit="return confirm('Изменить статус пользователя?');"><input type="hidden" name="csrf_token" value="<?= admin_h(csrf_token()) ?>"><input type="hidden" name="action" value="toggle"><input type="hidden" name="user_id" value="<?= (int)$user['id'] ?>"><button class="btn btn-<?= (int)$user['is_active'] === 1 ? 'warning' : 'success' ?> btn-sm" type="submit"><?= (int)$user['is_active'] === 1 ? 'Отключить' : 'Включить' ?></button></form><?php else: ?><span class="text-muted">Текущий пользователь</span><?php endif; ?></td></tr><?php endforeach; ?></tbody></table></div></div>
</div></body></html>

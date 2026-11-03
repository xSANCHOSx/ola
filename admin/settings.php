<?php
declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';
admin_require_auth();
$pdo = dev_db_connection();
$user = admin_current_user() ?? [];
$message = '';
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validate_csrf_token()) {
        $error = 'Сессия формы устарела. Обновите страницу.';
    } elseif (!check_rate_limit('admin_password:' . (int)($user['id'] ?? 0), 5, 900)) {
        $error = 'Слишком много попыток. Попробуйте через 15 минут.';
    } else {
        $currentPassword = (string)($_POST['current_password'] ?? '');
        $newPassword = (string)($_POST['new_password'] ?? '');
        $confirmPassword = (string)($_POST['confirm_password'] ?? '');
        try {
            $stmt = $pdo->prepare('SELECT password_hash FROM admin_users WHERE id = :id AND is_active = 1');
            $stmt->execute(['id' => (int)($user['id'] ?? 0)]);
            $record = $stmt->fetch();
            if (!$record || !password_verify($currentPassword, (string)$record['password_hash'])) throw new RuntimeException('Текущий пароль указан неверно.');
            if (strlen($newPassword) < 10) throw new RuntimeException('Новый пароль должен содержать минимум 10 символов.');
            if ($newPassword !== $confirmPassword) throw new RuntimeException('Новый пароль и подтверждение не совпадают.');
            if (password_verify($newPassword, (string)$record['password_hash'])) throw new RuntimeException('Новый пароль должен отличаться от текущего.');
            $update = $pdo->prepare('UPDATE admin_users SET password_hash = :password_hash WHERE id = :id');
            $update->execute(['password_hash' => password_hash($newPassword, PASSWORD_DEFAULT), 'id' => (int)$user['id']]);
            dev_log_security('ADMIN_PASSWORD_CHANGED', ['admin_id' => $user['id'] ?? null]);
            $message = 'Пароль успешно изменён.';
        } catch (Throwable $e) {
            $error = $e instanceof RuntimeException ? $e->getMessage() : 'Не удалось изменить пароль.';
            if (!$e instanceof RuntimeException) dev_log_runtime('Admin password change failed: ' . $e->getMessage());
        }
    }
}
$roleLabel = (($user['role'] ?? 'admin') === 'admin') ? 'Администратор' : 'Менеджер';
?>
<!doctype html>
<html lang="ru"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Настройки профиля</title><link rel="stylesheet" href="/css/bootstrap.min.css"><link rel="stylesheet" href="/css/admin.css"><style>.settings-card{max-width:620px;background:#fff;border:1px solid #ddd;border-radius:6px;padding:20px}.settings-card .form-group{margin-bottom:16px}</style></head>
<body><div class="container"><?php require __DIR__ . '/_nav.php'; ?><h3>Настройки профиля</h3><?php if ($message): ?><div class="alert alert-success"><?= admin_h($message) ?></div><?php endif; ?><?php if ($error): ?><div class="alert alert-danger"><?= admin_h($error) ?></div><?php endif; ?><div class="settings-card"><p><strong>Пользователь:</strong> <?= admin_h((string)($user['username'] ?? '')) ?><br><strong>Роль:</strong> <?= admin_h($roleLabel) ?></p><hr><h4>Смена пароля</h4><form method="post"><input type="hidden" name="csrf_token" value="<?= admin_h(csrf_token()) ?>"><div class="form-group"><label>Текущий пароль</label><input class="form-control" type="password" name="current_password" required autocomplete="current-password"></div><div class="form-group"><label>Новый пароль</label><input class="form-control" type="password" name="new_password" required minlength="10" autocomplete="new-password"><small class="text-muted">Минимум 10 символов.</small></div><div class="form-group"><label>Подтвердите новый пароль</label><input class="form-control" type="password" name="confirm_password" required minlength="10" autocomplete="new-password"></div><button class="btn btn-primary" type="submit">Изменить пароль</button></form></div></div></body></html>

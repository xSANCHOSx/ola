<?php
declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';

if (admin_is_auth()) {
    header('Location: /admin/');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim((string)($_POST['username'] ?? ''));
    $password = (string)($_POST['password'] ?? '');
    $pdo = dev_db_connection();
    if ($pdo instanceof PDO) {
        $stmt = $pdo->prepare('SELECT id, username, password_hash FROM admin_users WHERE username = :username LIMIT 1');
        $stmt->execute(['username' => $username]);
        $user = $stmt->fetch();
        if ($user && password_verify($password, $user['password_hash'])) {
            session_regenerate_id(true);
            admin_session_set($user);
            $pdo->prepare('UPDATE admin_users SET last_login = NOW() WHERE id = :id')->execute(['id' => $user['id']]);
            header('Location: /admin/');
            exit;
        }
    }
    $error = 'Неверный логин или пароль';
}
?>
<!doctype html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login</title>
    <link rel="stylesheet" href="/css/bootstrap.min.css">
</head>
<body>
<div class="container" style="max-width:420px; margin-top:60px;">
    <h3>Вход в админку</h3>
    <?php if ($error): ?><div class="alert alert-danger"><?= admin_h($error) ?></div><?php endif; ?>
    <form method="post">
        <div class="form-group">
            <label>Логин</label>
            <input class="form-control" name="username" required>
        </div>
        <div class="form-group">
            <label>Пароль</label>
            <input class="form-control" type="password" name="password" required>
        </div>
        <button class="btn btn-primary">Войти</button>
    </form>
</div>
</body>
</html>

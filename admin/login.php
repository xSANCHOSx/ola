<?php

declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';

if (admin_is_auth()) {
    header('Location: /admin/');
    exit;
}

$csrf = csrf_token();

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Проверка CSRF токена
    $postedCsrf = (string)($_POST['csrf_token'] ?? '');
    if (empty($postedCsrf) || !hash_equals($csrf, $postedCsrf)) {
        $error = 'Security error. Please reload the page.';
    } elseif (!check_rate_limit('admin_login_' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'), 10, 300)) {
        $error = 'Слишком много попыток входа. Попробуйте через 5 минут.';
    } else {
        $username = trim((string)($_POST['username'] ?? ''));
        $password = (string)($_POST['password'] ?? '');
        $pdo = null;
        try {
            $pdo = dev_db_connection();
        } catch (Throwable $e) {
            $error = 'Ошибка подключения к БД. Обратитесь к администратору.';
        }
        if ($pdo instanceof PDO) {
            $user = null;
            try {
                $stmt = $pdo->prepare('SELECT id, username, password_hash, role, is_active, permissions_json FROM admin_users WHERE username = :username LIMIT 1');
                $stmt->execute(['username' => $username]);
                $user = $stmt->fetch() ?: null;
            } catch (Throwable $e) {
                // Allow the existing administrator to log in during a staged deployment
                // before the role and permission migrations have been applied.
                dev_log_runtime('Admin login schema is not fully migrated: ' . $e->getMessage());
                try {
                    $stmt = $pdo->prepare('SELECT id, username, password_hash, role, is_active FROM admin_users WHERE username = :username LIMIT 1');
                    $stmt->execute(['username' => $username]);
                    $user = $stmt->fetch() ?: null;
                    if ($user) $user['permissions_json'] = null;
                } catch (Throwable $e) {
                    try {
                        $stmt = $pdo->prepare('SELECT id, username, password_hash FROM admin_users WHERE username = :username LIMIT 1');
                        $stmt->execute(['username' => $username]);
                        $user = $stmt->fetch() ?: null;
                        if ($user) {
                            // Legacy users retain administrator access until migrations run.
                            $user['role'] = 'admin';
                            $user['is_active'] = 1;
                            $user['permissions_json'] = null;
                        }
                    } catch (Throwable $legacyError) {
                        dev_log_runtime('Admin login query failed: ' . $legacyError->getMessage());
                        $user = null;
                    }
                }
            }

            $dummyHash   = '$2y$10$dummyHashToPreventTimingAttackXXXXXXXXXXXXXX';
            $hashToCheck = $user ? $user['password_hash'] : $dummyHash;
            if ($user && (int)$user['is_active'] === 1 && password_verify($password, $hashToCheck)) {
                session_regenerate_id(true);
                admin_session_set($user);
                $pdo->prepare('UPDATE admin_users SET last_login = CURRENT_TIMESTAMP WHERE id = :id')->execute(['id' => $user['id']]);
                header('Location: /admin/');
                exit;
            }
        }
        if (empty($error)) {
            $error = 'Неверный логин или пароль';
        }
    }
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
            <input type="hidden" name="csrf_token" value="<?= admin_h($csrf) ?>">
            <div class="form-group">
                <label>Логин</label>
                <input class="form-control" name="username" required autocomplete="username">
            </div>
            <div class="form-group">
                <label>Пароль</label>
                <input class="form-control" type="password" name="password" required autocomplete="current-password">
            </div>
            <button class="btn btn-primary">Войти</button>
        </form>
    </div>
</body>

</html>

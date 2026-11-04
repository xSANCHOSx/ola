<?php

declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';
admin_require_auth();

if (admin_is_admin() || admin_default_path() !== '/admin/no-access.php') {
    header('Location: ' . admin_default_path());
    exit;
}

$user = admin_current_user() ?? [];
?>
<!doctype html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Нет доступных разделов</title>
    <link rel="stylesheet" href="/css/bootstrap.min.css">
    <link rel="stylesheet" href="/css/admin.css">
    <style>
        .access-empty { max-width: 680px; margin: 48px auto; padding: 30px; text-align: center; background: #fff; border: 1px solid #ddd; border-radius: 8px; }
        .access-empty h3 { margin-top: 0; color: #243447; }
        .access-empty p { color: #666; line-height: 1.6; }
        .access-empty__icon { width: 58px; height: 58px; margin: 0 auto 18px; border-radius: 50%; background: #fff3cd; color: #856404; display: flex; align-items: center; justify-content: center; font-size: 30px; font-weight: 700; }
        .access-empty__actions { display: flex; justify-content: center; gap: 10px; flex-wrap: wrap; margin-top: 24px; }
    </style>
</head>
<body>
<div class="container">
    <?php require __DIR__ . '/_nav.php'; ?>
    <main class="access-empty" role="alert">
        <div class="access-empty__icon" aria-hidden="true">!</div>
        <h3>Нет доступных разделов</h3>
        <p>Для пользователя <strong><?= admin_h((string)($user['username'] ?? '')) ?></strong> пока не назначены разрешения на просмотр разделов админки.</p>
        <p>Обратитесь к администратору, чтобы он включил необходимые права. Вы можете самостоятельно сменить пароль в настройках профиля.</p>
        <div class="access-empty__actions">
            <a class="btn btn-primary" href="/admin/settings.php">Настройки профиля и смена пароля</a>
            <a class="btn btn-default" href="/admin/logout.php">Выйти</a>
        </div>
    </main>
</div>
</body>
</html>

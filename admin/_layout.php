<?php
// Использование: $adminPageTitle = 'Заказы'; require __DIR__ . '/_layout.php';
$adminPageTitle ??= 'Админка';
?>
<!doctype html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Админка — <?= htmlspecialchars($adminPageTitle) ?></title>
    <link rel="stylesheet" href="/css/bootstrap.min.css">
    <link rel="stylesheet" href="/css/admin.css">
</head>
<body>
<div class="container">
    <?php require __DIR__ . '/_nav.php'; ?>

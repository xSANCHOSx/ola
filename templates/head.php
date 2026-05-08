<?php
// templates/head.php
// Змінні: $pageTitle, $pageDescription, $extraCss
$pageTitle       ??= 'Олаплекс (Olaplex) Для Волос Купить В Интернет-Магазине';
$pageDescription ??= 'Средства для волос Olaplex (Олаплекс) для домашнего использования можно заказать у нас! Отличные цены, доставка по всей территории России!';
$cssBust = function (string $f) {
    return file_exists(__DIR__ . '/../' . $f) ? filemtime(__DIR__ . '/../' . $f) : time();
};
?>

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="yandex-verification" content="0bef8202615abb0b" />
    <meta name="google-site-verification" content="RzlStn4gRa1keV7FRXBfC3k3Ns_qAVPFEEos43KSWsA" />
    <title><?= htmlspecialchars($pageTitle) ?></title>
    <meta name="description" content="<?= htmlspecialchars($pageDescription) ?>">

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css?family=Open+Sans:400,700&display=swap&subset=cyrillic-ext"
        rel="stylesheet">
    <!-- Libs -->
    <link rel="stylesheet" href="/css/bootstrap.min.css">
    <link rel="stylesheet" href="/css/font-awesome.min.css">
    <link rel="stylesheet" href="/css/animate.css">
    <link rel="stylesheet" href="/css/icon.css">
    <link rel="stylesheet" href="/css/flexslider.css">
    <link rel="icon" href="/favicon.ico" type="image/x-icon">

    <!-- App CSS -->
    <link rel="stylesheet" href="/css/styles.css?v=<?= $cssBust('css/styles.css') ?>">
    <link rel="stylesheet" href="/css/wicart.css?v=<?= $cssBust('css/wicart.css') ?>">

    <?php if (!empty($extraCss)) echo $extraCss; ?>

    <!-- Analytics -->
    <?php require __DIR__ . '/analytics.php'; ?>
</head>
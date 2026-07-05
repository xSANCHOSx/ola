<?php
// templates/head.php
// Переменные: $pageTitle, $pageDescription, $extraCss
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
	<link href="https://fonts.googleapis.com/css2?family=Open+Sans:wght@400;700&display=swap" rel="stylesheet"
		media="print" onload="this.media='all'">
	<noscript>
		<link href="https://fonts.googleapis.com/css2?family=Open+Sans:wght@400;700&display=swap" rel="stylesheet">
	</noscript>

	<!-- Preload Critical Assets -->
	<link rel="preload" href="/css/styles.css?v=<?= $cssBust('css/styles.css') ?>" as="style">

	<!-- CSS Loading -->
	<!-- BUGFIX: was loading Bootstrap 3.4.1 from CDN while /js/bootstrap.min.js
	     (and the rest of the project, incl. admin) is Bootstrap 5.3.3. That
	     v3-CSS / v5-JS mismatch is what broke things like the burger menu.
	     Now using the same local v5.3.3 stylesheet as everywhere else. -->
	<link rel="stylesheet" href="/css/bootstrap.min.css?v=<?= $cssBust('css/bootstrap.min.css') ?>">

	<link rel="stylesheet" href="/css/styles.css?v=<?= $cssBust('css/styles.css') ?>">
	<link rel="stylesheet" href="/css/wicart.css?v=<?= $cssBust('css/wicart.css') ?>" media="print"
		onload="this.media='all'">

	<link rel="icon" href="/favicon.ico" type="image/x-icon">

	<?php if (!empty($extraCss)) echo $extraCss; ?>
</head>
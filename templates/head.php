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
	<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@3.4.1/dist/css/bootstrap.min.css"
		integrity="sha384-HSMxcRTRxnN+Bdg0JdbxYKrThecOKuH5zCYotlSAcp1+c8xmyTe9GYg1l9a69psu" crossorigin="anonymous">

	<link rel="stylesheet" href="/css/styles.css?v=<?= $cssBust('css/styles.css') ?>">
	<link rel="stylesheet" href="/css/wicart.css?v=<?= $cssBust('css/wicart.css') ?>" media="print"
		onload="this.media='all'">

	<link rel="icon" href="/favicon.ico" type="image/x-icon">

	<?php if (!empty($extraCss)) echo $extraCss; ?>
</head>
<?php
require_once __DIR__ . '/../data/products.php';

$currentUrl = $_SERVER['REQUEST_URI']; // Получаем текущий URL

$menuItems = [];
foreach ($products as $product) {
	$menuItems[] = [
		'href' => $product['link'],
		'name' => $product['cat_number'] . ' ' . $product['name']
	];
}
?>
<!-- Footer Section Starts -->
<section id="max-footer">
	<div class="container-fluid">
		<div class="row">

			<div class="col-md-12 col-xs-12">
				<nav class="navbar navbar-default ">
					<ul class="nav navbar-nav main">
						<li><a href="/#max-aboutus-section">Что это?</a></li>
						<li><a href="/#max-featured-section">Продукция</a></li>
						<li><a href="/#max-work-section">Как использовать</a></li>
						<li><a href="/delivery">Доставка и оплата</a></li>
						<li><a href="/info">Справка</a></li>
						<li class="navbar-brand hidden-md hidden-lg"> <a href="tel:+74950322929">+7 (495) 032-29-29</a>
							<a href="https://wa.me/79096962720"><img src="/images/whatsapp.svg" class="whatsapp" alt="whatsapp"
									loading="lazy"></a>
						</li>
						<li class="hidden-md hidden-lg"><a href="mailto:admin@olaplex-shop.ru"
								class="navbar-link-email">admin@olaplex-shop.ru</a><a href="mailto:client@olaplex-shop.ru"
								class="navbar-link-email">client@olaplex-shop.ru</a></li>
					</ul>
					<div class="number navbar-brand hidden-sm hidden-xs">
						<a href="tel:+74950322929">+7 (495) 032-29-29</a>
						<a href="https://wa.me/79096962720"><img src="/images/whatsapp.svg" class="whatsapp" alt="whatsapp"
								loading="lazy"></a>
					</div>
					<div class="number navbar-brand hidden-sm hidden-xs mob_email_wrap"><a href="mailto:admin@olaplex-shop.ru"
							class="navbar-link-email">admin@olaplex-shop.ru</a></br><a href="mailto:client@olaplex-shop.ru"
							class="navbar-link-email">client@olaplex-shop.ru</a></div>
				</nav>
			</div>
		</div>
		<div class="row">
			<div class="col-md-12 col-xs-12 text-center">
				<div id="footer-copyrights">
					Время работы: Пн–Пт 10:00–18:00 (по будням)
				</div>
			</div>
		</div>
		<div class="row">
			<nav class="navbar navbar-default ">
				<ul class="nav navbar-nav menu_footer">
					<?php foreach ($menuItems as $item): ?>
					<?php if ($currentUrl == $item['href']): ?>
					<li class="active_footer"><?= htmlspecialchars($item['name']) ?></li>
					<?php else: ?>
					<li><a href="<?= htmlspecialchars($item['href']) ?>"><?= htmlspecialchars($item['name']) ?></a></li>
					<?php endif; ?>
					<?php endforeach; ?>
				</ul>
			</nav>
		</div>
		<div class="row">
			<div class="col-md-12 col-xs-12 text-center">

				<div id="footer-copyrights">
					Copyright © <?= date('Y'); ?> All Rights Reserved.| <a class="policy" href="/policy">Политика
						конфиденциальности</a>
				</div>
			</div>
		</div>
</section>
<!-- ./ Footer Section Ends -->

<div id="cookie-notice"
	style="position: fixed; bottom: 0; width: 100%; background: #333; color: #fff; padding: 10px; text-align: center; z-index: 1000; display: none;">
	Этот сайт использует cookies. <a href="/policy" style="color: #fff; text-decoration: underline;">Подробнее</a>
	<button onclick="acceptCookies()"
		style="margin-left: 10px; background: #0073aa; color: #fff; border: none; padding: 5px 10px;">Принять</button>
</div>

<!-- Top.Mail.Ru counter -->
<script type="text/javascript">
(function() {
	function loadTMR() {
		var _tmr = window._tmr || (window._tmr = []);
		_tmr.push({
			id: "3629866",
			type: "pageView",
			start: (new Date()).getTime()
		});
		if (document.getElementById('tmr-code')) return;
		var ts = document.createElement("script");
		ts.type = "text/javascript";
		ts.async = true;
		ts.id = "tmr-code";
		ts.src = "https://top-fwz1.mail.ru/js/code.js";
		var s = document.getElementsByTagName("script")[0];
		s.parentNode.insertBefore(ts, s);
	}
	if (window.requestIdleCallback) {
		requestIdleCallback(loadTMR);
	} else {
		setTimeout(loadTMR, 1500);
	}
})();
</script>
<noscript>
	<div><img src="https://top-fwz1.mail.ru/counter?id=3629866;js=na" style="position:absolute;left:-9999px;"
			alt="Top.Mail.Ru" /></div>
</noscript>

<!-- Envybox callback widget -->
<link rel="preload" href="https://cdn.envybox.io/widget/cbk.css" as="style"
	onload="this.onload=null;this.rel='stylesheet'">
<noscript>
	<link rel="stylesheet" href="https://cdn.envybox.io/widget/cbk.css">
</noscript>
<script src="https://cdn.envybox.io/widget/cbk.js?wcb_code=e4d8a7b33dcf97067342ac246b5aecaa" charset="UTF-8" async
	defer></script>

<!-- Analytics moved to end of body -->
<?php require __DIR__ . '/analytics.php'; ?>

<!-- Scroll to Top Button -->
<a href="#" id="scroll-to-top" title="Наверх">
	<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
		stroke-linecap="round" stroke-linejoin="round">
		<polyline points="18 15 12 9 6 15"></polyline>
	</svg>
</a>
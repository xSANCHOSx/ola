<?php session_start();

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/../data/products.php';
save_utm_cookies();

$currentUrl = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$links = array_column($products, 'link');

$productIndex = array_search($currentUrl, $links, true);
if ($productIndex !== false) {
	$currentProduct = $products[$productIndex];
} else {
	header("HTTP/1.1 404 Not Found");
	include $_SERVER['DOCUMENT_ROOT'] . '/404.php';
	exit();
}

$defaultTitle = htmlspecialchars($currentProduct['name'], ENT_QUOTES, 'UTF-8') . ' - Олаплекс (Olaplex) Для Волос Купить В Интернет-Магазине';
$defaultDescription = generate_product_seo_description($currentProduct);
$pageTitle = !empty($currentProduct['seo_title']) ? htmlspecialchars((string)$currentProduct['seo_title'], ENT_QUOTES, 'UTF-8') : generate_product_seo_title($currentProduct);
$pageDescription = !empty($currentProduct['seo_description']) ? htmlspecialchars((string)$currentProduct['seo_description'], ENT_QUOTES, 'UTF-8') : $defaultDescription;

// Собираем галерею из основного изображения и дополнительных файлов товара,
// если они есть в публичной папке images. Старые данные БД при этом остаются совместимыми.
$galleryImages = [$currentProduct['image']];
$imageId = pathinfo(basename((string)$currentProduct['image']), PATHINFO_FILENAME);
$imageId = preg_replace('/_min$/', '', $imageId);
$imageCandidates = glob($_SERVER['DOCUMENT_ROOT'] . '/images/' . $imageId . '*.png') ?: [];
foreach ($imageCandidates as $candidate) {
	$publicPath = '/images/' . basename($candidate);
	if (!in_array($publicPath, $galleryImages, true)) {
		$galleryImages[] = $publicPath;
	}
}
$galleryImages = array_slice($galleryImages, 0, 4);
?>

<!DOCTYPE html>
<html lang="ru">
<?php
$extraCss = ($extraCss ?? '') . '
<link rel="stylesheet" href="/css/flexslider.css">';

// Generate Product schema JSON-LD
$productSchema = [
	'@context' => 'https://schema.org',
	'@type' => 'Product',
	'name' => $currentProduct['name'],
	'image' => $currentProduct['image'],
	'description' => $currentProduct['short_desc'] ?: $currentProduct['desc'],
	'sku' => $currentProduct['cat_number'] ?: $currentProduct['id'],
	'brand' => [
		'@type' => 'Brand',
		'name' => 'Olaplex'
	],
	'offers' => [
		'@type' => 'Offer',
		'url' => $currentProduct['link'],
		'priceCurrency' => 'RUB',
		'price' => (string)$currentProduct['price'],
		'availability' => $currentProduct['in_stock'] ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock'
	]
];

// Add aggregateRating if available
$productSchema['aggregateRating'] = [
	'@type' => 'AggregateRating',
	'ratingValue' => '5',
	'bestRating' => '5',
	'ratingCount' => '30'
];

$extraCss .= '<script type="application/ld+json">' . json_encode($productSchema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . '</script>';

require __DIR__ . '/head.php'; ?>

<body class="single">
	<?php include 'header.php'; ?>

		<section class="product-page" aria-labelledby="product-title">
			<div class="product-shell">
				<div class="product-breadcrumb">Olaplex <span>/</span> Каталог <span>/</span> <?= htmlspecialchars($currentProduct['cat_number'], ENT_QUOTES, 'UTF-8') ?></div>
				<div class="product-layout">
					<div class="product-gallery">
						<div class="product-thumbnails" role="list" aria-label="Фотографии товара">
							<?php foreach ($galleryImages as $galleryIndex => $galleryImage): ?>
								<button class="product-thumbnail<?= $galleryIndex === 0 ? ' is-active' : '' ?>" type="button" data-product-image="<?= htmlspecialchars($galleryImage, ENT_QUOTES, 'UTF-8') ?>" aria-label="Фото <?= $galleryIndex + 1 ?>">
									<?= webp_img($galleryImage, $currentProduct['name'], '', ['width' => 88, 'height' => 88, 'loading' => $galleryIndex === 0 ? 'eager' : 'lazy']) ?>
								</button>
							<?php endforeach; ?>
						</div>
						<div class="product-main-image">
							<?= webp_img($galleryImages[0], $currentProduct['name'], 'product-main-image__img', ['width' => 650, 'height' => 650, 'fetchpriority' => 'high']) ?>
							<div class="product-image-note">Оригинальный товар Olaplex</div>
						</div>
					</div>

					<div class="product-info tovar-name" data-id="<?= htmlspecialchars((string)$currentProduct['id']) ?>">
						<div class="product-eyebrow">Olaplex Professional</div>
						<h1 id="product-title"><?= htmlspecialchars($currentProduct['cat_number'], ENT_QUOTES, 'UTF-8') ?> <?= htmlspecialchars($currentProduct['name'], ENT_QUOTES, 'UTF-8') ?></h1>
						<div class="product-rating"><span class="rating-stars">★★★★★</span> <span>5.0 · 30 отзывов</span></div>
						<div class="product-divider"></div>
						<div class="product-short-description"><?= $currentProduct['short_desc'] ?></div>
						<div class="product-buy-panel">
							<?php if (product_is_buyable($currentProduct)) { ?>
								<?php include 'single_special.php'; ?>
								<div class="product-price-row">
									<div><span class="product-price-old"><?= htmlspecialchars((string)$currentProduct['old_price'], ENT_QUOTES, 'UTF-8') ?> ₽</span><strong class="product-price"><?= htmlspecialchars((string)$currentProduct['price'], ENT_QUOTES, 'UTF-8') ?> ₽</strong></div>
									<span class="product-stock"><i></i> В наличии</span>
								</div>
							<?php } elseif (!empty($currentProduct['status']) && $currentProduct['status'] === 'preorder') { ?>
								<div class="product-price-row"><strong class="product-price">Предзаказ</strong><span class="product-stock">Доставка 7–14 дней</span></div>
							<?php } else { ?>
								<div class="product-price-row"><strong class="product-price">Нет в наличии</strong></div>
							<?php } ?>
							<button class="b1c product-buy-button" <?php if (product_is_buyable($currentProduct)) { ?>onclick="cart.addToCart(this, '<?= htmlspecialchars((string)$currentProduct['id']) ?>')" <?php } else { ?>disabled<?php } ?>><?= product_button_label($currentProduct) ?><span>→</span></button>
							<div class="product-benefits"><span>✓ Оригинал</span><span>✓ Безопасная оплата</span><span>✓ Поддержка стилиста</span></div>
						</div>
						<div class="product-accordions"><details open><summary>О продукте <span>+</span></summary><p><?= !empty($currentProduct['full_desc']) ? $currentProduct['full_desc'] : $currentProduct['desc'] ?></p></details><details><summary>Доставка и возврат <span>+</span></summary><p>Доставляем по Москве, Санкт-Петербургу и регионам России. Условия доставки уточнит оператор после оформления заказа.</p></details></div>
					</div>
				</div>
			</div>
		</section>
	<!-- ./ Feature Section Ends -->
	<?php include 'slider_in_card.php'; ?>
	<?php include 'delivery.php'; ?>
	<?php include 'footer.php'; ?>
	<?php include 'order_form.php'; ?>

	<!-- All JavaScript libraries -->
	<script defer src="/js/jquery-3.7.1.min.js"></script>
	<script defer src="/js/bootstrap.min.js"></script>
	<script>
		<?php
		$_numKey = (int)$currentProduct['id'];
		$_productMap = [];
		$_productMap[$_numKey] = $currentProduct;
		if ((string)$_numKey !== (string)$currentProduct['id']) {
			$_productMap[(string)$currentProduct['id']] = $currentProduct;
		}
		?>
		window.PRODUCTS = <?= json_encode($_productMap, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?>;
	</script>
	<script defer src="/js/cart.js?v=<?= $cssBust('js/cart.js') ?>"></script>
	<script defer src="/js/cart-init.js"></script>
	<script defer src="/js/jquery.flexslider-min.js"></script>
	<script defer src="/js/main.js?v=<?= date('Ymd', filemtime(__DIR__ . '/../js/main.js')) ?>"></script>

		<script>
			window.addEventListener('DOMContentLoaded', function() {
				document.querySelectorAll('.product-thumbnail').forEach(function(thumbnail) {
					thumbnail.addEventListener('click', function() {
						var image = document.querySelector('.product-main-image__img');
						if (!image) return;
						image.src = this.dataset.productImage;
						document.querySelectorAll('.product-thumbnail').forEach(function(item) { item.classList.remove('is-active'); });
						this.classList.add('is-active');
					});
				});

				// tiny helper function to add breakpoints
				function getGridSize() {
				return (window.innerWidth < 600) ? 2 :
					(window.innerWidth < 900) ? 3 : 4
			}

			$('.flexslider').flexslider({
				animation: "slide",
				directionNav: false,
				itemWidth: 240,
				itemMargin: 5,
				animationLoop: true,
				minItems: getGridSize(),
				maxItems: getGridSize(),
				startAt: 0,
				slideshow: true,
				slideshowSpeed: 7000,
				animationSpeed: 600,
				initDelay: 0,
				start: function(slider) {
					slider.addClass('flex-ready');
				}
			});

			// check grid size on resize event
			$(window).resize(function() {
				var gridSize = getGridSize()
				var flex = $('.flexslider').data('flexslider');
				if (flex) {
					flex.vars.minItems = gridSize;
					flex.vars.maxItems = gridSize;
				}
			});

			$('#order .close_popup').click(function() {
				$('#formToSend input:checkbox').removeAttr("checked")
				$("#formToSend input[type=submit]").attr('disabled', 'disabled')
				$('#formToSend input[type=hidden].valTrFal').val('valTrFal_disabled')
			})

			$('#formToSend input:checkbox').change(function() {
				if ($(this).is(':checked')) {
					$("#formToSend input[type=submit]").removeAttr('disabled')
					$('#formToSend input[type=hidden].valTrFal').val('valTrFal_true')
				} else {
					$("#formToSend input[type=submit]").attr('disabled', 'disabled')
					$('#formToSend input[type=hidden].valTrFal').val('valTrFal_disabled')
				}
			})

			$('#send').click(function() {
				if (($("#formToSend input[type=text]").val()) == !"") {
					$('#formToSend input[type=hidden].valTrFal').remove()
					$('#formToSend .font-geometria-light').remove()
					$('#overflw .basket_num_buttons').remove()
				}
			})

			$(".youtube").on("click", function() {
				var elm = $(this),
					conts = elm.contents(),
					le = conts.length,
					ifr = null
				for (var i = 0; i < le; i++) {
					if (conts[i].nodeType == 8) ifr = conts[i].textContent
				}
				elm.addClass("player").html(ifr)
				elm.off("click")
			})
		});
	</script>
</body>

</html>

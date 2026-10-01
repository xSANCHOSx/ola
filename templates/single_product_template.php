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
$productImages = [];
foreach (array_merge([(string)($currentProduct['image'] ?? '')], (array)($currentProduct['gallery'] ?? [])) as $productImage) {
	$productImage = trim((string)$productImage);
	if ($productImage !== '' && !in_array($productImage, $productImages, true)) {
		$productImages[] = $productImage;
	}
}
$appConfig = function_exists('dev_app_config') ? dev_app_config() : [];
$siteDomain = trim((string)($appConfig['site_domain'] ?? ($_SERVER['HTTP_HOST'] ?? 'localhost')));
$siteDomain = preg_replace('#^https?://#', '', $siteDomain);
$siteBaseUrl = 'https://' . rtrim((string)$siteDomain, '/');
$absoluteProductImages = array_map(static fn(string $image): string => $siteBaseUrl . '/' . ltrim($image, '/'), $productImages);
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
	'image' => $absoluteProductImages,
	'description' => $currentProduct['short_desc'] ?: $currentProduct['desc'],
	'sku' => $currentProduct['cat_number'] ?: $currentProduct['id'],
	'brand' => [
		'@type' => 'Brand',
		'name' => 'Olaplex'
	],
	'offers' => [
		'@type' => 'Offer',
		'url' => $siteBaseUrl . '/' . ltrim((string)$currentProduct['link'], '/'),
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

	<!-- ./ Container End Home -->
	<!-- Feature Section Starts -->
	<section id="max-featured-section">
		<div class="max-section-title product">
			<h1><?= htmlspecialchars($currentProduct['cat_number'], ENT_QUOTES, 'UTF-8') ?>
				<?= htmlspecialchars($currentProduct['name'], ENT_QUOTES, 'UTF-8') ?></h1>
		</div>
		<div class="max-feature-section-list container-fluid even3">
			<div class="row">
					<!-- Галерея товара: миниатюры сбоку от большого фото -->
					<div class="col-sm-12 col-md-5 offset-md-1">
						<div class="product-gallery animated fadeInDown" data-product-gallery>
							<?php if ($productImages): ?>
								<div class="product-gallery-thumbs-wrap">
									<button type="button" class="product-gallery-nav" data-gallery-nav="-1" aria-label="Предыдущие фото">⌃</button>
									<div class="product-gallery-thumbs" data-gallery-thumbs role="list">
										<?php foreach ($productImages as $imageIndex => $productImage): ?>
											<?php $webpProductImage = webp_image_source($productImage); ?>
											<button type="button" class="product-gallery-thumb <?= $imageIndex === 0 ? 'is-active' : '' ?>"
												data-gallery-image="<?= htmlspecialchars($productImage, ENT_QUOTES, 'UTF-8') ?>"
												data-gallery-webp="<?= htmlspecialchars((string)$webpProductImage, ENT_QUOTES, 'UTF-8') ?>"
												aria-label="Фото <?= $imageIndex + 1 ?>" aria-selected="<?= $imageIndex === 0 ? 'true' : 'false' ?>" aria-current="<?= $imageIndex === 0 ? 'true' : 'false' ?>">
												<picture>
													<?php if ($webpProductImage): ?><source srcset="/<?= htmlspecialchars($webpProductImage, ENT_QUOTES, 'UTF-8') ?>" type="image/webp"><?php endif; ?>
													<img src="/<?= htmlspecialchars($productImage, ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($currentProduct['name'], ENT_QUOTES, 'UTF-8') ?> — фото <?= $imageIndex + 1 ?>" loading="lazy">
												</picture>
											</button>
										<?php endforeach; ?>
									</div>
									<button type="button" class="product-gallery-nav" data-gallery-nav="1" aria-label="Следующие фото">⌄</button>
								</div>
								<div class="product-gallery-main">
									<button type="button" class="product-gallery-main-nav product-gallery-main-prev" data-gallery-main-nav="-1" aria-label="Предыдущее фото">‹</button>
									<?php $mainWebpImage = webp_image_source($productImages[0]); ?>
									<picture>
										<?php if ($mainWebpImage): ?><source data-gallery-main-webp srcset="/<?= htmlspecialchars($mainWebpImage, ENT_QUOTES, 'UTF-8') ?>" type="image/webp"><?php endif; ?>
										<img data-gallery-main src="/<?= htmlspecialchars($productImages[0], ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($currentProduct['name'], ENT_QUOTES, 'UTF-8') ?>" width="600" height="600">
									</picture>
									<button type="button" class="product-gallery-main-nav product-gallery-main-next" data-gallery-main-nav="1" aria-label="Следующее фото">›</button>
								</div>
							<?php else: ?>
								<div class="product-gallery-empty">Фото товара пока не добавлено</div>
							<?php endif; ?>
						</div>
					</div>
				<!-- Информация о товаре -->
				<div class="col-sm-12 col-md-5 tovar-name animated fadeInDown">
					<span></span>
					<div class="col-xs-12 buy">
						<?php if (product_is_buyable($currentProduct)) { ?>
							<?php include 'single_special.php'; ?>
							<div class="price_inner">
								<p>Цена: <span
										class="price_old"><?= htmlspecialchars($currentProduct['old_price'], ENT_QUOTES, 'UTF-8') ?></span>
									<strong><?= htmlspecialchars($currentProduct['price'], ENT_QUOTES, 'UTF-8') ?></strong> РУБ
								</p>
								<div class="stars">
									<div class="stars-rating"></div>
									<div style="display: none;" id="block_rating" itemprop="aggregateRating" itemscope=""
										itemtype="http://schema.org/AggregateRating">
										<meta itemprop="bestRating" content="5">
										<meta itemprop="ratingValue" content="5">
										<span class="ratingCount" itemprop="ratingCount">30</span>
									</div>
									<div itemprop="offers" itemscope itemtype="https://schema.org/Offer">
										<meta itemprop="priceCurrency" content="RUB" />
										<meta itemprop="price"
											content="<?= htmlspecialchars($currentProduct['price'], ENT_QUOTES, 'UTF-8') ?>" />
									</div>
								</div>
							</div>
						<?php } elseif (!empty($currentProduct['status']) && $currentProduct['status'] === 'preorder') { ?>
							<p><span class="regular_price"><strong>Предзаказ</strong></span></p>
							<p><strong>Срок доставки: 7-14 дней</strong></p>
						<?php } else { ?>
							<p><span class="regular_price"><strong>Нет в наличии</strong></span></p>
						<?php } ?>
						<p><?php echo nl2br($currentProduct['short_desc']); ?></p>
						<button class="b1c"
							<?php if (!empty($currentProduct['in_stock']) || (!empty($currentProduct['status']) && $currentProduct['status'] === 'preorder')) { ?>
							onclick="cart.addToCart(this, '<?= htmlspecialchars((string)$currentProduct['id']) ?>')" <?php } else { ?>
							disabled <?php } ?>>
							<?php echo product_button_label($currentProduct); ?>
						</button>
					</div>
					<noindex>
						<div style="text-align: justify;" class="product-description">
							<?php
							$description = !empty($currentProduct['full_desc']) ? $currentProduct['full_desc'] : $currentProduct['desc'];
							echo $description;
							?>
						</div>
					</noindex>
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
				document.querySelectorAll('[data-product-gallery]').forEach(function(gallery) {
					var thumbs = Array.prototype.slice.call(gallery.querySelectorAll('[data-gallery-image]'));
					var mainImage = gallery.querySelector('[data-gallery-main]');
					var mainWebp = gallery.querySelector('[data-gallery-main-webp]');
					var thumbsContainer = gallery.querySelector('[data-gallery-thumbs]');
					if (!mainImage || !thumbs.length) return;

					var currentIndex = 0;
					var imageUrl = function(path) {
						return path.charAt(0) === '/' ? path : '/' + path;
					};
					var showImage = function(index) {
						currentIndex = (index + thumbs.length) % thumbs.length;
						var activeThumb = thumbs[currentIndex];
						mainImage.src = imageUrl(activeThumb.getAttribute('data-gallery-image'));
						if (mainWebp) {
							var webpPath = activeThumb.getAttribute('data-gallery-webp');
							mainWebp.srcset = webpPath ? imageUrl(webpPath) : '';
						}
						thumbs.forEach(function(thumb, thumbIndex) {
							var isActive = thumbIndex === currentIndex;
							thumb.classList.toggle('is-active', isActive);
							thumb.setAttribute('aria-selected', isActive ? 'true' : 'false');
							thumb.setAttribute('aria-current', isActive ? 'true' : 'false');
						});
						activeThumb.scrollIntoView({ block: 'nearest', inline: 'nearest', behavior: 'smooth' });
					};

					thumbs.forEach(function(thumb, index) {
						thumb.addEventListener('click', function() {
							showImage(index);
						});
					});

					gallery.querySelectorAll('[data-gallery-main-nav]').forEach(function(button) {
						button.addEventListener('click', function() {
							showImage(currentIndex + parseInt(button.getAttribute('data-gallery-main-nav'), 10));
						});
					});
					gallery.setAttribute('tabindex', '0');
					gallery.addEventListener('keydown', function(event) {
						if (event.key === 'ArrowLeft' || event.key === 'ArrowUp') {
							event.preventDefault();
							showImage(currentIndex - 1);
						} else if (event.key === 'ArrowRight' || event.key === 'ArrowDown') {
							event.preventDefault();
							showImage(currentIndex + 1);
						}
					});
					var touchStartX = null;
					gallery.addEventListener('touchstart', function(event) {
						touchStartX = event.changedTouches[0].clientX;
					}, { passive: true });
					gallery.addEventListener('touchend', function(event) {
						if (touchStartX === null) return;
						var deltaX = event.changedTouches[0].clientX - touchStartX;
						touchStartX = null;
						if (Math.abs(deltaX) >= 40) showImage(currentIndex + (deltaX < 0 ? 1 : -1));
					}, { passive: true });
					mainImage.addEventListener('error', function() {
						mainImage.alt = 'Изображение товара недоступно';
						gallery.classList.add('has-image-error');
					});

					gallery.querySelectorAll('[data-gallery-nav]').forEach(function(button) {
						button.addEventListener('click', function() {
							var direction = parseInt(button.getAttribute('data-gallery-nav'), 10);
							var isHorizontal = thumbsContainer.scrollWidth > thumbsContainer.clientWidth;
							thumbsContainer.scrollBy(isHorizontal ? { left: direction * 150, behavior: 'smooth' } : { top: direction * 150, behavior: 'smooth' });
						});
					});

					if (thumbs.length < 2) {
						gallery.querySelectorAll('[data-gallery-nav], [data-gallery-main-nav]').forEach(function(button) {
							button.hidden = true;
						});
					}
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

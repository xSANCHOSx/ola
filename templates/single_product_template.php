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
$defaultDescription = 'Средства для волос Olaplex (Олаплекс) для домашнего использования можно заказать у нас! Отличные цены, доставка по всей территории России!';
$pageTitle = !empty($currentProduct['seo_title']) ? htmlspecialchars((string)$currentProduct['seo_title'], ENT_QUOTES, 'UTF-8') : $defaultTitle;
$pageDescription = !empty($currentProduct['seo_description']) ? htmlspecialchars((string)$currentProduct['seo_description'], ENT_QUOTES, 'UTF-8') : $defaultDescription;
?>

<!DOCTYPE html>
<html lang="ru">
<?php $extraCss = ($extraCss ?? '') . '
<link rel="stylesheet" href="/css/flexslider.css">';
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
		<div class="max-feature-section-list" class="container-fluid even3">
			<div class="row">
				<div class="col-sm-12 col-md-5 offset-md-1 visible-md visible-lg ">
					<div class="img animated fadeInDown"><img
							src="<?= htmlspecialchars($currentProduct['image'], ENT_QUOTES, 'UTF-8') ?>"
							alt="<?= htmlspecialchars($currentProduct['name'], ENT_QUOTES, 'UTF-8') ?>" fetchpriority="high"></div>
				</div>
				<div class="col-sm-12 col-md-5 offset-md-1 visible-xs visible-sm">
					<div class="image">
						<?= webp_img($currentProduct['image'], $currentProduct['name'], 'img-responsive', ['width' => 600, 'height' => 600]) ?>
					</div>
				</div>
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
									<img style="width: 18px;" src="/images/star.png" loading="lazy">
									<img style="width: 18px;" src="/images/star.png" loading="lazy">
									<img style="width: 18px;" src="/images/star.png" loading="lazy">
									<img style="width: 18px;" src="/images/star.png" loading="lazy">
									<img style="width: 18px;" src="/images/star.png" loading="lazy">
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
	<script defer src="/js/cart.js"></script>
	<script defer src="/js/cart-init.js"></script>
	<script defer src="/js/jquery.flexslider-min.js"></script>
	<script defer src="/js/main.js?v=<?= date('Ymd', filemtime(__DIR__ . '/../js/main.js')) ?>"></script>

	<script>
		window.addEventListener('DOMContentLoaded', function() {
			// tiny helper function to add breakpoints
			function getGridSize() {
				return (window.innerWidth < 600) ? 2 :
					(window.innerWidth < 900) ? 3 : 4
			}

			$('.flexslider').flexslider({
				animation: "slide",
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
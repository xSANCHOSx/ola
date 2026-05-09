<?php
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

?>

<!DOCTYPE html>
<html lang="ru">

<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<meta name="yandex-verification" content="0bef8202615abb0b" />
	<meta name="google-site-verification" content="RzlStn4gRa1keV7FRXBfC3k3Ns_qAVPFEEos43KSWsA" />
	<?php
	$defaultTitle = htmlspecialchars($currentProduct['name'], ENT_QUOTES, 'UTF-8') . ' - Олаплекс (Olaplex) Для Волос Купить В Интернет-Магазине';
	$defaultDescription = 'Средства для волос Olaplex (Олаплекс) для домашнего использования можно заказать у нас! Отличные цены, доставка по всей территории России!';
	$metaTitle = !empty($currentProduct['seo_title']) ? htmlspecialchars((string)$currentProduct['seo_title'], ENT_QUOTES, 'UTF-8') : $defaultTitle;
	$metaDescription = !empty($currentProduct['seo_description']) ? htmlspecialchars((string)$currentProduct['seo_description'], ENT_QUOTES, 'UTF-8') : $defaultDescription;
	?>
	<title><?= $metaTitle ?></title>
	<meta name="description" content="<?= $metaDescription ?>" />

	<!-- CSS Libraries -->
	<link href="https://fonts.googleapis.com/css?family=Open+Sans:400,700&amp;subset=cyrillic-ext" rel="stylesheet">
	<link rel="stylesheet" href="css/bootstrap.min.css">
	<link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/font-awesome/4.4.0/css/font-awesome.min.css">
	<link href="css/animate.css" rel="stylesheet" type="text/css" />

	<link href="css/icon.css" rel="stylesheet" type="text/css" />
	<link rel="icon" href="favicon.ico" type="image/x-icon">
	<link href="css/flexslider.css" rel="stylesheet" type="text/css" />
	<!-- Custom CSS -->
	<link rel="stylesheet" href="css/styles.css">
	<link rel="stylesheet" href="css/wicart.css">
	<!-- Yandex.Metrika counter -->
	<script type="text/javascript">
		(function(m, e, t, r, i, k, a) {
			m[i] = m[i] || function() {
				(m[i].a = m[i].a || []).push(arguments)
			}
			m[i].l = 1 * new Date()
			for (var j = 0; j < document.scripts.length; j++) {
				if (document.scripts[j].src === r) {
					return
				}
			}
			k = e.createElement(t), a = e.getElementsByTagName(t)[0], k.async = 1, k.src = r, a.parentNode.insertBefore(k, a)
		})
		(window, document, "script", "https://mc.yandex.ru/metrika/tag.js", "ym")

		ym(48443993, "init", {
			clickmap: true,
			trackLinks: true,
			accurateTrackBounce: true,
			webvisor: true,
			ecommerce: "dataLayer"
		});
	</script>
	<noscript>
		<div><img src="https://mc.yandex.ru/watch/48443993" style="position:absolute; left:-9999px;" alt="" /></div>
	</noscript>
	<!-- /Yandex.Metrika counter -->

	<!-- Global site tag (gtag.js) - Google Analytics -->
	<script async src="https://www.googletagmanager.com/gtag/js?id=UA-120050968-1"></script>
	<script>
		window.dataLayer = window.dataLayer || []

		function gtag() {
			dataLayer.push(arguments)
		}
		gtag('js', new Date())

		gtag('config', 'UA-120050968-1');
	</script>

</head>

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
					<div class="img animate--one" data-animate="fadeInDown" data-duration="2"><img
							src="<?= htmlspecialchars($currentProduct['image'], ENT_QUOTES, 'UTF-8') ?>"
							alt="<?= htmlspecialchars($currentProduct['name'], ENT_QUOTES, 'UTF-8') ?>"></div>
				</div>
				<div class="col-sm-12 col-md-5 offset-md-1 visible-xs visible-sm">
					<div class="image"><img src="<?= htmlspecialchars($currentProduct['image'], ENT_QUOTES, 'UTF-8') ?>"
							alt="<?= htmlspecialchars($currentProduct['name'], ENT_QUOTES, 'UTF-8') ?>"></div>
				</div>
				<div class="col-sm-12 col-md-5 tovar-name animate--one" data-animate="fadeInDown" data-duration="3">
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
									<img style="width: 18px;" src="/images/star.png">
									<img style="width: 18px;" src="/images/star.png">
									<img style="width: 18px;" src="/images/star.png">
									<img style="width: 18px;" src="/images/star.png">
									<img style="width: 18px;" src="/images/star.png">
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
							<?php echo  product_button_label($currentProduct) ?>
							<p><span class="regular_price"><strong>Предзаказ</strong></span></p>
							<p><strong>Срок доставки: 7-14 дней</strong></p>
						<?php } else { ?>
							<p><span class="regular_price"><strong>Нет в наличии</strong></span></p>

						<?php } ?>
						<p><?php echo nl2br($currentProduct['short_desc']); ?></p>
						<button class="b1c"
							<?php if (!empty($currentProduct['in_stock']) || (!empty($currentProduct['status']) && $currentProduct['status'] === 'preorder')) { ?>
							onclick="cart.addToCart(this, <?= (int)$currentProduct['id'] ?>)" <?php } else { ?> disabled <?php } ?>>
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
	<script src="/js/jquery-3.7.1.min.js"></script>
	<script src="/js/bootstrap.min.js"></script>
	<script src="/js/jquery.inputmask.bundle.js"></script>
	<script src="/js/cart.js" type="text/javascript"></script>
	<script src="/js/cart-init.js" type="text/javascript"></script>
	<script defer src="/js/jquery.flexslider-min.js"></script>
	<script type="text/javascript">
		/*	$(window).load(function(){
				$('.flexslider').flexslider({
				animation: "slide"
				});
			});*/
		(function() {

			// store the slider in a local variable
			var $window = $(window),
				flexslider = {
					vars: {}
				}

			// tiny helper function to add breakpoints
			function getGridSize() {
				return (window.innerWidth < 600) ? 2 :
					(window.innerWidth < 900) ? 3 : 4
			}

			/* $(function() {
				 SyntaxHighlighter.all();
			 });
			*/

			$window.load(function() {
				$('.flexslider').flexslider({
					animation: "slide",
					itemWidth: 240,
					itemMargin: 5,
					animationLoop: true,
					minItems: getGridSize(), // use function to pull in initial value
					maxItems: getGridSize(), // use function to pull in initial value
					startAt: 0,
					slideshow: true, //Boolean: Animate slider automatically
					slideshowSpeed: 7000, //Integer: Set the speed of the slideshow cycling, in milliseconds
					animationSpeed: 600, //Integer: Set the speed of animations, in milliseconds
					initDelay: 0,
				})
			})

			// check grid size on resize event
			$window.resize(function() {
				var gridSize = getGridSize()

				flexslider.vars.minItems = gridSize
				flexslider.vars.maxItems = gridSize
			})
		}());
	</script>

	<!-- Custom JavaScript -->
	<script src="/js/main.js"></script>

	<script>
		$(document).ready(function() {
			$('#order .close_popup').click(function() {
				$('#formToSend input:checkbox').removeAttr("checked")
				$("#formToSend input[type=submit]").attr('disabled', 'disabled')
				$('#formToSend input[type=hidden].valTrFal').val('valTrFal_disabled')
			})
			$(function() {
				$('#formToSend input:checkbox').change(function() {
					if ($(this).is(':checked')) {
						$("#formToSend input[type=submit]").removeAttr('disabled')
						$('#formToSend input[type=hidden].valTrFal').val('valTrFal_true')
					} else {
						$("#formToSend input[type=submit]").attr('disabled', 'disabled')
						$('#formToSend input[type=hidden].valTrFal').val('valTrFal_disabled')
					}
				})
			})
			$('#send').click(function() {
				if (($("#formToSend input[type=text]").val()) == !"") {
					$('#formToSend input[type=hidden].valTrFal').remove()
					$('#formToSend .font-geometria-light').remove()
					$('#overflw .basket_num_buttons').remove()

				}
			})
		});

		//});
	</script>

	<script>
		$('#phoneNumber').inputmask("+7(999)999-99-99")
		$(function() {
			var videos = $(".youtube")

			videos.on("click", function() {
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
	charset="UTF-8" async></script>

</body>

</html>
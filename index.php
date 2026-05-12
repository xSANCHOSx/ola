<?php session_start();
include 'templates/helpers.php';
include 'data/products.php';
save_utm_cookies();
$currentUrl = $_SERVER['REQUEST_URI'];
?>
<!DOCTYPE html>
<html lang="ru">

<?php require __DIR__ . '/templates/head.php'; ?>

<body>
	<?php include 'templates/header.php'; ?>

	<!-- Container Start Home -->
	<div class="container-fluid">
		<div class="row">
			<section class="slider">
				<div class="flexslider">
					<ul class="slides">
						<li>
							<div class="flex-caption_l">
								<span>
									<!--Гарантия Защиты Волос-->
								</span>
							</div>
							<img src="/images/banner-1.jpg" fetchpriority="high" />
							<div class="flex-caption_r">
								<span>
									<!--Один Ингридиент меняет все-->
								</span>
							</div>
						</li>
						<li>
							<img src="/images/banner-2.jpg" loading="lazy" />
						</li>
					</ul>
				</div>
			</section>
		</div>
	</div>
	<!-- ./ Container End Home -->
	<!-- About us section -->
	<section id="max-aboutus-section">
		<div class="max-section-title">
			<h1>Olaplex для волос</h1>
			<h2>Красота ваших волос в домашнем уходе салонными средствами</h2>
		</div>
		<div class="container" id="aboutus-section">
			<div class="row">
				<div class="col-md-6">
					<div id="max-feature-para" class="animate--one animated fadeInDown">
						<p style="text-align: justify;">Уникальный продукт Olaplex создан для восстановления и усиления поврежденных
							дисульфидных связей, отвечающих за силу, прочность и эластичность волос. Всего один компонент. Без масел,
							альдегидов, сульфатов и силиконов. Формула восстанавливает и защищает волосы, травмированные частыми
							окрашиваниями. Если процедура окрашивания будет производиться первый раз, то формула защитит Ваши волосы
							от возможных повреждений, в процессе окрашивания. </p>
						<h2 style="text-align: center;"><b>100% сохранность волос</b></h2>

						<p style="text-align: justify;">Olaplex для волос – гарантия и страховка, шанс в первый раз не искать
							компромиссных решений между результатом окрашивания и здоровья волос посетителя. Эффективность заметна
							после первого использования продукта.</p>
						<p>Результат:</p>
						<ul>
							<li>Возвращает утраченную упругость, прочность и эластичность волосам после окрашивания;</li>
							<li>Защищает Ваши волосы от повреждений во время окрашивания;</li>
							<li>Помогает сохранить природный блеск;</li>
							<li>Сохраняет яркость и насыщенность цвета. </li>
						</ul>
					</div>
				</div>
				<div class="col-md-6 youtube animate--one animated fadeInDown">
					<div class="flexslider" id="slider">
						<ul class="slides">
							<li id="slide1">
								<img src="/images/bg.png" loading="lazy">
								<!-- <iframe width="560" height="315" src="https://www.youtube.com/embed/NKfTbACBRfs" title="YouTube video player" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>-->
							</li>

							<li id="slide2">
								<img src="/images/poster.png" loading="lazy">
								<!-- <iframe width="560" height="315" src="https://www.youtube.com/embed/NKfTbACBRfs" title="YouTube video player" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>-->
							</li>
						</ul>
					</div>
				</div>
			</div>
		</div>
	</section>
	<!-- ./About us section end -->
	<!-- Feature Section Starts -->
	<section id="max-featured-section" class="products_list">
		<?php foreach ($products as $index => $product): ?>
		<?php
			$template = 'templates/product_template_even_mod.php';
			include $template;
			?>
		<?php endforeach; ?>
	</section>
	<!-- ./ Feature Section Ends -->


	<!--  Work Section Starts -->
	<section id="max-work-section">
		<div class="max-section-title">
			<h2>Использование</h2>
		</div>
		<div id="max-work-container" class="container">
			<div class="row">
				<div class="col-md-10 col-md-offset-1 col-xs-12" style="text-align: justify;">
					<p>Средства для волос Olaplex представлены в широком ассортименте, предлагая потребителям разнообразные товары
						для ухода за волосами. В каталоге этого бренда вы найдете стайлинг-продукты, аксессуары и различные
						бальзамы. Среди предлагаемых товаров также имеются средства для очищения волос, несмываемые композиции,
						гели, лаки и лосьоны. Косметические скрабы также входят в ассортимент Olaplex, обеспечивая разносторонний
						уход за вашими волосами.</p>
					<p><span>Олаплекс для волос произведен по особенной технологии, которая позволила создать трехступенчатый уход
							за волосами, подвергающимися окрашиванию или иному неблагоприятному воздействию. Например, термообработка,
							химическая завивка и пр. Олаплекс работает с любым красителем, в том числе со смесями. Совместим
							практически со всеми уходами за волосами, которые предлагают мировые салоны.</span></p>
					<p><span>Содержащиеся в уходе </span>Olaplex<span> ингредиенты делают волосы здоровыми, убирают повреждения их
							структуры, готовят к окрашиванию, которое пройдет без повреждения. Активные элементы поддержат салонный
							уход, делая его более продуктивным.</span></p>
					<p>Абсолютно безопасный Olaplex не испытывался на животных. В составе не содержит фталатов, сульфатов,
						дегидроэпиандростерона и спирта, лишенного водорода. Продукт возобновляет разорванные дисульфидные
						соединения, которые были повреждены в процессе окрашиваний, выравниваний или завиваний волос.</p>
					<h2>Методика Olaplex заключается в пяти этапах.</h2>
					<p><span>Трехступенчатый уход разбит на 5 этапов. К первой ступени относят 2 этапа:</span></p>
					<ul>
						<li><span>очистка шампунем;</span></li>
						<li><span>смягчение кондиционером.</span></li>
					</ul>
					<p><span>Во второй ступени также 2 этапа:</span></p>
					<ul>
						<li><span>BondMultiplier</span></li>
						<li><span>BondPerfector</span></li>
					</ul>
					<p><span>А в третьей 1 этап &mdash; Hair Perfector, который можно использовать для любого типа волос.</span>
					</p>
					<p>Шампунь Bond Maintenance очищает волосы, далее кондиционер укрепляет и увлажняет.</p>
					<p>BondMultiplier и BondPerfector &ndash; это два этапа, обеспечивающих надежную защиту волос от любых
						повреждений во время салонных процедур.</p>
					<p>Пятый этап &ndash; Hair Perfector, используется в домашних условиях. Защищает локоны от механических и
						термических ежедневных повреждений, обычно применяется раз в неделю и чаще.</p>
					<p><strong>Применение средств:</strong></p>
					<p>Нанесите соответствующее количество шампуня Олаплекс на мокрые волосы. Вспеньте и тщательно смойте. Далее
						нанести кондиционер Олаплекс и оставить на 3 минуты. Ополоснуть водой.</p>
					<h2>Применение №3 &laquo;Совершенства Волос&raquo; дома</h2>
					<p>Эликсир наносится на слегка влажные локоны и прикорневую зону. Равномерно распределить руками, после
						тщательно расчесать. Подождать 10-15 минут (при поврежденных прядях рекомендуется нанести второй слой
						эликсира по прошествии 10-ти минут, и подождать еще около 20-ти минут). Держать дольше &ndash; не страшно,
						эффект будет только лучше. Смыть с шампунем, воспользоваться кондиционером.</p>
					<p>Специалисты советуют использовать средство Olaplex №3 один раз в семь дней, если нет потребности применять
						чаще.</p>
					<p>Использовать уход Олаплекс Hair Perfector можно регулярно или при необходимости защитить, восстановить,
						сделать волосы более ухоженными. Система нового поколения всегда даст идеальный результат.</p>
					<h2>Фиксирующий коктейль Bond Perfector является второй ступенью</h2>
					<p>Применение:</p>
					<ul>
						<li>Смыть краску. В случае необходимости нанести тонирующую основу (при потребности с добавлением Bond
							Multiplier). Подождать указанное количество времени, тщательно смыть водой.</li>
						<li>Распределить нужное количество коктейля Olaplex(от 5-ти до 25-ти мл) по завершению процедуры
							окрашивания. Прочесать пряди расческой.</li>
						<li>Подождать минимум 20 мин. Если выждать дольше, результат будет только лучше.</li>
						<li>Тщательно смыть с шампунем, после воспользоваться кондиционером.</li>
					</ul>
					<p><span>BondMultiplier помогает волосам восстановить структуру. Наносится вместе с краской или
							непосредственно перед ее нанесением.</span></p>
					<p><span>Маска для волос </span>O<span>laplex помогает делать волосы более эластичными, проникая внутрь
							волоса, укрепляя его, уплотняя, давая гладкость, а следовательно и здоровый блеск.</span></p>
					<p>Защитный концентрат Olaplex является первым этапом, который применяется во время процедур с клиентами, с
						поврежденными волосами.</p>
					<p>В нашем интернет-магазине Вы можете купить комплексы для ухода Olaplex по оптимальным ценам.</p>
				</div>
			</div>
		</div>
	</section>
	<!-- ./ Work section ends -->



	<!-- Meet the team section -->
	<section id="max-team-section">
		<div class="max-section-title">
			<h2>Реальные примеры</h2>
		</div>
		<div id="max-team" class="container">
			<div class="row">
				<div class="col-md-4 col-xs-12">
					<div class="member-box animate--one animated zoomIn">
						<div class="member-profile">
							<img src="/images/5.png" alt="Member 1" loading="lazy">
						</div>

					</div>
				</div>
				<div class="col-md-4 col-xs-12">
					<div class="member-box animate--one animated zoomIn">
						<div class="member-profile">
							<img src="/images/6.jpg" alt="Member 1" loading="lazy">
						</div>
					</div>
				</div>
				<div class="col-md-4 col-xs-12">

					<div class="member-box animate--one animated zoomIn">
						<div class="member-profile">
							<img src="/images/5.png" alt="Member 1" loading="lazy">
						</div>
					</div>
				</div>
			</div>
		</div>
	</section>
	<!-- ./ Ending Meet the team section -->
	<!-- What Customers Say section -->
	<section id="max-testimonial-section">
		<div class="row">
			<div class="col-md-12">
				<div class="max-section-title testimonial-title">
					<h2>Отзывы Олаплекс</h2>
				</div>
			</div>
		</div>

		<div id="max-customer-section" class="container">
			<div class="row">
				<div class="col-md-12">
					<div class="flexslider">
						<ul class="slides">
							<li>
								<!-- Quote 1 -->
								<div class="item">
									<div class="row">
										<div class="col-sm-2 col-sm-offset-2 col-xs-12 testimonial-box">
											<img class="img-responsive " src="/images/test1.png" alt="Profile 1" loading="lazy">
										</div>
										<div class="col-sm-6 col-xs-12 testimonial-box">
											<div class="testimonial-name">Татьяна</h2>
												<p class="testimonial-quote">Я считаю, что радость клиента важна настолько же, насколько важен
													результат.
													Каждая девушка, пришедшая ко мне в салон, с восторгом смотрела на свои волосы, и обещала
													рекомендовать
													мои услуги своим подругам! А все благодаря роскошной системе Olaplex. Она действительно
													реанимирует даже
													самые, казалось бы, безнадежные пряди. И на цвет влияет тоже хорошо, сама этими штуками
													пользуюсь, и вам
													советую!</p>

											</div>
										</div>
									</div>
							</li>
							<li>
								<!-- Quote 2 -->
								<div class="item">
									<div class="row">
										<div class="col-sm-2 col-sm-offset-2 col-xs-12 testimonial-box">
											<img class="img-responsive " src="/images/test2.png" alt="Profile 2" loading="lazy">
										</div>
										<div class="col-sm-6 col-xs-12 testimonial-box">
											<div class="testimonial-name">Анна</h2>
												<p class="testimonial-quote">Ко мне часто приходят девушки, которые раньше красились дома, ну и,
													конечно же,
													сожгли свои волосы. Я в таких случаях стопроцентно использую защиту и коктейль Olaplex,
													поскольку после них
													локоны становятся блестящими, мягкими и выглядят ухоженными, каждая клиентка уходит счастливой
													неимоверно!
													Сразу же видно, даже на мокрых волосах, что они стали здоровее. Действительно отличный
													помощник при окрашивании
													или выравнивании. </p>
											</div>
										</div>
									</div>
							</li>
							<li>
								<!-- Quote 3 -->
								<div class="item">
									<div class="row">
										<div class="col-sm-2 col-sm-offset-2 col-xs-12 testimonial-box">
											<img class="img-responsive " src="/images/test3.png" alt="Profile 3" loading="lazy">
										</div>
										<div class="col-sm-6 col-xs-12 testimonial-box">
											<div class="testimonial-name">Ольга</h2>
												<p class="testimonial-quote">Перед тем, как использовать что-то новенькое в салоне, я сначала
													пробую на себе!
													В общем, скажу сразу – это потрясающие средства! Мои волосы стали гладкими, убрались все
													торчащие волоски.
													Мягкие, очень приятные на ощупь, и цвет получился просто шикарный! После мытья головы эффект
													не пропал,
													а вот домашний уход имеет накопительный эффект, что очень радует. За такие чудеса не жалко и
													миллион отдать,
													что мне, что моим посетителям!</p>
											</div>
										</div>
									</div>
							</li>
						</ul>
					</div>
				</div>
			</div>
		</div>
	</section>
	<!-- Meet the team section -->
	<section id="max-team-section">
		<div class="max-section-title">
			<h2>Кто использует Olaplex</h2>
		</div>
		<div id="max-team" class="container">
			<div class="flexslider">
				<ul class="slides">
					<li>
						<div class="col-sm-4 col-xs-12">
							<div class="member-box animate--one animated zoomIn">
								<div class="member-profile star">
									<img src="/images/kim.jpg" alt="Member 1" loading="lazy">
									<div class="member-name">KIM KARDASHIAN</div>
								</div>

							</div>
						</div>
						<div class="col-sm-4 col-xs-12">
							<div class="member-box animate--one animated zoomIn">
								<div class="member-profile star">
									<img src="/images/khloe.jpg" alt="Member 2" loading="lazy">
									<div class="member-name">KHLOE KARDASHIAN</div>
								</div>
							</div>
						</div>
						<div class="col-sm-4 col-xs-12">
							<div class="member-box animate--one animated zoomIn">
								<div class="member-profile star">
									<img src="/images/jlo.jpg" alt="Member 3" loading="lazy">
									<div class="member-name">JENNIFER LOPEZ</div>
								</div>
							</div>
						</div>
					</li>
					<li>
						<div class="col-sm-4 col-xs-12">
							<div class="member-box animate--one animated zoomIn">
								<div class="member-profile star">
									<img src="/images/gwenth.jpg" alt="Member 4" loading="lazy">
									<div class="member-name">GWYNETH PALTROW</div>
								</div>

							</div>
						</div>
						<div class="col-sm-4 col-xs-12">
							<div class="member-box animate--one animated zoomIn">
								<div class="member-profile star">
									<img src="/images/theron.jpg" alt="Member 5" loading="lazy">
									<div class="member-name">CHARLIZE THERON</div>
								</div>
							</div>
						</div>

						<div class="col-sm-4 col-xs-12">
							<div class="member-box animate--one animated zoomIn">
								<div class="member-profile star">
									<img src="/images/blunt.jpg" alt="Member 6" loading="lazy">
									<div class="member-name">EMILY BLUNT</div>
								</div>
							</div>
						</div>
					</li>
				</ul>
			</div>
		</div>
	</section>
	<!-- ./ Ending Meet the team section -->
	<?php include 'templates/delivery.php'; ?>
	<?php include 'templates/footer.php'; ?>
	<?php include 'templates/order_form.php'; ?>

	<!-- All JavaScript libraries -->
	<script defer src="/js/jquery-3.7.1.min.js"></script>
	<script defer src="/js/bootstrap.min.js"></script>
	<script>
	<?php
		$_productsById = [];
		foreach ($products as $p) {
			// Сохраняем по числовому ключу (010 → 10) и оригинальной строке
			$_numKey = (int)$p['id'];
			$_productsById[$_numKey] = $p;
			if ((string)$_numKey !== (string)$p['id']) {
				$_productsById[(string)$p['id']] = $p; // '010' также
			}
		}
		?>
	window.PRODUCTS = <?= json_encode($_productsById, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?>;
	</script>
	<script defer src="/js/cart.js"></script>
	<script defer src="/js/cart-init.js"></script>
	<script defer src="/js/jquery.flexslider-min.js"></script>
	<script defer src="/js/main.js?v=<?php echo date('Ymd', filemtime(__DIR__ . '/js/main.js')); ?>"></script>

	<script>
	window.addEventListener('DOMContentLoaded', function() {
		$('.flexslider').flexslider({
			animation: "slide"
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

		$("#slide1").on("click", function() {
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

		$("#slide2").on("click", function() {
			var elm2 = $(this),
				conts2 = elm2.contents(),
				le2 = conts2.length,
				ifr2 = null
			for (var i = 0; i < le2; i++) {
				if (conts2[i].nodeType == 8) ifr2 = conts2[i].textContent
			}
			elm2.addClass("player").html(ifr2)
			elm2.off("click")
		})
	});
	</script>
</body>
</html>

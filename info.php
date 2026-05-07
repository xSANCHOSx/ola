<!DOCTYPE html>
<html lang="ru">

<?php 
$pageTitle = 'Информация о продукции Olaplex';
$pageDescription = 'FAQ о том, как правильно пользоваться продукцией Олаплекс, что нужно знать перед тем как начать использовать продукцию.';
require __DIR__ . '/templates/head.php'; 
?>

<body>
	<header id="header" class="sticky">
		<div class="container-fluid">
			<nav class="navbar navbar-default ">

				<!-- Brand and toggle get grouped for better mobile display -->
				<div class="navbar-header">
					<button type="button" class="navbar-toggle collapsed" data-toggle="collapse"
						data-target="#bs-example-navbar-collapse-1" aria-expanded="false">
						<span class="sr-only">Toggle navigation</span>
						<span class="icon-bar"></span>
						<span class="icon-bar"></span>
						<span class="icon-bar"></span>
					</button>
					<a class="navbar-brand" href="/">
						<img id="logo" src="images/logo.png" alt="Olaplex Logo">
					</a>

				</div>

				<!-- Collect the nav links, forms, and other content for toggling -->
				<div class="collapse navbar-collapse" id="bs-example-navbar-collapse-1">
					<ul class="nav navbar-nav main">
						<li><a href="/#max-aboutus-section">Что это?</a></li>
						<li><a href="/#max-featured-section">Продукция</a></li>
						<li><a href="/#max-work-section">Как использовать</a></li>
						<li><a href="/#max-purchase-section">Доставка</a></li>
						<li><a href="info.html">Справка</a></li>
					</ul>
					<div class="number navbar-brand hidden-sm hidden-xs">
						<a href="tel:+74950322929">+7 (495) 032-29-29</a>
						<a href="https://wa.me/79096962720"><img src="images/whatsapp.svg" class="whatsapp" alt="whatsapp"></a>
					</div>
					<div class="cart visible-lg visible-md cart_full" onclick="cart.showWinow('bcontainer', 1)">
						<img src="images/basket.png" />
						<span id="basketwidjet"></span>
					</div>
				</div>
				<div class="number navbar-brand hidden-md hidden-lg"><a href="tel:+74950322929">+7 (495) 032-29-29</a>
					<a href="https://wa.me/79096962720"><img src="images/whatsapp.svg" class="whatsapp" alt="whatsapp"></a>
				</div>
				<div class="cart visible-sm visible-xs cart_mobile" onclick="cart.showWinow('bcontainer', 1)">
					<img src="images/basket.png" /><span id="basketwidjet"></span>
				</div>
				<!-- /.navbar-collapse -->
			</nav>
		</div>
		<!-- /.container-fluid -->

	</header>


	<!-- About us section -->
	<section id="max-aboutus-section product">
		<div class="max-section-title">
			<h1>Информация о том как правильно использовать Олаплекс</h1>
		</div>
		<div class="container" id="aboutus-section">
			<div class="row">
				<div class="col-md-12">
					<div id="max-feature-para" class="animate--one" data-animate="fadeInDown" data-duration="1">
						<h2 style="text-align: center;">КАК СОБРАТЬ ДОЗАТОР?</h2>
						<ol>
							<li style="text-align: justify;">Снять герметическую упаковку с бутылочки защитного концентрата Olaplex
								No.1 Bond Multiplier. Поместить тонкую часть дозатора в бутылку и закрутить его.</li>
							<li style="text-align: justify;">Для эксплуатации совлечь колпачок с флакона, плавно сжать бутылочку,
								отмерив нужный объем средства при помощи разделений дозатора.</li>
							<li style="text-align: justify;">Если было отмерено больше необходимости, остатки продукта можно оставить
								в крышке на следующий раз.</li>
							<li style="text-align: justify;">Хранить бутылку №1 закрытой, исключительно в вертикальной позиции.</li>
						</ol>
						<h2 style="text-align: center;">УХОД АКТИВНАЯ ЗАЩИТА OLAPLEX</h2>
						<p style="text-align: justify;">Лучшее средство для подготовки поврежденных локонов к обработке &ndash; это
							Уход Активная Защита. Уход является глубоким перезапуском для волос, который возвратит их строение к
							положению, когда волосы впору заново окрасить. Реализуется до, и/или после всяческих процедур для волос.
							Предназначен для любого типа волос, от натуральных до сильно поврежденных.</p>
						<p style="text-align: justify;">Полезная рекомендация: при выполнении блондирования в несколько этапов,
							советуем применять уход Активная Защита после каждой стадии.</p>
						<ol>
							<li style="text-align: justify;">Защитный состав Olaplexсмешать в пропорциях половины дозы (15 мл) Bond
								Multiplier и 90 мл дистиллированной воды в чистой таре без распылителя. Защитный концентрат не рассчитан
								на распыление.</li>
							<li style="text-align: justify;">Напитать сухие волосы по всей длине. При обильном загрязнении или избытке
								стайлинговых средств на локонах рекомендуется предварительно вымыть голову с помощью шампуня.</li>
							<li style="text-align: justify;">Подождать 5-10 минут.</li>
							<li style="text-align: justify;">Нанести Olaplex №2 фиксирующий коктейль, прочесать пряди расческой,
								подождать 15-20 минут. Чем больше подождать, тем эффективнее воздействие.</li>
							<li style="text-align: justify;">В заключение процесса вымыть голову с применением шампуня,
								воспользоваться кондиционером.</li>
						</ol>
						<h2 style="text-align: center;">УХОД БАЗОВАЯ ЗАЩИТА OLAPLEX</h2>
						<p style="text-align: justify;">Бесхитростная быстрая Базовая Защита Olaplex&ndash; это замечательная
							возможность предложить услугу любому посетителю, даже если у него не окрашены волосы. &nbsp;Уход служит
							для укрепления структуры волос, их смягчения и укрощения. Базовая Защита с легкостью расширяет спектр
							возможностей и позволяет более эффективно расходовать фиксирующий коктейль Bond Perfector.</p>
						<ol>
							<li style="text-align: justify;">Необходимый объем №2 (от 5-ти до 25-ти мл) на слегка влажные волосы.
								Бережно прочесать и подождать приблизительно 5 минут.</li>
							<li style="text-align: justify;">Нанести дополнительный слой без смывания, выждать 10 минут.</li>
							<li style="text-align: justify;">В заключение процесса вымыть голову с шампунем и применением
								кондиционера.</li>
						</ol>
					</div>
				</div>
			</div>
		</div>
	</section>

	<!-- ./About us section end -->
	<?php include 'templates/footer.php'; ?>
	<!-- ./About us section end -->
	<!---Форма для магазина-------------------------------->
	<div id="order" class="popup">
		<a href="#" onclick="cart.closeWindow('order', 0)" style="float:right">[закрыть]</a>
		<h4>Введите ваши контактные данные</h4>

		<form id="formToSend">
			<input id="fio" type="text" placeholder="Ваши фамилия и имя" class="" />
			<input id="city" type="text" placeholder="Город" class="text-input" />
			<input id="phone" type="text" placeholder="Контактный телефон" class="text-input" />
			<input id="email" type="text" placeholder="Электронная почта" class="" />
			<br>
			<textarea id="question" placeholder="Адрес"></textarea>
		</form>
		<button onclick="cart.sendOrder('formToSend,overflw,bsum');" href="#">Отправить заказ</button>
	</div>


	<!----------------------------------------------------->

	<!-- All JavaScript libraries -->
	<script src="js/jquery-3.7.1.min.js"></script>
	<script src="js/bootstrap.min.js"></script>
		<script src="js/jquery.inputmask.bundle.js"></script>
		<script src="js/cart.js" type="text/javascript"></script>
		<script src="js/cart-init.js" type="text/javascript"></script>
	
		<!-- Custom JavaScript -->
		<script src="js/main.js"></script>

</body>

</html>
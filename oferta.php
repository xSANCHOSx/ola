<!DOCTYPE html>
<html lang="ru">

<?php 
$pageTitle = 'Соглашение на обработку персональный данных';
$extraCss = '<meta name="robots" content="noindex, nofollow" />';
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
					<div class="number navbar-brand hidden-sm hidden-xs">+7 (495) 135-28-44</div>
					<div class="cart visible-lg visible-md cart_full" onclick="cart.showWinow('bcontainer', 1)">
						<img src="images/basket.png" />
						<span id="basketwidjet"></span>
					</div>
				</div>
				<div class="number navbar-brand hidden-md hidden-lg">+7 (495) 135-28-44</div>
				<div class="cart visible-sm visible-xs cart_mobile" onclick="cart.showWinow('bcontainer', 1)">
					<img src="images/basket.png" /><span id="basketwidjet"></span>
				</div>
				<!-- /.navbar-collapse -->
			</nav>
		</div>
		<!-- /.container-fluid -->

	</header>


	<!-- About us section -->
	<section id="max-aboutus-section" style="margin-top:60px;">
		<div class="max-section-title">
			<h2>Соглашение на обработку персональный данных</h2>
		</div>
		<div class="container" id="aboutus-section">
			<div class="row">
				<div class="col-md-12">
					<div id="max-feature-para" class="animate--one" data-animate="fadeInDown" data-duration="1">
						<p style="text-align: justify;font-size: 17px;">
							Предоставляя свои персональные данные Пользователь даёт согласие на обработку, хранение и использование
							своих персональных данных на основании ФЗ № 152-ФЗ «О персональных данных» от 27.07.2006 г. в следующих
							целях:</br>
						<ul>
							<li>Осуществление клиентской поддержки</li>
							<li>Получения Пользователем информации о маркетинговых событиях</li>
							<li>Проведения аудита и прочих внутренних исследований с целью повышения качества предоставляемых услуг.
							</li>
						</ul>
						</p>
						<p style="text-align: justify;font-size: 17px;">
							Под персональными данными подразумевается любая информация личного характера, позволяющая установить
							личность Пользователя/Покупателя такая как:</br>
						<ul>
							<li>Фамилия, Имя, Отчество</li>
							<li>Дата рождения</li>
							<li>Контактный телефон</li>
							<li>Адрес электронной почты</li>
							<li>Почтовый адрес</li>
						</ul>
						</p>
						<p style="text-align: justify;font-size: 17px;">
							Персональные данные Пользователей хранятся исключительно на электронных носителях и обрабатываются с
							использованием автоматизированных систем, за исключением случаев, когда неавтоматизированная обработка
							персональных данных необходима в связи с исполнением требований законодательства.
							Компания обязуется не передавать полученные персональные данные третьим лицам, за исключением следующих
							случаев:</br>
						<ul>
							<li>По запросам уполномоченных органов государственной власти РФ только по основаниям и в порядке,
								установленным законодательством РФ</li>
							<li>Стратегическим партнерам, которые работают с Компанией для предоставления продуктов и услуг, или тем
								из них, которые помогают Компании реализовывать продукты и услуги потребителям. Мы предоставляем третьим
								лицам минимальный объем персональных данных, необходимый только для оказания требуемой услуги или
								проведения необходимой транзакции.</li>
							<li>Компания оставляет за собой право вносить изменения в одностороннем порядке в настоящие правила, при
								условии, что изменения не противоречат действующему законодательству РФ. Изменения условий настоящих
								правил вступают в силу после их публикации на Сайте.</li>
						</ul>
						</p>
					</div>
				</div>
			</div>
		</div>
	</section>


	<!-- ./About us section end -->
	<?php include 'templates/footer.php'; ?>
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
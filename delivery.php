<!DOCTYPE html>
<html lang="ru">

<?php
session_start();
require_once __DIR__ . '/config/db.php';

$pageTitle = 'Доставка и оплата - Олаплекс (Olaplex) Для Волос Купить В Интернет-Магазине';
require __DIR__ . '/templates/head.php';
?>

<body>
	<?php include 'templates/header.php'; ?>

	<!-- About us section -->
	<section id="max-aboutus-section">
		<div class="max-section-title">
			<h2>ОПЛАТА И ДОСТАВКА</h2>
		</div>
		<div class="container" id="aboutus-section">
			<div class="row">
				<p class="zakaz-min">Сумма минимального заказа - <strong>500</strong>рублей.</p>
				<div class="col-md-12">
					<div id="max-feature-para" class="animate--one animated fadeInDown">
						<h2>ДОСТАВКА ПО МОСКВЕ:</h2>
						<p style="text-align:justify"><strong>Бесплатная доставка</strong>&nbsp;
							в пределах МКАД при сумме заказа свыше&nbsp;
							<strong>5000</strong>&nbsp;
							руб.
						</p>
						<p style="text-align:justify">Стоимость доставки в пределах МКАД - 350 руб.<br />Стоимость доставки за
							пределы МКАД - рассчитывается индивидуально. (В зависимости от удаленности транспортных узлов, трафика
							и т.д.)</p>
					</div>
				</div>
			</div>
			<div class="row">
				<div class="col-md-12">
					<div id="max-feature-para" class="animate--one animated fadeInDown">
						<h2>СРОКИ ДОСТАВКИ:</h2>
						<p style="text-align:justify">Курьерская доставка осуществляется в течении 2-3 рабочих дней.</p>
						<p style="text-align:justify">Доставка осуществляется на следующий день,
							после подтверждения заказа,
							если он был сделан до 16:00,
							и через день,
							если он был сделан после 16:00.</p>
					</div>
				</div>
			</div>
			<div class="row">
				<div class="col-md-12">
					<div id="max-feature-para" class="animate--one animated fadeInDown">
						<h2 style="text-align: center;">ДОСТАВКА ПО РОССИИ:</h2>
						<p style="text-align:justify"><strong>Бесплатная доставка</strong>&nbsp;
							при сумме заказа свыше&nbsp;
							<strong>15 000</strong>&nbsp;
							руб.
						</p>
						<p style="text-align:justify">Доставка в регионы России осуществляется компанией СДЭК. Заказ
							доставляется ДО ДВЕРИ. Стоимость доставки ниже или на уровне с почтой России,
							а сроки доставки от 2 до 7 дней. Стоимость до Вашего адреса будет Вам сообщаться менеджером при
							подтверждении заказа</p>
						<p style="text-align:justify">Также мы отправляем &quot;
							Почтой России&quot;
							(1 класс, наложенный платеж) или &quot;
							EMS Почта России&quot;
							. Стоимость доставки рассчитывается исходя из базового тарифа компании,
							а также от веса посылки и места назначения и сообщается клиенту посредством телефонного звонка. После
							подтверждения заказа клиентом,
							заказ отправляется по указанному адресу.</p>
					</div>
				</div>
			</div>
			<div class="row">
				<div class="col-md-12">
					<div id="max-feature-para" class="animate--one animated fadeInDown">
						<h2>СПОСОБЫ ОПЛАТЫ:</h2>
						<p style="text-align:justify">В нашем интернет-магазине Olaplex-Shop.ru Вы можете оплатить заказ любыми
							удобными для Вас способами.</p>
						<p style="text-align:justify"><span style="font-size:13px!important"><em>Всю дополнительную информацию
									можно получить по телефонам,
									указанным на главной странице сайта. Возможна отправка заказа любой транспортной службой,
									для этого в поле &quot;
									комментарии&quot;
									следует указать название транспортной кампании.</em></span></p>
						<p style="text-align:justify"><img class="hidden-mobile-phone hidden-tablet" alt=""
								src="https://www.xn--80aaapvimf6o.xn--p1ai/local/templates/makadamia-rf/img/payment.png"
								loading="lazy" /><img class="mobile-responsive-img hidden-desktop" alt=""
								src="https://www.xn--80aaapvimf6o.xn--p1ai/local/templates/makadamia-rf/img/mobile-payment-systems.png"
								loading="lazy" />
						</p>
					</div>
				</div>
			</div>
		</div>
	</section>
	<!-- ./About us section end -->

	<?php include 'templates/footer.php'; ?>
	<?php include 'templates/order_form.php'; ?>

	<!-- All JavaScript libraries -->
	<script defer src="/js/jquery-3.7.1.min.js"></script>
	<script defer src="/js/bootstrap.min.js"></script>
	<script defer src="/js/cart.js"></script>
	<script defer src="/js/cart-init.js"></script>
	<!-- Custom JavaScript -->
	<script defer src="/js/main.js?v=<?php echo date('Ymd', filemtime(__DIR__ . '/js/main.js')); ?>"></script>
</body>

</html>
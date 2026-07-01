<!DOCTYPE html>
<html lang="ru">
<?php
$pageTitle = 'Информация о продукции Olaplex';
$pageDescription = 'FAQ о том, как правильно пользоваться продукцией Олаплекс, что нужно знать перед тем как начать использовать продукцию.';
require __DIR__ . '/templates/head.php';
?>

<body>
	<?php include 'templates/header.php'; ?>
	<!-- About us section -->
	<section id="max-aboutus-section product" class="mt-block">
		<div class="max-section-title">
			<h1>Информация о том как правильно использовать Олаплекс</h1>
		</div>
		<div class="container" id="aboutus-section">
			<div class="row">
				<div class="col-md-12">
					<div id="max-feature-para" class="animate--one animated fadeInDown">
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
	<?php include 'templates/order_form.php'; ?>
	<!-- All JavaScript libraries -->
	<script defer src="/js/jquery-3.7.1.min.js"></script>
	<script defer src="/js/bootstrap.min.js"></script>
	<script defer src="/js/cart.js"></script>
	<script defer src="/js/cart-init.js"></script>
	<!-- Custom JavaScript -->
	<script defer src="/js/anchor-scroll.js"></script>
	<script defer src="/js/main.js?v=<?php echo date('Ymd', filemtime(__DIR__ . '/js/main.js')); ?>"></script>
</body>

</html>
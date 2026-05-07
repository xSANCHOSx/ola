<?php
session_start();
$order_id = isset($_SESSION['order_id']) ? $_SESSION['order_id'] : '—';


// можно очистить после получения
unset($_SESSION['order_id']);

?>
<!DOCTYPE html>
<html lang="ru">

<?php 
$pageTitle = 'Спасибо за заказ!';
$extraCss = '<style>
    .success-page { margin: 20vh auto; text-align: center; }
    .success-title { font-size: 28px; margin-bottom: 10px; }
    .success-order { font-size: 22px; margin-bottom: 20px; font-weight: bold; }
    .success-warning { background: #f5f5f5; padding: 15px; border-radius: 6px; margin: 20px auto; max-width: 600px; color: #444; font-size: 14px; }
    .messengers { margin: 25px 0; display: flex; justify-content: center; gap: 15px; flex-wrap: wrap; }
    .messengers .whatsapp { background: #25D366; }
    .messengers .telegram { background: #0088cc; }
    .messengers .max { background: #222; }
    .btn-msg { padding: 12px 18px; border-radius: 6px; color: #fff; text-decoration: none; font-weight: 600; display: inline-block; }
</style>';
require __DIR__ . '/templates/head.php'; 
?>

<body>

	<?php include 'templates/header.php'; ?>

	<section class="success-page container">

		<h1 class="success-title">Спасибо за заказ 🎉</h1>

		<div class="success-order">
			Ваш заказ №<?php echo htmlspecialchars($order_id); ?>
		</div>

		<p class="success-desc">
			Наш менеджер свяжется с вами в ближайшее время.
		</p>

		<!-- ⚠️ Блок как на скрине -->

		<p class="success-desc">
			<b>Нажмите, чтобы подтвердить заказ. Мы сразу начнём его обработку!</b>
		</p>
		<!-- 📲 Кнопки мессенджеров -->
		<div class="messengers">

			<a href="https://wa.me/79096962720" target="_blank" class="btn-msg whatsapp">
				<i class="fa fa-whatsapp"></i> WhatsApp
			</a>
			<a href="https://t.me/@kosmoprof" target="_blank" class="btn-msg telegram">
				<i class="fa fa-paper-plane"></i> Telegram
			</a>
			<a href="https://max.ru/id7733272706_bot" target="_blank" class="btn-msg max">
				Max
			</a>
		</div>
		<div class="success-warning">
			❗ Если вы выбрали <b>Max</b>, напишите нам первыми и добавьте нас в контакты,
			иначе Max не позволит вам ответить.
		</div>
		<a href="/" class="b1c">Вернуться на главную</a>

	</section>

	<?php include 'templates/footer.php'; ?>

</body>

</html>
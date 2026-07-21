<?php
session_start();

if (empty($_SESSION['order_id'])) {
    header('Location: /');
    exit;
}

$order_id       = $_SESSION['order_id'];
$coupon_code    = isset($_SESSION['coupon_code'])    ? $_SESSION['coupon_code']    : '';
$discount_amount= isset($_SESSION['discount_amount'])? (float)$_SESSION['discount_amount'] : 0.0;
$base_total     = isset($_SESSION['base_total'])     ? (float)$_SESSION['base_total']      : 0.0;

// очищаем после получения
unset($_SESSION['order_id'], $_SESSION['coupon_code'], $_SESSION['discount_amount'], $_SESSION['base_total']);

?>
<!DOCTYPE html>
<html lang="ru">

<?php 
$pageTitle = 'Спасибо за заказ!';
$extraCss = '<style>
    .success-page { margin: 20vh auto; text-align: center; }
    .success-title { font-size: 28px; margin-bottom: 10px; }
    .success-order { font-size: 22px; margin-bottom: 20px; font-weight: bold; }
    .success-coupon { display: inline-block; background: #fff0f4; border: 1px solid #f5c2cf; border-radius: 6px; padding: 10px 20px; margin-bottom: 18px; color: #ba385c; font-size: 15px; }
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

		<?php if ($coupon_code !== '' && $discount_amount > 0.0): ?>
		<div class="success-coupon">
			🎟 Купон <strong><?php echo htmlspecialchars($coupon_code); ?></strong> применён
			— скидка <strong><?php echo number_format($discount_amount, 0, '.', ' '); ?> руб.</strong>
		</div>
		<?php endif; ?>

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
				<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16"
					 viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"
					 style="vertical-align:middle;margin-right:5px">
					<path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347z"/>
					<path d="M12 0C5.373 0 0 5.373 0 12c0 2.124.554 4.118 1.523 5.847L0 24l6.335-1.502A11.945 11.945 0 0012 24c6.627 0 12-5.373 12-12S18.627 0 12 0zm0 21.818a9.808 9.808 0 01-5.001-1.367l-.36-.214-3.722.882.916-3.619-.235-.372A9.808 9.808 0 012.182 12C2.182 6.58 6.58 2.182 12 2.182S21.818 6.58 21.818 12 17.42 21.818 12 21.818z"/>
				</svg>
				WhatsApp
			</a>
			<a href="https://t.me/kosmoprof" target="_blank" class="btn-msg telegram">
				<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16"
					 viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"
					 style="vertical-align:middle;margin-right:5px">
					<path d="M2.01 21L23 12 2.01 3 2 10l15 2-15 2z"/>
				</svg>
				Telegram
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

	<?php
	// Итоговая сумма заказа с учётом скидки купона (если купона не было — просто base_total)
	$order_revenue = $base_total > 0 ? ($base_total - $discount_amount) : 0;
	?>
	<script>
		(function() {
			// Состав корзины кладёт js/cart.js (ModernCart.sendOrder) прямо перед
			// редиректом на эту страницу — здесь мы дочитываем его и отправляем
			// событие purchase вместе с реальным номером заказа с сервера.
			var raw;
			try {
				raw = sessionStorage.getItem('ola_pending_purchase');
			} catch (e) {
				raw = null;
			}
			if (!raw) return;

			try {
				sessionStorage.removeItem('ola_pending_purchase');
			} catch (e) {}

			var staged;
			try {
				staged = JSON.parse(raw);
			} catch (e) {
				return;
			}
			if (!staged || !staged.products || !staged.products.length) return;

			window.dataLayer = window.dataLayer || [];
			window.dataLayer.push({
				ecommerce: {
					currencyCode: 'RUB',
					purchase: {
						actionField: {
							id: <?= json_encode((string)$order_id, JSON_UNESCAPED_UNICODE) ?>,
							revenue: (<?= json_encode($order_revenue) ?> || staged.revenue || 0)
						},
						products: staged.products
					}
				}
			});
		})();
	</script>

</body>

</html>

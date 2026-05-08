<!DOCTYPE html>
<html lang="ru">

<?php
$pageTitle = '404 - Страница не найдена';
$extraCss = '<style>
    .page-404-wrapper { min-height: 60vh; display: flex; align-items: center; justify-content: center; padding: 40px 15px; background-color: #fafafa; }
    .page-404-container { display: flex; flex-wrap: wrap; align-items: center; max-width: 1140px; width: 100%; margin: 0 auto; background: #fff; border-radius: 10px; box-shadow: 0 10px 30px rgba(0, 0, 0, 0.05); overflow: hidden; }
    .page-404-image { flex: 0 0 50%; max-width: 50%; text-align: center; padding: 20px; }
    .page-404-image img { max-width: 100%; height: auto; display: block; margin: 0 auto; }
    .page-404-content { flex: 0 0 50%; max-width: 50%; padding: 40px 60px; }
    .page-404-content h1 { font-family: "Open Sans", sans-serif; font-size: 42px; font-weight: 700; color: #111; margin-top: 0; margin-bottom: 20px; line-height: 1.2; }
    .page-404-content p { font-family: "Open Sans", sans-serif; font-size: 18px; color: #555; margin-bottom: 35px; line-height: 1.6; }
    .btn-404-custom { display: inline-block; background-color: #ba385c; color: #ffffff !important; padding: 15px 35px; font-size: 16px; font-weight: 600; text-decoration: none; border-radius: 4px; transition: all 0.3s ease; border: none; }
    .btn-404-custom:hover { background-color: #9a2b49; transform: translateY(-2px); box-shadow: 0 5px 15px rgba(186, 56, 92, 0.3); }
    @media (max-width: 991px) { .page-404-content h1 { font-size: 32px; } .page-404-content { padding: 30px 40px; } }
    @media (max-width: 768px) { .page-404-container { flex-direction: column; text-align: center; } .page-404-image, .page-404-content { flex: 0 0 100%; max-width: 100%; } .page-404-content { padding: 20px 20px 40px 20px; } }
</style>';
require __DIR__ . '/templates/head.php';
?>

<body>
	<?php include 'templates/header.php'; ?>

	<section class="page-404-wrapper">
		<div class="page-404-container">

			<div class="page-404-image">
				<img src="/images/404.png" alt="404 - Страница не найдена">
			</div>

			<div class="page-404-content">
				<h1>404 &mdash;<br>Страница не найдена</h1>
				<p>Извините, запрашиваемая страница была удалена, переименована или временно недоступна.</p>
				<a href="/" class="btn-404-custom">Вернуться на главную</a>
			</div>

		</div>
	</section>

	<?php include 'templates/footer.php'; ?>

	<script src="/js/jquery-3.7.1.min.js"></script>
	<script src="/js/bootstrap.min.js"></script>
	<script src="/js/jquery.inputmask.bundle.js"></script>
	<script src="/js/cart.js" type="text/javascript"></script>
	<script src="/js/cart-init.js" type="text/javascript"></script>

	<script src="/js/main.js"></script>

	<script>
		$('#phoneNumber').inputmask("+7(999)999-99-99")
	</script>
	<link rel="stylesheet" href="https://cdn.envybox.io/widget/cbk.css">
	<script type="text/javascript" src="https://cdn.envybox.io/widget/cbk.js?wcb_code=e4d8a7b33dcf97067342ac246b5aecaa"
		charset="UTF-8" async></script>

</body>

</html>
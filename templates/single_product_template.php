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
$defaultDescription = generate_product_seo_description($currentProduct);
$pageTitle = !empty($currentProduct['seo_title']) ? htmlspecialchars((string)$currentProduct['seo_title'], ENT_QUOTES, 'UTF-8') : generate_product_seo_title($currentProduct);
$pageDescription = !empty($currentProduct['seo_description']) ? htmlspecialchars((string)$currentProduct['seo_description'], ENT_QUOTES, 'UTF-8') : $defaultDescription;

// Объединяем основное и дополнительные изображения, сохраняя порядок из БД.
$productImages = [];
foreach (array_merge([(string)($currentProduct['image'] ?? '')], (array)($currentProduct['gallery'] ?? [])) as $productImage) {
    $productImage = trim((string)$productImage);
    if ($productImage !== '' && !in_array($productImage, $productImages, true)) {
        $productImages[] = $productImage;
    }
}
$appConfig = function_exists('dev_app_config') ? dev_app_config() : [];
$siteDomain = trim((string)($appConfig['site_domain'] ?? ($_SERVER['HTTP_HOST'] ?? 'localhost')));
$siteDomain = preg_replace('#^https?://#', '', $siteDomain);
$siteBaseUrl = 'https://' . rtrim((string)$siteDomain, '/');
$absoluteProductImages = array_map(static fn(string $image): string => $siteBaseUrl . '/' . ltrim($image, '/'), $productImages);
?>

<!DOCTYPE html>
<html lang="ru">
<?php
$extraCss = ($extraCss ?? '') . '<link rel="stylesheet" href="/css/flexslider.css">';
$productSchema = [
    '@context' => 'https://schema.org',
    '@type' => 'Product',
    'name' => $currentProduct['name'],
    'image' => $absoluteProductImages,
    'description' => $currentProduct['short_desc'] ?: $currentProduct['desc'],
    'sku' => $currentProduct['cat_number'] ?: $currentProduct['id'],
    'brand' => ['@type' => 'Brand', 'name' => 'Olaplex'],
    'offers' => [
        '@type' => 'Offer',
        'url' => $siteBaseUrl . '/' . ltrim((string)$currentProduct['link'], '/'),
        'priceCurrency' => 'RUB',
        'price' => (string)$currentProduct['price'],
        'availability' => $currentProduct['in_stock'] ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock'
    ],
    'aggregateRating' => [
        '@type' => 'AggregateRating', 'ratingValue' => '5', 'bestRating' => '5', 'ratingCount' => '30'
    ]
];
$extraCss .= '<script type="application/ld+json">' . json_encode($productSchema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . '</script>';
require __DIR__ . '/head.php'; ?>

<body class="single">
    <?php include 'header.php'; ?>
    <section class="product-page" aria-labelledby="product-title">
        <div class="product-shell">
            <div class="product-breadcrumb">Olaplex <span>/</span> Каталог <span>/</span> <?= htmlspecialchars($currentProduct['cat_number'], ENT_QUOTES, 'UTF-8') ?></div>
            <div class="product-layout">
                <div class="product-gallery" data-product-gallery tabindex="0">
                    <?php if ($productImages): ?>
                        <div class="product-thumbnails" role="list" aria-label="Фотографии товара">
                            <?php foreach ($productImages as $imageIndex => $productImage): ?>
                                <?php $webpProductImage = webp_image_source($productImage); ?>
                                <button class="product-thumbnail<?= $imageIndex === 0 ? ' is-active' : '' ?>" type="button"
                                    data-gallery-image="<?= htmlspecialchars($productImage, ENT_QUOTES, 'UTF-8') ?>"
                                    data-gallery-webp="<?= htmlspecialchars((string)$webpProductImage, ENT_QUOTES, 'UTF-8') ?>"
                                    aria-label="Фото <?= $imageIndex + 1 ?>" aria-selected="<?= $imageIndex === 0 ? 'true' : 'false' ?>">
                                    <?php $thumbAttrs = ['width' => 88, 'height' => 88, 'loading' => $imageIndex === 0 ? 'eager' : 'lazy']; ?>
                                    <?= webp_img($productImage, $currentProduct['name'] . ' — фото ' . ($imageIndex + 1), '', $thumbAttrs) ?>
                                </button>
                            <?php endforeach; ?>
                        </div>
                        <div class="product-main-image">
                            <?php $mainWebpImage = webp_image_source($productImages[0]); ?>
                            <?php if ($mainWebpImage): ?><picture><source data-gallery-main-webp srcset="/<?= htmlspecialchars($mainWebpImage, ENT_QUOTES, 'UTF-8') ?>" type="image/webp"><img data-gallery-main class="product-main-image__img" src="/<?= htmlspecialchars($productImages[0], ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($currentProduct['name'], ENT_QUOTES, 'UTF-8') ?>" width="650" height="650" fetchpriority="high"></picture><?php else: ?><img data-gallery-main class="product-main-image__img" src="/<?= htmlspecialchars($productImages[0], ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($currentProduct['name'], ENT_QUOTES, 'UTF-8') ?>" width="650" height="650" fetchpriority="high"><?php endif; ?>
                            <button type="button" class="product-gallery-main-nav product-gallery-main-prev" data-gallery-main-nav="-1" aria-label="Предыдущее фото">‹</button>
                            <button type="button" class="product-gallery-main-nav product-gallery-main-next" data-gallery-main-nav="1" aria-label="Следующее фото">›</button>
                            <div class="product-image-note">Оригинальный товар Olaplex</div>
                        </div>
                    <?php else: ?>
                        <div class="product-main-image product-gallery-empty">Фото товара пока не добавлено</div>
                    <?php endif; ?>
                </div>

                <div class="product-info tovar-name" data-id="<?= htmlspecialchars((string)$currentProduct['id'], ENT_QUOTES, 'UTF-8') ?>">
                    <h1 id="product-title"><?= htmlspecialchars($currentProduct['cat_number'], ENT_QUOTES, 'UTF-8') ?> <?= htmlspecialchars($currentProduct['name'], ENT_QUOTES, 'UTF-8') ?></h1>
                    <div class="product-rating"><span class="rating-stars">★★★★★</span> <span>5.0 · 30 отзывов</span></div>
                    <div class="product-divider"></div>
                    <div class="product-short-description"><?= $currentProduct['short_desc'] ?></div>
                    <div class="product-buy-panel">
                        <?php if (product_is_buyable($currentProduct)): ?>
                            <?php include 'single_special.php'; ?>
                            <div class="product-price-row"><div><span class="product-price-old"><?= htmlspecialchars((string)$currentProduct['old_price'], ENT_QUOTES, 'UTF-8') ?> ₽</span><strong class="product-price"><?= htmlspecialchars((string)$currentProduct['price'], ENT_QUOTES, 'UTF-8') ?> ₽</strong></div><span class="product-stock"><i></i> В наличии</span></div>
                        <?php elseif (($currentProduct['status'] ?? '') === 'preorder'): ?>
                            <div class="product-price-row"><strong class="product-price">Предзаказ</strong><span class="product-stock">Доставка 7–14 дней</span></div>
                        <?php else: ?>
                            <div class="product-price-row"><strong class="product-price">Нет в наличии</strong></div>
                        <?php endif; ?>
                        <button class="b1c product-buy-button" <?php if (product_is_buyable($currentProduct)): ?>onclick="cart.addToCart(this, '<?= htmlspecialchars((string)$currentProduct['id'], ENT_QUOTES, 'UTF-8') ?>')"<?php else: ?>disabled<?php endif; ?>><?= product_button_label($currentProduct) ?><span>→</span></button>
                        <div class="product-benefits"><span>✓ Оригинал</span><span>✓ Безопасная оплата</span><span>✓ Поддержка стилиста</span></div>
                    </div>
                    <div class="product-description"><h2>О продукте</h2><div><?= !empty($currentProduct['full_desc']) ? $currentProduct['full_desc'] : $currentProduct['desc'] ?></div></div>
                </div>
            </div>
        </div>
    </section>
    <?php include __DIR__ . '/slider_in_card.php'; ?>
    <?php include __DIR__ . '/delivery.php'; ?>
    <?php include 'footer.php'; ?>
    <?php include 'order_form.php'; ?>

    <script defer src="/js/jquery-3.7.1.min.js"></script>
    <script defer src="/js/bootstrap.min.js"></script>
    <script>
        <?php $_numKey = (int)$currentProduct['id']; $_productMap = [$_numKey => $currentProduct]; ?>
        window.PRODUCTS = <?= json_encode($_productMap, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?>;
    </script>
    <script defer src="/js/cart.js?v=<?= $cssBust('js/cart.js') ?>"></script>
    <script defer src="/js/cart-init.js"></script>
    <script defer src="/js/jquery.flexslider-min.js"></script>
    <script defer src="/js/main.js?v=<?= date('Ymd', filemtime(__DIR__ . '/../js/main.js')) ?>"></script>
    <script>
        window.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('[data-product-gallery]').forEach(function(gallery) {
                var thumbs = Array.prototype.slice.call(gallery.querySelectorAll('[data-gallery-image]'));
                var mainImage = gallery.querySelector('[data-gallery-main]');
                var mainWebp = gallery.querySelector('[data-gallery-main-webp]');
                if (!mainImage || !thumbs.length) return;
                var currentIndex = 0;
                var imageUrl = function(path) { return path.charAt(0) === '/' ? path : '/' + path; };
                var showImage = function(index) {
                    currentIndex = (index + thumbs.length) % thumbs.length;
                    var activeThumb = thumbs[currentIndex];
                    mainImage.src = imageUrl(activeThumb.getAttribute('data-gallery-image'));
                    if (mainWebp) { var webpPath = activeThumb.getAttribute('data-gallery-webp'); mainWebp.srcset = webpPath ? imageUrl(webpPath) : ''; }
                    thumbs.forEach(function(thumb, thumbIndex) { var active = thumbIndex === currentIndex; thumb.classList.toggle('is-active', active); thumb.setAttribute('aria-selected', active ? 'true' : 'false'); });
                    activeThumb.scrollIntoView({ block: 'nearest', inline: 'nearest', behavior: 'smooth' });
                };
                thumbs.forEach(function(thumb, index) { thumb.addEventListener('click', function() { showImage(index); }); });
                gallery.querySelectorAll('[data-gallery-main-nav]').forEach(function(button) { button.addEventListener('click', function() { showImage(currentIndex + parseInt(button.getAttribute('data-gallery-main-nav'), 10)); }); });
                gallery.addEventListener('keydown', function(event) { if (event.key === 'ArrowLeft' || event.key === 'ArrowUp') { event.preventDefault(); showImage(currentIndex - 1); } else if (event.key === 'ArrowRight' || event.key === 'ArrowDown') { event.preventDefault(); showImage(currentIndex + 1); } });
                if (thumbs.length < 2) gallery.querySelectorAll('[data-gallery-main-nav]').forEach(function(button) { button.hidden = true; });
            });
            function getGridSize() { return (window.innerWidth < 600) ? 2 : (window.innerWidth < 900) ? 3 : 4; }
            $('.flexslider').flexslider({ animation: 'slide', directionNav: false, itemWidth: 240, itemMargin: 5, animationLoop: true, minItems: getGridSize(), maxItems: getGridSize(), startAt: 0, slideshow: true, slideshowSpeed: 7000, animationSpeed: 600, initDelay: 0, start: function(slider) { slider.addClass('flex-ready'); } });
            $(window).resize(function() { var gridSize = getGridSize(), flex = $('.flexslider').data('flexslider'); if (flex) { flex.vars.minItems = gridSize; flex.vars.maxItems = gridSize; } });
            $('#order .close_popup').click(function() { $('#formToSend input:checkbox').removeAttr('checked'); $('#formToSend input[type=submit]').attr('disabled', 'disabled'); $('#formToSend input[type=hidden].valTrFal').val('valTrFal_disabled'); });
            $('#formToSend input:checkbox').change(function() { if ($(this).is(':checked')) { $('#formToSend input[type=submit]').removeAttr('disabled'); $('#formToSend input[type=hidden].valTrFal').val('valTrFal_true'); } else { $('#formToSend input[type=submit]').attr('disabled', 'disabled'); $('#formToSend input[type=hidden].valTrFal').val('valTrFal_disabled'); } });
            $('#send').click(function() { if ($('#formToSend input[type=text]').val() != '') { $('#formToSend input[type=hidden].valTrFal').remove(); $('#formToSend .font-geometria-light').remove(); $('#overflw .basket_num_buttons').remove(); } });
            $('.youtube').on('click', function() { var elm = $(this), conts = elm.contents(), ifr = null; for (var i = 0; i < conts.length; i++) if (conts[i].nodeType == 8) ifr = conts[i].textContent; elm.addClass('player').html(ifr).off('click'); });
        });
    </script>
</body>
</html>

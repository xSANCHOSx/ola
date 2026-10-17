<?php session_start();

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/../data/products.php';
require_once __DIR__ . '/../lib/reviews.php';
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
$productBreadcrumbLabel = trim((string)$currentProduct['cat_number'] . ' ' . (string)$currentProduct['name']);
$breadcrumbs = [['label' => 'Каталог', 'url' => '/#max-featured-section'], ['label' => $productBreadcrumbLabel]];
$breadcrumbSchema = [['label' => $productBreadcrumbLabel]];

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
$reviewPdo = dev_db_connection();
$productDbId = (int)($currentProduct['db_id'] ?? 0);
$reviewStats = product_review_stats($reviewPdo, $productDbId);
$approvedReviews = product_approved_reviews($reviewPdo, $productDbId);
$reviewForm = [
    'author_name' => '',
    'author_email' => '',
    'rating' => '5',
    'review_text' => ''
];
$reviewErrors = [];
// Флаг «отзыв отправлен» живёт в сессии один показ — без параметров в URL.
$reviewSent = !empty($_SESSION['review_sent']);
unset($_SESSION['review_sent']);
$reviewAlreadyLeft = product_review_already_left($reviewPdo, $productDbId);
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['review_action'] ?? '') === 'submit') {
    $reviewForm = array_merge($reviewForm, [
        'author_name' => trim((string)($_POST['author_name'] ?? '')),
        'author_email' => trim((string)($_POST['author_email'] ?? '')),
        'rating' => (string)($_POST['rating'] ?? '5'),
        'review_text' => trim((string)($_POST['review_text'] ?? '')),
    ]);
    if ($reviewAlreadyLeft) {
        $reviewErrors = ['general' => 'Вы уже оставили отзыв на этот товар.'];
    } elseif (!validate_csrf_token()) {
        $reviewErrors = ['general' => 'Сессия формы устарела. Обновите страницу и попробуйте снова.'];
    } elseif (!empty($_POST['website'] ?? '')) {
        $reviewErrors = ['general' => 'Не удалось отправить отзыв.'];
    } elseif ((int)($_POST['review_started_at'] ?? 0) <= 0 || time() - (int)$_POST['review_started_at'] < 3) {
        $reviewErrors = ['general' => 'Форма заполнена слишком быстро. Попробуйте ещё раз.'];
    } elseif (!check_rate_limit('product_review:' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'), 50, 3600) /* TEMP: было 3 */) {
        $reviewErrors = ['general' => 'Слишком много попыток. Попробуйте позже.'];
    } else {
        $reviewResult = submit_product_review($reviewPdo, $productDbId, $reviewForm);
        if ($reviewResult['success']) {
            product_review_mark_left($productDbId);
            $_SESSION['review_sent'] = true;
            header('Location: ' . (string)($currentProduct['link'] ?? '/'));
            exit;
        }
        $reviewErrors = $reviewResult['errors'] ?? ['general' => 'Не удалось отправить отзыв.'];
    }
}
?>

<!DOCTYPE html>
<html lang="ru">
<?php
$extraCss = ($extraCss ?? '') . '<link rel="stylesheet" href="/css/flexslider.css"><link rel="stylesheet" href="/css/reviews.css?v=' . filemtime(__DIR__ . '/../css/reviews.css') . '">';
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
];
if ($reviewStats['count'] > 0) {
    $productSchema['aggregateRating'] = [
        '@type' => 'AggregateRating',
        'ratingValue' => (string)$reviewStats['average'],
        'bestRating' => '5',
        'worstRating' => '1',
        'ratingCount' => $reviewStats['count'],
    ];
    $productSchema['review'] = array_map(static function (array $review): array {
        return [
            '@type' => 'Review',
            'author' => ['@type' => 'Person', 'name' => $review['author_name']],
            'datePublished' => date('Y-m-d', strtotime((string)$review['created_at'])),
            'reviewBody' => review_plain_text((string)$review['review_text']),
            'reviewRating' => [
                '@type' => 'Rating',
                'ratingValue' => (string)$review['rating'],
                'bestRating' => '5',
                'worstRating' => '1',
            ],
        ];
    }, $approvedReviews);
}
$extraCss .= '<script type="application/ld+json">' . json_encode($productSchema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . '</script>';
require __DIR__ . '/head.php'; ?>

<body class="single">
    <?php include 'header.php'; ?>
    <?php include __DIR__ . '/breadcrumbs.php'; ?>
    <section class="product-page" aria-labelledby="product-title">
        <div class="product-shell">
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
                            <?php if ($mainWebpImage): ?><picture>
                                    <source data-gallery-main-webp srcset="/<?= htmlspecialchars($mainWebpImage, ENT_QUOTES, 'UTF-8') ?>"
                                        type="image/webp"><img data-gallery-main class="product-main-image__img"
                                        src="/<?= htmlspecialchars($productImages[0], ENT_QUOTES, 'UTF-8') ?>"
                                        alt="<?= htmlspecialchars($currentProduct['name'], ENT_QUOTES, 'UTF-8') ?>" width="650" height="650"
                                        fetchpriority="high">
                                </picture><?php else: ?><img data-gallery-main class="product-main-image__img"
                                    src="/<?= htmlspecialchars($productImages[0], ENT_QUOTES, 'UTF-8') ?>"
                                    alt="<?= htmlspecialchars($currentProduct['name'], ENT_QUOTES, 'UTF-8') ?>" width="650" height="650"
                                    fetchpriority="high"><?php endif; ?>
                            <button type="button" class="product-gallery-main-nav product-gallery-main-prev" data-gallery-main-nav="-1"
                                aria-label="Предыдущее фото">‹</button>
                            <button type="button" class="product-gallery-main-nav product-gallery-main-next" data-gallery-main-nav="1"
                                aria-label="Следующее фото">›</button>
                        </div>
                    <?php else: ?>
                        <div class="product-main-image product-gallery-empty">Фото товара пока не добавлено</div>
                    <?php endif; ?>
                </div>

                <div class="product-info tovar-name"
                    data-id="<?= htmlspecialchars((string)$currentProduct['id'], ENT_QUOTES, 'UTF-8') ?>">
                    <h1 id="product-title"><?= htmlspecialchars($currentProduct['cat_number'], ENT_QUOTES, 'UTF-8') ?>
                        <?= htmlspecialchars($currentProduct['name'], ENT_QUOTES, 'UTF-8') ?></h1>
                    <?php $ratingStars = $reviewStats['count'] > 0 ? (int)round($reviewStats['average']) : 0; ?>
                    <div class="product-rating"><span class="rating-stars"
                            aria-label="Рейтинг <?= $reviewStats['average'] ?>/5"><?= str_repeat('★', $ratingStars) . str_repeat('☆', 5 - $ratingStars) ?></span>
                        <a class="product-rating__link" href="#product-reviews"
                            data-open-reviews><?= $reviewStats['count'] > 0 ? htmlspecialchars((string)$reviewStats['average']) . ' · ' . (int)$reviewStats['count'] . ' ' . review_count_label($reviewStats['count']) : 'Пока нет отзывов' ?></a>
                    </div>
                    <div class="product-divider"></div>
                    <div class="product-short-description"><?= $currentProduct['short_desc'] ?></div>
                    <div class="product-buy-panel">
                        <?php if (product_is_buyable($currentProduct)): ?>
                            <?php include 'single_special.php'; ?>
                            <div class="product-price-row">
                                <div><span
                                        class="product-price-old"><?= htmlspecialchars((string)$currentProduct['old_price'], ENT_QUOTES, 'UTF-8') ?>
                                        ₽</span><strong
                                        class="product-price"><?= htmlspecialchars((string)$currentProduct['price'], ENT_QUOTES, 'UTF-8') ?>
                                        ₽</strong></div><span class="product-stock"><i></i> В наличии</span>
                            </div>
                        <?php elseif (($currentProduct['status'] ?? '') === 'preorder'): ?>
                            <div class="product-price-row"><strong class="product-price">Предзаказ</strong><span
                                    class="product-stock">Доставка 7–14 дней</span></div>
                        <?php else: ?>
                            <div class="product-price-row"><strong class="product-price">Нет в наличии</strong></div>
                        <?php endif; ?>
                        <button class="b1c product-buy-button"
                            <?php if (product_is_buyable($currentProduct)): ?>onclick="cart.addToCart(this, '<?= htmlspecialchars((string)$currentProduct['id'], ENT_QUOTES, 'UTF-8') ?>')"
                            <?php else: ?>disabled<?php endif; ?>><?= product_button_label($currentProduct) ?><span>→</span></button>
                        <div class="product-benefits"><span>✓ Оригинал</span><span>✓ Безопасная оплата</span></div>
                    </div>
                    <div class="product-accordion">
                        <details class="product-accordion__item" id="product-about" name="product-accordion"
                            <?= ($reviewSent || $reviewErrors) ? '' : ' open' ?>>
                            <summary class="product-accordion__summary">
                                <h2>О продукте</h2>
                            </summary>
                            <div class="product-accordion__body product-description">
                                <?= !empty($currentProduct['full_desc']) ? $currentProduct['full_desc'] : $currentProduct['desc'] ?>
                            </div>
                        </details>
                        <details class="product-accordion__item product-reviews" id="product-reviews" name="product-accordion"
                            <?= ($reviewSent || $reviewErrors) ? ' open' : '' ?>>
                            <summary class="product-accordion__summary">
                                <h2>Отзывы<?php if ($reviewStats['count'] > 0): ?> <span
                                        class="product-accordion__count"><?= (int)$reviewStats['count'] ?></span><?php endif; ?></h2>
                            </summary>
                            <div class="product-accordion__body">
                                <?php if ($reviewSent): ?><div class="review-alert review-alert--success">Спасибо! Отзыв отправлен и
                                        появится на сайте после проверки администратором.</div><?php endif; ?>
                                <?php if ($reviewErrors): ?><div class="review-alert review-alert--error">
                                        <?= htmlspecialchars((string)($reviewErrors['general'] ?? 'Проверьте заполнение формы.'), ENT_QUOTES, 'UTF-8') ?>
                                    </div><?php endif; ?>
                                <?php if ($approvedReviews): ?>
                                    <div class="product-reviews__list">
                                        <?php foreach ($approvedReviews as $review): ?>
                                            <article class="product-review">
                                                <div class="product-review__top">
                                                    <strong><?= htmlspecialchars((string)$review['author_name'], ENT_QUOTES, 'UTF-8') ?></strong><span
                                                        class="product-review__stars"><?= str_repeat('★', (int)$review['rating']) . str_repeat('☆', 5 - (int)$review['rating']) ?></span><time
                                                        style="display: none;opacity: 0;visibility: hidden;"
                                                        datetime="<?= htmlspecialchars(date('Y-m-d', strtotime((string)$review['created_at'])), ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars(date('d.m.Y', strtotime((string)$review['created_at'])), ENT_QUOTES, 'UTF-8') ?></time>
                                                </div>
                                                <div class="product-review__text"><?= review_render_html((string)$review['review_text']) ?></div>
                                            </article>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    <p class="product-reviews__empty">У этого товара пока нет опубликованных отзывов. Оставьте отзыв первым.
                                    </p>
                                <?php endif; ?>
                                <?php if ($reviewAlreadyLeft): ?>
                                    <?php if (!$reviewSent && !$reviewErrors): ?><p class="product-reviews__empty">Вы уже оставили отзыв на
                                            этот товар. Спасибо!</p><?php endif; ?>
                                <?php else: ?>
                                    <form class="product-review-form" method="post">
                                        <input type="hidden" name="review_action" value="submit">
                                        <input type="hidden" name="csrf_token"
                                            value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
                                        <input type="hidden" name="review_started_at" value="<?= time() ?>">
                                        <div class="review-form__honeypot" aria-hidden="true"><label>Website<input type="text" name="website"
                                                    tabindex="-1" autocomplete="off"></label></div>
                                        <h3>Оставить отзыв</h3>
                                        <div class="review-form__grid">
                                            <label>Ваше имя<input type="text" name="author_name" required maxlength="120"
                                                    value="<?= htmlspecialchars((string)$reviewForm['author_name'], ENT_QUOTES, 'UTF-8') ?>"></label>
                                            <label>Email <span>(необязательно)</span><input type="email" name="author_email" maxlength="255"
                                                    value="<?= htmlspecialchars((string)$reviewForm['author_email'], ENT_QUOTES, 'UTF-8') ?>"></label>
                                        </div>
                                        <label>Оценка
                                            <select name="rating" required>
                                                <?php for ($rating = 5; $rating >= 1; $rating--): ?><option value="<?= $rating ?>"
                                                        <?= (string)$rating === (string)$reviewForm['rating'] ? ' selected' : '' ?>><?= $rating ?> из 5
                                                    </option><?php endfor; ?>
                                            </select>
                                        </label>
                                        <label>Ваш отзыв<textarea name="review_text" required minlength="10" maxlength="3000"
                                                rows="5"><?= htmlspecialchars((string)$reviewForm['review_text'], ENT_QUOTES, 'UTF-8') ?></textarea></label>
                                        <?php if (!empty($reviewErrors['author_name'])): ?><small
                                                class="review-field-error"><?= htmlspecialchars($reviewErrors['author_name'], ENT_QUOTES, 'UTF-8') ?></small><?php endif; ?>
                                        <?php if (!empty($reviewErrors['author_email'])): ?><small
                                                class="review-field-error"><?= htmlspecialchars($reviewErrors['author_email'], ENT_QUOTES, 'UTF-8') ?></small><?php endif; ?>
                                        <?php if (!empty($reviewErrors['review_text'])): ?><small
                                                class="review-field-error"><?= htmlspecialchars($reviewErrors['review_text'], ENT_QUOTES, 'UTF-8') ?></small><?php endif; ?>
                                        <button class="b1c review-form__submit" type="submit">Отправить отзыв</button>
                                        <p class="review-form__note">Отзыв будет опубликован после проверки администратором.</p>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </details>
                    </div>
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
        <?php $_numKey = (int)$currentProduct['id'];
        $_productMap = [$_numKey => $currentProduct]; ?>
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
                var imageUrl = function(path) {
                    return path.charAt(0) === '/' ? path : '/' + path;
                };
                var showImage = function(index) {
                    currentIndex = (index + thumbs.length) % thumbs.length;
                    var activeThumb = thumbs[currentIndex];
                    mainImage.src = imageUrl(activeThumb.getAttribute('data-gallery-image'));
                    if (mainWebp) {
                        var webpPath = activeThumb.getAttribute('data-gallery-webp');
                        mainWebp.srcset = webpPath ? imageUrl(webpPath) : '';
                    }
                    thumbs.forEach(function(thumb, thumbIndex) {
                        var active = thumbIndex === currentIndex;
                        thumb.classList.toggle('is-active', active);
                        thumb.setAttribute('aria-selected', active ? 'true' : 'false');
                    });
                    activeThumb.scrollIntoView({
                        block: 'nearest',
                        inline: 'nearest',
                        behavior: 'smooth'
                    });
                };
                thumbs.forEach(function(thumb, index) {
                    thumb.addEventListener('click', function() {
                        showImage(index);
                    });
                });
                gallery.querySelectorAll('[data-gallery-main-nav]').forEach(function(button) {
                    button.addEventListener('click', function() {
                        showImage(currentIndex + parseInt(button.getAttribute('data-gallery-main-nav'), 10));
                    });
                });
                gallery.addEventListener('keydown', function(event) {
                    if (event.key === 'ArrowLeft' || event.key === 'ArrowUp') {
                        event.preventDefault();
                        showImage(currentIndex - 1);
                    } else if (event.key === 'ArrowRight' || event.key === 'ArrowDown') {
                        event.preventDefault();
                        showImage(currentIndex + 1);
                    }
                });
                if (thumbs.length < 2) gallery.querySelectorAll('[data-gallery-main-nav]').forEach(function(button) {
                    button.hidden = true;
                });
            });

            function getGridSize() {
                return (window.innerWidth < 600) ? 2 : (window.innerWidth < 900) ? 3 : 4;
            }

            function getRelatedGridSize() {
                return (window.innerWidth < 600) ? 1 : getGridSize();
            }
            $('.flexslider').not('#max-team .flexslider').flexslider({
                animation: 'slide',
                directionNav: false,
                itemWidth: 240,
                itemMargin: 5,
                animationLoop: true,
                minItems: getGridSize(),
                maxItems: getGridSize(),
                startAt: 0,
                slideshow: true,
                slideshowSpeed: 7000,
                animationSpeed: 600,
                initDelay: 0,
                start: function(slider) {
                    slider.addClass('flex-ready');
                }
            });
            var relatedSlider = $('#max-team .flexslider');
            if (relatedSlider.length) {
                relatedSlider.flexslider({
                    animation: 'slide',
                    directionNav: true,
                    controlNav: false,
                    keyboard: true,
                    touch: true,
                    itemWidth: 240,
                    itemMargin: 5,
                    animationLoop: true,
                    minItems: getRelatedGridSize(),
                    maxItems: getRelatedGridSize(),
                    startAt: 0,
                    slideshow: false,
                    animationSpeed: 450,
                    start: function(slider) {
                        slider.addClass('flex-ready');
                    }
                });
                var relatedElement = relatedSlider[0],
                    dragStartX = 0,
                    dragMoved = false,
                    suppressRelatedClick = false;
                relatedElement.addEventListener('pointerdown', function(event) {
                    if (event.pointerType !== 'mouse' || event.button !== 0 || event.target.closest('.flex-direction-nav'))
                        return;
                    dragStartX = event.clientX;
                    dragMoved = false;
                    relatedElement.classList.add('is-dragging');
                    relatedElement.setPointerCapture(event.pointerId);
                });
                relatedElement.addEventListener('pointermove', function(event) {
                    if (!relatedElement.classList.contains('is-dragging')) return;
                    if (Math.abs(event.clientX - dragStartX) > 8) dragMoved = true;
                });
                relatedElement.addEventListener('pointerup', function(event) {
                    if (!relatedElement.classList.contains('is-dragging')) return;
                    relatedElement.classList.remove('is-dragging');
                    if (dragMoved) {
                        event.preventDefault();
                        suppressRelatedClick = true;
                        relatedSlider.flexslider(event.clientX < dragStartX ? 'next' : 'prev');
                    }
                    if (relatedElement.hasPointerCapture(event.pointerId)) relatedElement.releasePointerCapture(event
                        .pointerId);
                });
                relatedElement.addEventListener('pointercancel', function() {
                    relatedElement.classList.remove('is-dragging');
                });
                relatedElement.addEventListener('click', function(event) {
                    if (suppressRelatedClick) {
                        event.preventDefault();
                        event.stopPropagation();
                        suppressRelatedClick = false;
                    }
                }, true);
            }
            $(window).resize(function() {
                var gridSize = getGridSize();
                $('.flexslider').each(function() {
                    var flex = $(this).data('flexslider');
                    if (flex) {
                        var size = $(this).closest('#max-team').length ? getRelatedGridSize() : gridSize;
                        flex.vars.minItems = size;
                        flex.vars.maxItems = size;
                    }
                });
            });
            $('#order .close_popup').click(function() {
                $('#formToSend input:checkbox').removeAttr('checked');
                $('#formToSend input[type=submit]').attr('disabled', 'disabled');
                $('#formToSend input[type=hidden].valTrFal').val('valTrFal_disabled');
            });
            $('#formToSend input:checkbox').change(function() {
                if ($(this).is(':checked')) {
                    $('#formToSend input[type=submit]').removeAttr('disabled');
                    $('#formToSend input[type=hidden].valTrFal').val('valTrFal_true');
                } else {
                    $('#formToSend input[type=submit]').attr('disabled', 'disabled');
                    $('#formToSend input[type=hidden].valTrFal').val('valTrFal_disabled');
                }
            });
            $('#send').click(function() {
                if ($('#formToSend input[type=text]').val() != '') {
                    $('#formToSend input[type=hidden].valTrFal').remove();
                    $('#formToSend .font-geometria-light').remove();
                    $('#overflw .basket_num_buttons').remove();
                }
            });
            $('.youtube').on('click', function() {
                var elm = $(this),
                    conts = elm.contents(),
                    ifr = null;
                for (var i = 0; i < conts.length; i++)
                    if (conts[i].nodeType == 8) ifr = conts[i].textContent;
                elm.addClass('player').html(ifr).off('click');
            });
        });
    </script>
    <script>
        window.addEventListener('DOMContentLoaded', function() {
            var reviews = document.getElementById('product-reviews');
            if (!reviews) return;
            // Аккордеон: при открытии одного пункта другой закрывается (состояние не сохраняется между загрузками).
            var items = document.querySelectorAll('.product-accordion__item');
            items.forEach(function(item) {
                item.addEventListener('toggle', function() {
                    if (!item.open) return;
                    items.forEach(function(other) {
                        if (other !== item) other.open = false;
                    });
                });
            });
            var openReviews = function(scroll) {
                reviews.open = true;
                if (scroll) reviews.scrollIntoView({
                    behavior: 'smooth',
                    block: 'start'
                });
            };
            document.querySelectorAll('[data-open-reviews]').forEach(function(link) {
                link.addEventListener('click', function(event) {
                    event.preventDefault();
                    openReviews(true);
                });
            });
            if (location.hash === '#product-reviews') openReviews(false);
        });
    </script>
</body>

</html>
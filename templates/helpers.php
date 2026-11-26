<?php

declare(strict_types=1);

/**
 * Возвращает нормализованный статус товара.
 *
 * Старые строки могут иметь NULL/пустой status, поэтому до миграции таких
 * товаров сохраняем прежнее поведение по флагу in_stock. Новые значения
 * status при этом не смешиваются с видимостью товара.
 */
function product_status(array $product): string
{
    $status = (string)($product['status'] ?? '');
    if (in_array($status, ['active', 'preorder', 'disabled'], true)) {
        return $status;
    }
    return !empty($product['in_stock']) ? 'active' : 'disabled';
}

function product_is_preorder(array $product): bool
{
    return product_status($product) === 'preorder';
}

/** Только активный товар в наличии можно добавить в обычную корзину. */
function product_is_buyable(array $product): bool
{
    return product_status($product) === 'active' && !empty($product['in_stock']);
}

function product_status_label(array $product): string
{
    return match (product_status($product)) {
        'active' => 'В наличии',
        'preorder' => 'Под заказ',
        default => 'Нет в наличии',
    };
}

/** Текст и действие кнопки не зависят от поля is_visible. */
function product_button_label(array $product): string
{
    return match (product_status($product)) {
        'active' => 'Купить',
        'preorder' => 'Под заказ',
        default => 'Выключено',
    };
}

/**
 * Генерирует HTML-блок таймера акции.
 * JS-логика обновления — в js/main.js (функция updateTimer)
 * Только разметка с data-end атрибутом.
 */
function getDiscountTimer(string $uniqueId): string
{
    $targetDate = strtotime('+15 days');
    return "
        <div class='expire_date' id='timer-{$uniqueId}' data-end='{$targetDate}'>
            До конца акции:
            <div class='flip-clock'>
                <div class='flip-unit'>
                    <span class='days'></span>
                    <div class='flip-label'>Дней</div>
                </div>
            </div>
        </div>
    ";
}


/**
 * Генерирует уникальное SEO описание для товара
 */
function generate_product_seo_description(array $product): string
{
    if (!empty($product['seo_description'])) {
        return $product['seo_description'];
    }

    $name = $product['name'] ?? '';
    $price = $product['price'] ?? 0;
    $shortDesc = $product['short_desc'] ?? '';

    // Генерируем уникальное описание на основе данных товара
    $description = "Купить {$name} по цене {$price} ₽ в интернет-магазине Olaplex. ";

    if (!empty($shortDesc)) {
        $description .= $shortDesc . '. ';
    }

    $description .= 'Доставка по всей России. Оригинальный товар с гарантией.';

    return $description;
}

/**
 * Генерирует уникальный SEO заголовок для товара
 */
function generate_product_seo_title(array $product): string
{
    if (!empty($product['seo_title'])) {
        return $product['seo_title'];
    }

    $name = $product['name'] ?? '';
    $catNumber = $product['cat_number'] ?? '';

    // Генерируем уникальный заголовок
    if (!empty($catNumber)) {
        return "{$catNumber} {$name} - Купить в интернет-магазине Olaplex";
    }

    return "{$name} - Olaplex для волос купить";
}

/**
 * Генерирует <picture> с WebP если файл существует, иначе — обычный <img>
 *
 * @param string $src    Путь к изображению (относительно корня сайта)
 * @param string $alt    Alt текст
 * @param string $class  CSS классы
 * @param array  $attrs  Дополнительные атрибуты ['loading' => 'lazy', 'width' => 400, ...]
 */
function webp_img(string $src, string $alt = '', string $class = '', array $attrs = []): string
{
    $webpSrc  = preg_replace('/\.(jpe?g|png)$/i', '.webp', $src);
    $webpPath = rtrim($_SERVER['DOCUMENT_ROOT'], '/') . '/' . ltrim($webpSrc, '/');

    // Собираем строку атрибутов
    $attrStr = '';
    if ($class) $attrs['class'] = $class;
    foreach ($attrs as $k => $v) {
        $attrStr .= ' ' . htmlspecialchars($k) . '="' . htmlspecialchars((string)$v) . '"';
    }

    $altStr = ' alt="' . htmlspecialchars($alt) . '"';

    if (file_exists($webpPath)) {
        return '<picture>'
            . '<source srcset="' . htmlspecialchars($webpSrc) . '" type="image/webp">'
            . '<img src="' . htmlspecialchars($src) . '"' . $altStr . $attrStr . '>'
            . '</picture>';
    }

    return '<img src="' . htmlspecialchars($src) . '"' . $altStr . $attrStr . '>';
}

/**
 * Возвращает путь к WebP-версии изображения, если она существует.
 */
function webp_image_source(string $src): ?string
{
    $webpSrc = preg_replace('/\.(jpe?g|png)$/i', '.webp', $src);
    if (!is_string($webpSrc) || $webpSrc === $src) {
        return null;
    }
    $webpPath = rtrim($_SERVER['DOCUMENT_ROOT'] ?? '', '/') . '/' . ltrim($webpSrc, '/');
    return is_file($webpPath) ? $webpSrc : null;
}

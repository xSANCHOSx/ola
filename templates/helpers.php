<?php

declare(strict_types=1);

/**
 * Проверяет, можно ли купить продукт
 * Можно купить, если товар в наличии или это предзаказ
 */
function product_is_buyable(array $product): bool
{
    return !empty($product['in_stock']) || (!empty($product['status']) && $product['status'] === 'preorder');
}

/**
 * Возвращает текст для кнопки покупки
 */
function product_button_label(array $product): string
{
    if (!empty($product['status']) && $product['status'] === 'preorder') {
        return 'Предзаказ';
    }
    return 'Купить';
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
 * Генерує <picture> з WebP якщо файл існує, інакше — звичайний <img>
 *
 * @param string $src    Шлях до зображення (відносно кореня сайту)
 * @param string $alt    Alt текст
 * @param string $class  CSS класи
 * @param array  $attrs  Додаткові атрибути ['loading' => 'lazy', 'width' => 400, ...]
 */
function webp_img(string $src, string $alt = '', string $class = '', array $attrs = []): string
{
    $webpSrc  = preg_replace('/\.(jpe?g|png)$/i', '.webp', $src);
    $webpPath = rtrim($_SERVER['DOCUMENT_ROOT'], '/') . '/' . ltrim($webpSrc, '/');

    // Збираємо рядок атрибутів
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

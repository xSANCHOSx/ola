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

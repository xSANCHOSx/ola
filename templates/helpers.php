<?php
declare(strict_types=1);

/**
 * Генерує HTML-блок таймера акції.
 * JS-логіка оновлення — в js/main.js (функція updateTimer)
 * Тут лише розмітка з data-end атрибутом.
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

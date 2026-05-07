<?php
function getDiscountTimer($uniqueId)
{
    $targetDate = strtotime('2025-05-01 00:00:00');
    $currentTime = time();
    $timeLeft = $targetDate - $currentTime;

    if ($timeLeft <= 0) {
        $targetDate = strtotime('+15 days', time());
        $timeLeft = $targetDate - $currentTime;
    }

    $days = floor($timeLeft / (60 * 60 * 24));
    $dayWord = ($days == 1) ? 'День' : (($days >= 2 && $days <= 4) ? 'Дня' : 'Дней');

    return "
        <div class='expire_date' id='timer-$uniqueId'>
            До конца акции:
            <div class='flip-clock'>
                <div class='flip-unit'><span class='days'>$days</span><div class='flip-label'>$dayWord</div></div>
            </div>
        </div>
        <script>
            let end$uniqueId = $targetDate;
            function updateTimer$uniqueId() {
                let now = new Date().getTime() / 1000;
                let timeLeft = end$uniqueId - now;

                if (timeLeft <= 0) {
                    end$uniqueId = now + (15 * 24 * 60 * 60);
                    timeLeft = end$uniqueId - now;
                }

                let days = Math.floor(timeLeft / (24 * 60 * 60));
                let dayWord = (days == 1) ? 'День' : ((days >= 2 && days <= 4) ? 'Дня' : 'Дней');

                document.querySelector('#timer-$uniqueId .days').innerText = days;
                document.querySelector('#timer-$uniqueId .flip-label').innerText = dayWord;
            }

            setInterval(updateTimer$uniqueId, 3600000); // Обновление раз в час
        </script>
    ";
}
?>

<div class="special">
    <div class="discont variant1">
        <span>Скидка <?php echo ($currentProduct['old_price'] - $currentProduct['price']); ?> ₽</span>
    </div>
    <?php echo getDiscountTimer($currentProduct['id']); ?>
</div>
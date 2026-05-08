<?php

<div class="special">
    <div class="discont variant1">
        <span>Скидка <?php echo ($currentProduct['old_price'] - $currentProduct['price']); ?> ₽</span>
    </div>
    <?php echo getDiscountTimer($currentProduct['id']); ?>
</div>
<?php require_once __DIR__ . "/helpers.php"; ?>
<div class="special">
    <div class="discont variant1">
        <span>Скидка <?php echo ($product['old_price'] - $product['price']); ?> ₽</span>
    </div>
    <?php echo getDiscountTimer($product['id']); ?>
</div>
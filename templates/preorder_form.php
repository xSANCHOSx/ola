<!-- Форма заявки на товар под заказ в общем модальном стиле оформления заказа. -->
<div id="preorder-request" class="popup preorder-request-popup" role="dialog" aria-modal="true" aria-labelledby="preorder-request-title" style="display:none">
    <a href="javascript:void(0)" class="close_popup" data-preorder-close aria-label="Закрыть">
        <img src="/images/close.png" alt="">
    </a>
    <h4 id="preorder-request-title">Оставить заявку на товар под заказ</h4>
    <div class="preorder-request-product">
        <label for="preorder-product-name">Товар</label>
        <input id="preorder-product-name" type="text" data-preorder-product-name readonly aria-readonly="true" tabindex="-1">
    </div>
    <form id="preorder-request-form" action="/preorder_request.php" method="post">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
        <input type="hidden" name="product_id" data-preorder-product-id value="">
        <label for="preorder-name">Ваше имя</label>
        <input id="preorder-name" name="name" type="text" maxlength="120" autocomplete="name" placeholder="Ваше имя" required>
        <label for="preorder-phone">Телефон</label>
        <input id="preorder-phone" name="phone" type="tel" maxlength="64" autocomplete="tel" placeholder="Контактный телефон" required>
        <label for="preorder-email">Email <span>(необязательно)</span></label>
        <input id="preorder-email" name="email" type="email" maxlength="255" autocomplete="email" placeholder="Электронная почта">
        <p class="preorder-request-error" data-preorder-error role="alert" hidden></p>
        <p class="preorder-request-success" data-preorder-success role="status" hidden>Заявка отправлена. Мы свяжемся с вами.</p>
        <button class="bbutton checkout" type="submit" data-preorder-submit>Оставить заявку</button>
    </form>
</div>

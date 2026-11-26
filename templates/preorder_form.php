<!-- Отдельная форма заявки на товар под заказ. Обычный checkout сюда не подключается. -->
<div id="preorder-request" class="popup" role="dialog" aria-modal="true" aria-labelledby="preorder-request-title" style="display:none">
    <button type="button" class="close_popup" data-preorder-close aria-label="Закрыть">×</button>
    <h4 id="preorder-request-title" style="text-align:center">Товар под заказ</h4>
    <label class="preorder-request-product-label">Товар
        <input type="text" data-preorder-product-name readonly aria-readonly="true" tabindex="-1">
    </label>
    <form id="preorder-request-form" action="/preorder_request.php" method="post">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
        <input type="hidden" name="product_id" data-preorder-product-id value="">
        <div class="preorder-request-honeypot" aria-hidden="true"><label>Website<input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>
        <label>Ваше имя
            <input name="name" type="text" maxlength="120" autocomplete="name" required>
        </label>
        <label>Телефон
            <input name="phone" type="tel" maxlength="64" autocomplete="tel" required>
        </label>
        <label>Email <span>(необязательно)</span>
            <input name="email" type="email" maxlength="255" autocomplete="email">
        </label>
        <p class="preorder-request-error" data-preorder-error role="alert" hidden></p>
        <p class="preorder-request-success" data-preorder-success role="status" hidden>Заявка отправлена. Мы свяжемся с вами.</p>
        <button class="bbutton checkout" type="submit" data-preorder-submit>Оставить заявку</button>
    </form>
</div>

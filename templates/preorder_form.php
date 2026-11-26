<div class="preorder-modal" id="preorderModal" hidden>
    <div class="preorder-modal__backdrop" data-preorder-close></div>
    <div class="preorder-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="preorderTitle">
        <button class="preorder-modal__close" type="button" data-preorder-close aria-label="Закрыть">×</button>
        <h2 id="preorderTitle">Товар под заказ</h2>
        <p class="preorder-modal__product" id="preorderProductName"></p>
        <form id="preorderForm" novalidate>
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="product_id" id="preorderProductId">
            <input type="text" name="website" tabindex="-1" autocomplete="off" aria-hidden="true" class="preorder-honeypot">
            <label>Имя <span>*</span><input type="text" name="name" required maxlength="255" autocomplete="name"></label>
            <label>Телефон <span>*</span><input type="tel" name="phone" required maxlength="64" autocomplete="tel"></label>
            <label>Email <small>необязательно</small><input type="email" name="email" maxlength="255" autocomplete="email"></label>
            <p class="preorder-form__error" id="preorderFormError" role="alert" hidden></p>
            <button type="submit" class="b1c">Отправить заявку <span>→</span></button>
        </form>
        <p class="preorder-form__success" id="preorderFormSuccess" role="status" hidden></p>
    </div>
</div>
<script>
(function () {
    var modal = document.getElementById('preorderModal');
    var form = document.getElementById('preorderForm');
    if (!modal || !form) return;
    var productId = document.getElementById('preorderProductId');
    var productName = document.getElementById('preorderProductName');
    var error = document.getElementById('preorderFormError');
    var success = document.getElementById('preorderFormSuccess');

    window.openPreorderModal = function (button) {
        productId.value = button.dataset.productId || '';
        productName.textContent = button.dataset.productName || 'Выбранный товар';
        error.hidden = true;
        success.hidden = true;
        form.hidden = false;
        form.reset();
        productId.value = button.dataset.productId || '';
        modal.hidden = false;
        document.body.classList.add('preorder-modal-open');
        form.querySelector('input[name="name"]').focus();
    };

    function close() {
        modal.hidden = true;
        document.body.classList.remove('preorder-modal-open');
    }
    modal.querySelectorAll('[data-preorder-close]').forEach(function (element) {
        element.addEventListener('click', close);
    });
    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && !modal.hidden) close();
    });
    form.addEventListener('submit', function (event) {
        event.preventDefault();
        error.hidden = true;
        var submit = form.querySelector('button[type="submit"]');
        submit.disabled = true;
        fetch('/preorder_request.php', { method: 'POST', body: new FormData(form), headers: { 'Accept': 'application/json' } })
            .then(function (response) { return response.json().then(function (data) { return { ok: response.ok, data: data }; }); })
            .then(function (result) {
                if (!result.ok || !result.data.success) throw new Error(result.data.message || 'Не удалось отправить заявку.');
                form.hidden = true;
                success.textContent = result.data.message;
                success.hidden = false;
            })
            .catch(function (requestError) {
                error.textContent = requestError.message;
                error.hidden = false;
            })
            .finally(function () { submit.disabled = false; });
    });
})();
</script>

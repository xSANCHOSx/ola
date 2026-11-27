<div class="preorder-modal" id="preorderModal" hidden>
    <div class="preorder-modal__backdrop" data-preorder-close></div>
    <div class="preorder-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="preorderTitle" aria-describedby="preorderProductName">
        <button class="preorder-modal__close" type="button" data-preorder-close aria-label="Закрыть">×</button>
        <h2 id="preorderTitle">Товар под заказ</h2>
        <form id="preorderForm" novalidate>
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="product_id" id="preorderProductId">
            <input type="text" name="website" tabindex="-1" autocomplete="off" aria-hidden="true" class="preorder-honeypot">
            <label>Товар <span>*</span><input type="text" id="preorderProductName" readonly aria-readonly="true" tabindex="-1"></label>
            <label>Имя <span>*</span><input type="text" name="name" required maxlength="255" autocomplete="name" aria-describedby="preorderNameError"><small class="preorder-field-error" id="preorderNameError" hidden></small></label>
            <label>Телефон <span>*</span><input type="tel" name="phone" required maxlength="64" autocomplete="tel" aria-describedby="preorderPhoneError"><small class="preorder-field-error" id="preorderPhoneError" hidden></small></label>
            <label>Email <small>необязательно</small><input type="email" name="email" maxlength="255" autocomplete="email" aria-describedby="preorderEmailError"><small class="preorder-field-error" id="preorderEmailError" hidden></small></label>
            <p class="preorder-form__error" id="preorderFormError" role="alert" hidden></p>
            <button type="submit" class="b1c">Отправить заявку <span>→</span></button>
        </form>
        <p class="preorder-form__success" id="preorderFormSuccess" role="status" tabindex="-1" hidden></p>
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
    var lastTrigger = null;

    function clearFieldErrors() {
        form.querySelectorAll('.preorder-field-error').forEach(function (fieldError) {
            fieldError.textContent = '';
            fieldError.hidden = true;
        });
        form.querySelectorAll('[aria-invalid="true"]').forEach(function (field) {
            field.removeAttribute('aria-invalid');
        });
    }

    function showFieldErrors(errors) {
        Object.keys(errors || {}).forEach(function (fieldName) {
            var field = form.querySelector('[name="' + fieldName + '"]');
            var fieldError = document.getElementById('preorder' + fieldName.charAt(0).toUpperCase() + fieldName.slice(1) + 'Error');
            if (field) field.setAttribute('aria-invalid', 'true');
            if (fieldError) {
                fieldError.textContent = errors[fieldName];
                fieldError.hidden = false;
            }
        });
    }

    function focusableElements() {
        return Array.prototype.slice.call(modal.querySelectorAll('button:not([disabled]), input:not([disabled]):not([type="hidden"]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])'));
    }

    window.openPreorderModal = function (button) {
        lastTrigger = button;
        var selectedProductId = button.dataset.productId || '';
        var selectedProductName = button.dataset.productName || 'Выбранный товар';
        error.hidden = true;
        success.hidden = true;
        clearFieldErrors();
        form.hidden = false;
        form.reset();
        productId.value = selectedProductId;
        productName.value = selectedProductName;
        modal.hidden = false;
        document.body.classList.add('preorder-modal-open');
        form.querySelector('input[name="name"]').focus();
    };

    function close() {
        modal.hidden = true;
        document.body.classList.remove('preorder-modal-open');
        if (lastTrigger && typeof lastTrigger.focus === 'function') lastTrigger.focus();
    }
    modal.querySelectorAll('[data-preorder-close]').forEach(function (element) {
        element.addEventListener('click', close);
    });
    document.addEventListener('keydown', function (event) {
        if (modal.hidden) return;
        if (event.key === 'Escape') {
            close();
            return;
        }
        if (event.key === 'Tab') {
            var elements = focusableElements();
            if (!elements.length) return;
            var first = elements[0];
            var last = elements[elements.length - 1];
            if (event.shiftKey && document.activeElement === first) {
                event.preventDefault();
                last.focus();
            } else if (!event.shiftKey && document.activeElement === last) {
                event.preventDefault();
                first.focus();
            }
        }
    });
    form.addEventListener('submit', function (event) {
        event.preventDefault();
        error.hidden = true;
        clearFieldErrors();
        var submit = form.querySelector('button[type="submit"]');
        submit.disabled = true;
        submit.setAttribute('aria-busy', 'true');
        fetch('/preorder_request.php', { method: 'POST', body: new FormData(form), headers: { 'Accept': 'application/json' } })
            .then(function (response) { return response.json().then(function (data) { return { ok: response.ok, data: data }; }); })
            .then(function (result) {
                if (!result.ok || !result.data.success) {
                    showFieldErrors(result.data.errors || {});
                    throw new Error(result.data.message || 'Не удалось отправить заявку.');
                }
                form.hidden = true;
                success.textContent = result.data.message;
                success.hidden = false;
                success.focus();
            })
            .catch(function (requestError) {
                error.textContent = requestError.message;
                error.hidden = false;
            })
            .finally(function () { submit.disabled = false; submit.removeAttribute('aria-busy'); });
    });
})();
</script>

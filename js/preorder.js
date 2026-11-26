/* Separate preorder form trigger; regular cart is intentionally not used. */
(function () {
    'use strict';

    function initPreorderForm() {
        var modal = document.getElementById('preorder-request');
        var form = document.getElementById('preorder-request-form');
        if (!modal || !form) return;

        var productName = modal.querySelector('[data-preorder-product-name]');
        var productId = modal.querySelector('[data-preorder-product-id]');
        var error = modal.querySelector('[data-preorder-error]');
        var success = modal.querySelector('[data-preorder-success]');
        var submit = modal.querySelector('[data-preorder-submit]');
        var openModal = function (button) {
            productId.value = button.getAttribute('data-product-id') || '';
            productName.value = button.getAttribute('data-product-name') || 'Выбранный товар';
            error.hidden = true;
            success.hidden = true;
            form.reset();
            productId.value = button.getAttribute('data-product-id') || '';
            productName.value = button.getAttribute('data-product-name') || 'Выбранный товар';
            modal.style.display = 'block';
            modal.classList.add('active');
        };
        var closeModal = function () {
            modal.classList.remove('active');
            modal.style.display = 'none';
        };

        document.addEventListener('click', function (event) {
            var button = event.target.closest('[data-preorder-product]');
            if (button) {
                event.preventDefault();
                openModal(button);
            }
            if (event.target.closest('[data-preorder-close]')) {
                event.preventDefault();
                closeModal();
            }
        });
        window.addEventListener('preorder:open', function (event) {
            var detail = event.detail || {};
            var requestedId = String(detail.productId || '');
            var button = Array.prototype.slice.call(document.querySelectorAll('[data-preorder-product]')).find(function (candidate) {
                return candidate.getAttribute('data-product-id') === requestedId;
            });
            if (button) openModal(button);
        });
        form.addEventListener('submit', function (event) {
            event.preventDefault();
            error.hidden = true;
            success.hidden = true;
            submit.disabled = true;
            fetch(form.action, { method: 'POST', body: new FormData(form), headers: { Accept: 'application/json' } })
                .then(function (response) {
                    return response.json().then(function (payload) {
                        if (!response.ok || !payload.success) throw new Error(payload.message || 'Не удалось отправить заявку.');
                        return payload;
                    });
                })
                .then(function () {
                    form.reset();
                    success.hidden = false;
                })
                .catch(function (requestError) {
                    error.textContent = requestError.message || 'Не удалось отправить заявку.';
                    error.hidden = false;
                })
                .finally(function () { submit.disabled = false; });
        });
    }

    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', initPreorderForm);
    else initPreorderForm();
}());

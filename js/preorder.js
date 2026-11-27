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
            var databaseId = button.getAttribute('data-preorder-db-id');
            var productReference = /^[1-9][0-9]*$/.test(databaseId || '')
                ? 'db:' + databaseId
                : (button.getAttribute('data-product-id') || '');
            productId.value = productReference;
            productName.value = button.getAttribute('data-product-name') || 'Выбранный товар';
            error.hidden = true;
            success.hidden = true;
            form.reset();
            productId.value = productReference;
            productName.value = button.getAttribute('data-product-name') || 'Выбранный товар';
            if (window.cart && window.cart.ui && typeof window.cart.ui.ensureBasketModal === 'function') {
                window.cart.ui.ensureBasketModal();
                window.cart.showWinow('preorder-request', 1);
            } else {
                modal.style.display = 'block';
                modal.classList.add('active');
            }
        };
        var closeModal = function () {
            if (window.cart && window.cart.ui && typeof window.cart.ui.ensureBasketModal === 'function') {
                window.cart.closeWindow('preorder-request', 1);
            } else {
                modal.classList.remove('active');
                modal.style.display = 'none';
            }
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
                        if (!response.ok || !payload.success) {
                            var fieldLabels = { product_id: 'Товар', name: 'Имя', phone: 'Телефон', email: 'Email' };
                            var details = Object.keys(payload.errors || {}).map(function (field) {
                                var label = fieldLabels[field] || 'Поле';
                                return label + ': ' + payload.errors[field];
                            });
                            var message = payload.message || 'Не удалось отправить заявку.';
                            if (details.length) message += ' ' + details.join(' ');
                            throw new Error(message);
                        }
                        return payload;
                    });
                })
                .then(function () {
                    form.reset();
                    var successMessage = success.textContent.trim() || 'Заявка отправлена. Мы свяжемся с вами.';
                    if (window.cart && typeof window.cart.showToast === 'function') {
                        closeModal();
                        window.cart.showToast(successMessage);
                    } else {
                        success.hidden = false;
                    }
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

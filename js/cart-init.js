/**
 * Уніфікована ініціалізація кошика з безпечними обгортками для запобігання помилок "undefined"
 */
var cart;
var config = {
    'clearAfterSend': true,
    'showAfterAdd': false
};

(function() {
    // Функція ініціалізації
    function initCart() {
        if (typeof WICard === 'function') {
            cart = new WICard('cart');
            cart.init("basketwidjet", config);
            console.log('Cart initialized successfully');
        } else {
            // Якщо WICard ще не завантажений, спробуємо ще раз через короткий час
            setTimeout(initCart, 100);
        }
    }

    // Ініціалізація при завантаженні DOM
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initCart);
    } else {
        initCart();
    }

    // Безпечне оновлення при зміні видимості
    document.addEventListener('visibilitychange', function() {
        if (!document.hidden && cart && typeof cart.init === 'function') {
            cart.init("basketwidjet", config);
        }
    }, false);

    // Створюємо проксі-об'єкт для cart, якщо він ще не ініціалізований, 
    // щоб уникнути помилок при виклику inline onclick подій
    if (typeof window.cart === 'undefined') {
        window.cart = new Proxy({}, {
            get: function(target, prop) {
                if (cart && cart[prop]) {
                    return cart[prop];
                }
                // Повертаємо порожню функцію, щоб уникнути "is not a function"
                return function() {
                    console.warn('Cart method "' + prop + '" called before initialization');
                };
            }
        });
    }
})();

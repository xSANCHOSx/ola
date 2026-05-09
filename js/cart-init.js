/**
 * Унифицированная инициализация корзины с безопасными обертками для предотвращения ошибок "undefined"
 */
var cart;
var config = {
    'clearAfterSend': true,
    'showAfterAdd': false
};

(function() {
    var actualCart = null;
    
    // Функция инициализации
    function initCart() {
        if (typeof WICard === 'function') {
            actualCart = new WICard('cart');
            actualCart.init("basketwidjet", config);
            cart = actualCart;
            //console.log('Cart initialized successfully');
        } else {
            // Если WICard еще не загружен, попробуем еще раз через короткий промежуток времени
            setTimeout(initCart, 100);
        }
    }

    // Инициализация при загрузке DOM
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initCart);
    } else {
        initCart();
    }

    // Безопасное обновление при изменении видимости

    // Создаем прокси-объект для cart, если он еще не инициализирован, 
    // чтобы избежать ошибок при вызове inline onclick событий
    if (typeof window.cart === 'undefined') {
        window.cart = new Proxy({}, {
            get: function(target, prop) {
                if (actualCart && typeof actualCart[prop] !== 'undefined') {
                    return actualCart[prop];
                }
                // Возвращаем пустую функцию, чтобы избежать "is not a function"
                return function() {
                    console.warn('Cart method "' + prop + '" called before initialization');
                };
            }
        });
    }
})();

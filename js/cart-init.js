var cart;
var config = {
    'clearAfterSend': true,
    'showAfterAdd': false
};

(function() {
    // Черга викликів, що надійшли до ініціалізації
    var queue = [];

    // Тимчасовий Proxy — перехоплює виклики і ставить у чергу
    window.cart = new Proxy({}, {
        get: function(target, prop) {
            return function() {
                var args = arguments;
                if (cart && cart !== window.cart && typeof cart[prop] === 'function') {
                    return cart[prop].apply(cart, args);
                }
                queue.push({ prop: prop, args: args });
            };
        }
    });

    function initCart() {
        if (typeof WICard === 'function') {
            var instance = new WICard('cart');
            instance.init('basketwidjet', config);
            cart = instance;
            window.cart = instance; // замінюємо Proxy реальним об'єктом

            // Відтворюємо відкладені виклики
            queue.forEach(function(item) {
                if (typeof instance[item.prop] === 'function') {
                    instance[item.prop].apply(instance, item.args);
                }
            });
            queue = [];
        } else {
            setTimeout(initCart, 100);
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initCart);
    } else {
        initCart();
    }
})();
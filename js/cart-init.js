var cart;
var config = {
    'clearAfterSend': true,
    'showAfterAdd': false
};

(function() {
    var queue = [];

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
            window.cart = instance; 

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
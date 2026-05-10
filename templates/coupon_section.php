<!-- templates/coupon_section.php — Раздел для применения купонов -->

<div id="coupon-section" style="background: #f9f9f9; padding: 20px; border-radius: 5px; margin: 20px 0;">
    <h3 style="margin-top: 0;">💰 У вас есть промокод?</h3>

    <!-- Сообщение об ошибке -->
    <div id="coupon-error" style="
        display: none;
        background: #f8d7da;
        color: #721c24;
        padding: 10px;
        border-radius: 3px;
        margin-bottom: 15px;
        border: 1px solid #f5c6cb;
    "></div>

    <!-- Сообщение об успехе -->
    <div id="coupon-success" style="
        display: none;
        background: #d4edda;
        color: #155724;
        padding: 10px;
        border-radius: 3px;
        margin-bottom: 15px;
        border: 1px solid #c3e6cb;
    "></div>

    <!-- Форма ввода купона -->
    <div style="display: flex; gap: 10px; margin-bottom: 20px;">
        <input
            id="coupon-code"
            type="text"
            placeholder="Введіть код промокода"
            maxlength="50"
            style="flex: 1; padding: 10px; border: 1px solid #ddd; border-radius: 3px; font-size: 14px;">
        <button
            id="apply-coupon"
            type="button"
            style="
                padding: 10px 20px;
                background: #007bff;
                color: white;
                border: none;
                border-radius: 3px;
                cursor: pointer;
                font-size: 14px;
                font-weight: bold;
            ">Применить</button>
    </div>

    <!-- Информация о скидке -->
    <div id="coupon-info" style="
        background: white;
        padding: 15px;
        border-left: 4px solid #28a745;
        border-radius: 3px;
        display: none;
    ">
        <div style="display: flex; justify-content: space-between; margin-bottom: 10px;">
            <span>Ваша скидка:</span>
            <strong id="discount-amount" style="color: #28a745; font-size: 18px;">0 р.</strong>
        </div>
        <div style="display: flex; justify-content: space-between; padding-top: 10px; border-top: 1px solid #eee;">
            <span><strong>К оплате:</strong></span>
            <strong id="final-sum" style="font-size: 18px;">0 р.</strong>
        </div>
    </div>
</div>

<!-- Скрипт инициализации -->
<script src="/js/coupon-manager.js"></script>
<script>
    // Инициализация модуля купонов
    const couponManager = new CouponManager({
        couponInput: '#coupon-code',
        sumDisplay: '#order-sum', // селектор поля с суммой заказа
        applyBtn: '#apply-coupon',
        discountDisplay: '#discount-amount',
        finalSumDisplay: '#final-sum',
        errorDisplay: '#coupon-error',
        successDisplay: '#coupon-success'
    });

    // Показати інформацію про знижку коли вона застосована
    window.addEventListener('couponApplied', (e) => {
        const infoBlock = document.getElementById('coupon-info');
        if (infoBlock) {
            infoBlock.style.display = 'block';
        }
    });

    // Також можна слухати зміну суми замовлення
    document.addEventListener('orderSumChanged', (e) => {
        // Якщо сума змінилась, переваліджити купон
        const code = couponManager.getCouponCode();
        if (code) {
            couponManager.validateCoupon(code, e.detail.newSum);
        }
    });
</script>

<!-- Стилі CSS (опціонально) -->
<style>
    #coupon-section input[type="text"] {
        transition: border-color 0.3s;
    }

    #coupon-section input[type="text"]:focus {
        outline: none;
        border-color: #007bff;
        box-shadow: 0 0 5px rgba(0, 123, 255, 0.3);
    }

    #apply-coupon {
        transition: background 0.3s;
    }

    #apply-coupon:hover {
        background: #0056b3;
    }

    #apply-coupon:active {
        transform: scale(0.98);
    }

    #coupon-info {
        animation: slideDown 0.3s ease;
    }

    @keyframes slideDown {
        from {
            opacity: 0;
            transform: translateY(-10px);
        }

        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
</style>
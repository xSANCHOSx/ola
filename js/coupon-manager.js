/**
 * CouponManager — JavaScript модуль для работы с купонами
 * 
 * Использование:
 * const coupon = new CouponManager({
 *     couponInput: '#coupon-code',
 *     sumDisplay: '#order-sum',
 *     applyBtn: '#apply-coupon',
 *     discountDisplay: '#discount-amount',
 *     finalSumDisplay: '#final-sum'
 * });
 * 
 * coupon.validateCoupon('OLA5600', 10000);
 */

class CouponManager {
    constructor(config = {}) {
        this.config = {
            apiUrl: '/api/validate_coupon.php',
            couponInput: '#coupon-code',
            sumDisplay: '#order-sum',
            applyBtn: '#apply-coupon',
            discountDisplay: '#discount-amount',
            finalSumDisplay: '#final-sum',
            errorDisplay: '#coupon-error',
            successDisplay: '#coupon-success',
            ...config
        };
        
        this.currentCoupon = null;
        this.currentDiscount = 0;
        this.isLoading = false;
        
        this.init();
    }
    
    /**
     * Инициализация модуля
     */
    init() {
        const applyBtn = document.querySelector(this.config.applyBtn);
        const couponInput = document.querySelector(this.config.couponInput);
        
        if (applyBtn) {
            applyBtn.addEventListener('click', () => this.applyButtonClick());
        }
        
        if (couponInput) {
            couponInput.addEventListener('keypress', (e) => {
                if (e.key === 'Enter') {
                    this.applyButtonClick();
                }
            });
        }
    }
    
    /**
     * Обработчик нажатия кнопки
     */
    async applyButtonClick() {
        const code = this.getCouponCode();
        const sum = this.getOrderSum();
        
        if (!code) {
            this.showError('Введите код купона');
            return;
        }
        
        if (!sum || sum <= 0) {
            this.showError('Неизвестная сумма заказа');
            return;
        }
        
        await this.validateCoupon(code, sum);
    }
    
    /**
     * Валидировать купон через API
     * 
     * @param {string} code Код купона
     * @param {number} sum Сумма заказа
     */
    async validateCoupon(code, sum) {
        if (this.isLoading) return;
        
        this.isLoading = true;
        this.hideMessages();
        
        try {
            const url = new URL(this.config.apiUrl, window.location.origin);
            url.searchParams.set('code', code.toUpperCase());
            url.searchParams.set('sum', sum.toString());
            
            const response = await fetch(url.toString());
            const data = await response.json();
            
            if (response.ok && data.valid) {
                this.applyCoupon(data);
                this.showSuccess(`Купон ${code} применен! Экономия: ${data.calculation.discount_percent}%`);
            } else {
                this.clearCoupon();
                this.showError(data.error || 'Купон невалиден');
            }
        } catch (error) {
            this.clearCoupon();
            this.showError('Ошибка при валидации купона');
            console.error('Coupon validation error:', error);
        } finally {
            this.isLoading = false;
        }
    }
    
    /**
     * Применить купон к заказу
     */
    applyCoupon(data) {
        this.currentCoupon = data.coupon;
        this.currentDiscount = data.calculation.discount_amount;
        
        // Обновить дисплеи
        const discountDisplay = document.querySelector(this.config.discountDisplay);
        const finalSumDisplay = document.querySelector(this.config.finalSumDisplay);
        
        if (discountDisplay) {
            discountDisplay.textContent = this.formatSum(this.currentDiscount);
            discountDisplay.parentElement?.classList.add('active');
        }
        
        if (finalSumDisplay) {
            finalSumDisplay.textContent = this.formatSum(data.calculation.final_sum);
        }
        
        // Добавить информацию в форму
        const form = document.querySelector('form[data-order-form]');
        if (form) {
            let couponInput = form.querySelector('input[name="coupon"]');
            if (!couponInput) {
                couponInput = document.createElement('input');
                couponInput.type = 'hidden';
                couponInput.name = 'coupon';
                form.appendChild(couponInput);
            }
            couponInput.value = this.currentCoupon.code;
        }
        
        // Отправить событие
        window.dispatchEvent(new CustomEvent('couponApplied', {
            detail: { coupon: this.currentCoupon, discount: this.currentDiscount }
        }));
    }
    
    /**
     * Удалить купон
     */
    clearCoupon() {
        this.currentCoupon = null;
        this.currentDiscount = 0;
        
        const discountDisplay = document.querySelector(this.config.discountDisplay);
        const finalSumDisplay = document.querySelector(this.config.finalSumDisplay);
        const couponInput = document.querySelector(this.config.couponInput);
        
        if (discountDisplay) {
            discountDisplay.textContent = '0 р.';
            discountDisplay.parentElement?.classList.remove('active');
        }
        
        if (finalSumDisplay) {
            finalSumDisplay.textContent = this.formatSum(this.getOrderSum());
        }
        
        // Очистить форму
        const form = document.querySelector('form[data-order-form]');
        if (form) {
            const couponField = form.querySelector('input[name="coupon"]');
            if (couponField) {
                couponField.value = '';
            }
        }
    }
    
    /**
     * Получить код купона из input
     */
    getCouponCode() {
        const input = document.querySelector(this.config.couponInput);
        return input ? input.value.trim().toUpperCase() : '';
    }
    
    /**
     * Получить сумму заказа
     */
    getOrderSum() {
        const display = document.querySelector(this.config.sumDisplay);
        if (!display) return 0;
        
        const text = display.textContent || display.value || '0';
        return parseFloat(text.replace(/[^\d.,]/g, '').replace(',', '.')) || 0;
    }
    
    /**
     * Форматирование суммы
     */
    formatSum(sum) {
        return new Intl.NumberFormat('ru-RU', {
            style: 'currency',
            currency: 'RUB'
        }).format(sum);
    }
    
    /**
     * Показать сообщение об ошибке
     */
    showError(message) {
        const errorDisplay = document.querySelector(this.config.errorDisplay);
        if (errorDisplay) {
            errorDisplay.textContent = message;
            errorDisplay.style.display = 'block';
        }
    }
    
    /**
     * Показать успешное сообщение
     */
    showSuccess(message) {
        const successDisplay = document.querySelector(this.config.successDisplay);
        if (successDisplay) {
            successDisplay.textContent = message;
            successDisplay.style.display = 'block';
        }
    }
    
    /**
     * Скрыть сообщения
     */
    hideMessages() {
        const errorDisplay = document.querySelector(this.config.errorDisplay);
        const successDisplay = document.querySelector(this.config.successDisplay);
        
        if (errorDisplay) errorDisplay.style.display = 'none';
        if (successDisplay) successDisplay.style.display = 'none';
    }
}

// Экспорт для использования в теге <script>
if (typeof module !== 'undefined' && module.exports) {
    module.exports = CouponManager;
}

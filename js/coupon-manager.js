/**
 * CouponManager — JavaScript модуль для роботи з купонами
 * 
 * Використання:
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
     * Ініціалізація модуля
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
     * Обробник натискання кнопки
     */
    async applyButtonClick() {
        const code = this.getCouponCode();
        const sum = this.getOrderSum();
        
        if (!code) {
            this.showError('Введіть код купона');
            return;
        }
        
        if (!sum || sum <= 0) {
            this.showError('Невідома сума замовлення');
            return;
        }
        
        await this.validateCoupon(code, sum);
    }
    
    /**
     * Валідувати купон через API
     * 
     * @param {string} code Код купона
     * @param {number} sum Сума замовлення
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
                this.showSuccess(`Купон ${code} застосовано! Економія: ${data.calculation.discount_percent}%`);
            } else {
                this.clearCoupon();
                this.showError(data.error || 'Купон невалідний');
            }
        } catch (error) {
            this.clearCoupon();
            this.showError('Помилка при валідації купона');
            console.error('Coupon validation error:', error);
        } finally {
            this.isLoading = false;
        }
    }
    
    /**
     * Застосувати купон до замовлення
     */
    applyCoupon(data) {
        this.currentCoupon = data.coupon;
        this.currentDiscount = data.calculation.discount_amount;
        
        // Оновити дисплеї
        const discountDisplay = document.querySelector(this.config.discountDisplay);
        const finalSumDisplay = document.querySelector(this.config.finalSumDisplay);
        
        if (discountDisplay) {
            discountDisplay.textContent = this.formatSum(this.currentDiscount);
            discountDisplay.parentElement?.classList.add('active');
        }
        
        if (finalSumDisplay) {
            finalSumDisplay.textContent = this.formatSum(data.calculation.final_sum);
        }
        
        // Додати інформацію до form
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
        
        // Відправити подію
        window.dispatchEvent(new CustomEvent('couponApplied', {
            detail: { coupon: this.currentCoupon, discount: this.currentDiscount }
        }));
    }
    
    /**
     * Видалити купон
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
        
        // Очистити form
        const form = document.querySelector('form[data-order-form]');
        if (form) {
            const couponField = form.querySelector('input[name="coupon"]');
            if (couponField) {
                couponField.value = '';
            }
        }
    }
    
    /**
     * Отримати код купона з input
     */
    getCouponCode() {
        const input = document.querySelector(this.config.couponInput);
        return input ? input.value.trim().toUpperCase() : '';
    }
    
    /**
     * Отримати суму замовлення
     */
    getOrderSum() {
        const display = document.querySelector(this.config.sumDisplay);
        if (!display) return 0;
        
        const text = display.textContent || display.value || '0';
        return parseFloat(text.replace(/[^\d.,]/g, '').replace(',', '.')) || 0;
    }
    
    /**
     * Форматування суми
     */
    formatSum(sum) {
        return new Intl.NumberFormat('uk-UA', {
            style: 'currency',
            currency: 'RUB'
        }).format(sum);
    }
    
    /**
     * Показати повідомлення про помилку
     */
    showError(message) {
        const errorDisplay = document.querySelector(this.config.errorDisplay);
        if (errorDisplay) {
            errorDisplay.textContent = message;
            errorDisplay.style.display = 'block';
        }
    }
    
    /**
     * Показати успішне повідомлення
     */
    showSuccess(message) {
        const successDisplay = document.querySelector(this.config.successDisplay);
        if (successDisplay) {
            successDisplay.textContent = message;
            successDisplay.style.display = 'block';
        }
    }
    
    /**
     * Приховати повідомлення
     */
    hideMessages() {
        const errorDisplay = document.querySelector(this.config.errorDisplay);
        const successDisplay = document.querySelector(this.config.successDisplay);
        
        if (errorDisplay) errorDisplay.style.display = 'none';
        if (successDisplay) successDisplay.style.display = 'none';
    }
}

// Експорт для використання в тегу <script>
if (typeof module !== 'undefined' && module.exports) {
    module.exports = CouponManager;
}

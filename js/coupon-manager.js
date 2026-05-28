
class CouponManager {
    constructor(config = {}) {
        this.config = {
            apiUrl: '/api/validate_coupon.php',
            couponInput: '#coupon-code',
            applyBtn: '#apply-coupon',
            discountDisplay: '#discount-amount',
            finalSumDisplay: '#final-sum',
            errorDisplay: '#coupon-error',
            successDisplay: '#coupon-success',
            ...config
        }
        this.isLoading = false
        this.init()
    }

    init() {
        const applyBtn    = document.querySelector(this.config.applyBtn)
        const couponInput = document.querySelector(this.config.couponInput)

        if (applyBtn) {
            applyBtn.addEventListener('click', () => this.applyButtonClick())
        }
        if (couponInput) {
            couponInput.addEventListener('keypress', e => {
                if (e.key === 'Enter') this.applyButtonClick()
            })
        }
    }

    async applyButtonClick() {
        const code = this.getCouponCode()
        if (!code) { this.showError('Введите код купона'); return }

        const cart = window.cart
        if (!cart || !cart.store) { this.showError('Корзина не инициализирована'); return }

        const sum = cart.store.baseTotalPrice()
        if (sum <= 0) { this.showError('Корзина пустая'); return }

        await this.validateCoupon(code, sum)
    }

    async validateCoupon(code, sum) {
        if (this.isLoading) return
        this.isLoading = true
        this.hideMessages()

        try {
            const url = new URL(this.config.apiUrl, window.location.origin)
            url.searchParams.set('code', code.toUpperCase())
            url.searchParams.set('sum', sum.toFixed(2))

            const response = await fetch(url.toString())
            const data     = await response.json()

            if (response.ok && data.valid) {
                const { discount_type: dtype, discount_value: dvalue } = data.coupon
                // Сохраняем в cart.store — отсюда и в POST, и в localStorage
                window.cart.store.setCoupon(code.toUpperCase(), dtype, dvalue)
                window.cart.ui && window.cart.ui.renderTotals && window.cart.ui.renderTotals()
                this._updateDiscountDisplay(discount, data.calculation.final_sum)
                this.showSuccess(`Купон ${code} применён! Скидка: ${data.calculation.discount_percent}%`)
            } else {
                window.cart && window.cart.store && window.cart.store.clearCoupon()
                this.showError(data.error || 'Купон недействительный')
            }
        } catch (e) {
            window.cart && window.cart.store && window.cart.store.clearCoupon()
            this.showError('Ошибка проверки купона')
            console.error('CouponManager error:', e)
        } finally {
            this.isLoading = false
        }
    }

    clearCoupon() {
        window.cart && window.cart.store && window.cart.store.clearCoupon()
        window.cart && window.cart.ui && window.cart.ui.renderTotals && window.cart.ui.renderTotals()
        this._updateDiscountDisplay(0, null)
    }

    getCouponCode() {
        const input = document.querySelector(this.config.couponInput)
        return input ? input.value.trim().toUpperCase() : ''
    }

    _updateDiscountDisplay(discount, finalSum) {
        const discountEl = document.querySelector(this.config.discountDisplay)
        const finalEl    = document.querySelector(this.config.finalSumDisplay)
        if (discountEl) {
            discountEl.textContent = this._fmt(discount)
            discountEl.parentElement && discountEl.parentElement.classList.toggle('active', discount > 0)
        }
        if (finalEl && finalSum != null) {
            finalEl.textContent = this._fmt(finalSum)
        }
    }

    _fmt(sum) {
        return new Intl.NumberFormat('ru-RU', { style: 'currency', currency: 'RUB' }).format(sum)
    }

    showError(msg) {
        const el = document.querySelector(this.config.errorDisplay)
        if (el) { el.textContent = msg; el.style.display = 'block' }
    }

    showSuccess(msg) {
        const el = document.querySelector(this.config.successDisplay)
        if (el) { el.textContent = msg; el.style.display = 'block' }
    }

    hideMessages() {
        [this.config.errorDisplay, this.config.successDisplay].forEach(sel => {
            const el = document.querySelector(sel)
            if (el) el.style.display = 'none'
        })
    }
}

if (typeof module !== 'undefined' && module.exports) {
    module.exports = CouponManager
}

/* Modern cart architecture: store + ui + checkout + legacy facade */
;(function (window, $) {
	'use strict'
	if (!$) return

	const COUPON_CODE = 'OLA5600'
	const COUPON_DISCOUNT = 5600

	class CartStore {
		constructor(storageKey) {
			this.storageKey = storageKey
			this.idsKey = storageKey + '_ids'
			this.items = this.readJSON(this.storageKey, {})
			this.ids = this.readJSON(this.idsKey, [])
			this.coupon =
				localStorage.getItem('coupon_take_' + COUPON_CODE) === COUPON_CODE
					? COUPON_CODE
					: ''
		}
		readJSON(key, fallback) {
			try {
				const raw = localStorage.getItem(key)
				return raw ? JSON.parse(raw) : fallback
			} catch (e) {
				return fallback
			}
		}
		persist() {
			localStorage.setItem(this.storageKey, JSON.stringify(this.items))
			localStorage.setItem(this.idsKey, JSON.stringify(this.ids))
		}
		add(params) {
			const rawId = params.id
			const id = $.isNumeric(rawId) ? 'ID' + rawId.toString() : rawId
			const key = params.subid ? id + '_' + params.subid : id
			const qty = Math.max(1, params.qty || 1)
			if (!this.items[key]) {
				this.items[key] = {
					id: key,
					catalogNumber: params.catalogNumber || '-',
					name: params.name || '',
					price: parseFloat(params.price) || 0,
					img: params.img || '',
					num: qty,
					url: params.url || window.location.href,
				}
				this.ids.push(key)
			} else {
				this.items[key].num += qty
			}
			this.persist()
		}
		updateQty(id, delta) {
			if (!this.items[id]) return
			this.items[id].num = Math.max(
				1,
				(parseInt(this.items[id].num, 10) || 1) + delta,
			)
			this.persist()
		}
		remove(id) {
			if (!this.items[id]) return
			delete this.items[id]
			this.ids = this.ids.filter(x => x !== id)
			this.persist()
		}
		clear() {
			this.items = {}
			this.ids = []
			this.persist()
		}
		setCoupon(code) {
			if (code === COUPON_CODE) {
				this.coupon = code
				localStorage.setItem('coupon_take_' + COUPON_CODE, code)
				return true
			}
			return false
		}
		totalItems() {
			return Object.values(this.items).reduce(
				(sum, item) => sum + (parseInt(item.num, 10) || 0),
				0,
			)
		}
		totalPrice() {
			let sum = Object.values(this.items).reduce(
				(s, item) =>
					s + (parseFloat(item.price) || 0) * (parseInt(item.num, 10) || 0),
				0,
			)
			if (this.coupon === COUPON_CODE) sum = Math.max(0, sum - COUPON_DISCOUNT)
			return sum
		}
		asOrderItems() {
			return Object.values(this.items).map(item => ({
				id: item.id,
				catalogNumber: item.catalogNumber || '-',
				name: item.name,
				price: item.price,
				num: item.num,
			}))
		}
	}

	class CartUI {
		constructor(store) {
			this.store = store
		}
		ensureAuxInputs() {
			if (!$('input.js-order-result').length) {
				$('body').append(
					'<input type="hidden" name="order_result[]" value="" class="js-order-result">',
				)
			}
			if (!$('input.js-page-params').length) {
				const params = {}
				let i = 0
				const searchParams = new URLSearchParams(window.location.search)
				for (const [name, value] of searchParams.entries())
					params[i++] = { name, value }
				$('body').append(
					$('<input>', {
						type: 'hidden',
						name: 'page_params',
						class: 'js-page-params',
						value: JSON.stringify(params),
					}),
				)
			}
		}
		updateWidgets(widgetSelector) {
			const count = this.store.totalItems()
			const txt = count > 0 ? '(' + count + ')' : '(0)'
			$(widgetSelector).html(txt)
			$('#basketwidjet2').html(txt)
		}
		ensureBasketModal() {
			if (!$('#bcontainer').length) {
				$('body').append(
					`<div id="blindLayer" class="blindLayer"></div>
					<div id="bcontainer" class="bcontainer">
						<div id="bsubject">
							Кошик
							<a id="bclose" href="javascript:void(0)" onclick='cart.closeWindow("bcontainer",1)'>×</a>
						</div>
						<div id="overflw">
							<table class="btable" id="btable"></table>
						</div>
						<div id="bfooter">
							<span id="bsum">...</span>
							<div class="btn_footer_order">
								<button class="bbutton" onclick="cart.showWinow('order',1)">Оформити замовлення</button>
								<button class="bbutton skip" onclick="cart.closeWindow('bcontainer',1)">Продовжити покупки</button>
							</div>
							<div class="coupon">
								<div class="coupon_value"></div>
								<div class="coupon__toggle" onclick="cart.toggleCoupon()">
									<span class="coupon__toggle-icon">+</span>
									<span>У мене є купон на знижку</span>
								</div>
								<div class="coupon_body">
									<div class="coupon_input">
										<span>Введіть код купону:</span>
										<input type="text" name="coupon_input_value" value="" placeholder="Код купону"/>
										<button class="bbutton" onclick="cart.setCoupon()">Застосувати</button>
									</div>
								</div>
							</div>
							<div class="delivery_checkout_text">Доставка</div>
							<div class="delivery_checkout">
								<div id="moscow">...</div>
								<div id="region">Регіони: уточнюйте у оператора</div>
							</div>
						</div>
					</div>`,
				)
			}
		}
		renderTable(onMinus, onPlus, onRemove) {
			this.ensureBasketModal()
			const $table = $('#btable')
			$table.html('')

			const items = Object.values(this.store.items)

			if (items.length === 0) {
				$table.html('<tr><td class="bitem__empty">Кошик порожній</td></tr>')
				this.renderTotals()
				return
			}

			items.forEach(item => {
				const imgHtml = item.img
					? `<img src="${item.img}" alt="${item.name || ''}" loading="lazy">`
					: `<div class="bitem__img-placeholder"></div>`

				const subtotal = (
					(parseFloat(item.price) || 0) * (parseInt(item.num, 10) || 0)
				).toFixed(2)

				$table.append(`
					<tr class="bitem" data-id="${item.id}">
						<td class="bitem__img-cell">${imgHtml}</td>
						<td class="bitem__info-cell">
							<a href="${item.url || '#'}" class="bitem__name">${item.name || ''}</a>
							<div class="bitem__price">${parseFloat(item.price).toFixed(2)} <span>руб.</span></div>
						</td>
						<td class="bitem__qty-cell">
							<div class="bitem__qty-controls">
								<button class="basket_num_buttons" data-op="minus" data-id="${item.id}">−</button>
								<span class="basket_num">${item.num}</span>
								<button class="basket_num_buttons" data-op="plus" data-id="${item.id}">+</button>
							</div>
							<div class="bitem__subtotal">${subtotal} руб.</div>
						</td>
						<td class="bitem__del-cell">
							<button class="bitem__del-btn" data-op="del" data-id="${item.id}" title="Видалити">×</button>
						</td>
					</tr>
				`)
			})

			$table
				.find('[data-op="minus"]')
				.off('click')
				.on('click', function () {
					onMinus($(this).data('id'))
				})
			$table
				.find('[data-op="plus"]')
				.off('click')
				.on('click', function () {
					onPlus($(this).data('id'))
				})
			$table
				.find('[data-op="del"]')
				.off('click')
				.on('click', function () {
					onRemove($(this).data('id'))
				})
			this.renderTotals()
		}
		renderTotals() {
			const sum = this.store.totalPrice()
			$('#bsum')
				.attr('data-price', sum.toFixed(2))
				.html(
					'Сума: <span class="price_value">' + sum.toFixed(2) + '</span> руб.',
				)
			if (sum < 5000) $('#moscow').html('<span>Москва</span>: +250 руб.')
			else $('#moscow').html('<span>Москва</span>: безкоштовно')
		}
		showCoupon(code) {
			$('.coupon__toggle').hide()
			$('.coupon_body').removeClass('is-open')
			$('.coupon_value')
				.show()
				.text('✓ Купон застосовано: ' + code)
		}
	}

	class CheckoutService {
		submit(orderItems, coupon, onDone) {
			$('#send').prop('disabled', true).val('Відправка...')
			$.post('sendmail.php?subj=Order_Olaplex', {
				name: $('#formToSend input#fio').val() || '',
				email: $('#formToSend input#email').val() || '',
				phone: $('#formToSend input#phoneNumber').val() || '',
				contact_method:
					$('#formToSend input[name="contact_method"]:checked').val() || '',
				contact_username: $('#formToSend input#contact_username').val() || '',
				order_result: JSON.stringify(orderItems),
				page_params: $('body input.js-page-params').val() || '',
				comments: $('#formToSend textarea#question').val() || '',
				id_product: orderItems[0] ? orderItems[0].id : '',
				order: '',
				coupon: coupon || '',
				client_order_uuid:
					Date.now().toString(36) + Math.random().toString(36).slice(2),
			})
				.done(onDone)
				.always(function () {
					$('#send').prop('disabled', false).val('Відправити')
				})
		}
	}

	class ModernCart {
		constructor() {
			this.store = null
			this.ui = null
			this.checkout = new CheckoutService()
			this.widgetSelector = ''
			this.CONFIG = {}
		}
		init(widgetID, config) {
			this.CONFIG = config || {}
			this.widgetSelector = '#' + widgetID
			this.store = new CartStore(widgetID)
			this.ui = new CartUI(this.store)
			this.ui.ensureAuxInputs()
			this.ui.updateWidgets(this.widgetSelector)
		}
		addToCart(el, id) {
			// ID може бути числом або рядком з нулями ('010') — нормалізуємо
			const key = String(id).replace(/^0+/, '') || String(id)
			const p = window.PRODUCTS?.[key] ?? window.PRODUCTS?.[id]
			if (!p) {
				console.warn('cart: product not found, id=', id)
				return
			}

			const qtyInput = $('#' + (window.wiNumInputPrefID || 'qty-') + id)
			const qty = parseInt(qtyInput.length ? qtyInput.val() : 1, 10) || 1

			this.store.add({
				id: id,
				name: p.name || '',
				price: p.price || 0,
				img: p.image || p.img || '', // поле 'image' у масиві
				catalogNumber: p.cat_number || p.catalogNumber || '-',
				url: p.link || window.location.href,
				qty,
			})
			this.ui.updateWidgets(this.widgetSelector)
			this.showToast('Товар додано до кошика!')
			if (this.CONFIG.showAfterAdd) this.showWinow('bcontainer', 1)
		}
		renderBasket() {
			this.ui.renderTable(
				id => {
					this.store.updateQty(id, -1)
					this.renderBasket()
				},
				id => {
					this.store.updateQty(id, 1)
					this.renderBasket()
				},
				id => {
					this.store.remove(id)
					this.renderBasket()
				},
			)
			this.ui.updateWidgets(this.widgetSelector)
		}
		showWinow(win, blind) {
			if (win === 'bcontainer') this.renderBasket()
			const $container = $('#' + win)
			const $blind = $('#blindLayer')
			$container.show()
			setTimeout(() => $container.addClass('active'), 10)
			if (blind) {
				$blind.show().addClass('active')
				// Закриття по кліку за межами корзини
				$blind
					.off('click.cartclose')
					.on('click.cartclose', () => this.closeWindow(win, true))
			}
		}
		closeWindow(win, blind) {
			const $container = $('#' + win)
			const $blind = $('#blindLayer')
			$container.removeClass('active')
			if (blind) $blind.removeClass('active')
			setTimeout(() => {
				$container.hide()
				if (blind) $blind.hide()
			}, 350)
		}
		showToast(message, duration = 3000) {
			const toast = $('<div class="cart-toast"></div>')
				.text(message)
				.appendTo('body')
				.fadeIn(250)
			setTimeout(() => toast.fadeOut(250, () => toast.remove()), duration)
		}
		setCoupon() {
			const code = $("input[name='coupon_input_value']").val()
			if (this.store.setCoupon(code)) {
				this.ui.showCoupon(code)
				this.ui.renderTotals()
			} else {
				$("input[name='coupon_input_value']").css('border-color', '#e74c3c')
				setTimeout(
					() => $("input[name='coupon_input_value']").css('border-color', ''),
					1500,
				)
			}
		}
		toggleCoupon() {
			const $toggle = $('.coupon__toggle')
			const $body = $('.coupon_body')
			const isOpen = $body.hasClass('is-open')
			$toggle.toggleClass('is-open', !isOpen)
			$body.toggleClass('is-open', !isOpen)
			if (!isOpen) $("input[name='coupon_input_value']").focus()
		}
		clearBasket() {
			this.store.clear()
			this.ui.updateWidgets(this.widgetSelector)
			$('#btable').html('')
		}
		sendOrder() {
			const items = this.store.asOrderItems()
			this.checkout.submit(items, this.store.coupon, data => {
				if (data === 'ok') {
					this.clearBasket()
					localStorage.removeItem('coupon_take_' + COUPON_CODE)
					window.location.href = '/success.php'
				}
			})
		}
	}

	// Legacy-compatible facade: cart = new WICard("cart"); cart.init(...); cart.addToCart(...)
	window.WICard = function WICard() {
		return new ModernCart()
	}
})(window, window.jQuery)

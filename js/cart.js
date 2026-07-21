/* Modern cart architecture: store + ui + checkout + legacy facade */
;(function (window, $) {
	'use strict'
	if (!$) return

	const COUPON_API_URL = '/api/validate_coupon.php'

	function escHtml(s) {
		return String(s == null ? '' : s)
			.replace(/&/g, '&amp;')
			.replace(/</g, '&lt;')
			.replace(/>/g, '&gt;')
			.replace(/"/g, '&quot;')
			.replace(/'/g, '&#039;')
	}

	// Яндекс.Метрика хранит ClientID посетителя в cookie _ym_uid. Сохраняем его
	// вместе с заказом (см. sendmail.php), чтобы позже иметь возможность
	// сматчить оффлайн-заказ с визитом через Offline Conversions API —
	// без этого purchase-событие теряется у части пользователей с блокировщиками.
	function getYmClientId() {
		const m = document.cookie.match(/(?:^|;\s*)_ym_uid=([^;]+)/)
		return m ? decodeURIComponent(m[1]) : ''
	}

	/*-------------------------------------------------------------
	 * Электронная коммерция — Яндекс.Метрика (dataLayer)
	 * Формат: https://yandex.ru/support/metrica/data/e-commerce.html
	 * window.dataLayer объявляется синхронно в templates/analytics.php,
	 * до подключения этого файла, но на всякий случай подстраховываемся.
	 *-------------------------------------------------------------*/
	const ECOM_BRAND = 'Olaplex'
	const ECOM_CURRENCY = 'RUB'
	const ECOM_PENDING_PURCHASE_KEY = 'ola_pending_purchase'

	const Ecommerce = {
		_seenDetail: new Set(),
		_dl() {
			window.dataLayer = window.dataLayer || []
			return window.dataLayer
		},
		// Тот же алгоритм подбора товара, что и в ModernCart.addToCart —
		// сначала пробуем id без ведущих нулей, потом id как есть.
		resolveProduct(id) {
			const key = String(id).replace(/^0+/, '') || String(id)
			return window.PRODUCTS?.[key] ?? window.PRODUCTS?.[id] ?? null
		},
		// Просмотр карточки товара (наведение в каталоге / открытие страницы товара)
		pushDetail(id, product) {
			const p = product || this.resolveProduct(id)
			if (!p) return
			const key = String(id)
			if (this._seenDetail.has(key)) return // не дублируем повторные наведения
			this._seenDetail.add(key)
			this._dl().push({
				ecommerce: {
					currencyCode: ECOM_CURRENCY,
					detail: {
						products: [{
							id: key,
							name: p.name || '',
							price: Number(p.price) || 0,
							brand: ECOM_BRAND,
						}],
					},
				},
			})
		},
		// Добавление товара в корзину
		pushAdd(id, product, qty) {
			const p = product || this.resolveProduct(id)
			if (!p) return
			this._dl().push({
				ecommerce: {
					currencyCode: ECOM_CURRENCY,
					add: {
						products: [{
							id: String(id),
							name: p.name || '',
							price: Number(p.price) || 0,
							brand: ECOM_BRAND,
							quantity: Number(qty) || 1,
						}],
					},
				},
			})
		},
		// Уменьшение количества / удаление товара из корзины — симметрично pushAdd
		pushRemove(id, item, qty) {
			if (!item) return
			this._dl().push({
				ecommerce: {
					currencyCode: ECOM_CURRENCY,
					remove: {
						products: [{
							id: String(id),
							name: item.name || '',
							price: Number(item.price) || 0,
							brand: ECOM_BRAND,
							quantity: Number(qty) || 1,
						}],
					},
				},
			})
		},
		// Открытие формы оформления заказа — начало чекаута (шаг перед purchase)
		pushCheckout(orderItems, revenue) {
			if (!orderItems || !orderItems.length) return
			this._dl().push({
				ecommerce: {
					currencyCode: ECOM_CURRENCY,
					checkout: {
						actionField: { step: 1 },
						products: orderItems.map(item => ({
							id: String(item.id),
							name: item.name || '',
							price: Number(item.price) || 0,
							brand: ECOM_BRAND,
							quantity: Number(item.num) || 1,
						})),
					},
				},
			})
		},
		// Покупка не может быть отправлена напрямую: страница успеха открывается
		// уже после редиректа, а реальный номер заказа известен только на сервере.
		// Поэтому мы сохраняем состав корзины во sessionStorage — success.php
		// дочитает его и отправит в dataLayer вместе с server-side order_id.
		stagePurchase(orderItems, revenue) {
			try {
				const products = (orderItems || []).map(item => ({
					id: String(item.id),
					name: item.name || '',
					price: Number(item.price) || 0,
					brand: ECOM_BRAND,
					quantity: Number(item.num) || 1,
				}))
				sessionStorage.setItem(ECOM_PENDING_PURCHASE_KEY, JSON.stringify({
					products,
					revenue: Number(revenue) || 0,
				}))
			} catch (e) {
				// приватный режим браузера и т.п. — не критично, просто не будет purchase-события
			}
		},
	}

	window.OlaEcommerce = Ecommerce

	class CartStore {
		constructor(storageKey) {
			this.storageKey = storageKey
			this.idsKey = storageKey + '_ids'
			this.items = this.readJSON(this.storageKey, {})
			this.ids = this.readJSON(this.idsKey, [])

			const saved = this.readJSON('cart_coupon', null)
			if (saved && saved.type && saved.value != null) {
				this.coupon       = saved.code || ''
				this.coupon_type  = saved.type
				this.coupon_value = Number(saved.value) || 0
			} else if (saved) {
				this.coupon       = ''
				this.coupon_type  = ''
				this.coupon_value = 0
				localStorage.removeItem('cart_coupon')
			} else {
				this.coupon       = ''
				this.coupon_type  = ''
				this.coupon_value = 0
			}
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
				const id = rawId.toString()
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
						volume: params.volume || '',
					}
				this.ids.push(key)
			} else {
				this.items[key].num += qty
			}
			this.persist()
		}
		updateQty(id, delta) {
			if (!this.items[id]) return
			const prevNum = parseInt(this.items[id].num, 10) || 1
			this.items[id].num = Math.max(1, prevNum + delta)
			if (delta < 0) {
				Ecommerce.pushRemove(id, this.items[id], Math.min(-delta, prevNum))
			}
			this.persist()
		}
		remove(id) {
			if (!this.items[id]) return
			Ecommerce.pushRemove(id, this.items[id], this.items[id].num)
			delete this.items[id]
			this.ids = this.ids.filter(x => x !== id)
			this.persist()
		}
		clear() {
			this.items = {}
			this.ids = []
			this.persist()
		}

		setCoupon(code, type, value) {
			this.coupon       = code
			this.coupon_type  = type
			this.coupon_value = value
			localStorage.setItem('cart_coupon', JSON.stringify({ code, type, value }))
		}
		clearCoupon() {
			this.coupon       = ''
			this.coupon_type  = ''
			this.coupon_value = 0
			localStorage.removeItem('cart_coupon')
		}
		baseTotalPrice() {
			return Object.values(this.items).reduce(
				(s, item) =>
					s + (parseFloat(item.price) || 0) * (parseInt(item.num, 10) || 0),
				0,
			)
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
			if (this.coupon && this.coupon_value > 0) {
				const disc = this.coupon_type === 'percent'
					? sum * this.coupon_value / 100
					: this.coupon_value
				sum = Math.max(0, sum - disc)
			}
			return sum
		}
asOrderItems() {
				return Object.values(this.items).map(item => ({
					id: item.id,
					catalogNumber: item.catalogNumber || '-',
					name: item.name,
					price: item.price,
					num: item.num,
					volume: item.volume || '',
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
			// Унифицированные селекторы: класс вместо ID
			$('.cart-widget-count').text(txt)
			// Обновление badge в drawer
			$('.minicart-badge').text(count > 0 ? count : '0')
		}
		ensureBasketModal() {
			if (!$('#bcontainer').length) {
				$('body').append(
					`<div id="blindLayer" class="blindLayer"></div>
					<div id="bcontainer" class="bcontainer">

						<!-- HEADER -->
						<div id="bsubject">
							<div class="minicart-title">
								<span>Корзина</span>
								<span class="minicart-badge">0</span>
							</div>
							<a id="bclose" href="javascript:void(0)"
								 onclick='cart.closeWindow("bcontainer",1)'>×</a>
						</div>

						<!-- ITEMS LIST -->
						<div id="overflw">
							<div class="minicart-items" id="minicart-items-list"></div>
						</div>
					<!-- ДОСТАВКА -->
					<div class="minicart-delivery-banner" id="minicart-delivery-banner">
						<svg viewBox="0 0 24 24" fill="none" stroke="currentColor"
								stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
							<rect x="1" y="3" width="15" height="13" rx="2"/>
							<path d="M16 8h4l3 5v3h-7V8z"/>
							<circle cx="5.5" cy="18.5" r="2.5"/>
							<circle cx="18.5" cy="18.5" r="2.5"/>
						</svg>
						<div class="delivery-lines">
							<span class="delivery-text delivery-moscow">Москва: +350₽ | От 5000 — бесплатно</span>
							<span class="delivery-text delivery-regions">Регионы: Уточняйте у оператора</span>
						</div>
					</div>
						<!-- FOOTER -->
						<div id="bfooter">
							<span id="bsum" data-price="0"></span>

							<div class="minicart-total-row">
								<span class="minicart-total-label">Вместе:</span>
								<span class="minicart-total-value" id="minicart-total-display">0 ₽</span>
							</div>

							<div class="btn_footer_order">
								<button class="bbutton checkout"
												onclick="cart.showWinow('order',1)">
									Оформить заказ
									<svg width="16" height="16" viewBox="0 0 24 24" fill="none"
											 stroke="currentColor" stroke-width="2.5"
											 stroke-linecap="round" stroke-linejoin="round">
										<path d="M5 12h14M12 5l7 7-7 7"/>
									</svg>
								</button>
								<button class="bbutton skip"
												onclick="cart.closeWindow('bcontainer',1)">
									Продолжить покупки
								</button>
							</div>

							<div class="coupon">
								<div class="coupon_value"></div>
								<div class="coupon__toggle" onclick="cart.toggleCoupon()">
									<span class="coupon__toggle-icon">+</span>
									<span>У меня есть купон со скидкой</span>
								</div>
								<div class="coupon_body">
									<div class="coupon_input">
										<span>Введите код купона:</span>
										<input type="text" name="coupon_input_value"
													 value="" placeholder="Код купона"/>
										<button class="bbutton" onclick="cart.setCoupon()">
											Применить
										</button>
									</div>
									<div class="coupon_error_msg" style="display:none"></div>
								</div>
							</div>

						</div>
					</div>`,
				)
			}
		}
		renderTable(onMinus, onPlus, onRemove) {
			this.ensureBasketModal()
			const $list = $('#minicart-items-list')
			$list.html('')

			const items = Object.values(this.store.items)

			if (items.length === 0) {
				$list.html('<div class="minicart-empty">Корзина пустая</div>')
				this.renderTotals()
				return
			}

			items.forEach(item => {
				const imgHtml = item.img
					? `<img src="${escHtml(item.img)}" alt="${escHtml(item.name)}" loading="lazy">`
					: `<div class="minicart-item__img-placeholder">
							<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
								<circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/>
								<path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/>
							</svg>
						</div>`

				const trashIcon = `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor"
					stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
					<polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14H6L5 6"/>
					<path d="M10 11v6M14 11v6"/><path d="M9 6V4h6v2"/>
				</svg>`

				$list.append(`
					<div class="minicart-item" data-id="${escHtml(item.id)}">
						<div class="minicart-item__img">${imgHtml}</div>

<div class="minicart-item__info">
								<a href="${escHtml(item.url || '#')}" class="minicart-item__name">
									${escHtml(item.name)}
								</a>
								${item.volume ? `<div class="minicart-item__volume">Объём: ${escHtml(item.volume)} ml</div>` : ''}
								<div class="minicart-item__price">
									${parseFloat(item.price).toFixed(2)} ₽
								</div>
							</div>

						<div class="minicart-item__qty">
							<button class="minicart-qty-btn" data-op="minus"
											data-id="${item.id}">−</button>
							<span class="minicart-qty-value">${item.num}</span>
							<button class="minicart-qty-btn" data-op="plus"
											data-id="${item.id}">+</button>
						</div>

<button class="minicart-item__remove" data-op="del"
											data-id="${item.id}" title="Удалить">
								${trashIcon}
							</button>
					</div>
				`)
			})

			$list.find('[data-op="minus"]').off('click').on('click', function () {
				onMinus($(this).data('id'))
			})
			$list.find('[data-op="plus"]').off('click').on('click', function () {
				onPlus($(this).data('id'))
			})
			$list.find('[data-op="del"]').off('click').on('click', function () {
				onRemove($(this).data('id'))
			})

			this.renderTotals()
		}
		renderTotals() {
			const sum     = this.store.totalPrice()
			const baseSum = this.store.baseTotalPrice()
			const DELIVERY_THRESHOLD = 5000
			const total = sum 

			$('#bsum')
				.attr('data-price', total.toFixed(2))
				.html('Сумма: <span class="price_value">' + total.toFixed(2) + '</span> ₽.')

			$('#minicart-total-display').text(total.toFixed(2) + ' ₽')

				if (baseSum >= DELIVERY_THRESHOLD) {
					$('#minicart-delivery-banner .delivery-moscow').text(
						'Москва: бесплатно | От 5000 — бесплатно'
					)
				} else {
					$('#minicart-delivery-banner .delivery-moscow').text(
						'Москва: +350₽ | От 5000 — бесплатно'
					)
			}

			if (this.store.coupon) {
				this.showCoupon(this.store.coupon)
			}
		}
		showCoupon(code) {
			$('.coupon__toggle').hide()
			$('.coupon_body').removeClass('is-open')
			$('.coupon_value')
				.show()
				.text('✓ Купон применено: ' + code)
		}
	}

	// Сопоставление текста ошибки от сервера с полем формы, которое нужно подсветить
	const FIELD_ERROR_MAP = [
		{ test: /имя/i,      selector: '#formToSend input#fio' },
		{ test: /телефон/i,  selector: '#formToSend input#phoneNumber' },
		{ test: /email|почт/i, selector: '#formToSend input#email' },
	]

	function clearFieldErrors() {
		$('#formToSend input, #formToSend textarea').removeClass('field-error')
	}

	function showFormError(msg) {
		$('.valid-text2').text(msg).show()
		clearFieldErrors()
		for (var i = 0; i < FIELD_ERROR_MAP.length; i++) {
			if (FIELD_ERROR_MAP[i].test.test(msg)) {
				$(FIELD_ERROR_MAP[i].selector).addClass('field-error').trigger('focus')
				break
			}
		}
	}

	// Снимаем подсветку с поля, как только пользователь начинает его исправлять
	$(document).on('input', '#formToSend input, #formToSend textarea', function () {
		$(this).removeClass('field-error')
	})

	class CheckoutService {
		submit(orderItems, coupon, onDone) {
			$('.valid-text2').hide()
			clearFieldErrors()
			$('#send').prop('disabled', true).val('Отправка...')
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
				client_order_uuid: (typeof crypto !== 'undefined' && crypto.randomUUID) ? crypto.randomUUID() : (Date.now().toString(36) + Math.random().toString(36).slice(2)),
				csrf_token: $('#formToSend input[name="csrf_token"]').val() || '',
				ym_client_id: getYmClientId(),
			})
			.done(function(data) {
				if (typeof onDone === 'function') onDone(data)
				if (data !== 'ok') {
					var msg = (data && data.error) ? data.error : 'Ошибка отправки. Попробуйте снова.'
					showFormError(msg)
				}
			})
			.fail(function(xhr) {
				// Сервер мог ответить 400/403/429/500 с телом вида {"error": "..."}.
				// jQuery считает такие статусы неудачей и не парсит JSON сам — делаем это вручную,
				// чтобы показать пользователю настоящую причину, а не общий текст.
				var msg = null
				if (xhr.responseJSON && xhr.responseJSON.error) {
					msg = xhr.responseJSON.error
				} else if (xhr.responseText) {
					try {
						var parsed = JSON.parse(xhr.responseText)
						if (parsed && parsed.error) msg = parsed.error
					} catch (e) { /* тело не JSON — используем запасной текст ниже */ }
				}
				if (!msg) {
					msg = 'Ошибка соединения. Проверьте интернет и попробуйте ещё раз.'
					if (xhr.status === 429) msg = 'Слишком много попыток. Подождите минуту.'
					if (xhr.status === 403) msg = 'Ошибка безопасности. Обновите страницу.'
				}
				showFormError(msg)
			})
			.always(function () {
				$('#send').prop('disabled', false).val('Отправить')
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
				img: p.image || p.img || '',
catalogNumber: p.cat_number || p.catalogNumber || '-',
					url: p.link || window.location.href,
					volume: p.volume || '',
					qty,
				})
			Ecommerce.pushAdd(id, p, qty)
			this.ui.updateWidgets(this.widgetSelector)
			this.showToast('Товар добавлено в корзину!')
			if (this.CONFIG.showAfterAdd) this.showWinow('bcontainer', 1)
		}
		renderBasket() {
			this.ui.renderTable(
				id => {
					const item = this.store.items[id]
					if (item && item.num <= 1) {
						this.showConfirm(
							`Убрать <strong>${escHtml(item.name || 'товар')}</strong> с корзины?`,
							() => {
								this.store.remove(id)
								this.renderBasket()
							},
						)
					} else {
						this.store.updateQty(id, -1)
						this.renderBasket()
					}
				},
				id => {
					this.store.updateQty(id, 1)
					this.renderBasket()
				},
				id => {
					const item = this.store.items[id]
					this.showConfirm(
						`Удалить <strong>${escHtml(item ? item.name : 'товар')}</strong> с корзины?`,
						() => {
							this.store.remove(id)
							this.renderBasket()
						},
					)
				},
			)
			this.ui.updateWidgets(this.widgetSelector)
		}
		showWinow(win, blind) {
			if (win === 'bcontainer') this.renderBasket()
			if (win === 'order') Ecommerce.pushCheckout(this.store.asOrderItems(), this.store.totalPrice())
			const $container = $('#' + win)
			const $blind = $('#blindLayer')
			$container.show()
			setTimeout(() => $container.addClass('active'), 10)
			if (blind) {
				$blind.show().addClass('active')
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
		showCouponError(msg) {
			const $err = $('.coupon_error_msg')
			$err.text(msg).stop(true).fadeIn(150)
			clearTimeout(this._couponErrTimer)
			this._couponErrTimer = setTimeout(() => $err.fadeOut(300), 5000)
		}
		hideCouponError() {
			clearTimeout(this._couponErrTimer)
			$('.coupon_error_msg').hide().text('')
		}
		async setCoupon() {
			const code = $("input[name='coupon_input_value']").val().trim().toUpperCase()
			if (!code) return

			const sum = this.store.baseTotalPrice() // сума БЕЗ скидки купона
			if (sum <= 0) return

			const $input = $("input[name='coupon_input_value']")
			const $btn = $('.coupon_input .bbutton')
			$btn.prop('disabled', true).text('...')
			this.ui.hideCouponError()  // скрыть предыдущую ошибку при новой попытке

			try {
				const url = new URL(COUPON_API_URL, window.location.origin)
				url.searchParams.set('code', code)
				url.searchParams.set('sum', sum.toFixed(2))
				const res = await fetch(url.toString())
				const data = await res.json()

				if (res.ok && data.valid) {
					const { discount_type: dtype, discount_value: dvalue } = data.coupon
					this.store.setCoupon(code, dtype, dvalue)
					this.ui.showCoupon(code)
					this.ui.renderTotals()
					$input.css('border-color', '#27ae60')
					setTimeout(() => $input.css('border-color', ''), 2000)
				} else {
					this.store.clearCoupon()
					$input.css('border-color', '#e74c3c')
					setTimeout(() => $input.css('border-color', ''), 2000)
					const errMsg = data.error || 'Купон недействителен'
					this.ui.showCouponError(errMsg)
				}
			} catch (e) {
				$input.css('border-color', '#e74c3c')
				setTimeout(() => $input.css('border-color', ''), 2000)
				this.ui.showCouponError('Ошибка соединения. Проверьте интернет и попробуйте снова.')
			} finally {
				$btn.prop('disabled', false).text('Применить')
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
			this.store.clearCoupon()
			this.ui.renderTable(
				id => this.store.updateQty(id, -1),
				id => this.store.updateQty(id, 1),
				id => this.store.remove(id)
			)
			this.ui.updateWidgets(this.widgetSelector)
		}
		showConfirm(message, onConfirm) {
			// Удаляем предыдущий диалог если есть
			$('#cart-confirm-overlay').remove()

			const $overlay = $(`
				<div id="cart-confirm-overlay" style="
					position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:99999;
					display:flex;align-items:center;justify-content:center;">
					<div id="cart-confirm-box" style="
						background:#fff;border-radius:12px;padding:28px 32px;max-width:340px;
						width:90%;box-shadow:0 8px 32px rgba(0,0,0,.18);text-align:center;">
						<p style="margin:0 0 20px;font-size:15px;line-height:1.5;color:#222;">
							${message}
						</p>
						<div style="display:flex;gap:10px;justify-content:center;">
							<button id="cart-confirm-yes" style="
								flex:1;padding:10px 0;border:none;border-radius:8px;
								background:#e74c3c;color:#fff;font-size:14px;font-weight:600;
								cursor:pointer;">Удалить</button>
							<button id="cart-confirm-no" style="
								flex:1;padding:10px 0;border:none;border-radius:8px;
								background:#f0f0f0;color:#444;font-size:14px;font-weight:600;
								cursor:pointer;">Отменить</button>
						</div>
					</div>
				</div>
			`)

			$('body').append($overlay)

			// Закрытие
			const close = () => $overlay.remove()

			$overlay.find('#cart-confirm-yes').on('click', () => {
				close()
				onConfirm()
			})
			$overlay.find('#cart-confirm-no').on('click', close)
			$overlay.on('click', function (e) {
				if ($(e.target).is($overlay)) close()
			})
		}
		sendOrder() {
			const form = document.getElementById('formToSend')
			if (form && !form.checkValidity()) {
				form.reportValidity()
				return
			}
			const phoneDigits = ($('#formToSend input#phoneNumber').val() || '').replace(/\D/g, '')
			if (phoneDigits.length < 10) {
				showFormError('Неверный номер телефона')
				return
			}
			const items = this.store.asOrderItems()
			const revenue = this.store.totalPrice()
			this.checkout.submit(items, this.store.coupon, data => {
				if (data === 'ok') {
					Ecommerce.stagePurchase(items, revenue)
					this.clearBasket() 
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
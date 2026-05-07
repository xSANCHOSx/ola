<!---Форма для магазина-------------------------------->
<div id="order" class="popup"> <a href="javascript:void(0)" class="close_popup" onclick="cart.closeWindow('order', 0)"
		style="position: absolute;margin: -25px 0px 0px 0px; right: 0;"><img src="images/close.png" /></a>
	<div class="valid-text2"></div>
	<h4 style="text-align: center;">Введите ваши контактные данные</h4>
	<form id="formToSend" onSubmit="cart.sendOrder('formToSend,overflw,bsum'); return(false);">
		<input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
		<input name="name" id="fio" type="text" placeholder="Ваши фамилия и имя" required />
		<input name="phone" id="phoneNumber" type="text" placeholder="Контактный телефон*" required class="text-input" />
		<input name="mail" id="email" pattern="[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,4}$" type="text" name="email"
			placeholder="Электронная почта" required />
		<div class="contact-method">
			<p>Желаемый способ коммуникации:</p>
			<label><input type="radio" name="contact_method" value="whatsapp"> WhatsApp</label>
			<label><input type="radio" name="contact_method" value="telegram"> Telegram</label>
			<label><input type="radio" name="contact_method" value="max"> Max</label>
		</div>
		<div id="contact-username-wrapper">
			<input type="text" name="contact_username" id="contact_username" placeholder="@username">
		</div>
		<textarea name="comments" id="question" placeholder="Адрес"></textarea>
		<input type="hidden" id="id_product" value="">
		<?php if (!empty($currentProduct['status'])) { ?>
			<input type="hidden" id="status" value="Предзаказ"> <?php } ?>
		<input type="hidden" class="valTrFal" value="valTrFal_disabled">
		<input type="checkbox" id="checkBoxId" style="margin: 2px;"> <label class="font-geometria-light">Я согласен с <a
				href="/oferta" class="oferta" target="_blank">обработкой персональных данных</a> и с <a href="/policy"
				class="oferta" target="_blank">политикой конфиденциальности</a></label>
		<input class="bbutton checkout send" id="send" type="submit" value="Відправити" disabled="disabled" />
	</form>
</div>
<!----------------------------------------------------->


<script>
	document.addEventListener('DOMContentLoaded', function() {
		const radios = document.querySelectorAll('input[name="contact_method"]');
		const wrapper = document.getElementById('contact-username-wrapper');
		const input = document.getElementById('contact_username');

		function updateField(value) {
			if (value === 'email') {
				wrapper.style.display = 'none';
				input.removeAttribute('required');
			} else {
				wrapper.style.display = 'block';
				input.setAttribute('required', 'required');

				let placeholder = '@username';

				if (value === 'whatsapp') placeholder = 'Номер WhatsApp';
				if (value === 'telegram') placeholder = '@telegram_username';
				if (value === 'max') placeholder = '@max_username';

				input.placeholder = placeholder;
			}
		}

		radios.forEach(radio => {
			radio.addEventListener('change', function() {
				updateField(this.value);
			});
		});

		const checked = document.querySelector('input[name="contact_method"]:checked');
		if (checked) updateField(checked.value);
	});
</script>

<style>
	.contact-method {
		margin: 15px 0;
	}

	.contact-method label {
		display: block;
		margin-bottom: 5px;
	}

	#contact-username-wrapper {
		margin-bottom: 10px;
	}
</style>
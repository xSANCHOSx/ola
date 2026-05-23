<!---Форма для магазина-------------------------------->
<div id="order" class="popup"> <a href="javascript:void(0)" class="close_popup" onclick="cart.closeWindow('order', 0)"
		style="position: absolute;margin: -25px 0px 0px 0px; right: 0;"><img src="/images/close.png" /></a>
	<div class="valid-text2"></div>
	<h4 style="text-align: center;">Введите ваши контактные данные</h4>
	<form id="formToSend" onSubmit="cart.sendOrder(); return false;">
		<input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
		<input name="name" id="fio" type="text" placeholder="Ваши фамилия и имя" required />
		<input name="phone" id="phoneNumber" type="text" placeholder="Контактный телефон*" required class="text-input" />
		<input name="email" id="email" type="email" placeholder="Электронная почта" required />
		<div class="contact-method">
			<p>Желаемый способ коммуникации:</p>
			<label><input type="radio" name="contact_method" value="whatsapp"> WhatsApp</label>
			<label><input type="radio" name="contact_method" value="telegram"> Telegram</label>
			<label><input type="radio" name="contact_method" value="max"> Max</label>
		</div>
		<div id="contact-username-wrapper">
			<input type="text" name="contact_username" id="contact_username" placeholder="@username">
		</div>
		<textarea name="comments" id="question" placeholder="Адрес" maxlength="2000"></textarea>
		<input type="hidden" id="id_product" value="">
		<?php if (!empty($currentProduct['status'])) { ?>
		<input type="hidden" id="status" value="Предзаказ"> <?php } ?>
		<input type="hidden" class="valTrFal" value="valTrFal_disabled">
		<input type="checkbox" id="checkBoxId" style="margin: 2px;"> <label class="font-geometria-light">Я согласен с <a
				href="/oferta" class="oferta" target="_blank">обработкой персональных данных</a> и с <a href="/policy"
				class="oferta" target="_blank">политикой конфиденциальности</a></label>
		<input class="bbutton checkout send" id="send" type="submit" value="Отправить" disabled="disabled" />
	</form>
</div>
<!----------------------------------------------------->
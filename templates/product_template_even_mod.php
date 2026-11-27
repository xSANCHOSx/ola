<?php $productStatus = product_status($product); ?>
<div class="max-feature-section-list container-fluid <?= ($index % 2 === 0) ? 'even' : 'odd'; ?>">
	<div class="row">
		<div class="col-sm-12 col-md-6 img_lg">
			<div class="image animated fadeInDown">
				<?= webp_img($product['image'], $product['name'], '', ['loading' => 'lazy', 'width' => 400, 'height' => 400]) ?>
			</div>
		</div>
		<div class="col-sm-12 col-md-6 tovar-name animated fadeInDown" data-id="<?= htmlspecialchars((string)$product['id']) ?>">
			<h2><a class="name_link" href="<?= $product['link'] ?>">
					<?php if (!empty($product['cat_number'])): ?>
						<strong><?= $product['cat_number'] ?></strong><br />
					<?php endif; ?>
					<?= $product['name'] ?></a>
			</h2>
			<span></span>
			<div class="col-xs-12 buy">
				<?php if (product_is_buyable($product)) { ?>
					<?php include 'product_special.php'; ?>
					<p>Цена:
						<?php if (!empty($product['old_price'])): ?>
							<span class="price_old"><?= $product['old_price'] ?></span>
						<?php endif; ?><span class="regular_price"> <strong><?= $product['price'] ?></strong></span> ₽
					</p>
					<?php } elseif ($productStatus === 'preorder') { ?>
							<p><span class="regular_price"><strong>Предзаказ</strong></span></p>
							<p><strong>Срок доставки: 7-14 дней</strong></p>
					<?php } else { ?>
							<p><span class="regular_price"><strong>Нет в наличии</strong></span></p>
						<?php } ?>
					<p><a class="name_link" href="<?= $product['link'] ?>"><?= $product['short_desc'] ?></a></p>
				<?php if (!empty($product['short_desc2'])): ?>
					<p><a class="name_link" href="<?= $product['link'] ?>"><?= $product['short_desc2'] ?></a></p>
				<?php endif; ?>
					<button class="b1c<?= $productStatus === 'preorder' ? ' product-buy-button--preorder' : '' ?>"
						data-product-status="<?= htmlspecialchars($productStatus, ENT_QUOTES, 'UTF-8') ?>"
						data-product-id="<?= htmlspecialchars((string)$product['id'], ENT_QUOTES, 'UTF-8') ?>"
						data-preorder-db-id="<?= (int)($product['db_id'] ?? 0) ?>"
						data-product-name="<?= htmlspecialchars((string)$product['name'], ENT_QUOTES, 'UTF-8') ?>"
						<?php if (product_is_buyable($product)): ?>
							onclick="cart.addToCart(this, '<?= htmlspecialchars((string)$product['id']) ?>')"
						<?php elseif ($productStatus === 'preorder'): ?>
							type="button" data-preorder-product
							aria-label="Оставить заявку на <?= htmlspecialchars((string)$product['name'], ENT_QUOTES, 'UTF-8') ?>"
						<?php else: ?>
							type="button" disabled
						<?php endif; ?>>
					<?php echo product_button_label($product); ?>
				</button>
			</div>
			<p style="text-align: justify;"><?= $product['desc'] ?></p>

		</div>
	</div>
</div>

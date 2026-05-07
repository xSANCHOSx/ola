<div class="max-feature-section-list container-fluid <?= ($index % 2 === 0) ? 'even' : 'odd'; ?>">
	<div class="row">
		<div class="col-sm-12 col-md-6 img_lg">
			<div class="image animate--one" data-animate="fadeInDown" data-duration="2"><img src="<?= $product['image'] ?>"
					alt="<?= $product['cat_number'] ?> <?= $product['name'] ?>"></div>
		</div>
		<div class="col-sm-12 col-md-6 tovar-name animate--one" data-animate="fadeInDown" data-duration="3" data-id="">
			<h2><a class="name_link" href="<?= $product['link'] ?>">
					<?php if (!empty($product['cat_number'])): ?>
						<strong><?= $product['cat_number'] ?></strong><br />
					<?php endif; ?>
					<?= $product['name'] ?></a>
			</h2>
			<span></span>
			<div class="col-xs-12 buy">
				<?php if ($product['in_stock']) { ?>
					<?php include 'product_special.php'; ?>
					<p>Цена:
						<?php if (!empty($product['old_price'])): ?>
							<span class="price_old"><?= $product['old_price'] ?></span>
						<?php endif; ?><span class="regular_price"> <strong><?= $product['price'] ?></strong></span> ₽
					</p>
				<?php } else { ?>
					<?php if (!empty($product['status']) && $product['status'] === 'preorder') { ?>
						<p><span class="regular_price"><strong>Предзаказ</strong></span></p>
						<p><strong>Срок доставки: 7-14 дней</strong></p>
					<?php } else { ?>
						<p><span class="regular_price"><strong>Нет в наличии</strong></span></p>
					<?php } ?>
				<?php } ?>
				<p><a class="name_link" href="<?= $product['link'] ?>"><?= $product['short_desc'] ?></a></p>
				<?php if (!empty($product['short_desc2'])): ?>
					<p><a class="name_link" href="<?= $product['link'] ?>"><?= $product['short_desc2'] ?></a></p>
				<?php endif; ?>
				<button class="b1c"
					<?php if (!empty($product['in_stock']) || (!empty($product['status']) && $product['status'] === 'preorder')) { ?>
					onclick="cart.addToCart(this, <?= (int)$product['id'] ?>)" <?php } else { ?> disabled <?php } ?>>
					<?php
					if (!empty($product['status']) && $product['status'] === 'preorder') {
						echo 'Предзаказ';
					} else {
						echo 'Купить';
					}
					?>
				</button>
			</div>
			<p style="text-align: justify;"><?= $product['desc'] ?></p>

		</div>
	</div>
</div>
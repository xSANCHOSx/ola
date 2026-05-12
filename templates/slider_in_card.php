<?php require_once __DIR__ . '/../data/products.php'; ?>
<!-- Start Slider section -->
<section id="max-team-section">
	<div class="max-section-title">
		<h2>ДРУГИЕ ТОВАРЫ OLAPLEX</h2>
	</div>
	<div id="max-team" class="container" style="padding-bottom: 0px;">
		<div class="flexslider">
			<ul class="slides">
				<?php foreach ($products as $index => $product): ?>
					<li>
						<div class="col-xs-12">
							<div class="member-box animate--one animated zoomIn">
								<div class="member-profile">
									<a href="<?= $product['link'] ?>">
										<h2 style="min-height: 72px;">
											<?php if (!empty($product['cat_number'])): ?>
												<strong><?= $product['cat_number'] ?></strong><br />
											<?php endif; ?>
											<?= $product['name'] ?>
										</h2>
										<div class="img_wrap">
											<img src="<?= $product['image'] ?>" alt="<?= $product['cat_number'] ?> <?= $product['name'] ?>" loading="lazy">
										</div>
									</a>
								</div>
							</div>
						</div>
					</li>
				<?php endforeach; ?>
			</ul>
		</div>
	</div>
</section>
<!-- ./ Ending Slider section -->

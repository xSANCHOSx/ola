<?php

/**
 * Partial: страница диплома (di_type = custom)
 * Используется из taxonomy-diploma_category.php
 *
 * @package ruyxd_theme
 */
require get_template_directory() . '/template-parts/func-in-template.php';


// ── Первый привязанный diploma CPT пост ───────────────────────────────────
$diploma_query = new WP_Query([
	'post_type'      => 'diploma',
	'post_status'    => 'publish',
	'posts_per_page' => 1,
	'orderby'        => 'menu_order',
	'order'          => 'ASC',
	'tax_query'      => [[
		'taxonomy' => 'diploma_category',
		'field'    => 'term_id',
		'terms'    => $term_id,
	]],
]);

$diploma_post = $diploma_query->have_posts() ? $diploma_query->posts[0] : null;
wp_reset_postdata();

// ── ACF поля из поста ─────────────────────────────────────────────────────
$years       = $diploma_post ? get_field('years',           $diploma_post->ID) : '';
$set         = $diploma_post ? get_field('set',             $diploma_post->ID) : 'Диплом + твёрдая обложка + приложение';
$quality     = $diploma_post ? get_field('quality',         $diploma_post->ID) : 'ГОЗНАК со всеми защитными элементами';
$qualification = $diploma_post ? get_field('qualification', $diploma_post->ID) : '';
$filling     = $diploma_post ? get_field('filling',         $diploma_post->ID) : 'по стандартам Минобрнауки РФ';
$terms_field = $diploma_post ? get_field('terms',           $diploma_post->ID) : 'изготовление за 1 день, доставка в течение 24 часов';
$in_stock    = $diploma_post ? get_field('in_stock',        $diploma_post->ID) : false;
$diploma_data = $diploma_post ? get_field('diploma_image', $diploma_post->ID) : null;
$diplom_url = is_array($diploma_data) ? $diploma_data['url'] : $diploma_data;
$post_url    = $diploma_post ? get_permalink($diploma_post->ID) : '#';


$current_cat = get_queried_object();
$cat_id = $current_cat->term_id;
$cat_image_field = get_field('cat_image', 'category_' . $cat_id);

if (!empty($cat_image_field)) {
	$main_thumb = $cat_image_field;
} elseif (!empty($diplom_url)) {
	$main_thumb = $diplom_url;
} else {
	$main_thumb = $assets_uri . '/images/popular-docs/popular-doc-thumb.png';
}

get_header();
get_sidebar();
?>

<main class="body">
	<div class="body__container">

		<section class="higher-edu" aria-labelledby="<?php echo 'diploma-title-' . $term_id; ?>">

			<?php // ── Хлебные крошки Yoast ──────────────────────────────────────────── 
			?>
			<?php if (function_exists('yoast_breadcrumb')) : ?>
				<?php yoast_breadcrumb('<div class="higher-edu__breadcrumbs">', '</div>'); ?>
			<?php else : ?>
				<div class="higher-edu__breadcrumbs">
					<a class="higher-edu__crumb" href="<?php echo esc_url(home_url('/')); ?>">Главная</a>
					<span class="higher-edu__crumb-sep">→</span>
					<span class="higher-edu__crumb higher-edu__crumb--current"><?php echo esc_html($term_name); ?></span>
				</div>
			<?php endif; ?>

			<div class="higher-edu__head">
				<h1 class="higher-edu__title" id="<?php echo 'diploma-title-' . $term_id; ?>">
					<?php echo esc_html($h1); ?>
				</h1>
				<div class="higher-edu__underline" aria-hidden="true"></div>
			</div>

			<?php $description = term_description($term_id, 'diploma_category'); ?>

			<?php if ($description) : ?>
				<div class="higher-edu__desc">
					<?php echo wp_kses_post($description); ?>
				</div>
			<?php else : ?>
				<p class="higher-edu__desc">
					На нашем сайте Вы можете купить <?php echo esc_html(mb_strtolower($term_name)); ?>.
					Документы изготавливаются на оригинальных бланках ГОЗНАК со всеми степенями защиты,
					с корректными реквизитами, печатями и подписями. Обращайтесь, сделаем быстро и качественно!
				</p>
			<?php endif; ?>
			<?php get_template_part('template-parts/prices-diplom'); ?>
		</section>

		<section class="diploma-year" aria-labelledby="diploma-card-title-<?php echo esc_attr($term_id); ?>">
			<article class="diploma-year-card">

				<div class="diploma-year-card__media" aria-hidden="true">
					<div class="diploma-year-card__main">
						<img src="<?php echo esc_url($main_thumb); ?>" alt="" />
					</div>
					<?php if (! empty($samples)) : ?>
						<div class="diploma-year-card__thumbs">
							<?php foreach ($samples as $sample) :
								$sample_url = is_array($sample) ? ($sample['url'] ?? '') : $sample;
							?>
								<img src="<?php echo esc_url($sample_url); ?>" alt="" />
							<?php endforeach; ?>
						</div>
					<?php endif; ?>
				</div>

				<div class="diploma-year-card__content">
					<h2 class="diploma-year-card__title" id="diploma-card-title-<?php echo esc_attr($term_id); ?>">
						<?php echo esc_html($h1); ?>
						<?php if ($years) : ?>— <?php echo esc_html($years); ?> года<?php endif; ?>
					</h2>

					<ul class="diploma-year-card__spec">
						<?php if ($set) : ?>
							<li><strong>Комплект:</strong> <?php echo esc_html($set); ?></li>
						<?php endif; ?>
						<?php if ($quality) : ?>
							<li><strong>Качество:</strong> <?php echo esc_html($quality); ?></li>
						<?php endif; ?>
						<?php if ($qualification) : ?>
							<li><strong>Квалификация:</strong> <?php echo esc_html($qualification); ?></li>
						<?php endif; ?>
						<?php if ($filling) : ?>
							<li><strong>Заполнение:</strong> <?php echo esc_html($filling); ?></li>
						<?php endif; ?>
						<?php if ($terms_field) : ?>
							<li><strong>Сроки:</strong> <?php echo esc_html($terms_field); ?></li>
						<?php endif; ?>
						<?php if ($in_stock) : ?>
							<li class="diploma-year-card__in-stock">ДИПЛОМ В НАЛИЧИИ!</li>
						<?php endif; ?>
					</ul>
					<?php get_template_part('template-parts/prices-button'); ?>
				</div>

			</article>
		</section>

		<?php require get_template_directory() . '/template-parts/related-diploms.php'; ?>

		<section class="requirements" aria-labelledby="how-to-order-title-<?php echo esc_attr($term_id); ?>">
			<h2 class="requirements__title" id="how-to-order-title-<?php echo esc_attr($term_id); ?>">
				Как заказать диплом?
			</h2>
			<p class="requirements__text">
				<?php require get_template_directory() . '/template-parts/contact-info.php'; ?>
			</p>
		</section>

		<?php require get_template_directory() . '/template-parts/order-online.php'; ?>
		<?php get_template_part('template-parts/prices-benefits'); ?>

	</div>
</main>

<?php get_footer(); ?>
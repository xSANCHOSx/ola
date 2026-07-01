<?php

declare(strict_types=1);
require_once __DIR__ . '/../config/db.php';

$pdo   = dev_db_connection();
$posts = [];
$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 10;
$offset  = ($page - 1) * $perPage;

// ── Фильтр по тегу ───────────────────────────────────────────────────────────
// Разрешаем только безопасные символы (латиница, цифры, дефис)
$activeTag     = isset($_GET['tag']) ? trim((string)$_GET['tag']) : '';
$activeTag     = preg_replace('/[^a-z0-9\-]/i', '', $activeTag);
$activeTagName = ''; // Человеческое имя тега для вывода

$totalPages = 1;

if ($pdo instanceof PDO) {

	if ($activeTag !== '') {
		// ── Режим фильтра по тегу ────────────────────────────────
		// Получить имя тега для заголовка
		$tagNameStmt = $pdo->prepare('SELECT name FROM blog_tags WHERE slug = :slug LIMIT 1');
		$tagNameStmt->execute(['slug' => $activeTag]);
		$tagRow = $tagNameStmt->fetch();
		if ($tagRow) {
			$activeTagName = (string)$tagRow['name'];
		}

		// Посты с этим тегом
		$postsStmt = $pdo->prepare(
			'
			SELECT bp.*
			FROM blog_posts bp
			JOIN blog_post_tags bpt ON bp.id  = bpt.post_id
			JOIN blog_tags      bt  ON bt.id  = bpt.tag_id
			WHERE bp.status = "published"
			  AND bt.slug   = :slug
			ORDER BY bp.published_at DESC
			LIMIT  ' . $perPage . '
			OFFSET ' . $offset
		);
		$postsStmt->execute(['slug' => $activeTag]);
		$posts = $postsStmt->fetchAll();

		// Общее количество для пагинации
		$cntStmt = $pdo->prepare('
			SELECT COUNT(*) 
			FROM blog_posts bp
			JOIN blog_post_tags bpt ON bp.id = bpt.post_id
			JOIN blog_tags      bt  ON bt.id = bpt.tag_id
			WHERE bp.status = "published"
			  AND bt.slug   = :slug
		');
		$cntStmt->execute(['slug' => $activeTag]);
		$total      = (int)$cntStmt->fetchColumn();
		$totalPages = (int)ceil($total / $perPage);
	} else {
		// ── Обычный список постов ─────────────────────────────────
		$postsStmt = $pdo->query(
			'SELECT * FROM blog_posts
			 WHERE status = "published"
			 ORDER BY published_at DESC
			 LIMIT ' . $perPage . ' OFFSET ' . $offset
		);
		$posts = $postsStmt ? $postsStmt->fetchAll() : [];

		$total      = (int)$pdo->query('SELECT COUNT(*) FROM blog_posts WHERE status = "published"')->fetchColumn();
		$totalPages = (int)ceil($total / $perPage);
	}
}

$pageTitle       = 'Блог Olaplex - Статьи про Уход за Волосами';
$pageDescription = 'Читайте полезные статьи о восстановлении и уходе за волосами с помощью инновационных средств Olaplex';
?>
<!DOCTYPE html>
<html lang="ru">

<?php require __DIR__ . '/head.php'; ?>

<style>
	/* Отступ для фиксированной шапки — как на странице отдельного поста (blog_single.php) */
	.blog-listing-wrap {
		padding-top: 90px;
		padding-bottom: 60px;
	}

	@media (max-width: 576px) {
		.blog-listing-wrap {
			padding-top: 80px;
		}
	}
</style>

<body>
	<?php require __DIR__ . '/header.php'; ?>

	<div class="container blog-listing-wrap">
		<div class="row">
			<div class="col-md-8">

				<?php if ($activeTag !== '' && $activeTagName !== ''): ?>
					<!-- ── Шапка фильтра по тегу ── -->
					<div
						style="display:flex; align-items:center; justify-content:space-between; margin-bottom:20px; flex-wrap:wrap; gap:10px;">
						<h1 style="margin:0; color:#333; border-bottom:2px solid #3e7ab6; padding-bottom:12px;">
							Блог
							<span style="font-weight:400; color:#3e7ab6; font-size:1.1rem;">
								&nbsp;/ тег: #<?= htmlspecialchars($activeTagName, ENT_QUOTES, 'UTF-8') ?>
							</span>
						</h1>
						<a href="/blog"
							style="white-space:nowrap; font-size:13px; color:#999; text-decoration:none; border:1px solid #ddd; padding:4px 12px; border-radius:20px;">
							✕ Сбросить фильтр
						</a>
					</div>
				<?php else: ?>
					<h1 style="margin-bottom:30px; color:#333; border-bottom:2px solid #3e7ab6; padding-bottom:15px;">Блог</h1>
				<?php endif; ?>

				<?php if (empty($posts)): ?>
					<div style="padding:30px; text-align:center; background:#f9f9f9; border-radius:5px;">
						<?php if ($activeTag !== ''): ?>
							<p style="color:#666; font-size:16px;">
								По тегу <strong>#<?= htmlspecialchars($activeTagName ?: $activeTag, ENT_QUOTES, 'UTF-8') ?></strong> постов
								не найдено.
							</p>
							<a href="/blog" style="color:#3e7ab6;">← Показать все посты</a>
						<?php else: ?>
							<p style="color:#666; font-size:16px;">Пока постов нет. Следите за обновлениями!</p>
						<?php endif; ?>
					</div>
				<?php else: ?>
					<?php foreach ($posts as $post): ?>

						<article style="margin-bottom:40px; padding:0 20px 30px; border-bottom:1px solid #eee;">

							<?php if (!empty($post['featured_image'])): ?>
								<a href="/blog/<?= htmlspecialchars((string)$post['slug']) ?>">
									<?php
									$_imgSrc  = '/' . ltrim((string)$post['featured_image'], '/');
									$_imgWebp = preg_replace('/\\.(jpe?g|png)$/i', '.webp', $_imgSrc);
									$_webpFull = rtrim($_SERVER['DOCUMENT_ROOT'], '/') . '/' . ltrim($_imgWebp, '/');
									if (file_exists($_webpFull)): ?>
										<picture>
											<source srcset="<?= htmlspecialchars($_imgWebp) ?>" type="image/webp">
											<img src="<?= htmlspecialchars($_imgSrc) ?>" alt="<?= htmlspecialchars((string)$post['title']) ?>"
												class="img-responsive blog_list_image" loading="lazy" width="800" height="450">
										</picture>
									<?php else: ?>
										<img src="<?= htmlspecialchars($_imgSrc) ?>" alt="<?= htmlspecialchars((string)$post['title']) ?>"
											class="img-responsive blog_list_image" loading="lazy" width="800" height="450">
									<?php endif; ?>
								</a>
							<?php endif; ?>

							<h2 style="margin-bottom:10px; color:#333;">
								<a href="/blog/<?= htmlspecialchars((string)$post['slug']) ?>" style="color:#3e7ab6; text-decoration:none;">
									<?= htmlspecialchars((string)$post['title']) ?>
								</a>
							</h2>

							<?php
							$excerpt = trim((string)($post['excerpt'] ?? ''));
							if (!empty($excerpt)): ?>
								<p style="color:#666; line-height:1.6; font-size:15px; margin-bottom:15px;">
									<?= htmlspecialchars($excerpt) ?>
								</p>
							<?php endif; ?>

							<a href="/blog/<?= htmlspecialchars((string)$post['slug']) ?>"
								style="color:#3e7ab6; font-weight:bold; text-decoration:none;">
								Подробнее →
							</a>
						</article>

					<?php endforeach; ?>

					<!-- ── Пагинация ── -->
					<?php if ($totalPages > 1): ?>
						<nav style="margin-top:40px; text-align:center;">
							<ul style="list-style:none; padding:0; display:inline-block;">
								<?php for ($i = 1; $i <= $totalPages; $i++): ?>
									<li style="display:inline-block; margin:0 5px;">
										<?php
										// Сохраняем параметр тега в ссылках пагинации
										$pageUrl = '/blog?page=' . $i;
										if ($activeTag !== '') {
											$pageUrl .= '&tag=' . urlencode($activeTag);
										}
										?>
										<?php if ($i === $page): ?>
											<span style="padding:8px 12px; background:#3e7ab6; color:white; border-radius:3px;">
												<?= $i ?>
											</span>
										<?php else: ?>
											<a href="<?= htmlspecialchars($pageUrl) ?>"
												style="padding:8px 12px; border:1px solid #ddd; border-radius:3px; text-decoration:none; color:#3e7ab6;">
												<?= $i ?>
											</a>
										<?php endif; ?>
									</li>
								<?php endfor; ?>
							</ul>
						</nav>
					<?php endif; ?>

				<?php endif; ?>
			</div><!-- /.col-md-8 -->

			<div class="col-md-4">
				<!-- Боковая панель -->
				<div style="background:#f9f9f9; padding:20px; border-radius:5px;">
					<h3 style="margin-bottom:15px; padding-bottom:10px; border-bottom:2px solid #3e7ab6;">Про Блог</h3>
					<p style="color:#666; line-height:1.6;">
						Читайте статьи про восстановление волос, правильный уход и применение инновационных средств Olaplex для
						здоровья ваших волос.
					</p>
				</div>

				<?php if ($pdo instanceof PDO):
					$tags = $pdo->query(
						'SELECT bt.slug, bt.name, COUNT(bpt.post_id) AS cnt
						 FROM blog_tags bt
						 LEFT JOIN blog_post_tags bpt ON bt.id = bpt.tag_id
						 LEFT JOIN blog_posts bp ON bpt.post_id = bp.id AND bp.status = "published"
						 GROUP BY bt.id, bt.slug, bt.name
						 HAVING cnt > 0
						 ORDER BY cnt DESC
						 LIMIT 20'
					)->fetchAll();
					if (!empty($tags)): ?>
						<div style="background:#f9f9f9; padding:20px; border-radius:5px; margin-top:20px;">
							<h3 style="margin-bottom:15px; padding-bottom:10px; border-bottom:2px solid #3e7ab6;">Теги</h3>
							<div style="display:flex; flex-wrap:wrap; gap:8px;">
								<?php foreach ($tags as $tag):
									$isActive = ($activeTag === (string)$tag['slug']);
								?>
									<a href="<?= $isActive ? '/blog' : '/blog?tag=' . htmlspecialchars((string)$tag['slug']) ?>" style="
										background: <?= $isActive ? '#3e7ab6' : 'white' ?>;
										color:       <?= $isActive ? '#fff'    : '#3e7ab6' ?>;
										border: 1px solid <?= $isActive ? '#3e7ab6' : '#ddd' ?>;
										padding: 6px 12px;
										border-radius: 20px;
										font-size: 13px;
										text-decoration: none;
									">
										#<?= htmlspecialchars((string)$tag['name']) ?>
										<span style="opacity:.7;">(<?= (int)$tag['cnt'] ?>)</span>
									</a>
								<?php endforeach; ?>
							</div>
						</div>
				<?php endif;
				endif; ?>

			</div><!-- /.col-md-4 -->
		</div><!-- /.row -->
	</div><!-- /.container -->

	<?php require __DIR__ . '/footer.php'; ?>
	<?php require __DIR__ . '/order_form.php'; ?>

	<!-- All JavaScript libraries -->
	<script defer src="/js/jquery-3.7.1.min.js"></script>
	<script defer src="/js/bootstrap.min.js"></script>
	<script defer src="/js/cart.js"></script>
	<script defer src="/js/cart-init.js"></script>
	<!-- Custom JavaScript -->
	<script defer src="/js/anchor-scroll.js"></script>
	<script defer src="/js/main.js?v=<?php echo date('Ymd', filemtime(__DIR__ . '/../js/main.js')); ?>"></script>
</body>

</html>
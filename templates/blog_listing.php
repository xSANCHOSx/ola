<?php

declare(strict_types=1);
require_once __DIR__ . '/../config/db.php';

$pdo = dev_db_connection();
$posts = [];
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 10;
$offset = ($page - 1) * $perPage;

if ($pdo instanceof PDO) {
	// Получить опубликованные посты
	$posts = $pdo->query(
		'SELECT * FROM blog_posts WHERE status = "published" ORDER BY published_at DESC LIMIT ' . $perPage . ' OFFSET ' . $offset
	)->fetchAll();

	// Получить общее количество
	$total = (int)$pdo->query('SELECT COUNT(*) as cnt FROM blog_posts WHERE status = "published"')->fetch()['cnt'];
	$totalPages = ceil($total / $perPage);
}

$pageTitle = 'Блог Olaplex - Статьи про Уход за Волосами';
$pageDescription = 'Читайте полезные статьи о восстановлении и уходе за волосами с помощью инновационных средств Olaplex';
?>
<!DOCTYPE html>
<html lang="ru">

<?php require __DIR__ . '/head.php'; ?>

<body>
	<?php require __DIR__ . '/header.php'; ?>

	<div class="container" style="padding-top: 40px; padding-bottom: 60px;">
		<div class="row">
			<div class="col-md-8">
				<h1 style="margin-bottom: 30px; color: #333; border-bottom: 2px solid #3e7ab6; padding-bottom: 15px;">Блог</h1>

				<?php if (empty($posts)): ?>
					<div style="padding: 30px; text-align: center; background: #f9f9f9; border-radius: 5px;">
						<p style="color: #666; font-size: 16px;">Пока постов нет. Следите за обновлениями!</p>
					</div>
				<?php else: ?>
					<?php foreach ($posts as $post): ?>

						<article style="margin-bottom: 40px; padding-bottom: 30px; border-bottom: 1px solid #eee;">
							<?php if (!empty($post['featured_image'])): ?>
								<img src="/<?= htmlspecialchars((string)$post['featured_image']) ?>"
									alt="<?= htmlspecialchars((string)$post['title']) ?>"
									style="width: 100%; max-height: 350px; object-fit: cover; border-radius: 5px; margin-bottom: 20px;">
							<?php endif; ?>

							<h2 style="margin-bottom: 10px; color: #333;">
								<a href="/blog/<?= htmlspecialchars((string)$post['slug']) ?>"
									style="color: #3e7ab6; text-decoration: none;">
									<?= htmlspecialchars((string)$post['title']) ?>
								</a>
							</h2>

							<div style="margin-bottom: 15px; color: #999; font-size: 14px;">
								<span>📅 <?php
													$ts = strtotime((string)$post['published_at']);
													echo $ts ? date('d.m.Y', $ts) : 'Дата невідома';
													?></span>
								<span style="margin-left: 20px;">👁 <?= (int)$post['views'] ?> переглядів</span>
							</div>

							<?php
							$excerpt = trim((string)($post['excerpt'] ?? ''));
							if (!empty($excerpt)):
							?>
								<p style="color: #666; line-height: 1.6; font-size: 15px; margin-bottom: 15px;">
									<?= htmlspecialchars($excerpt) ?>
								</p>
							<?php endif; ?>

							<a href="/blog/<?= htmlspecialchars((string)$post['slug']) ?>"
								style="color: #3e7ab6; font-weight: bold; text-decoration: none;">
								Подробнее →
							</a>
						</article>
					<?php endforeach; ?>

					<!-- Пагинация -->
					<?php if ($totalPages > 1): ?>
						<nav style="margin-top: 40px; text-align: center;">
							<ul style="list-style: none; padding: 0; display: inline-block;">
								<?php for ($i = 1; $i <= $totalPages; $i++): ?>
									<li style="display: inline-block; margin: 0 5px;">
										<?php if ($i === $page): ?>
											<span style="padding: 8px 12px; background: #3e7ab6; color: white; border-radius: 3px;">
												<?= $i ?>
											</span>
										<?php else: ?>
											<a href="/blog?page=<?= $i ?>"
												style="padding: 8px 12px; border: 1px solid #ddd; border-radius: 3px; text-decoration: none; color: #3e7ab6;">
												<?= $i ?>
											</a>
										<?php endif; ?>
									</li>
								<?php endfor; ?>
							</ul>
						</nav>
					<?php endif; ?>
				<?php endif; ?>
			</div>

			<div class="col-md-4">
				<!-- Боковая панель -->
				<div style="background: #f9f9f9; padding: 20px; border-radius: 5px;">
					<h3 style="margin-bottom: 15px; padding-bottom: 10px; border-bottom: 2px solid #3e7ab6;">Про Блог</h3>
					<p style="color: #666; line-height: 1.6;">
						Читайте статьи про восстановление волос, правильный уход и применение инновационных средств Olaplex для
						здоровья ваших волос.
					</p>
				</div>

				<?php
				// Теги
				if ($pdo instanceof PDO) {
					$tags = $pdo->query(
						'SELECT DISTINCT bt.slug, bt.name, COUNT(bpt.post_id) as count 
                     FROM blog_tags bt 
                     LEFT JOIN blog_post_tags bpt ON bt.id = bpt.tag_id 
                     LEFT JOIN blog_posts bp ON bpt.post_id = bp.id AND bp.status = "published"
                     GROUP BY bt.id 
                     ORDER BY count DESC 
                     LIMIT 15'
					)->fetchAll();

					if (!empty($tags)):
				?>
						<div style="background: #f9f9f9; padding: 20px; border-radius: 5px; margin-top: 20px;">
							<h3 style="margin-bottom: 15px; padding-bottom: 10px; border-bottom: 2px solid #3e7ab6;">Теги</h3>
							<div style="display: flex; flex-wrap: wrap; gap: 8px;">
								<?php foreach ($tags as $tag): ?>
									<a href="/blog?tag=<?= htmlspecialchars((string)$tag['slug']) ?>"
										style="background: white; border: 1px solid #ddd; padding: 6px 12px; border-radius: 20px; font-size: 13px; text-decoration: none; color: #3e7ab6;">
										#<?= htmlspecialchars((string)$tag['name']) ?>
										<span style="color: #999;">(<?= (int)$tag['count'] ?>)</span>
									</a>
								<?php endforeach; ?>
							</div>
						</div>
				<?php
					endif;
				}
				?>
			</div>
		</div>
	</div>

	<?php require __DIR__ . '/footer.php'; ?>

</body>

</html>
<?php

declare(strict_types=1);
require_once __DIR__ . '/../config/db.php';

$pdo = dev_db_connection();
$post = null;

// Безпечний вхід slug параметру
$slug = isset($_GET['slug']) ? preg_replace('/[^a-z0-9\-]/i', '', (string)$_GET['slug']) : '';

if (empty($slug) || strlen($slug) > 255) {
	header('HTTP/1.0 404 Not Found');
	require __DIR__ . '/../404.php';
	exit;
}

if ($pdo instanceof PDO) {
	// Використовуємо prepared statement для безпеки
	$stmt = $pdo->prepare('SELECT * FROM blog_posts WHERE slug = :slug AND status = "published" LIMIT 1');
	$stmt->execute(['slug' => $slug]);
	$post = $stmt->fetch();
}

if (!$post) {
	header('HTTP/1.0 404 Not Found');
	require __DIR__ . '/../404.php';
	exit;
}

// Кешування view counter (редис або file-based)
// Щоб не робити UPDATE при кожному перегляді
$viewCacheTTL = 3600; // 1 година
$viewCacheFile = __DIR__ . '/../log/view_cache_' . (int)$post['id'] . '.txt';

$shouldUpdateViews = true;
if (file_exists($viewCacheFile)) {
	$cacheTime = filemtime($viewCacheFile);
	if ($cacheTime && (time() - $cacheTime) < $viewCacheTTL) {
		$shouldUpdateViews = false;
	}
}

if ($shouldUpdateViews) {
	try {
		$pdo->prepare('UPDATE blog_posts SET views = views + 1 WHERE id = :id')
			->execute(['id' => $post['id']]);
		@touch($viewCacheFile);
	} catch (Exception $e) {
		dev_log_runtime('View counter update failed: ' . $e->getMessage());
	}
}

// Отримати теги для цього поста
$tags = [];
if ($pdo instanceof PDO) {
	$tagsStmt = $pdo->prepare('
        SELECT bt.name, bt.slug FROM blog_tags bt 
        JOIN blog_post_tags bpt ON bt.id = bpt.tag_id 
        WHERE bpt.post_id = :id
        ORDER BY bt.name ASC
    ');
	$tagsStmt->execute(['id' => $post['id']]);
	$tags = $tagsStmt->fetchAll();
}

// SEO
$pageTitle = !empty($post['seo_title']) ? $post['seo_title'] : $post['title'];
$pageDescription = !empty($post['seo_description']) ? $post['seo_description'] : (empty($post['excerpt']) ? 'Стаття на блогу' : $post['excerpt']);

// Отримати сусідні пости для навігації
$prevPost = null;
$nextPost = null;
if ($pdo instanceof PDO) {
	$prev = $pdo->prepare('
        SELECT id, title, slug FROM blog_posts 
        WHERE status = "published" AND published_at < :published_at 
        ORDER BY published_at DESC LIMIT 1
    ');
	$prev->execute(['published_at' => $post['published_at']]);
	$prevPost = $prev->fetch();

	$next = $pdo->prepare('
        SELECT id, title, slug FROM blog_posts 
        WHERE status = "published" AND published_at > :published_at 
        ORDER BY published_at ASC LIMIT 1
    ');
	$next->execute(['published_at' => $post['published_at']]);
	$nextPost = $next->fetch();
}

// Функция для безпечного виведення path до файлів
function getSafeImagePath(string $path): ?string
{
	// Перевірка на path traversal атаки
	if (strpos($path, '..') !== false || strpos($path, "\0") !== false) {
		return null;
	}

	// Дозволяємо лише файли з дозволених папок
	if (!preg_match('#^data/uploads/blog/[a-z0-9]+\.(jpg|jpeg|png|webp|gif)$#i', $path)) {
		return null;
	}

	// Перевіримо що файл реально існує
	$fullPath = __DIR__ . '/../' . $path;
	if (!file_exists($fullPath) || !is_file($fullPath)) {
		return null;
	}

	return '/' . htmlspecialchars($path, ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="uk">

<?php require __DIR__ . '/head.php'; ?>

<body>
	<?php require __DIR__ . '/header.php'; ?>

	<div class="container" style="padding-top: 40px; padding-bottom: 60px;">
		<div class="row">
			<div class="col-md-8">
				<!-- Хлібна крошка -->
				<nav style="margin-bottom: 20px;">
					<a href="/" style="color: #3e7ab6; text-decoration: none;">Головна</a> →
					<a href="/blog" style="color: #3e7ab6; text-decoration: none;">Блог</a> →
					<span style="color: #999;"><?= htmlspecialchars((string)$post['title'], ENT_QUOTES, 'UTF-8') ?></span>
				</nav>

				<article>
					<h1 style="margin-bottom: 15px; color: #333;">
						<?= htmlspecialchars((string)$post['title'], ENT_QUOTES, 'UTF-8') ?>
					</h1>

					<div
						style="margin-bottom: 25px; padding-bottom: 20px; border-bottom: 1px solid #eee; font-size: 14px; color: #999;">
						<span>📅 Опубліковано:
							<?= htmlspecialchars(date('d.m.Y', strtotime((string)$post['published_at'])), ENT_QUOTES, 'UTF-8') ?></span>
						<span style="margin-left: 20px;">👁 Переглядів: <?= (int)$post['views'] ?></span>
					</div>

					<!-- Головне зображення -->
					<?php
					if (!empty($post['featured_image'])) {
						$imagePath = getSafeImagePath((string)$post['featured_image']);
						if ($imagePath):
					?>
							<img src="<?= $imagePath ?>" alt="<?= htmlspecialchars((string)$post['title'], ENT_QUOTES, 'UTF-8') ?>"
								style="width: 100%; max-height: 500px; object-fit: cover; border-radius: 5px; margin-bottom: 30px;">
					<?php
						endif;
					}
					?>

					<!-- Вміст статті (вже HTML з TinyMCE) -->
					<div style="font-size: 16px; line-height: 1.8; color: #333; margin-bottom: 40px;">
						<?php
						// TinyMCE генерує HTML, тому не еskape, але переконуємося що нема потенційно шкідливого
						// В production потрібен HTML purifier (HTMLPurifier lib)
						echo $post['content'];
						?>
					</div>

					<!-- Теги -->
					<?php if (!empty($tags)): ?>
						<div
							style="padding-top: 20px; padding-bottom: 30px; border-top: 1px solid #eee; border-bottom: 1px solid #eee; margin-bottom: 30px;">
							<div style="margin-bottom: 10px; color: #999; font-size: 14px;">Теги:</div>
							<div style="display: flex; flex-wrap: wrap; gap: 8px;">
								<?php foreach ($tags as $tag): ?>
									<a href="/blog?tag=<?= htmlspecialchars((string)$tag['slug'], ENT_QUOTES, 'UTF-8') ?>"
										style="background: #f0f0f0; padding: 6px 12px; border-radius: 20px; font-size: 13px; text-decoration: none; color: #3e7ab6;">
										#<?= htmlspecialchars((string)$tag['name'], ENT_QUOTES, 'UTF-8') ?>
									</a>
								<?php endforeach; ?>
							</div>
						</div>
					<?php endif; ?>

					<!-- Навігація між постами -->
					<div
						style="display: flex; justify-content: space-between; align-items: center; padding: 20px 0; border-top: 1px solid #eee;">
						<?php if ($prevPost): ?>
							<a href="/blog/<?= htmlspecialchars((string)$prevPost['slug'], ENT_QUOTES, 'UTF-8') ?>"
								style="color: #3e7ab6; text-decoration: none; max-width: 45%;">
								← <?= htmlspecialchars((string)$prevPost['title'], ENT_QUOTES, 'UTF-8') ?>
							</a>
						<?php else: ?>
							<span></span>
						<?php endif; ?>

						<a href="/blog" style="color: #3e7ab6; text-decoration: none;">Всі пости</a>

						<?php if ($nextPost): ?>
							<a href="/blog/<?= htmlspecialchars((string)$nextPost['slug'], ENT_QUOTES, 'UTF-8') ?>"
								style="color: #3e7ab6; text-decoration: none; max-width: 45%; text-align: right;">
								<?= htmlspecialchars((string)$nextPost['title'], ENT_QUOTES, 'UTF-8') ?> →
							</a>
						<?php else: ?>
							<span></span>
						<?php endif; ?>
					</div>
				</article>
			</div>

			<div class="col-md-4">
				<!-- Боковая панель з іншими постами -->
				<div style="background: #f9f9f9; padding: 20px; border-radius: 5px;">
					<h3 style="margin-bottom: 15px; padding-bottom: 10px; border-bottom: 2px solid #3e7ab6;">Інші пості</h3>
					<?php
					if ($pdo instanceof PDO) {
						$related = $pdo->prepare('
                        SELECT id, title, slug, published_at FROM blog_posts 
                        WHERE status = "published" AND id != :id 
                        ORDER BY published_at DESC LIMIT 5
                    ');
						$related->execute(['id' => $post['id']]);
						$relatedPosts = $related->fetchAll();

						if (!empty($relatedPosts)):
							foreach ($relatedPosts as $rel):
					?>
								<div style="padding-bottom: 15px; margin-bottom: 15px; border-bottom: 1px solid #ddd;">
									<a href="/blog/<?= htmlspecialchars((string)$rel['slug'], ENT_QUOTES, 'UTF-8') ?>"
										style="color: #3e7ab6; text-decoration: none; font-weight: 500;">
										<?= htmlspecialchars((string)$rel['title'], ENT_QUOTES, 'UTF-8') ?>
									</a>
									<div style="color: #999; font-size: 12px; margin-top: 5px;">
										<?= htmlspecialchars(date('d.m.Y', strtotime((string)$rel['published_at'])), ENT_QUOTES, 'UTF-8') ?>
									</div>
								</div>
					<?php
							endforeach;
						endif;
					}
					?>
				</div>

				<!-- About блога -->
				<div style="background: #f9f9f9; padding: 20px; border-radius: 5px; margin-top: 20px;">
					<h3 style="margin-bottom: 15px; padding-bottom: 10px; border-bottom: 2px solid #3e7ab6;">Про Блог</h3>
					<p style="color: #666; line-height: 1.6; font-size: 14px;">
						Статті про відновлення та правильний догляд за волоссям з інноваційними засобами Olaplex.
					</p>
					<a href="/blog" style="color: #3e7ab6; text-decoration: none; font-weight: bold;">Всі пости →</a>
				</div>
			</div>
		</div>
	</div>

	<?php require __DIR__ . '/footer.php'; ?>

</body>

</html>
<?php

declare(strict_types=1);
require_once __DIR__ . '/../config/db.php';

$pdo = dev_db_connection();
$post = null;

// Безопасный ввод slug параметра
$slug = isset($_GET['slug']) ? preg_replace('/[^a-z0-9\-]/i', '', (string)$_GET['slug']) : '';

if (empty($slug) || strlen($slug) > 255) {
	header('HTTP/1.0 404 Not Found');
	require __DIR__ . '/../404.php';
	exit;
}

if ($pdo instanceof PDO) {
	// Используем prepared statement для безопасности
	$stmt = $pdo->prepare('SELECT * FROM blog_posts WHERE slug = :slug AND status = "published" LIMIT 1');
	$stmt->execute(['slug' => $slug]);
	$post = $stmt->fetch();
}

if (!$post) {
	header('HTTP/1.0 404 Not Found');
	require __DIR__ . '/../404.php';
	exit;
}

// Кеширование view counter (redis или file-based)
// Чтобы не делать UPDATE при каждом просмотре
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

// Получить теги для этого поста
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
$pageDescription = !empty($post['seo_description']) ? $post['seo_description'] : (empty($post['excerpt']) ? 'Статья в блоге' : $post['excerpt']);

// Получить соседние посты для навигации
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

// Функция для безопасного вывода path до файлов
function getSafeImagePath(string $path): ?string
{
	// Проверка на path traversal атаки
	if (strpos($path, '..') !== false || strpos($path, "\0") !== false) {
		return null;
	}

	// Разрешаем только файлы из разрешённых папок
	if (!preg_match('#^data/uploads/blog/[a-z0-9]+\.(jpg|jpeg|png|webp|gif)$#i', $path)) {
		return null;
	}

	// Проверим что файл действительно существует
	$fullPath = __DIR__ . '/../' . $path;
	if (!file_exists($fullPath) || !is_file($fullPath)) {
		return null;
	}

	return '/' . htmlspecialchars($path, ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="ru">

<?php require __DIR__ . '/head.php'; ?>

<style>
/* Відступ для фіксованої шапки (header.sticky = 50px) */
.blog-single-wrap {
	padding-top: 80px;
	padding-bottom: 60px;
}

.blog-article {
	max-width: 860px;
	margin: 0 auto;
}

.blog-breadcrumb {
	margin-bottom: 20px;
	font-size: 13px;
	color: #999;
}

.blog-breadcrumb a {
	color: #3e7ab6;
	text-decoration: none;
}

.blog-breadcrumb a:hover {
	text-decoration: underline;
}

.blog-title {
	font-size: 2rem;
	font-weight: 700;
	color: #222;
	margin-bottom: 12px;
	line-height: 1.3;
}

.blog-meta {
	display: flex;
	align-items: center;
	gap: 20px;
	font-size: 13px;
	color: #888;
	margin-bottom: 24px;
	padding-bottom: 18px;
	border-bottom: 1px solid #eee;
}

/* Обкладинка — перший елемент після мета, на всю ширину */
.blog-featured-image {
	width: 100%;
	max-height: 480px;
	object-fit: cover;
	border-radius: 8px;
	margin-bottom: 32px;
	display: block;
}

.blog-content {
	font-size: 16px;
	line-height: 1.85;
	color: #333;
	margin-bottom: 40px;
}

.blog-content img {
	max-width: 100%;
	height: auto;
	border-radius: 5px;
}

.blog-content h2 {
	margin-top: 2em;
}

.blog-content h3 {
	margin-top: 1.5em;
}

.blog-tags {
	padding: 20px 0;
	border-top: 1px solid #eee;
	border-bottom: 1px solid #eee;
	margin-bottom: 30px;
}

.blog-tags__label {
	font-size: 13px;
	color: #999;
	margin-bottom: 10px;
}

.blog-tags__list {
	display: flex;
	flex-wrap: wrap;
	gap: 8px;
}

.blog-tags__item {
	background: #f0f0f0;
	padding: 6px 14px;
	border-radius: 20px;
	font-size: 13px;
	text-decoration: none;
	color: #3e7ab6;
	transition: background .2s;
}

.blog-tags__item:hover {
	background: #dde8f5;
}

.blog-nav {
	display: flex;
	justify-content: space-between;
	align-items: center;
	padding: 20px 0;
	border-top: 1px solid #eee;
	gap: 20px;
}

.blog-nav a {
	color: #3e7ab6;
	text-decoration: none;
	max-width: 42%;
}

.blog-nav a:hover {
	text-decoration: underline;
}

.blog-nav__center {
	white-space: nowrap;
}

@media (max-width: 576px) {
	.blog-single-wrap {
		padding-top: 70px;
	}

	.blog-title {
		font-size: 1.5rem;
	}

	.blog-nav {
		flex-direction: column;
		gap: 10px;
		text-align: center;
	}

	.blog-nav a {
		max-width: 100%;
	}
}
</style>

<body>
	<?php require __DIR__ . '/header.php'; ?>

	<div class="container blog-single-wrap">
		<div class="row">
			<!-- col-12 — без сайдбара, на всю ширину -->
			<div class="col-12">
				<div class="blog-article">

					<!-- Хлебная крошка -->
					<nav class="blog-breadcrumb">
						<a href="/">Главная</a> →
						<a href="/blog">Блог</a> →
						<span><?= htmlspecialchars((string)$post['title'], ENT_QUOTES, 'UTF-8') ?></span>
					</nav>

					<article>
						<h1 class="blog-title">
							<?= htmlspecialchars((string)$post['title'], ENT_QUOTES, 'UTF-8') ?>
						</h1>

						<!-- Мета: дата + перегляди -->
						<div class="blog-meta">

						</div>

						<!-- Обкладинка — одразу після заголовка та мета -->
						<?php
						$imagePath = null;
						if (!empty($post['featured_image'])) {
							$imagePath = getSafeImagePath((string)$post['featured_image']);
						}
						if ($imagePath):
						?>
						<img src="<?= $imagePath ?>" alt="<?= htmlspecialchars((string)$post['title'], ENT_QUOTES, 'UTF-8') ?>"
							class="blog-featured-image">
						<?php endif; ?>

						<!-- Зміст статті -->
						<div class="blog-content">
							<?= $post['content'] ?>
						</div>

						<!-- Теги -->
						<?php if (!empty($tags)): ?>
						<div class="blog-tags">
							<div class="blog-tags__label">Теги:</div>
							<div class="blog-tags__list">
								<?php foreach ($tags as $tag): ?>
								<a href="/blog?tag=<?= htmlspecialchars((string)$tag['slug'], ENT_QUOTES, 'UTF-8') ?>"
									class="blog-tags__item">
									#<?= htmlspecialchars((string)$tag['name'], ENT_QUOTES, 'UTF-8') ?>
								</a>
								<?php endforeach; ?>
							</div>
						</div>
						<?php endif; ?>

						<!-- Навигация між постами -->
						<div class="blog-nav">
							<?php if ($prevPost): ?>
							<a href="/blog/<?= htmlspecialchars((string)$prevPost['slug'], ENT_QUOTES, 'UTF-8') ?>">
								← <?= htmlspecialchars((string)$prevPost['title'], ENT_QUOTES, 'UTF-8') ?>
							</a>
							<?php else: ?><span></span><?php endif; ?>

							<a href="/blog" class="blog-nav__center">Все посты</a>

							<?php if ($nextPost): ?>
							<a href="/blog/<?= htmlspecialchars((string)$nextPost['slug'], ENT_QUOTES, 'UTF-8') ?>"
								style="text-align:right;">
								<?= htmlspecialchars((string)$nextPost['title'], ENT_QUOTES, 'UTF-8') ?> →
							</a>
							<?php else: ?><span></span><?php endif; ?>
						</div>
					</article>

				</div><!-- /.blog-article -->
			</div><!-- /.col-12 -->
		</div><!-- /.row -->
	</div><!-- /.container -->

	<?php require __DIR__ . '/footer.php'; ?>

</body>

</html>
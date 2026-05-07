<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/db.php';

$pdo = dev_db_connection();
$post = null;
$slug = isset($_GET['slug']) ? preg_replace('/[^a-z0-9\-]/i', '', (string)$_GET['slug']) : '';

if (!empty($slug) && $pdo instanceof PDO) {
    $stmt = $pdo->prepare('SELECT * FROM blog_posts WHERE slug = :slug AND status = "published"');
    $stmt->execute(['slug' => $slug]);
    $post = $stmt->fetch();
    
    if ($post) {
        // Увеличить счетчик просмотров
        $pdo->prepare('UPDATE blog_posts SET views = views + 1 WHERE id = :id')->execute(['id' => $post['id']]);
    }
}

if (!$post) {
    header('HTTP/1.0 404 Not Found');
    require __DIR__ . '/../404.php';
    exit;
}

// Получить теги для этого поста
$tags = [];
if ($pdo instanceof PDO) {
    $tagsStmt = $pdo->prepare('
        SELECT bt.name, bt.slug FROM blog_tags bt 
        JOIN blog_post_tags bpt ON bt.id = bpt.tag_id 
        WHERE bpt.post_id = :id
    ');
    $tagsStmt->execute(['id' => $post['id']]);
    $tags = $tagsStmt->fetchAll();
}

// SEO
$pageTitle = !empty($post['seo_title']) ? $post['seo_title'] : $post['title'];
$pageDescription = !empty($post['seo_description']) ? $post['seo_description'] : $post['excerpt'];

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
?>
<!DOCTYPE html>
<html lang="ru">

<?php require __DIR__ . '/head.php'; ?>

<body>
	<?php require __DIR__ . '/header.php'; ?>

	<div class="container" style="padding-top: 40px; padding-bottom: 60px;">
		<div class="row">
			<div class="col-md-8">
				<!-- Хлебная крошка -->
				<nav style="margin-bottom: 20px;">
					<a href="/" style="color: #3e7ab6; text-decoration: none;">Главная</a> →
					<a href="/blog" style="color: #3e7ab6; text-decoration: none;">Блог</a> →
					<span style="color: #999;"><?= htmlspecialchars((string)$post['title']) ?></span>
				</nav>

				<article>
					<h1 style="margin-bottom: 15px; color: #333;">
						<?= htmlspecialchars((string)$post['title']) ?>
					</h1>

					<div
						style="margin-bottom: 25px; padding-bottom: 20px; border-bottom: 1px solid #eee; font-size: 14px; color: #999;">
					<span>📅 Опубліковано: <?php 
						$ts = strtotime((string)$post['published_at']);
						echo $ts ? date('d.m.Y', $ts) : 'Дата невідома';
					?></span>
						<span style="margin-left: 20px;">✏️ Автор: Адміністратор</span>
						<?php endif; ?>
					</div>

					<!-- Главное изображение -->
					<?php if (!empty($post['featured_image'])): ?>
					<img src="/<?= htmlspecialchars((string)$post['featured_image']) ?>"
						alt="<?= htmlspecialchars((string)$post['title']) ?>"
						style="width: 100%; max-height: 500px; object-fit: cover; border-radius: 5px; margin-bottom: 30px;">
					<?php endif; ?>

					<!-- Содержание -->
					<div style="font-size: 16px; line-height: 1.8; color: #333; margin-bottom: 40px;">
						<?= $post['content'] ?>
					</div>

					<!-- Теги -->
					<?php if (!empty($tags)): ?>
					<div
						style="padding-top: 20px; padding-bottom: 30px; border-top: 1px solid #eee; border-bottom: 1px solid #eee; margin-bottom: 30px;">
						<div style="margin-bottom: 10px; color: #999; font-size: 14px;">Теги:</div>
						<div style="display: flex; flex-wrap: wrap; gap: 8px;">
							<?php foreach ($tags as $tag): ?>
							<a href="/blog?tag=<?= htmlspecialchars((string)$tag['slug']) ?>"
								style="background: #f0f0f0; padding: 6px 12px; border-radius: 20px; font-size: 13px; text-decoration: none; color: #3e7ab6;">
								#<?= htmlspecialchars((string)$tag['name']) ?>
							</a>
							<?php endforeach; ?>
						</div>
					</div>
					<?php endif; ?>

					<!-- Навигация между постами -->
					<div
						style="display: flex; justify-content: space-between; align-items: center; padding: 20px 0; border-top: 1px solid #eee;">
						<?php if ($prevPost): ?>
						<a href="/blog/<?= htmlspecialchars((string)$prevPost['slug']) ?>"
							style="color: #3e7ab6; text-decoration: none; max-width: 45%;">
							← <?= htmlspecialchars((string)$prevPost['title']) ?>
						</a>
						<?php else: ?>
						<span></span>
						<?php endif; ?>

						<a href="/blog" style="color: #3e7ab6; text-decoration: none;">Все посты</a>

						<?php if ($nextPost): ?>
						<a href="/blog/<?= htmlspecialchars((string)$nextPost['slug']) ?>"
							style="color: #3e7ab6; text-decoration: none; max-width: 45%; text-align: right;">
							<?= htmlspecialchars((string)$nextPost['title']) ?> →
						</a>
						<?php else: ?>
						<span></span>
						<?php endif; ?>
					</div>
				</article>
			</div>

			<div class="col-md-4">
				<!-- Боковая панель с похожими постами -->
				<div style="background: #f9f9f9; padding: 20px; border-radius: 5px;">
					<h3 style="margin-bottom: 15px; padding-bottom: 10px; border-bottom: 2px solid #3e7ab6;">Inший Пості</h3>
					<?php 
                if ($pdo instanceof PDO) {
                    $related = $pdo->query(
                        'SELECT id, title, slug, published_at FROM blog_posts 
                         WHERE status = "published" AND id != ' . (int)$post['id'] . ' 
                         ORDER BY published_at DESC LIMIT 5'
                    )->fetchAll();
                    
                    if (!empty($related)):
                        foreach ($related as $rel):
                ?>
					<div style="padding-bottom: 15px; margin-bottom: 15px; border-bottom: 1px solid #ddd;">
						<a href="/blog/<?= htmlspecialchars((string)$rel['slug']) ?>"
							style="color: #3e7ab6; text-decoration: none; font-weight: 500;">
							<?= htmlspecialchars((string)$rel['title']) ?>
						</a>
						<div style="color: #999; font-size: 12px; margin-top: 5px;">
							<?= date('d.m.Y', strtotime((string)$rel['published_at'])) ?>
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
						Статьи про восстановлення і правильний догляд за волоссям з інноваційними засобами Olaplex.
					</p>
					<a href="/blog" style="color: #3e7ab6; text-decoration: none; font-weight: bold;">Всі пости →</a>
				</div>
			</div>
		</div>
	</div>

	<?php require __DIR__ . '/footer.php'; ?>

</body>

</html>
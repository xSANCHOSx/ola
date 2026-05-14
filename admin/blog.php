<?php

declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';
admin_require_auth();
$pdo = dev_db_connection();
$user = admin_current_user();

if (!$pdo instanceof PDO) {
	die('Database connection failed');
}

// POST handler
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	if (!validate_csrf_token()) {
		dev_log_security('CSRF_VALIDATION_FAILED', ['page' => 'admin/blog']);
		http_response_code(403);
		exit('CSRF check failed');
	}

	$id = (int)($_POST['id'] ?? 0);
	$action = trim((string)($_POST['action'] ?? 'save'));

	// Delete post
	if ($action === 'delete' && $id > 0) {
		try {
			$pdo->prepare('DELETE FROM blog_post_tags WHERE post_id = :id')->execute(['id' => $id]);
			$pdo->prepare('DELETE FROM blog_posts WHERE id = :id')->execute(['id' => $id]);
			dev_log_security('BLOG_POST_DELETED', ['id' => $id, 'user' => $user['username'] ?? 'unknown']);
			header('Location: /admin/blog.php?msg=deleted');
			exit;
		} catch (Exception $e) {
			dev_log_runtime('Blog delete error: ' . $e->getMessage());
			header('Location: /admin/blog.php?msg=error&edit=' . $id);
			exit;
		}
	}

	// Save/Update post
	if ($action === 'save') {
		$title = trim((string)($_POST['title'] ?? ''));
		$slug = trim((string)($_POST['slug'] ?? ''));
		$content = (string)($_POST['content'] ?? '');
		$excerpt = trim((string)($_POST['excerpt'] ?? ''));
		$featured_image = trim((string)($_POST['featured_image'] ?? ''));
		$status = trim((string)($_POST['status'] ?? 'draft'));
		$seo_title = trim((string)($_POST['seo_title'] ?? ''));
		$seo_description = trim((string)($_POST['seo_description'] ?? ''));

		// Валидация
		if (empty($title) || empty($content)) {
			dev_log_security('BLOG_VALIDATION_FAILED', ['reason' => 'empty_required_fields']);
			header('Location: /admin/blog.php?msg=error&edit=' . $id);
			exit;
		}

		// Title max length
		if (strlen($title) > 255) {
			header('Location: /admin/blog.php?msg=error&edit=' . $id);
			exit;
		}

		// Auto-generate slug if empty
		if (empty($slug)) {
			$slug = generateSlug($title);
		} else {
			// Очистить slug - только буквы, цифры, дефис
			$slug = preg_replace('/[^a-z0-9\-]/i', '', $slug);
			$slug = preg_replace('/\-+/', '-', $slug);
			$slug = strtolower(trim($slug, '-'));
		}

		if (empty($slug)) {
			header('Location: /admin/blog.php?msg=error&edit=' . $id);
			exit;
		}

		// Проверка дублей slug (кроме текущего поста)
		$slugCheck = $pdo->prepare('SELECT id FROM blog_posts WHERE slug = :slug AND id != :id');
		$slugCheck->execute(['slug' => $slug, 'id' => $id ?: 0]);
		if ($slugCheck->rowCount() > 0) {
			dev_log_security('BLOG_SLUG_DUPLICATE', ['slug' => $slug]);
			header('Location: /admin/blog.php?msg=error&edit=' . $id . '&reason=slug_exists');
			exit;
		}

		// Валидация image path -防止 path traversal
		if (!empty($featured_image)) {
			if (strpos($featured_image, '..') !== false || strpos($featured_image, '/') === 0) {
				$featured_image = '';
			}
		}

		// Валидация status
		if (!in_array($status, ['draft', 'published'], true)) {
			$status = 'draft';
		}

		// Handle image upload
		if (!empty($_FILES['image_upload']['tmp_name']) && is_uploaded_file($_FILES['image_upload']['tmp_name'])) {
			$ext = strtolower(pathinfo((string)$_FILES['image_upload']['name'], PATHINFO_EXTENSION));
			$allowed = ['jpg', 'jpeg', 'png', 'webp', 'gif'];

			if (!in_array($ext, $allowed, true)) {
				dev_log_security('BLOG_INVALID_IMAGE_TYPE', ['ext' => $ext]);
				header('Location: /admin/blog.php?msg=error&edit=' . $id . '&reason=invalid_image');
				exit;
			}

			if ((int)$_FILES['image_upload']['size'] > 10 * 1024 * 1024) {
				header('Location: /admin/blog.php?msg=error&edit=' . $id . '&reason=image_too_large');
				exit;
			}

			// Дополнительная проверка - это реально изображение
			$finfo = getimagesize($_FILES['image_upload']['tmp_name']);
			if ($finfo === false) {
				dev_log_security('BLOG_INVALID_IMAGE_FILE', []);
				header('Location: /admin/blog.php?msg=error&edit=' . $id);
				exit;
			}

			$dir = __DIR__ . '/../data/uploads/blog';
			if (!is_dir($dir)) {
				mkdir($dir, 0755, true);
			}

			$fileName = bin2hex(random_bytes(12)) . '.' . $ext;
			$target = $dir . '/' . $fileName;
			if (move_uploaded_file($_FILES['image_upload']['tmp_name'], $target)) {
				$featured_image = 'data/uploads/blog/' . $fileName;
			}
		}

		try {
			if ($id > 0) {
				// Update
				$sql = 'UPDATE blog_posts SET 
                    title=:title, 
                    slug=:slug, 
                    content=:content, 
                    excerpt=:excerpt, 
                    featured_image=:featured_image, 
                    status=:status, 
                    seo_title=:seo_title, 
                    seo_description=:seo_description 
                    WHERE id=:id';

				$pdo->prepare($sql)->execute([
					'id' => $id,
					'title' => $title,
					'slug' => $slug,
					'content' => $content,
					'excerpt' => $excerpt,
					'featured_image' => $featured_image,
					'status' => $status,
					'seo_title' => $seo_title,
					'seo_description' => $seo_description,
				]);

				dev_log_security('BLOG_POST_UPDATED', ['id' => $id, 'user' => $user['username'] ?? 'unknown']);
			} else {
				// Insert
				$published_at = null;
				if ($status === 'published') {
					$published_at = date('Y-m-d H:i:s');
				}

				$sql = 'INSERT INTO blog_posts 
                    (title, slug, content, excerpt, featured_image, author_id, status, seo_title, seo_description, published_at) 
                    VALUES (:title, :slug, :content, :excerpt, :featured_image, :author_id, :status, :seo_title, :seo_description, :published_at)';

				$pdo->prepare($sql)->execute([
					'title' => $title,
					'slug' => $slug,
					'content' => $content,
					'excerpt' => $excerpt,
					'featured_image' => $featured_image,
					'author_id' => $user['id'] ?? null,
					'status' => $status,
					'seo_title' => $seo_title,
					'seo_description' => $seo_description,
					'published_at' => $published_at,
				]);

				$id = (int)$pdo->lastInsertId();
				dev_log_security('BLOG_POST_CREATED', ['id' => $id, 'user' => $user['username'] ?? 'unknown']);
			}

			// Handle tags
			$tags = array_filter(array_map('trim', explode(',', (string)($_POST['tags'] ?? ''))));
			if (!empty($tags)) {
				$pdo->prepare('DELETE FROM blog_post_tags WHERE post_id = :id')->execute(['id' => $id]);

				foreach ($tags as $tag) {
					if (strlen($tag) > 100) continue; // Skip too long tags

					$slug = preg_replace('/[^a-z0-9\s\-]/i', '', $tag);
					$slug = preg_replace('/\s+/', '-', strtolower($slug));

					// Insert or get existing tag
					$tagStmt = $pdo->prepare('INSERT IGNORE INTO blog_tags (name, slug) VALUES (:name, :slug)');
					$tagStmt->execute(['name' => $tag, 'slug' => $slug]);

					$getTag = $pdo->prepare('SELECT id FROM blog_tags WHERE slug = :slug');
					$getTag->execute(['slug' => $slug]);
					$tagId = (int)$getTag->fetch()['id'];

					if ($tagId > 0) {
						$pdo->prepare('INSERT IGNORE INTO blog_post_tags (post_id, tag_id) VALUES (:post_id, :tag_id)')
							->execute(['post_id' => $id, 'tag_id' => $tagId]);
					}
				}
			}

			header('Location: /admin/blog.php?msg=saved');
			exit;
		} catch (Exception $e) {
			dev_log_runtime('Blog save error: ' . $e->getMessage());
			header('Location: /admin/blog.php?msg=error&edit=' . $id);
			exit;
		}
	}
}

// Fetch posts
$posts = [];
$stmt = $pdo->query('SELECT * FROM blog_posts ORDER BY created_at DESC LIMIT 200');
if ($stmt instanceof PDOStatement) {
	$posts = $stmt->fetchAll();
}

// Fetch single post for editing
$edit = null;
if (isset($_GET['edit'])) {
	$editId = (int)$_GET['edit'];
	$edit = []; // порожній масив = новий пост; null = список без форми
	if ($editId > 0) {
		$editStmt = $pdo->prepare('SELECT * FROM blog_posts WHERE id = :id');
		$editStmt->execute(['id' => $editId]);
		$fetched = $editStmt->fetch();
		if ($fetched !== false) {
			$edit = $fetched;
		}
	}
	if (!empty($edit['id'])) {
		$tagsStmt = $pdo->prepare('SELECT bt.name FROM blog_tags bt 
            JOIN blog_post_tags bpt ON bt.id = bpt.tag_id 
            WHERE bpt.post_id = :id');
		$tagsStmt->execute(['id' => (int)$edit['id']]);
		$edit['tags'] = implode(', ', array_column($tagsStmt->fetchAll(), 'name'));
	}
}

$msg = '';
$reason = '';
if (isset($_GET['msg'])) {
	$msgCode = (string)$_GET['msg'];
	$messages = [
		'saved' => 'Пост успешно сохранён',
		'deleted' => 'Пост удалён',
		'error' => 'Ошибка при сохранении'
	];
	$msg = $messages[$msgCode] ?? '';

	if (isset($_GET['reason'])) {
		$reasons = [
			'slug_exists' => ' (slug уже существует)',
			'invalid_image' => ' (неверный тип изображения)',
			'image_too_large' => ' (изображение слишком большое)',
		];
		$reason = $reasons[(string)$_GET['reason']] ?? '';
	}
}

// Helper function для генерации slug
function generateSlug(string $text): string
{
	$text = strtolower($text);
	$text = preg_replace('/[^a-z0-9\s\-]/u', '', $text);
	$text = preg_replace('/\s+/', '-', $text);
	return trim($text, '-');
}
?>
<!doctype html>
<html lang="ru">

<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Админка - Блог</title>
	<link rel="stylesheet" href="/css/bootstrap.min.css">
	<!-- CKEditor 5 -->
	<script src="https://cdn.ckeditor.com/ckeditor5/41.1.0/classic/ckeditor.js"></script>
	<style>
	.form-group {
		margin-bottom: 15px;
	}

	.form-group label {
		font-weight: bold;
		margin-bottom: 5px;
		display: block;
	}

	.ck-content {
		min-height: 300px;
	}

	.alert {
		padding: 10px;
		margin-bottom: 15px;
		border-radius: 4px;
	}

	.alert-success {
		background: #d4edda;
		color: #155724;
		border: 1px solid #c3e6cb;
	}

	.alert-error {
		background: #f8d7da;
		color: #721c24;
		border: 1px solid #f5c6cb;
	}

	table {
		word-break: break-word;
	}

	.slug-input {
		font-family: monospace;
		font-size: 12px;
	}

	/* Image upload widget */
	.image-preview-container {
		position: relative;
		display: inline-block;
		width: 200px;
	}

	.image-placeholder {
		width: 200px;
		height: 200px;
		background: #e9ecef;
		border: 2px dashed #dee2e6;
		border-radius: 8px;
		display: flex;
		align-items: center;
		justify-content: center;
		cursor: pointer;
		transition: all 0.3s;
		color: #999;
		font-size: 14px;
		overflow: hidden;
	}

	.image-placeholder:hover {
		border-color: #adb5bd;
		background: #dee2e6;
	}

	.image-preview-img {
		width: 100%;
		height: 100%;
		object-fit: cover;
		border-radius: 6px;
		display: block;
	}

	.btn-remove-image {
		position: absolute;
		top: 4px;
		right: 4px;
		background: #dc3545;
		border: none;
		border-radius: 50%;
		width: 28px;
		height: 28px;
		padding: 0;
		display: none;
		align-items: center;
		justify-content: center;
		cursor: pointer;
		transition: all 0.2s;
		z-index: 20;
		box-shadow: 0 2px 6px rgba(0, 0, 0, 0.2);
	}

	.btn-remove-image:hover {
		background: #c82333;
		transform: scale(1.1);
	}

	.btn-remove-image.show {
		display: flex;
	}

	.btn-remove-image svg {
		width: 16px;
		height: 16px;
		display: block;
		stroke: white;
		stroke-width: 2.5;
		stroke-linecap: round;
		stroke-linejoin: round;
	}

	.image-upload-input {
		display: none !important;
	}
	</style>
</head>

<body>
	<div class="container">
		<?php require __DIR__ . '/_nav.php'; ?>

		<h2>Управление блогом</h2>

		<?php if ($msg): ?>
		<div class="alert alert-<?= strpos($msg, 'Ошибка') !== false ? 'error' : 'success' ?>">
			<?= admin_h($msg . $reason) ?>
		</div>
		<?php endif; ?>

		<form method="post" enctype="multipart/form-data"
			style="background:#f9f9f9; padding:20px; border:1px solid #ddd; margin-bottom:30px;">
			<input type="hidden" name="id" value="<?= admin_h((string)($edit['id'] ?? '')) ?>">
			<input type="hidden" name="action" value="save">
			<input type="hidden" name="csrf_token" value="<?= admin_h(csrf_token()) ?>">

			<div class="form-group">
				<label>Заголовок *</label>
				<input class="form-control" name="title" placeholder="Заголовок поста"
					value="<?= admin_h((string)($edit['title'] ?? '')) ?>" required maxlength="255">
			</div>

			<div class="row">
				<div class="col-md-6">
					<div class="form-group">
						<label>URL Slug *</label>
						<input class="form-control slug-input" name="slug" placeholder="url-slug (автогенерируется)"
							value="<?= admin_h((string)($edit['slug'] ?? '')) ?>">
						<small style="color:#666;">Если пусто, автоматически генерируется из заголовка</small>
					</div>
				</div>
				<div class="col-md-6">
					<div class="form-group">
						<label>Статус</label>
						<select class="form-control" name="status">
							<option value="draft" <?= ($edit['status'] ?? 'draft') === 'draft' ? 'selected' : '' ?>>Черновик</option>
							<option value="published" <?= ($edit['status'] ?? 'draft') === 'published' ? 'selected' : '' ?>>
								Опубликовано</option>
						</select>
					</div>
				</div>
			</div>

			<div class="form-group">
				<label>Анонс (выписка для списка)</label>
				<textarea class="form-control" rows="3" name="excerpt" placeholder="Короткое описание для страницы блога"
					maxlength="500"><?= admin_h((string)($edit['excerpt'] ?? '')) ?></textarea>
			</div>

			<div class="form-group">
				<label>Содержимое *</label>
				<textarea id="content" name="content"><?= admin_h((string)($edit['content'] ?? '')) ?></textarea>
			</div>

			<script>
			ClassicEditor
				.create(document.querySelector('#content'), {
					toolbar: {
						items: [
							'undo', 'redo',
							'|',
							'heading',
							'|',
							'bold', 'italic', 'underline', 'strikethrough',
							'|',
							'alignment',
							'|',
							'bulletedList', 'numberedList',
							'|',
							'link', 'imageUpload', 'blockQuote', 'insertTable',
							'|',
							'removeFormat', 'sourceEditing'
						],
						shouldNotGroupWhenFull: true
					},
					heading: {
						options: [{
								model: 'paragraph',
								title: 'Параграф',
								class: 'ck-heading_paragraph'
							},
							{
								model: 'heading1',
								view: 'h1',
								title: 'Заголовок 1',
								class: 'ck-heading_heading1'
							},
							{
								model: 'heading2',
								view: 'h2',
								title: 'Заголовок 2',
								class: 'ck-heading_heading2'
							},
							{
								model: 'heading3',
								view: 'h3',
								title: 'Заголовок 3',
								class: 'ck-heading_heading3'
							}
						]
					},
					image: {
						upload: {
							types: ['jpeg', 'png', 'gif', 'webp']
						},
						resizeOptions: [{
								name: 'imageResizePercentages',
								values: ['25', '50', '75', '100']
							},
							{
								name: 'imageResizeByWidth',
								values: ['200', '300', '400', '500', '600', '800']
							}
						],
						styles: [
							'full',
							'alignLeft',
							'alignRight',
							'alignCenter'
						]
					},
					simpleUpload: {
						uploadUrl: '/admin/blog-upload.php'
					},
					table: {
						contentToolbar: ['tableColumn', 'tableRow', 'mergeTableCells']
					},
					language: 'uk',
					autosave: {
						save(editor) {
							console.log('Content autosaved');
						},
						waitingTime: 3000,
						backoffDelay: 5000
					}
				})
				.catch(error => {
					console.error(error);
				});
			</script>

			<div class="row">
				<div class="col-md-8">
					<div class="form-group">
						<label>Изображение обложки</label>
						<input type="hidden" name="featured_image" id="featuredImageInput"
							value="<?= admin_h((string)($edit['featured_image'] ?? '')) ?>">
						<div class="image-preview-container">
							<div id="blogImagePreview" class="image-placeholder">
								<?php if (!empty($edit['featured_image'])): ?>
								<img src="/<?= admin_h((string)$edit['featured_image']) ?>" alt="Cover" class="image-preview-img">
								<?php else: ?>
								<span>📷 Обкладинка</span>
								<?php endif; ?>
							</div>
							<button type="button" class="btn-remove-image <?= !empty($edit['featured_image']) ? 'show' : '' ?>"
								id="blogBtnRemove" onclick="blogRemoveImage(event)">
								<svg viewBox="0 0 24 24" fill="none">
									<line x1="18" y1="6" x2="6" y2="18"></line>
									<line x1="6" y1="6" x2="18" y2="18"></line>
								</svg>
							</button>
						</div>
						<input type="file" id="blogImageUpload" class="image-upload-input" name="image_upload" accept="image/*">
						<small class="text-muted" style="display:block; margin-top:6px;">Клацніть на превью для вибору файлу</small>
					</div>
				</div>
				<div class="col-md-4">
					<div class="form-group">
						<label>Теги (через запятую)</label>
						<input class="form-control" name="tags" placeholder="тег1, тег2, тег3"
							value="<?= admin_h((string)($edit['tags'] ?? '')) ?>">
					</div>
				</div>
			</div>

			<div class="row">
				<div class="col-md-6">
					<div class="form-group">
						<label>SEO Заголовок</label>
						<input class="form-control" name="seo_title" placeholder="META title для поиска"
							value="<?= admin_h((string)($edit['seo_title'] ?? '')) ?>" maxlength="255">
					</div>
				</div>
				<div class="col-md-6">
					<div class="form-group">
						<label>SEO Описание</label>
						<input class="form-control" name="seo_description" placeholder="META description"
							value="<?= admin_h((string)($edit['seo_description'] ?? '')) ?>" maxlength="255">
					</div>
				</div>
			</div>

			<div style="margin-top:20px;">
				<button type="submit" class="btn btn-success btn-lg" style="margin-right:10px;">
					<?= !empty($edit['id']) ? '💾 Сохранить' : '➕ Создать пост' ?>
				</button>
				<?php if ($edit !== null): ?>
				<a href="/admin/blog.php" class="btn btn-secondary btn-lg">↩ Отменить</a>
				<?php endif; ?>
			</div>
		</form>

		<h3>Все посты (<?= count($posts) ?>)</h3>
		<table class="table table-bordered table-striped table-sm">
			<thead>
				<tr>
					<th>ID</th>
					<th>Заголовок</th>
					<th>Slug</th>
					<th>Статус</th>
					<th>Дата</th>
					<th>Просмотры</th>
					<th style="width:200px;">Действия</th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ($posts as $p): ?>
				<tr>
					<td><?= admin_h((string)$p['id']) ?></td>
					<td><?= admin_h((string)$p['title']) ?></td>
					<td><code><?= admin_h((string)$p['slug']) ?></code></td>
					<td>
						<span
							style="background:<?= $p['status'] === 'published' ? '#d4edda' : '#fff3cd' ?>; padding:3px 8px; border-radius:3px; font-size:12px;">
							<?= admin_h((string)$p['status']) ?>
						</span>
					</td>
					<td><?= admin_h(date('d.m.Y H:i', strtotime((string)$p['created_at']))) ?></td>
					<td><?= admin_h((string)$p['views']) ?></td>
					<td>
						<a href="/admin/blog.php?edit=<?= (int)$p['id'] ?>" class="btn btn-sm btn-info">✎ Редактировать</a>
						<form method="post" style="display:inline;">
							<input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
							<input type="hidden" name="action" value="delete">
							<input type="hidden" name="csrf_token" value="<?= admin_h(csrf_token()) ?>">
							<button type="submit" class="btn btn-sm btn-danger" onclick="return confirm('Удалить?')">✕
								Удалить</button>
						</form>
					</td>
				</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	</div>
	<script>
	(function() {
		var imageUpload = document.getElementById('blogImageUpload');
		if (!imageUpload) return;

		var imagePreview = document.getElementById('blogImagePreview');
		var btnRemove = document.getElementById('blogBtnRemove');

		// Клік на превью — відкрити діалог вибору файлу
		imagePreview.addEventListener('click', function() {
			imageUpload.click();
		});

		// Завантаження фото
		imageUpload.addEventListener('change', function(e) {
			var file = e.target.files[0];
			if (!file) return;
			var reader = new FileReader();
			reader.onload = function(event) {
				imagePreview.innerHTML = '<img src="' + event.target.result +
					'" alt="Preview" class="image-preview-img">';
				btnRemove.classList.add('show');
			};
			reader.readAsDataURL(file);
		});

		// Видалення фото
		window.blogRemoveImage = function(event) {
			event.preventDefault();
			event.stopPropagation();
			document.getElementById('featuredImageInput').value = '';
			imageUpload.value = '';
			imagePreview.innerHTML = '<span>📷 Обкладинка</span>';
			btnRemove.classList.remove('show');
		};
	})();
	</script>
</body>

</html>
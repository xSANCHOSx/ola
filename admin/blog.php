<?php

declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';
admin_require_auth();

$pdo = dev_db_connection();
$user = admin_current_user();

if (!$pdo instanceof PDO) {
	die('Database connection failed');
}

$msg = '';
$reason = '';
$edit = null;
$formHasErrors = false;

// Helper function for generating slug
function generateSlug(string $text): string
{
	$text = strtolower($text);
	$text = preg_replace('/[^a-z0-9\s\-]/u', '', $text);
	$text = preg_replace('/\s+/', '-', $text);
	return trim($text, '-');
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
			// Удалить теги без постов
			$pdo->query('DELETE bt FROM blog_tags bt LEFT JOIN blog_post_tags bpt ON bt.id = bpt.tag_id 
		WHERE bpt.tag_id IS NULL');
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
		$tagsRaw = (string)($_POST['tags'] ?? '');

		// Keep form values if validation fails
		$edit = [
			'id' => $id > 0 ? $id : null,
			'title' => $title,
			'slug' => $slug,
			'content' => $content,
			'excerpt' => $excerpt,
			'featured_image' => $featured_image,
			'status' => $status,
			'seo_title' => $seo_title,
			'seo_description' => $seo_description,
			'tags' => $tagsRaw,
		];

		// Server-side validation
		$errors = [];

		if ($title === '') {
			$errors[] = 'title_empty';
		}

		if ($content === '') {
			$errors[] = 'content_empty';
		}

		// Title max length
		if (strlen($title) > 255) {
			$errors[] = 'title_too_long';
		}

		// Auto-generate slug if empty
		if (empty($slug)) {
			$slug = generateSlug($title);
		} else {
			// Clean slug - only letters, numbers, hyphen
			$slug = preg_replace('/[^a-z0-9\-]/i', '', $slug);
			$slug = preg_replace('/\-+/', '-', $slug);
			$slug = strtolower(trim($slug, '-'));
		}

		// If slug is still empty after generation/cleanup, create fallback
		if (empty($slug)) {
			$slug = 'post-' . time();
		}

		// Validate image path - prevent path traversal
		if (!empty($featured_image)) {
			if (strpos($featured_image, '..') !== false || strpos($featured_image, '/') === 0) {
				$featured_image = '';
				$edit['featured_image'] = '';
			}
		}

		// Validate status
		if (!in_array($status, ['draft', 'published'], true)) {
			$status = 'draft';
			$edit['status'] = 'draft';
		}

		// If validation failed, stay on page and keep data
		if (!empty($errors)) {
			$formHasErrors = true;
			dev_log_security('BLOG_VALIDATION_FAILED', [
				'reason' => implode(',', $errors),
				'user' => $user['username'] ?? 'unknown',
			]);

			$msg = 'Ошибка при сохранении';
			$reasonMap = [
				'title_empty' => ' (заголовок обязателен)',
				'content_empty' => ' (контент обязателен)',
				'title_too_long' => ' (заголовок слишком длинный)',
				'slug_empty' => ' (slug не может быть пустым)',
			];
			foreach ($errors as $err) {
				if (isset($reasonMap[$err])) {
					$reason = $reasonMap[$err];
					break;
				}
			}
		} else {
			// Check duplicate slug (except current post)
			$slugCheck = $pdo->prepare('SELECT id FROM blog_posts WHERE slug = :slug AND id != :id');
			$slugCheck->execute(['slug' => $slug, 'id' => $id ?: 0]);
			if ($slugCheck->rowCount() > 0) {
				dev_log_security('BLOG_SLUG_DUPLICATE', ['slug' => $slug]);

				$formHasErrors = true;
				$msg = 'Ошибка при сохранении';
				$reason = ' (slug уже существует)';
			} else {
				// Handle image upload
				if (!empty($_FILES['image_upload']['tmp_name']) && is_uploaded_file($_FILES['image_upload']['tmp_name'])) {
					$ext = strtolower(pathinfo((string)$_FILES['image_upload']['name'], PATHINFO_EXTENSION));
					$allowed = ['jpg', 'jpeg', 'png', 'webp', 'gif'];

					if (!in_array($ext, $allowed, true)) {
						dev_log_security('BLOG_INVALID_IMAGE_TYPE', ['ext' => $ext]);
						$formHasErrors = true;
						$msg = 'Ошибка при сохранении';
						$reason = ' (неверный тип изображения)';
					} elseif ((int)$_FILES['image_upload']['size'] > 10 * 1024 * 1024) {
						$formHasErrors = true;
						$msg = 'Ошибка при сохранении';
						$reason = ' (изображение слишком большое)';
					} else {
						// Additional check - real image
						$finfo = getimagesize($_FILES['image_upload']['tmp_name']);
						if ($finfo === false) {
							dev_log_security('BLOG_INVALID_IMAGE_FILE', []);
							$formHasErrors = true;
							$msg = 'Ошибка при сохранении';
							$reason = ' (неверный файл изображения)';
						} else {
							$dir = __DIR__ . '/../data/uploads/blog';
							if (!is_dir($dir)) {
								mkdir($dir, 0755, true);
							}

							$fileName = bin2hex(random_bytes(12)) . '.' . $ext;
							$target = $dir . '/' . $fileName;
							if (move_uploaded_file($_FILES['image_upload']['tmp_name'], $target)) {
								$featured_image = 'data/uploads/blog/' . $fileName;
								$edit['featured_image'] = $featured_image;
							}
						}
					}
				}

				// Save only if still no errors
				if (!$formHasErrors) {
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
							$edit['id'] = $id;
							dev_log_security('BLOG_POST_CREATED', ['id' => $id, 'user' => $user['username'] ?? 'unknown']);
						}

						// Handle tags
						$tags = array_filter(array_map('trim', explode(',', $tagsRaw)));
						if (!empty($tags)) {
							$pdo->prepare('DELETE FROM blog_post_tags WHERE post_id = :id')->execute(['id' => $id]);

							foreach ($tags as $tag) {
								if (strlen($tag) > 100) {
									continue;
								}

								$tagSlug = preg_replace('/[^a-z0-9\s\-]/i', '', $tag);
								$tagSlug = preg_replace('/\s+/', '-', strtolower($tagSlug));

								// Insert or get existing tag
								$tagStmt = $pdo->prepare('INSERT IGNORE INTO blog_tags (name, slug) VALUES (:name, :slug)');
								$tagStmt->execute(['name' => $tag, 'slug' => $tagSlug]);

								$getTag = $pdo->prepare('SELECT id FROM blog_tags WHERE slug = :slug');
								$getTag->execute(['slug' => $tagSlug]);
								$tagRow = $getTag->fetch();

								$tagId = $tagRow !== false ? (int)$tagRow['id'] : 0;

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
						$formHasErrors = true;
						$msg = 'Ошибка при сохранении';
						$reason = '';
					}
				}
			}
		}
	}
}

// Fetch posts
$posts = [];
$stmt = $pdo->query('SELECT * FROM blog_posts ORDER BY created_at DESC LIMIT 200');
if ($stmt instanceof PDOStatement) {
	$posts = $stmt->fetchAll();
}

// Fetch single post for editing only if there was no failed POST validation
if (!$formHasErrors && isset($_GET['edit'])) {
	$editId = (int)$_GET['edit'];
	$edit = []; // empty array = new post; null = list without form
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

if (isset($_GET['msg'])) {
	$msgCode = (string)$_GET['msg'];
	$messages = [
		'saved' => 'Пост успешно сохранён',
		'deleted' => 'Пост удалён',
		'error' => 'Ошибка при сохранении'
	];
	$msg = $messages[$msgCode] ?? $msg;

	if (isset($_GET['reason'])) {
		$reasons = [
			'slug_exists' => ' (slug уже существует)',
			'invalid_image' => ' (неверный тип изображения)',
			'image_too_large' => ' (изображение слишком большое)',
		];
		$reason = $reasons[(string)$_GET['reason']] ?? $reason;
	}
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
	/* ── Alerts ── */
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

	/* ── Blog form wrapper ── */
	.blog-form-wrapper {
		display: flex;
		flex-direction: column;
		gap: 24px;
		margin-bottom: 40px;
	}

	/* ── Top: two-column grid (left info | right image) ── */
	.blog-form-top {
		display: grid;
		grid-template-columns: 1.5fr 1fr;
		gap: 30px;
		background: #f9f9f9;
		border: 1px solid #ddd;
		border-radius: 8px;
		padding: 24px;
	}

	.blog-form-left {
		display: flex;
		flex-direction: column;
		gap: 16px;
	}

	.blog-form-right {
		display: flex;
		flex-direction: column;
		gap: 10px;
	}

	/* ── Shared form-group style ── */
	.form-group-wrapper {
		display: flex;
		flex-direction: column;
		gap: 4px;
	}

	.form-group-wrapper label {
		font-weight: 600;
		font-size: 13px;
		color: #444;
	}

	.form-group-wrapper input,
	.form-group-wrapper select,
	.form-group-wrapper textarea {
		width: 100%;
		padding: 8px 10px;
		border: 1px solid #ccc;
		border-radius: 5px;
		font-size: 14px;
		box-sizing: border-box;
	}

	.status-slug-row {
		display: grid;
		grid-template-columns: 1fr 1fr;
		gap: 16px;
	}

	/* ── Descriptions (excerpt + content) ── */
	.descriptions-section {
		background: #f9f9f9;
		border: 1px solid #ddd;
		border-radius: 8px;
		padding: 24px;
		display: flex;
		flex-direction: column;
		gap: 20px;
	}

	.descriptions-section h5 {
		margin: 0 0 12px;
		font-size: 15px;
		font-weight: 700;
		color: #333;
		border-bottom: 2px solid #3e7ab6;
		padding-bottom: 6px;
	}

	.descriptions-section textarea {
		width: 100%;
		box-sizing: border-box;
	}

	/* ── SEO block ── */
	.seo-section {
		background: #f0f4fa;
		border: 1px solid #c5d6ec;
		border-radius: 8px;
		padding: 24px;
	}

	.seo-section h5 {
		margin: 0 0 16px;
		font-size: 15px;
		font-weight: 700;
		color: #2c5fa0;
		border-bottom: 2px solid #3e7ab6;
		padding-bottom: 6px;
	}

	.seo-two-cols {
		display: grid;
		grid-template-columns: 1fr 1fr;
		gap: 20px;
	}

	/* ── Image widget ── */
	.image-section {
		background: #fff;
		border: 1px solid #e0e0e0;
		border-radius: 8px;
		padding: 16px;
		text-align: center;
	}

	.image-preview-container {
		position: relative;
		display: block;
		width: 100%;
		margin: 0 auto;
	}

	.image-placeholder {
		width: 100%;
		aspect-ratio: 16 / 9;
		background: #e9ecef;
		border: 2px dashed #dee2e6;
		border-radius: 8px;
		display: flex;
		align-items: center;
		justify-content: center;
		cursor: pointer;
		transition: all 0.3s;
		color: #999;
		font-size: 15px;
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
		top: 6px;
		right: 6px;
		background: #dc3545;
		border: none;
		border-radius: 50%;
		width: 32px;
		height: 32px;
		padding: 0;
		display: none;
		align-items: center;
		justify-content: center;
		cursor: pointer;
		transition: all 0.2s;
		z-index: 20;
		box-shadow: 0 2px 8px rgba(0, 0, 0, .2);
	}

	.btn-remove-image:hover {
		background: #c82333;
		transform: scale(1.1);
	}

	.btn-remove-image.show {
		display: flex;
	}

	.btn-remove-image svg {
		width: 18px;
		height: 18px;
		display: block;
		stroke: white;
		stroke-width: 2.5;
		stroke-linecap: round;
		stroke-linejoin: round;
	}

	.image-upload-input {
		display: none !important;
	}

	/* ── CKEditor ── */
	.ck-content {
		min-height: 300px;
	}

	/* ── Responsive ── */
	@media (max-width: 768px) {
		.blog-form-top {
			grid-template-columns: 1fr;
		}

		.status-slug-row {
			grid-template-columns: 1fr;
		}

		.seo-two-cols {
			grid-template-columns: 1fr;
		}
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

		<form method="post" enctype="multipart/form-data" id="blogForm">
			<input type="hidden" name="id" value="<?= admin_h((string)($edit['id'] ?? '')) ?>">
			<input type="hidden" name="action" value="save">
			<input type="hidden" name="csrf_token" value="<?= admin_h(csrf_token()) ?>">

			<div class="blog-form-wrapper">

				<!-- ── TOP: LEFT info + RIGHT image ── -->
				<div class="blog-form-top">
					<!-- LEFT: заголовок, статус, slug, теги -->
					<div class="blog-form-left">
						<div class="form-group-wrapper">
							<label>Заголовок *</label>
							<input name="title" placeholder="Заголовок поста" value="<?= admin_h((string)($edit['title'] ?? '')) ?>"
								required maxlength="255">
						</div>

						<div class="status-slug-row">
							<div class="form-group-wrapper">
								<label>Статус</label>
								<select name="status">
									<option value="draft" <?= ($edit['status'] ?? 'draft') === 'draft' ? 'selected' : '' ?>>Черновик
									</option>
									<option value="published" <?= ($edit['status'] ?? 'draft') === 'published' ? 'selected' : '' ?>>
										Опубликовано</option>
								</select>
							</div>
							<div class="form-group-wrapper">
								<label>URL Slug</label>
								<input name="slug" placeholder="url-slug (автогенерируется)"
									value="<?= admin_h((string)($edit['slug'] ?? '')) ?>" style="font-family:monospace; font-size:12px;">
								<small style="color:#888; font-size:11px;">Если пусто — генерируется из заголовка</small>
							</div>
						</div>

						<div class="form-group-wrapper">
							<label>Теги (через запятую)</label>
							<input name="tags" placeholder="тег1, тег2, тег3" value="<?= admin_h((string)($edit['tags'] ?? '')) ?>">
						</div>
					</div>

					<!-- RIGHT: изображение обложки -->
					<div class="blog-form-right">
						<div class="image-section">
							<label style="font-weight:600; font-size:13px; color:#444; display:block; margin-bottom:10px;">Изображение
								обложки</label>
							<input type="hidden" name="featured_image" id="featuredImageInput"
								value="<?= admin_h((string)($edit['featured_image'] ?? '')) ?>">
							<div class="image-preview-container">
								<div id="blogImagePreview" class="image-placeholder">
									<?php if (!empty($edit['featured_image'])): ?>
									<img src="/<?= admin_h((string)$edit['featured_image']) ?>" alt="Cover" class="image-preview-img">
									<?php else: ?>
									<span>📷 Нажмите для выбора</span>
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
							<small style="color:#888; display:block; margin-top:8px; font-size:11px;">JPG, PNG, WEBP · до 10
								МБ</small>
						</div>
					</div>
				</div>

				<!-- ── MIDDLE: Анонс + Содержимое (на всю ширину) ── -->
				<div class="descriptions-section">
					<h5>📝 Контент</h5>

					<div class="form-group-wrapper">
						<label>Анонс (выписка для страницы блога)</label>
						<textarea name="excerpt" rows="3" placeholder="Короткое описание для карточки поста"
							maxlength="500"><?= admin_h((string)($edit['excerpt'] ?? '')) ?></textarea>
					</div>

					<div class="form-group-wrapper">
						<label>Полное содержимое *</label>
						<textarea id="content" name="content"
							style="position: absolute; width: 0; height: 0; opacity: 0; pointer-events: none;"></textarea>
						<div id="contentError" style="color: #dc3545; font-size: 13px; margin-top: 6px; display: none;">
							<?= $edit['content'] ?? '' ?></div>
					</div>
				</div>

				<!-- ── BOTTOM: SEO отдельным блоком ── -->
				<div class="seo-section">
					<h5>🔍 SEO</h5>
					<div class="seo-two-cols">
						<div class="form-group-wrapper">
							<label>SEO Заголовок (META title)</label>
							<input name="seo_title" placeholder="META title для поиска"
								value="<?= admin_h((string)($edit['seo_title'] ?? '')) ?>" maxlength="255">
						</div>
						<div class="form-group-wrapper">
							<label>SEO Описание (META description)</label>
							<input name="seo_description" placeholder="META description"
								value="<?= admin_h((string)($edit['seo_description'] ?? '')) ?>" maxlength="255">
						</div>
					</div>
				</div>

				<!-- ── Кнопки ── -->
				<div style="display:flex; gap:12px; align-items:center;">
					<button type="submit" class="btn btn-success btn-lg">
						<?= !empty($edit['id']) ? '💾 Сохранить' : '➕ Создать пост' ?>
					</button>
					<?php if ($edit !== null): ?>
					<a href="/admin/blog.php" class="btn btn-secondary btn-lg">↩ Отменить</a>
					<?php endif; ?>
				</div>

			</div><!-- /.blog-form-wrapper -->
		</form>

		<script>
		let blogEditor = null;

		function stripHtml(html) {
			return (html || '')
				.replace(/<[^>]*>/g, ' ')
				.replace(/&nbsp;/g, ' ')
				.replace(/\s+/g, ' ')
				.trim();
		}

		function editorHasContent(html) {
			return stripHtml(html).length > 0;
		}

		function generateClientSlug(text) {
			return text
				.toLowerCase()
				.replace(/[^a-z0-9\s\-]/g, '')
				.replace(/\s+/g, '-')
				.replace(/\-+/g, '-')
				.replace(/^\-+|\-+$/g, '');
		}

		// Auto-generate slug when title changes
		document.addEventListener('DOMContentLoaded', function() {
			const titleInput = document.querySelector('input[name="title"]');
			const slugInput = document.querySelector('input[name="slug"]');

			if (titleInput && slugInput) {
				titleInput.addEventListener('input', function() {
					if (!slugInput.value || slugInput.value === '') {
						slugInput.value = generateClientSlug(titleInput.value) || '';
					}
				});
			}
		});

		ClassicEditor
			.create(document.querySelector('#content'), {
				toolbar: {
					items: [
						'undo', 'redo', '|',
						'heading', '|',
						'bold', 'italic', 'underline', 'strikethrough', '|',
						'alignment', '|',
						'bulletedList', 'numberedList', '|',
						'link', 'imageUpload', 'blockQuote', 'insertTable', '|',
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
					styles: ['full', 'alignLeft', 'alignRight', 'alignCenter']
				},
				simpleUpload: {
					uploadUrl: '/admin/blog-upload.php'
				},
				table: {
					contentToolbar: ['tableColumn', 'tableRow', 'mergeTableCells']
				},
				language: 'uk'
			})
			.then(editor => {
				blogEditor = editor;
				window.blogEditor = editor;

				const form = document.getElementById('blogForm');
				const contentTextarea = document.getElementById('content');
				const contentError = document.getElementById('contentError');

				form.addEventListener('submit', function(e) {
					// Sync CKEditor data back to textarea before validation/submission
					if (blogEditor) {
						contentTextarea.value = blogEditor.getData();
					}

					// Check if content is empty
					if (!editorHasContent(contentTextarea.value)) {
						contentError.textContent = 'Поле "Полное содержимое" обязательно';
						contentError.style.display = 'block';
						e.preventDefault();
						return false;
					}

					// Hide error if content is valid
					contentError.style.display = 'none';

					// Native validation for regular fields
					if (!form.checkValidity()) {
						e.preventDefault();
						form.reportValidity();
						return false;
					}

					return true;
				});
			})
			.catch(error => {
				console.error(error);
			});
		</script>

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
							<?= $p['status'] === 'published' ? 'Опубликовано' : 'Черновик' ?>
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

		// Open file picker when clicking preview
		imagePreview.addEventListener('click', function() {
			imageUpload.click();
		});

		// Preview selected file
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

		// Remove image
		window.blogRemoveImage = function(event) {
			event.preventDefault();
			event.stopPropagation();
			document.getElementById('featuredImageInput').value = '';
			imageUpload.value = '';
			imagePreview.innerHTML = '<span>📷 Обложка</span>';
			btnRemove.classList.remove('show');
		};
	})();
	</script>
</body>

</html><?php

declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';
admin_require_auth();

$pdo = dev_db_connection();
$user = admin_current_user();

if (!$pdo instanceof PDO) {
	die('Database connection failed');
}

$msg = '';
$reason = '';
$edit = null;
$formHasErrors = false;

// Helper function for generating slug
function generateSlug(string $text): string
{
	$text = strtolower($text);
	$text = preg_replace('/[^a-z0-9\s\-]/u', '', $text);
	$text = preg_replace('/\s+/', '-', $text);
	return trim($text, '-');
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
			$msg = 'Пост видалено';
		} catch (Throwable $e) {
			$msg = 'Помилка при видаленні';
			$reason = $e->getMessage();
		}
	} 
	// Save/Update post
	elseif ($action === 'save') {
		$title = trim((string)($_POST['title'] ?? ''));
		$slug = trim((string)($_POST['slug'] ?? ''));
		$content = trim((string)($_POST['content'] ?? ''));
		$excerpt = trim((string)($_POST['excerpt'] ?? ''));
		$status = trim((string)($_POST['status'] ?? 'draft'));
		$featured_image = trim((string)($_POST['featured_image'] ?? ''));
		$tags_input = trim((string)($_POST['tags'] ?? ''));

		if ($title === '' || $content === '') {
			$msg = 'Заповніть заголовок та контент';
			$formHasErrors = true;
		} else {
			if ($slug === '') {
				$slug = generateSlug($title);
			}

			try {
				if ($id > 0) {
					$stmt = $pdo->prepare('UPDATE blog_posts SET title = :title, slug = :slug, content = :content, excerpt = :excerpt, status = :status, featured_image = :featured_image, updated_at = NOW() WHERE id = :id');
					$stmt->execute([
						'title' => $title,
						'slug' => $slug,
						'content' => $content,
						'excerpt' => $excerpt,
						'status' => $status,
						'featured_image' => $featured_image,
						'id' => $id
					]);
					$post_id = $id;
					$msg = 'Пост оновлено';
				} else {
					$stmt = $pdo->prepare('INSERT INTO blog_posts (title, slug, content, excerpt, status, featured_image, author_id, created_at, updated_at) VALUES (:title, :slug, :content, :excerpt, :status, :featured_image, :author_id, NOW(), NOW())');
					$stmt->execute([
						'title' => $title,
						'slug' => $slug,
						'content' => $content,
						'excerpt' => $excerpt,
						'status' => $status,
						'featured_image' => $featured_image,
						'author_id' => $user['id']
					]);
					$post_id = (int)$pdo->lastInsertId();
					$msg = 'Пост створено';
				}

				// Handle Tags
				$pdo->prepare('DELETE FROM blog_post_tags WHERE post_id = :id')->execute(['id' => $post_id]);
				$tags = array_filter(array_map('trim', explode(',', $tags_input)));
				foreach ($tags as $tag_name) {
					$stmt = $pdo->prepare('SELECT id FROM blog_tags WHERE name = :name');
					$stmt->execute(['name' => $tag_name]);
					$tag_id = $stmt->fetchColumn();

					if (!$tag_id) {
						$stmt = $pdo->prepare('INSERT INTO blog_tags (name, slug) VALUES (:name, :slug)');
						$stmt->execute(['name' => $tag_name, 'slug' => generateSlug($tag_name)]);
						$tag_id = (int)$pdo->lastInsertId();
					}

					$pdo->prepare('INSERT INTO blog_post_tags (post_id, tag_id) VALUES (:post_id, :tag_id)')->execute([
						'post_id' => $post_id,
						'tag_id' => $tag_id
					]);
				}
                
                // ВАЖЛИВО: Після збереження перенаправляємо або залишаємо ID для завантаження даних
                $edit_id_to_load = $post_id;

			} catch (Throwable $e) {
				$msg = 'Помилка бази даних';
				$reason = $e->getMessage();
				$formHasErrors = true;
			}
		}
	}
}

// Визначаємо, який ID завантажувати (з URL або після щойно виконаного POST)
$edit_id = (int)($_GET['edit'] ?? $edit_id_to_load ?? 0);

if ($edit_id > 0) {
	$stmt = $pdo->prepare('SELECT * FROM blog_posts WHERE id = :id');
	$stmt->execute(['id' => $edit_id]);
	$edit = $stmt->fetch(PDO::FETCH_ASSOC);

	if ($edit) {
		$stmt = $pdo->prepare('SELECT t.name FROM blog_tags t JOIN blog_post_tags pt ON t.id = pt.tag_id WHERE pt.post_id = :id');
		$stmt->execute(['id' => $edit_id]);
		$edit['tags'] = implode(', ', $stmt->fetchAll(PDO::FETCH_COLUMN));
	}
}

// Load all posts
$posts = $pdo->query('SELECT p.*, u.username as author_name FROM blog_posts p LEFT JOIN admin_users u ON p.author_id = u.id ORDER BY p.created_at DESC')->fetchAll(PDO::FETCH_ASSOC);

require __DIR__ . '/templates/head.php';
?>

<div class="container-fluid admin-container">
	<div class="d-flex justify-content-between align-items-center mb-4">
		<h1>Управління блогом</h1>
		<a href="index.php" class="btn btn-outline-secondary">← Назад</a>
	</div>

	<?php if ($msg): ?>
	<div class="alert alert-<?= $formHasErrors ? 'danger' : 'success' ?>">
		<?= admin_h($msg) ?>
		<?php if ($reason): ?><br><small><?= admin_h($reason) ?></small><?php endif; ?>
	</div>
	<?php endif; ?>

	<div class="card mb-5">
		<div class="card-header bg-primary text-white">
			<?= $edit ? 'Редагувати пост: ' . admin_h($edit['title']) : 'Створити новий запис' ?>
		</div>
		<div class="card-body">
			<form method="POST" action="blog.php" id="blogForm">
				<input type="hidden" name="csrf_token" value="<?= admin_h(csrf_token()) ?>">
				<input type="hidden" name="id" value="<?= $edit['id'] ?? 0 ?>">
				<input type="hidden" name="action" value="save">

				<div class="row">
					<div class="col-md-8">
						<div class="mb-3">
							<label class="form-label">Заголовок</label>
							<input type="text" name="title" class="form-control" value="<?= admin_h($edit['title'] ?? '') ?>"
								required>
						</div>

						<div class="mb-3">
							<label class="form-label">Текст новини</label>
							<textarea id="content" name="content"
								style="position: absolute; width: 1px; height: 1px; opacity: 0; pointer-events: none;"
								required><?= admin_h($edit['content'] ?? '') ?></textarea>
						</div>

						<div class="mb-3">
							<label class="form-label">Короткий опис (Excerpt)</label>
							<textarea name="excerpt" class="form-control" rows="3"><?= admin_h($edit['excerpt'] ?? '') ?></textarea>
						</div>
					</div>

					<div class="col-md-4">
						<div class="mb-3">
							<label class="form-label">URL Slug</label>
							<input type="text" name="slug" class="form-control" value="<?= admin_h($edit['slug'] ?? '') ?>">
						</div>

						<div class="mb-3">
							<label class="form-label">Статус</label>
							<select name="status" class="form-control">
								<option value="draft" <?= ($edit['status'] ?? '') === 'draft' ? 'selected' : '' ?>>Чернетка</option>
								<option value="published" <?= ($edit['status'] ?? '') === 'published' ? 'selected' : '' ?>>Опубліковано
								</option>
							</select>
						</div>

						<div class="mb-3">
							<label class="form-label">Теги</label>
							<input type="text" name="tags" class="form-control" value="<?= admin_h($edit['tags'] ?? '') ?>"
								placeholder="новини, акції">
						</div>
					</div>
				</div>

				<div class="mt-4">
					<button type="submit" class="btn btn-success px-5">Зберегти пост</button>
					<?php if ($edit): ?>
					<a href="blog.php" class="btn btn-link">Скасувати</a>
					<?php endif; ?>
				</div>
			</form>
		</div>
	</div>

	<div class="table-responsive">
		<table class="table table-hover">
		</table>
	</div>
</div>

<script src="https://cdn.ckeditor.com/ckeditor5/41.1.0/classic/ckeditor.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
	let editorInstance;
	const contentArea = document.querySelector('#content');

	if (contentArea) {
		ClassicEditor
			.create(contentArea)
			.then(editor => {
				editorInstance = editor;
				// ПРИМУСОВЕ ВСТАНОВЛЕННЯ ДАНИХ:
				// Якщо в textarea є текст (з PHP), а редактор порожній - заповнюємо його
				const initialData = contentArea.value;
				if (initialData && !editor.getData()) {
					editor.setData(initialData);
				}
			})
			.catch(error => {
				console.error(error);
			});
	}

	const form = document.getElementById('blogForm');
	if (form) {
		form.addEventListener('submit', function(e) {
			if (editorInstance) {
				contentArea.value = editorInstance.getData();
			}
			if (!form.reportValidity()) {
				e.preventDefault();
				return false;
			}
		});
	}
});
</script>

<?php require __DIR__ . '/templates/footer.php'; ?>
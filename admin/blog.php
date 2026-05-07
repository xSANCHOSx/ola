<?php

declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';
admin_require_auth();
$pdo = dev_db_connection();
$user = admin_current_user();
// POST handler
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $pdo instanceof PDO) {
	if (!validate_csrf_token()) {
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
			header('Location: /admin/blog.php?msg=deleted');
			exit;
		} catch (Exception $e) {
			error_log($e->getMessage());
		}
	}
	// Save/Update post
	if ($action === 'save') {
		$data = [
			'title' => trim((string)($_POST['title'] ?? '')),
			'slug' => trim((string)($_POST['slug'] ?? '')),
			'content' => (string)($_POST['content'] ?? ''),
			'excerpt' => trim((string)($_POST['excerpt'] ?? '')),
			'featured_image' => trim((string)($_POST['featured_image'] ?? '')),
			'status' => trim((string)($_POST['status'] ?? 'draft')),
			'seo_title' => trim((string)($_POST['seo_title'] ?? '')),
			'seo_description' => trim((string)($_POST['seo_description'] ?? '')),
		];
		// Auto-generate slug if empty
		if (empty($data['slug'])) {
			$data['slug'] = strtolower(preg_replace('/[^a-z0-9\s\-]/i', '', $data['title']));
			$data['slug'] = preg_replace('/\s+/', '-', $data['slug']);
		}
		// Validate required fields
		if (empty($data['title']) || empty($data['content']) || empty($data['excerpt'])) {
			header('Location: /admin/blog.php?msg=error&edit=' . $id);
			exit;
		}
		// Handle image upload
		if (!empty($_FILES['image_upload']['tmp_name']) && is_uploaded_file($_FILES['image_upload']['tmp_name'])) {
			$ext = strtolower(pathinfo((string)$_FILES['image_upload']['name'], PATHINFO_EXTENSION));
			$allowed = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
			if (in_array($ext, $allowed, true) && (int)$_FILES['image_upload']['size'] <= 10 * 1024 * 1024) {
				$dir = __DIR__ . '/../data/uploads/blog';
				if (!is_dir($dir)) {
					mkdir($dir, 0755, true);
				}
				$fileName = bin2hex(random_bytes(12)) . '.' . $ext;
				$target = $dir . '/' . $fileName;
				if (move_uploaded_file($_FILES['image_upload']['tmp_name'], $target)) {
					$data['featured_image'] = 'data/uploads/blog/' . $fileName;
				}
			}
		}
		try {
			if ($id > 0) {
				// Update
				$data['id'] = $id;
				// Если меняем статус на published и дата еще не установлена, установить ее
				if ($data['status'] === 'published' && empty($post['published_at'])) {
					$data['published_at'] = date('Y-m-d H:i:s');
				}
				$sql = 'UPDATE blog_posts SET title=:title, slug=:slug, content=:content, excerpt=:excerpt, featured_image=:featured_image, status=:status, seo_title=:seo_title, seo_description=:seo_description' . (isset($data['published_at']) ? ', published_at=:published_at' : '') . ' WHERE id=:id';
				$pdo->prepare($sql)->execute($data);
			} else {
				// Insert
				if ($data['status'] === 'published') {
					$data['published_at'] = date('Y-m-d H:i:s');
				}
				$data['author_id'] = $user['id'] ?? null;
				$sql = 'INSERT INTO blog_posts (title, slug, content, excerpt, featured_image, author_id, status, seo_title, seo_description, published_at) VALUES (:title, :slug, :content, :excerpt, :featured_image, :author_id, :status, :seo_title, :seo_description, ' . (isset($data['published_at']) ? ':published_at' : 'NULL') . ')';
				$pdo->prepare($sql)->execute($data);
				$id = (int)$pdo->lastInsertId();
			}
			// Handle tags
			$tags = array_filter(array_map('trim', explode(',', (string)($_POST['tags'] ?? ''))));
			if (!empty($tags)) {
				$pdo->prepare('DELETE FROM blog_post_tags WHERE post_id = :id')->execute(['id' => $id]);
				$stmt = $pdo->prepare('INSERT INTO blog_tags (name, slug) VALUES (:name, :slug) ON DUPLICATE KEY UPDATE id=LAST_INSERT_ID(id)');
				foreach ($tags as $tag) {
					$slug = strtolower(preg_replace('/[^a-z0-9\s\-]/i', '', $tag));
					$slug = preg_replace('/\s+/', '-', $slug);
					$stmt->execute(['name' => $tag, 'slug' => $slug]);
					$tagId = (int)$pdo->lastInsertId();
					$pdo->prepare('INSERT IGNORE INTO blog_post_tags (post_id, tag_id) VALUES (:post_id, :tag_id)')->execute(['post_id' => $id, 'tag_id' => $tagId]);
				}
			}
			header('Location: /admin/blog.php?msg=saved');
			exit;
		} catch (Exception $e) {
			error_log($e->getMessage());
			header('Location: /admin/blog.php?msg=error&edit=' . $id);
			exit;
		}
	}
}
// Fetch posts
$posts = [];
if ($pdo instanceof PDO) {
	$posts = $pdo->query('SELECT * FROM blog_posts ORDER BY created_at DESC LIMIT 200')->fetchAll();
}
// Fetch single post for editing
$edit = null;
if (isset($_GET['edit'])) {
	$editId = (int)$_GET['edit'];
	foreach ($posts as $p) {
		if ((int)$p['id'] === $editId) {
			$edit = $p;
			// Get tags
			if ($pdo instanceof PDO) {
				$tags = $pdo->prepare('SELECT bt.name FROM blog_tags bt JOIN blog_post_tags bpt ON bt.id = bpt.tag_id WHERE bpt.post_id = :id');
				$tags->execute(['id' => $editId]);
				$edit['tags'] = implode(', ', array_column($tags->fetchAll(), 'name'));
			}
			break;
		}
	}
}
$msg = '';
if (isset($_GET['msg'])) {
	$msgCode = (string)$_GET['msg'];
	$messages = [
		'saved' => 'Пост успішно збережено',
		'deleted' => 'Пост видалено',
		'error' => 'Помилка при збереженні'
	];
	$msg = $messages[$msgCode] ?? '';
}
?>
<!doctype html>
<html lang="uk">

<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Адмінка - Блог</title>
	<link rel="stylesheet" href="/css/bootstrap.min.css">
	<script src="https://cdn.ckeditor.com/ckeditor5/39.0.1/classic/ckeditor.js"></script>
	<script>
		document.addEventListener('DOMContentLoaded', function() {
			// Кастомний адаптер для сумісності з вашим бекендом (повертає 'location')
			class UploadAdapter {
				constructor(loader) {
					this.loader = loader;
				}
				upload() {
					return this.loader.file.then(file => new Promise((resolve, reject) => {
						const data = new FormData();
						data.append('file', file);
						const xhr = new XMLHttpRequest();
						xhr.open('POST', '/admin/blog-upload.php');
						xhr.onload = () => {
							if (xhr.status >= 200 && xhr.status < 300) {
								try {
									const response = JSON.parse(xhr.responseText);
									// Ваш бекенд повертає { location: "..." }, CKEditor очікує { default: "..." }
									if (response && response.location) {
										resolve({
											default: response.location
										});
									} else {
										reject('Invalid response from server');
									}
								} catch (e) {
									reject('JSON parse error');
								}
							} else {
								reject('HTTP error: ' + xhr.status);
							}
						};
						xhr.onerror = () => reject('Network error');
						xhr.send(data);
					}));
				}
				abort() {}
			}

			function registerUploadAdapter(editor) {
				editor.plugins.get('FileRepository').createUploadAdapter = (loader) => new UploadAdapter(loader);
			}

			// Ініціалізуємо тільки якщо елемент існує
			const contentElement = document.querySelector('#content');
			if (contentElement) {
				ClassicEditor
					.create(contentElement, {
						toolbar: [
							'heading', '|', 'bold', 'italic', 'link', 'bulletedList', 'numberedList',
							'blockQuote', 'imageUpload', 'insertTable', 'code', '|', 'undo', 'redo'
						],
						extraPlugins: [registerUploadAdapter],
						heading: {
							options: [{
									model: 'paragraph',
									title: 'Paragraph',
									class: 'ck-heading_paragraph'
								},
								{
									model: 'heading1',
									view: 'h1',
									title: 'Heading 1',
									class: 'ck-heading_heading1'
								},
								{
									model: 'heading2',
									view: 'h2',
									title: 'Heading 2',
									class: 'ck-heading_heading2'
								},
								{
									model: 'heading3',
									view: 'h3',
									title: 'Heading 3',
									class: 'ck-heading_heading3'
								}
							]
						}
					})
					.catch(error => console.error('CKEditor initialization error:', error));
			}
		});
	</script>
	<style>
		.form-group {
			margin-bottom: 15px;
		}

		.form-group label {
			font-weight: bold;
			margin-bottom: 5px;
			display: block;
		}

		.ck-editor__editable_inline {
			min-height: 400px;
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

		/* CKEditor 5 не підтримує height у конфігурації, тому задаємо через CSS */
		.ck-editor__editable_inline {
			min-height: 400px;
		}
	</style>
</head>

<body>
	<div class="container">
		<?php require __DIR__ . '/_nav.php'; ?>
		<h2>Управління блогом</h2>
		<?php if ($msg): ?>
			<div class="alert alert-<?= strpos($msg, 'Помилка') !== false ? 'error' : 'success' ?>">
				<?= admin_h($msg) ?>
			</div>
		<?php endif; ?>
		<form method="post" enctype="multipart/form-data"
			style="background:#f9f9f9; padding:20px; border:1px solid #ddd; margin-bottom:30px;">
			<input type="hidden" name="id" value="<?= admin_h((string)($edit['id'] ?? '')) ?>">
			<input type="hidden" name="action" value="save">
			<input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
			<div class="form-group">
				<label>Заголовок *</label>
				<input class="form-control" name="title" placeholder="Заголовок поста"
					value="<?= admin_h((string)($edit['title'] ?? '')) ?>" required>
			</div>
			<div class="row">
				<div class="col-md-6">
					<div class="form-group">
						<label>URL Slug *</label>
						<input class="form-control" name="slug" placeholder="url-slug (автогенерується)"
							value="<?= admin_h((string)($edit['slug'] ?? '')) ?>">
						<small style="color:#666;">Якщо пусто, автоматично генеруватиметься з заголовка</small>
					</div>
				</div>
				<div class="col-md-6">
					<div class="form-group">
						<label>Статус</label>
						<select class="form-control" name="status">
							<option value="draft" <?= ($edit['status'] ?? 'draft') === 'draft' ? 'selected' : '' ?>>Чернетка</option>
							<option value="published" <?= ($edit['status'] ?? 'draft') === 'published' ? 'selected' : '' ?>>
								Опубліковано</option>
						</select>
					</div>
				</div>
			</div>
			<div class="form-group">
				<label>Анонс (виписка для переліку) *</label>
				<textarea class="form-control" rows="3" name="excerpt" required
					placeholder="Короткий опис для сторінки блога"><?= admin_h((string)($edit['excerpt'] ?? '')) ?></textarea>
			</div>
			<div class="form-group">
				<label>Вміст *</label>
				<textarea id="content" name="content"><?= admin_h((string)($edit['content'] ?? '')) ?></textarea>
			</div>
			<div class="row">
				<div class="col-md-8">
					<div class="form-group">
						<label>Зображення обкладинки</label>
						<div style="margin-bottom:10px;">
							<input class="form-control" name="featured_image" placeholder="Шлях до зображення"
								value="<?= admin_h((string)($edit['featured_image'] ?? '')) ?>">
						</div>
						<input type="file" class="form-control" name="image_upload" accept="image/*">
						<?php if (!empty($edit['featured_image'])): ?>
							<img src="/<?= admin_h((string)$edit['featured_image']) ?>"
								style="max-width:200px; max-height:200px; margin-top:10px;">
						<?php endif; ?>
					</div>
				</div>
				<div class="col-md-4">
					<div class="form-group">
						<label>Теги (через кому)</label>
						<input class="form-control" name="tags" placeholder="тег1, тег2, тег3"
							value="<?= admin_h((string)($edit['tags'] ?? '')) ?>">
					</div>
				</div>
			</div>
			<div class="row">
				<div class="col-md-6">
					<div class="form-group">
						<label>SEO Заголовок</label>
						<input class="form-control" name="seo_title" placeholder="META title для пошуку"
							value="<?= admin_h((string)($edit['seo_title'] ?? '')) ?>">
					</div>
				</div>
				<div class="col-md-6">
					<div class="form-group">
						<label>SEO Опис</label>
						<input class="form-control" name="seo_description" placeholder="META description"
							value="<?= admin_h((string)($edit['seo_description'] ?? '')) ?>">
					</div>
				</div>
			</div>
			<div style="margin-top:20px;">
				<button type="submit" class="btn btn-success" style="margin-right:10px;">
					<?= $edit ? 'Зберегти' : 'Створити пост' ?>
				</button>
				<?php if ($edit): ?>
					<a href="/admin/blog.php" class="btn btn-secondary">Скасувати</a>
				<?php endif; ?>
			</div>
		</form>
		<h3>Всі пости (<?= count($posts) ?>)</h3>
		<table class="table table-bordered table-striped">
			<thead>
				<tr>
					<th>ID</th>
					<th>Заголовок</th>
					<th>Slug</th>
					<th>Статус</th>
					<th>Дата</th>
					<th>Перегляди</th>
					<th></th>
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
							<a href="/admin/blog.php?edit=<?= (int)$p['id'] ?>" class="btn btn-sm btn-info" style="margin-right:5px;">✎
								Редакт</a>
							<form method="post" style="display:inline;">
								<input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
								<input type="hidden" name="action" value="delete">
								<input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
								<button type="submit" class="btn btn-sm btn-danger" onclick="return confirm('Видалити?')">✕
									Видалити</button>
							</form>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	</div>
</body>

</html>
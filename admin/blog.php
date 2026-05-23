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

// Helper function for transliteration and slug generation
function generateSlug(string $text): string
{
	$cyr = [
		'а',
		'б',
		'в',
		'г',
		'д',
		'е',
		'ё',
		'ж',
		'з',
		'и',
		'й',
		'к',
		'л',
		'м',
		'н',
		'о',
		'п',
		'р',
		'с',
		'т',
		'у',
		'ф',
		'х',
		'ц',
		'ч',
		'ш',
		'щ',
		'ъ',
		'ы',
		'ь',
		'э',
		'ю',
		'я',
		'А',
		'Б',
		'В',
		'Г',
		'Д',
		'Е',
		'Ё',
		'Ж',
		'З',
		'И',
		'Й',
		'К',
		'Л',
		'М',
		'Н',
		'О',
		'П',
		'Р',
		'С',
		'Т',
		'У',
		'Ф',
		'Х',
		'Ц',
		'Ч',
		'Ш',
		'Щ',
		'Ъ',
		'Ы',
		'Ь',
		'Э',
		'Ю',
		'Я',
		// Ukrainian
		'і',
		'ї',
		'є',
		'ґ',
		'І',
		'Ї',
		'Є',
		'Ґ',
	];
	$lat = [
		'a',
		'b',
		'v',
		'g',
		'd',
		'e',
		'io',
		'zh',
		'z',
		'i',
		'y',
		'k',
		'l',
		'm',
		'n',
		'o',
		'p',
		'r',
		's',
		't',
		'u',
		'f',
		'h',
		'ts',
		'ch',
		'sh',
		'shb',
		'',
		'y',
		'',
		'e',
		'yu',
		'ya',
		'a',
		'b',
		'v',
		'g',
		'd',
		'e',
		'io',
		'zh',
		'z',
		'i',
		'y',
		'k',
		'l',
		'm',
		'n',
		'o',
		'p',
		'r',
		's',
		't',
		'u',
		'f',
		'h',
		'ts',
		'ch',
		'sh',
		'shb',
		'',
		'y',
		'',
		'e',
		'yu',
		'ya',
		// Ukrainian
		'i',
		'yi',
		'ye',
		'g',
		'i',
		'yi',
		'ye',
		'g',
	];
	$text = str_replace($cyr, $lat, $text);
	$text = strtolower($text);
	$text = preg_replace('/[^a-z0-9\s\-]/u', '', $text);
	$text = preg_replace('/\s+/', '-', $text);
	$text = preg_replace('/\-+/', '-', $text);
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

	// ── Delete post ─────────────────────────────────────────────────────────
	if ($action === 'delete' && $id > 0) {
		try {
			$pdo->prepare('DELETE FROM blog_post_tags WHERE post_id = :id')->execute(['id' => $id]);
			$pdo->prepare('DELETE FROM blog_posts WHERE id = :id')->execute(['id' => $id]);

			// Удаляем теги без постов ТОЛЬКО при удалении поста — это безопасно,
			// т.к. мы точно знаем что пост удалён
			$pdo->query(
				'DELETE bt FROM blog_tags bt
LEFT JOIN blog_post_tags bpt ON bt.id = bpt.tag_id
WHERE bpt.tag_id IS NULL'
			);

			dev_log_security('BLOG_POST_DELETED', ['id' => $id, 'user' => $user['username'] ?? 'unknown']);
			header('Location: /admin/blog.php?msg=deleted');
			exit;
		} catch (Exception $e) {
			dev_log_runtime('Blog delete error: ' . $e->getMessage());
			header('Location: /admin/blog.php?msg=error&edit=' . $id);
			exit;
		}
	}

	// ── Save / Update post ──────────────────────────────────────────────────
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

		// Сохраняем значения формы на случай ошибки
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

		// ── Валидация ────────────────────────────────────────────
		$errors = [];
		if ($title === '') {
			$errors[] = 'title_empty';
		}
		if ($content === '') {
			$errors[] = 'content_empty';
		}
		if (strlen($title) > 255) {
			$errors[] = 'title_too_long';
		}

		// Генерация slug
		if (empty($slug)) {
			$slug = generateSlug($title);
		} else {
			$slug = preg_replace('/[^a-z0-9\-]/i', '', $slug);
			$slug = preg_replace('/\-+/', '-', $slug);
			$slug = strtolower(trim($slug, '-'));
		}
		if (empty($slug)) {
			$slug = 'post-' . time();
		}

		// Защита от path traversal в пути изображения
		if (!empty($featured_image)) {
			if (strpos($featured_image, '..') !== false || strpos($featured_image, '/') === 0) {
				$featured_image = '';
				$edit['featured_image'] = '';
			}
		}

		// Статус
		if (!in_array($status, ['draft', 'published'], true)) {
			$status = 'draft';
			$edit['status'] = 'draft';
		}

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
			];
			foreach ($errors as $err) {
				if (isset($reasonMap[$err])) {
					$reason = $reasonMap[$err];
					break;
				}
			}
		} else {
			// Проверяем дублирование slug
			$slugCheck = $pdo->prepare('SELECT id FROM blog_posts WHERE slug = :slug AND id != :id');
			$slugCheck->execute(['slug' => $slug, 'id' => $id ?: 0]);
			if ($slugCheck->rowCount() > 0) {
				dev_log_security('BLOG_SLUG_DUPLICATE', ['slug' => $slug]);
				$formHasErrors = true;
				$msg = 'Ошибка при сохранении';
				$reason = ' (slug уже существует)';
			} else {
				// ── Загрузка изображения ─────────────────────────
				if (
					!empty($_FILES['image_upload']['tmp_name']) &&
					is_uploaded_file($_FILES['image_upload']['tmp_name'])
				) {
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
			}
		}

		// ── Сохранение в БД ─────────────────────────────────────
		if (!$formHasErrors) {
			try {
				if ($id > 0) {
					// Обновление существующего поста
					$pdo->prepare(
						'UPDATE blog_posts SET
title = :title,
slug = :slug,
content = :content,
excerpt = :excerpt,
featured_image = :featured_image,
status = :status,
seo_title = :seo_title,
seo_description = :seo_description
WHERE id = :id'
					)->execute([
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
					// Создание нового поста
					$published_at = ($status === 'published') ? date('Y-m-d H:i:s') : null;

					$pdo->prepare(
						'INSERT INTO blog_posts
(title, slug, content, excerpt, featured_image, author_id, status, seo_title, seo_description, published_at)
VALUES
(:title, :slug, :content, :excerpt, :featured_image, :author_id, :status, :seo_title, :seo_description, :published_at)'
					)->execute([
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

				// ── Сохранение тегов ─────────────────────────────────────────
				//
				// ВАЖНО: не удаляем "осиротевшие" теги из blog_tags при сохранении поста.
				// Причина: если удалить связи (blog_post_tags) и тут же запустить
				// очистку blog_tags до того, как новые связи вставлены — теги пропадут.
				// Теги в blog_tags — это справочник; лишние записи не мешают работе
				// и будут доступны в autocomplete для будущих постов.
				// Очистка осиротевших тегов происходит только при УДАЛЕНИИ поста.
				//
				// Алгоритм:
				// 1. Разбираем новый список тегов из формы
				// 2. Для каждого тега — INSERT IGNORE в blog_tags (создать если нет)
				// 3. Получаем id каждого тега
				// 4. Заменяем все связи поста одним DELETE + INSERT

				$newTagNames = array_values(array_unique(array_filter(
					array_map('trim', explode(',', $tagsRaw))
				)));

				// Собираем id тегов (создавая новые по необходимости)
				$newTagIds = [];
				foreach ($newTagNames as $tagName) {
					if (strlen($tagName) > 100) {
						continue;
					}

					$tagSlug = generateSlug($tagName);
					if (empty($tagSlug)) {
						// Название не транслитерируется — пропускаем
						continue;
					}

					// Вставляем тег если не существует; если существует — ничего не делаем
					$pdo->prepare('INSERT IGNORE INTO blog_tags (name, slug) VALUES (:name, :slug)')
						->execute(['name' => $tagName, 'slug' => $tagSlug]);

					// Получаем id (работает и для нового, и для уже существующего)
					$tagIdStmt = $pdo->prepare('SELECT id FROM blog_tags WHERE slug = :slug LIMIT 1');
					$tagIdStmt->execute(['slug' => $tagSlug]);
					$tagId = (int)($tagIdStmt->fetchColumn() ?: 0);

					if ($tagId > 0) {
						$newTagIds[] = $tagId;
					}
				}

				// Теперь обновляем связи: удаляем старые и вставляем новые
				// Делаем это ПОСЛЕ того как все теги уже существуют в blog_tags
				$pdo->prepare('DELETE FROM blog_post_tags WHERE post_id = :id')
					->execute(['id' => $id]);

				foreach ($newTagIds as $tagId) {
					$pdo->prepare('INSERT IGNORE INTO blog_post_tags (post_id, tag_id) VALUES (:post_id, :tag_id)')
						->execute(['post_id' => $id, 'tag_id' => $tagId]);
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

// ── Список постов ─────────────────────────────────────────────────────────────
$posts = [];
$stmt = $pdo->query('SELECT * FROM blog_posts ORDER BY created_at DESC LIMIT 200');
if ($stmt instanceof PDOStatement) {
	$posts = $stmt->fetchAll();
}

// ── Загрузка поста для редактирования ────────────────────────────────────────
if (!$formHasErrors && isset($_GET['edit'])) {
	$editId = (int)$_GET['edit'];
	$edit = [];
	if ($editId > 0) {
		$editStmt = $pdo->prepare('SELECT * FROM blog_posts WHERE id = :id');
		$editStmt->execute(['id' => $editId]);
		$fetched = $editStmt->fetch();
		if ($fetched !== false) {
			$edit = $fetched;
		}
	}
	if (!empty($edit['id'])) {
		$tagsStmt = $pdo->prepare(
			'SELECT bt.name FROM blog_tags bt
JOIN blog_post_tags bpt ON bt.id = bpt.tag_id
WHERE bpt.post_id = :id
ORDER BY bt.name ASC'
		);
		$tagsStmt->execute(['id' => (int)$edit['id']]);
		$edit['tags'] = implode(', ', array_column($tagsStmt->fetchAll(), 'name'));
	}
}

// ── Теги для autocomplete (отсортированы по частоте использования) ────────────
// Передаём имена тегов + счётчик; JS будет показывать популярные при пустом поиске
$allTagsRaw = [];
$allTagsStmt = $pdo->query(
	'SELECT bt.name, COUNT(bpt.post_id) AS cnt
FROM blog_tags bt
LEFT JOIN blog_post_tags bpt ON bt.id = bpt.tag_id
GROUP BY bt.id, bt.name
ORDER BY cnt DESC, bt.name ASC'
);
if ($allTagsStmt) {
	$allTagsRaw = $allTagsStmt->fetchAll(PDO::FETCH_ASSOC);
}
// Для JS — просто массив имён в порядке популярности
$allTagsList = array_column($allTagsRaw, 'name');

// ── Сообщения ────────────────────────────────────────────────────────────────
if (isset($_GET['msg'])) {
	$msgCode = (string)$_GET['msg'];
	$messages = [
		'saved' => 'Пост успешно сохранён',
		'deleted' => 'Пост удалён',
		'error' => 'Ошибка при сохранении',
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
    <link rel="stylesheet" href="/css/admin.css">
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

	/* ── Top grid ── */
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

	/* ── Descriptions ── */
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

	/* ── SEO ── */
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
		margin: 0 auto;
		stroke: white;
		stroke-width: 2.5;
		stroke-linecap: round;
		stroke-linejoin: round;
	}

	.image-upload-input {
		display: none !important;
	}


	.ck-content {
		min-height: 300px;
	}

	/* ══════════════════════════════════════════════════════════
		   Tag widget
		═══════════════════════════════════════════════════════ */
	.tag-widget {
		position: relative;
		border: 1px solid #ccc;
		border-radius: 5px;
		background: #fff;
		padding: 6px 8px;
		min-height: 42px;
		cursor: text;
		transition: border-color .15s;
	}

	.tag-widget:focus-within {
		border-color: #3e7ab6;
		box-shadow: 0 0 0 3px rgba(62, 122, 182, .15);
	}

	.tag-pills {
		display: flex;
		flex-wrap: wrap;
		gap: 6px;
		margin-bottom: 2px;
	}

	.tag-pill {
		display: inline-flex;
		align-items: center;
		gap: 4px;
		background: #dde8f5;
		color: #2c5fa0;
		border-radius: 20px;
		padding: 3px 10px 3px 12px;
		font-size: 13px;
		font-weight: 500;
		line-height: 1.4;
	}

	.tag-pill-remove {
		cursor: pointer;
		font-size: 17px;
		line-height: 1;
		color: #7a9fcb;
		border: none;
		background: none;
		padding: 0 0 0 2px;
	}

	.tag-pill-remove:hover {
		color: #dc3545;
	}

	.tag-input-bare {
		border: none !important;
		outline: none !important;
		box-shadow: none !important;
		padding: 4px 4px !important;
		font-size: 14px !important;
		width: auto !important;
		min-width: 160px;
		background: transparent;
	}

	/* Dropdown */
	.tag-dropdown {
		position: absolute;
		left: 0;
		right: 0;
		top: calc(100% + 2px);
		background: #fff;
		border: 1px solid #bcd;
		border-radius: 6px;
		max-height: 220px;
		overflow-y: auto;
		z-index: 200;
		box-shadow: 0 6px 20px rgba(0, 0, 0, .12);
		display: none;
	}

	.tag-dropdown-header {
		padding: 6px 12px 4px;
		font-size: 11px;
		font-weight: 600;
		color: #aaa;
		text-transform: uppercase;
		letter-spacing: .04em;
		border-bottom: 1px solid #f0f0f0;
	}

	.tag-dropdown-item {
		padding: 8px 14px;
		cursor: pointer;
		font-size: 13px;
		color: #333;
		display: flex;
		align-items: center;
		gap: 6px;
	}

	.tag-dropdown-item:hover,
	.tag-dropdown-item.active {
		background: #f0f4fa;
	}

	.tag-dropdown-item mark {
		background: #ffeaa0;
		border-radius: 2px;
		padding: 0 1px;
	}

	.tag-dropdown-item.new-tag {
		color: #2c8a3d;
		font-style: italic;
		border-top: 1px solid #eee;
	}

	.tag-dropdown-empty {
		padding: 12px 14px;
		font-size: 13px;
		color: #aaa;
		font-style: italic;
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
									<option value="draft" <?= ($edit['status'] ?? 'draft') === 'draft' ? 'selected' : '' ?>>
										Черновик
									</option>
									<option value="published" <?= ($edit['status'] ?? 'draft') === 'published' ? 'selected' : '' ?>>
										Опубликовано
									</option>
								</select>
							</div>
							<div class="form-group-wrapper">
								<label>URL Slug</label>
								<input name="slug" placeholder="url-slug (автогенерируется)"
									value="<?= admin_h((string)($edit['slug'] ?? '')) ?>" style="font-family:monospace; font-size:12px;">
								<small style="color:#888; font-size:11px;">Если пусто — генерируется из заголовка</small>
							</div>
						</div>

						<!-- ── Виджет тегов ── -->
						<div class="form-group-wrapper">
							<label>Теги</label>
							<input type="hidden" name="tags" id="tagsHidden" value="<?= admin_h((string)($edit['tags'] ?? '')) ?>">
							<div id="tagWidget" class="tag-widget">
								<div id="tagPills" class="tag-pills"></div>
								<input type="text" id="tagInput" class="tag-input-bare" autocomplete="off" spellcheck="false"
									placeholder="Введите тег или выберите из списка…">
								<div id="tagDropdown" class="tag-dropdown"></div>
							</div>
							<small style="color:#888; font-size:11px;">
								Enter или запятая — добавить. Backspace — удалить последний.
							</small>
						</div>

					</div><!-- /.blog-form-left -->

					<!-- RIGHT: изображение обложки -->
					<div class="blog-form-right">
						<div class="image-section">
							<label style="font-weight:600; font-size:13px; color:#444; display:block; margin-bottom:10px;">
								Изображение обложки
							</label>
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
							<small style="color:#888; display:block; margin-top:8px; font-size:11px;">
								JPG, PNG, WEBP · до 10 МБ
							</small>
						</div>
					</div><!-- /.blog-form-right -->
				</div><!-- /.blog-form-top -->

				<!-- ── MIDDLE: Анонс + Содержимое ── -->
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
							style="position:absolute; width:0; height:0; opacity:0; pointer-events:none;"><?= admin_h((string)($edit['content'] ?? '')) ?></textarea>
						<div id="contentError" style="color:#dc3545; font-size:13px; margin-top:6px; display:none;"></div>
					</div>
				</div>

				<!-- ── BOTTOM: SEO ── -->
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
				<div style="display:flex; flex-wrap:wrap; gap:12px; align-items:center;">
					<button type="submit" class="btn btn-success btn-lg">
						<?= !empty($edit['id']) ? 'Сохранить' : 'Создать пост' ?>
					</button>
					<?php if ($edit !== null): ?>
					<a href="/admin/blog.php" class="btn btn-secondary btn-lg">Отменить</a>
					<?php endif; ?>
				</div>

			</div><!-- /.blog-form-wrapper -->
		</form>

		<script>
		/* ══════════════════════════════════════════════════════════════
		   TAG WIDGET
		   Все теги из БД отсортированы по частоте использования.
		   При пустом поле — показываем первые POPULAR_LIMIT тегов.
		   При вводе — фильтруем и подсвечиваем совпадение.
		══════════════════════════════════════════════════════════════ */
		(function() {
			const ALL_TAGS = <?= json_encode($allTagsList, JSON_UNESCAPED_UNICODE) ?>;
			const POPULAR_LIMIT = 8; // Сколько тегов показывать без ввода

			const hiddenInput = document.getElementById('tagsHidden');
			const pillsEl = document.getElementById('tagPills');
			const tagInput = document.getElementById('tagInput');
			const dropdown = document.getElementById('tagDropdown');
			const widget = document.getElementById('tagWidget');

			// Разбираем начальное значение из скрытого поля
			let selected = hiddenInput.value ?
				hiddenInput.value.split(',').map(t => t.trim()).filter(Boolean) : [];

			let highlightIdx = -1;

			// ── Утилиты ──────────────────────────────────────────────
			function escHtml(str) {
				return String(str)
					.replace(/&/g, '&amp;')
					.replace(/</g, '&lt;')
					.replace(/>/g, '&gt;');
			}

			function sync() {
				hiddenInput.value = selected.join(', ');
			}

			// ── Пилюли ───────────────────────────────────────────────
			function renderPills() {
				pillsEl.innerHTML = '';
				selected.forEach(function(tag, i) {
					const pill = document.createElement('span');
					pill.className = 'tag-pill';
					pill.innerHTML =
						'#' + escHtml(tag) +
						' <button type="button" class="tag-pill-remove" title="Удалить тег">×</button>';
					pill.querySelector('button').addEventListener('click', function() {
						selected.splice(i, 1);
						renderPills();
						sync();
						tagInput.focus();
					});
					pillsEl.appendChild(pill);
				});
			}

			// ── Добавить тег ─────────────────────────────────────────
			function addTag(name) {
				name = name.trim();
				if (!name) return;
				// Проверяем дубль без учёта регистра
				if (selected.some(function(t) {
						return t.toLowerCase() === name.toLowerCase();
					})) {
					tagInput.value = '';
					hideDropdown();
					return;
				}
				selected.push(name);
				renderPills();
				sync();
				tagInput.value = '';
				hideDropdown();
			}

			// ── Dropdown ──────────────────────────────────────────────
			function buildItem(tag, query) {
				const item = document.createElement('div');
				item.className = 'tag-dropdown-item';

				let label;
				if (query) {
					// Подсвечиваем совпадающую часть
					const idx = tag.toLowerCase().indexOf(query.toLowerCase());
					if (idx !== -1) {
						label =
							escHtml(tag.slice(0, idx)) +
							'<mark>' + escHtml(tag.slice(idx, idx + query.length)) + '</mark>' +
							escHtml(tag.slice(idx + query.length));
					} else {
						label = escHtml(tag);
					}
				} else {
					label = escHtml(tag);
				}
				item.innerHTML = '🏷 ' + label;

				item.addEventListener('mousedown', function(e) {
					e.preventDefault(); // не убирать фокус с input
					addTag(tag);
				});
				return item;
			}

			function showDropdown(query) {
				dropdown.innerHTML = '';
				highlightIdx = -1;

				const q = query.trim();

				// Теги, не выбранные ещё
				const available = ALL_TAGS.filter(function(t) {
					return !selected.some(function(s) {
						return s.toLowerCase() === t.toLowerCase();
					});
				});

				if (q === '') {
					// ── Пустой ввод: показываем популярные теги ──────
					const popular = available.slice(0, POPULAR_LIMIT);
					if (popular.length === 0) {
						const empty = document.createElement('div');
						empty.className = 'tag-dropdown-empty';
						empty.textContent = 'Нет тегов. Введите название нового.';
						dropdown.appendChild(empty);
					} else {
						const header = document.createElement('div');
						header.className = 'tag-dropdown-header';
						header.textContent = 'Популярные теги';
						dropdown.appendChild(header);
						popular.forEach(function(tag) {
							dropdown.appendChild(buildItem(tag, ''));
						});
					}
					dropdown.style.display = 'block';
					return;
				}

				// ── Есть текст: фильтруем ─────────────────────────────
				const matches = available.filter(function(t) {
					return t.toLowerCase().includes(q.toLowerCase());
				});

				if (matches.length > 0) {
					const header = document.createElement('div');
					header.className = 'tag-dropdown-header';
					header.textContent = 'Существующие теги';
					dropdown.appendChild(header);
					matches.slice(0, 12).forEach(function(tag) {
						dropdown.appendChild(buildItem(tag, q));
					});
				}

				// «Создать новый» если точного совпадения нет
				const exactExists = ALL_TAGS.some(function(t) {
					return t.toLowerCase() === q.toLowerCase();
				});
				const alreadySelected = selected.some(function(s) {
					return s.toLowerCase() === q.toLowerCase();
				});
				if (!exactExists && !alreadySelected) {
					const item = document.createElement('div');
					item.className = 'tag-dropdown-item new-tag';
					item.innerHTML = '+ Создать тег «' + escHtml(q) + '»';
					item.addEventListener('mousedown', function(e) {
						e.preventDefault();
						addTag(q);
					});
					dropdown.appendChild(item);
				}

				if (dropdown.children.length === 0) {
					hideDropdown();
					return;
				}

				dropdown.style.display = 'block';
			}

			function hideDropdown() {
				dropdown.style.display = 'none';
				highlightIdx = -1;
			}

			function getItems() {
				return Array.from(dropdown.querySelectorAll('.tag-dropdown-item'));
			}

			function setHighlight(idx) {
				const items = getItems();
				items.forEach(function(el, i) {
					el.classList.toggle('active', i === idx);
				});
				highlightIdx = idx;
			}

			// ── События ──────────────────────────────────────────────
			// Клик по виджету фокусирует поле
			widget.addEventListener('click', function() {
				tagInput.focus();
			});

			tagInput.addEventListener('focus', function() {
				showDropdown(tagInput.value);
			});

			tagInput.addEventListener('input', function() {
				showDropdown(tagInput.value);
			});

			tagInput.addEventListener('blur', function() {
				// Небольшая задержка чтобы mousedown на item успел сработать
				setTimeout(hideDropdown, 200);
			});

			tagInput.addEventListener('keydown', function(e) {
				const items = getItems();

				switch (e.key) {
					case 'ArrowDown':
						e.preventDefault();
						setHighlight(Math.min(highlightIdx + 1, items.length - 1));
						break;

					case 'ArrowUp':
						e.preventDefault();
						setHighlight(Math.max(highlightIdx - 1, 0));
						break;

					case 'Enter':
					case ',':
						e.preventDefault();
						if (highlightIdx >= 0 && items[highlightIdx]) {
							// Вытаскиваем имя тега: убираем «+ Создать тег «…»»,
							// эмодзи и HTML-подсветку
							const rawText = items[highlightIdx].textContent
								.replace(/^\+\s*Создать тег\s*«(.+)»$/, '$1')
								.replace(/^🏷\s*/, '')
								.trim();
							addTag(rawText);
						} else if (tagInput.value.trim()) {
							addTag(tagInput.value.trim());
						}
						break;

					case 'Backspace':
						if (!tagInput.value && selected.length) {
							selected.pop();
							renderPills();
							sync();
						}
						break;

					case 'Escape':
						hideDropdown();
						break;
				}
			});

			// Первичная отрисовка пилюль из уже сохранённых тегов
			renderPills();
		})();

		/* ══════════════════════════════════════════════════════════════
		   SLUG AUTO-GENERATION
		══════════════════════════════════════════════════════════════ */
		(function() {
			const cyrMap = {
				'а': 'a',
				'б': 'b',
				'в': 'v',
				'г': 'g',
				'д': 'd',
				'е': 'e',
				'ё': 'io',
				'ж': 'zh',
				'з': 'z',
				'и': 'i',
				'й': 'y',
				'к': 'k',
				'л': 'l',
				'м': 'm',
				'н': 'n',
				'о': 'o',
				'п': 'p',
				'р': 'r',
				'с': 's',
				'т': 't',
				'у': 'u',
				'ф': 'f',
				'х': 'h',
				'ц': 'ts',
				'ч': 'ch',
				'ш': 'sh',
				'щ': 'shb',
				'ъ': '',
				'ы': 'y',
				'ь': '',
				'э': 'e',
				'ю': 'yu',
				'я': 'ya',
				'і': 'i',
				'ї': 'yi',
				'є': 'ye',
				'ґ': 'g',
			};
			const fullMap = {};
			Object.entries(cyrMap).forEach(function([k, v]) {
				fullMap[k] = v;
				fullMap[k.toUpperCase()] = v;
			});

			function generateClientSlug(text) {
				return text.split('').map(function(c) {
						return fullMap[c] !== undefined ? fullMap[c] : c;
					}).join('')
					.toLowerCase()
					.replace(/[^a-z0-9\s\-]/g, '')
					.replace(/\s+/g, '-')
					.replace(/\-+/g, '-')
					.replace(/^\-+|\-+$/g, '');
			}

			const titleInput = document.querySelector('input[name="title"]');
			const slugInput = document.querySelector('input[name="slug"]');
			if (titleInput && slugInput) {
				titleInput.addEventListener('input', function() {
					if (!slugInput.value) {
						slugInput.value = generateClientSlug(titleInput.value) || '';
					}
				});
			}
		})();

		/* ══════════════════════════════════════════════════════════════
		   CKEDITOR
		══════════════════════════════════════════════════════════════ */
		ClassicEditor
			.create(document.querySelector('#content'), {
				toolbar: {
					items: [
						'undo', 'redo', '|', 'heading', '|',
						'bold', 'italic', '|',
						'bulletedList', 'numberedList', '|',
						'link', 'imageUpload', 'blockQuote', 'insertTable', '|',
						'removeFormat'
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
						},
					]
				},
				simpleUpload: {
					uploadUrl: '/admin/blog-upload.php'
				},
				table: {
					contentToolbar: ['tableColumn', 'tableRow', 'mergeTableCells']
				},
				language: 'ru'
			})
			.then(function(editor) {
				window.blogEditor = editor;

				const form = document.getElementById('blogForm');
				const contentArea = document.getElementById('content');
				const contentError = document.getElementById('contentError');

				form.addEventListener('submit', function(e) {
					contentArea.value = editor.getData();

					const text = (contentArea.value || '')
						.replace(/<[^>]*>/g, ' ')
						.replace(/&nbsp;/g, ' ')
						.replace(/\s+/g, ' ')
						.trim();

					if (!text.length) {
						contentError.textContent = 'Поле "Полное содержимое" обязательно';
						contentError.style.display = 'block';
						e.preventDefault();
						return false;
					}
					contentError.style.display = 'none';

					if (!form.checkValidity()) {
						e.preventDefault();
						form.reportValidity();
						return false;
					}
					return true;
				});
			})
			.catch(function(err) {
				console.error(err);
			});

		/* ══════════════════════════════════════════════════════════════
		   IMAGE PREVIEW WIDGET
		══════════════════════════════════════════════════════════════ */
		(function() {
			const imageUpload = document.getElementById('blogImageUpload');
			if (!imageUpload) return;

			const imagePreview = document.getElementById('blogImagePreview');
			const btnRemove = document.getElementById('blogBtnRemove');

			imagePreview.addEventListener('click', function() {
				imageUpload.click();
			});

			imageUpload.addEventListener('change', function(e) {
				const file = e.target.files[0];
				if (!file) return;
				const reader = new FileReader();
				reader.onload = function(ev) {
					imagePreview.innerHTML =
						'<img src="' + ev.target.result + '" alt="Preview" class="image-preview-img">';
					btnRemove.classList.add('show');
				};
				reader.readAsDataURL(file);
			});

			window.blogRemoveImage = function(event) {
				event.preventDefault();
				event.stopPropagation();
				document.getElementById('featuredImageInput').value = '';
				imageUpload.value = '';
				imagePreview.innerHTML = '<span>📷 Нажмите для выбора</span>';
				btnRemove.classList.remove('show');
			};
		})();
		</script>

		<!-- ── Список всех постов ── -->
		<h3 style="margin-top:40px;">Все посты (<?= count($posts) ?>)</h3>
  <div class="table-responsive">
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
							<button type="submit" class="btn btn-sm btn-danger" onclick="return confirm('Удалить пост?')">✕
								Удалить</button>
						</form>
					</td>
				</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
  </div><!-- /.table-responsive -->

	</div><!-- /.container -->
</body>

</html>
<?php

declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';
admin_require_auth();
$pdo = dev_db_connection();

function save_product_image_upload(array $file, array &$errors = [], array &$createdFiles = []): ?string
{
	$error = (int)($file['error'] ?? UPLOAD_ERR_NO_FILE);
	if ($error !== UPLOAD_ERR_OK) {
		if ($error !== UPLOAD_ERR_NO_FILE) {
			$errors[] = 'Не удалось загрузить файл «' . (string)($file['name'] ?? '') . '». Код ошибки: ' . $error . '.';
		}
		return null;
	}
	if (empty($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
		$errors[] = 'Загруженный файл не прошёл проверку безопасности.';
		return null;
	}
	if ((int)($file['size'] ?? 0) > 5 * 1024 * 1024) {
		$errors[] = 'Файл «' . (string)($file['name'] ?? '') . '» превышает лимит 5 МБ.';
		return null;
	}
	$ext = strtolower(pathinfo((string)($file['name'] ?? ''), PATHINFO_EXTENSION));
	$allowedMimes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
	$finfo = function_exists('finfo_open') ? finfo_open(FILEINFO_MIME_TYPE) : false;
	$mime = $finfo ? finfo_file($finfo, $file['tmp_name']) : null;
	if ($finfo) {
		finfo_close($finfo);
	}
	if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif'], true)
		|| ($mime !== null && !in_array($mime, $allowedMimes, true))
		|| @getimagesize($file['tmp_name']) === false) {
		$errors[] = 'Файл «' . (string)($file['name'] ?? '') . '» имеет неподдерживаемый формат.';
		return null;
	}
	$dir = __DIR__ . '/../data/uploads/products';
	if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
		$errors[] = 'Не удалось создать каталог загрузок изображений.';
		return null;
	}
	$htaccess = $dir . '/.htaccess';
	if (!is_file($htaccess)) {
		@file_put_contents($htaccess, "php_flag engine off\nAddType text/plain .php .phtml .phar\n");
	}
	$fileName = bin2hex(random_bytes(10)) . '.' . $ext;
	$target = $dir . '/' . $fileName;
	if (!move_uploaded_file($file['tmp_name'], $target)) {
		$errors[] = 'Не удалось сохранить файл «' . (string)($file['name'] ?? '') . '».';
		return null;
	}
	@chmod($target, 0644);
	convert_to_webp($target);
	$createdFiles[] = 'data/uploads/products/' . $fileName;
	return 'data/uploads/products/' . $fileName;
}

function product_images_table_exists(PDO $pdo): bool
{
	try {
		$pdo->query('SELECT 1 FROM product_images LIMIT 1');
		return true;
	} catch (Throwable $e) {
		return false;
	}
}

function product_flash(string $message, string $type = 'success'): void
{
	$_SESSION['product_flash'] = ['message' => $message, 'type' => $type];
}

function delete_product_image_file(string $image): void
{
	$relative = ltrim($image, '/');
	if (strpos($relative, 'data/uploads/products/') !== 0) {
		return;
	}
	$path = __DIR__ . '/../' . $relative;
	if (is_file($path)) {
		@unlink($path);
	}
	$webpPath = preg_replace('/\.(jpe?g|png)$/i', '.webp', $path);
	if ($webpPath && $webpPath !== $path && is_file($webpPath)) {
		@unlink($webpPath);
	}
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_gallery_image') {
	header('Content-Type: application/json; charset=utf-8');
	if (!validate_csrf_token()) {
		http_response_code(403);
		echo json_encode(['success' => false, 'message' => 'CSRF check failed'], JSON_UNESCAPED_UNICODE);
		exit;
	}

	$productId = (int)($_POST['product_id'] ?? 0);
	$imageId = (int)($_POST['image_id'] ?? 0);
	if ($productId <= 0 || $imageId <= 0 || !product_images_table_exists($pdo)) {
		http_response_code(400);
		echo json_encode(['success' => false, 'message' => 'Некорректные параметры изображения.'], JSON_UNESCAPED_UNICODE);
		exit;
	}

	try {
		$stmt = $pdo->prepare('SELECT image FROM product_images WHERE id = :id AND product_id = :product_id');
		$stmt->execute(['id' => $imageId, 'product_id' => $productId]);
		$image = $stmt->fetchColumn();
		if ($image === false) {
			http_response_code(404);
			echo json_encode(['success' => false, 'message' => 'Изображение не найдено.'], JSON_UNESCAPED_UNICODE);
			exit;
		}

		$pdo->beginTransaction();
		$delete = $pdo->prepare('DELETE FROM product_images WHERE id = :id AND product_id = :product_id');
		$delete->execute(['id' => $imageId, 'product_id' => $productId]);
		$pdo->commit();
		delete_product_image_file((string)$image);

		echo json_encode(['success' => true], JSON_UNESCAPED_UNICODE);
	} catch (Throwable $e) {
		if ($pdo->inTransaction()) {
			$pdo->rollBack();
		}
		http_response_code(500);
		echo json_encode(['success' => false, 'message' => 'Не удалось удалить изображение.'], JSON_UNESCAPED_UNICODE);
	}
	exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $pdo instanceof PDO) {
	if (!validate_csrf_token()) {
		http_response_code(403);
		exit('CSRF check failed');
	}
	$id = (int)($_POST['id'] ?? 0);
	$data = [
		'external_id' => trim((string)($_POST['external_id'] ?? '')),
		'cat_number' => trim((string)($_POST['cat_number'] ?? '')),
		'name' => trim((string)($_POST['name'] ?? '')),
		'old_price' => (float)($_POST['old_price'] ?? 0),
		'price' => (float)($_POST['price'] ?? 0),
		'image' => trim((string)($_POST['image'] ?? '')),
		'link' => trim((string)($_POST['link'] ?? '')),
		'short_desc' => (string)($_POST['short_desc'] ?? ''),
		'desc' => (string)($_POST['desc'] ?? ''),
		'full_desc' => (string)($_POST['full_desc'] ?? ''),
		'in_stock' => !empty($_POST['in_stock']) ? 1 : 0,
		'status' => trim((string)($_POST['status'] ?? '')),
		'seo_title' => trim((string)($_POST['seo_title'] ?? '')),
		'seo_description' => trim((string)($_POST['seo_description'] ?? '')),
		'volume' => trim((string)($_POST['volume'] ?? '')),
		'sort_order' => (int)($_POST['sort_order'] ?? 0),

	];

	$uploadErrors = [];
	$createdFiles = [];
	$oldImage = '';
	$mainImage = save_product_image_upload($_FILES['image_upload'] ?? [], $uploadErrors, $createdFiles);
	if ($mainImage !== null) {
		$data['image'] = $mainImage;
	}
	$mainCreatedFilesCount = count($createdFiles);
	$galleryFiles = $_FILES['gallery_uploads'] ?? [];
	$galleryCount = is_array($galleryFiles['name'] ?? null) ? count($galleryFiles['name']) : 0;
	if ($galleryCount > 10) {
		$uploadErrors[] = 'Можно загрузить не более 10 дополнительных фотографий за один раз.';
		$galleryCount = 10;
	}
	$deletedImages = [];
	try {
		$pdo->beginTransaction();
		if ($id > 0) {
			$oldStmt = $pdo->prepare('SELECT image FROM products WHERE id = ? FOR UPDATE');
			$oldStmt->execute([$id]);
			$oldImage = (string)($oldStmt->fetchColumn() ?: '');
			$data['id'] = $id;
			$sql = 'UPDATE products SET external_id=:external_id, cat_number=:cat_number, name=:name, old_price=:old_price, price=:price, image=:image, link=:link, short_desc=:short_desc, `desc`=:desc, full_desc=:full_desc, in_stock=:in_stock, status=:status, seo_title=:seo_title, seo_description=:seo_description, volume=:volume, sort_order=:sort_order WHERE id=:id';
		} else {
			$sql = 'INSERT INTO products (external_id, cat_number, name, old_price, price, image, link, short_desc, `desc`, full_desc, in_stock, status, seo_title, seo_description, volume, sort_order) VALUES (:external_id,:cat_number,:name,:old_price,:price,:image,:link,:short_desc,:desc,:full_desc,:in_stock,:status,:seo_title,:seo_description,:volume,:sort_order)';
		}
		$pdo->prepare($sql)->execute($data);
		$productId = $id > 0 ? $id : (int)$pdo->lastInsertId();
		$galleryAvailable = product_images_table_exists($pdo);
		if ($galleryAvailable && $productId > 0) {
			$deleteIds = array_values(array_unique(array_filter(array_map('intval', (array)($_POST['delete_gallery'] ?? [])))));
			if ($deleteIds) {
				$placeholders = implode(',', array_fill(0, count($deleteIds), '?'));
				$selectImages = $pdo->prepare("SELECT image FROM product_images WHERE product_id = ? AND id IN ($placeholders)");
				$selectImages->execute(array_merge([$productId], $deleteIds));
				$deletedImages = array_map(static fn(array $row): string => (string)$row['image'], $selectImages->fetchAll());
				$pdo->prepare("DELETE FROM product_images WHERE product_id = ? AND id IN ($placeholders)")->execute(array_merge([$productId], $deleteIds));
			}
			$galleryOrder = json_decode((string)($_POST['gallery_order'] ?? ''), true);
			if (is_array($galleryOrder)) {
				$updateGalleryOrder = $pdo->prepare('UPDATE product_images SET sort_order = :sort_order WHERE id = :id AND product_id = :product_id');
				foreach (array_values(array_filter(array_map('intval', $galleryOrder))) as $sortOrder => $galleryImageId) {
					$updateGalleryOrder->execute(['sort_order' => $sortOrder, 'id' => $galleryImageId, 'product_id' => $productId]);
				}
			}
			if ($galleryCount > 0) {
				$nextSort = (int)$pdo->query('SELECT COALESCE(MAX(sort_order), -1) + 1 FROM product_images WHERE product_id = ' . (int)$productId)->fetchColumn();
				$insertGallery = $pdo->prepare('INSERT INTO product_images (product_id, image, sort_order) VALUES (:product_id, :image, :sort_order)');
				for ($i = 0; $i < $galleryCount; $i++) {
					$galleryFile = ['name' => $galleryFiles['name'][$i] ?? '', 'tmp_name' => $galleryFiles['tmp_name'][$i] ?? '', 'size' => $galleryFiles['size'][$i] ?? 0, 'error' => $galleryFiles['error'][$i] ?? UPLOAD_ERR_NO_FILE];
					$galleryImage = save_product_image_upload($galleryFile, $uploadErrors, $createdFiles);
					if ($galleryImage !== null) {
						$insertGallery->execute(['product_id' => $productId, 'image' => $galleryImage, 'sort_order' => $nextSort++]);
					}
				}
			}
		} elseif (!$galleryAvailable && ($galleryCount > 0 || !empty($_POST['delete_gallery'] ?? []))) {
			foreach (array_slice($createdFiles, $mainCreatedFilesCount) as $createdFile) {
				delete_product_image_file($createdFile);
			}
			$createdFiles = array_slice($createdFiles, 0, $mainCreatedFilesCount);
			product_flash('Товар сохранён, но галерея недоступна: примените database/migration_product_images.sql.', 'warning');
		}
		$pdo->commit();
	} catch (Throwable $e) {
		if ($pdo->inTransaction()) {
			$pdo->rollBack();
		}
		foreach ($createdFiles as $createdFile) {
			delete_product_image_file($createdFile);
		}
		product_flash('Товар не сохранён: произошла ошибка базы данных.', 'danger');
		header('Location: /admin/products.php');
		exit;
	}
	if ($oldImage !== '' && $oldImage !== $data['image']) {
		delete_product_image_file($oldImage);
	}
	foreach ($deletedImages as $deletedImage) {
		delete_product_image_file($deletedImage);
	}
	if ($uploadErrors) {
		product_flash('Товар сохранён. ' . implode(' ', $uploadErrors), 'warning');
	} elseif (!isset($_SESSION['product_flash'])) {
		product_flash('Товар успешно сохранён.');
	}
	header('Location: /admin/products.php');
	exit;
}

// ИСПРАВЛЕНО: ORDER BY sort_order ASC вместо id DESC,
// чтобы админ видел товары в том же порядке что и посетитель сайта.
// id ASC — тайбрейкер для товаров с одинаковым sort_order (новые = 0).
$products = [];
if ($pdo instanceof PDO) {
	$products = $pdo->query('SELECT * FROM products ORDER BY sort_order ASC, id ASC LIMIT 500')->fetchAll();
}
$edit = null;
$gallery = [];
	if (isset($_GET['edit'])) {
	$editId = (int)$_GET['edit'];
	$edit = []; // пустой массив = новый товар; null = список без формы
	foreach ($products as $p) {
		if ((int)$p['id'] === $editId) {
			$edit = $p;
			break;
		}
	}
	if (!empty($edit['id']) && $pdo instanceof PDO) {
		if (!product_images_table_exists($pdo)) {
				product_flash('Галерея недоступна: примените database/migration_product_images.sql.', 'warning');
		} else {
			try {
				$galleryStmt = $pdo->prepare('SELECT id, image, sort_order FROM product_images WHERE product_id = :product_id ORDER BY sort_order ASC, id ASC');
				$galleryStmt->execute(['product_id' => (int)$edit['id']]);
				$gallery = $galleryStmt->fetchAll();
			} catch (Throwable $e) {
				product_flash('Галерея недоступна: примените database/migration_product_images.sql.', 'warning');
			}
			}
		}
	}
		$productFlash = $_SESSION['product_flash'] ?? null;
	unset($_SESSION['product_flash']);
?>
<!doctype html>
<html lang="ru">

<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<!-- CSRF токен для AJAX-запросов (drag-and-drop сохранение порядка) -->
	<meta name="csrf-token" content="<?= csrf_token() ?>">
	<title>Админка - Товары</title>
	<link rel="stylesheet" href="/css/bootstrap.min.css">
	<link rel="stylesheet" href="/css/admin.css">
	<!-- SortableJS — drag-and-drop библиотека для таблицы товаров -->
	<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.2/Sortable.min.js"></script>
	<!-- CKEditor 5 — визуальный редактор, используемый в блоге -->
	<script src="https://cdn.ckeditor.com/ckeditor5/41.1.0/classic/ckeditor.js"></script>
	<style>
		.product-form-wrapper {
			display: flex;
			flex-direction: column;
			gap: 30px;
			margin-bottom: 40px;
		}

		.product-form-top {
			display: grid;
			grid-template-columns: 1.5fr 1fr;
			gap: 30px;
			align-items: start;
		}

		.product-form-right {
			display: flex;
			flex-direction: column;
		}

		.product-form-left {
			display: flex;
			flex-direction: column;
			gap: 20px;
		}

		.image-section {
			position: relative;
			background: #f8f9fa;
			border-radius: 8px;
			padding: 20px;
			text-align: center;
		}

		.image-preview-container {
			position: relative;
			display: block;
			width: 100%;
			height: auto;
			margin: 0 auto;
			overflow: visible;
		}

		.image-placeholder {
			width: 100%;
			aspect-ratio: 1 / 1;
			background: #e9ecef;
			border: 2px dashed #dee2e6;
			border-radius: 8px;
			display: flex;
			align-items: center;
			justify-content: center;
			cursor: pointer;
			transition: all 0.3s;
			color: #999;
			font-size: 16px;
			position: relative;
			overflow: hidden;
		}

		.image-preview-img {
			width: 100%;
			height: 100%;
			object-fit: contain;
			border-radius: 8px;
			display: block;
		}

		.image-placeholder:hover .btn-remove-image.show {
			opacity: 1;
			visibility: visible;
		}

		.btn-remove-image {
			position: absolute;
			top: 0px;
			right: 0px;
			background: #dc3545;
			border: none;
			border-radius: 50%;
			width: 36px;
			height: 36px;
			padding: 0;
			display: none;
			align-items: center;
			justify-content: center;
			cursor: pointer;
			transition: all 0.2s;
			z-index: 20;
			box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
		}

		.btn-remove-image:hover {
			background: #c82333;
			transform: scale(1.15);
		}

		.btn-remove-image.show {
			display: flex;
		}

		.btn-remove-image svg {
			width: 20px;
			height: 20px;
			display: block;
			margin: 0 auto;
			stroke: white;
			stroke-width: 2.5;
			stroke-linecap: round;
			stroke-linejoin: round;
		}

		.image-upload-input {
			display: none !important;
			visibility: hidden !important;
		}

		.gallery-manager {
			clear: both;
			position: relative;
			z-index: 1;
			width: 100%;
			margin-top: 24px;
			padding: 20px 0 0;
			border-top: 1px solid #dee2e6;
			text-align: left;
		}

		.gallery-manager label {
			font-weight: 600;
			color: #333;
		}

		.gallery-upload-input {
			display: block;
			width: 100%;
			margin-top: 8px;
			font-size: 13px;
		}

		.gallery-help {
			display: block;
			margin-top: 6px;
			color: #777;
			font-size: 12px;
		}

		.gallery-existing {
			display: grid;
			grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
			gap: 12px;
			margin-top: 12px;
		}

		.gallery-upload-preview {
			margin-top: 16px;
		}

		.gallery-upload-preview:empty {
			display: none;
		}

		.gallery-existing-item {
			position: relative;
			min-width: 0;
			min-height: 150px;
			padding: 8px;
			border: 1px solid #dee2e6;
			border-radius: 8px;
			background: #fff;
			font-size: 11px;
			overflow: hidden;
		}

		.gallery-existing-item img {
			display: block;
			width: 100%;
			height: 130px;
			object-fit: contain;
			margin-bottom: 8px;
		}

		.gallery-existing-item .gallery-remove-image {
			top: 6px;
			right: 6px;
			width: 30px;
			height: 30px;
		}

		.gallery-existing-item .gallery-remove-image svg {
			width: 16px;
			height: 16px;
		}

		.gallery-upload-preview .gallery-existing-item {
			border-style: dashed;
		}

		.gallery-upload-preview .gallery-existing-item small {
			display: block;
			overflow: hidden;
			text-overflow: ellipsis;
			white-space: nowrap;
		}

		.gallery-order-buttons {
			display: flex;
			gap: 4px;
			margin-bottom: 4px;
		}

		.gallery-move {
			flex: 1;
			padding: 2px 4px;
			border: 1px solid #ced4da;
			border-radius: 3px;
			background: #fff;
			cursor: pointer;
			line-height: 1;
		}

		.form-group-wrapper {
			margin-bottom: 15px;
		}

		.form-group-wrapper label {
			font-weight: 600;
			margin-bottom: 5px;
			display: block;
			color: #333;
		}

		.form-group-wrapper input,
		.form-group-wrapper textarea,
		.form-group-wrapper select {
			width: 100%;
		}

		.form-row-inline {
			display: grid;
			grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
			gap: 15px;
		}

		.form-row-inline.two-cols {
			grid-template-columns: repeat(2, 1fr);
		}

		.form-row-inline.three-cols {
			grid-template-columns: repeat(3, 1fr);
		}

		.form-row-inline.full {
			grid-template-columns: 1fr;
		}

		.status-section {
			background: #f8f9fa;
			border-radius: 8px;
			padding: 15px;
		}

		.status-row {
			display: grid;
			grid-template-columns: 1fr 1fr;
			gap: 12px;
			align-items: flex-end;
		}

		.status-row .form-group-wrapper {
			margin-bottom: 0;
		}

		.status-row .form-group-wrapper label {
			margin-bottom: 3px;
			font-size: 0.9rem;
		}

		.status-badge {
			display: inline-block;
			padding: 8px 16px;
			border-radius: 6px;
			font-weight: 600;
			text-align: center;
			cursor: pointer;
			transition: all 0.3s;
			border: none;
			width: 100%;
		}

		.status-badge.active {
			background: #28a745;
			color: white;
		}

		.status-badge.inactive {
			background: #dc3545;
			color: white;
		}

		/* ── Кольорові рядки таблиці за статусом ── */
		tr.table-row-active {
			background-color: #d4edda !important;
		}

		/* зелений */
		tr.table-row-active:hover {
			background-color: #c3e6cb !important;
		}

		tr.table-row-preorder {
			background-color: #fff3cd !important;
		}

		/* жовтий */
		tr.table-row-preorder:hover {
			background-color: #ffeeba !important;
		}

		tr.table-row-disabled {
			background-color: #f8d7da !important;
		}

		/* червоний */
		tr.table-row-disabled:hover {
			background-color: #f5c6cb !important;
		}

		.descriptions-section {
			background: #f8f9fa;
			border-radius: 8px;
			padding: 20px;
			margin-bottom: 20px;
		}

		.descriptions-section h5 {
			margin-bottom: 15px;
			color: #333;
			font-weight: 600;
		}

		.descriptions-grid {
			display: grid;
			grid-template-columns: repeat(1, 1fr);
			gap: 15px;
		}

		.descriptions-grid .form-group-wrapper {
			margin-bottom: 0;
		}

		.descriptions-grid .form-group-wrapper label {
			margin-bottom: 3px;
		}

		.descriptions-grid textarea {
			min-height: 100px;
		}

		.descriptions-grid .ck-editor {
			width: 100%;
		}

		.descriptions-grid .ck-content {
			min-height: 180px;
		}

		.seo-section {
			background: #e7f3ff;
			border-left: 4px solid #007bff;
			border-radius: 8px;
			padding: 20px;
			margin-bottom: 20px;
		}

		.seo-section h5 {
			margin-bottom: 15px;
			color: #0056b3;
			font-weight: 600;
		}

		.form-actions {
			margin-top: 30px;
			padding-top: 20px;
			border-top: 1px solid #dee2e6;
			display: flex;
			gap: 10px;
		}



		.form-actions button,
		.form-actions a {
			padding: 12px 24px;
			font-size: 16px;
		}

		.checkbox-wrapper {
			display: flex;
			align-items: center;
			gap: 10px;
			margin-top: 10px;
		}

		.checkbox-wrapper input[type="checkbox"] {
			width: auto;
			margin: 0;
		}

		.checkbox-wrapper label {
			margin: 0;
			font-weight: normal;
		}

		.products-list-section {
			margin-top: 50px;
		}

		/* ====== Drag-and-drop стили ====== */
		.drag-handle {
			cursor: grab;
			color: #aaa;
			font-size: 20px;
			padding: 0 6px;
			user-select: none;
			text-align: center;
			line-height: 1;
		}

		.drag-handle:active {
			cursor: grabbing;
		}

		/* Строка-привидение при перетягивании */
		.sortable-ghost {
			opacity: 0.35;
			background: #cce5ff !important;
		}

		/* Выбранная строка при перетягивании */
		.sortable-chosen {
			background: #e8f4fd !important;
			box-shadow: 0 2px 8px rgba(0, 123, 255, .25);
		}

		/* Кнопка сохранения — скрыта пока нет изменений */
		#saveOrderBtn {
			display: none;
		}

		#saveOrderBtn.has-changes {
			display: inline-block;
		}

		.save-success {
			color: #28a745;
			font-weight: 600;
		}

		.products-list-header {
			display: flex;
			align-items: center;
			gap: 15px;
			margin-bottom: 12px;
			flex-wrap: wrap;
		}

		.products-list-header h4 {
			margin: 0;
			color: #333;
		}

		/* ================================== */

		@media (max-width: 1200px) {
			.product-form-top {
				grid-template-columns: 1fr;
			}

			.descriptions-grid {
				grid-template-columns: 1fr;
			}
		}

		@media (max-width: 768px) {
			.product-form-top {
				grid-template-columns: 1fr;
			}

			.image-preview-container {
				max-width: 100%;
				width: 100%;
			}

			.image-placeholder {
				aspect-ratio: 1 / 1;
				max-height: 350px;
			}

			.image-section {
				padding: 15px;
			}

			.form-row-inline.two-cols {
				grid-template-columns: 1fr;
			}

			.form-row-inline.three-cols {
				grid-template-columns: 1fr;
			}

			.status-row {
				grid-template-columns: 1fr;
			}

			.form-actions {
				flex-direction: column;
			}

			.form-actions button,
			.form-actions a {
				width: 100%;
			}
		}

		@media (max-width: 480px) {
			.image-placeholder {
				aspect-ratio: 1 / 1;
				max-height: 280px;
			}

			.product-form-wrapper {
				gap: 20px;
			}

			.status-section {
				padding: 12px;
			}

			.descriptions-section {
				padding: 15px;
			}

			.seo-section {
				padding: 15px;
			}

			.form-group-wrapper label {
				font-size: 0.9rem;
			}
		}
	</style>
</head>

<body>
	<div class="container">
		<?php require __DIR__ . '/_nav.php'; ?>
		<h3>Товары</h3>
		<?php if (is_array($productFlash)): ?>
			<div class="alert alert-<?= admin_h((string)($productFlash['type'] ?? 'info')) ?>">
				<?= admin_h((string)($productFlash['message'] ?? '')) ?>
			</div>
		<?php endif; ?>

		<?php if ($edit !== null): ?>
			<form id="productForm" method="post" enctype="multipart/form-data">
				<input type="hidden" name="id" value="<?= admin_h((string)($edit['id'] ?? '')) ?>">
				<input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
				<input type="hidden" name="image" id="imageInput" value="<?= admin_h((string)($edit['image'] ?? '')) ?>">

				<div class="product-form-wrapper">
					<!-- ВЕРХНЯЯ ЧАСТЬ - Две колонки -->
					<div class="product-form-top">
						<!-- ЛЕВАЯ ЧАСТЬ - Контент -->
						<div class="product-form-left">
							<!-- Статус и наличие -->
							<div class="status-section">
								<div class="status-row">
									<div class="form-group-wrapper">
										<label for="status">Статус</label>
										<select class="form-control" id="status" name="status">
											<option value="">Выбрать</option>
											<option value="active" <?= ($edit['status'] ?? '') === 'active' ? 'selected' : '' ?>>Активный
											</option>
											<option value="preorder" <?= ($edit['status'] ?? '') === 'preorder' ? 'selected' : '' ?>>Предзаказ
											</option>
											<option value="disabled" <?= ($edit['status'] ?? '') === 'disabled' ? 'selected' : '' ?>>Выключен
											</option>
										</select>
									</div>
									<div class="form-group-wrapper">
										<button type="button" class="status-badge <?= !empty($edit['in_stock']) ? 'active' : 'inactive' ?>"
											id="inStockToggle" onclick="toggleInStock()">
											<?= !empty($edit['in_stock']) ? '✓ В наличии' : '✗ Нет' ?>
										</button>
										<input type="hidden" id="in_stock" name="in_stock"
											value="<?= !empty($edit['in_stock']) ? '1' : '0' ?>">
									</div>
								</div>
							</div>
							<div class="form-group-wrapper">
								<label for="sort_order">Порядок сортировки <small style="color:#888;font-weight:normal">(устанавливается
										перетягиванием в списке)</small></label>
								<input type="number" class="form-control" id="sort_order" name="sort_order"
									value="<?= admin_h((string)($edit['sort_order'] ?? '0')) ?>">
							</div>
							<!-- Основные данные товара -->
							<div class="form-group-wrapper">
								<label for="external_id">ID товара *</label>
								<input type="text" class="form-control" id="external_id" name="external_id"
									value="<?= admin_h((string)($edit['external_id'] ?? '')) ?>" required>
							</div>

							<div class="form-row-inline two-cols">
								<div class="form-group-wrapper">
									<label for="cat_number">Код каталога</label>
									<input type="text" class="form-control" id="cat_number" name="cat_number"
										value="<?= admin_h((string)($edit['cat_number'] ?? '')) ?>">
								</div>
								<div class="form-group-wrapper">
									<label for="name">Название *</label>
									<input type="text" class="form-control" id="name" name="name"
										value="<?= admin_h((string)($edit['name'] ?? '')) ?>" required>
								</div>
							</div>

							<!-- Цены -->
							<div class="form-row-inline two-cols">
								<div class="form-group-wrapper">
									<label for="price">Текущая цена *</label>
									<input type="number" step="0.01" class="form-control" id="price" name="price"
										value="<?= admin_h((string)($edit['price'] ?? '0')) ?>" required>
								</div>
								<div class="form-group-wrapper">
									<label for="old_price">Старая цена</label>
									<input type="number" step="0.01" class="form-control" id="old_price" name="old_price"
										value="<?= admin_h((string)($edit['old_price'] ?? '0')) ?>">
								</div>
							</div>

							<!-- URL и Объем -->
							<div class="form-row-inline two-cols">
								<div class="form-group-wrapper">
									<label for="link">URL (Slug) *</label>
									<input type="text" class="form-control" id="link" name="link"
										value="<?= admin_h((string)($edit['link'] ?? '')) ?>" required>
								</div>
								<div class="form-group-wrapper">
									<label for="volume">Объем</label>
									<input type="text" class="form-control" id="volume" name="volume"
										value="<?= admin_h((string)($edit['volume'] ?? '')) ?>" placeholder="напр. 500мл, 1л">
								</div>
							</div>
						</div>

						<!-- ПРАВАЯ ЧАСТЬ - Фото -->
						<div class="product-form-right">
							<div class="image-section">
								<div class="image-preview-container">
									<div id="imagePreview" class="image-placeholder">
										<?php if (!empty($edit['image'])): ?>
											<img src="/<?= admin_h((string)$edit['image']) ?>" alt="Product" class="image-preview-img">
										<?php else: ?>
											<span>📷 Нажмите для загрузки</span>
										<?php endif; ?>
									</div>
									<button type="button" class="btn-remove-image <?= !empty($edit['image']) ? 'show' : '' ?>"
										id="btnRemoveImage" onclick="removeImage(event)">
										<svg viewBox="0 0 24 24" fill="none">
											<line x1="18" y1="6" x2="6" y2="18"></line>
											<line x1="6" y1="6" x2="18" y2="18"></line>
										</svg>
									</button>
									<input type="file" id="imageUpload" class="image-upload-input" name="image_upload" accept="image/*"
										style="display: none !important;">
								<div class="gallery-manager">
									<label for="galleryUploads">Дополнительные фото</label>
									<input type="file" id="galleryUploads" class="gallery-upload-input"
										name="gallery_uploads[]" accept="image/jpeg,image/png,image/webp,image/gif" multiple>
									<small class="gallery-help">Можно выбрать несколько файлов. Максимум 5 МБ на фото.</small>
									<div id="galleryUploadPreview" class="gallery-existing gallery-upload-preview" aria-live="polite"></div>
								<?php if ($gallery): ?>
									<input type="hidden" name="gallery_order" id="galleryOrder" value="<?= admin_h(json_encode(array_column($gallery, 'id'))) ?>">
									<div class="gallery-existing">
										<?php foreach ($gallery as $galleryImage): ?>
											<div class="gallery-existing-item" data-gallery-id="<?= (int)$galleryImage['id'] ?>">
												<img src="/<?= admin_h((string)$galleryImage['image']) ?>" alt="Дополнительное фото">
												<button type="button" class="btn-remove-image show gallery-remove-image"
													data-gallery-delete="<?= (int)$galleryImage['id'] ?>"
													onclick="removeGalleryImage(event, <?= (int)$galleryImage['id'] ?>)"
													aria-label="Удалить дополнительное фото">
													<svg viewBox="0 0 24 24" fill="none">
														<line x1="18" y1="6" x2="6" y2="18"></line>
														<line x1="6" y1="6" x2="18" y2="18"></line>
													</svg>
												</button>
												<div class="gallery-order-buttons">
													<button type="button" class="gallery-move" data-gallery-move="up" aria-label="Выше">↑</button>
													<button type="button" class="gallery-move" data-gallery-move="down" aria-label="Ниже">↓</button>
												</div>
											</div>
											<?php endforeach; ?>
										</div>
										<?php endif; ?>
									</div>
								</div>
								</div>
						</div>

					</div>

					<!-- НИЖНЯЯ ЧАСТЬ - Описания на всю ширину -->
					<div class="descriptions-section">
						<h5>📝 Описания</h5>
						<div class="descriptions-grid">
							<div class="form-group-wrapper">
								<label for="short_desc">Краткое</label>
								<textarea class="form-control product-description-editor" id="short_desc" rows="3"
									name="short_desc"><?= admin_h((string)($edit['short_desc'] ?? '')) ?></textarea>
							</div>

							<div class="form-group-wrapper">
								<label for="desc">Обычное</label>
								<textarea class="form-control product-description-editor" id="desc" rows="3"
									name="desc"><?= admin_h((string)($edit['desc'] ?? '')) ?></textarea>
							</div>

							<div class="form-group-wrapper">
								<label for="full_desc">Полное</label>
								<textarea class="form-control product-description-editor" id="full_desc" rows="3"
									name="full_desc"><?= admin_h((string)($edit['full_desc'] ?? '')) ?></textarea>
							</div>
						</div>
					</div>

					<!-- SEO модуль -->
					<div class="seo-section">
						<h5>🔍 SEO</h5>
						<div class="form-row-inline two-cols">
							<div class="form-group-wrapper">
								<label for="seo_title">SEO Title</label>
								<input type="text" class="form-control" id="seo_title" name="seo_title"
									value="<?= admin_h((string)($edit['seo_title'] ?? '')) ?>">
							</div>
							<div class="form-group-wrapper">
								<label for="seo_description">SEO Description</label>
								<textarea class="form-control" id="seo_description" rows="2"
									name="seo_description"><?= admin_h((string)($edit['seo_description'] ?? '')) ?></textarea>
							</div>
						</div>
					</div>
				</div>

				<!-- Кнопки действия -->
				<div class="form-actions">
					<button type="submit" class="btn btn-success btn-lg">💾 Сохранить</button>
					<a href="/admin/products.php" class="btn btn-secondary btn-lg">↩ Отмена</a>
				</div>
			</form>
		<?php endif; ?>

		<!-- Таблица товаров -->
		<div class="products-list-section">

			<!-- Заголовок + кнопка сохранения порядка -->
			<div class="products-list-header">
				<h4>📦 Список товаров</h4>
				<button id="saveOrderBtn" class="btn btn-warning btn-sm">
					💾 Сохранить порядок
				</button>
				<span id="saveStatus"></span>
			</div>

			<p style="color:#888;font-size:0.88rem;margin-bottom:10px;">
				⠿ Перетащите строку за иконку чтобы изменить порядок. После перестановки нажмите «Сохранить порядок».
			</p>

			<div class="table-responsive">
				<table class="table table-bordered table-striped">
					<thead>
						<tr>
							<th style="width:36px" title="Перетащите для сортировки">⠿</th>
							<th style="width:40px; display:none;">#</th>
							<th>ID AMO</th>
							<th>Код каталога</th>
							<th>Название</th>
							<th>Объем</th>
							<th>Цена</th>
							<th>Slug</th>
							<th>SEO</th>
							<th>Статус</th>
							<th></th>
						</tr>
					</thead>
					<tbody id="sortable-products">
						<?php foreach ($products as $i => $p):
							$st = (string)($p['status'] ?? '');
							$rowClass = match ($st) {
								'active'   => 'table-row-active',
								'preorder' => 'table-row-preorder',
								'disabled' => 'table-row-disabled',
								default    => '',
							};
							$statusLabel = match ($st) {
								'active'   => 'Активный',
								'preorder' => 'Предзаказ',
								'disabled' => 'Выключен',
								default    => '—',
							};
						?>
							<tr data-id="<?= (int)$p['id'] ?>" class="<?= $rowClass ?>">
								<td class="drag-handle" title="Перетащите для изменения порядка">⠿</td>
								<td class="row-position" style=" display:none;"><?= $i + 1 ?></td>
								<td><?= admin_h((string)$p['external_id']) ?></td>
								<td><?= admin_h((string)$p['cat_number']) ?></td>
								<td><?= admin_h((string)$p['name']) ?></td>
								<td><?= admin_h((string)$p['volume']) ?></td>
								<td><?= admin_h((string)$p['price']) ?></td>
								<td><?= admin_h((string)$p['link']) ?></td>
								<td><?= admin_h((string)$p['seo_title']) ?></td>
								<td><?= admin_h($statusLabel) ?></td>
								<td><a href="/admin/products.php?edit=<?= (int)$p['id'] ?>">Редактировать</a></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div><!-- /.table-responsive -->
		</div>

		<!-- Кнопка добавления нового товара -->
		<?php if ($edit === null): ?>
			<div style="margin-top: 20px;">
				<button type="button" class="btn btn-primary btn-lg" onclick="location.href='/admin/products.php?edit=0'">
					➕ Добавить новый товар
				</button>
			</div>
		<?php endif; ?>
	</div>

		<script>
			/* ====== Визуальные редакторы описаний товара ====== */
			(function() {
				var form = document.getElementById('productForm');
				var textareas = document.querySelectorAll('.product-description-editor');
				if (!form || !textareas.length || typeof ClassicEditor === 'undefined') return;

				var editors = [];
				var editorConfig = {
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
							}
						]
					},
					simpleUpload: {
						uploadUrl: '/admin/blog-upload.php'
					},
					table: {
						contentToolbar: ['tableColumn', 'tableRow', 'mergeTableCells']
					},
					language: 'ru'
				};

				Array.prototype.forEach.call(textareas, function(textarea) {
					ClassicEditor.create(textarea, editorConfig)
						.then(function(editor) {
							editors.push({ editor: editor, textarea: textarea });
						})
						.catch(function(error) {
							console.error('Не удалось загрузить редактор описания товара', error);
						});
				});

				form.addEventListener('submit', function() {
					editors.forEach(function(item) {
						item.textarea.value = item.editor.getData();
					});
				});
			})();

			/* ====== Порядок дополнительных фото ====== */
			(function() {
				var orderInput = document.getElementById('galleryOrder');
				var galleryList = document.querySelector('.gallery-existing');
				if (!orderInput || !galleryList) return;
				function syncGalleryOrder() {
					var ids = Array.prototype.map.call(galleryList.querySelectorAll('[data-gallery-id]'), function(item) {
						return parseInt(item.getAttribute('data-gallery-id'), 10);
					});
					orderInput.value = JSON.stringify(ids);
				}
					galleryList.addEventListener('click', function(event) {
					var button = event.target.closest('[data-gallery-move]');
					if (!button) return;
					var item = button.closest('[data-gallery-id]');
					if (!item) return;
					if (button.getAttribute('data-gallery-move') === 'up' && item.previousElementSibling) {
						galleryList.insertBefore(item, item.previousElementSibling);
					} else if (button.getAttribute('data-gallery-move') === 'down' && item.nextElementSibling) {
						galleryList.insertBefore(item.nextElementSibling, item);
					}
					syncGalleryOrder();
					});
				})();

				/* ====== Предпросмотр новых дополнительных фото ====== */
				(function() {
					var galleryUpload = document.getElementById('galleryUploads');
					var preview = document.getElementById('galleryUploadPreview');
					if (!galleryUpload || !preview) return;

					galleryUpload.addEventListener('change', function() {
						preview.innerHTML = '';
						Array.prototype.forEach.call(galleryUpload.files, function(file) {
							if (!file.type || file.type.indexOf('image/') !== 0) return;
							var item = document.createElement('div');
							item.className = 'gallery-existing-item';
							var image = document.createElement('img');
							image.src = URL.createObjectURL(file);
							image.alt = 'Предпросмотр нового фото';
							var name = document.createElement('small');
							name.textContent = file.name;
							item.appendChild(image);
							item.appendChild(name);
							preview.appendChild(item);
						});
					});
				})();

				/* ====== Немедленное удаление дополнительного фото ====== */
				window.removeGalleryImage = function(event, imageId) {
					event.preventDefault();
					event.stopPropagation();
					var form = document.getElementById('productForm');
					var item = event.currentTarget.closest('[data-gallery-id]');
					if (!form || !item) return;

					var body = new URLSearchParams();
					body.set('action', 'delete_gallery_image');
					body.set('product_id', form.querySelector('[name="id"]').value);
					body.set('image_id', String(imageId));
					body.set('csrf_token', form.querySelector('[name="csrf_token"]').value);
					var button = event.currentTarget;
					button.disabled = true;

					fetch(window.location.href, {
						method: 'POST',
						headers: {'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'},
						body: body.toString()
					})
					.then(function(response) {
						return response.json().then(function(result) {
							if (!response.ok || !result.success) {
								throw new Error(result.message || 'Не удалось удалить фото.');
							}
							return result;
						});
					})
					.then(function() {
						item.remove();
						var orderInput = document.getElementById('galleryOrder');
						var galleryList = document.querySelector('.gallery-existing:not(#galleryUploadPreview)');
						if (orderInput && galleryList) {
							orderInput.value = JSON.stringify(Array.prototype.map.call(galleryList.querySelectorAll('[data-gallery-id]'), function(row) {
								return parseInt(row.getAttribute('data-gallery-id'), 10);
							}));
						}
					})
					.catch(function(error) {
						button.disabled = false;
						window.alert(error.message);
					});
				};

				/* ====== Форма редактирования товара ====== */
		(function() {
			var imageUpload = document.getElementById('imageUpload');
			if (!imageUpload) return; // форма отсутствует на странице

			var imagePreview = document.getElementById('imagePreview');
			var btnRemove = document.getElementById('btnRemoveImage');

			// Клик на превью — открыть диалог выбора файла
			imagePreview.addEventListener('click', function() {
				imageUpload.click();
			});

			// Загрузка фото
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

			// Удаление фото
				window.removeImage = function(event) {
				event.preventDefault();
				event.stopPropagation();
				document.getElementById('imageInput').value = '';
				imageUpload.value = '';
				imagePreview.innerHTML = '<span>📷 Нажмите для загрузки</span>';
				btnRemove.classList.remove('show');
			};
		})();

		// Переключение статуса наличия
		function toggleInStock() {
			var toggle = document.getElementById('inStockToggle');
			var input = document.getElementById('in_stock');
			if (toggle.classList.contains('active')) {
				toggle.classList.replace('active', 'inactive');
				toggle.textContent = '✗ Нет';
				input.value = '0';
			} else {
				toggle.classList.replace('inactive', 'active');
				toggle.textContent = '✓ В наличии';
				input.value = '1';
			}
		}

		/* ====== Drag-and-drop сортировка товаров ====== */
		(function() {
			var tbody = document.getElementById('sortable-products');
			var saveBtn = document.getElementById('saveOrderBtn');
			var status = document.getElementById('saveStatus');

			// Если таблица отсутствует (страница редактирования) — ничего не делаем
			if (!tbody || !saveBtn) return;

			// CSRF токен з мета-тегу
			var csrfMeta = document.querySelector('meta[name="csrf-token"]');
			var csrf = csrfMeta ? csrfMeta.content : '';

			var hasChanges = false;

			// Инициализация SortableJS
			Sortable.create(tbody, {
				handle: '.drag-handle', // тащить только за иконку
				animation: 150, // плавная анимация перемещения
				ghostClass: 'sortable-ghost',
				chosenClass: 'sortable-chosen',

				onEnd: function() {
					// Обновляем порядковые номера в колонке \"#\"
					tbody.querySelectorAll('tr').forEach(function(tr, i) {
						var cell = tr.querySelector('.row-position');
						if (cell) cell.textContent = i + 1;
					});

					// Показываем кнопку сохранения
					hasChanges = true;
					saveBtn.classList.add('has-changes');
					status.textContent = '';
					status.className = '';
				}
			});

			// Сохранение нового порядка через AJAX
			saveBtn.addEventListener('click', function() {
				var rows = tbody.querySelectorAll('tr[data-id]');
				var order = [];

				rows.forEach(function(tr, i) {
					order.push({
						id: parseInt(tr.getAttribute('data-id'), 10),
						sort_order: i
					});
				});

				saveBtn.disabled = true;
				saveBtn.textContent = '⏳ Сохранение...';
				status.textContent = '';

				fetch('/admin/api/reorder_products.php', {
						method: 'POST',
						headers: {
							'Content-Type': 'application/json'
						},
						body: JSON.stringify({
							csrf_token: csrf,
							order: order
						})
					})
					.then(function(r) {
						return r.json();
					})
					.then(function(data) {
						if (data.success) {
							hasChanges = false;
							saveBtn.classList.remove('has-changes');
							status.textContent = '✓ Порядок сохранен';
							status.className = 'save-success';
						} else {
							status.textContent = '✗ Ошибка: ' + (data.error || 'неизвестная');
							status.className = 'text-danger';
						}
					})
					.catch(function() {
						status.textContent = '✗ Сетевая ошибка';
						status.className = 'text-danger';
					})
					.finally(function() {
						saveBtn.disabled = false;
						saveBtn.textContent = '💾 Сохранить порядок';
					});
			});

			// Предупреждение при попытке закрыть вкладку с несохраненными изменениями
			window.addEventListener('beforeunload', function(e) {
				if (hasChanges) {
					e.preventDefault();
					e.returnValue = '';
				}
			});
		})();
	</script>
</body>

</html>

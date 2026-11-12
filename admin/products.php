<?php

declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../lib/feed_generator.php';
admin_require_permission('products', 'view');
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

function products_return_query(): string
{
	$params = [];
	foreach (['q', 'status_filter', 'page', 'per_page'] as $key) {
		if (isset($_GET[$key]) && $_GET[$key] !== '') {
			$params[$key] = (string)$_GET[$key];
		}
	}
	return $params ? '?' . http_build_query($params) : '';
}

function product_slug_base(string $value): string
{
	$value = trim($value);
	$translit = ['а'=>'a','б'=>'b','в'=>'v','г'=>'g','д'=>'d','е'=>'e','ё'=>'e','ж'=>'zh','з'=>'z','и'=>'i','й'=>'j','к'=>'k','л'=>'l','м'=>'m','н'=>'n','о'=>'o','п'=>'p','р'=>'r','с'=>'s','т'=>'t','у'=>'u','ф'=>'f','х'=>'h','ц'=>'c','ч'=>'ch','ш'=>'sh','щ'=>'sch','ъ'=>'','ы'=>'y','ь'=>'','э'=>'e','ю'=>'yu','я'=>'ya'];
	$value = strtr(function_exists('mb_strtolower') ? mb_strtolower($value) : strtolower($value), $translit);
	$value = preg_replace('/[^a-zA-Z0-9\-\s_]/u', '', $value) ?? '';
	$value = preg_replace('/[\s_]+/', '-', $value) ?? '';
	return trim(strtolower($value), '-');
}

function unique_product_link(PDO $pdo, string $candidate, ?int $excludeId = null): string
{
	$base = product_slug_base($candidate) ?: 'product';
	$link = $base;
	$suffix = 2;
	while (true) {
		$sql = 'SELECT id FROM products WHERE link = :link';
		$params = ['link' => $link];
		if ($excludeId !== null) {
			$sql .= ' AND id <> :exclude_id';
			$params['exclude_id'] = $excludeId;
		}
		$stmt = $pdo->prepare($sql . ' LIMIT 1');
		$stmt->execute($params);
		if (!$stmt->fetchColumn()) return $link;
		$link = $base . '-' . $suffix++;
	}
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $pdo instanceof PDO && ($_POST['action'] ?? '') === 'quick_update') {
	admin_require_permission('products', 'edit');
	header('Content-Type: application/json; charset=utf-8');
	if (!validate_csrf_token()) {
		http_response_code(403);
		echo json_encode(['success' => false, 'message' => 'CSRF check failed'], JSON_UNESCAPED_UNICODE);
		exit;
	}
	$id = (int)($_POST['product_id'] ?? 0);
	$field = (string)($_POST['field'] ?? '');
	$value = trim((string)($_POST['value'] ?? ''));
	$allowed = ['price', 'old_price', 'status', 'in_stock'];
	if ($id <= 0 || !in_array($field, $allowed, true)) {
		http_response_code(400);
		echo json_encode(['success' => false, 'message' => 'Некорректные параметры'], JSON_UNESCAPED_UNICODE);
		exit;
	}
	if ($field === 'status' && !in_array($value, ['active', 'preorder', 'disabled'], true)) {
		http_response_code(422);
		echo json_encode(['success' => false, 'message' => 'Некорректный статус'], JSON_UNESCAPED_UNICODE);
		exit;
	}
	if ($field === 'in_stock' && !in_array($value, ['0', '1'], true)) {
		http_response_code(422);
		echo json_encode(['success' => false, 'message' => 'Некорректное значение наличия'], JSON_UNESCAPED_UNICODE);
		exit;
	}
	if (in_array($field, ['price', 'old_price'], true) && (!is_numeric($value) || (float)$value < 0)) {
		http_response_code(422);
		echo json_encode(['success' => false, 'message' => 'Некорректная цена'], JSON_UNESCAPED_UNICODE);
		exit;
	}
	$stmt = $pdo->prepare("UPDATE products SET `$field` = :value WHERE id = :id");
	$stmt->execute(['value' => $field === 'in_stock' ? (int)$value : $value, 'id' => $id]);
	try {
		generate_product_feeds($pdo, dirname(__DIR__));
	} catch (Throwable $e) {
		dev_log_runtime('Quick product update feed regeneration failed: ' . $e->getMessage());
	}
	echo json_encode(['success' => true], JSON_UNESCAPED_UNICODE);
	exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $pdo instanceof PDO && ($_POST['action'] ?? '') === 'bulk_update') {
	admin_require_permission('products', 'edit');
	if (!validate_csrf_token()) {
		product_flash('Сессия формы устарела. Обновите страницу.', 'danger');
		header('Location: /admin/products.php' . (string)($_POST['return_query'] ?? ''));
		exit;
	}
	$ids = array_values(array_unique(array_filter(array_map('intval', (array)($_POST['product_ids'] ?? [])))));
	$field = (string)($_POST['bulk_field'] ?? '');
	$value = trim((string)($_POST['bulk_value'] ?? ''));
		if (!$ids || !in_array($field, ['status', 'price', 'in_stock'], true)) {
			product_flash('Выберите товары и поле для изменения.', 'warning');
			header('Location: /admin/products.php' . (string)($_POST['return_query'] ?? ''));
			exit;
		}
		if ($field === 'status' && !in_array($value, ['active', 'preorder', 'disabled'], true)) {
			product_flash('Выбран некорректный статус.', 'danger');
			header('Location: /admin/products.php' . (string)($_POST['return_query'] ?? ''));
			exit;
		}
		if ($field === 'in_stock' && !in_array($value, ['0', '1', 'active', 'disabled'], true)) {
			product_flash('Выберите корректное значение наличия.', 'danger');
			header('Location: /admin/products.php' . (string)($_POST['return_query'] ?? ''));
			exit;
		}
		if ($field === 'price' && (!is_numeric($value) || (float)$value < 0)) {
			product_flash('Укажите корректную цену.', 'danger');
			header('Location: /admin/products.php' . (string)($_POST['return_query'] ?? ''));
			exit;
		}
		$placeholders = implode(',', array_fill(0, count($ids), '?'));
		$normalizedValue = $field === 'in_stock'
			? (int)in_array($value, ['1', 'active'], true)
			: $value;
		$params = array_merge([$normalizedValue], $ids);
	$stmt = $pdo->prepare("UPDATE products SET `$field` = ? WHERE id IN ($placeholders)");
	$stmt->execute($params);
	try {
		generate_product_feeds($pdo, dirname(__DIR__));
	} catch (Throwable $e) {
		dev_log_runtime('Bulk product update feed regeneration failed: ' . $e->getMessage());
	}
	product_flash('Изменено товаров: ' . count($ids));
	header('Location: /admin/products.php' . (string)($_POST['return_query'] ?? ''));
	exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $pdo instanceof PDO && ($_POST['action'] ?? '') === 'duplicate_product') {
	admin_require_permission('products', 'create');
	if (!validate_csrf_token()) {
		product_flash('Сессия формы устарела. Обновите страницу.', 'danger');
		header('Location: /admin/products.php');
		exit;
	}
	$id = (int)($_POST['product_id'] ?? 0);
	$stmt = $pdo->prepare('SELECT * FROM products WHERE id = :id LIMIT 1');
	$stmt->execute(['id' => $id]);
	$source = $stmt->fetch();
	if (!$source) {
		product_flash('Товар для копирования не найден.', 'danger');
		header('Location: /admin/products.php');
		exit;
	}
	$externalId = (string)$source['external_id'] . '-copy';
	$counter = 2;
	while (true) {
		$check = $pdo->prepare('SELECT id FROM products WHERE external_id = :external_id LIMIT 1');
		$check->execute(['external_id' => $externalId]);
		if (!$check->fetchColumn()) break;
		$externalId = (string)$source['external_id'] . '-copy-' . $counter++;
	}
	$link = unique_product_link($pdo, (string)$source['link'] . '-copy');
	$copy = $pdo->prepare('INSERT INTO products (external_id, cat_number, name, old_price, price, image, link, short_desc, `desc`, full_desc, in_stock, status, seo_title, seo_description, volume, sort_order) VALUES (:external_id,:cat_number,:name,:old_price,:price,:image,:link,:short_desc,:desc,:full_desc,:in_stock,:status,:seo_title,:seo_description,:volume,:sort_order)');
	$copy->execute([
		'external_id' => $externalId,
		'cat_number' => $source['cat_number'],
		'name' => (string)$source['name'] . ' (копия)',
		'old_price' => $source['old_price'],
		'price' => $source['price'],
		'image' => $source['image'],
		'link' => $link,
		'short_desc' => $source['short_desc'],
		'desc' => $source['desc'],
		'full_desc' => $source['full_desc'],
		'in_stock' => $source['in_stock'],
		'status' => 'disabled',
		'seo_title' => $source['seo_title'],
		'seo_description' => $source['seo_description'],
		'volume' => $source['volume'],
		'sort_order' => ((int)$source['sort_order']) + 1,
	]);
	$newId = (int)$pdo->lastInsertId();
	product_flash('Товар скопирован. Проверьте данные перед публикацией.');
	header('Location: /admin/products.php?edit=' . $newId);
	exit;
}

	if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_gallery_image') {
		admin_require_permission('products', 'delete');
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
			$feedWarning = null;
			try {
				generate_product_feeds($pdo, dirname(__DIR__));
			} catch (Throwable $e) {
				$feedWarning = 'Фото удалено, но feed не обновлён.';
				if (function_exists('dev_log_runtime')) {
					dev_log_runtime('Automatic feed regeneration after gallery image deletion failed: ' . $e->getMessage());
				}
			}

			echo json_encode(['success' => true, 'warning' => $feedWarning], JSON_UNESCAPED_UNICODE);
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
		admin_require_permission('products', $id > 0 ? 'edit' : 'create');
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
		if ($data['external_id'] === '' || $data['name'] === '' || $data['price'] < 0) {
			product_flash('Заполните обязательные поля товара и укажите корректную цену.', 'danger');
			header('Location: /admin/products.php' . ($id > 0 ? '?edit=' . $id : '?edit=0'));
			exit;
		}
		$data['link'] = unique_product_link($pdo, $data['link'] !== '' ? $data['link'] : $data['name'], $id > 0 ? $id : null);

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
	$feedError = null;
	try {
		generate_product_feeds($pdo, dirname(__DIR__));
	} catch (Throwable $e) {
		$feedError = $e->getMessage();
		if (function_exists('dev_log_runtime')) {
			dev_log_runtime('Automatic feed regeneration after product save failed: ' . $e->getMessage());
		}
	}
	if ($feedError !== null) {
		product_flash('Товар сохранён, но feed не удалось обновить. Ошибка записана в журнал.', 'warning');
	} elseif ($uploadErrors) {
		product_flash('Товар сохранён. ' . implode(' ', $uploadErrors), 'warning');
	} elseif (!isset($_SESSION['product_flash'])) {
		product_flash('Товар успешно сохранён.');
	}
	header('Location: /admin/products.php');
	exit;
}

// Серверная выборка списка: поиск и фильтры применяются до пагинации.
$search = trim((string)($_GET['q'] ?? ''));
$statusFilter = (string)($_GET['status_filter'] ?? '');
if (!in_array($statusFilter, ['active', 'preorder', 'disabled'], true)) $statusFilter = '';
$perPage = (int)($_GET['per_page'] ?? 100);
if (!in_array($perPage, [50, 100], true)) $perPage = 100;
$page = max(1, (int)($_GET['page'] ?? 1));
$products = [];
$totalProducts = 0;
$totalPages = 1;
if ($pdo instanceof PDO) {
	$where = [];
	$params = [];
	if ($search !== '') {
		$where[] = '(name LIKE :search_name OR external_id LIKE :search_external_id OR cat_number LIKE :search_cat_number OR link LIKE :search_link)';
		$searchValue = '%' . $search . '%';
		$params['search_name'] = $searchValue;
		$params['search_external_id'] = $searchValue;
		$params['search_cat_number'] = $searchValue;
		$params['search_link'] = $searchValue;
	}
	if ($statusFilter !== '') {
		$where[] = 'status = :status_filter';
		$params['status_filter'] = $statusFilter;
	}
	$whereSql = $where ? ' WHERE ' . implode(' AND ', $where) : '';
	$countStmt = $pdo->prepare('SELECT COUNT(*) FROM products' . $whereSql);
	$countStmt->execute($params);
	$totalProducts = (int)$countStmt->fetchColumn();
	// Каталог рассчитан максимум на 100 товаров: показываем его целиком на одной странице.
	// Параметр per_page сохраняется для совместимости и для будущего роста каталога.
	if ($totalProducts > 0 && $totalProducts <= 100) {
		$perPage = $totalProducts;
	}
	$totalPages = max(1, (int)ceil($totalProducts / $perPage));
	$page = min($page, $totalPages);
	$offset = ($page - 1) * $perPage;
	$listStmt = $pdo->prepare('SELECT * FROM products' . $whereSql . ' ORDER BY sort_order ASC, id ASC LIMIT ' . $perPage . ' OFFSET ' . $offset);
	$listStmt->execute($params);
	$products = $listStmt->fetchAll();
}
$edit = null;
$gallery = [];
	if (isset($_GET['edit'])) {
	$editId = (int)$_GET['edit'];
	$edit = []; // пустой массив = новый товар; null = список без формы
	if ($editId > 0 && $pdo instanceof PDO) {
		$editStmt = $pdo->prepare('SELECT * FROM products WHERE id = :id LIMIT 1');
		$editStmt->execute(['id' => $editId]);
		$edit = $editStmt->fetch() ?: [];
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
	<script src="/admin/ckeditor-upload-adapter.js"></script>
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

		.product-availability {
			display: inline-flex;
			align-items: center;
			justify-content: center;
			width: 28px;
			height: 28px;
			border-radius: 4px;
			color: #fff;
			font-size: 18px;
			font-weight: 700;
			line-height: 1;
			vertical-align: middle;
		}
		.product-availability--active { background: #28a745; }
		.product-availability--preorder { background: #f39c12; }
		.product-availability--disabled { background: #dc3545; }

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

		.products-availability-filter {
			display: inline-flex;
			align-items: center;
			gap: 8px;
			margin-left: auto;
		}

		.products-availability-filter label {
			margin: 0;
			font-weight: 600;
			color: #555;
		}

		.products-availability-filter select {
			min-width: 170px;
		}

		.products-availability-filter input {
			min-width: 210px;
		}

		.products-bulk-actions {
			display: flex;
			align-items: center;
			gap: 8px;
			flex-wrap: wrap;
			margin-bottom: 12px;
		}

		.products-bulk-actions select {
			width: auto;
			min-width: 170px;
		}

		.products-pagination-summary {
			margin-top: 12px;
			color: #777;
		}

		.products-pagination {
			margin-top: 8px;
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
								<option value="preorder" <?= ($edit['status'] ?? '') === 'preorder' ? 'selected' : '' ?>>Под заказ
								</option>
								<option value="disabled" <?= ($edit['status'] ?? '') === 'disabled' ? 'selected' : '' ?>>Нет в наличии
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
										<small><a id="productPreviewLink" href="/<?= admin_h((string)($edit['link'] ?? '')) ?>" target="_blank" rel="noopener">Открыть предпросмотр товара</a></small>
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
									<small class="text-muted"><span id="seoTitleCount">0</span>/60 символов</small>
							</div>
							<div class="form-group-wrapper">
								<label for="seo_description">SEO Description</label>
									<textarea class="form-control" id="seo_description" rows="2"
										name="seo_description"><?= admin_h((string)($edit['seo_description'] ?? '')) ?></textarea>
									<small class="text-muted"><span id="seoDescriptionCount">0</span>/160 символов</small>
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
				<form method="get" class="products-availability-filter">
					<label for="productSearch">Поиск:</label>
					<input id="productSearch" name="q" class="form-control input-sm" value="<?= admin_h($search) ?>" placeholder="Название, ID, код, slug">
					<select name="status_filter" class="form-control input-sm" aria-label="Фильтр наличия">
						<option value="">Все товары</option>
						<option value="active" <?= $statusFilter === 'active' ? 'selected' : '' ?>>Активный</option>
						<option value="preorder" <?= $statusFilter === 'preorder' ? 'selected' : '' ?>>Под заказ</option>
						<option value="disabled" <?= $statusFilter === 'disabled' ? 'selected' : '' ?>>Нет в наличии</option>
					</select>
					<select name="per_page" class="form-control input-sm" aria-label="Товаров на странице">
						<?php foreach ([50, 100] as $pageSize): ?><option value="<?= $pageSize ?>" <?= ($perPage === $pageSize || ($pageSize === 100 && $totalProducts <= 100)) ? 'selected' : '' ?>><?= $pageSize ?> / стр.</option><?php endforeach; ?>
					</select>
					<button class="btn btn-primary btn-sm" type="submit">Найти</button>
					<a class="btn btn-light btn-sm" href="/admin/products.php">Сбросить</a>
				</form>
				<a class="btn btn-outline-secondary btn-sm" href="/admin/products-export.php<?= admin_h(products_return_query()) ?>">CSV экспорт</a>
				<a class="btn btn-outline-secondary btn-sm" href="/admin/products-import.php">CSV импорт</a>
				<button id="saveOrderBtn" class="btn btn-warning btn-sm">
					💾 Сохранить порядок
				</button>
				<span id="saveStatus"></span>
			</div>

			<p style="color:#888;font-size:0.88rem;margin-bottom:10px;">
				⠿ Перетащите строку за иконку чтобы изменить порядок. После перестановки нажмите «Сохранить порядок».
			</p>
			<form method="post" id="bulkProductForm">
				<input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
				<input type="hidden" name="action" value="bulk_update">
				<input type="hidden" name="product_id" id="duplicateProductId" value="">
				<input type="hidden" name="return_query" value="<?= admin_h(products_return_query()) ?>">
				<div class="products-bulk-actions">
					<strong>Массовое изменение:</strong>
					<select name="bulk_field" id="bulkField" class="form-control input-sm" required>
						<option value="">Выберите поле</option>
						<option value="status">Статус наличия</option>
						<option value="price">Цена</option>
						<option value="in_stock">Флаг «В наличии»</option>
					</select>
						<select name="bulk_value" id="bulkValue" class="form-control input-sm" required>
						<option value="">Выберите значение</option>
						<option value="active" data-bulk-fields="status">Активный</option>
						<option value="preorder" data-bulk-fields="status">Под заказ</option>
						<option value="disabled" data-bulk-fields="status">Нет в наличии</option>
						<option value="1" data-bulk-fields="in_stock">В наличии (1)</option>
						<option value="0" data-bulk-fields="in_stock">Нет в наличии (0)</option>
						</select>
						<input type="number" name="bulk_value" id="bulkPrice" class="form-control input-sm" min="0" step="0.01" placeholder="Новая цена" disabled>
						<button class="btn btn-warning btn-sm" type="submit">Применить к выбранным</button>
						<?php if (admin_can('products', 'create')): ?><button class="btn btn-default btn-sm" type="button" id="duplicateSelectedProduct">Быстрые действия: копировать выбранный</button><?php endif; ?>
				</div>

			<div class="table-responsive">
				<table class="table table-bordered table-striped">
					<thead>
						<tr>
							<th style="width:36px" title="Перетащите для сортировки">⠿</th>
							<th style="width:32px"><input type="checkbox" id="selectAllProducts" aria-label="Выбрать все товары"></th>
							<th style="width:40px; display:none;">#</th>
							<th>ID AMO</th>
							<th>Код каталога</th>
							<th>Название</th>
							<th>Объем</th>
							<th>Цена</th>
							<th>Slug</th>
							<th>Наличие</th>
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
								'preorder' => 'Под заказ',
								'disabled' => 'Нет в наличии',
								default    => '—',
							};
							$availability = empty($p['in_stock'])
								? ['class' => 'disabled', 'icon' => '×', 'label' => 'Нет в наличии']
								: ($st === 'preorder'
									? ['class' => 'preorder', 'icon' => '◷', 'label' => 'Под заказ']
									: ['class' => 'active', 'icon' => '✓', 'label' => 'В наличии']);
						?>
							<tr data-id="<?= (int)$p['id'] ?>" data-availability="<?= admin_h($availability['class']) ?>" class="<?= $rowClass ?>">
								<td class="drag-handle" title="Перетащите для изменения порядка">⠿</td>
								<td><input type="checkbox" name="product_ids[]" value="<?= (int)$p['id'] ?>" class="product-select" aria-label="Выбрать товар"></td>
								<td class="row-position" style=" display:none;"><?= $i + 1 ?></td>
								<td><?= admin_h((string)$p['external_id']) ?></td>
								<td><?= admin_h((string)$p['cat_number']) ?></td>
								<td><?= admin_h((string)$p['name']) ?></td>
								<td><?= admin_h((string)$p['volume']) ?></td>
								<td><input class="form-control input-sm product-inline-field" data-product-id="<?= (int)$p['id'] ?>" data-quick-field="price" type="number" step="0.01" min="0" value="<?= admin_h((string)$p['price']) ?>" aria-label="Цена"></td>
								<td><?= admin_h((string)$p['link']) ?></td>
								<td>
									<span class="product-availability product-availability--<?= admin_h($availability['class']) ?>"
										title="<?= admin_h($availability['label']) ?>"
										role="img"
										aria-label="<?= admin_h($availability['label']) ?>"><?= admin_h($availability['icon']) ?></span>
								</td>
								<td><select class="form-control input-sm product-inline-field" data-product-id="<?= (int)$p['id'] ?>" data-quick-field="status" aria-label="Статус">
									<?php foreach (['active' => 'Активный', 'preorder' => 'Под заказ', 'disabled' => 'Нет в наличии'] as $statusValue => $statusText): ?><option value="<?= $statusValue ?>" <?= $st === $statusValue ? 'selected' : '' ?>><?= $statusText ?></option><?php endforeach; ?>
								</select></td>
								<td><a href="/admin/products.php?edit=<?= (int)$p['id'] ?>">Редактировать</a></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div><!-- /.table-responsive -->
				<div class="products-pagination-summary">Показано <?= count($products) ?> из <?= $totalProducts ?> товаров</div>
				<?php if ($totalPages > 1): ?><nav class="products-pagination" aria-label="Страницы товаров"><ul class="pagination">
					<?php for ($paginationPage = 1; $paginationPage <= $totalPages; $paginationPage++): ?><li class="page-item <?= $paginationPage === $page ? 'active' : '' ?>"><a class="page-link" href="?<?= admin_h(http_build_query(array_filter(['q' => $search, 'status_filter' => $statusFilter, 'per_page' => $perPage, 'page' => $paginationPage], static fn($value) => $value !== ''))) ?>"><?= $paginationPage ?></a></li><?php endfor; ?>
				</ul></nav><?php endif; ?>
			</form>
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
					extraPlugins: [CkeditorUploadAdapterPlugin],
					ckUploadUrl: '/admin/blog-upload.php?context=product',
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
						styles: [
							'full',
							'side',
							'alignLeft',
							'alignCenter',
							'alignRight'
						],
						toolbar: [
							'imageTextAlternative', '|',
							'imageStyle:full', 'imageStyle:side', '|',
							'imageStyle:alignLeft', 'imageStyle:alignCenter', 'imageStyle:alignRight'
						]
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
				var galleryList = document.querySelector('.gallery-existing:not(#galleryUploadPreview)');
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

			(function() {
				var csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
				var bulkForm = document.getElementById('bulkProductForm');
				var selectAll = document.getElementById('selectAllProducts');
					if (selectAll && bulkForm) {
						selectAll.addEventListener('change', function() {
							bulkForm.querySelectorAll('.product-select').forEach(function(input) { input.checked = selectAll.checked; });
						});
					}
					var bulkField = document.getElementById('bulkField');
					var bulkValue = document.getElementById('bulkValue');
					var bulkPrice = document.getElementById('bulkPrice');
					function syncBulkValues() {
						if (!bulkField || !bulkValue || !bulkPrice) return;
						var field = bulkField.value;
						bulkValue.value = '';
						var isPrice = field === 'price';
						bulkValue.hidden = isPrice;
						bulkValue.disabled = isPrice;
						bulkPrice.hidden = !isPrice;
						bulkPrice.disabled = !isPrice;
						bulkValue.querySelectorAll('option[data-bulk-fields]').forEach(function(option) {
							option.hidden = isPrice || option.dataset.bulkFields.indexOf(field) === -1;
						});
					}
					if (bulkField) {
						bulkField.addEventListener('change', syncBulkValues);
						syncBulkValues();
					}
					document.querySelectorAll('.product-inline-field').forEach(function(field) {
					field.addEventListener('change', function() {
						var oldValue = field.getAttribute('data-original') || field.value;
						field.disabled = true;
						var body = new URLSearchParams({action: 'quick_update', csrf_token: csrf, product_id: field.dataset.productId, field: field.dataset.quickField, value: field.value});
						fetch('/admin/products.php', {method: 'POST', headers: {'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'}, body: body.toString()})
							.then(function(response) { return response.json().then(function(data) { if (!response.ok || !data.success) throw new Error(data.message || 'Ошибка сохранения'); }); })
							.then(function() { field.setAttribute('data-original', field.value); field.classList.add('is-valid'); setTimeout(function() { field.classList.remove('is-valid'); }, 1000); })
							.catch(function(error) { field.value = oldValue; window.alert(error.message); })
							.finally(function() { field.disabled = false; });
					});
				});
					var duplicateSelected = document.getElementById('duplicateSelectedProduct');
					if (duplicateSelected) {
						duplicateSelected.addEventListener('click', function() {
							if (!bulkForm) return;
							var selected = bulkForm.querySelectorAll('.product-select:checked');
							if (selected.length !== 1) {
								window.alert('Для копирования выберите ровно один товар.');
								return;
							}
							bulkForm.querySelector('[name="action"]').value = 'duplicate_product';
							bulkForm.querySelector('[name="product_id"]').value = selected[0].value;
							bulkForm.submit();
						});
					}
				var nameInput = document.getElementById('name');
				var slugInput = document.getElementById('link');
				var previewLink = document.getElementById('productPreviewLink');
				var slugTouched = !!(slugInput && slugInput.value);
				function slugify(value) { return value.toLowerCase().replace(/[^a-z0-9а-яё]+/gi, '-').replace(/^-+|-+$/g, ''); }
				if (slugInput) slugInput.addEventListener('input', function() { slugTouched = true; if (previewLink) previewLink.href = '/' + slugInput.value.replace(/^\/+/, ''); });
				if (nameInput && slugInput) nameInput.addEventListener('input', function() { if (!slugTouched) { slugInput.value = slugify(nameInput.value); if (previewLink) previewLink.href = '/' + slugInput.value; } });
				[['seo_title', 'seoTitleCount'], ['seo_description', 'seoDescriptionCount']].forEach(function(pair) { var input = document.getElementById(pair[0]); var counter = document.getElementById(pair[1]); if (input && counter) { var update = function() { counter.textContent = input.value.length; }; input.addEventListener('input', update); update(); } });
			})();

			/* ====== Drag-and-drop сортировка товаров ====== */
			(function() {
				var tbody = document.getElementById('sortable-products');
				var saveBtn = document.getElementById('saveOrderBtn');
				var status = document.getElementById('saveStatus');
				var availabilityFilter = document.getElementById('availabilityFilter');
				var availabilityFilterCount = document.getElementById('availabilityFilterCount');

			// Если таблица отсутствует (страница редактирования) — ничего не делаем
			if (!tbody || !saveBtn) return;

			// CSRF токен з мета-тегу
			var csrfMeta = document.querySelector('meta[name="csrf-token"]');
			var csrf = csrfMeta ? csrfMeta.content : '';

			var hasChanges = false;

			// Инициализация SortableJS
				var sortable = Sortable.create(tbody, {
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

				function applyAvailabilityFilter() {
					var selected = availabilityFilter ? availabilityFilter.value : 'all';
					var visibleCount = 0;

					tbody.querySelectorAll('tr[data-availability]').forEach(function(tr) {
						var isVisible = selected === 'all' || tr.getAttribute('data-availability') === selected;
						tr.style.display = isVisible ? '' : 'none';
						if (isVisible) visibleCount += 1;
					});

					if (availabilityFilterCount) {
						availabilityFilterCount.textContent = 'Показано: ' + visibleCount;
					}
					sortable.option('disabled', selected !== 'all');
				}

				if (availabilityFilter) {
					availabilityFilter.addEventListener('change', applyAvailabilityFilter);
					applyAvailabilityFilter();
				}

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

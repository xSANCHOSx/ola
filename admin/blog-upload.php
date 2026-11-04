<?php

declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';
admin_require_auth();
header('Content-Type: application/json; charset=utf-8');

// Один endpoint используется двумя редакторами, но права не смешиваются:
// блог передаёт context=blog, товары — context=product.
$uploadContext = (string)($_GET['context'] ?? 'blog');
$permissionSection = match ($uploadContext) {
	'blog' => 'blog',
	'product' => 'products',
	default => null,
};
if ($permissionSection === null) {
	http_response_code(400);
	echo json_encode([
		'uploaded' => false,
		'error' => ['message' => 'Неизвестный контекст загрузки изображения'],
	], JSON_UNESCAPED_UNICODE);
	exit;
}
if (!admin_can($permissionSection, 'create') && !admin_can($permissionSection, 'edit')) {
	http_response_code(403);
	echo json_encode([
		'uploaded' => false,
		'error' => [
			'message' => 'Недостаточно прав для загрузки изображения'
		]
	], JSON_UNESCAPED_UNICODE);
	exit;
}

// CKEditor 5 ожидает поле 'uploaded' вместо успешного статуса
if (!isset($_FILES['upload'])) {
	http_response_code(400);
	echo json_encode([
		'uploaded' => false,
		'error' => [
			'message' => 'No file provided'
		]
	]);
	exit;
}

$file = $_FILES['upload'];
$uploadError = (int)($file['error'] ?? UPLOAD_ERR_NO_FILE);
if ($uploadError !== UPLOAD_ERR_OK) {
	$message = match ($uploadError) {
		UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'Изображение превышает лимит размера загрузки на сервере.',
		UPLOAD_ERR_PARTIAL => 'Изображение загрузилось не полностью. Повторите попытку.',
		UPLOAD_ERR_NO_TMP_DIR, UPLOAD_ERR_CANT_WRITE => 'Сервер не может сохранить загруженное изображение.',
		default => 'Не удалось загрузить изображение. Повторите попытку.',
	};
	http_response_code(400);
	echo json_encode([
		'uploaded' => false,
		'error' => ['message' => $message],
	], JSON_UNESCAPED_UNICODE);
	exit;
}
if (empty($file['tmp_name'])) {
	http_response_code(400);
	echo json_encode([
		'uploaded' => false,
		'error' => ['message' => 'Не удалось получить файл изображения.'],
	], JSON_UNESCAPED_UNICODE);
	exit;
}

// Валидация размера
if ((int)$file['size'] > 10 * 1024 * 1024) {
	http_response_code(413);
	echo json_encode([
		'uploaded' => false,
		'error' => [
			'message' => 'File too large (max 10MB)'
		]
	]);
	exit;
}

// Валидация расширения
$ext = strtolower(pathinfo((string)$file['name'], PATHINFO_EXTENSION));
$allowed = ['jpg', 'jpeg', 'png', 'webp', 'gif'];

if (!in_array($ext, $allowed, true)) {
	dev_log_security('BLOG_UPLOAD_INVALID_EXT', ['ext' => $ext]);
	http_response_code(400);
	echo json_encode([
		'uploaded' => false,
		'error' => [
			'message' => 'File type not allowed. Allowed: jpg, jpeg, png, webp, gif'
		]
	]);
	exit;
}

// Валидация что это действительно загруженный файл
if (!is_uploaded_file($file['tmp_name'])) {
	dev_log_security('BLOG_UPLOAD_SUSPICIOUS', ['filename' => $file['name']]);
	http_response_code(400);
	echo json_encode([
		'uploaded' => false,
		'error' => [
			'message' => 'Invalid upload'
		]
	]);
	exit;
}

// Проверка что это действительно картинка
$imageInfo = @getimagesize($file['tmp_name']);
if ($imageInfo === false) {
	dev_log_security('BLOG_UPLOAD_NOT_IMAGE', ['filename' => $file['name']]);
	http_response_code(400);
	echo json_encode([
		'uploaded' => false,
		'error' => [
			'message' => 'File is not a valid image'
		]
	]);
	exit;
}

// MIME type валидация
$finfo = @finfo_open(FILEINFO_MIME_TYPE);
$mimeType = $finfo ? @finfo_file($finfo, $file['tmp_name']) : null;
if ($finfo) @finfo_close($finfo);

$allowedMimes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
if ($mimeType && !in_array($mimeType, $allowedMimes, true)) {
	dev_log_security('BLOG_UPLOAD_MIME_MISMATCH', ['mime' => $mimeType, 'ext' => $ext]);
	http_response_code(400);
	echo json_encode([
		'uploaded' => false,
		'error' => [
			'message' => 'MIME type does not match extension'
		]
	]);
	exit;
}

$dir = __DIR__ . '/../data/uploads/blog';
if (!is_dir($dir)) {
	@mkdir($dir, 0755, true);
	// Создаем .htaccess для запрета выполнения PHP
	@file_put_contents($dir . '/.htaccess', "php_flag engine off\nAddType text/plain .php .phtml .php3 .php4 .php5 .php6 .php7 .phps .pht .phar .shtml");
}

// Генерируем уникальное имя файла
$fileName = bin2hex(random_bytes(16)) . '.' . $ext;
$target = $dir . '/' . $fileName;

if (!move_uploaded_file($file['tmp_name'], $target)) {
	dev_log_runtime('Blog image upload failed: cannot move file');
	http_response_code(500);
	echo json_encode([
		'uploaded' => false,
		'error' => [
			'message' => 'Failed to save file'
		]
	]);
	exit;
}

// Устанавливаем более строгие права доступа
@chmod($target, 0644);
convert_to_webp($target);

// Если convert_to_webp создал .webp версию — возвращаем её URL.
// SimpleUploadAdapter CKEditor 5 использует поле `default`.
$webpTarget = preg_replace('/\.[^.]+$/', '.webp', $target);
$returnFileName = (file_exists($webpTarget) && $webpTarget !== $target)
	? preg_replace('/\.[^.]+$/', '.webp', $fileName)
	: $fileName;
dev_log_security('BLOG_UPLOAD_SUCCESS', ['filename' => $returnFileName]);
echo json_encode([
	'uploaded' => true,
	'default' => '/data/uploads/blog/' . $returnFileName,
	'url' => '/data/uploads/blog/' . $returnFileName,
]);

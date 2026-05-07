<?php

declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';
admin_require_auth();

header('Content-Type: application/json; charset=utf-8');

if (!isset($_FILES['file'])) {
	http_response_code(400);
	echo json_encode(['error' => 'No file provided']);
	exit;
}

$file = $_FILES['file'];
$ext = strtolower(pathinfo((string)$file['name'], PATHINFO_EXTENSION));
$allowed = ['jpg', 'jpeg', 'png', 'webp', 'gif'];

if (!in_array($ext, $allowed, true)) {
	http_response_code(400);
	echo json_encode(['error' => 'File type not allowed']);
	exit;
}

if ((int)$file['size'] > 10 * 1024 * 1024) {
	http_response_code(400);
	echo json_encode(['error' => 'File too large']);
	exit;
}

if (!is_uploaded_file($file['tmp_name'])) {
	http_response_code(400);
	echo json_encode(['error' => 'Invalid upload']);
	exit;
}

$dir = __DIR__ . '/../data/uploads/blog';
if (!is_dir($dir)) {
	mkdir($dir, 0755, true);
}

$fileName = bin2hex(random_bytes(12)) . '.' . $ext;
$target = $dir . '/' . $fileName;

if (move_uploaded_file($file['tmp_name'], $target)) {
	echo json_encode(['location' => '/data/uploads/blog/' . $fileName]);
} else {
	http_response_code(500);
	echo json_encode(['error' => 'Failed to save file']);
}

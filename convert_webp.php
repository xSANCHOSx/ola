<?php
// Захист — запускати тільки з командного рядка або з паролем
if (!isset($_GET['key']) || $_GET['key'] !== 'sanchos12345!') {
	die('Forbidden');
}

function convertToWebp(string $source, int $quality = 82): string
{
	$ext  = strtolower(pathinfo($source, PATHINFO_EXTENSION));
	$dest = preg_replace('/\.(jpe?g|png)$/i', '.webp', $source);

	if (file_exists($dest)) return "пропущено (вже є): $dest";

	$image = match ($ext) {
		'jpg', 'jpeg' => imagecreatefromjpeg($source),
		'png'         => imagecreatefrompng($source),
		default       => null
	};

	if (!$image) return "❌ помилка читання: $source";

	// Для PNG зберігаємо прозорість
	if ($ext === 'png') {
		imagepalettetotruecolor($image);
		imagealphablending($image, true);
		imagesavealpha($image, true);
	}

	$ok = imagewebp($image, $dest, $quality);
	imagedestroy($image);

	if ($ok) {
		$saved = round((filesize($source) - filesize($dest)) / 1024);
		return "✅ $dest (зекономлено {$saved} KB)";
	}
	return "❌ помилка запису: $dest";
}

$imageDirs = [
	__DIR__ . '/images/',
	__DIR__ . '/data/images/',
	__DIR__ . '/data/uploads/products/',
	__DIR__ . '/data/uploads/blog/',
];

echo "<pre>\n";
$count = 0;
foreach ($imageDirs as $imagesDir) {
	if (!is_dir($imagesDir)) {
		echo "⚠️ Папка не існує: $imagesDir\n";
		continue;
	}
	echo "\n📁 $imagesDir\n";
	$files = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator($imagesDir)
	);
	foreach ($files as $file) {
		if (!$file->isFile()) continue;
		$ext = strtolower($file->getExtension());
		if (!in_array($ext, ['jpg', 'jpeg', 'png'])) continue;

		echo convertToWebp($file->getPathname()) . "\n";
		$count++;
	}
}
echo "\nГотово. Оброблено файлів: $count\n</pre>";
<?php

declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';
admin_require_auth();
$pdo = dev_db_connection();

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
    ];

    if (!empty($_FILES['image_upload']['tmp_name']) && is_uploaded_file($_FILES['image_upload']['tmp_name'])) {
        $ext = strtolower(pathinfo((string)$_FILES['image_upload']['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
        if (in_array($ext, $allowed, true) && (int)$_FILES['image_upload']['size'] <= 5 * 1024 * 1024) {
            $dir = __DIR__ . '/../data/uploads/products';
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
            $fileName = bin2hex(random_bytes(10)) . '.' . $ext;
            $target = $dir . '/' . $fileName;
            if (move_uploaded_file($_FILES['image_upload']['tmp_name'], $target)) {
                $data['image'] = 'data/uploads/products/' . $fileName;
                convert_to_webp($target);
            }
        }
    }

    if ($id > 0) {
        $data['id'] = $id;
        $sql = 'UPDATE products SET external_id=:external_id, cat_number=:cat_number, name=:name, old_price=:old_price, price=:price, image=:image, link=:link, short_desc=:short_desc, `desc`=:desc, full_desc=:full_desc, in_stock=:in_stock, status=:status, seo_title=:seo_title, seo_description=:seo_description, volume=:volume WHERE id=:id';
    } else {
        $sql = 'INSERT INTO products (external_id, cat_number, name, old_price, price, image, link, short_desc, `desc`, full_desc, in_stock, status, seo_title, seo_description, volume) VALUES (:external_id,:cat_number,:name,:old_price,:price,:image,:link,:short_desc,:desc,:full_desc,:in_stock,:status,:seo_title,:seo_description,:volume)';
    }
    $stmt = $pdo->prepare($sql);
    $stmt->execute($data);
    header('Location: /admin/products.php');
    exit;
}

$products = [];
if ($pdo instanceof PDO) {
    $products = $pdo->query('SELECT * FROM products ORDER BY id DESC LIMIT 500')->fetchAll();
}
$edit = null;
if (isset($_GET['edit'])) {
    $editId = (int)$_GET['edit'];
    foreach ($products as $p) {
        if ((int)$p['id'] === $editId) {
            $edit = $p;
            break;
        }
    }
}
?>
<!doctype html>
<html lang="ru">

<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Админка - Товары</title>
	<link rel="stylesheet" href="/css/bootstrap.min.css">
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
		height: 100%;
		margin: 0 auto;
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
		object-fit: cover;
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

	.products-list-section h4 {
		margin-bottom: 20px;
		color: #333;
	}

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
		<!-- Кнопка добавления нового товара -->
		<?php if (!$edit): ?>
		<div style="margin-top: 20px;">
			<button type="button" class="btn btn-primary btn-lg" onclick="location.href='/admin/products.php?edit=0'">
				➕ Добавить новый товар
			</button>
		</div>
		<?php endif; ?>
		<h3>Товары</h3>

		<?php if ($edit): ?>
		<form method="post" enctype="multipart/form-data">
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
								<div id="imagePreview" class="image-placeholder"
									onclick="document.getElementById('imageUpload').click();" style="cursor: pointer;">
									<?php if (!empty($edit['image'])): ?>
									<img src="/<?= admin_h((string)$edit['image']) ?>" alt="Product" class="image-preview-img">
									<?php else: ?>
									<span>📷 Нажмите для загрузки</span>
									<?php endif; ?>
									<button type="button" class="btn-remove-image <?= !empty($edit['image']) ? 'show' : '' ?>"
										onclick="removeImage(event)">
										<svg viewBox="0 0 24 24" fill="none">
											<line x1="18" y1="6" x2="6" y2="18"></line>
											<line x1="6" y1="6" x2="18" y2="18"></line>
										</svg>
									</button>
								</div>
							</div>
							<input type="file" id="imageUpload" class="image-upload-input" name="image_upload" accept="image/*"
								style="display: none !important;">
						</div>
					</div>

				</div>

				<!-- НИЖНЯЯ ЧАСТЬ - Описания на всю ширину -->
				<div class="descriptions-section">
					<h5>📝 Описания</h5>
					<div class="descriptions-grid">
						<div class="form-group-wrapper">
							<label for="short_desc">Краткое</label>
							<textarea class="form-control" id="short_desc" rows="3"
								name="short_desc"><?= admin_h((string)($edit['short_desc'] ?? '')) ?></textarea>
						</div>

						<div class="form-group-wrapper">
							<label for="desc">Обычное</label>
							<textarea class="form-control" id="desc" rows="3"
								name="desc"><?= admin_h((string)($edit['desc'] ?? '')) ?></textarea>
						</div>

						<div class="form-group-wrapper">
							<label for="full_desc">Полное</label>
							<textarea class="form-control" id="full_desc" rows="3"
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
			<h4>📦 Список товаров</h4>
			<table class="table table-bordered table-striped">
				<thead>
					<tr>
						<th>ID</th>
						<th>Код каталога</th>
						<th>Slug</th>
						<th>Название</th>
						<th>Объем</th>
						<th>Цена</th>
						<th>SEO</th>
						<th></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ($products as $p): ?>
					<tr>
						<td><?= admin_h((string)$p['external_id']) ?></td>
						<td><?= admin_h((string)$p['cat_number']) ?></td>
						<td><?= admin_h((string)$p['link']) ?></td>
						<td><?= admin_h((string)$p['name']) ?></td>
						<td><?= admin_h((string)$p['volume']) ?></td>
						<td><?= admin_h((string)$p['price']) ?></td>
						<td><?= admin_h((string)$p['seo_title']) ?></td>
						<td><a href="/admin/products.php?edit=<?= (int)$p['id'] ?>">Редактировать</a></td>
					</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>

		<!-- Кнопка добавления нового товара -->
		<?php if (!$edit): ?>
		<div style="margin-top: 20px;">
			<button type="button" class="btn btn-primary btn-lg" onclick="location.href='/admin/products.php?edit=0'">
				➕ Добавить новый товар
			</button>
		</div>
		<?php endif; ?>
	</div>

	<script>
	// Обработка загрузки фото
	document.getElementById('imageUpload').addEventListener('change', function(e) {
		const file = e.target.files[0];
		if (file) {
			const reader = new FileReader();
			reader.onload = function(event) {
				const preview = document.getElementById('imagePreview');
				preview.innerHTML = '<img src="' + event.target.result + '" alt="Preview" class="image-preview-img">';
				document.querySelector('.btn-remove-image').classList.add('show');
			};
			reader.readAsDataURL(file);
		}
	});

	// Удаление фото
	function removeImage(event) {
		event.preventDefault();
		document.getElementById('imageInput').value = '';
		document.getElementById('imageUpload').value = '';
		const preview = document.getElementById('imagePreview');
		preview.innerHTML = '<span>📷 Нажмите для загрузки</span>';
		document.querySelector('.btn-remove-image').classList.remove('show');
	}

	// Клик на превью для открытия диалога выбора файла
	document.getElementById('imagePreview').addEventListener('click', function() {
		document.getElementById('imageUpload').click();
	});

	// Переключение статуса в наличии
	function toggleInStock() {
		const toggle = document.getElementById('inStockToggle');
		const input = document.getElementById('in_stock');
		const isActive = toggle.classList.contains('active');

		if (isActive) {
			toggle.classList.remove('active');
			toggle.classList.add('inactive');
			toggle.textContent = '✗ Нет';
			input.value = '0';
		} else {
			toggle.classList.remove('inactive');
			toggle.classList.add('active');
			toggle.textContent = '✓ В наличии';
			input.value = '1';
		}
	}
	</script>
</body>

</html>
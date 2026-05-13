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
        .image-preview-container {
            margin-top: 15px;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 5px;
            text-align: center;
        }
        .image-placeholder {
            width: 200px;
            height: 200px;
            background: #e9ecef;
            border: 2px dashed #dee2e6;
            border-radius: 5px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto;
            cursor: pointer;
            transition: all 0.3s;
            color: #999;
            font-size: 14px;
        }
        .image-placeholder:hover {
            background: #dee2e6;
            border-color: #adb5bd;
        }
        .image-preview-img {
            max-width: 200px;
            max-height: 200px;
            border-radius: 5px;
            margin: 0 auto;
        }
        .image-upload-input {
            display: none;
        }
        .btn-remove-image {
            margin-top: 10px;
            display: none;
        }
        .btn-remove-image.show {
            display: inline-block;
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
        .form-actions {
            margin-top: 20px;
            padding-top: 15px;
            border-top: 1px solid #dee2e6;
        }
        .form-actions button {
            margin-right: 10px;
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
    </style>
</head>

<body>
    <div class="container">
        <?php require __DIR__ . '/_nav.php'; ?>
        <h3>Товары</h3>
        <form method="post" enctype="multipart/form-data" class="well">
            <input type="hidden" name="id" value="<?= admin_h((string)($edit['id'] ?? '')) ?>">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
            <input type="hidden" name="image" id="imageInput" value="<?= admin_h((string)($edit['image'] ?? '')) ?>">
            
            <!-- Основные данные товара -->
            <div class="form-row-inline three-cols">
                <div class="form-group-wrapper">
                    <label for="external_id">ID товара *</label>
                    <input type="text" class="form-control" id="external_id" name="external_id" 
                        value="<?= admin_h((string)($edit['external_id'] ?? '')) ?>" required>
                </div>
                <div class="form-group-wrapper">
                    <label for="cat_number">Код в каталоге товаров</label>
                    <input type="text" class="form-control" id="cat_number" name="cat_number" 
                        value="<?= admin_h((string)($edit['cat_number'] ?? '')) ?>">
                </div>
                <div class="form-group-wrapper">
                    <label for="name">Название товара *</label>
                    <input type="text" class="form-control" id="name" name="name" 
                        value="<?= admin_h((string)($edit['name'] ?? '')) ?>" required>
                </div>
            </div>

            <!-- Цены -->
            <div class="form-row-inline two-cols">
                <div class="form-group-wrapper">
                    <label for="price">Цена (текущая) *</label>
                    <input type="number" step="0.01" class="form-control" id="price" name="price" 
                        value="<?= admin_h((string)($edit['price'] ?? '0')) ?>" required>
                </div>
                <div class="form-group-wrapper">
                    <label for="old_price">Старая цена</label>
                    <input type="number" step="0.01" class="form-control" id="old_price" name="old_price" 
                        value="<?= admin_h((string)($edit['old_price'] ?? '0')) ?>">
                </div>
            </div>

            <!-- URL товара та Об'єм -->
            <div class="form-row-inline two-cols">
                <div class="form-group-wrapper">
                    <label for="link">URL (Slug) *</label>
                    <input type="text" class="form-control" id="link" name="link" 
                        value="<?= admin_h((string)($edit['link'] ?? '')) ?>" required>
                </div>
                <div class="form-group-wrapper">
                    <label for="volume">Об'єм</label>
                    <input type="text" class="form-control" id="volume" name="volume" 
                        value="<?= admin_h((string)($edit['volume'] ?? '')) ?>" placeholder="напр. 500мл, 1л">
                </div>
            </div>

            <!-- Фото товара -->
            <div class="form-group-wrapper">
                <label>Фото товара</label>
                <div class="image-preview-container">
                    <div id="imagePreview" class="image-placeholder" onclick="document.getElementById('imageUpload').click();">
                        <?php if (!empty($edit['image'])): ?>
                            <img src="/<?= admin_h((string)$edit['image']) ?>" alt="Product" class="image-preview-img">
                        <?php else: ?>
                            <span>📷 Нажмите для загрузки фото</span>
                        <?php endif; ?>
                    </div>
                    <input type="file" id="imageUpload" class="image-upload-input" name="image_upload" accept="image/*">
                    <?php if (!empty($edit['image'])): ?>
                        <button type="button" class="btn btn-danger btn-sm btn-remove-image show" onclick="removeImage()">Удалить фото</button>
                    <?php else: ?>
                        <button type="button" class="btn btn-danger btn-sm btn-remove-image" onclick="removeImage()">Удалить фото</button>
                    <?php endif; ?>
                </div>
            </div>

            <!-- SEO данные -->
            <div class="form-row-inline two-cols">
                <div class="form-group-wrapper">
                    <label for="seo_title">SEO Title</label>
                    <input type="text" class="form-control" id="seo_title" name="seo_title" 
                        value="<?= admin_h((string)($edit['seo_title'] ?? '')) ?>">
                </div>
                <div class="form-group-wrapper">
                    <label for="seo_description">SEO Description</label>
                    <input type="text" class="form-control" id="seo_description" name="seo_description" 
                        value="<?= admin_h((string)($edit['seo_description'] ?? '')) ?>">
                </div>
            </div>

            <!-- Описания -->
            <div class="form-group-wrapper">
                <label for="short_desc">Краткое описание</label>
                <textarea class="form-control" id="short_desc" rows="2" name="short_desc"><?= admin_h((string)($edit['short_desc'] ?? '')) ?></textarea>
            </div>

            <div class="form-row-inline two-cols">
                <div class="form-group-wrapper">
                    <label for="desc">Описание</label>
                    <textarea class="form-control" id="desc" rows="4" name="desc"><?= admin_h((string)($edit['desc'] ?? '')) ?></textarea>
                </div>
                <div class="form-group-wrapper">
                    <label for="full_desc">Полное описание</label>
                    <textarea class="form-control" id="full_desc" rows="4" name="full_desc"><?= admin_h((string)($edit['full_desc'] ?? '')) ?></textarea>
                </div>
            </div>

            <!-- Статус и наличие -->
            <div class="form-row-inline two-cols">
                <div class="form-group-wrapper">
                    <label for="status">Статус</label>
                    <input type="text" class="form-control" id="status" name="status" 
                        placeholder="preorder, limited, etc." value="<?= admin_h((string)($edit['status'] ?? '')) ?>">
                </div>
                <div class="form-group-wrapper">
                    <label>&nbsp;</label>
                    <div class="checkbox-wrapper">
                        <input type="checkbox" id="in_stock" name="in_stock" value="1" <?= !empty($edit['in_stock']) ? 'checked' : '' ?>>
                        <label for="in_stock">В наличии</label>
                    </div>
                </div>
            </div>

            <!-- Кнопки действия -->
            <div class="form-actions">
                <button type="submit" class="btn btn-success btn-lg"><?= $edit ? 'Сохранить изменения' : 'Добавить товар' ?></button>
                <?php if ($edit): ?>
                    <a href="/admin/products.php" class="btn btn-secondary btn-lg">Отмена</a>
                <?php endif; ?>
            </div>
        </form>

        <!-- Таблица товаров -->
        <h4 style="margin-top: 40px;">Список товаров</h4>
        <table class="table table-bordered table-striped">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Код каталога</th>
                    <th>Slug</th>
                    <th>Название</th>
                    <th>Об'єм</th>
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
        function removeImage() {
            document.getElementById('imageInput').value = '';
            document.getElementById('imageUpload').value = '';
            const preview = document.getElementById('imagePreview');
            preview.innerHTML = '<span>📷 Нажмите для загрузки фото</span>';
            document.querySelector('.btn-remove-image').classList.remove('show');
        }

        // Клик на превью для открытия диалога выбора файла
        document.getElementById('imagePreview').addEventListener('click', function() {
            document.getElementById('imageUpload').click();
        });
    </script>
</body>

</html>

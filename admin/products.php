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
            }
        }
    }

    if ($id > 0) {
        $data['id'] = $id;
        $sql = 'UPDATE products SET external_id=:external_id, cat_number=:cat_number, name=:name, old_price=:old_price, price=:price, image=:image, link=:link, short_desc=:short_desc, `desc`=:desc, full_desc=:full_desc, in_stock=:in_stock, status=:status, seo_title=:seo_title, seo_description=:seo_description WHERE id=:id';
    } else {
        $sql = 'INSERT INTO products (external_id, cat_number, name, old_price, price, image, link, short_desc, `desc`, full_desc, in_stock, status, seo_title, seo_description) VALUES (:external_id,:cat_number,:name,:old_price,:price,:image,:link,:short_desc,:desc,:full_desc,:in_stock,:status,:seo_title,:seo_description)';
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
</head>

<body>
    <div class="container">
        <?php require __DIR__ . '/_nav.php'; ?>
        <h3>Товары</h3>
        <form method="post" enctype="multipart/form-data" class="well">
            <input type="hidden" name="id" value="<?= admin_h((string)($edit['id'] ?? '')) ?>">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
            <div class="row">
                <div class="col-md-2"><input class="form-control" name="external_id" placeholder="ID"
                        value="<?= admin_h((string)($edit['external_id'] ?? '')) ?>" required></div>
                <div class="col-md-2"><input class="form-control" name="cat_number" placeholder="Cat"
                        value="<?= admin_h((string)($edit['cat_number'] ?? '')) ?>"></div>
                <div class="col-md-4"><input class="form-control" name="name" placeholder="Название"
                        value="<?= admin_h((string)($edit['name'] ?? '')) ?>" required></div>
                <div class="col-md-2"><input class="form-control" name="price" placeholder="Цена"
                        value="<?= admin_h((string)($edit['price'] ?? '0')) ?>" required></div>
                <div class="col-md-2"><input class="form-control" name="old_price" placeholder="Старая цена"
                        value="<?= admin_h((string)($edit['old_price'] ?? '0')) ?>"></div>
            </div>
            <div class="row" style="margin-top:10px;">
                <div class="col-md-4"><input class="form-control" name="link" placeholder="/slug"
                        value="<?= admin_h((string)($edit['link'] ?? '')) ?>" required></div>
                <div class="col-md-4"><input class="form-control" name="image" placeholder="Путь к фото"
                        value="<?= admin_h((string)($edit['image'] ?? '')) ?>"></div>
                <div class="col-md-4"><input type="file" class="form-control" name="image_upload"></div>
            </div>
            <div class="row" style="margin-top:10px;">
                <div class="col-md-6"><input class="form-control" name="seo_title" placeholder="SEO title"
                        value="<?= admin_h((string)($edit['seo_title'] ?? '')) ?>"></div>
                <div class="col-md-6"><input class="form-control" name="seo_description" placeholder="SEO description"
                        value="<?= admin_h((string)($edit['seo_description'] ?? '')) ?>"></div>
            </div>
            <div class="row" style="margin-top:10px;">
                <div class="col-md-12"><textarea class="form-control" rows="2" name="short_desc"
                        placeholder="Краткое описание"><?= admin_h((string)($edit['short_desc'] ?? '')) ?></textarea></div>
            </div>
            <div class="row" style="margin-top:10px;">
                <div class="col-md-6"><textarea class="form-control" rows="4" name="desc"
                        placeholder="Описание"><?= admin_h((string)($edit['desc'] ?? '')) ?></textarea></div>
                <div class="col-md-6"><textarea class="form-control" rows="4" name="full_desc"
                        placeholder="Полное описание"><?= admin_h((string)($edit['full_desc'] ?? '')) ?></textarea></div>
            </div>
            <div style="margin-top:10px;">
                <label><input type="checkbox" name="in_stock" value="1" <?= !empty($edit['in_stock']) ? 'checked' : '' ?>> В
                    наличии</label>
                <input class="form-control" style="display:inline-block; width:220px; margin-left:10px;" name="status"
                    placeholder="status (preorder)" value="<?= admin_h((string)($edit['status'] ?? '')) ?>">
                <button class="btn btn-success" style="margin-left:10px;"><?= $edit ? 'Сохранить' : 'Добавить товар' ?></button>
            </div>
        </form>

        <table class="table table-bordered table-striped">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Slug</th>
                    <th>Название</th>
                    <th>Цена</th>
                    <th>SEO</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($products as $p): ?>
                    <tr>
                        <td><?= admin_h((string)$p['external_id']) ?></td>
                        <td><?= admin_h((string)$p['link']) ?></td>
                        <td><?= admin_h((string)$p['name']) ?></td>
                        <td><?= admin_h((string)$p['price']) ?></td>
                        <td><?= admin_h((string)$p['seo_title']) ?></td>
                        <td><a href="/admin/products.php?edit=<?= (int)$p['id'] ?>">Редактировать</a></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</body>

</html>
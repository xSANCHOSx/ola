<?php

declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../lib/feed_generator.php';
admin_require_permission('products', 'view');
$pdo = dev_db_connection();
$errors = [];
$summary = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    admin_require_permission('products', 'edit');
    if (!validate_csrf_token()) {
        $errors[] = 'Сессия формы устарела. Обновите страницу.';
    } elseif (empty($_FILES['csv_file']['tmp_name']) || !is_uploaded_file($_FILES['csv_file']['tmp_name'])) {
        $errors[] = 'Выберите CSV-файл.';
    } else {
        $handle = fopen($_FILES['csv_file']['tmp_name'], 'rb');
        $firstLine = $handle ? (string)fgets($handle) : '';
        $delimiter = substr_count($firstLine, ';') >= substr_count($firstLine, ',') ? ';' : ',';
        if ($handle) rewind($handle);
        $headers = $handle ? fgetcsv($handle, 0, $delimiter) : false;
        if (!$headers) {
            $errors[] = 'CSV-файл пустой или имеет неверный формат.';
        } else {
            $headers = array_map(static function ($header): string {
                return strtolower(trim((string)$header, " \t\n\r\0\x0B\xEF\xBB\xBF"));
            }, $headers);
            $required = ['external_id', 'name', 'price', 'link'];
            if (array_diff($required, $headers)) {
                $errors[] = 'В CSV обязательны колонки: external_id, name, price, link.';
            } else {
                $inserted = 0;
                $updated = 0;
                $line = 1;
                try {
                    $pdo->beginTransaction();
                    while (($values = fgetcsv($handle, 0, $delimiter)) !== false) {
                        $line++;
                        if (count(array_filter($values, static fn($value) => trim((string)$value) !== '')) === 0) continue;
                        $row = [];
                        foreach ($headers as $index => $header) $row[$header] = trim((string)($values[$index] ?? ''));
                        if ($row['external_id'] === '' || $row['name'] === '' || $row['link'] === '' || !is_numeric($row['price']) || (float)$row['price'] < 0) {
                            $errors[] = 'Строка ' . $line . ': обязательные поля заполнены неверно.';
                            continue;
                        }
                        $existing = $pdo->prepare('SELECT id FROM products WHERE external_id = :external_id LIMIT 1');
                        $existing->execute(['external_id' => $row['external_id']]);
                        $id = (int)($existing->fetchColumn() ?: 0);
                        $data = [
                            'external_id' => $row['external_id'], 'cat_number' => $row['cat_number'] ?? '', 'name' => $row['name'],
                            'old_price' => is_numeric($row['old_price'] ?? '') ? (float)$row['old_price'] : 0, 'price' => (float)$row['price'],
                            'link' => $row['link'], 'in_stock' => (($row['in_stock'] ?? '1') === '0' ? 0 : 1),
                            'status' => in_array($row['status'] ?? '', ['active', 'preorder', 'disabled'], true) ? $row['status'] : 'disabled',
                            'seo_title' => $row['seo_title'] ?? '', 'seo_description' => $row['seo_description'] ?? '', 'volume' => $row['volume'] ?? '',
                        ];
                        if ($id > 0) {
                            // external_id используется для поиска записи, но не является
                            // параметром UPDATE. Передаём только именованные параметры,
                            // присутствующие в SQL-запросе.
                            $updateData = [
                                'id' => $id,
                                'cat_number' => $data['cat_number'],
                                'name' => $data['name'],
                                'old_price' => $data['old_price'],
                                'price' => $data['price'],
                                'link' => $data['link'],
                                'in_stock' => $data['in_stock'],
                                'status' => $data['status'],
                                'seo_title' => $data['seo_title'],
                                'seo_description' => $data['seo_description'],
                                'volume' => $data['volume'],
                            ];
                            $pdo->prepare('UPDATE products SET cat_number=:cat_number, name=:name, old_price=:old_price, price=:price, link=:link, in_stock=:in_stock, status=:status, seo_title=:seo_title, seo_description=:seo_description, volume=:volume WHERE id=:id')->execute($updateData);
                            $updated++;
                        } else {
                            $pdo->prepare('INSERT INTO products (external_id, cat_number, name, old_price, price, link, in_stock, status, seo_title, seo_description, volume) VALUES (:external_id,:cat_number,:name,:old_price,:price,:link,:in_stock,:status,:seo_title,:seo_description,:volume)')->execute($data);
                            $inserted++;
                        }
                    }
                    $pdo->commit();
                    try { generate_product_feeds($pdo, dirname(__DIR__)); } catch (Throwable $e) { dev_log_runtime('CSV import feed regeneration failed: ' . $e->getMessage()); }
                    $summary = "Импорт завершён: добавлено {$inserted}, обновлено {$updated}.";
                } catch (Throwable $e) {
                    if ($pdo->inTransaction()) $pdo->rollBack();
                    $errors[] = 'Импорт остановлен: проверьте уникальность external_id и link.';
                    dev_log_runtime('CSV import failed: ' . $e->getMessage());
                }
            }
        }
        if (is_resource($handle)) fclose($handle);
    }
}
?>
<!doctype html>
<html lang="ru"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>CSV импорт товаров</title><link rel="stylesheet" href="/css/bootstrap.min.css"><link rel="stylesheet" href="/css/admin.css"></head>
<body><div class="container"><?php require __DIR__ . '/_nav.php'; ?><h3>CSV импорт товаров</h3>
<?php if ($summary): ?><div class="alert alert-success"><?= admin_h($summary) ?></div><?php endif; ?>
<?php foreach ($errors as $error): ?><div class="alert alert-danger"><?= admin_h($error) ?></div><?php endforeach; ?>
<p>Обязательные колонки: <code>external_id</code>, <code>name</code>, <code>price</code>, <code>link</code>. Дополнительно поддерживаются <code>cat_number</code>, <code>old_price</code>, <code>in_stock</code>, <code>status</code>, <code>seo_title</code>, <code>seo_description</code>, <code>volume</code>.</p>
<form method="post" enctype="multipart/form-data"><input type="hidden" name="csrf_token" value="<?= csrf_token() ?>"><div class="form-group"><label for="csv_file">CSV-файл</label><input class="form-control" type="file" id="csv_file" name="csv_file" accept=".csv,text/csv" required></div><button class="btn btn-primary" type="submit">Импортировать</button> <a class="btn btn-secondary" href="/admin/products.php">Назад к товарам</a></form>
</div></body></html>

<?php
declare(strict_types=1);

/**
 * Generates both product feeds from the current products database.
 * Returns the absolute paths of the written files.
 */
function generate_product_feeds(PDO $pdo, ?string $documentRoot = null): array
{
    $root = $documentRoot !== null && $documentRoot !== ''
        ? rtrim($documentRoot, '/')
        : dirname(__DIR__);
    $domain = function_exists('site_domain') ? site_domain() : (getenv('SITE_DOMAIN') ?: 'localhost');
    $domain = preg_replace('#^https?://#i', '', trim((string)$domain));
    $baseUrl = 'https://' . rtrim((string)$domain, '/');

    $products = [];
    $stmt = $pdo->query(
        'SELECT id AS db_id, external_id, name, old_price, price, image, link, `desc`, in_stock, status
         FROM products ORDER BY id ASC'
    );
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $productIndexes = [];
    foreach ($rows as $row) {
        $productIndexes[(int)$row['db_id']] = count($products);
        $products[] = [
            'db_id' => (int)$row['db_id'],
            'id' => (string)$row['external_id'],
            'name' => (string)$row['name'],
            'old_price' => (float)($row['old_price'] ?? 0),
            'price' => (float)$row['price'],
            'image' => trim((string)($row['image'] ?? '')),
            'link' => (string)$row['link'],
            'desc' => (string)($row['desc'] ?? ''),
            'in_stock' => (bool)$row['in_stock'],
            'status' => $row['status'] !== null ? (string)$row['status'] : null,
            'gallery' => [],
        ];
    }

    if ($productIndexes) {
        try {
            $placeholders = implode(',', array_fill(0, count($productIndexes), '?'));
            $galleryStmt = $pdo->prepare(
                "SELECT product_id, image FROM product_images
                 WHERE product_id IN ($placeholders)
                 ORDER BY sort_order ASC, id ASC"
            );
            $galleryStmt->execute(array_keys($productIndexes));
            foreach ($galleryStmt->fetchAll(PDO::FETCH_ASSOC) as $galleryRow) {
                $productId = (int)$galleryRow['product_id'];
                $index = $productIndexes[$productId] ?? null;
                $image = trim((string)($galleryRow['image'] ?? ''));
                if ($index !== null && $image !== '' && $image !== $products[$index]['image']
                    && !in_array($image, $products[$index]['gallery'], true)
                    && count($products[$index]['gallery']) < 10) {
                    $products[$index]['gallery'][] = $image;
                }
            }
        } catch (Throwable $e) {
            if (function_exists('dev_log_runtime')) {
                dev_log_runtime('Feed gallery load skipped: ' . $e->getMessage());
            }
        }
    }

    if (!class_exists('SimpleXMLElement') || !class_exists('DOMDocument') || !class_exists('XMLWriter')) {
        throw new RuntimeException('PHP XML extensions (SimpleXML, DOM and XMLWriter) are required to create feeds.');
    }

    $yandexXml = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><yml_catalog date="' . date('Y-m-d H:i') . '"></yml_catalog>');
    $shop = $yandexXml->addChild('shop');
    $shop->addChild('name', feed_xml_text($domain));
    $shop->addChild('company', feed_xml_text('Olaplex'));
    $shop->addChild('url', feed_xml_text($baseUrl));
    $currencies = $shop->addChild('currencies');
    $currency = $currencies->addChild('currency');
    $currency->addAttribute('id', 'RUB');
    $currency->addAttribute('rate', '1');
    $categories = $shop->addChild('categories');
    $category = $categories->addChild('category', feed_xml_text('Защита для волос'));
    $category->addAttribute('id', '1');
    $delivery = $shop->addChild('delivery-options');
    $deliveryOption = $delivery->addChild('option');
    $deliveryOption->addAttribute('cost', '0');
    $deliveryOption->addAttribute('days', '1');
    $offers = $shop->addChild('offers');

    foreach ($products as $product) {
        $offer = $offers->addChild('offer');
        $offer->addAttribute('id', $product['id']);
        $isPreorder = $product['status'] === 'preorder';
        $offer->addAttribute('available', $isPreorder || !$product['in_stock'] ? 'false' : 'true');
        if ($isPreorder) {
            $offerDelivery = $offer->addChild('delivery-options');
            $offerOption = $offerDelivery->addChild('option');
            $offerOption->addAttribute('cost', '0');
            $offerOption->addAttribute('days', '7-14');
        }
        $offer->addChild('url', feed_xml_text($baseUrl . '/' . ltrim($product['link'], '/')));
        foreach (array_merge([$product['image']], $product['gallery']) as $image) {
            if ($image !== '') {
                $offer->addChild('picture', feed_xml_text($baseUrl . '/' . ltrim($image, '/')));
            }
        }
        $offer->addChild('name', feed_xml_text($product['name']));
        $offer->addChild('description', feed_xml_text(strip_tags($product['desc'])));
        $offer->addChild('sales_notes', feed_xml_text('Бесплатная доставка по МСК при заказе от 5000р.'));
        $offer->addChild('vendor', feed_xml_text('Olaplex'));
        $offer->addChild('vendorCode', feed_xml_text($product['id']));
        $offer->addChild('currencyId', feed_xml_text('RUB'));
        $offer->addChild('categoryId', feed_xml_text('1'));
        if ($product['old_price'] > $product['price']) {
            $offer->addChild('oldprice', (string)$product['old_price']);
        }
        $offer->addChild('price', (string)$product['price']);
    }

    $yandexDom = new DOMDocument('1.0', 'UTF-8');
    $yandexDom->preserveWhiteSpace = false;
    $yandexDom->formatOutput = true;
    if (!$yandexDom->loadXML($yandexXml->asXML())) {
        throw new RuntimeException('Could not build Yandex feed XML.');
    }
    $yandexPath = $root . '/yandex_feed.xml';
    $googlePath = $root . '/google_feed.xml';
    write_feed_atomically($yandexPath, $yandexDom->saveXML());

    $writer = new XMLWriter();
    if (!$writer->openMemory()) {
        throw new RuntimeException('Could not initialize Google feed XML writer.');
    }
    $writer->startDocument('1.0', 'UTF-8');
    $writer->setIndent(true);
    $writer->startElement('rss');
    $writer->writeAttribute('version', '2.0');
    $writer->writeAttribute('xmlns:g', 'http://base.google.com/ns/1.0');
    $writer->startElement('channel');
    $writer->writeElement('title', $domain);
    $writer->writeElement('link', $baseUrl . '/');
    $writer->writeElement('description', 'Каталог средств Olaplex для ухода за волосами');
    foreach ($products as $product) {
        $writer->startElement('item');
        $writer->writeElement('g:id', $product['id']);
        $writer->writeElement('g:title', $product['name']);
        $writer->writeElement('g:description', strip_tags($product['desc']));
        $writer->writeElement('g:link', $baseUrl . '/' . ltrim($product['link'], '/'));
        if ($product['image'] !== '') {
            $writer->writeElement('g:image_link', $baseUrl . '/' . ltrim($product['image'], '/'));
        }
        foreach ($product['gallery'] as $image) {
            $writer->writeElement('g:additional_image_link', $baseUrl . '/' . ltrim($image, '/'));
        }
        $writer->writeElement('g:availability', $product['status'] === 'preorder' ? 'preorder' : ($product['in_stock'] ? 'in_stock' : 'out_of_stock'));
        if ($product['old_price'] > $product['price']) {
            $writer->writeElement('g:price', $product['old_price'] . ' RUB');
            $writer->writeElement('g:sale_price', $product['price'] . ' RUB');
        } else {
            $writer->writeElement('g:price', $product['price'] . ' RUB');
        }
        $writer->writeElement('g:brand', 'Olaplex');
        $writer->writeElement('g:condition', 'new');
        $writer->endElement();
    }
    $writer->endElement();
    $writer->endElement();
    $writer->endDocument();
    write_feed_atomically($googlePath, $writer->outputMemory());

    return [$yandexPath, $googlePath];
}

function write_feed_atomically(string $path, string $contents): void
{
    $directory = dirname($path);
    if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
        throw new RuntimeException('Feed directory does not exist and could not be created: ' . $directory);
    }
    $temporary = tempnam($directory, basename($path) . '.tmp-');
    if ($temporary === false || file_put_contents($temporary, $contents, LOCK_EX) === false) {
        throw new RuntimeException('Could not write temporary feed file: ' . $path);
    }
    @chmod($temporary, 0644);
    if (!rename($temporary, $path)) {
        @unlink($temporary);
        throw new RuntimeException('Could not replace feed file: ' . $path);
    }
}

function feed_xml_text(string $value): string
{
    return htmlspecialchars($value, ENT_XML1 | ENT_COMPAT, 'UTF-8');
}

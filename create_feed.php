<?php

declare(strict_types=1);

require_once __DIR__ . '/config/db.php';

$products = [];
$pdo = dev_db_connection();
if ($pdo instanceof PDO) {
	$stmt = $pdo->query('SELECT external_id, cat_number, name, old_price, price, image, link, short_desc, `desc`, full_desc, in_stock, status FROM products ORDER BY id ASC');
	$rows = $stmt->fetchAll();
	foreach ($rows as $row) {
		$products[] = [
			'id' => (string)$row['external_id'],
			'cat_number' => (string)($row['cat_number'] ?? ''),
			'name' => (string)$row['name'],
			'old_price' => (float)($row['old_price'] ?? 0),
			'price' => (float)$row['price'],
			'image' => (string)($row['image'] ?? ''),
			'link' => (string)$row['link'],
			'short_desc' => (string)($row['short_desc'] ?? ''),
			'desc' => (string)($row['desc'] ?? ''),
			'full_desc' => (string)($row['full_desc'] ?? ''),
			'in_stock' => (bool)$row['in_stock'],
			'status' => $row['status'] !== null ? (string)$row['status'] : null,
		];
	}
}

$xml = new SimpleXMLElement('<?xml version="1.0" encoding="utf-8"?>
<!DOCTYPE yml_catalog SYSTEM "shops.dtd">
<yml_catalog date="' . date('Y-m-d H:i') . '">
</yml_catalog>');

$shop = $xml->addChild('shop');
$shop->addChild('name', 'olaplex-shop.ru');
$shop->addChild('company', 'Olaplex');
$shop->addChild('url', 'https://olaplex-shop.ru');

// Добавляем валюту
$currencies = $shop->addChild('currencies');
$currency = $currencies->addChild('currency');
$currency->addAttribute('id', 'RUB');
$currency->addAttribute('rate', '1');

// Добавляем категории
$categories = $shop->addChild('categories');
$category = $categories->addChild('category', 'Защита для волос');
$category->addAttribute('id', '1');

// Опции доставки
$delivery = $shop->addChild('delivery-options');
$option = $delivery->addChild('option');
$option->addAttribute('cost', '0');
$option->addAttribute('days', '1');

// Добавляем товары
$offers = $shop->addChild('offers');
foreach ($products as $product) {
	$offer = $offers->addChild('offer');
	$offer->addAttribute('id', $product['id']);
	if (!empty($product['status']) && $product['status'] === 'preorder') {
		$offer->addAttribute('available', 'false');

		$offerDelivery = $offer->addChild('delivery-options');
		$offerOption = $offerDelivery->addChild('option');
		$offerOption->addAttribute('cost', '0');
		$offerOption->addAttribute('days', '7-14');
	} else {
		$available = (!empty($product['in_stock'])) ? 'true' : 'false';
		$offer->addAttribute('available', $available);
	}

	$offer->addChild('url', 'https://olaplex-shop.ru' . $product['link']);
	$offer->addChild('picture', 'https://olaplex-shop.ru/' . $product['image']);
	$offer->addChild('name', $product['name']);
	$offer->addChild('description', htmlspecialchars($product['desc']));
	$offer->addChild('sales_notes', 'Бесплатная доставка по МСК при заказе от 2500р.');
	$offer->addChild('vendor', 'Olaplex');
	$offer->addChild('vendorCode', 'Olaplex');
	$offer->addChild('barcode', '896364002329'); // Заменить на реальный штрихкод
	$offer->addChild('model', $product['name']);

	$param = $offer->addChild('param', '100');
	$param->addAttribute('name', 'Объем');
	$param->addAttribute('unit', 'мл');

	$offer->addChild('currencyId', 'RUB');
	$offer->addChild('categoryId', '1');
	$offer->addChild('oldprice', $product['old_price']);
	$offer->addChild('price', $product['price']);
}

// Сохраняем XML в файл с форматированием
$file = $_SERVER['DOCUMENT_ROOT'] . '/yandex_feed.xml';
$dom = new DOMDocument("1.0", "UTF-8");
$dom->preserveWhiteSpace = false;
$dom->formatOutput = true;
$dom->loadXML($xml->asXML());
$dom->save($file);

echo 'Фид успешно создан и сохранен. Для просмотра перейдите по ссылке <a
	href="https://olaplex-shop.ru/yandex_feed.xml">yandex_feed.xml</a>';

// Создаем объект XMLWriter
$xml = new XMLWriter();
$xml->openURI('google_feed.xml');
$xml->startDocument('1.0', 'UTF-8');
$xml->setIndent(true);

// Начало элемента rss
$xml->startElement('rss');
$xml->writeAttribute('version', '2.0');
$xml->writeAttribute('xmlns:g', 'http://base.google.com/ns/1.0');

// Начало элемента channel
$xml->startElement('channel');

// Основная информация о магазине
$xml->writeElement('title', 'olaplex-shop.ru');
$xml->writeElement('link', 'https://olaplex-shop.ru/');
$xml->writeElement('description', 'Описание вашего магазина');

// Добавляем товары
foreach ($products as $product) {
	$xml->startElement('item');

	$xml->writeElement('g:id', $product['id']);
	$xml->writeElement('g:title', $product['name']);
	$xml->writeElement('g:description', htmlspecialchars($product['desc']));
	$xml->writeElement('g:link', 'https://olaplex-shop.ru' . $product['link']);
	$xml->writeElement('g:image_link', 'https://olaplex-shop.ru/' . $product['image']);

	if (!empty($product['status']) && $product['status'] === 'preorder') {
		$availability = 'preorder';
	} else {
		$availability = (!empty($product['in_stock'])) ? 'in_stock' : 'out_of_stock';
	}
	$xml->writeElement('g:availability', $availability);

	if (!empty($product['old_price'])) {
		$xml->writeElement('g:price', $product['old_price'] . ' RUB');
		$xml->writeElement('g:sale_price', $product['price'] . ' RUB');
	} else {
		$xml->writeElement('g:price', $product['price'] . ' RUB');
	}

	$xml->writeElement('g:brand', 'Olaplex');

	if (!empty($product['gtin'])) {
		$xml->writeElement('g:gtin', $product['gtin']);
	}
	if (!empty($product['mpn'])) {
		$xml->writeElement('g:mpn', $product['mpn']);
	}
	if (!empty($product['condition'])) {
		$xml->writeElement('g:condition', $product['condition']);
	}

	if ($availability === 'preorder') {
		$xml->startElement('g:shipping');
		$xml->writeElement('g:country', 'RU');
		$xml->writeElement('g:service', 'Standard');
		$xml->writeElement('g:price', '0 RUB');
		$xml->endElement(); // g:shipping

		$xml->writeElement('g:delivery_time', '7-14');
	}

	$xml->endElement(); // Конец item
}

// Закрываем элементы channel и rss
$xml->endElement(); // Конец элемента channel
$xml->endElement(); // Конец элемента rss

// Завершаем документ
$xml->endDocument();

// Закрываем XMLWriter
$xml->flush();
echo '<br>';
echo 'Фид успешно создан и сохранен. Для просмотра перейдите по ссылке <a
	href="https://olaplex-shop.ru/google_feed.xml">google_feed.xml</a>';

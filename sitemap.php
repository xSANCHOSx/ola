<?php

declare(strict_types=1);

require_once __DIR__ . '/config/db.php';

$scheme  = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host    = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost';
$baseUrl = $scheme . '://' . $host;

// Статические страницы сайта
$staticPages = [
    ['loc' => '/',              'priority' => '1.0', 'changefreq' => 'daily'],
    ['loc' => '/catalog',       'priority' => '0.9', 'changefreq' => 'daily'],
    ['loc' => '/about',         'priority' => '0.5', 'changefreq' => 'monthly'],
    ['loc' => '/contacts',      'priority' => '0.5', 'changefreq' => 'monthly'],
    ['loc' => '/delivery',      'priority' => '0.6', 'changefreq' => 'monthly'],
];

// Загружаем товары из БД
$products = [];
$pdo = dev_db_connection();
if ($pdo instanceof PDO) {
    $stmt = $pdo->query('SELECT link, updated_at FROM products WHERE status != "deleted" OR status IS NULL ORDER BY id ASC');
    $rows = $stmt->fetchAll();
    foreach ($rows as $row) {
        $products[] = [
            'link'       => (string)$row['link'],
            'updated_at' => $row['updated_at'] ?? null,
        ];
    }
}

$now = date('Y-m-d');

$xml = new XMLWriter();
$xml->openMemory();
$xml->startDocument('1.0', 'UTF-8');
$xml->setIndent(true);
$xml->setIndentString('  ');

$xml->startElement('urlset');
$xml->writeAttribute('xmlns', 'http://www.sitemaps.org/schemas/sitemap/0.9');

// Статические страницы
foreach ($staticPages as $page) {
    $xml->startElement('url');
    $xml->writeElement('loc',        $baseUrl . $page['loc']);
    $xml->writeElement('lastmod',    $now);
    $xml->writeElement('changefreq', $page['changefreq']);
    $xml->writeElement('priority',   $page['priority']);
    $xml->endElement();
}

// Страницы товаров
foreach ($products as $product) {
    $lastmod = $product['updated_at']
        ? date('Y-m-d', strtotime($product['updated_at']))
        : $now;

    $xml->startElement('url');
    $xml->writeElement('loc',        $baseUrl . $product['link']);
    $xml->writeElement('lastmod',    $lastmod);
    $xml->writeElement('changefreq', 'weekly');
    $xml->writeElement('priority',   '0.8');
    $xml->endElement();
}

$xml->endElement(); // urlset
$xml->endDocument();

// Сохраняем файл
$file = $_SERVER['DOCUMENT_ROOT'] . '/sitemap.xml';
file_put_contents($file, $xml->outputMemory());

echo 'Sitemap успешно создан и сохранён. Для просмотра перейдите по ссылке <a href="' . $baseUrl . '/sitemap.xml">sitemap.xml</a>';

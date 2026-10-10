<?php
/**
 * Общие хлебные крошки для публичных страниц.
 * Перед подключением можно передать массив $breadcrumbs:
 * [['label' => 'Каталог', 'url' => '/catalog'], ['label' => 'Товар']]
 */
$breadcrumbItems = [];
foreach ((array)($breadcrumbs ?? []) as $item) {
    if (!is_array($item) || trim((string)($item['label'] ?? '')) === '') {
        continue;
    }
    $breadcrumbItems[] = [
        'label' => trim((string)$item['label']),
        'url' => isset($item['url']) && (string)$item['url'] !== '' ? (string)$item['url'] : null,
    ];
}

if (!$breadcrumbItems) {
    $breadcrumbItems[] = [
        'label' => trim((string)($pageTitle ?? 'Страница')),
        'url' => null,
    ];
}

$breadcrumbBaseUrl = 'https://' . preg_replace('/:\d+$/', '', (string)($_SERVER['HTTP_HOST'] ?? 'localhost'));
$breadcrumbBaseUrl = rtrim($breadcrumbBaseUrl, '/');
$breadcrumbCanonicalUrl = static function (?string $url) use ($breadcrumbBaseUrl): string {
    if ($url === null || $url === '') {
        $path = parse_url((string)($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/';
        return $breadcrumbBaseUrl . '/' . ltrim($path, '/');
    }
    if (preg_match('#^https?://#i', $url)) {
        return $url;
    }
    return $breadcrumbBaseUrl . '/' . ltrim($url, '/');
};

$breadcrumbSchemaItems = [[
    '@type' => 'ListItem',
    'position' => 1,
    'name' => 'Главная',
    'item' => $breadcrumbBaseUrl . '/',
]];
$breadcrumbSchemaSource = [];
foreach ((array)($breadcrumbSchema ?? $breadcrumbItems) as $item) {
    if (!is_array($item) || trim((string)($item['label'] ?? '')) === '') {
        continue;
    }
    $breadcrumbSchemaSource[] = [
        'label' => trim((string)$item['label']),
        'url' => isset($item['url']) && (string)$item['url'] !== '' ? (string)$item['url'] : null,
    ];
}
foreach ($breadcrumbSchemaSource as $position => $item) {
    $breadcrumbSchemaItems[] = [
        '@type' => 'ListItem',
        'position' => $position + 2,
        'name' => $item['label'],
        'item' => $breadcrumbCanonicalUrl($item['url']),
    ];
}
?>
<nav class="site-breadcrumb" aria-label="Хлебные крошки">
    <ol>
        <li><a href="/">Главная</a></li>
        <?php foreach ($breadcrumbItems as $itemIndex => $item): ?>
            <li aria-hidden="true">/</li>
            <li>
                <?php if ($item['url'] !== null && $itemIndex < count($breadcrumbItems) - 1): ?>
                    <a href="<?= htmlspecialchars($item['url'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8') ?></a>
                <?php else: ?>
                    <span aria-current="page"><?= htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8') ?></span>
                <?php endif; ?>
            </li>
        <?php endforeach; ?>
    </ol>
</nav>
<script type="application/ld+json">
<?= json_encode([
    '@context' => 'https://schema.org',
    '@type' => 'BreadcrumbList',
    'itemListElement' => $breadcrumbSchemaItems,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) ?>
</script>

<?php

declare(strict_types=1);

function ensure_url_redirects_table(PDO $pdo): void
{
    static $checked = [];
    $key = spl_object_id($pdo);
    if (!empty($checked[$key])) return;
    $driver = (string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    $sql = $driver === 'sqlite' ? "CREATE TABLE IF NOT EXISTS url_redirects (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        source_path VARCHAR(500) NOT NULL UNIQUE,
        target_path VARCHAR(500) NOT NULL,
        product_id INTEGER DEFAULT NULL,
        reason VARCHAR(80) NOT NULL DEFAULT 'manual',
        status_code INTEGER NOT NULL DEFAULT 301,
        is_active INTEGER NOT NULL DEFAULT 1,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )" : "CREATE TABLE IF NOT EXISTS url_redirects (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        source_path VARCHAR(500) NOT NULL,
        target_path VARCHAR(500) NOT NULL,
        product_id INT UNSIGNED DEFAULT NULL,
        reason VARCHAR(80) NOT NULL DEFAULT 'manual',
        status_code SMALLINT UNSIGNED NOT NULL DEFAULT 301,
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uq_url_redirects_source_path (source_path),
        KEY idx_url_redirects_active (is_active),
        KEY idx_url_redirects_product (product_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    $pdo->exec($sql);
    $checked[$key] = true;
}

function normalize_redirect_path(string $path): string
{
    $path = trim($path);
    if ($path === '') return '/';
    $parsed = parse_url($path, PHP_URL_PATH);
    $path = is_string($parsed) && $parsed !== '' ? $parsed : $path;
    return '/' . ltrim($path, '/');
}

function normalize_product_link(string $value): string
{
    $value = trim((string)(parse_url($value, PHP_URL_PATH) ?: $value), '/');
    $translit = ['а'=>'a','б'=>'b','в'=>'v','г'=>'g','д'=>'d','е'=>'e','ё'=>'e','ж'=>'zh','з'=>'z','и'=>'i','й'=>'j','к'=>'k','л'=>'l','м'=>'m','н'=>'n','о'=>'o','п'=>'p','р'=>'r','с'=>'s','т'=>'t','у'=>'u','ф'=>'f','х'=>'h','ц'=>'c','ч'=>'ch','ш'=>'sh','щ'=>'sch','ъ'=>'','ы'=>'y','ь'=>'','э'=>'e','ю'=>'yu','я'=>'ya'];
    $lower = function_exists('mb_strtolower') ? mb_strtolower($value) : strtolower($value);
    $value = strtr($lower, $translit);
    $value = preg_replace('/[^a-zA-Z0-9\s_\-]/u', '', $value) ?? '';
    $value = preg_replace('/[\s\-]+/', '_', $value) ?? '';
    return '/' . trim(strtolower($value), '_');
}

function redirect_path_from_request(): string
{
    return normalize_redirect_path((string)parse_url((string)($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH));
}

function find_active_redirect(PDO $pdo, string $sourcePath): ?array
{
    try {
        ensure_url_redirects_table($pdo);
        $stmt = $pdo->prepare("SELECT id, source_path, target_path, status_code FROM url_redirects WHERE source_path = :source_path AND is_active = 1 LIMIT 1");
        $stmt->execute(['source_path' => normalize_redirect_path($sourcePath)]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    } catch (Throwable $e) {
        // Миграция может быть ещё не применена: сайт продолжает работать без редиректов.
        if (function_exists('dev_log_runtime')) dev_log_runtime('Redirect lookup skipped: ' . $e->getMessage());
        return null;
    }
}

function save_url_redirect(PDO $pdo, string $sourcePath, string $targetPath, ?int $productId = null, string $reason = 'product_slug_change'): void
{
    $sourcePath = normalize_redirect_path($sourcePath);
    $targetPath = normalize_redirect_path($targetPath);
    if ($sourcePath === $targetPath) return;
    try {
        ensure_url_redirects_table($pdo);
        $find = $pdo->prepare('SELECT id FROM url_redirects WHERE source_path = :source_path LIMIT 1');
        $find->execute(['source_path' => $sourcePath]);
        $id = (int)($find->fetchColumn() ?: 0);
        if ($id > 0) {
            $stmt = $pdo->prepare('UPDATE url_redirects SET target_path = :target_path, product_id = :product_id, reason = :reason, is_active = 1, updated_at = CURRENT_TIMESTAMP WHERE id = :id');
            $stmt->execute(['target_path' => $targetPath, 'product_id' => $productId, 'reason' => $reason, 'id' => $id]);
        } else {
            $stmt = $pdo->prepare('INSERT INTO url_redirects (source_path, target_path, product_id, reason, status_code, is_active) VALUES (:source_path, :target_path, :product_id, :reason, 301, 1)');
            $stmt->execute(['source_path' => $sourcePath, 'target_path' => $targetPath, 'product_id' => $productId, 'reason' => $reason]);
        }
    } catch (Throwable $e) {
        throw new RuntimeException('Не удалось сохранить редирект.', 0, $e);
    }
}

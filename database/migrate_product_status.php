<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';

$pdo = dev_db_connection();
if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) !== 'mysql') {
    fwrite(STDERR, "Product status migration requires MySQL.\n");
    exit(1);
}

$version = '2026_11_26_products_status_varchar_and_visibility';
$pdo->exec(
    'CREATE TABLE IF NOT EXISTS schema_migrations (' .
    'version VARCHAR(191) NOT NULL PRIMARY KEY, ' .
    'applied_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP' .
    ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
);

$checkApplied = $pdo->prepare('SELECT 1 FROM schema_migrations WHERE version = :version LIMIT 1');
$checkApplied->execute(['version' => $version]);
if ($checkApplied->fetchColumn()) {
    echo "Migration {$version} already applied.\n";
    exit(0);
}

$columnsStatement = $pdo->query(
    "SELECT COLUMN_NAME, DATA_TYPE, CHARACTER_MAXIMUM_LENGTH FROM information_schema.COLUMNS " .
    "WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'products'"
);
$columns = [];
foreach ($columnsStatement->fetchAll(PDO::FETCH_ASSOC) as $column) {
    $columns[(string)$column['COLUMN_NAME']] = [
        'type' => strtolower((string)$column['DATA_TYPE']),
        'length' => $column['CHARACTER_MAXIMUM_LENGTH'] === null ? null : (int)$column['CHARACTER_MAXIMUM_LENGTH'],
    ];
}

if (!isset($columns['status'])) {
    $pdo->exec("ALTER TABLE products ADD COLUMN status VARCHAR(64) NULL DEFAULT NULL AFTER in_stock");
    echo "Added products.status as VARCHAR(64).\n";
} else {
    $statusType = $columns['status']['type'];
    $statusLength = $columns['status']['length'];
    $isTextType = in_array($statusType, ['varchar', 'char', 'tinytext', 'text', 'mediumtext', 'longtext'], true);
    if (!$isTextType || ($statusType === 'varchar' && $statusLength !== null && $statusLength < 64)) {
        $pdo->exec('ALTER TABLE products MODIFY COLUMN status VARCHAR(64) NULL DEFAULT NULL');
        echo "Changed products.status to VARCHAR(64).\n";
    }
}

if (!isset($columns['is_visible'])) {
    $pdo->exec(
        'ALTER TABLE products ADD COLUMN is_visible TINYINT(1) NOT NULL DEFAULT 1 ' .
        'COMMENT \'1 = visible, 0 = hidden\' AFTER status'
    );
    echo "Added products.is_visible.\n";
}

$indexCheck = $pdo->prepare(
    "SELECT 1 FROM information_schema.STATISTICS " .
    "WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'products' " .
    "AND INDEX_NAME = 'idx_products_is_visible' LIMIT 1"
);
$indexCheck->execute();
if (!$indexCheck->fetchColumn()) {
    $pdo->exec('ALTER TABLE products ADD KEY idx_products_is_visible (is_visible)');
}

$recordApplied = $pdo->prepare('INSERT INTO schema_migrations (version) VALUES (:version)');
$recordApplied->execute(['version' => $version]);
echo "Migration {$version} completed.\n";

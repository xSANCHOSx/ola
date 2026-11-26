<?php
declare(strict_types=1);

/**
 * Создаёт или обновляет таблицу заявок «Под заказ» без ручного запуска SQL.
 * Вызывается из endpoint и страницы админки; на одном PDO выполняется один раз.
 */
function ensure_preorder_requests_table(PDO $pdo): void
{
    static $completed = [];
    $key = spl_object_id($pdo);
    if (!empty($completed[$key])) return;

    $driver = (string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    if ($driver === 'sqlite') {
        $pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS preorder_requests (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  product_id INTEGER NOT NULL,
  product_external_id VARCHAR(32) NOT NULL,
  product_name_snapshot VARCHAR(255) NOT NULL,
  product_cat_number_snapshot VARCHAR(120) DEFAULT NULL,
  customer_name VARCHAR(255) NOT NULL,
  customer_phone VARCHAR(64) NOT NULL,
  customer_email VARCHAR(255) DEFAULT NULL,
  status VARCHAR(32) NOT NULL DEFAULT 'new' CHECK (status IN ('new', 'in_progress', 'processed', 'cancelled')),
  manager_comment TEXT DEFAULT NULL,
  processed_at DATETIME DEFAULT NULL,
  processed_by INTEGER DEFAULT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
)
SQL);
        $columns = [];
        foreach ($pdo->query('PRAGMA table_info(preorder_requests)')->fetchAll(PDO::FETCH_ASSOC) as $column) {
            $columns[(string)$column['name']] = true;
        }
        $additions = [
            'product_external_id' => 'VARCHAR(32)',
            'product_name_snapshot' => 'VARCHAR(255)',
            'product_cat_number_snapshot' => 'VARCHAR(120) DEFAULT NULL',
            'customer_name' => 'VARCHAR(255)',
            'customer_phone' => 'VARCHAR(64)',
            'customer_email' => 'VARCHAR(255) DEFAULT NULL',
            'status' => "VARCHAR(32) NOT NULL DEFAULT 'new'",
            'manager_comment' => 'TEXT DEFAULT NULL',
            'processed_at' => 'DATETIME DEFAULT NULL',
            'processed_by' => 'INTEGER DEFAULT NULL',
            'created_at' => 'DATETIME DEFAULT CURRENT_TIMESTAMP',
            'updated_at' => 'DATETIME DEFAULT CURRENT_TIMESTAMP',
        ];
        foreach ($additions as $column => $definition) {
            if (empty($columns[$column])) {
                $pdo->exec('ALTER TABLE preorder_requests ADD COLUMN ' . $column . ' ' . $definition);
            }
        }
        $pdo->exec("UPDATE preorder_requests SET status = CASE status
            WHEN 'pending' THEN 'new'
            WHEN 'contacted' THEN 'in_progress'
            WHEN 'approved' THEN 'processed'
            WHEN 'ordered' THEN 'processed'
            WHEN 'closed' THEN 'processed'
            WHEN 'rejected' THEN 'cancelled'
            ELSE status END");
        if (!empty($columns['admin_note'])) {
            $pdo->exec('UPDATE preorder_requests SET manager_comment = admin_note WHERE manager_comment IS NULL AND admin_note IS NOT NULL');
        }
        preorder_ensure_sqlite_index($pdo, 'idx_preorder_requests_status_created', 'status, created_at');
        preorder_ensure_sqlite_index($pdo, 'idx_preorder_requests_product_created', 'product_id, created_at');
        preorder_ensure_sqlite_index($pdo, 'idx_preorder_requests_processed_by', 'processed_by');
        $completed[$key] = true;
        return;
    }

    $pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS preorder_requests (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  product_id BIGINT UNSIGNED NOT NULL,
  product_external_id VARCHAR(32) NOT NULL,
  product_name_snapshot VARCHAR(255) NOT NULL,
  product_cat_number_snapshot VARCHAR(120) DEFAULT NULL,
  customer_name VARCHAR(255) NOT NULL,
  customer_phone VARCHAR(64) NOT NULL,
  customer_email VARCHAR(255) DEFAULT NULL,
  status VARCHAR(32) NOT NULL DEFAULT 'new',
  manager_comment TEXT DEFAULT NULL,
  processed_at DATETIME DEFAULT NULL,
  processed_by INT UNSIGNED DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_preorder_requests_status_created (status, created_at),
  KEY idx_preorder_requests_product_created (product_id, created_at),
  KEY idx_preorder_requests_processed_by (processed_by),
  CONSTRAINT fk_preorder_requests_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE RESTRICT,
  CONSTRAINT fk_preorder_requests_admin FOREIGN KEY (processed_by) REFERENCES admin_users(id) ON DELETE SET NULL,
  CONSTRAINT chk_preorder_requests_status CHECK (status IN ('new', 'in_progress', 'processed', 'cancelled'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
SQL);

    $columns = [];
    foreach ($pdo->query('SHOW COLUMNS FROM preorder_requests')->fetchAll(PDO::FETCH_ASSOC) as $column) {
        $columns[(string)$column['Field']] = true;
    }
    $additions = [
        'status' => "VARCHAR(32) NOT NULL DEFAULT 'new'",
        'product_cat_number_snapshot' => 'VARCHAR(120) DEFAULT NULL',
        'manager_comment' => 'TEXT DEFAULT NULL',
        'processed_at' => 'DATETIME DEFAULT NULL',
        'processed_by' => 'INT UNSIGNED DEFAULT NULL',
    ];
    foreach ($additions as $column => $definition) {
        if (empty($columns[$column])) {
            $pdo->exec('ALTER TABLE preorder_requests ADD COLUMN ' . $column . ' ' . $definition);
        }
    }

    // Старые версии имели ENUM и другие названия статусов. Сначала расширяем тип,
    // затем нормализуем значения и только после этого добавляем CHECK.
    $pdo->exec("ALTER TABLE preorder_requests MODIFY COLUMN status VARCHAR(32) NOT NULL DEFAULT 'new'");
    $pdo->exec("UPDATE preorder_requests SET status = CASE status
        WHEN 'pending' THEN 'new'
        WHEN 'contacted' THEN 'in_progress'
        WHEN 'approved' THEN 'processed'
        WHEN 'ordered' THEN 'processed'
        WHEN 'closed' THEN 'processed'
        WHEN 'rejected' THEN 'cancelled'
        ELSE status END");
    if (!empty($columns['admin_note'])) {
        $pdo->exec('UPDATE preorder_requests SET manager_comment = admin_note WHERE manager_comment IS NULL AND admin_note IS NOT NULL');
    }

    if (!empty($columns['customer_phone_normalized'])) {
        $pdo->exec('ALTER TABLE preorder_requests MODIFY COLUMN customer_phone_normalized VARCHAR(64) NULL DEFAULT NULL');
    }
    $indexStmt = $pdo->query('SHOW INDEX FROM preorder_requests');
    $existingIndexes = [];
    foreach ($indexStmt->fetchAll(PDO::FETCH_ASSOC) as $index) {
        $existingIndexes[(string)$index['Key_name']] = true;
    }
    if (empty($existingIndexes['idx_preorder_requests_processed_by'])) {
        $pdo->exec('ALTER TABLE preorder_requests ADD KEY idx_preorder_requests_processed_by (processed_by)');
    }

    try {
        $pdo->exec("ALTER TABLE preorder_requests ADD CONSTRAINT chk_preorder_requests_status CHECK (status IN ('new', 'in_progress', 'processed', 'cancelled'))");
    } catch (Throwable $e) {
        // Ограничение уже существует — повторный запуск должен быть безопасным.
    }

    // Приводим старую связь товара к RESTRICT, чтобы история заявок не терялась.
    try {
        $constraintStmt = $pdo->prepare(
            "SELECT CONSTRAINT_NAME, DELETE_RULE
             FROM information_schema.REFERENTIAL_CONSTRAINTS
             WHERE CONSTRAINT_SCHEMA = DATABASE()
               AND TABLE_NAME = 'preorder_requests'
               AND UNIQUE_CONSTRAINT_TABLE_NAME = 'products'"
        );
        $constraintStmt->execute();
        foreach ($constraintStmt->fetchAll(PDO::FETCH_ASSOC) as $constraint) {
            if ((string)$constraint['DELETE_RULE'] !== 'RESTRICT') {
                $pdo->exec('ALTER TABLE preorder_requests DROP FOREIGN KEY `' . str_replace('`', '``', (string)$constraint['CONSTRAINT_NAME']) . '`');
            }
        }
        $pdo->exec('ALTER TABLE preorder_requests ADD CONSTRAINT fk_preorder_requests_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE RESTRICT');
    } catch (Throwable $e) {
        // Связь уже корректна или база не разрешает повторное объявление constraint.
    }

    try {
        $pdo->exec('ALTER TABLE preorder_requests ADD CONSTRAINT fk_preorder_requests_admin FOREIGN KEY (processed_by) REFERENCES admin_users(id) ON DELETE SET NULL');
    } catch (Throwable $e) {
        // Внешний ключ уже существует.
    }

    $completed[$key] = true;
}

function preorder_ensure_sqlite_index(PDO $pdo, string $name, string $columns): void
{
    $safeName = preg_replace('/[^a-zA-Z0-9_]/', '', $name) ?: $name;
    $exists = $pdo->prepare("SELECT 1 FROM sqlite_master WHERE type = 'index' AND name = :name LIMIT 1");
    $exists->execute(['name' => $safeName]);
    if (!$exists->fetchColumn()) {
        $pdo->exec('CREATE INDEX ' . $safeName . ' ON preorder_requests (' . $columns . ')');
    }
}

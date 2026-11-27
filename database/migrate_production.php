<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';

const OLA_MIGRATION_LEDGER = 'production_migrations';
const OLA_MIGRATION_LOCK = 'olaplex_production_migrations';
const OLA_MIGRATIONS = [
    '2026_11_27_preorder_requests' => 'migration_preorder_requests.sql',
    '2026_11_27_preorder_archive' => 'migration_preorder_archive.sql',
];

/** Apply pending production migrations using the application's PDO. */
function ola_apply_production_migrations(PDO $pdo): array
{
    if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) !== 'mysql') {
        throw new RuntimeException('Production migrations require MySQL.');
    }

    ola_ensure_migration_ledger($pdo);
    if (ola_pending_migrations($pdo) === []) {
        return [];
    }

    $lock = $pdo->prepare('SELECT GET_LOCK(?, 10)');
    $lock->execute([OLA_MIGRATION_LOCK]);
    if ((int)$lock->fetchColumn() !== 1) {
        throw new RuntimeException('Could not obtain the production migration lock.');
    }

    $appliedNow = [];
    try {
        // Re-check after acquiring the lock in case another request won the race.
        foreach (ola_pending_migrations($pdo) as $version => $filename) {
            ola_apply_migration($pdo, $version, $filename);
            $record = $pdo->prepare(
                'INSERT INTO `' . OLA_MIGRATION_LEDGER . '` (migration, checksum) VALUES (:migration, :checksum)'
            );
            $record->execute([
                'migration' => $version,
                'checksum' => ola_migration_checksum($filename),
            ]);
            $appliedNow[] = $version;
        }
    } finally {
        $release = $pdo->prepare('SELECT RELEASE_LOCK(?)');
        $release->execute([OLA_MIGRATION_LOCK]);
    }

    return $appliedNow;
}

/** @return array<string, string> */
function ola_pending_migrations(PDO $pdo): array
{
    $applied = ola_applied_migrations($pdo);
    $pending = [];
    foreach (OLA_MIGRATIONS as $version => $filename) {
        $checksum = ola_migration_checksum($filename);
        if (isset($applied[$version])) {
            if (!hash_equals($applied[$version]['checksum'], $checksum)) {
                throw new RuntimeException('Checksum mismatch for applied migration ' . $version . '.');
            }
            continue;
        }
        $pending[$version] = $filename;
    }
    return $pending;
}

/** @return array<string, array{checksum:string, applied_at:string}> */
function ola_applied_migrations(PDO $pdo): array
{
    if (!ola_migration_ledger_exists($pdo)) {
        return [];
    }

    $statement = $pdo->query(
        'SELECT migration, checksum, applied_at FROM `' . OLA_MIGRATION_LEDGER . '` ORDER BY migration'
    );
    $result = [];
    foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $result[(string)$row['migration']] = [
            'checksum' => (string)$row['checksum'],
            'applied_at' => (string)$row['applied_at'],
        ];
    }
    return $result;
}

function ola_migration_ledger_exists(PDO $pdo): bool
{
    $statement = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?'
    );
    $statement->execute([OLA_MIGRATION_LEDGER]);
    return (int)$statement->fetchColumn() === 1;
}

function ola_ensure_migration_ledger(PDO $pdo): void
{
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS `' . OLA_MIGRATION_LEDGER . '` ('
        . 'migration VARCHAR(190) NOT NULL PRIMARY KEY, '
        . 'checksum CHAR(64) NOT NULL, '
        . 'applied_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP'
        . ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );
}

function ola_migration_checksum(string $filename): string
{
    $path = __DIR__ . DIRECTORY_SEPARATOR . $filename;
    if (!is_file($path) || !is_readable($path)) {
        throw new RuntimeException('Migration source is unavailable: ' . $filename);
    }
    $checksum = hash_file('sha256', $path);
    if ($checksum === false) {
        throw new RuntimeException('Could not checksum migration source: ' . $filename);
    }
    return $checksum;
}

function ola_apply_migration(PDO $pdo, string $version, string $filename): void
{
    if ($version === '2026_11_27_preorder_requests' && $filename === 'migration_preorder_requests.sql') {
        $sql = file_get_contents(__DIR__ . DIRECTORY_SEPARATOR . $filename);
        if ($sql === false || trim($sql) === '') {
            throw new RuntimeException('Preorder migration file is empty or unreadable.');
        }
        // CREATE TABLE IF NOT EXISTS is safe to retry; the helper reconciles legacy layouts.
        $pdo->exec($sql);
        require_once __DIR__ . '/../lib/preorder_requests.php';
        ensure_preorder_requests_table($pdo);
        return;
    }

    if ($version === '2026_11_27_preorder_archive' && $filename === 'migration_preorder_archive.sql') {
        ola_ensure_preorder_archive_schema($pdo);
        return;
    }

    throw new RuntimeException('Unknown production migration: ' . $version);
}

/** Apply archive DDL one item at a time so a partial failure can be retried safely. */
function ola_ensure_preorder_archive_schema(PDO $pdo): void
{
    $columns = [
        'is_archived' => 'TINYINT(1) NOT NULL DEFAULT 0',
        'archived_at' => 'DATETIME DEFAULT NULL',
        'archived_by' => 'INT UNSIGNED DEFAULT NULL',
    ];
    foreach ($columns as $name => $definition) {
        $exists = $pdo->prepare(
            'SELECT COUNT(*) FROM information_schema.columns '
            . 'WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?'
        );
        $exists->execute(['preorder_requests', $name]);
        if ((int)$exists->fetchColumn() === 0) {
            $pdo->exec('ALTER TABLE `preorder_requests` ADD COLUMN `' . $name . '` ' . $definition);
        }
    }

    $index = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.statistics '
        . 'WHERE table_schema = DATABASE() AND table_name = ? AND index_name = ?'
    );
    $index->execute(['preorder_requests', 'idx_preorder_requests_archived_created']);
    if ((int)$index->fetchColumn() === 0) {
        $pdo->exec(
            'ALTER TABLE `preorder_requests` ADD KEY `idx_preorder_requests_archived_created` '
            . '(`is_archived`, `created_at`)'
        );
    }

    $constraint = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.table_constraints '
        . 'WHERE constraint_schema = DATABASE() AND table_name = ? '
        . 'AND constraint_name = ? AND constraint_type = ?'
    );
    $constraint->execute(['preorder_requests', 'fk_preorder_requests_archived_admin', 'FOREIGN KEY']);
    if ((int)$constraint->fetchColumn() === 0) {
        $pdo->exec(
            'ALTER TABLE `preorder_requests` ADD CONSTRAINT `fk_preorder_requests_archived_admin` '
            . 'FOREIGN KEY (`archived_by`) REFERENCES `admin_users` (`id`) ON DELETE SET NULL'
        );
    }
}

function ola_cli_migration_mode(array $argv): string
{
    $mode = 'dry-run';
    $seen = false;
    foreach (array_slice($argv, 1) as $argument) {
        if (!in_array($argument, ['--status', '--dry-run', '--apply'], true) || $seen) {
            throw new InvalidArgumentException('Use exactly one of: --status, --dry-run, --apply.');
        }
        $mode = substr($argument, 2);
        $seen = true;
    }
    return $mode;
}

function ola_run_migration_cli(array $argv): int
{
    try {
        $mode = ola_cli_migration_mode($argv);
        if ($mode === 'apply' && getenv('APP_ENV') !== 'production') {
            throw new RuntimeException('Set APP_ENV=production before using --apply.');
        }
        $pdo = dev_db_connection();
        if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) !== 'mysql') {
            throw new RuntimeException('Production migrations require MySQL.');
        }
        $applied = ola_applied_migrations($pdo);
        $pending = ola_pending_migrations($pdo);
        foreach (OLA_MIGRATIONS as $version => $filename) {
            echo isset($applied[$version]) ? '[applied] ' . $filename . PHP_EOL : '[pending] ' . $filename . PHP_EOL;
        }
        if ($mode === 'apply') {
            $newlyApplied = ola_apply_production_migrations($pdo);
            echo $newlyApplied ? 'Applied: ' . implode(', ', $newlyApplied) . PHP_EOL : 'All migrations already applied.' . PHP_EOL;
        } elseif ($mode === 'dry-run') {
            echo 'Dry-run only; no schema changes were made.' . PHP_EOL;
        } elseif ($mode === 'status' && $pending === []) {
            echo 'All migrations already applied.' . PHP_EOL;
        }
        return 0;
    } catch (Throwable $exception) {
        fwrite(STDERR, 'Migration error: ' . $exception->getMessage() . PHP_EOL);
        return 1;
    }
}

// Refuse direct HTTP execution; the file may only be included by authenticated admin code.
if (realpath((string)($_SERVER['SCRIPT_FILENAME'] ?? '')) === __FILE__) {
    if (PHP_SAPI !== 'cli') {
        http_response_code(404);
        exit;
    }
    exit(ola_run_migration_cli($argv ?? []));
}

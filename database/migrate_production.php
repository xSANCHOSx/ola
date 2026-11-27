<?php

declare(strict_types=1);

/**
 * Безопасное применение production-миграций Olaplex.
 *
 * По умолчанию скрипт работает в режиме dry-run.
 * Для фактического применения необходимо явно указать --apply.
 *
 * Примеры:
 *   APP_ENV=production php database/migrate_production.php --status
 *   APP_ENV=production php database/migrate_production.php --dry-run
 *   APP_ENV=production php database/migrate_production.php --apply
 *
 * Переменные окружения:
 *   DB_HOST — хост MySQL, обязательно
 *   DB_PORT — порт MySQL, по умолчанию 3306
 *   DB_NAME — имя базы, обязательно
 *   DB_USER — пользователь базы, обязательно
 *   DB_PASS — пароль базы, обязательно
 */

const MIGRATIONS = [
    'migration_product_visibility.sql',
    'migration_preorder_requests.sql',
    'migration_preorder_archive.sql',
];

const MIGRATIONS_TABLE = 'schema_migrations';
const MYSQL_LOCK_NAME = 'olaplex_production_migrations';

function fail(string $message, int $code = 1): void
{
    fwrite(STDERR, "ERROR: {$message}\n");
    exit($code);
}

function info(string $message): void
{
    fwrite(STDOUT, $message . PHP_EOL);
}

function usage(): void
{
    info(<<<'USAGE'
Использование:
  php database/migrate_production.php [--status|--dry-run|--apply]

Режимы:
  --status   Показать применённые и ожидающие миграции, без изменений.
  --dry-run  Показать план применения, без изменений. Используется по умолчанию.
  --apply    Применить ожидающие миграции.
  --help     Показать эту справку.

Для --apply требуется APP_ENV=production.
USAGE);
    exit(0);
}

function parseMode(array $argv): string
{
    $mode = 'dry-run';

    foreach (array_slice($argv, 1) as $argument) {
        switch ($argument) {
            case '--status':
                if ($mode !== 'dry-run') {
                    fail('Нельзя указывать несколько режимов одновременно.');
                }
                $mode = 'status';
                break;
            case '--dry-run':
                if ($mode !== 'dry-run') {
                    fail('Нельзя указывать несколько режимов одновременно.');
                }
                $mode = 'dry-run';
                break;
            case '--apply':
                if ($mode !== 'dry-run') {
                    fail('Нельзя указывать несколько режимов одновременно.');
                }
                $mode = 'apply';
                break;
            case '--help':
            case '-h':
                usage();
                break;
            default:
                fail("Неизвестный аргумент: {$argument}. Используйте --help.");
        }
    }

    return $mode;
}

function requiredEnv(string $name): string
{
    $value = getenv($name);
    if ($value === false || trim($value) === '') {
        fail("Не задана обязательная переменная окружения {$name}.");
    }
    return $value;
}

function connect(): PDO
{
    $host = requiredEnv('DB_HOST');
    $name = requiredEnv('DB_NAME');
    $user = requiredEnv('DB_USER');
    $pass = requiredEnv('DB_PASS');
    $port = getenv('DB_PORT') ?: '3306';

    if (!ctype_digit($port) || (int)$port < 1 || (int)$port > 65535) {
        fail('DB_PORT должен быть числом от 1 до 65535.');
    }

    $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $host, $port, $name);

    try {
        return new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    } catch (PDOException $exception) {
        fail('Не удалось подключиться к MySQL: ' . $exception->getMessage());
    }
}

function migrationsDirectory(): string
{
    return __DIR__;
}

function migrationChecksum(string $filename): string
{
    $path = migrationsDirectory() . DIRECTORY_SEPARATOR . $filename;
    if (!is_readable($path)) {
        fail("Файл миграции недоступен для чтения: {$path}");
    }

    $checksum = hash_file('sha256', $path);
    if ($checksum === false) {
        fail("Не удалось вычислить контрольную сумму: {$path}");
    }

    return $checksum;
}

function ensureMigrationsTable(PDO $pdo): void
{
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS `' . MIGRATIONS_TABLE . '` ('
        . ' `migration` VARCHAR(190) NOT NULL PRIMARY KEY,'
        . ' `checksum` CHAR(64) NOT NULL,'
        . ' `applied_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP'
        . ' ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );
}

function migrationsTableExists(PDO $pdo): bool
{
    $statement = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.tables'
        . ' WHERE table_schema = DATABASE() AND table_name = ?'
    );
    $statement->execute([MIGRATIONS_TABLE]);
    return (int)$statement->fetchColumn() === 1;
}

/** @return array<string, array{checksum: string, applied_at: string}> */
function appliedMigrations(PDO $pdo): array
{
    $statement = $pdo->prepare(
        'SELECT migration, checksum, applied_at FROM `' . MIGRATIONS_TABLE . '`'
        . ' WHERE migration IN ('
        . implode(',', array_fill(0, count(MIGRATIONS), '?'))
        . ') ORDER BY migration'
    );
    $statement->execute(MIGRATIONS);

    $result = [];
    foreach ($statement->fetchAll() as $row) {
        $result[(string)$row['migration']] = [
            'checksum'  => (string)$row['checksum'],
            'applied_at' => (string)$row['applied_at'],
        ];
    }

    return $result;
}

function acquireLock(PDO $pdo): void
{
    $statement = $pdo->prepare('SELECT GET_LOCK(?, 10) AS lock_acquired');
    $statement->execute([MYSQL_LOCK_NAME]);
    $acquired = (int)$statement->fetchColumn();
    if ($acquired !== 1) {
        fail('Не удалось получить блокировку миграций. Возможно, другой процесс уже выполняет миграции.');
    }
}

function releaseLock(PDO $pdo): void
{
    $statement = $pdo->prepare('SELECT RELEASE_LOCK(?)');
    $statement->execute([MYSQL_LOCK_NAME]);
}

/** @return list<array{filename: string, checksum: string}> */
function migrationPlan(array $applied): array
{
    $plan = [];

    foreach (MIGRATIONS as $filename) {
        $checksum = migrationChecksum($filename);
        if (isset($applied[$filename])) {
            if (!hash_equals($applied[$filename]['checksum'], $checksum)) {
                fail(
                    "Контрольная сумма уже применённой миграции {$filename} изменилась. "
                    . 'Остановлено для защиты production-базы.'
                );
            }
            continue;
        }

        $plan[] = ['filename' => $filename, 'checksum' => $checksum];
    }

    return $plan;
}

function showPlan(array $applied, array $plan, string $mode): void
{
    info('Режим: ' . $mode);
    info('Порядок миграций:');

    foreach (MIGRATIONS as $filename) {
        if (isset($applied[$filename])) {
            info("  [applied] {$filename} ({$applied[$filename]['applied_at']})");
        } else {
            info("  [pending] {$filename}");
        }
    }

    if ($mode === 'dry-run') {
        info('Dry-run завершён: изменений в базе не выполнено.');
    } elseif ($mode === 'status' || $plan === []) {
        info($plan === [] ? 'Все миграции уже применены.' : 'Проверка статуса завершена.');
    }
}

function applyPlan(PDO $pdo, array $plan): void
{
    foreach ($plan as $migration) {
        $filename = $migration['filename'];
        $path = migrationsDirectory() . DIRECTORY_SEPARATOR . $filename;
        $sql = file_get_contents($path);
        if ($sql === false || trim($sql) === '') {
            fail("Миграция пуста или недоступна: {$filename}");
        }

        info("Применяю {$filename}...");
        try {
            // В текущем наборе каждый SQL-файл содержит одну DDL-операцию.
            $pdo->exec($sql);
            $statement = $pdo->prepare(
                'INSERT INTO `' . MIGRATIONS_TABLE . '` (migration, checksum) VALUES (?, ?)'
            );
            $statement->execute([$filename, $migration['checksum']]);
        } catch (Throwable $exception) {
            fail("Миграция {$filename} завершилась ошибкой: {$exception->getMessage()}");
        }
        info("Готово: {$filename}");
    }
}

$mode = parseMode($argv);
if ($mode === 'apply' && getenv('APP_ENV') !== 'production') {
    fail('Для --apply необходимо явно установить APP_ENV=production.');
}

$pdo = connect();
$pdo->query("SET SESSION sql_mode = 'STRICT_TRANS_TABLES,NO_ZERO_DATE,NO_ZERO_IN_DATE'");

if ($mode === 'apply') {
    acquireLock($pdo);
}

try {
    $hasMigrationsTable = migrationsTableExists($pdo);
    if ($mode === 'apply' && !$hasMigrationsTable) {
        ensureMigrationsTable($pdo);
        $hasMigrationsTable = true;
    }

    $applied = $hasMigrationsTable ? appliedMigrations($pdo) : [];
    $plan = migrationPlan($applied);
    showPlan($applied, $plan, $mode);

    if ($mode === 'apply' && $plan !== []) {
        applyPlan($pdo, $plan);
        info('Все ожидающие миграции успешно применены.');
    }
} finally {
    if ($mode === 'apply') {
        releaseLock($pdo);
    }
}

<?php

declare(strict_types=1);

/**
 * Integration test for the preorder database flow.
 *
 * Required environment variables:
 *   TEST_DB_HOST, TEST_DB_NAME, TEST_DB_USER
 * Optional: TEST_DB_PORT, TEST_DB_PASS
 *
 * The test uses one transaction and rolls it back in all cases.
 */

function test_fail(string $message): never
{
    throw new RuntimeException($message);
}

function test_assert(bool $condition, string $message): void
{
    if (!$condition) {
        test_fail($message);
    }
}

function test_env(string $name, ?string $default = null): string
{
    $value = getenv($name);
    if ($value === false || $value === '') {
        if ($default !== null) {
            return $default;
        }
        throw new RuntimeException("Missing required environment variable: {$name}");
    }
    return $value;
}

try {
    $host = test_env('TEST_DB_HOST');
    $name = test_env('TEST_DB_NAME');
    $user = test_env('TEST_DB_USER');
    $port = test_env('TEST_DB_PORT', '3306');
    $password = getenv('TEST_DB_PASS');

    $pdo = new PDO(
        "mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4",
        $user,
        $password === false ? '' : $password,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );

    $requiredTables = ['admin_users', 'products', 'preorder_requests', 'orders'];
    $tableStmt = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.tables
         WHERE table_schema = DATABASE() AND table_name = :table_name'
    );
    foreach ($requiredTables as $table) {
        $tableStmt->execute(['table_name' => $table]);
        test_assert((int)$tableStmt->fetchColumn() === 1, "Required table is missing: {$table}");
    }

    $columnStmt = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.columns
         WHERE table_schema = DATABASE()
           AND table_name = "preorder_requests"
           AND column_name = :column_name'
    );
    foreach (['is_archived', 'archived_at', 'archived_by'] as $column) {
        $columnStmt->execute(['column_name' => $column]);
        test_assert((int)$columnStmt->fetchColumn() === 1, "Archive column is missing: {$column}");
    }

    $suffix = bin2hex(random_bytes(6));
    $externalId = 'it-' . $suffix;
    $username = 'it_' . $suffix;
    $link = '/integration-test-' . $suffix;

    $pdo->beginTransaction();
    try {
        $adminInsert = $pdo->prepare(
            'INSERT INTO admin_users (username, password_hash, role, is_active)
             VALUES (:username, :password_hash, "admin", 1)'
        );
        $adminInsert->execute([
            'username' => $username,
            'password_hash' => password_hash('integration-test', PASSWORD_DEFAULT),
        ]);
        $adminId = (int)$pdo->lastInsertId();

        $productInsert = $pdo->prepare(
            'INSERT INTO products
                (external_id, name, price, link, status, is_visible)
             VALUES
                (:external_id, :name, 10.00, :link, "preorder", 1)'
        );
        $productInsert->execute([
            'external_id' => $externalId,
            'name' => 'Integration preorder product',
            'link' => $link,
        ]);
        $productId = (int)$pdo->lastInsertId();

        $eligible = $pdo->prepare(
            'SELECT id FROM products
             WHERE external_id = :external_id AND is_visible = 1 AND status = "preorder"'
        );
        $eligible->execute(['external_id' => $externalId]);
        test_assert((int)$eligible->fetchColumn() === $productId, 'Visible preorder product was not found.');

        $requestInsert = $pdo->prepare(
            'INSERT INTO preorder_requests
                (product_id, product_external_id, product_name_snapshot, customer_name, customer_phone)
             VALUES
                (:product_id, :product_external_id, :product_name_snapshot, :customer_name, :customer_phone)'
        );
        $requestInsert->execute([
            'product_id' => $productId,
            'product_external_id' => $externalId,
            'product_name_snapshot' => 'Integration preorder product',
            'customer_name' => 'Integration Test',
            'customer_phone' => '79990000000',
        ]);
        $requestId = (int)$pdo->lastInsertId();

        $activeCount = $pdo->prepare(
            'SELECT COUNT(*) FROM preorder_requests WHERE id = :id AND is_archived = 0'
        );
        $activeCount->execute(['id' => $requestId]);
        test_assert((int)$activeCount->fetchColumn() === 1, 'New preorder request is not active.');

        $duplicate = $pdo->prepare(
            'SELECT id
             FROM preorder_requests
             WHERE product_external_id = :product_external_id
               AND customer_phone = :customer_phone
               AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
             LIMIT 1'
        );
        $duplicate->execute([
            'product_external_id' => $externalId,
            'customer_phone' => '79990000000',
        ]);
        test_assert((int)$duplicate->fetchColumn() === $requestId, 'Duplicate preorder request was not detected.');

        $update = $pdo->prepare(
            'UPDATE preorder_requests
             SET status = :status,
                 processed_at = NOW(),
                 processed_by = :processed_by
             WHERE id = :id'
        );
        $update->execute(['status' => 'processed', 'processed_by' => $adminId, 'id' => $requestId]);

        $statusCheck = $pdo->prepare(
            'SELECT status, processed_by FROM preorder_requests WHERE id = :id'
        );
        $statusCheck->execute(['id' => $requestId]);
        $status = $statusCheck->fetch();
        test_assert($status !== false && $status['status'] === 'processed', 'Preorder status was not updated.');
        test_assert((int)$status['processed_by'] === $adminId, 'Processed-by admin was not stored.');

        $archive = $pdo->prepare(
            'UPDATE preorder_requests
             SET is_archived = 1, archived_at = NOW(), archived_by = :admin_id
             WHERE id = :id'
        );
        $archive->execute(['admin_id' => $adminId, 'id' => $requestId]);

        $archivedCount = $pdo->prepare(
            'SELECT COUNT(*) FROM preorder_requests WHERE id = :id AND is_archived = 1'
        );
        $archivedCount->execute(['id' => $requestId]);
        test_assert((int)$archivedCount->fetchColumn() === 1, 'Preorder request was not archived.');

        $restore = $pdo->prepare(
            'UPDATE preorder_requests
             SET is_archived = 0, archived_at = NULL, archived_by = NULL
             WHERE id = :id'
        );
        $restore->execute(['id' => $requestId]);
        $activeCount->execute(['id' => $requestId]);
        test_assert((int)$activeCount->fetchColumn() === 1, 'Preorder request was not restored.');

        $ordersBefore = (int)$pdo->query('SELECT COUNT(*) FROM orders')->fetchColumn();
        $delete = $pdo->prepare('DELETE FROM preorder_requests WHERE id = :id');
        $delete->execute(['id' => $requestId]);
        $ordersAfter = (int)$pdo->query('SELECT COUNT(*) FROM orders')->fetchColumn();
        test_assert($ordersBefore === $ordersAfter, 'Preorder flow changed the ordinary orders table.');

        $pdo->rollBack();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }

    fwrite(STDOUT, "PASS: preorder MySQL integration flow\n");
    exit(0);
} catch (Throwable $e) {
    if (strpos($e->getMessage(), 'Missing required environment variable:') === 0) {
        fwrite(STDOUT, "SKIP: {$e->getMessage()}\n");
        exit(77);
    }
    fwrite(STDERR, "FAIL: {$e->getMessage()}\n");
    exit(1);
}

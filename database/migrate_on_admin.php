<?php

declare(strict_types=1);

// This one-time trigger is included only after admin/_bootstrap.php has refreshed
// the active admin account and role from the database. Direct access is rejected.
if (realpath((string)($_SERVER['SCRIPT_FILENAME'] ?? '')) === __FILE__) {
    if (PHP_SAPI !== 'cli') {
        http_response_code(404);
    }
    exit(1);
}

require_once __DIR__ . '/migrate_production.php';

/**
 * Run the guarded migrations and remove this trigger only after success.
 * A failed or partial migration keeps the trigger available for a safe retry.
 *
 * @return list<string> migration versions newly applied by this call
 */
function ola_run_admin_migrations(PDO $pdo): array
{
    $applied = ola_apply_production_migrations($pdo);

    // The durable migration ledger is authoritative; this file is only a one-time entry point.
    if (is_file(__FILE__) && !@unlink(__FILE__)) {
        dev_log_runtime('Admin migration succeeded but the one-time trigger file could not be removed.');
    }

    return $applied;
}

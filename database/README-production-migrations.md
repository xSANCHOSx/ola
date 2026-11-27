# Production migrations

`migrate_production.php` is the guarded runner for preorder-request and archive schema migrations. It uses the application's `dev_db_connection()` and has CLI modes `--status`, `--dry-run` (default), and `--apply`. Direct HTTP execution is rejected. In normal deployment, do not invoke the CLI apply mode: the one-time web trigger runs after the admin bootstrap has refreshed the active user's role from the database.

## Admin-triggered one-time run

On the first admin request after deployment, `admin/_bootstrap.php` checks the current user's active status and role directly in `admin_users`. Only an active user whose fresh database role is exactly `admin` can include `database/migrate_on_admin.php`. The trigger is not an endpoint: direct HTTP access returns 404. It invokes the guarded runner, which requires MySQL, takes an advisory lock, checks migration source checksums, and records successful migrations in the `production_migrations` ledger.

The trigger file is removed only after the runner returns successfully. On an error it remains in place, a generic 503 is shown, and the detailed error is written to the application runtime log; the runner's idempotent DDL and durable ledger allow a later admin request to retry safely. If removing the trigger itself fails after migrations succeed, the ledger prevents reapplying completed migrations and the runtime log records the cleanup failure. Restrict write access to the deployed application files as usual.

## Scope and separation

The runner applies, in order, the preorder-request table and archive-schema migrations. Product `status`/`is_visible` is handled separately by the existing one-time `migrate_product_status.php` deployment step, which uses the legacy `schema_migrations(version)` table. The preorder runner uses its own `production_migrations` ledger; the formats do not collide. The deploy workflow's product-status migration and its temporary-file cleanup remain unchanged.

Archive columns, index, and foreign key are checked and applied individually, so partial DDL can be retried. Do not run the raw archive SQL file manually; use the runner.

## CLI inspection

For controlled maintenance only, run from the repository root with the application's database configuration. Back up the database and confirm the target before any manual apply.

```bash
APP_ENV=production php database/migrate_production.php --status
APP_ENV=production php database/migrate_production.php --dry-run
APP_ENV=production php database/migrate_production.php --apply
```

CLI `--apply` requires `APP_ENV=production`. Do not run it as part of local verification. No production database connection or manual production migration was used for this change.

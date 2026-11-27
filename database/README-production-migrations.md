# Production migrations

`migrate_production.php` is the guarded CLI runner for the preorder-request schema. It uses the application's `dev_db_connection()` configuration and supports `--status`, `--dry-run` (default), and `--apply`. Direct HTTP execution is rejected. The admin-page trigger is added separately in PR #35.

## CLI usage

Run from the repository root. Use the same production `DB_HOST`, `DB_NAME`, `DB_USER`, and `DB_PASS` configuration as the application. Before an intentional manual apply, take a database backup and confirm the target database.

```bash
APP_ENV=production php database/migrate_production.php --status
APP_ENV=production php database/migrate_production.php --dry-run
APP_ENV=production php database/migrate_production.php --apply
```

The CLI `--apply` mode is restricted to `APP_ENV=production`; do not run it during local verification. No production migration was run as part of the code checks for this change.

## Scope and safety

The runner applies, in order, the preorder request table and archive-schema migrations. Product `status`/`is_visible` is handled by the existing one-time `migrate_product_status.php` step in `.github/workflows/deploy.yml`; it uses the legacy `schema_migrations(version)` table. The preorder runner deliberately uses the separate `production_migrations` ledger, so the two formats do not collide.

The runner requires MySQL, checks and stores SHA-256 checksums for applied migration sources, obtains a MySQL advisory lock before changing schema, and records each migration only after its idempotent DDL succeeds. Archive columns, index, and foreign key are checked and applied individually, so a partial failure can be retried on a later run. Do not run the raw archive SQL file manually; use the runner.

The existing deploy workflow applies and removes the product status migration from the server's temporary deployment directory before synchronizing the site. It is separate from local verification and from the preorder migration runner.

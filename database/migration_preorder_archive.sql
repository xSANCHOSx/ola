-- ============================================================
-- Миграция: архивирование заявок «Под заказ»
-- Активные заявки не удаляются при архивировании и доступны для восстановления.
-- Запускать после database/migration_preorder_requests.sql:
-- mysql -u user -p db_name < migration_preorder_archive.sql
-- ============================================================

ALTER TABLE preorder_requests
  ADD COLUMN is_archived TINYINT(1) NOT NULL DEFAULT 0 AFTER status,
  ADD COLUMN archived_at DATETIME DEFAULT NULL AFTER processed_by,
  ADD COLUMN archived_by INT UNSIGNED DEFAULT NULL AFTER archived_at,
  ADD KEY idx_preorder_requests_archived_created (is_archived, created_at),
  ADD CONSTRAINT fk_preorder_requests_archived_admin
    FOREIGN KEY (archived_by) REFERENCES admin_users(id) ON DELETE SET NULL;

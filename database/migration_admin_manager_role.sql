-- ============================================================
-- Миграция: роли администраторов и менеджеров
-- ============================================================
-- Существующие пользователи получают полные права администратора.
ALTER TABLE admin_users
  ADD COLUMN role ENUM('admin', 'manager') NOT NULL DEFAULT 'admin' AFTER password_hash,
  ADD COLUMN is_active TINYINT(1) NOT NULL DEFAULT 1 AFTER role,
  ADD KEY idx_admin_users_role_active (role, is_active);

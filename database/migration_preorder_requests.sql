-- ============================================================
-- Миграция: заявки «Под заказ»
-- Заявки хранятся отдельно от orders и не участвуют в CRM-потоке.
-- Запускать: mysql -u user -p db_name < migration_preorder_requests.sql
-- ============================================================

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
  CONSTRAINT fk_preorder_requests_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE RESTRICT,
  CONSTRAINT fk_preorder_requests_admin FOREIGN KEY (processed_by) REFERENCES admin_users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

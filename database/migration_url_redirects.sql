-- Управляемые 301-редиректы старых URL на новые.
-- Применить один раз после резервного копирования базы данных.
CREATE TABLE IF NOT EXISTS url_redirects (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  source_path VARCHAR(500) NOT NULL,
  target_path VARCHAR(500) NOT NULL,
  product_id INT UNSIGNED DEFAULT NULL,
  reason VARCHAR(80) NOT NULL DEFAULT 'manual',
  status_code SMALLINT UNSIGNED NOT NULL DEFAULT 301,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_url_redirects_source_path (source_path),
  KEY idx_url_redirects_active (is_active),
  KEY idx_url_redirects_product (product_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

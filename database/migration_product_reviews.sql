-- ============================================================
-- Миграция: отзывы товаров с модерацией администратора
-- ============================================================
CREATE TABLE IF NOT EXISTS product_reviews (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  product_id BIGINT UNSIGNED NOT NULL,
  author_name VARCHAR(120) NOT NULL,
  author_email VARCHAR(255) DEFAULT NULL,
  rating TINYINT UNSIGNED NOT NULL,
  review_text TEXT NOT NULL,
  status ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'pending',
  admin_note VARCHAR(500) DEFAULT NULL,
  ip_hash CHAR(64) DEFAULT NULL,
  moderated_at DATETIME DEFAULT NULL,
  moderated_by INT UNSIGNED DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_product_reviews_product_status (product_id, status),
  KEY idx_product_reviews_status_created (status, created_at),
  CONSTRAINT fk_product_reviews_product
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
  CONSTRAINT fk_product_reviews_admin
    FOREIGN KEY (moderated_by) REFERENCES admin_users(id) ON DELETE SET NULL,
  CONSTRAINT chk_product_reviews_rating CHECK (rating BETWEEN 1 AND 5)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
  COMMENT='Отзывы товаров с ручной модерацией';

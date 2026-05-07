CREATE TABLE IF NOT EXISTS admin_users (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(120) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  last_login TIMESTAMP NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS customers (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  full_name VARCHAR(255) NOT NULL,
  email VARCHAR(255) DEFAULT NULL,
  phone VARCHAR(64) DEFAULT NULL,
  contact_method VARCHAR(32) DEFAULT NULL,
  contact_username VARCHAR(255) DEFAULT NULL,
  phone_normalized VARCHAR(64) DEFAULT NULL,
  email_normalized VARCHAR(255) DEFAULT NULL,
  first_order_number BIGINT UNSIGNED DEFAULT NULL,
  last_order_number BIGINT UNSIGNED DEFAULT NULL,
  orders_count INT UNSIGNED NOT NULL DEFAULT 0,
  total_spent DECIMAL(12,2) NOT NULL DEFAULT 0,
  last_order_at TIMESTAMP NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_customers_email_normalized (email_normalized),
  INDEX idx_customers_last_order_at (last_order_at),
  INDEX idx_customers_phone_normalized (phone_normalized)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS order_sequence (
  id TINYINT UNSIGNED PRIMARY KEY DEFAULT 1,
  current_value BIGINT UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS products (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  external_id VARCHAR(32) NOT NULL,
  cat_number VARCHAR(120) DEFAULT NULL,
  name VARCHAR(255) NOT NULL,
  old_price DECIMAL(12,2) DEFAULT NULL,
  price DECIMAL(12,2) NOT NULL,
  image VARCHAR(255) DEFAULT NULL,
  link VARCHAR(255) NOT NULL,
  short_desc TEXT,
  `desc` TEXT,
  full_desc MEDIUMTEXT,
  in_stock TINYINT(1) NOT NULL DEFAULT 1,
  status VARCHAR(64) DEFAULT NULL,
  seo_title VARCHAR(255) DEFAULT NULL,
  seo_description VARCHAR(255) DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_products_link (link),
  UNIQUE KEY uq_products_external_id (external_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS orders (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  order_number BIGINT UNSIGNED NOT NULL,
  customer_id BIGINT UNSIGNED DEFAULT NULL,
  customer_name_snapshot VARCHAR(255) DEFAULT NULL,
  customer_email_snapshot VARCHAR(255) DEFAULT NULL,
  customer_phone_snapshot VARCHAR(64) DEFAULT NULL,
  contact_method_snapshot VARCHAR(32) DEFAULT NULL,
  contact_username_snapshot VARCHAR(255) DEFAULT NULL,
  delivery_address_snapshot TEXT,
  coupon VARCHAR(64) DEFAULT NULL,
  total DECIMAL(12,2) NOT NULL DEFAULT 0,
  outbound_email_sent TINYINT(1) NOT NULL DEFAULT 0,
  outbound_crm_sent TINYINT(1) NOT NULL DEFAULT 0,
  outbound_amo_sent TINYINT(1) NOT NULL DEFAULT 0,
  idempotency_key VARCHAR(64) DEFAULT NULL,
  raw_payload JSON NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_orders_number (order_number),
  UNIQUE KEY uq_orders_idempotency (idempotency_key),
  KEY idx_orders_customer_id (customer_id),
  CONSTRAINT fk_orders_customer FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS order_items (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  order_id BIGINT UNSIGNED NOT NULL,
  product_external_id VARCHAR(32) DEFAULT NULL,
  catalog_number VARCHAR(120) DEFAULT NULL,
  name VARCHAR(255) NOT NULL,
  price DECIMAL(12,2) NOT NULL,
  quantity INT UNSIGNED NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  KEY idx_order_items_order_id (order_id),
  CONSTRAINT fk_order_items_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ═════════════════════════════════════════════════════════════════════════════
-- Міграція: Додавання модулю управління купонами
-- ═════════════════════════════════════════════════════════════════════════════

-- ─────────────────────────────────────────────────────────────────────────────
-- Таблиця COUPONS — Основна інформація про купони
-- ─────────────────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS coupons (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  
  -- Ідентифікатори
  code VARCHAR(50) NOT NULL UNIQUE COMMENT 'Код купона: OLA5600, SUMMER20, TEST123',
  name VARCHAR(255) NOT NULL COMMENT 'Дружелюбна назва для адмін-панелі',
  
  -- Тип і величина знижки
  discount_type ENUM('fixed', 'percent') NOT NULL DEFAULT 'fixed' 
    COMMENT 'fixed = фіксована сума (руб), percent = відсоток від суми',
  discount_value DECIMAL(10, 2) NOT NULL 
    COMMENT 'Значення знижки: 100.00 для fixed або 20.00 для percent',
  
  -- Умова активації
  min_order_sum DECIMAL(12, 2) NOT NULL DEFAULT 0 
    COMMENT 'Мінімальна сума замовлення для активності купона',
  
  -- Період дії
  valid_from DATETIME DEFAULT NULL COMMENT 'Початок дії купона',
  valid_to DATETIME DEFAULT NULL COMMENT 'Закінчення дії купона',
  
  -- Обмеження використання
  max_usage_count INT UNSIGNED DEFAULT NULL 
    COMMENT 'Максимум разів використання. NULL = безлімітно',
  used_count INT UNSIGNED NOT NULL DEFAULT 0 
    COMMENT 'Поточна кількість використань',
  
  -- Статус
  is_active TINYINT(1) NOT NULL DEFAULT 1 
    COMMENT '1 = активний, 0 = деактивований',
  
  -- Аудит
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  
  -- Індекси для швидкого пошуку
  KEY idx_coupons_code (code),
  KEY idx_coupons_is_active (is_active),
  KEY idx_coupons_valid_to (valid_to),
  KEY idx_coupons_valid_from (valid_from)
  
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 
  COMMENT='Таблиця купонів для системи знижок';


-- ─────────────────────────────────────────────────────────────────────────────
-- Таблиця COUPON_USAGE — Логування використання купонів
-- ─────────────────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS coupon_usage (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  
  -- Посилання
  coupon_id INT UNSIGNED NOT NULL,
  order_id BIGINT UNSIGNED NOT NULL,
  customer_id BIGINT UNSIGNED DEFAULT NULL,
  
  -- Результат
  discount_amount DECIMAL(12, 2) NOT NULL 
    COMMENT 'Фактична величина знижки, застосована для замовлення',
  
  -- Аудит
  used_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  
  -- Внутрішні ключі
  KEY idx_coupon_usage_coupon_id (coupon_id),
  KEY idx_coupon_usage_order_id (order_id),
  KEY idx_coupon_usage_customer_id (customer_id),
  KEY idx_coupon_usage_used_at (used_at),
  
  -- Зовнішні ключи
  CONSTRAINT fk_coupon_usage_coupon FOREIGN KEY (coupon_id) 
    REFERENCES coupons(id) ON DELETE RESTRICT,
  CONSTRAINT fk_coupon_usage_order FOREIGN KEY (order_id) 
    REFERENCES orders(id) ON DELETE CASCADE,
  CONSTRAINT fk_coupon_usage_customer FOREIGN KEY (customer_id) 
    REFERENCES customers(id) ON DELETE SET NULL
    
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 
  COMMENT='Логування факту використання купонів для замовлень';


-- ─────────────────────────────────────────────────────────────────────────────
-- Дефолтні купони для тестування
-- ─────────────────────────────────────────────────────────────────────────────
INSERT INTO coupons 
  (code, name, discount_type, discount_value, min_order_sum, valid_from, valid_to, max_usage_count, is_active) 
VALUES 
  ('OLA5600', 'Базова знижка на Олаплекс', 'fixed', 5600.00, 0, NOW(), DATE_ADD(NOW(), INTERVAL 30 DAY), NULL, 1),
  ('SUMMER20', 'Літня акція 20%', 'percent', 20.00, 5000.00, NOW(), DATE_ADD(NOW(), INTERVAL 90 DAY), NULL, 1);

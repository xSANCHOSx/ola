-- ═════════════════════════════════════════════════════════════════════════════
-- Миграция: Добавление модуля управления купонами
-- ═════════════════════════════════════════════════════════════════════════════

-- ─────────────────────────────────────────────────────────────────────────────
-- Таблица COUPONS — Основная информация о купонах
-- ─────────────────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS coupons (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  
  -- Идентификаторы
  code VARCHAR(50) NOT NULL UNIQUE COMMENT 'Код купона: OLA5600, SUMMER20, TEST123',
  name VARCHAR(255) NOT NULL COMMENT 'Дружелюбное название для админ-панели',
  
  -- Тип и величина скидки
  discount_type ENUM('fixed', 'percent') NOT NULL DEFAULT 'fixed' 
    COMMENT 'fixed = фиксированная сумма (руб), percent = процент от суммы',
  discount_value DECIMAL(10, 2) NOT NULL 
    COMMENT 'Значение скидки: 100.00 для fixed или 20.00 для percent',
  
  -- Условие активации
  min_order_sum DECIMAL(12, 2) NOT NULL DEFAULT 0 
    COMMENT 'Минимальная сумма заказа для активности купона',
  
  -- Период действия
  valid_from DATETIME DEFAULT NULL COMMENT 'Начало действия купона',
  valid_to DATETIME DEFAULT NULL COMMENT 'Окончание действия купона',
  
  -- Обмеження використання
  max_usage_count INT UNSIGNED DEFAULT NULL 
    COMMENT 'Максимум раз использования. NULL = безлимитно',
  used_count INT UNSIGNED NOT NULL DEFAULT 0 
    COMMENT 'Текущее количество использований',
  
  -- Статус
  is_active TINYINT(1) NOT NULL DEFAULT 1 
    COMMENT '1 = активный, 0 = деактивированный',
  
  -- Аудит
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  
  -- Ндексы для быстрого поиска
  KEY idx_coupons_code (code),
  KEY idx_coupons_is_active (is_active),
  KEY idx_coupons_valid_to (valid_to),
  KEY idx_coupons_valid_from (valid_from)
  
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 
  COMMENT='Таблица купонов для системы скидок';


-- ─────────────────────────────────────────────────────────────────────────────
-- Таблица COUPON_USAGE — Логирование использования купонов
-- ─────────────────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS coupon_usage (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  
  -- Ссылки
  coupon_id INT UNSIGNED NOT NULL,
  order_id BIGINT UNSIGNED NOT NULL,
  customer_id BIGINT UNSIGNED DEFAULT NULL,
  
  -- Результат
  discount_amount DECIMAL(12, 2) NOT NULL 
    COMMENT 'Фактические размер скидки, по скидки для заказа',
  
  -- Аудит
  used_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  
  -- Внутрішні ключі
  KEY idx_coupon_usage_coupon_id (coupon_id),
  KEY idx_coupon_usage_order_id (order_id),
  KEY idx_coupon_usage_customer_id (customer_id),
  KEY idx_coupon_usage_used_at (used_at),
  
  -- Внешние ключи
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

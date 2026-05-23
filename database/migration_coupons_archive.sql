-- ═════════════════════════════════════════════════════════════════════════════
-- Міграція: Додавання архівації купонів
-- Дата: 2026-05-23
-- ═════════════════════════════════════════════════════════════════════════════

-- ─────────────────────────────────────────────────────────────────────────────
-- Таблиця COUPONS_ARCHIVED — Архів видалених купонів
-- ─────────────────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS coupons_archived (
  id INT UNSIGNED PRIMARY KEY COMMENT 'Оригінальний ID з таблиці coupons',

  -- Ідентифікатори (копія з coupons)
  code VARCHAR(50) NOT NULL COMMENT 'Код купона',
  name VARCHAR(255) NOT NULL COMMENT 'Назва купона',

  -- Тип та величина знижки
  discount_type ENUM('fixed', 'percent') NOT NULL DEFAULT 'fixed',
  discount_value DECIMAL(10, 2) NOT NULL,

  -- Умови активації
  min_order_sum DECIMAL(12, 2) NOT NULL DEFAULT 0,

  -- Період дії
  valid_from DATETIME DEFAULT NULL,
  valid_to DATETIME DEFAULT NULL,

  -- Обмеження використання
  max_usage_count INT UNSIGNED DEFAULT NULL,
  used_count INT UNSIGNED NOT NULL DEFAULT 0,

  -- Статус
  is_active TINYINT(1) NOT NULL DEFAULT 1,

  -- Оригінальні дати
  created_at TIMESTAMP NULL COMMENT 'Дата створення купона',
  updated_at TIMESTAMP NULL COMMENT 'Остання зміна купона',

  -- Дані архівації
  archived_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'Дата архівації',
  archived_by INT UNSIGNED DEFAULT NULL COMMENT 'ID адміністратора, який архівував',

  -- Індекси
  KEY idx_archived_code (code),
  KEY idx_archived_at (archived_at),
  KEY idx_archived_by (archived_by)

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
  COMMENT='Архів видалених купонів для збереження історії';


-- ─────────────────────────────────────────────────────────────────────────────
-- Зміна constraint для безпечного видалення після архівації
-- ─────────────────────────────────────────────────────────────────────────────

-- Видаляємо старий constraint з RESTRICT
ALTER TABLE coupon_usage
  DROP FOREIGN KEY fk_coupon_usage_coupon;

-- Додаємо новий constraint з CASCADE
-- Тепер після архівації купона можна безпечно видалити його з основної таблиці
ALTER TABLE coupon_usage
  ADD CONSTRAINT fk_coupon_usage_coupon
    FOREIGN KEY (coupon_id)
    REFERENCES coupons(id)
    ON DELETE CASCADE;

-- Примітка: CASCADE безпечний, бо перед видаленням з coupons
-- ми копіюємо запис в coupons_archived, зберігаючи повну історію

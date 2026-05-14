-- ═════════════════════════════════════════════════════════════════════════════
-- Міграція: Підключення купонів до замовлень
-- Додає coupon_id (FK на coupons) і coupon_discount_amount до таблиці orders
-- ═════════════════════════════════════════════════════════════════════════════

-- 1. Додати колонку coupon_id (nullable FK на coupons)
ALTER TABLE orders
    ADD COLUMN coupon_id INT UNSIGNED DEFAULT NULL
        COMMENT 'FK на таблицю coupons. NULL якщо купон не застосовувався.'
        AFTER coupon;

-- 2. Додати колонку coupon_discount_amount — фактична знижка в рублях
ALTER TABLE orders
    ADD COLUMN coupon_discount_amount DECIMAL(12, 2) DEFAULT NULL
        COMMENT 'Сума знижки по купону. NULL якщо купон не застосовувався.'
        AFTER coupon_id;

-- 3. FK на таблицю coupons
ALTER TABLE orders
    ADD CONSTRAINT fk_orders_coupon
        FOREIGN KEY (coupon_id) REFERENCES coupons(id)
        ON DELETE SET NULL;

-- 4. Індекс для пошуку замовлень по купону
ALTER TABLE orders
    ADD KEY idx_orders_coupon_id (coupon_id);

-- ============================================================
-- Миграция: отделить видимость товара от наличия
--
-- is_visible = 1 — товар доступен в публичном каталоге и по своей ссылке.
-- is_visible = 0 — товар скрыт с сайта независимо от status и in_stock.
-- Запускать: mysql -u user -p db_name < migration_product_visibility.sql
-- ============================================================

ALTER TABLE products
    ADD COLUMN is_visible TINYINT(1) NOT NULL DEFAULT 1
        COMMENT 'Показывать товар в публичном каталоге'
        AFTER in_stock,
    ADD KEY idx_products_is_visible (is_visible);

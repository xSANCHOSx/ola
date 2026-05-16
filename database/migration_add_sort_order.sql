-- ============================================================
-- Міграція: додати sort_order у таблицю products
-- Дата: 2026-05-16
-- BUG-01: admin/products.php використовує sort_order, але
--         колонки не існувало у schema.sql → SQL fatal error
-- Запускати: mysql -u user -p db_name < migration_add_sort_order.sql
-- ============================================================

ALTER TABLE products
    ADD COLUMN sort_order INT UNSIGNED NOT NULL DEFAULT 0
        COMMENT 'Порядок сортування товарів у адмінці'
        AFTER volume,
    ADD KEY idx_products_sort_order (sort_order);

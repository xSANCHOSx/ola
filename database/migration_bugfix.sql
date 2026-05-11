-- ============================================================
-- Міграція: виправлення багів у схемі БД
-- Дата: 2026-05-11
-- Запускати на продакшн базі ПІСЛЯ backup'у!
-- ============================================================

-- ─── КРОК 1: Очистити дублі клієнтів (якщо є) ───────────────
-- Перед додаванням UNIQUE ключів потрібно прибрати дублі.
-- Цей запит покаже скільки дублів є:
-- SELECT phone_normalized, COUNT(*) c FROM customers GROUP BY phone_normalized HAVING c > 1;
-- SELECT email_normalized, COUNT(*) c FROM customers GROUP BY email_normalized HAVING c > 1;
--
-- Якщо дублі є — залишити тільки перший рядок для кожного phone/email:
-- DELETE c1 FROM customers c1
--   INNER JOIN customers c2
--   WHERE c1.id > c2.id AND c1.phone_normalized = c2.phone_normalized AND c1.phone_normalized IS NOT NULL;
--
-- DELETE c1 FROM customers c1
--   INNER JOIN customers c2
--   WHERE c1.id > c2.id AND c1.email_normalized = c2.email_normalized AND c1.email_normalized IS NOT NULL;

-- ─── КРОК 2: FIX BUG-2 — UNIQUE ключі на customers ──────────
-- Змінюємо звичайні INDEX на UNIQUE KEY (INSERT IGNORE тепер правильно спрацює)
  ALTER TABLE customers
    DROP INDEX idx_customers_phone_normalized,
    DROP INDEX idx_customers_email_normalized,
    ADD UNIQUE KEY uq_customers_phone_normalized (phone_normalized),
    ADD UNIQUE KEY uq_customers_email_normalized (email_normalized);

-- ─── КРОК 3: FIX BUG-1 — поле price_verified в orders ───────
-- Нове поле: 0 = ціна від клієнта (products таблиця порожня),
-- 1 = ціна підтверджена з БД
ALTER TABLE orders
  ADD COLUMN price_verified TINYINT(1) NOT NULL DEFAULT 0
  AFTER total;

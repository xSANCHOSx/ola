<?php

declare(strict_types=1);

// Обробка UTM параметрів для аналітики
if (isset($_GET['utm_source'])) {
    $cookieTime = time() + 60 * 60 * 24 * 7;
    setcookie('utm_source', (string)$_GET['utm_source'], $cookieTime, '/');
    setcookie('utm_medium', (string)($_GET['utm_medium'] ?? ''), $cookieTime, '/');
    setcookie('utm_campaign', (string)($_GET['utm_campaign'] ?? ''), $cookieTime, '/');
    setcookie('utm_content', (string)($_GET['utm_content'] ?? ''), $cookieTime, '/');
}

require_once __DIR__ . '/../config/db.php';

/**
 * Отримати список активних продуктів з кешуванням
 * Результат кешується на весь час виконання скрипту (static)
 */
function get_products(): array
{
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }

    $cache = [];
    $pdo = dev_db_connection();
    if ($pdo instanceof PDO) {
        try {
            $stmt = $pdo->query('SELECT external_id, cat_number, name, old_price, price, image, link, short_desc, `desc`, full_desc, in_stock, status, seo_title, seo_description FROM products WHERE status = "active" ORDER BY id ASC');
            foreach ($stmt->fetchAll() as $row) {
                $cache[] = [
                    'id' => (string)$row['external_id'],
                    'cat_number' => (string)($row['cat_number'] ?? ''),
                    'name' => (string)$row['name'],
                    'old_price' => (float)($row['old_price'] ?? 0),
                    'price' => (float)$row['price'],
                    'image' => (string)($row['image'] ?? ''),
                    'link' => (string)$row['link'],
                    'short_desc' => (string)($row['short_desc'] ?? ''),
                    'desc' => (string)($row['desc'] ?? ''),
                    'full_desc' => (string)($row['full_desc'] ?? ''),
                    'in_stock' => (bool)$row['in_stock'],
                    'status' => $row['status'] !== null ? (string)$row['status'] : null,
                    'seo_title' => $row['seo_title'] !== null ? (string)$row['seo_title'] : '',
                    'seo_description' => $row['seo_description'] !== null ? (string)$row['seo_description'] : '',
                ];
            }
        } catch (Throwable $e) {
            dev_log_runtime('Products load from DB failed: ' . $e->getMessage());
            // Якщо БД недоступна - порожній масив (сайт працюватиме без продуктів)
            $cache = [];
        }
    }

    return $cache;
}

// Для зворотної сумісності з існуючим кодом, який використовує $products глобально
$products = get_products();

/**
 * Функція для отримання таймера акції
 */
function getDiscountTimer($uniqueId)
{
    $targetDate = strtotime('2026-06-01 00:00:00');
    $currentTime = time();
    $timeLeft = $targetDate - $currentTime;

    if ($timeLeft <= 0) {
        $targetDate = strtotime('+15 days', time());
        $timeLeft = $targetDate - $currentTime;
    }

    $days = floor($timeLeft / (60 * 60 * 24));
    $dayWord = ($days == 1) ? 'День' : (($days >= 2 && $days <= 4) ? 'Дня' : 'Дней');

    return "
			<div class='expire_date' id='timer-$uniqueId' data-end='$targetDate'>
					До конца акции:
					<div class='flip-clock'>
							<div class='flip-unit'><span class='days'>$days</span><div class='flip-label'>$dayWord</div></div>
					</div>
			</div>
	";
}

<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';

function review_text_length(string $value): int
{
    if (function_exists('mb_strlen')) return mb_strlen($value);
    $characters = preg_match_all('/./us', $value, $matches);
    return $characters === false ? strlen($value) : $characters;
}

function review_count_label(int $count): string
{
    $mod100 = $count % 100;
    $mod10 = $count % 10;
    if ($mod100 >= 11 && $mod100 <= 14) return 'отзывов';
    if ($mod10 === 1) return 'отзыв';
    if ($mod10 >= 2 && $mod10 <= 4) return 'отзыва';
    return 'отзывов';
}

function product_review_stats(PDO $pdo, int $productId): array
{
    if ($productId <= 0) {
        return ['count' => 0, 'average' => 0.0];
    }
    try {
        $stmt = $pdo->prepare(
            "SELECT COUNT(*) AS review_count, COALESCE(AVG(rating), 0) AS average_rating
             FROM product_reviews WHERE product_id = :product_id AND status = 'approved'"
        );
        $stmt->execute(['product_id' => $productId]);
        $row = $stmt->fetch() ?: [];
        return [
            'count' => (int)($row['review_count'] ?? 0),
            'average' => round((float)($row['average_rating'] ?? 0), 1),
        ];
    } catch (Throwable $e) {
        // До применения миграции сайт продолжает работать без отзывов.
        dev_log_runtime('Product review stats unavailable: ' . $e->getMessage());
        return ['count' => 0, 'average' => 0.0];
    }
}

function product_approved_reviews(PDO $pdo, int $productId, int $limit = 20): array
{
    if ($productId <= 0) {
        return [];
    }
    try {
        $limit = max(1, min(100, $limit));
        $stmt = $pdo->prepare(
            "SELECT id, author_name, rating, review_text, created_at
             FROM product_reviews
             WHERE product_id = :product_id AND status = 'approved'
             ORDER BY created_at DESC, id DESC LIMIT {$limit}"
        );
        $stmt->execute(['product_id' => $productId]);
        return $stmt->fetchAll() ?: [];
    } catch (Throwable $e) {
        dev_log_runtime('Product reviews unavailable: ' . $e->getMessage());
        return [];
    }
}

// Сколько времени клиент не может повторно оставить отзыв на тот же товар.
const REVIEW_COOLDOWN_COOKIE_DAYS = 30; // по cookie браузера
const REVIEW_COOLDOWN_IP_HOURS = 24;    // по IP (короче: за одним IP могут быть разные покупатели)

function review_ip_hash(): string
{
    $ip = (string)($_SERVER['REMOTE_ADDR'] ?? 'unknown');
    $salt = (string)(getenv('APP_SECRET') ?: 'product-review');
    return hash('sha256', $salt . '|' . $ip);
}

function review_cooldown_cookie_name(int $productId): string
{
    return 'rv_' . $productId;
}

/**
 * Клиент уже оставлял отзыв на этот товар: есть cookie или недавний отзыв с того же IP.
 */
function product_review_already_left(PDO $pdo, int $productId): bool
{
    return false; // TEMP: защита от повторных отзывов отключена, удалить эту строку чтобы вернуть
    if ($productId <= 0) {
        return false;
    }
    if (!empty($_COOKIE[review_cooldown_cookie_name($productId)])) {
        return true;
    }
    try {
        $stmt = $pdo->prepare(
            'SELECT 1 FROM product_reviews
             WHERE product_id = :product_id AND ip_hash = :ip_hash
               AND created_at >= (NOW() - INTERVAL ' . (int)REVIEW_COOLDOWN_IP_HOURS . ' HOUR)
             LIMIT 1'
        );
        $stmt->execute(['product_id' => $productId, 'ip_hash' => review_ip_hash()]);
        return (bool)$stmt->fetchColumn();
    } catch (Throwable $e) {
        dev_log_runtime('Product review cooldown check failed: ' . $e->getMessage());
        return false;
    }
}

function product_review_mark_left(int $productId): void
{
    setcookie(review_cooldown_cookie_name($productId), '1', [
        'expires' => time() + 86400 * REVIEW_COOLDOWN_COOKIE_DAYS,
        'path' => '/',
        'secure' => !empty($_SERVER['HTTPS']) || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

function submit_product_review(PDO $pdo, int $productId, array $data): array
{
    $name = trim((string)($data['author_name'] ?? ''));
    $email = trim((string)($data['author_email'] ?? ''));
    $text = trim((string)($data['review_text'] ?? ''));
    $rating = (int)($data['rating'] ?? 0);
    $errors = [];

    if ($productId <= 0) $errors['general'] = 'Товар не найден.';
    elseif (product_review_already_left($pdo, $productId)) $errors['general'] = 'Вы уже оставили отзыв на этот товар.';
    if ($name === '' || review_text_length($name) < 2 || review_text_length($name) > 120) {
        $errors['author_name'] = 'Укажите имя от 2 до 120 символов.';
    }
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['author_email'] = 'Укажите корректный email.';
    }
    if ($rating < 1 || $rating > 5) $errors['rating'] = 'Выберите оценку от 1 до 5.';
    if ($text === '' || review_text_length($text) < 10 || review_text_length($text) > 3000) {
        $errors['review_text'] = 'Текст отзыва должен быть от 10 до 3000 символов.';
    }
    if ($errors) return ['success' => false, 'errors' => $errors];

    try {
        $stmt = $pdo->prepare(
            "INSERT INTO product_reviews
             (product_id, author_name, author_email, rating, review_text, status, ip_hash)
             VALUES (:product_id, :author_name, :author_email, :rating, :review_text, 'pending', :ip_hash)"
        );
        $stmt->execute([
            'product_id' => $productId,
            'author_name' => $name,
            'author_email' => $email !== '' ? $email : null,
            'rating' => $rating,
            'review_text' => $text,
            'ip_hash' => review_ip_hash(),
        ]);
        return ['success' => true];
    } catch (Throwable $e) {
        dev_log_runtime('Product review submission failed: ' . $e->getMessage());
        return ['success' => false, 'errors' => ['general' => 'Не удалось отправить отзыв. Попробуйте позже.']];
    }
}
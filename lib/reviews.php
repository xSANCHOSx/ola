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

// ===== Текст отзыва: HTML из визуального редактора админки =====
// Покупатель отправляет обычный текст. Администратор редактирует отзыв в визуальном
// редакторе, и в базу попадает уже очищенный HTML (только безопасные теги).
// HTML-текст всегда начинается с блочного тега (<p>, <ul>, <ol>, <blockquote>).

/** Текст отзыва сохранён как HTML (после правки в админке), а не как обычный текст? */
function review_text_is_html(string $text): bool
{
    return (bool)preg_match('/^\s*<(?:p|ul|ol|blockquote)[\s>\/]/i', $text);
}

/**
 * Оставляет в HTML отзыва только безопасные теги: p, br, strong/b, em/i, ul/ol/li, blockquote
 * и ссылки a (только http, https, mailto). Заголовки превращаются в жирный абзац,
 * script/style/iframe и подобное удаляются вместе с содержимым, остальные теги снимаются,
 * а текст остаётся. Все атрибуты удаляются, у ссылок ставятся target и rel.
 */
function review_sanitize_html(string $html): string
{
    $html = trim($html);
    if ($html === '') return '';

    if (!class_exists('DOMDocument')) {
        // Запасной вариант без расширения DOM: превращаем в обычный текст, каждая строка — абзац.
        $plain = (string)preg_replace(['~</(?:p|li|blockquote)>~i', '~<br\s*/?>~i'], "\n", $html);
        $plain = html_entity_decode(strip_tags($plain), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $result = '';
        foreach (preg_split('/\n+/', trim($plain)) ?: [] as $line) {
            $line = trim($line);
            if ($line !== '') $result .= '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</p>';
        }
        return $result;
    }

    $allowed = ['p' => 1, 'br' => 1, 'strong' => 1, 'b' => 1, 'em' => 1, 'i' => 1, 'ul' => 1, 'ol' => 1, 'li' => 1, 'blockquote' => 1, 'a' => 1];
    $dropWithContent = ['script', 'style', 'iframe', 'object', 'embed', 'noscript', 'template', 'textarea', 'select', 'option',
        'svg', 'math', 'head', 'title', 'form', 'button', 'input', 'audio', 'video', 'canvas', 'frame', 'frameset', 'applet'];
    $blockTags = ['p', 'ul', 'ol', 'blockquote', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6'];

    $previous = libxml_use_internal_errors(true);
    $doc = new DOMDocument('1.0', 'UTF-8');
    $doc->loadHTML('<?xml encoding="UTF-8"><div>' . $html . '</div>', LIBXML_NOERROR | LIBXML_NOWARNING | LIBXML_NONET);
    libxml_clear_errors();
    libxml_use_internal_errors($previous);
    $root = $doc->getElementsByTagName('div')->item(0);
    if (!$root) return '';

    $renderNode = null;
    $renderChildren = static function (DOMNode $node) use (&$renderNode): string {
        $out = '';
        foreach ($node->childNodes as $child) $out .= $renderNode($child);
        return $out;
    };
    $renderNode = static function (DOMNode $node) use (&$renderChildren, $allowed, $dropWithContent): string {
        if ($node instanceof DOMText) { // DOMCdataSection тоже текст
            return htmlspecialchars((string)$node->nodeValue, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        }
        if (!($node instanceof DOMElement)) return ''; // комментарии, инструкции
        $tag = strtolower($node->nodeName);
        if (in_array($tag, $dropWithContent, true)) return '';
        if ($tag === 'br') return '<br>';
        $inner = $renderChildren($node);
        if (preg_match('/^h[1-6]$/', $tag)) return trim($inner) === '' ? '' : '<p><strong>' . $inner . '</strong></p>';
        if (!isset($allowed[$tag])) return $inner; // неизвестный тег снимаем, текст оставляем
        if ($tag === 'a') {
            $href = trim($node->getAttribute('href'));
            if (!preg_match('~^(?:https?://|mailto:)~i', $href)) return $inner;
            return '<a href="' . htmlspecialchars($href, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
                . '" target="_blank" rel="nofollow noopener noreferrer">' . $inner . '</a>';
        }
        return '<' . $tag . '>' . $inner . '</' . $tag . '>';
    };

    $emptyInline = static fn(string $value): bool => trim(str_replace(["\xC2\xA0", '&nbsp;'], ' ', strip_tags($value))) === '' && stripos($value, '<br') === false;
    $result = '';
    $buffer = '';
    foreach ($root->childNodes as $child) {
        $isBlock = $child instanceof DOMElement && in_array(strtolower($child->nodeName), $blockTags, true);
        if (!$isBlock) {
            $buffer .= $renderNode($child);
            continue;
        }
        if (!$emptyInline($buffer)) $result .= '<p>' . trim($buffer) . '</p>';
        $buffer = '';
        $result .= $renderNode($child);
    }
    if (!$emptyInline($buffer)) $result .= '<p>' . trim($buffer) . '</p>';

    // Пустые абзацы в начале и в конце (редактор любит оставлять <p>&nbsp;</p>) убираем.
    $emptyParagraph = '<p>(?:\s|&nbsp;|\x{00A0}|<br>)*</p>';
    $result = (string)preg_replace('~^(?:' . $emptyParagraph . ')+|(?:' . $emptyParagraph . ')+$~u', '', $result);
    return $result;
}

/** Текст отзыва без разметки (для микроразметки schema.org и проверки длины). */
function review_plain_text(string $text): string
{
    if (!review_text_is_html($text)) return trim($text);
    $text = (string)preg_replace(['~</(?:p|li|blockquote)>~i', '~<br\s*/?>~i'], "\n", $text);
    $text = html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $text = str_replace("\xC2\xA0", ' ', $text);
    $text = (string)preg_replace("/[ \t]*\n[ \t\n]*/", "\n", $text);
    return trim($text);
}

/** Безопасный HTML текста отзыва для вывода: на сайте и в админке. */
function review_render_html(string $text): string
{
    if (review_text_is_html($text)) return review_sanitize_html($text);
    return nl2br(htmlspecialchars($text, ENT_QUOTES, 'UTF-8'));
}

/** HTML для загрузки в визуальный редактор: обычный текст превращается в абзацы. */
function review_editor_html(string $text): string
{
    if (review_text_is_html($text)) return review_sanitize_html($text);
    $paragraphs = preg_split('/(?:\r\n|\r|\n){2,}/', trim($text)) ?: [];
    $html = '';
    foreach ($paragraphs as $paragraph) {
        $paragraph = trim($paragraph);
        if ($paragraph !== '') $html .= '<p>' . nl2br(htmlspecialchars($paragraph, ENT_QUOTES, 'UTF-8')) . '</p>';
    }
    return $html;
}

/**
 * Проверка и подготовка данных при правке отзыва администратором.
 * Правила те же, что у формы на сайте: имя 2–120 символов, оценка 1–5, текст 10–3000 символов.
 * Возвращает ['errors' => [...], 'values' => [author_name, author_email, rating, review_text]].
 */
function review_prepare_admin_edit(array $data): array
{
    $name = trim((string)($data['author_name'] ?? ''));
    $email = trim((string)($data['author_email'] ?? ''));
    $rating = (int)($data['rating'] ?? 0);
    $rawText = (string)($data['review_text'] ?? '');
    $errors = [];

    if ($name === '' || review_text_length($name) < 2 || review_text_length($name) > 120) {
        $errors['author_name'] = 'Укажите имя от 2 до 120 символов.';
    }
    if ($email !== '' && (!filter_var($email, FILTER_VALIDATE_EMAIL) || review_text_length($email) > 255)) {
        $errors['author_email'] = 'Укажите корректный email.';
    }
    if ($rating < 1 || $rating > 5) $errors['rating'] = 'Выберите оценку от 1 до 5.';

    $text = '';
    if (strlen($rawText) > 60000) {
        $errors['review_text'] = 'Текст отзыва слишком большой.';
    } else {
        $text = review_sanitize_html($rawText);
        $plainLength = review_text_length(review_plain_text($text));
        if ($plainLength < 10 || $plainLength > 3000) {
            $errors['review_text'] = 'Текст отзыва должен быть от 10 до 3000 символов.';
        } elseif (strlen($text) > 20000) {
            $errors['review_text'] = 'В тексте слишком много форматирования. Упростите его.';
        }
    }

    return [
        'errors' => $errors,
        'values' => ['author_name' => $name, 'author_email' => $email, 'rating' => $rating, 'review_text' => $text],
    ];
}

/** Данные отзыва для карточки в админке и для модального окна редактирования. */
function review_admin_view_data(array $review): array
{
    $rating = max(1, min(5, (int)($review['rating'] ?? 5)));
    $text = (string)($review['review_text'] ?? '');
    return [
        'id' => (int)($review['id'] ?? 0),
        'author_name' => (string)($review['author_name'] ?? ''),
        'author_email' => (string)($review['author_email'] ?? ''),
        'rating' => $rating,
        'stars' => str_repeat('★', $rating) . str_repeat('☆', 5 - $rating),
        'text_html' => review_render_html($text),
        'text_is_html' => review_text_is_html($text),
        'editor_html' => review_editor_html($text),
    ];
}

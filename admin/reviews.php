<?php
declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';
admin_require_auth();

$pdo = dev_db_connection();
$message = '';
$error = '';
$statusFilter = (string)($_GET['status'] ?? 'pending');
if (!in_array($statusFilter, ['all', 'pending', 'approved', 'rejected'], true)) $statusFilter = 'pending';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validate_csrf_token()) {
        $error = 'Недействительный CSRF-токен. Обновите страницу.';
    } else {
        $reviewId = (int)($_POST['review_id'] ?? 0);
        $action = (string)($_POST['action'] ?? '');
        $admin = admin_current_user() ?? [];
        try {
            if ($reviewId <= 0) throw new RuntimeException('Отзыв не найден.');
            if (in_array($action, ['approve', 'reject'], true)) {
                $newStatus = $action === 'approve' ? 'approved' : 'rejected';
                $stmt = $pdo->prepare(
                    'UPDATE product_reviews SET status = :status, moderated_at = NOW(), moderated_by = :admin_id WHERE id = :id'
                );
                $stmt->execute(['status' => $newStatus, 'admin_id' => (int)($admin['id'] ?? 0), 'id' => $reviewId]);
                dev_log_security('PRODUCT_REVIEW_MODERATED', ['review_id' => $reviewId, 'status' => $newStatus, 'admin_id' => $admin['id'] ?? null]);
                $message = $newStatus === 'approved' ? 'Отзыв опубликован.' : 'Отзыв отклонён.';
            } elseif ($action === 'delete') {
                $stmt = $pdo->prepare('DELETE FROM product_reviews WHERE id = :id');
                $stmt->execute(['id' => $reviewId]);
                dev_log_security('PRODUCT_REVIEW_DELETED', ['review_id' => $reviewId, 'admin_id' => $admin['id'] ?? null]);
                $message = 'Отзыв удалён.';
            } else {
                throw new RuntimeException('Неизвестное действие.');
            }
        } catch (Throwable $e) {
            $error = $e instanceof RuntimeException ? $e->getMessage() : 'Не удалось изменить отзыв.';
            dev_log_runtime('Product review admin action failed: ' . $e->getMessage());
        }
    }
}

$reviews = [];
$stats = ['pending' => 0, 'approved' => 0, 'rejected' => 0];
try {
    $stats = $pdo->query("SELECT status, COUNT(*) AS total FROM product_reviews GROUP BY status")->fetchAll(PDO::FETCH_KEY_PAIR) ?: $stats;
    $query = '
        SELECT r.*, p.name AS product_name, p.link AS product_link
        FROM product_reviews r
        INNER JOIN products p ON p.id = r.product_id
        WHERE 1=1';
    $params = [];
    if ($statusFilter !== 'all') {
        $query .= ' AND r.status = :status';
        $params['status'] = $statusFilter;
    }
    $query .= ' ORDER BY FIELD(r.status, \'pending\', \'approved\', \'rejected\'), r.created_at DESC, r.id DESC LIMIT 300';
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $reviews = $stmt->fetchAll() ?: [];
} catch (Throwable $e) {
    $error = $error ?: 'Таблица отзывов ещё не создана. Выполните SQL-миграцию.';
    dev_log_runtime('Product reviews admin list failed: ' . $e->getMessage());
}
function review_status_label(string $status): string
{
    return ['pending' => 'На проверке', 'approved' => 'Опубликован', 'rejected' => 'Отклонён'][$status] ?? $status;
}
?>
<!doctype html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Отзывы товаров — Админка</title>
    <link rel="stylesheet" href="/css/bootstrap.min.css">
    <link rel="stylesheet" href="/css/admin.css">
    <style>
        .review-stat { display: inline-block; margin: 0 8px 14px 0; padding: 9px 14px; border: 1px solid #ddd; border-radius: 5px; background: #fff; }
        .review-stat a { color: inherit; text-decoration: none; }
        .review-stat strong { font-size: 18px; }
        .review-card { margin-bottom: 16px; padding: 18px; border: 1px solid #ddd; border-radius: 6px; background: #fff; }
        .review-card__head { display: flex; justify-content: space-between; gap: 12px; flex-wrap: wrap; }
        .review-card__product { color: #337ab7; }
        .review-card__meta { color: #777; font-size: 12px; }
        .review-card__stars { color: #ba385c; letter-spacing: 2px; }
        .review-card__text { margin: 14px 0; white-space: pre-wrap; line-height: 1.55; }
        .review-card__actions { display: flex; gap: 8px; flex-wrap: wrap; }
        .review-status { display: inline-block; padding: 3px 8px; border-radius: 12px; font-size: 12px; }
        .review-status--pending { color: #856404; background: #fff3cd; }
        .review-status--approved { color: #155724; background: #d4edda; }
        .review-status--rejected { color: #721c24; background: #f8d7da; }
    </style>
</head>
<body>
<div class="container">
    <?php require __DIR__ . '/_nav.php'; ?>
    <h3>Отзывы товаров</h3>
    <?php if ($message): ?><div class="alert alert-success"><?= admin_h($message) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert alert-danger"><?= admin_h($error) ?></div><?php endif; ?>
    <div class="review-stat"><a href="?status=pending">На проверке: <strong><?= (int)($stats['pending'] ?? 0) ?></strong></a></div>
    <div class="review-stat"><a href="?status=approved">Опубликовано: <strong><?= (int)($stats['approved'] ?? 0) ?></strong></a></div>
    <div class="review-stat"><a href="?status=rejected">Отклонено: <strong><?= (int)($stats['rejected'] ?? 0) ?></strong></a></div>
    <div class="review-stat"><a href="?status=all">Все отзывы</a></div>
    <?php if (!$reviews): ?>
        <p class="text-muted">Отзывов в выбранном статусе нет.</p>
    <?php endif; ?>
    <?php foreach ($reviews as $review): ?>
        <article class="review-card">
            <div class="review-card__head">
                <div>
                    <strong><?= admin_h((string)$review['author_name']) ?></strong>
                    <?php if (!empty($review['author_email'])): ?> <span class="review-card__meta">&lt;<?= admin_h((string)$review['author_email']) ?>&gt;</span><?php endif; ?>
                    <div class="review-card__meta"><?= admin_h((string)$review['created_at']) ?></div>
                </div>
                <div>
                    <span class="review-card__stars"><?= str_repeat('★', (int)$review['rating']) . str_repeat('☆', 5 - (int)$review['rating']) ?></span>
                    <span class="review-status review-status--<?= admin_h((string)$review['status']) ?>"><?= admin_h(review_status_label((string)$review['status'])) ?></span>
                </div>
            </div>
            <p class="review-card__product"><a href="<?= admin_h((string)$review['product_link']) ?>" target="_blank" rel="noopener"><?= admin_h((string)$review['product_name']) ?></a></p>
            <div class="review-card__text"><?= admin_h((string)$review['review_text']) ?></div>
            <div class="review-card__actions">
                <?php if ($review['status'] !== 'approved'): ?><form method="post"><input type="hidden" name="csrf_token" value="<?= admin_h(csrf_token()) ?>"><input type="hidden" name="review_id" value="<?= (int)$review['id'] ?>"><input type="hidden" name="action" value="approve"><button class="btn btn-success btn-sm" type="submit">Опубликовать</button></form><?php endif; ?>
                <?php if ($review['status'] !== 'rejected'): ?><form method="post"><input type="hidden" name="csrf_token" value="<?= admin_h(csrf_token()) ?>"><input type="hidden" name="review_id" value="<?= (int)$review['id'] ?>"><input type="hidden" name="action" value="reject"><button class="btn btn-warning btn-sm" type="submit">Отклонить</button></form><?php endif; ?>
                <form method="post" onsubmit="return confirm('Удалить этот отзыв без возможности восстановления?');"><input type="hidden" name="csrf_token" value="<?= admin_h(csrf_token()) ?>"><input type="hidden" name="review_id" value="<?= (int)$review['id'] ?>"><input type="hidden" name="action" value="delete"><button class="btn btn-danger btn-sm" type="submit">Удалить</button></form>
            </div>
        </article>
    <?php endforeach; ?>
</div>
</body>
</html>

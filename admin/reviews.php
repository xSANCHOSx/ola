<?php
declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../lib/reviews.php';
admin_require_auth();

$pdo = dev_db_connection();

// Редактирование отзыва из модального окна: запрос идёт через fetch, ответ в JSON.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (string)($_POST['action'] ?? '') === 'edit') {
    $respond = static function (array $payload, int $status = 200): void {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE);
        exit;
    };
    if (!validate_csrf_token()) {
        $respond(['ok' => false, 'error' => 'Недействительный CSRF-токен. Обновите страницу.'], 403);
    }
    $editId = (int)($_POST['review_id'] ?? 0);
    $prepared = review_prepare_admin_edit($_POST);
    if ($editId <= 0) {
        $respond(['ok' => false, 'error' => 'Отзыв не найден.'], 404);
    }
    if ($prepared['errors']) {
        $respond(['ok' => false, 'error' => implode(' ', $prepared['errors'])], 422);
    }
    try {
        $exists = $pdo->prepare('SELECT id FROM product_reviews WHERE id = :id');
        $exists->execute(['id' => $editId]);
        if (!$exists->fetchColumn()) {
            $respond(['ok' => false, 'error' => 'Отзыв не найден. Возможно, его уже удалили.'], 404);
        }
        $values = $prepared['values'];
        $update = $pdo->prepare(
            'UPDATE product_reviews SET author_name = :author_name, author_email = :author_email, rating = :rating, review_text = :review_text WHERE id = :id'
        );
        $update->execute([
            'author_name' => $values['author_name'],
            'author_email' => $values['author_email'] !== '' ? $values['author_email'] : null,
            'rating' => $values['rating'],
            'review_text' => $values['review_text'],
            'id' => $editId,
        ]);
        $editor = admin_current_user() ?? [];
        dev_log_security('PRODUCT_REVIEW_EDITED', ['review_id' => $editId, 'admin_id' => $editor['id'] ?? null]);
        $respond(['ok' => true, 'message' => 'Отзыв сохранён.', 'review' => review_admin_view_data($values + ['id' => $editId])]);
    } catch (Throwable $e) {
        dev_log_runtime('Product review edit failed: ' . $e->getMessage());
        $respond(['ok' => false, 'error' => 'Не удалось сохранить отзыв. Попробуйте позже.'], 500);
    }
}
$message = '';
$error = '';
$statusFilter = (string)($_GET['status'] ?? 'all');
if (!in_array($statusFilter, ['all', 'pending', 'approved', 'rejected'], true)) $statusFilter = 'all';

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
        .review-stat--active { border-color: #337ab7; background: #eaf3fb; }
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
        .review-card__text--html { white-space: normal; }
        .review-card__text p, .review-card__text ul, .review-card__text ol, .review-card__text blockquote { margin: 0 0 8px; }
        .review-card__text > :last-child { margin-bottom: 0; }
        .review-card__text blockquote { padding-left: 12px; border-left: 3px solid #ddd; color: #666; }
        #reviewEditModal .ck-editor__editable { min-height: 200px; }
        /* всплывающие панели CKEditor (ссылка и т.п.) должны быть выше модального окна Bootstrap */
        body .ck.ck-balloon-panel, body .ck.ck-dropdown__panel { z-index: 2000; }
        #reviewFlash:empty { display: none; }
    </style>
</head>
<body>
<div class="container">
    <?php require __DIR__ . '/_nav.php'; ?>
    <h3>Отзывы товаров</h3>
    <div id="reviewFlash"></div>
    <?php if ($message): ?><div class="alert alert-success"><?= admin_h($message) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert alert-danger"><?= admin_h($error) ?></div><?php endif; ?>
    <?php $totalReviews = (int)array_sum(array_map('intval', $stats)); ?>
    <div class="review-stat<?= $statusFilter === 'all' ? ' review-stat--active' : '' ?>"><a href="?status=all">Все отзывы: <strong><?= $totalReviews ?></strong></a></div>
    <div class="review-stat<?= $statusFilter === 'pending' ? ' review-stat--active' : '' ?>"><a href="?status=pending">На проверке: <strong><?= (int)($stats['pending'] ?? 0) ?></strong></a></div>
    <div class="review-stat<?= $statusFilter === 'approved' ? ' review-stat--active' : '' ?>"><a href="?status=approved">Опубликовано: <strong><?= (int)($stats['approved'] ?? 0) ?></strong></a></div>
    <div class="review-stat<?= $statusFilter === 'rejected' ? ' review-stat--active' : '' ?>"><a href="?status=rejected">Отклонено: <strong><?= (int)($stats['rejected'] ?? 0) ?></strong></a></div>
    <?php if (!$reviews): ?>
        <p class="text-muted">Отзывов в выбранном статусе нет.</p>
    <?php endif; ?>
    <?php foreach ($reviews as $review): ?>
        <?php $reviewView = review_admin_view_data($review); ?>
        <article class="review-card" data-review-id="<?= (int)$review['id'] ?>">
            <div class="review-card__head">
                <div>
                    <strong class="js-review-name"><?= admin_h((string)$review['author_name']) ?></strong>
                    <span class="review-card__meta js-review-email"<?= empty($review['author_email']) ? ' hidden' : '' ?>>&lt;<?= admin_h((string)($review['author_email'] ?? '')) ?>&gt;</span>
                    <div class="review-card__meta"><?= admin_h((string)$review['created_at']) ?></div>
                </div>
                <div>
                    <span class="review-card__stars"><?= str_repeat('★', (int)$review['rating']) . str_repeat('☆', 5 - (int)$review['rating']) ?></span>
                    <span class="review-status review-status--<?= admin_h((string)$review['status']) ?>"><?= admin_h(review_status_label((string)$review['status'])) ?></span>
                </div>
            </div>
            <p class="review-card__product"><a href="<?= admin_h((string)$review['product_link']) ?>" target="_blank" rel="noopener"><?= admin_h((string)$review['product_name']) ?></a></p>
            <div class="review-card__text js-review-text<?= $reviewView['text_is_html'] ? ' review-card__text--html' : '' ?>"><?= $reviewView['text_html'] ?></div>
            <div class="review-card__actions">
                <button type="button" class="btn btn-primary btn-sm js-review-edit" data-review="<?= admin_h((string)(json_encode($reviewView, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?: '{}')) ?>">Редактировать</button>
                <?php if ($review['status'] !== 'approved'): ?><form method="post"><input type="hidden" name="csrf_token" value="<?= admin_h(csrf_token()) ?>"><input type="hidden" name="review_id" value="<?= (int)$review['id'] ?>"><input type="hidden" name="action" value="approve"><button class="btn btn-success btn-sm" type="submit">Опубликовать</button></form><?php endif; ?>
                <?php if ($review['status'] !== 'rejected'): ?><form method="post"><input type="hidden" name="csrf_token" value="<?= admin_h(csrf_token()) ?>"><input type="hidden" name="review_id" value="<?= (int)$review['id'] ?>"><input type="hidden" name="action" value="reject"><button class="btn btn-warning btn-sm" type="submit">Отклонить</button></form><?php endif; ?>
                <form method="post" onsubmit="return confirm('Удалить этот отзыв без возможности восстановления?');"><input type="hidden" name="csrf_token" value="<?= admin_h(csrf_token()) ?>"><input type="hidden" name="review_id" value="<?= (int)$review['id'] ?>"><input type="hidden" name="action" value="delete"><button class="btn btn-danger btn-sm" type="submit">Удалить</button></form>
            </div>
        </article>
    <?php endforeach; ?>
</div>
<div class="modal fade" id="reviewEditModal" tabindex="-1" aria-labelledby="reviewEditTitle" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form id="reviewEditForm" novalidate>
                <div class="modal-header">
                    <h5 class="modal-title" id="reviewEditTitle">Редактирование отзыва</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Закрыть"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-danger d-none" id="reviewEditError" role="alert"></div>
                    <input type="hidden" name="csrf_token" value="<?= admin_h(csrf_token()) ?>">
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="review_id" id="reviewEditId" value="">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label" for="reviewEditName">Имя</label>
                            <input type="text" class="form-control" id="reviewEditName" name="author_name" maxlength="120" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label" for="reviewEditEmail">Email (необязательно)</label>
                            <input type="email" class="form-control" id="reviewEditEmail" name="author_email" maxlength="255">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="reviewEditRating">Оценка</label>
                        <select class="form-select" id="reviewEditRating" name="rating">
                            <option value="5">★★★★★ — 5</option>
                            <option value="4">★★★★☆ — 4</option>
                            <option value="3">★★★☆☆ — 3</option>
                            <option value="2">★★☆☆☆ — 2</option>
                            <option value="1">★☆☆☆☆ — 1</option>
                        </select>
                    </div>
                    <div class="mb-1">
                        <label class="form-label" for="reviewEditText">Текст отзыва</label>
                        <textarea class="form-control" id="reviewEditText" name="review_text" rows="8"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отмена</button>
                    <button type="submit" class="btn btn-primary" id="reviewEditSave">Сохранить</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- CKEditor 5 — тот же визуальный редактор, что и в описании товара -->
<script src="https://cdn.ckeditor.com/ckeditor5/41.1.0/classic/ckeditor.js"></script>
<script src="https://cdn.ckeditor.com/ckeditor5/41.1.0/classic/translations/ru.js"></script>
<script src="/js/bootstrap.min.js"></script>
<script>
(function () {
    var modalEl = document.getElementById('reviewEditModal');
    if (!modalEl || typeof bootstrap === 'undefined') return;

    var form = document.getElementById('reviewEditForm');
    var errorBox = document.getElementById('reviewEditError');
    var saveBtn = document.getElementById('reviewEditSave');
    var textarea = document.getElementById('reviewEditText');
    var flash = document.getElementById('reviewFlash');
    // focus: false — иначе модальное окно перехватывает фокус и всплывающие панели CKEditor (ссылка) не работают
    var modal = new bootstrap.Modal(modalEl, { focus: false });
    // после открытия ставим фокус в первое поле, чтобы работали Esc и клавиатура
    modalEl.addEventListener('shown.bs.modal', function () { document.getElementById('reviewEditName').focus(); });
    var editor = null, editorPromise = null, currentButton = null, currentCard = null, flashTimer = null;

    function createEditor() {
        if (editorPromise) return editorPromise;
        if (typeof ClassicEditor === 'undefined') {
            // Редактор не загрузился (нет доступа к CDN): остаётся обычное поле с HTML-кодом
            editorPromise = Promise.resolve(null);
            return editorPromise;
        }
        editorPromise = ClassicEditor.create(textarea, {
            toolbar: {
                items: ['undo', 'redo', '|', 'bold', 'italic', '|', 'bulletedList', 'numberedList', '|', 'link', 'blockQuote'],
                shouldNotGroupWhenFull: true
            },
            language: 'ru'
        }).then(function (created) {
            editor = created;
            return created;
        }).catch(function (error) {
            console.error('Не удалось загрузить редактор отзыва', error);
            return null;
        });
        return editorPromise;
    }

    function showError(message) {
        errorBox.textContent = message;
        errorBox.classList.remove('d-none');
    }

    function showFlash(message) {
        flash.innerHTML = '';
        var box = document.createElement('div');
        box.className = 'alert alert-success';
        box.textContent = message;
        flash.appendChild(box);
        clearTimeout(flashTimer);
        flashTimer = setTimeout(function () { flash.innerHTML = ''; }, 5000);
    }

    function updateCard(card, button, review) {
        card.querySelector('.js-review-name').textContent = review.author_name;
        var email = card.querySelector('.js-review-email');
        email.textContent = review.author_email ? '<' + review.author_email + '>' : '';
        email.hidden = !review.author_email;
        card.querySelector('.review-card__stars').textContent = review.stars;
        var text = card.querySelector('.js-review-text');
        text.innerHTML = review.text_html; // HTML очищен на сервере
        text.classList.toggle('review-card__text--html', !!review.text_is_html);
        button.setAttribute('data-review', JSON.stringify(review));
    }

    document.addEventListener('click', function (event) {
        var button = event.target.closest('.js-review-edit');
        if (!button) return;
        var data;
        try { data = JSON.parse(button.getAttribute('data-review') || '{}'); } catch (e) { return; }
        currentButton = button;
        currentCard = button.closest('.review-card');
        errorBox.classList.add('d-none');
        document.getElementById('reviewEditId').value = data.id || '';
        document.getElementById('reviewEditName').value = data.author_name || '';
        document.getElementById('reviewEditEmail').value = data.author_email || '';
        document.getElementById('reviewEditRating').value = String(data.rating || 5);
        textarea.value = data.editor_html || '';
        modal.show();
        createEditor().then(function (instance) {
            if (instance) instance.setData(data.editor_html || '');
        });
    });

    form.addEventListener('submit', function (event) {
        event.preventDefault();
        if (editor) textarea.value = editor.getData();
        errorBox.classList.add('d-none');
        saveBtn.disabled = true;
        fetch(window.location.pathname, {
            method: 'POST',
            body: new FormData(form),
            credentials: 'same-origin',
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        }).then(function (response) {
            return response.json().catch(function () { return { ok: false, error: 'Некорректный ответ сервера.' }; });
        }).then(function (result) {
            if (!result.ok) { showError(result.error || 'Не удалось сохранить отзыв.'); return; }
            updateCard(currentCard, currentButton, result.review);
            modal.hide();
            showFlash(result.message || 'Отзыв сохранён.');
        }).catch(function () {
            showError('Не удалось связаться с сервером. Проверьте подключение.');
        }).then(function () {
            saveBtn.disabled = false;
        });
    });
})();
</script>
</body>
</html>
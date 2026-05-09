<?php

declare(strict_types=1);

// admin/coupons.php — Управління купонами в адмін-панелі
// Функціональність: CREATE, READ, UPDATE, DELETE, активація/деактивація

require __DIR__ . '/_bootstrap.php';
admin_require_auth();

// ═══ Функції CRUD ═══════════════════════════════════════════════════════════

/**
 * Отримати список купонів з фільтрацією
 */
function get_coupons_list(PDO $pdo, ?bool $active = null, ?string $search = null): array
{
    $query = 'SELECT * FROM coupons WHERE 1=1';
    $params = [];

    if ($active !== null) {
        $query .= ' AND is_active = ?';
        $params[] = (int)$active;
    }

    if (!empty($search)) {
        $query .= ' AND (code LIKE ? OR name LIKE ?)';
        $searchTerm = '%' . $search . '%';
        $params[] = $searchTerm;
        $params[] = $searchTerm;
    }

    $query .= ' ORDER BY created_at DESC LIMIT 100';

    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    return $stmt->fetchAll() ?: [];
}

/**
 * Отримати купон за ID
 */
function get_coupon_by_id(PDO $pdo, int $coupon_id): ?array
{
    $stmt = $pdo->prepare('SELECT * FROM coupons WHERE id = ?');
    $stmt->execute([$coupon_id]);
    $result = $stmt->fetch();
    return $result ?: null;
}

/**
 * Отримати купон за кодом
 */
function get_coupon_by_code(PDO $pdo, string $code): ?array
{
    $stmt = $pdo->prepare('SELECT * FROM coupons WHERE code = ?');
    $stmt->execute([$code]);
    $result = $stmt->fetch();
    return $result ?: null;
}

/**
 * Валідувати дані купона перед збереженням
 */
function validate_coupon_data(array $data): array
{
    $errors = [];

    if (empty($data['code'])) {
        $errors['code'] = 'Код купона обов\'язковий';
    } elseif (strlen($data['code']) < 3 || strlen($data['code']) > 50) {
        $errors['code'] = 'Код мусить бути від 3 до 50 символів';
    } elseif (!preg_match('/^[A-Z0-9]+$/i', $data['code'])) {
        $errors['code'] = 'Код мусить містити лише латиницю та цифри';
    }

    if (empty($data['name'])) {
        $errors['name'] = 'Назва купона обов\'язкова';
    } elseif (strlen($data['name']) > 255) {
        $errors['name'] = 'Назва не повинна перевищувати 255 символів';
    }

    if (empty($data['discount_type']) || !in_array($data['discount_type'], ['fixed', 'percent'])) {
        $errors['discount_type'] = 'Тип знижки повинен бути fixed або percent';
    }

    $discount_value = (float)($data['discount_value'] ?? 0);
    if ($discount_value <= 0) {
        $errors['discount_value'] = 'Значення знижки повинно бути більше 0';
    }
    if (($data['discount_type'] ?? '') === 'percent' && $discount_value > 100) {
        $errors['discount_value'] = 'Відсоток не може перевищувати 100%';
    }

    $min_sum = (float)($data['min_order_sum'] ?? 0);
    if ($min_sum < 0) {
        $errors['min_order_sum'] = 'Мінімальна сума не може бути негативною';
    }

    $valid_from = $data['valid_from'] ?? null;
    $valid_to   = $data['valid_to']   ?? null;

    if (!empty($valid_from) && !strtotime($valid_from)) {
        $errors['valid_from'] = 'Неправильний формат дати початку';
    }
    if (!empty($valid_to) && !strtotime($valid_to)) {
        $errors['valid_to'] = 'Неправильний формат дати закінчення';
    }
    if (!empty($valid_from) && !empty($valid_to) && strtotime($valid_from) > strtotime($valid_to)) {
        $errors['valid_from'] = 'Дата початку повинна бути раніше дати закінчення';
    }

    return $errors;
}

/**
 * Створити новий купон
 */
function create_coupon(PDO $pdo, array $data): array
{
    $errors = validate_coupon_data($data);
    if (!empty($errors)) {
        return ['success' => false, 'errors' => $errors];
    }

    if (get_coupon_by_code($pdo, $data['code'])) {
        return ['success' => false, 'errors' => ['code' => 'Купон з таким кодом вже існує']];
    }

    try {
        $stmt = $pdo->prepare('
            INSERT INTO coupons
            (code, name, discount_type, discount_value, min_order_sum, valid_from, valid_to, max_usage_count, is_active)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ');

        $stmt->execute([
            $data['code'],
            $data['name'],
            $data['discount_type'],
            (float)$data['discount_value'],
            (float)($data['min_order_sum'] ?? 0),
            !empty($data['valid_from']) ? $data['valid_from'] : null,
            !empty($data['valid_to'])   ? $data['valid_to']   : null,
            !empty($data['max_usage_count']) ? (int)$data['max_usage_count'] : null,
            isset($data['is_active']) ? 1 : 0,
        ]);

        dev_log_security_event('COUPON_CREATED', [
            'coupon_code' => $data['code'],
            'admin_id'    => $_SESSION[dev_app_config()['admin_session_key']] ?? null,
        ]);

        return ['success' => true, 'coupon_id' => (int)$pdo->lastInsertId()];
    } catch (Throwable $e) {
        dev_log_runtime('Coupon creation failed: ' . $e->getMessage());
        return ['success' => false, 'errors' => ['general' => 'Помилка при створенні купона']];
    }
}

/**
 * Оновити існуючий купон
 */
function update_coupon(PDO $pdo, int $coupon_id, array $data): array
{
    $coupon = get_coupon_by_id($pdo, $coupon_id);
    if (!$coupon) {
        return ['success' => false, 'errors' => ['general' => 'Купон не знайдено']];
    }

    $errors = validate_coupon_data($data);
    if (!empty($errors)) {
        return ['success' => false, 'errors' => $errors];
    }

    if ($data['code'] !== $coupon['code'] && get_coupon_by_code($pdo, $data['code'])) {
        return ['success' => false, 'errors' => ['code' => 'Купон з таким кодом вже існує']];
    }

    try {
        $stmt = $pdo->prepare('
            UPDATE coupons SET
                code = ?, name = ?, discount_type = ?, discount_value = ?,
                min_order_sum = ?, valid_from = ?, valid_to = ?, max_usage_count = ?, is_active = ?
            WHERE id = ?
        ');

        $stmt->execute([
            $data['code'],
            $data['name'],
            $data['discount_type'],
            (float)$data['discount_value'],
            (float)($data['min_order_sum'] ?? 0),
            !empty($data['valid_from']) ? $data['valid_from'] : null,
            !empty($data['valid_to'])   ? $data['valid_to']   : null,
            !empty($data['max_usage_count']) ? (int)$data['max_usage_count'] : null,
            isset($data['is_active']) ? 1 : 0,
            $coupon_id,
        ]);

        dev_log_security_event('COUPON_UPDATED', [
            'coupon_id'   => $coupon_id,
            'coupon_code' => $data['code'],
            'admin_id'    => $_SESSION[dev_app_config()['admin_session_key']] ?? null,
        ]);

        return ['success' => true];
    } catch (Throwable $e) {
        dev_log_runtime('Coupon update failed: ' . $e->getMessage());
        return ['success' => false, 'errors' => ['general' => 'Помилка при оновленні купона']];
    }
}

/**
 * Видалити купон
 */
function delete_coupon(PDO $pdo, int $coupon_id): array
{
    $coupon = get_coupon_by_id($pdo, $coupon_id);
    if (!$coupon) {
        return ['success' => false, 'error' => 'Купон не знайдено'];
    }

    try {
        $stmt = $pdo->prepare('DELETE FROM coupons WHERE id = ?');
        $stmt->execute([$coupon_id]);

        dev_log_security_event('COUPON_DELETED', [
            'coupon_id'   => $coupon_id,
            'coupon_code' => $coupon['code'],
            'admin_id'    => $_SESSION[dev_app_config()['admin_session_key']] ?? null,
        ]);

        return ['success' => true];
    } catch (Throwable $e) {
        dev_log_runtime('Coupon deletion failed: ' . $e->getMessage());
        return ['success' => false, 'error' => 'Помилка при видаленні купона'];
    }
}

/**
 * Переключити статус активності купона
 */
function toggle_coupon_status(PDO $pdo, int $coupon_id): array
{
    $coupon = get_coupon_by_id($pdo, $coupon_id);
    if (!$coupon) {
        return ['success' => false, 'error' => 'Купон не знайдено'];
    }

    try {
        $new_status = (int)!$coupon['is_active'];
        $stmt = $pdo->prepare('UPDATE coupons SET is_active = ? WHERE id = ?');
        $stmt->execute([$new_status, $coupon_id]);

        dev_log_security_event('COUPON_STATUS_CHANGED', [
            'coupon_id'   => $coupon_id,
            'coupon_code' => $coupon['code'],
            'new_status'  => $new_status,
            'admin_id'    => $_SESSION[dev_app_config()['admin_session_key']] ?? null,
        ]);

        return ['success' => true, 'new_status' => $new_status];
    } catch (Throwable $e) {
        dev_log_runtime('Coupon status toggle failed: ' . $e->getMessage());
        return ['success' => false, 'error' => 'Помилка при зміні статусу'];
    }
}

// ═══ Обробка AJAX запитів ═══════════════════════════════════════════════════

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_GET['ajax'])) {
    header('Content-Type: application/json');
    $pdo    = dev_db_connection();
    $action = $_GET['action'] ?? '';

    $csrf = $_POST['csrf_token'] ?? '';
    if (!hash_equals(csrf_token(), $csrf)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'CSRF validation failed']);
        exit;
    }

    switch ($action) {
        case 'create':
            echo json_encode(create_coupon($pdo, $_POST));
            break;
        case 'update':
            echo json_encode(update_coupon($pdo, (int)($_POST['coupon_id'] ?? 0), $_POST));
            break;
        case 'delete':
            echo json_encode(delete_coupon($pdo, (int)($_POST['coupon_id'] ?? 0)));
            break;
        case 'toggle_status':
            echo json_encode(toggle_coupon_status($pdo, (int)($_POST['coupon_id'] ?? 0)));
            break;
        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Unknown action']);
    }
    exit;
}

// ═══ Відображення сторінки ══════════════════════════════════════════════════

$pdo     = dev_db_connection();
$coupons = get_coupons_list($pdo);
$csrf    = csrf_token();
$active_count = count(array_filter($coupons, fn($c) => $c['is_active']));
?>
<!doctype html>
<html lang="uk">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Управління купонами - Адмін</title>
    <link rel="stylesheet" href="/css/bootstrap.min.css">
</head>

<body>
    <div class="container">
        <?php require __DIR__ . '/_nav.php'; ?>

        <div class="d-flex align-items-center justify-content-between mb-3">
            <h3 class="mb-0">💰 Управління купонами</h3>
            <button class="btn btn-success" onclick="openCreateModal()">➕ Новий купон</button>
        </div>

        <div id="successMsg" class="alert alert-success d-none"></div>
        <div id="errorMsg" class="alert alert-danger  d-none"></div>

        <h5 class="mb-3">Активних купонів: <span class="badge bg-success"><?= $active_count ?></span></h5>

        <?php if (!empty($coupons)): ?>
            <table class="table table-bordered table-striped">
                <thead class="table-primary">
                    <tr>
                        <th>Код</th>
                        <th>Назва</th>
                        <th>Знижка</th>
                        <th>Мін. сума</th>
                        <th>Дійсно до</th>
                        <th>Використано</th>
                        <th>Статус</th>
                        <th>Дії</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($coupons as $coupon): ?>
                        <tr>
                            <td><strong><?= admin_h((string)$coupon['code']) ?></strong></td>
                            <td><?= admin_h((string)$coupon['name']) ?></td>
                            <td>
                                <?php if ($coupon['discount_type'] === 'percent'): ?>
                                    <span class="badge bg-warning text-dark"><?= admin_h((string)$coupon['discount_value']) ?>%</span>
                                <?php else: ?>
                                    <span class="badge bg-success"><?= admin_h((string)$coupon['discount_value']) ?> р.</span>
                                <?php endif; ?>
                            </td>
                            <td><?= number_format((float)$coupon['min_order_sum'], 2) ?> р.</td>
                            <td><?= $coupon['valid_to'] ? date('d.m.Y', strtotime($coupon['valid_to'])) : '—' ?></td>
                            <td>
                                <?= (int)$coupon['used_count'] ?><?= $coupon['max_usage_count'] ? '/' . (int)$coupon['max_usage_count'] : '' ?>
                            </td>
                            <td>
                                <?php if ($coupon['is_active']): ?>
                                    <span class="badge bg-success">Активний</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary">Неактивний</span>
                                <?php endif; ?>
                            </td>
                            <td style="white-space: nowrap;">
                                <button class="btn btn-sm btn-primary"
                                    onclick="openEditModal(<?= (int)$coupon['id'] ?>, <?= htmlspecialchars(json_encode($coupon), ENT_QUOTES) ?>)">
                                    ✎ Ред.
                                </button>
                                <button class="btn btn-sm btn-warning" onclick="toggleStatus(<?= (int)$coupon['id'] ?>)">
                                    <?= $coupon['is_active'] ? '🔴 Вимк.' : '🟢 Увімк.' ?>
                                </button>
                                <button class="btn btn-sm btn-danger"
                                    onclick="deleteCoupon(<?= (int)$coupon['id'] ?>, '<?= admin_h((string)$coupon['code']) ?>')">
                                    🗑
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p class="text-muted text-center py-4">Купони не знайдені</p>
        <?php endif; ?>
    </div>

    <!-- Bootstrap Modal -->
    <div class="modal fade" id="couponModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalTitle">Новий купон</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="couponForm">
                        <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                        <input type="hidden" id="couponId" name="coupon_id">

                        <div class="mb-3">
                            <label class="form-label fw-bold">Код купона (латиниця, цифри)</label>
                            <input type="text" id="code" name="code" class="form-control" required maxlength="50">
                            <div class="text-danger small" id="code-error"></div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold">Назва купона</label>
                            <input type="text" id="name" name="name" class="form-control" required maxlength="255">
                            <div class="text-danger small" id="name-error"></div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold">Тип знижки</label>
                            <select id="discountType" name="discount_type" class="form-select" onchange="updateDiscountLabel()">
                                <option value="fixed">Фіксована сума (р.)</option>
                                <option value="percent">Відсоток (%)</option>
                            </select>
                            <div class="text-danger small" id="discount_type-error"></div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold" id="discountLabel">Величина знижки (р.)</label>
                            <input type="number" id="discountValue" name="discount_value" class="form-control" step="0.01" required>
                            <div class="text-danger small" id="discount_value-error"></div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold">Мінімальна сума замовлення (р.)</label>
                            <input type="number" id="minOrderSum" name="min_order_sum" class="form-control" step="0.01" value="0">
                            <div class="text-danger small" id="min_order_sum-error"></div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold">Дійсний з (опціонально)</label>
                            <input type="datetime-local" id="validFrom" name="valid_from" class="form-control">
                            <div class="text-danger small" id="valid_from-error"></div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold">Дійсний до (опціонально)</label>
                            <input type="datetime-local" id="validTo" name="valid_to" class="form-control">
                            <div class="text-danger small" id="valid_to-error"></div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold">Максимум використань (порожньо = без обмежень)</label>
                            <input type="number" id="maxUsageCount" name="max_usage_count" class="form-control" min="1">
                            <div class="text-danger small" id="max_usage_count-error"></div>
                        </div>

                        <div class="mb-3 form-check">
                            <input type="checkbox" class="form-check-input" id="isActive" name="is_active" checked>
                            <label class="form-check-label" for="isActive">Активний купон</label>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Скасувати</button>
                    <button type="button" class="btn btn-primary" onclick="submitCouponForm()">Зберегти купон</button>
                </div>
            </div>
        </div>
    </div>

    <script src="/js/jquery-3.7.1.min.js"></script>
    <script src="/js/bootstrap.bundle.min.js"></script>
    <script>
        const CSRF = '<?= $csrf ?>';
        const bsModal = new bootstrap.Modal(document.getElementById('couponModal'));

        function updateDiscountLabel() {
            const type = document.getElementById('discountType').value;
            document.getElementById('discountLabel').textContent =
                type === 'percent' ? 'Величина знижки (%)' : 'Величина знижки (р.)';
        }

        function openCreateModal() {
            document.getElementById('modalTitle').textContent = 'Новий купон';
            document.getElementById('couponForm').reset();
            document.getElementById('couponId').value = '';
            clearErrors();
            bsModal.show();
        }

        function openEditModal(couponId, coupon) {
            document.getElementById('modalTitle').textContent = 'Редагування купона';
            document.getElementById('couponId').value = couponId;
            document.getElementById('code').value = coupon.code;
            document.getElementById('name').value = coupon.name;
            document.getElementById('discountType').value = coupon.discount_type;
            document.getElementById('discountValue').value = coupon.discount_value;
            document.getElementById('minOrderSum').value = coupon.min_order_sum;
            document.getElementById('isActive').checked = coupon.is_active == 1;
            document.getElementById('validFrom').value = coupon.valid_from ? coupon.valid_from.replace(' ', 'T') : '';
            document.getElementById('validTo').value = coupon.valid_to ? coupon.valid_to.replace(' ', 'T') : '';
            document.getElementById('maxUsageCount').value = coupon.max_usage_count ?? '';
            updateDiscountLabel();
            clearErrors();
            bsModal.show();
        }

        function clearErrors() {
            document.querySelectorAll('[id$="-error"]').forEach(el => el.textContent = '');
        }

        function showMessage(type, message) {
            const el = document.getElementById(type === 'success' ? 'successMsg' : 'errorMsg');
            el.textContent = message;
            el.classList.remove('d-none');
            setTimeout(() => el.classList.add('d-none'), 5000);
        }

        function displayErrors(errors) {
            clearErrors();
            Object.keys(errors).forEach(field => {
                const el = document.getElementById(field + '-error');
                if (el) el.textContent = errors[field];
            });
        }

        function submitCouponForm() {
            const couponId = document.getElementById('couponId').value;
            const action = couponId ? 'update' : 'create';
            const formData = new FormData(document.getElementById('couponForm'));
            formData.set('csrf_token', CSRF);

            fetch(`?ajax=1&action=${action}`, {
                    method: 'POST',
                    body: formData
                })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        showMessage('success', 'Купон успішно збережений');
                        bsModal.hide();
                        setTimeout(() => location.reload(), 1000);
                    } else {
                        data.errors ? displayErrors(data.errors) : showMessage('error', data.error || 'Помилка при збереженні');
                    }
                })
                .catch(e => showMessage('error', 'Помилка мережі: ' + e.message));
        }

        function toggleStatus(couponId) {
            if (!confirm('Ви впевнені?')) return;
            fetch('?ajax=1&action=toggle_status', {
                    method: 'POST',
                    body: new URLSearchParams({
                        csrf_token: CSRF,
                        coupon_id: couponId
                    })
                })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        showMessage('success', 'Статус змінено');
                        setTimeout(() => location.reload(), 1000);
                    } else showMessage('error', data.error);
                })
                .catch(e => showMessage('error', 'Помилка: ' + e.message));
        }

        function deleteCoupon(couponId, code) {
            if (!confirm(`Видалити купон "${code}"? Цю дію не можна скасувати!`)) return;
            fetch('?ajax=1&action=delete', {
                    method: 'POST',
                    body: new URLSearchParams({
                        csrf_token: CSRF,
                        coupon_id: couponId
                    })
                })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        showMessage('success', 'Купон видалено');
                        setTimeout(() => location.reload(), 1000);
                    } else showMessage('error', data.error);
                })
                .catch(e => showMessage('error', 'Помилка: ' + e.message));
        }
    </script>
</body>

</html>
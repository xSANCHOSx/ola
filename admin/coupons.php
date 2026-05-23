<?php

declare(strict_types=1);

// admin/coupons.php — Управление купонами в админ-панели
// Функциональность: CREATE, READ, UPDATE, DELETE, активация/деактивация

require __DIR__ . '/_bootstrap.php';
admin_require_auth();

// ═══ Функции CRUD ═══════════════════════════════════════════════════════════

/**
 * Получить список купонов с фильтрацией
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
 * Получить список архивированных купонов
 */
function get_archived_coupons_list(PDO $pdo, ?string $search = null): array
{
    $query = 'SELECT * FROM coupons_archived WHERE 1=1';
    $params = [];

    if (!empty($search)) {
        $query .= ' AND (code LIKE ? OR name LIKE ?)';
        $searchTerm = '%' . $search . '%';
        $params[] = $searchTerm;
        $params[] = $searchTerm;
    }

    $query .= ' ORDER BY archived_at DESC LIMIT 100';

    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    return $stmt->fetchAll() ?: [];
}

/**
 * Получить купон по ID
 */
function get_coupon_by_id(PDO $pdo, int $coupon_id): ?array
{
    $stmt = $pdo->prepare('SELECT * FROM coupons WHERE id = ?');
    $stmt->execute([$coupon_id]);
    $result = $stmt->fetch();
    return $result ?: null;
}

/**
 * Получить купон по коду
 */
function get_coupon_by_code(PDO $pdo, string $code): ?array
{
    $stmt = $pdo->prepare('SELECT * FROM coupons WHERE code = ?');
    $stmt->execute([$code]);
    $result = $stmt->fetch();
    return $result ?: null;
}

/**
 * Валидировать данные купона перед сохранением
 */
function validate_coupon_data(array $data): array
{
    $errors = [];

    if (empty($data['code'])) {
        $errors['code'] = 'Код купона обязателен';
    } elseif (strlen($data['code']) < 3 || strlen($data['code']) > 50) {
        $errors['code'] = 'Код должен быть от 3 до 50 символов';
    } elseif (!preg_match('/^[A-Z0-9]+$/i', $data['code'])) {
        $errors['code'] = 'Код должен содержать только латиницу и цифры';
    }

    if (empty($data['name'])) {
        $errors['name'] = 'Название купона обязательно';
    } elseif (strlen($data['name']) > 255) {
        $errors['name'] = 'Название не должно превышать 255 символов';
    }

    if (empty($data['discount_type']) || !in_array($data['discount_type'], ['fixed', 'percent'])) {
        $errors['discount_type'] = 'Тип скидки должен быть fixed или percent';
    }

    $discount_value = (float)($data['discount_value'] ?? 0);
    if ($discount_value <= 0) {
        $errors['discount_value'] = 'Значение скидки должно быть больше 0';
    }
    if (($data['discount_type'] ?? '') === 'percent' && $discount_value > 100) {
        $errors['discount_value'] = 'Процент не может превышать 100%';
    }

    $min_sum = (float)($data['min_order_sum'] ?? 0);
    if ($min_sum < 0) {
        $errors['min_order_sum'] = 'Минимальная сумма не может быть отрицательной';
    }

    $valid_from = $data['valid_from'] ?? null;
    $valid_to   = $data['valid_to']   ?? null;

    if (!empty($valid_from) && !strtotime($valid_from)) {
        $errors['valid_from'] = 'Неправильный формат даты начала';
    }
    if (!empty($valid_to) && !strtotime($valid_to)) {
        $errors['valid_to'] = 'Неправильный формат даты окончания';
    }
    if (!empty($valid_from) && !empty($valid_to) && strtotime($valid_from) > strtotime($valid_to)) {
        $errors['valid_from'] = 'Дата начала должна быть раньше даты окончания';
    }

    return $errors;
}

/**
 * Создать новый купон
 */
/**
 * Нормализовать дату из datetime-local (2026-05-13T00:00) → MySQL DATETIME (2026-05-13 00:00:00)
 * Для valid_to дополнительно выставляем конец дня (23:59:59) чтобы купон работал весь день
 */
function normalize_datetime(?string $val, bool $endOfDay = false): ?string
{
    if (empty($val)) return null;
    $ts = strtotime($val);
    if (!$ts) return null;
    if ($endOfDay) {
        // Если время не указано явно (00:00) — ставим конец дня
        $hasTime = preg_match('/T\d{2}:\d{2}(?::\d{2})?$/', $val) && !str_ends_with($val, 'T00:00') && !str_ends_with($val, 'T00:00:00');
        if (!$hasTime) {
            return date('Y-m-d', $ts) . ' 23:59:59';
        }
    }
    return date('Y-m-d H:i:s', $ts);
}

function create_coupon(PDO $pdo, array $data): array
{
    $errors = validate_coupon_data($data);
    if (!empty($errors)) {
        return ['success' => false, 'errors' => $errors];
    }

    if (get_coupon_by_code($pdo, $data['code'])) {
        return ['success' => false, 'errors' => ['code' => 'Купон с таким кодом уже существует']];
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
            !empty($data['valid_from']) ? normalize_datetime($data['valid_from']) : null,
            !empty($data['valid_to'])   ? normalize_datetime($data['valid_to'], true) : null,
            !empty($data['max_usage_count']) ? (int)$data['max_usage_count'] : null,
            isset($data['is_active']) ? 1 : 0,
        ]);

        $cfg = dev_app_config();
        $admin_session = $_SESSION[$cfg['admin_session_key']] ?? null;
        $admin_id = $admin_session['id'] ?? null;

        dev_log_security('COUPON_CREATED', [
            'coupon_code' => $data['code'],
            'admin_id'    => $admin_id,
        ]);

        return ['success' => true, 'coupon_id' => (int)$pdo->lastInsertId()];
    } catch (Throwable $e) {
        dev_log_runtime('Coupon creation failed: ' . $e->getMessage());
        return ['success' => false, 'errors' => ['general' => 'Ошибка при создании купона']];
    }
}

/**
 * Обновить существующий купон
 */
function update_coupon(PDO $pdo, int $coupon_id, array $data): array
{
    $coupon = get_coupon_by_id($pdo, $coupon_id);
    if (!$coupon) {
        return ['success' => false, 'errors' => ['general' => 'Купон не найден']];
    }

    $errors = validate_coupon_data($data);
    if (!empty($errors)) {
        return ['success' => false, 'errors' => $errors];
    }

    if ($data['code'] !== $coupon['code'] && get_coupon_by_code($pdo, $data['code'])) {
        return ['success' => false, 'errors' => ['code' => 'Купон с таким кодом уже существует']];
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
            !empty($data['valid_from']) ? normalize_datetime($data['valid_from']) : null,
            !empty($data['valid_to'])   ? normalize_datetime($data['valid_to'], true) : null,
            !empty($data['max_usage_count']) ? (int)$data['max_usage_count'] : null,
            isset($data['is_active']) ? 1 : 0,
            $coupon_id,
        ]);

        dev_log_security('COUPON_UPDATED', [
            'coupon_id'   => $coupon_id,
            'coupon_code' => $data['code'],
            'admin_id'    => $_SESSION[dev_app_config()['admin_session_key']]['id'] ?? null,
        ]);

        return ['success' => true];
    } catch (Throwable $e) {
        dev_log_runtime('Coupon update failed: ' . $e->getMessage());
        return ['success' => false, 'errors' => ['general' => 'Ошибка при обновлении купона']];
    }
}

/**
 * Удалить купон (с архивацией)
 */
function delete_coupon(PDO $pdo, int $coupon_id): array
{
    $coupon = get_coupon_by_id($pdo, $coupon_id);
    if (!$coupon) {
        return ['success' => false, 'error' => 'Купон не найден'];
    }

    try {
        $pdo->beginTransaction();

        // Шаг 1: Архивируем купон
        $cfg = dev_app_config();
        $admin_session = $_SESSION[$cfg['admin_session_key']] ?? null;
        $admin_id = $admin_session['id'] ?? null;

        $stmt = $pdo->prepare('
            INSERT INTO coupons_archived
            (id, code, name, discount_type, discount_value, min_order_sum,
             valid_from, valid_to, max_usage_count, used_count, is_active,
             created_at, updated_at, archived_by)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ');

        $stmt->execute([
            $coupon['id'],
            $coupon['code'],
            $coupon['name'],
            $coupon['discount_type'],
            $coupon['discount_value'],
            $coupon['min_order_sum'],
            $coupon['valid_from'],
            $coupon['valid_to'],
            $coupon['max_usage_count'],
            $coupon['used_count'],
            $coupon['is_active'],
            $coupon['created_at'],
            $coupon['updated_at'],
            $admin_id,
        ]);

        // Шаг 2: Удаляем из основной таблицы
        // CASCADE автоматически удалит связанные записи в coupon_usage
        $stmt = $pdo->prepare('DELETE FROM coupons WHERE id = ?');
        $stmt->execute([$coupon_id]);

        $pdo->commit();

        dev_log_security('COUPON_ARCHIVED', [
            'coupon_id'   => $coupon_id,
            'coupon_code' => $coupon['code'],
            'used_count'  => $coupon['used_count'],
            'admin_id'    => $admin_id,
        ]);

        return ['success' => true];
    } catch (Throwable $e) {
        $pdo->rollBack();
        dev_log_runtime('Coupon archiving failed: ' . $e->getMessage());
        return ['success' => false, 'error' => 'Ошибка при архивации купона'];
    }
}

/**
 * Переключить статус активности купона
 */
function toggle_coupon_status(PDO $pdo, int $coupon_id): array
{
    $coupon = get_coupon_by_id($pdo, $coupon_id);
    if (!$coupon) {
        return ['success' => false, 'error' => 'Купон не найден'];
    }

    try {
        $new_status = (int)!$coupon['is_active'];
        $stmt = $pdo->prepare('UPDATE coupons SET is_active = ? WHERE id = ?');
        $stmt->execute([$new_status, $coupon_id]);

        dev_log_security('COUPON_STATUS_CHANGED', [
            'coupon_id'   => $coupon_id,
            'coupon_code' => $coupon['code'],
            'new_status'  => $new_status,
            'admin_id'    => $_SESSION[dev_app_config()['admin_session_key']]['id'] ?? null,
        ]);

        return ['success' => true, 'new_status' => $new_status];
    } catch (Throwable $e) {
        dev_log_runtime('Coupon status toggle failed: ' . $e->getMessage());
        return ['success' => false, 'error' => 'Ошибка при изменении статуса'];
    }
}

// ═══ Обработка AJAX запросов ═══════════════════════════════════════════════════

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

// ═══ Отображение страницы ══════════════════════════════════════════════════

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
    <title>Управление купонами - Админ</title>
    <link rel="stylesheet" href="/css/bootstrap.min.css">
    <link rel="stylesheet" href="/css/admin.css">
</head>

<body>
    <div class="container">
        <?php require __DIR__ . '/_nav.php'; ?>

        <div class="d-flex align-items-center justify-content-between mb-3">
            <h3 class="mb-0">Управление купонами</h3>
            <div>
                <a href="/admin/coupons_archive.php" class="btn btn-secondary me-2">📦 Архив</a>
                <button class="btn btn-success" onclick="openCreateModal()">+ Новый купон</button>
            </div>
        </div>

        <div id="successMsg" class="alert alert-success d-none" style="display:none!important"></div>
        <div id="errorMsg" class="alert alert-danger  d-none" style="display:none!important"></div>

        <h5 class="mb-3">Активных купонов: <span class="badge bg-success"><?= $active_count ?></span></h5>

        <?php if (!empty($coupons)): ?>
            <div class="table-responsive">
            <table class="table table-bordered table-striped">
                <thead class="table-primary">
                    <tr>
                        <th>Код</th>
                        <th>Название</th>
                        <th>Скидка</th>
                        <th>Мин. сумма</th>
                        <th>Действительно до</th>
                        <th>Использовано</th>
                        <th>Статус</th>
                        <th>Действия</th>
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
                                    <span class="badge bg-success">Активный</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary">Неактивный</span>
                                <?php endif; ?>
                            </td>
                            <td style="white-space: nowrap;">
                                <button class="btn btn-sm btn-primary"
                                    onclick="openEditModal(<?= (int)$coupon['id'] ?>, <?= htmlspecialchars(json_encode($coupon), ENT_QUOTES) ?>)">
                                    Ред.
                                </button>
                                <button class="btn btn-sm btn-warning" onclick="toggleStatus(<?= (int)$coupon['id'] ?>)">
                                    <?= $coupon['is_active'] ? 'Выкл.' : 'Вкл.' ?>
                                </button>
                                <button class="btn btn-sm btn-danger"
                                    onclick="deleteCoupon(<?= (int)$coupon['id'] ?>, '<?= admin_h((string)$coupon['code']) ?>')">
                                    
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            </div><!-- /.table-responsive -->
        <?php else: ?>
            <p class="text-muted text-center py-4">Купоны не найдены</p>
        <?php endif; ?>
    </div>

    <!-- Bootstrap 3 Modal -->
    <div class="modal fade" id="couponModal" tabindex="-1" role="dialog">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    <h4 class="modal-title" id="modalTitle">Новый купон</h4>
                </div>
                <div class="modal-body">
                    <form id="couponForm">
                        <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                        <input type="hidden" id="couponId" name="coupon_id">

                        <div class="mb-3">
                            <label class="form-label fw-bold">Код купона (латиница, цифры)</label>
                            <input type="text" id="code" name="code" class="form-control" required maxlength="50">
                            <div class="text-danger small" id="code-error"></div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold">Название купона</label>
                            <input type="text" id="name" name="name" class="form-control" required maxlength="255">
                            <div class="text-danger small" id="name-error"></div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold">Тип скидки</label>
                            <select id="discountType" name="discount_type" class="form-select" onchange="updateDiscountLabel()">
                                <option value="fixed">Фиксированная сумма (р.)</option>
                                <option value="percent">Процент (%)</option>
                            </select>
                            <div class="text-danger small" id="discount_type-error"></div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold" id="discountLabel">Размер скидки (р.)</label>
                            <input type="number" id="discountValue" name="discount_value" class="form-control" step="0.01" required>
                            <div class="text-danger small" id="discount_value-error"></div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold">Минимальная сумма заказа (р.)</label>
                            <input type="number" id="minOrderSum" name="min_order_sum" class="form-control" step="0.01" value="0">
                            <div class="text-danger small" id="min_order_sum-error"></div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold">Действителен с (опционально)</label>
                            <input type="datetime-local" id="validFrom" name="valid_from" class="form-control">
                            <div class="text-danger small" id="valid_from-error"></div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold">Действителен до (опционально)</label>
                            <input type="datetime-local" id="validTo" name="valid_to" class="form-control">
                            <div class="text-danger small" id="valid_to-error"></div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold">Максимум использований (пусто = без ограничений)</label>
                            <input type="number" id="maxUsageCount" name="max_usage_count" class="form-control" min="1">
                            <div class="text-danger small" id="max_usage_count-error"></div>
                        </div>

                        <div class="mb-3 form-check">
                            <input type="checkbox" class="form-check-input" id="isActive" name="is_active" checked>
                            <label class="form-check-label" for="isActive">Активный купон</label>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отмена</button>
                    <button type="button" class="btn btn-primary" onclick="submitCouponForm()">Сохранить купон</button>
                </div>
            </div>
        </div>
    </div>

    <script src="/js/bootstrap.min.js"></script>
    <script>
        const CSRF = '<?= $csrf ?>';
        let bsModal = null;

        function getModal() {
            if (!bsModal) {
                const el = document.getElementById('couponModal');
                bsModal = new bootstrap.Modal(el);
            }
            return bsModal;
        }

        function updateDiscountLabel() {
            const type = document.getElementById('discountType').value;
            document.getElementById('discountLabel').textContent =
                type === 'percent' ? 'Размер скидки (%)' : 'Размер скидки (р.)';
        }

        function openCreateModal() {
            document.getElementById('modalTitle').textContent = 'Новый купон';
            document.getElementById('couponForm').reset();
            document.getElementById('couponId').value = '';
            clearErrors();
            getModal().show();
        }

        function openEditModal(couponId, coupon) {
            document.getElementById('modalTitle').textContent = 'Редактирование купона';
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
            getModal().show();
        }

        function clearErrors() {
            document.querySelectorAll('[id$="-error"]').forEach(el => el.textContent = '');
        }

        function showMessage(type, message) {
            const el = document.getElementById(type === 'success' ? 'successMsg' : 'errorMsg');
            el.textContent = message;
            el.style.removeProperty('display');
            el.classList.remove('d-none');
            setTimeout(() => {
                el.classList.add('d-none');
                el.style.setProperty('display', 'none', 'important');
            }, 5000);
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
                        showMessage('success', 'Купон успешно сохранен');
                        getModal().hide();
                        setTimeout(() => location.reload(), 1000);
                    } else {
                        data.errors ? displayErrors(data.errors) : showMessage('error', data.error || 'Ошибка при сохранении');
                    }
                })
                .catch(e => showMessage('error', 'Ошибка сети: ' + e.message));
        }

        function toggleStatus(couponId) {
            if (!confirm('Вы уверены?')) return;
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
                        showMessage('success', 'Статус изменен');
                        setTimeout(() => location.reload(), 1000);
                    } else showMessage('error', data.error);
                })
                .catch(e => showMessage('error', 'Ошибка: ' + e.message));
        }

        function deleteCoupon(couponId, code) {
            if (!confirm(`Удалить купон "‎${code}‎"? Это действие нельзя отменить!`)) return;
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
                        showMessage('success', 'Купон удален');
                        setTimeout(() => location.reload(), 1000);
                    } else showMessage('error', data.error);
                })
                .catch(e => showMessage('error', 'Ошибка: ' + e.message));
        }
    </script>
</body>

</html>
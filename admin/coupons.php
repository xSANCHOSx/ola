<?php

declare(strict_types=1);

// admin/coupons.php — Управління купонами в адмін-панелі
// Функціональність: CREATE, READ, UPDATE, DELETE, активація/деактивація

session_start();

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../admin/_bootstrap.php';

// ═══ Перевірка авторизації ══════════════════════════════════════════════════
if (empty($_SESSION[dev_app_config()['admin_session_key']])) {
    header('Location: /admin/login.php');
    exit;
}

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
    
    // Код: обов'язковий, 3-50 символів, латиниця+цифри
    if (empty($data['code'])) {
        $errors['code'] = 'Код купона обов\'язковий';
    } elseif (strlen($data['code']) < 3 || strlen($data['code']) > 50) {
        $errors['code'] = 'Код мусить бути від 3 до 50 символів';
    } elseif (!preg_match('/^[A-Z0-9]+$/i', $data['code'])) {
        $errors['code'] = 'Код мусить містити лише латиницю та цифри';
    }
    
    // Назва
    if (empty($data['name'])) {
        $errors['name'] = 'Назва купона обов\'язкова';
    } elseif (strlen($data['name']) > 255) {
        $errors['name'] = 'Назва не повинна перевищувати 255 символів';
    }
    
    // Тип знижки
    if (empty($data['discount_type']) || !in_array($data['discount_type'], ['fixed', 'percent'])) {
        $errors['discount_type'] = 'Тип знижки повинен бути fixed або percent';
    }
    
    // Значення знижки
    $discount_value = (float)($data['discount_value'] ?? 0);
    if ($discount_value <= 0) {
        $errors['discount_value'] = 'Значення знижки повинно бути більше 0';
    }
    if ($data['discount_type'] === 'percent' && $discount_value > 100) {
        $errors['discount_value'] = 'Відсоток не може перевищувати 100%';
    }
    
    // Мінімальна сума
    $min_sum = (float)($data['min_order_sum'] ?? 0);
    if ($min_sum < 0) {
        $errors['min_order_sum'] = 'Мінімальна сума не може бути негативною';
    }
    
    // Дати
    $valid_from = $data['valid_from'] ?? null;
    $valid_to = $data['valid_to'] ?? null;
    
    if (!empty($valid_from) && !strtotime($valid_from)) {
        $errors['valid_from'] = 'Неправильний формат дати початку';
    }
    if (!empty($valid_to) && !strtotime($valid_to)) {
        $errors['valid_to'] = 'Неправильний формат дати закінчення';
    }
    if (!empty($valid_from) && !empty($valid_to)) {
        if (strtotime($valid_from) > strtotime($valid_to)) {
            $errors['valid_from'] = 'Дата початку повинна бути раніше дати закінчення';
        }
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
    
    // Перевірити унікальність коду
    $existing = get_coupon_by_code($pdo, $data['code']);
    if ($existing) {
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
            !empty($data['valid_to']) ? $data['valid_to'] : null,
            !empty($data['max_usage_count']) ? (int)$data['max_usage_count'] : null,
            isset($data['is_active']) ? 1 : 0,
        ]);
        
        dev_log_security_event('COUPON_CREATED', [
            'coupon_code' => $data['code'],
            'admin_id' => $_SESSION[dev_app_config()['admin_session_key']] ?? null,
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
    
    // Перевірити унікальність коду (якщо змінили)
    if ($data['code'] !== $coupon['code']) {
        $existing = get_coupon_by_code($pdo, $data['code']);
        if ($existing) {
            return ['success' => false, 'errors' => ['code' => 'Купон з таким кодом вже існує']];
        }
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
            !empty($data['valid_to']) ? $data['valid_to'] : null,
            !empty($data['max_usage_count']) ? (int)$data['max_usage_count'] : null,
            isset($data['is_active']) ? 1 : 0,
            $coupon_id,
        ]);
        
        dev_log_security_event('COUPON_UPDATED', [
            'coupon_id' => $coupon_id,
            'coupon_code' => $data['code'],
            'admin_id' => $_SESSION[dev_app_config()['admin_session_key']] ?? null,
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
            'coupon_id' => $coupon_id,
            'coupon_code' => $coupon['code'],
            'admin_id' => $_SESSION[dev_app_config()['admin_session_key']] ?? null,
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
            'coupon_id' => $coupon_id,
            'coupon_code' => $coupon['code'],
            'new_status' => $new_status,
            'admin_id' => $_SESSION[dev_app_config()['admin_session_key']] ?? null,
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
    $pdo = dev_db_connection();
    $action = $_GET['action'] ?? '';
    
    // Перевірка CSRF токена
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
            $coupon_id = (int)($_POST['coupon_id'] ?? 0);
            echo json_encode(update_coupon($pdo, $coupon_id, $_POST));
            break;
            
        case 'delete':
            $coupon_id = (int)($_POST['coupon_id'] ?? 0);
            echo json_encode(delete_coupon($pdo, $coupon_id));
            break;
            
        case 'toggle_status':
            $coupon_id = (int)($_POST['coupon_id'] ?? 0);
            echo json_encode(toggle_coupon_status($pdo, $coupon_id));
            break;
            
        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Unknown action']);
    }
    exit;
}

// ═══ Відображення сторінки ═════════════════════════════════════════════════

$pdo = dev_db_connection();
$coupons = get_coupons_list($pdo);
$csrf = csrf_token();
?>
<!DOCTYPE html>
<html lang="uk">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Управління купонами - Адмін-панель</title>
    <link rel="stylesheet" href="/css/bootstrap.min.css">
    <style>
        body { font-family: Arial, sans-serif; background: #f5f5f5; padding: 20px; }
        .container { max-width: 1200px; margin: 0 auto; background: white; padding: 20px; border-radius: 5px; }
        .header { margin-bottom: 30px; border-bottom: 2px solid #007bff; padding-bottom: 10px; }
        .btn-group { margin-bottom: 20px; }
        table { width: 100%; border-collapse: collapse; }
        th { background: #007bff; color: white; padding: 10px; text-align: left; }
        td { padding: 10px; border-bottom: 1px solid #ddd; }
        tr:hover { background: #f9f9f9; }
        .status-active { color: green; font-weight: bold; }
        .status-inactive { color: red; font-weight: bold; }
        .badge { padding: 3px 8px; border-radius: 3px; font-size: 0.85em; }
        .badge-fixed { background: #28a745; color: white; }
        .badge-percent { background: #ffc107; color: black; }
        .action-btn { padding: 5px 10px; margin: 2px; font-size: 0.9em; }
        .modal { display: none; position: fixed; z-index: 1; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.4); }
        .modal.active { display: block; }
        .modal-content { background: white; margin: 10% auto; padding: 20px; border-radius: 5px; width: 500px; }
        .modal-header { font-size: 1.5em; margin-bottom: 20px; font-weight: bold; }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: bold; }
        .form-group input, .form-group select, .form-group textarea { width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 3px; }
        .error-text { color: red; font-size: 0.9em; margin-top: 3px; }
        .success-msg { background: #d4edda; color: #155724; padding: 10px; border-radius: 3px; margin-bottom: 15px; }
        .error-msg { background: #f8d7da; color: #721c24; padding: 10px; border-radius: 3px; margin-bottom: 15px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>💰 Управління купонами</h1>
            <p>Створення, редагування та управління кодами знижок</p>
        </div>
        
        <div class="btn-group">
            <button class="btn btn-success" onclick="openCreateModal()">➕ Новий купон</button>
            <a href="/admin/" class="btn btn-secondary">← Повернутися в адмін</a>
        </div>
        
        <div id="successMsg" class="success-msg" style="display:none;"></div>
        <div id="errorMsg" class="error-msg" style="display:none;"></div>
        
        <h2>Активні купони (<?= count(array_filter($coupons, fn($c) => $c['is_active'])) ?>)</h2>
        
        <?php if (!empty($coupons)): ?>
        <table>
            <thead>
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
                    <td><strong><?= htmlspecialchars($coupon['code']) ?></strong></td>
                    <td><?= htmlspecialchars($coupon['name']) ?></td>
                    <td>
                        <span class="badge <?= $coupon['discount_type'] === 'percent' ? 'badge-percent' : 'badge-fixed' ?>">
                            <?= $coupon['discount_value'] ?><?= $coupon['discount_type'] === 'percent' ? '%' : 'р.' ?>
                        </span>
                    </td>
                    <td><?= number_format((float)$coupon['min_order_sum'], 2) ?> р.</td>
                    <td><?= $coupon['valid_to'] ? date('d.m.Y', strtotime($coupon['valid_to'])) : '—' ?></td>
                    <td><?= $coupon['used_count'] ?><?= $coupon['max_usage_count'] ? '/' . $coupon['max_usage_count'] : '' ?></td>
                    <td>
                        <span class="<?= $coupon['is_active'] ? 'status-active' : 'status-inactive' ?>">
                            <?= $coupon['is_active'] ? '✓ Активний' : '✗ Неактивний' ?>
                        </span>
                    </td>
                    <td>
                        <button class="btn btn-primary action-btn" onclick="openEditModal(<?= $coupon['id'] ?>, <?= htmlspecialchars(json_encode($coupon)) ?>)">✎ Редагувати</button>
                        <button class="btn btn-warning action-btn" onclick="toggleStatus(<?= $coupon['id'] ?>, <?= $coupon['is_active'] ?>)">
                            <?= $coupon['is_active'] ? '🔴 Деактивувати' : '🟢 Активувати' ?>
                        </button>
                        <button class="btn btn-danger action-btn" onclick="deleteCoupon(<?= $coupon['id'] ?>, '<?= htmlspecialchars($coupon['code']) ?>')">🗑 Видалити</button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?>
            <p style="color: #666; text-align: center; padding: 20px;">Купони не знайдені</p>
        <?php endif; ?>
    </div>
    
    <!-- Модальне вікно для створення/редагування -->
    <div id="couponModal" class="modal">
        <div class="modal-content">
            <span onclick="closeCouponModal()" style="cursor:pointer; float:right; font-size:24px;">&times;</span>
            <div class="modal-header" id="modalTitle">Новий купон</div>
            
            <form id="couponForm">
                <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                <input type="hidden" id="couponId" name="coupon_id">
                
                <div class="form-group">
                    <label>Код купона (латиниця, цифри)</label>
                    <input type="text" id="code" name="code" required maxlength="50">
                    <div class="error-text" id="code-error"></div>
                </div>
                
                <div class="form-group">
                    <label>Назва купона</label>
                    <input type="text" id="name" name="name" required maxlength="255">
                    <div class="error-text" id="name-error"></div>
                </div>
                
                <div class="form-group">
                    <label>Тип знижки</label>
                    <select id="discountType" name="discount_type" onchange="updateDiscountLabel()">
                        <option value="fixed">Фіксована сума (руб.)</option>
                        <option value="percent">Відсоток (%)</option>
                    </select>
                    <div class="error-text" id="discount_type-error"></div>
                </div>
                
                <div class="form-group">
                    <label id="discountLabel">Величина знижки (руб.)</label>
                    <input type="number" id="discountValue" name="discount_value" step="0.01" required>
                    <div class="error-text" id="discount_value-error"></div>
                </div>
                
                <div class="form-group">
                    <label>Мінімальна сума замовлення (руб.)</label>
                    <input type="number" id="minOrderSum" name="min_order_sum" step="0.01" value="0">
                    <div class="error-text" id="min_order_sum-error"></div>
                </div>
                
                <div class="form-group">
                    <label>Дійсний з (опціонально)</label>
                    <input type="datetime-local" id="validFrom" name="valid_from">
                    <div class="error-text" id="valid_from-error"></div>
                </div>
                
                <div class="form-group">
                    <label>Дійсний до (опціонально)</label>
                    <input type="datetime-local" id="validTo" name="valid_to">
                    <div class="error-text" id="valid_to-error"></div>
                </div>
                
                <div class="form-group">
                    <label>Максимум використань (опціонально, порожньо = без обмежень)</label>
                    <input type="number" id="maxUsageCount" name="max_usage_count" min="1">
                    <div class="error-text" id="max_usage_count-error"></div>
                </div>
                
                <div class="form-group">
                    <label>
                        <input type="checkbox" id="isActive" name="is_active" checked>
                        Активний купон
                    </label>
                </div>
                
                <div style="margin-top: 20px; text-align: right;">
                    <button type="button" class="btn btn-secondary" onclick="closeCouponModal()">Скасувати</button>
                    <button type="submit" class="btn btn-primary">Зберегти купон</button>
                </div>
            </form>
        </div>
    </div>
    
    <script src="/js/jquery-3.7.1.min.js"></script>
    <script>
        const CSRF = '<?= $csrf ?>';
        
        function updateDiscountLabel() {
            const type = document.getElementById('discountType').value;
            const label = type === 'percent' ? 'Величина знижки (%)' : 'Величина знижки (руб.)';
            document.getElementById('discountLabel').textContent = label;
        }
        
        function openCreateModal() {
            document.getElementById('modalTitle').textContent = 'Новий купон';
            document.getElementById('couponForm').reset();
            document.getElementById('couponId').value = '';
            clearErrors();
            document.getElementById('couponModal').classList.add('active');
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
            
            if (coupon.valid_from) {
                document.getElementById('validFrom').value = coupon.valid_from.replace(' ', 'T');
            }
            if (coupon.valid_to) {
                document.getElementById('validTo').value = coupon.valid_to.replace(' ', 'T');
            }
            if (coupon.max_usage_count) {
                document.getElementById('maxUsageCount').value = coupon.max_usage_count;
            }
            
            updateDiscountLabel();
            clearErrors();
            document.getElementById('couponModal').classList.add('active');
        }
        
        function closeCouponModal() {
            document.getElementById('couponModal').classList.remove('active');
        }
        
        function clearErrors() {
            document.querySelectorAll('.error-text').forEach(el => el.textContent = '');
        }
        
        function showMessage(type, message) {
            const msgEl = type === 'success' ? document.getElementById('successMsg') : document.getElementById('errorMsg');
            msgEl.textContent = message;
            msgEl.style.display = 'block';
            setTimeout(() => { msgEl.style.display = 'none'; }, 5000);
        }
        
        function displayErrors(errors) {
            clearErrors();
            Object.keys(errors).forEach(field => {
                const errorEl = document.getElementById(field + '-error');
                if (errorEl) {
                    errorEl.textContent = errors[field];
                }
            });
        }
        
        document.getElementById('couponForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const couponId = document.getElementById('couponId').value;
            const action = couponId ? 'update' : 'create';
            const formData = new FormData(this);
            formData.set('csrf_token', CSRF);
            
            fetch(`?ajax=1&action=${action}`, {
                method: 'POST',
                body: formData
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    showMessage('success', 'Купон успішно збережений');
                    closeCouponModal();
                    setTimeout(() => location.reload(), 1000);
                } else {
                    if (data.errors) {
                        displayErrors(data.errors);
                    } else {
                        showMessage('error', data.error || 'Помилка при збереженні');
                    }
                }
            })
            .catch(e => showMessage('error', 'Помилка мережі: ' + e.message));
        });
        
        function toggleStatus(couponId, currentStatus) {
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
                } else {
                    showMessage('error', data.error);
                }
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
                } else {
                    showMessage('error', data.error);
                }
            })
            .catch(e => showMessage('error', 'Помилка: ' + e.message));
        }
    </script>
</body>
</html>

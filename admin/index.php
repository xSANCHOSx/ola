<?php
declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';
admin_require_auth();
$pdo = dev_db_connection();
$orders = [];
if ($pdo instanceof PDO) {
    $orders = $pdo->query('
        SELECT 
            o.id, 
            o.order_number, 
            o.customer_name_snapshot, 
            o.customer_phone_snapshot, 
            o.customer_email_snapshot, 
            o.total, 
            o.created_at, 
            (SELECT COALESCE(SUM(quantity),0) FROM order_items oi WHERE oi.order_id = o.id) AS items_count
        FROM orders o 
        ORDER BY o.id DESC 
        LIMIT 100
    ')->fetchAll();
}

function get_order_items_with_links(PDO $pdo, int $orderId): array {
    // Пробуємо знайти товар спочатку за external_id, потім за назвою
    // Використовуємо GROUP BY oi.id щоб уникнути дублювання через JOIN, якщо дані в БД некоректні
    $stmt = $pdo->prepare('
        SELECT 
            oi.name, 
            oi.quantity, 
            oi.product_external_id,
            COALESCE(p1.id, p2.id) as product_id
        FROM order_items oi 
        LEFT JOIN products p1 ON (oi.product_external_id = p1.external_id AND oi.product_external_id != "")
        LEFT JOIN products p2 ON (oi.name = p2.name AND p1.id IS NULL)
        WHERE oi.order_id = :order_id
        GROUP BY oi.id
    ');
    $stmt->execute(['order_id' => $orderId]);
    return $stmt->fetchAll();
}
?>
<!doctype html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Админка - Заказы</title>
    <link rel="stylesheet" href="/css/bootstrap.min.css">
    <style>
        .items-list { font-size: 0.85rem; color: #555; line-height: 1.4; }
        .item-row { margin-bottom: 4px; display: block; }
        .item-link { color: #007bff; text-decoration: underline; font-weight: 500; }
        .search-form { background: #f8f9fa; padding: 15px; border-radius: 5px; margin-bottom: 20px; }
        .search-form .form-group { margin-bottom: 10px; }
        .search-form label { font-weight: 600; margin-bottom: 5px; display: block; }
        .search-form input, .search-form select { width: 100%; }
        .search-tabs { display: flex; gap: 10px; margin-bottom: 15px; flex-wrap: wrap; }
        .search-tabs button { padding: 8px 16px; border: 1px solid #ddd; background: #fff; cursor: pointer; border-radius: 3px; transition: all 0.2s; }
        .search-tabs button.active { background: #007bff; color: #fff; border-color: #007bff; }
        .search-tabs button:hover { border-color: #007bff; }
        .date-range-inputs { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
        .loading-spinner { display: none; text-align: center; color: #666; }
        .no-results { text-align: center; color: #999; padding: 20px; }
        .results-info { color: #666; margin-bottom: 10px; font-size: 0.9rem; }
    </style>
</head>
<body>
<div class="container">
    <?php require __DIR__ . '/_nav.php'; ?>
    <h3>Заказы</h3>
    
    <!-- Форма пошуку -->
    <div class="search-form">
        <div class="search-tabs">
            <button class="search-tab-btn active" data-type="all">Все заказы</button>
            <button class="search-tab-btn" data-type="order_number">По номеру</button>
            <button class="search-tab-btn" data-type="customer_name">По клиенту (ФИО)</button>
            <button class="search-tab-btn" data-type="customer_phone">По телефону</button>
            <button class="search-tab-btn" data-type="customer_email">По email</button>
            <button class="search-tab-btn" data-type="date_exact">По дате</button>
            <button class="search-tab-btn" data-type="date_range">По диапазону дат</button>
        </div>
        
        <form id="searchForm" method="get">
            <!-- Пошук за номером замовлення -->
            <div id="search-order_number" class="search-input-group" style="display: none;">
                <div class="form-group">
                    <label for="orderNumber">Номер заказа:</label>
                    <input type="text" id="orderNumber" class="form-control" placeholder="Введите номер заказа">
                </div>
            </div>
            
            <!-- Пошук за ПІБ клієнта -->
            <div id="search-customer_name" class="search-input-group" style="display: none;">
                <div class="form-group">
                    <label for="customerName">ФИО клиента:</label>
                    <input type="text" id="customerName" class="form-control" placeholder="Введите ФИО клиента">
                </div>
            </div>
            
            <!-- Пошук за телефоном клієнта -->
            <div id="search-customer_phone" class="search-input-group" style="display: none;">
                <div class="form-group">
                    <label for="customerPhone">Телефон:</label>
                    <input type="text" id="customerPhone" class="form-control" placeholder="Введите номер телефона">
                </div>
            </div>
            
            <!-- Пошук за email клієнта -->
            <div id="search-customer_email" class="search-input-group" style="display: none;">
                <div class="form-group">
                    <label for="customerEmail">Email:</label>
                    <input type="email" id="customerEmail" class="form-control" placeholder="Введите email">
                </div>
            </div>
            
            <!-- Пошук за конкретною датою -->
            <div id="search-date_exact" class="search-input-group" style="display: none;">
                <div class="form-group">
                    <label for="dateExact">Дата:</label>
                    <input type="date" id="dateExact" class="form-control">
                </div>
            </div>
            
            <!-- Пошук за діапазоном дат -->
            <div id="search-date_range" class="search-input-group" style="display: none;">
                <div class="form-group">
                    <label>Диапазон дат:</label>
                    <div class="date-range-inputs">
                        <div>
                            <label for="dateFrom" style="font-weight: normal; margin-bottom: 3px;">От:</label>
                            <input type="date" id="dateFrom" class="form-control">
                        </div>
                        <div>
                            <label for="dateTo" style="font-weight: normal; margin-bottom: 3px;">До:</label>
                            <input type="date" id="dateTo" class="form-control">
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="form-group">
                <button type="button" class="btn btn-primary" id="searchBtn">Поиск</button>
                <button type="button" class="btn btn-secondary" id="resetBtn">Сброс</button>
            </div>
        </form>
        
        <div class="loading-spinner" id="loadingSpinner">
            <p>Загрузка...</p>
        </div>
        
        <div class="results-info" id="resultsInfo" style="display: none;"></div>
    </div>
    
    <!-- Таблиця замовлень -->
    <table class="table table-bordered table-striped" id="ordersTable">
        <thead>
        <tr>
            <th>#</th>
            <th>Дата</th>
            <th>Клиент</th>
            <th>Телефон</th>
            <th>Товары (кол-во)</th>
            <th>Сумма</th>
        </tr>
        </thead>
        <tbody id="ordersTableBody">
        <?php foreach ($orders as $o): ?>
            <tr>
                <td><?= admin_h((string)$o['order_number']) ?></td>
                <td><?= admin_h((string)$o['created_at']) ?></td>
                <td>
                    <?= admin_h((string)$o['customer_name_snapshot']) ?><br>
                    <small class="text-muted"><?= admin_h((string)$o['customer_email_snapshot']) ?></small>
                </td>
                <td><?= admin_h((string)$o['customer_phone_snapshot']) ?></td>
                <td>
                    <div class="items-list">
                        <?php 
                        $items = get_order_items_with_links($pdo, (int)$o['id']);
                        foreach ($items as $item): 
                        ?>
                            <span class="item-row">
                                <?php if (!empty($item['product_id'])): ?>
                                    <a href="/admin/products.php?edit=<?= (int)$item['product_id'] ?>" target="_blank" class="item-link">
                                        <?= admin_h((string)$item['name']) ?>
                                    </a>
                                <?php else: ?>
                                    <span class="text-danger"><?= admin_h((string)$item['name']) ?></span>
                                    <small class="text-muted">(ID: <?= admin_h((string)$item['product_external_id']) ?>)</small>
                                <?php endif; ?>
                                <strong>x <?= (int)$item['quantity'] ?></strong>
                            </span>
                        <?php endforeach; ?>
                    </div>
                    <small class="text-muted">Всего: <?= admin_h((string)$o['items_count']) ?></small>
                </td>
                <td><?= admin_h((string)$o['total']) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    
    <div class="no-results" id="noResults" style="display: none;">
        Результаты не найдены
    </div>
</div>

<script>
    let currentSearchType = 'all';
    let currentOrders = <?= json_encode($orders, JSON_UNESCAPED_UNICODE) ?>;
    
    // Обработчики для вкладок поиска
    document.querySelectorAll('.search-tab-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            // Удаляем активный класс со всех кнопок
            document.querySelectorAll('.search-tab-btn').forEach(b => b.classList.remove('active'));
            this.classList.add('active');
            
            // Скрываем все поля ввода
            document.querySelectorAll('.search-input-group').forEach(group => {
                group.style.display = 'none';
            });
            
            currentSearchType = this.dataset.type;
            
            // Показываем нужное поле ввода
            if (currentSearchType !== 'all') {
                const searchGroup = document.getElementById('search-' + currentSearchType);
                if (searchGroup) {
                    searchGroup.style.display = 'block';
                }
            }
            
            // Если выбран "Все заказы", показываем исходные данные
            if (currentSearchType === 'all') {
                renderOrders(currentOrders);
                document.getElementById('resultsInfo').style.display = 'none';
                document.getElementById('noResults').style.display = 'none';
            }
        });
    });
    
    // Обработчик поиска
    document.getElementById('searchBtn').addEventListener('click', function() {
        if (currentSearchType === 'all') {
            renderOrders(currentOrders);
            return;
        }
        
        performSearch();
    });
    
    // Обработчик сброса
    document.getElementById('resetBtn').addEventListener('click', function() {
        // Сбрасываем все поля
        document.getElementById('orderNumber').value = '';
        document.getElementById('customerName').value = '';
        document.getElementById('customerPhone').value = '';
        document.getElementById('customerEmail').value = '';
        document.getElementById('dateExact').value = '';
        document.getElementById('dateFrom').value = '';
        document.getElementById('dateTo').value = '';
        
        // Переходим на вкладку "Все заказы"
        document.querySelector('[data-type="all"]').click();
    });
    
    // Функция выполнения поиска
    async function performSearch() {
        const searchBtn = document.getElementById('searchBtn');
        const loadingSpinner = document.getElementById('loadingSpinner');
        const resultsInfo = document.getElementById('resultsInfo');
        const noResults = document.getElementById('noResults');
        
        let searchValue = '';
        let dateFrom = '';
        let dateTo = '';
        
        // Получаем значение в зависимости от типа поиска
        if (currentSearchType === 'order_number') {
            searchValue = document.getElementById('orderNumber').value;
        } else if (currentSearchType === 'customer_name') {
            searchValue = document.getElementById('customerName').value;
        } else if (currentSearchType === 'customer_phone') {
            searchValue = document.getElementById('customerPhone').value;
        } else if (currentSearchType === 'customer_email') {
            searchValue = document.getElementById('customerEmail').value;
        } else if (currentSearchType === 'date_exact') {
            searchValue = document.getElementById('dateExact').value;
        } else if (currentSearchType === 'date_range') {
            dateFrom = document.getElementById('dateFrom').value;
            dateTo = document.getElementById('dateTo').value;
        }
        
        if (!searchValue && currentSearchType !== 'date_range') {
            alert('Пожалуйста, введите значение для поиска');
            return;
        }
        
        if (currentSearchType === 'date_range' && !dateFrom && !dateTo) {
            alert('Пожалуйста, введите хотя бы одну дату');
            return;
        }
        
        // Показываем спиннер загрузки
        loadingSpinner.style.display = 'block';
        noResults.style.display = 'none';
        resultsInfo.style.display = 'none';
        
        try {
            const params = new URLSearchParams();
            params.append('type', currentSearchType);
            if (searchValue) params.append('value', searchValue);
            if (dateFrom) params.append('date_from', dateFrom);
            if (dateTo) params.append('date_to', dateTo);
            
            const response = await fetch('/api/search_orders.php?' + params.toString());
            
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            
            const result = await response.json();
            
            if (result.success) {
                currentOrders = result.data;
                renderOrders(result.data);
                
                if (result.data.length === 0) {
                    noResults.style.display = 'block';
                    resultsInfo.style.display = 'none';
                } else {
                    resultsInfo.textContent = `Найдено ${result.total} заказов`;
                    resultsInfo.style.display = 'block';
                    noResults.style.display = 'none';
                }
            } else {
                alert('Ошибка поиска: ' + (result.error || 'Unknown error'));
            }
        } catch (error) {
            console.error('Error:', error);
            alert('Ошибка при выполнении поиска');
        } finally {
            loadingSpinner.style.display = 'none';
        }
    }
    
    // Функция отрисовки заказов в таблицу
    function renderOrders(orders) {
        const tbody = document.getElementById('ordersTableBody');
        tbody.innerHTML = '';
        
        if (orders.length === 0) {
            document.getElementById('noResults').style.display = 'block';
            return;
        }
        
        document.getElementById('noResults').style.display = 'none';
        
        orders.forEach(order => {
            const row = document.createElement('tr');
            row.innerHTML = `
                <td>${escapeHtml(order.order_number)}</td>
                <td>${escapeHtml(order.created_at)}</td>
                <td>
                    ${escapeHtml(order.customer_name_snapshot)}<br>
                    <small class="text-muted">${escapeHtml(order.customer_email_snapshot)}</small>
                </td>
                <td>${escapeHtml(order.customer_phone_snapshot)}</td>
                <td>
                    <small class="text-muted">Всего: ${order.items_count}</small>
                </td>
                <td>${escapeHtml(order.total)}</td>
            `;
            tbody.appendChild(row);
        });
    }
    
    // Функция экранирования HTML
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
</script>
</body>
</html>

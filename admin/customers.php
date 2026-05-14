<?php

declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';
admin_require_auth();
$pdo = dev_db_connection();
$customers = [];
$orders    = [];
$customerId = (int)($_GET['id'] ?? 0);

if ($pdo instanceof PDO) {
    $customers = $pdo->query('SELECT * FROM customers ORDER BY last_order_at DESC, id DESC LIMIT 500')->fetchAll();
    if ($customerId > 0) {
        $stmt = $pdo->prepare('
            SELECT o.id, o.order_number, o.total, o.created_at
            FROM orders o
            WHERE o.customer_id = :id
            ORDER BY o.id DESC
        ');
        $stmt->execute(['id' => $customerId]);
        $orders = $stmt->fetchAll();
    }
}
// get_order_items_with_links() — визначена в _bootstrap.php
?>
<!doctype html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Админка - Клиенты</title>
    <link rel="stylesheet" href="/css/bootstrap.min.css">
    <style>
        .items-list  { font-size: 0.85rem; color: #555; line-height: 1.4; }
        .item-row    { margin-bottom: 4px; display: block; }
        .item-link   { color: #007bff; text-decoration: underline; font-weight: 500; }
        /* Search (shared style with index.php) */
        .search-form { background: #f8f9fa; padding: 15px; border-radius: 5px; margin-bottom: 20px; }
        .search-form .form-group { margin-bottom: 10px; }
        .search-form label { font-weight: 600; margin-bottom: 5px; display: block; }
        .search-form input, .search-form select { width: 100%; }
        .search-tabs { display: flex; gap: 10px; margin-bottom: 15px; flex-wrap: wrap; }
        .search-tabs button { padding: 8px 16px; border: 1px solid #ddd; background: #fff; cursor: pointer; border-radius: 3px; transition: all 0.2s; }
        .search-tabs button.active { background: #007bff; color: #fff; border-color: #007bff; }
        .search-tabs button:hover  { border-color: #007bff; }
        .loading-spinner { display: none; text-align: center; color: #666; }
        .no-results  { text-align: center; color: #999; padding: 20px; }
        .results-info { color: #666; margin-bottom: 10px; font-size: 0.9rem; }
    </style>
</head>
<body>
<div class="container">
    <?php require __DIR__ . '/_nav.php'; ?>
    <h3>Клиенты</h3>

    <!-- Форма пошуку -->
    <div class="search-form">
        <div class="search-tabs">
            <button class="search-tab-btn active" data-type="all">Все клиенты</button>
            <button class="search-tab-btn" data-type="name">По имени (ФИО)</button>
            <button class="search-tab-btn" data-type="phone">По телефону</button>
            <button class="search-tab-btn" data-type="email">По email</button>
        </div>

        <div id="search-name" class="search-input-group" style="display:none;">
            <div class="form-group">
                <label>ФИО клиента:</label>
                <input type="text" id="customerName" class="form-control" placeholder="Введите ФИО">
            </div>
        </div>
        <div id="search-phone" class="search-input-group" style="display:none;">
            <div class="form-group">
                <label>Телефон:</label>
                <input type="text" id="customerPhone" class="form-control" placeholder="Введите телефон">
            </div>
        </div>
        <div id="search-email" class="search-input-group" style="display:none;">
            <div class="form-group">
                <label>Email:</label>
                <input type="email" id="customerEmail" class="form-control" placeholder="Введите email">
            </div>
        </div>

        <div class="form-group">
            <button type="button" class="btn btn-primary" id="searchBtn">Поиск</button>
            <button type="button" class="btn btn-secondary" id="resetBtn">Сброс</button>
        </div>

        <div class="loading-spinner" id="loadingSpinner"><p>Загрузка...</p></div>
        <div class="results-info" id="resultsInfo" style="display:none;"></div>
    </div>

    <!-- Таблиця клієнтів -->
    <table class="table table-bordered table-striped" id="customersTable">
        <thead>
            <tr>
                <th>Имя</th>
                <th>Телефон</th>
                <th>Email</th>
                <th>Заказов</th>
                <th>Последний #</th>
                <th>Последний заказ</th>
                <th></th>
            </tr>
        </thead>
        <tbody id="customersTableBody">
        <?php foreach ($customers as $c): ?>
            <tr>
                <td><?= admin_h((string)$c['full_name']) ?></td>
                <td><?= admin_h((string)$c['phone']) ?></td>
                <td><?= admin_h((string)$c['email']) ?></td>
                <td><?= admin_h((string)$c['orders_count']) ?></td>
                <td><?= admin_h((string)$c['last_order_number']) ?></td>
                <td><?= admin_h((string)$c['last_order_at']) ?></td>
                <td><a href="/admin/customers.php?id=<?= (int)$c['id'] ?>">История</a></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>

    <div class="no-results" id="noResults" style="display:none;">Результаты не найдены</div>

    <?php if ($orders): ?>
        <hr>
        <h4>История заказов клиента</h4>
        <table class="table table-bordered">
            <thead>
                <tr>
                    <th>Номер</th>
                    <th>Дата</th>
                    <th>Товары (кол-во)</th>
                    <th>Сумма</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($orders as $o): ?>
                    <tr>
                        <td><?= admin_h((string)$o['order_number']) ?></td>
                        <td><?= admin_h((string)$o['created_at']) ?></td>
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
                        </td>
                        <td><?= admin_h((string)$o['total']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<script>
(function () {
    var currentType = 'all';
    var allCustomers = <?= json_encode($customers, JSON_UNESCAPED_UNICODE) ?>;

    var inputMap = { name: 'customerName', phone: 'customerPhone', email: 'customerEmail' };

    document.querySelectorAll('.search-tab-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            document.querySelectorAll('.search-tab-btn').forEach(function (b) { b.classList.remove('active'); });
            this.classList.add('active');
            document.querySelectorAll('.search-input-group').forEach(function (g) { g.style.display = 'none'; });
            currentType = this.dataset.type;
            if (currentType !== 'all') {
                var g = document.getElementById('search-' + currentType);
                if (g) g.style.display = 'block';
            } else {
                renderCustomers(allCustomers);
                document.getElementById('resultsInfo').style.display = 'none';
                document.getElementById('noResults').style.display  = 'none';
            }
        });
    });

    document.getElementById('searchBtn').addEventListener('click', function () {
        if (currentType === 'all') { renderCustomers(allCustomers); return; }
        performSearch();
    });

    document.getElementById('resetBtn').addEventListener('click', function () {
        Object.values(inputMap).forEach(function (id) {
            var el = document.getElementById(id);
            if (el) el.value = '';
        });
        document.getElementById('resultsInfo').style.display = 'none';
        document.getElementById('noResults').style.display  = 'none';
        document.getElementById('loadingSpinner').style.display = 'none';
        document.querySelector('[data-type="all"]').click();
    });

    async function performSearch() {
        var inputId = inputMap[currentType];
        var value = inputId ? (document.getElementById(inputId).value || '') : '';

        if (!value) { alert('Пожалуйста, введите значение для поиска'); return; }

        var spinner   = document.getElementById('loadingSpinner');
        var resultsInfo = document.getElementById('resultsInfo');
        var noResults = document.getElementById('noResults');

        spinner.style.display   = 'block';
        noResults.style.display = 'none';
        resultsInfo.style.display = 'none';

        try {
            var params = new URLSearchParams({ type: currentType, value: value });
            var resp = await fetch('/api/search_customers.php?' + params.toString());
            if (!resp.ok) throw new Error('Network error');
            var result = await resp.json();

            if (result.success) {
                allCustomers = result.data;
                renderCustomers(result.data);
                if (result.data.length === 0) {
                    noResults.style.display = 'block';
                } else {
                    resultsInfo.textContent = 'Найдено ' + result.total + ' клиентов';
                    resultsInfo.style.display = 'block';
                }
            } else {
                alert('Ошибка поиска: ' + (result.error || 'Unknown error'));
            }
        } catch (e) {
            console.error(e);
            alert('Ошибка при выполнении поиска');
        } finally {
            spinner.style.display = 'none';
        }
    }

    function renderCustomers(customers) {
        var tbody = document.getElementById('customersTableBody');
        tbody.innerHTML = '';
        document.getElementById('noResults').style.display = 'none';
        if (customers.length === 0) {
            document.getElementById('noResults').style.display = 'block';
            return;
        }
        customers.forEach(function (c) {
            var tr = document.createElement('tr');
            tr.innerHTML =
                '<td>' + esc(c.full_name)          + '</td>' +
                '<td>' + esc(c.phone)               + '</td>' +
                '<td>' + esc(c.email)               + '</td>' +
                '<td>' + esc(String(c.orders_count)) + '</td>' +
                '<td>' + esc(c.last_order_number)   + '</td>' +
                '<td>' + esc(c.last_order_at)        + '</td>' +
                '<td><a href="/admin/customers.php?id=' + parseInt(c.id) + '">История</a></td>';
            tbody.appendChild(tr);
        });
    }

    function esc(text) {
        var d = document.createElement('div');
        d.textContent = String(text ?? '');
        return d.innerHTML;
    }
})();
</script>
</body>
</html>

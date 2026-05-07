# 🏗️ Архітектурний огляд проєкту `ola`

> **Роль:** Senior-інженер, перший день у проєкті.
> **Ціль:** зрозуміти архітектуру, виявити проблеми, скласти план дій.
> **Метод:** аналіз усіх файлів, cross-reference між шарами.

---

## Зміст

1. [Загальна архітектура](#1-загальна-архітектура)
2. [Що зроблено добре](#2-що-зроблено-добре)
3. [Критичні проблеми 🔴](#3-критичні-проблеми-)
4. [Архітектурні проблеми 🟠](#4-архітектурні-проблеми-)
5. [Якість коду 🟡](#5-якість-коду-)
6. [Стратегія рефакторингу](#6-стратегія-рефакторингу)
7. [План дій по спринтах](#7-план-дій-по-спринтах)
8. [Покращений код](#8-покращений-код)

---

## 1. Загальна архітектура

```
ola/
├── [PHP pages]          index.php, delivery.php, info.php, ...   ← без роутингу
├── config/
│   ├── app.php          ← конфіг (rate limit, paths)
│   └── db.php           ← PDO + всі utility-функції (validate, csrf, log...)
├── data/
│   └── products.php     ← DB-запит + UTM-обробка
├── admin/               ← мінімальна адмінка (замовлення, клієнти, продукти)
├── amo/                 ← AmoCRM інтеграція
├── templates/           ← PHP-темплейти (header, footer, product, order_form)
├── js/
│   ├── cart.js          ← CartStore + CartUI + CheckoutService + ModernCart
│   ├── main.js          ← sticky nav, analytics, timer, cookies
│   └── main_legacy.js   ← 727 рядків старого коду ❌
├── css/                 ← bootstrap, styles.css, wicart.css, ...
└── sendmail.php         ← ~300 рядків: валідація + DB + email + CRM (God-функція)
```

**Стек:**
- PHP 8+ (declare strict_types) · jQuery 3.7 · Bootstrap 3 · MariaDB/SQLite
- Без фреймворку, без Composer, без npm build-процесу
- Деплой: пряме завантаження файлів на сервер

**Потік замовлення:**
```
[JS localStorage] → [sendmail.php] → [DB transaction] → [mail()] → [AMO CRM] → [Bitrix CRM]
                         ↓                                              ↓
                   [order_sequence]                             [amo/order.php]
                   [customers upsert]
                   [order_items insert]
```

---

## 2. Що зроблено добре

| ✅ | Деталь |
|----|--------|
| CSRF-токен | `hash_equals()` захист у sendmail.php |
| Prepared statements | Скрізь у PHP, SQL-ін'єкції закриті |
| Idempotency key | Повторні POST-и ігноруються |
| DB транзакція | `BEGIN/COMMIT/ROLLBACK` навколо order insert |
| CartStore клас | Чисте розділення стану і UI |
| Input validation | `validate_phone`, `validate_email` перед збереженням |
| Schema з індексами | `idx_customers_phone_normalized`, `idx_orders_customer_id` |
| Fallback counter | Якщо БД недоступна — файл `counter.txt` |
| Rate limiting | Існує (але file-based — див. проблеми) |

---

## 3. Критичні проблеми 🔴

### 3.1 Пароль БД у репозиторії

**Файл:** `config/db.php`, рядки 4–7

```php
// ЯК Є — ПРОБЛЕМА:
$dbPass = getenv('DB_PASS') ?: 'dI3wW1tT3d';
//                              ^^^^^^^^^^^^
//                    Пароль у відкритому вигляді у git-history
```

**Ризик:** будь-хто з доступом до репо (або хто знайшов на GitHub) має пароль до продакшн-БД.

**Виправлення:**
```php
// ЯК МАЄ БУТИ:
$dbPass = getenv('DB_PASS');
if ($dbPass === false || $dbPass === '') {
    if (php_sapi_name() === 'cli' || getenv('APP_ENV') === 'development') {
        $dbPass = 'dev_only_password'; // лише для локальної розробки
    } else {
        throw new RuntimeException('DB_PASS env variable is required');
    }
}
```

**Дія зараз:** змінити пароль на сервері, додати `config/db.php` до `.gitignore` або використовувати `.env`.

---

### 3.2 Купон валідується лише на клієнті(Купон отрібно переробити під задавання через адмінку, як окрема сторінка)

**Файл:** `js/cart.js`, рядки 6–7

```javascript
// ВЕСЬ КУПОН — У ВІДКРИТОМУ JS:
const COUPON_CODE = 'OLA5600'
const COUPON_DISCOUNT = 5600   // ← сума знижки видна у браузері
```

**Файл:** `sendmail.php` — купон **зберігається** у БД, але знижка **не перераховується**:

```php
// sendmail.php рядок ~145
$totalSum += ((float)($item['price'] ?? 0) * (int)($item['num'] ?? 0));
// ↑ totalSum береться з клієнтських даних, купон не відраховується
```

**Ризик:** будь-хто може відкрити DevTools і отримати будь-яку знижку — змінити `COUPON_DISCOUNT` або передати довільний `coupon` у POST.

**Виправлення у sendmail.php:**
```php
// Додати після розрахунку $totalSum:
$VALID_COUPONS = ['OLA5600' => 5600]; // або виносимо у config/app.php
if (!empty($payload['coupon']) && isset($VALID_COUPONS[$payload['coupon']])) {
    $totalSum = max(0, $totalSum - $VALID_COUPONS[$payload['coupon']]);
}
```

---

### 3.3 Ціни товарів не верифікуються сервером

**Файл:** `sendmail.php`, рядок ~155

```php
// ЯК Є — ціна береться з клієнта:
$totalSum += ((float)($item['price'] ?? 0) * (int)($item['num'] ?? 0));
//                           ^^^^^^^^^^^
//            клієнт передає будь-яку ціну, сервер вірить їй
```

**Ризик:** POST-запит з `price=0.01` — замовлення пройде, email надійде, менеджер обробить.

**Виправлення:**
```php
// sendmail.php — верифікувати ціни з БД:
$productIds = array_column($orderResult, 'id');
$placeholders = implode(',', array_fill(0, count($productIds), '?'));
$stmt = $pdo->prepare("SELECT external_id, price FROM products WHERE external_id IN ($placeholders)");
$stmt->execute($productIds);
$dbPrices = array_column($stmt->fetchAll(), 'price', 'external_id');

$totalSum = 0.0;
foreach ($orderResult as $item) {
    $pid = (string)($item['id'] ?? '');
    $serverPrice = isset($dbPrices[$pid]) ? (float)$dbPrices[$pid] : null;
    if ($serverPrice === null) {
        // товар не знайдено в БД — відхилити замовлення
        http_response_code(400);
        echo json_encode(['error' => 'Invalid product: ' . $pid]);
        exit;
    }
    $totalSum += $serverPrice * (int)($item['num'] ?? 0);
}
```

---

### 3.4 `setup.php` доступний без авторизації(Видалити після встановлення)

**Файл:** `setup.php`, рядок 1

```php
<?php
require_once __DIR__ . '/config/db.php';
$pdo->exec($schema); // ← виконує schema.sql — DROP/CREATE таблиць
```

**Ризик:** будь-хто може зайти на `https://site.ru/setup.php` і переконати сервер скинути або пересоздати таблиці.

**Виправлення:**
```php
// setup.php — додати на самий початок:
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    echo 'Forbidden. Run from CLI only.';
    exit;
}
// або видалити файл з сервера після першого запуску
```

---

### 3.5 Rate limiting має race condition

**Файл:** `config/db.php`, рядок 139

```php
$cache_file = $dir . '/ratelimit_' . md5($key) . '.json';
// ...
if (count($data['timestamps']) >= $limit) { return false; }
$data['timestamps'][] = $now;
@file_put_contents($cache_file, json_encode($data)); // ← немає file lock!
```

**Ризик:** при паралельних запитах (burst) обидва читають файл одночасно, обидва бачать 0 записів, обидва проходять — rate limit обходиться.

**Виправлення:**
```php
function check_rate_limit(string $key, int $limit, int $window): bool {
    $cache_file = /* ... */;
    $fp = fopen($cache_file, 'c+');
    if (!$fp) return true; // якщо файл недоступний — пропускаємо
    flock($fp, LOCK_EX);   // ← ексклюзивне блокування
    $data = json_decode(stream_get_contents($fp), true) ?? [];
    // ... логіка ...
    ftruncate($fp, 0);
    rewind($fp);
    fwrite($fp, json_encode($data));
    flock($fp, LOCK_UN);
    fclose($fp);
    return $allowed;
}
```

---

## 4. Архітектурні проблеми 🟠

### 4.1 `sendmail.php` — God Function (300+ рядків, 7 відповідальностей)

Один файл робить усе:
1. Парсинг і валідація POST-даних
2. Генерація номеру замовлення (з fallback)
3. Upsert клієнта в БД
4. Insert замовлення + order_items
5. Побудова HTML email-шаблону
6. Відправка email через `mail()`
7. Виклик AMO CRM + Bitrix CRM

**Виправлення — розбити на сервіси:**
```
src/
├── Service/
│   ├── OrderService.php       ← validatePayload, saveOrder
│   ├── EmailService.php       ← buildTemplate, send
│   └── CrmService.php         ← sendToCrm (AMO + Bitrix)
└── sendmail.php               ← тонкий контролер: ~30 рядків
```

---

### 4.2 `<head>` дублюється на кожній сторінці

Наступний блок скопійований **мінімум у 5 файлах** (`index.php`, `single_product_template.php`, `success.php`, `404.php`, `delivery.php`, `info.php`...):

```html
<!-- Yandex.Metrika -->
<!-- Google gtag.js  -->
<!-- Google Fonts    -->
<!-- Bootstrap CSS   -->
<!-- Font Awesome    -->
<!-- animate.css     -->
<!-- styles.css      -->
<!-- wicart.css      -->
```

**Виправлення — виділити `templates/head.php`:**
```php
// templates/head.php
<head>
    <meta charset="UTF-8">
    <title><?= $pageTitle ?? 'Olaplex' ?></title>
    <!-- всі link і script тут -->
</head>

// Всі сторінки:
<?php $pageTitle = 'Доставка'; require 'templates/head.php'; ?>
```

---

### 4.3 Global `$pdo` — antipattern

**Файл:** `config/db.php`

```php
// Глобальна змінна:
$pdo = new PDO($dsn, $dbUser, $dbPass, $pdoOptions);

// Доступ через global:
function dev_db_connection(): ?PDO {
    global $pdo;  // ← antipattern
    return $pdo instanceof PDO ? $pdo : null;
}
```

**Проблеми:** важко тестувати, важко підмінити на mock, приховане coupling.

**Виправлення — Singleton або DI:**
```php
// config/db.php
function dev_db_connection(): PDO {
    static $instance = null;
    if ($instance === null) {
        $instance = new PDO($dsn, $user, $pass, $opts);
    }
    return $instance;
}
// Глобальна $pdo більше не потрібна
```

---

### 4.4 `data/products.php` виконується 4 рази

Файл `require_once`-ується у:
- `index.php`
- `templates/footer.php`
- `templates/slider_in_card.php`
- `templates/single_product_template.php`

Кожен `require_once` = нова змінна `$products`. Якщо footer і slider завантажені разом з index — DB-запит виконається **тричі** на одну сторінку.

**Виправлення — кешувати результат:**
```php
// data/products.php
function get_products(): array {
    static $cache = null;
    if ($cache !== null) return $cache;
    $pdo = dev_db_connection();
    // ... query ...
    $cache = $results;
    return $cache;
}
$products = get_products(); // backward compat
```

---

### 4.5 Дві CRM-системи з неузгодженим іменуванням

- `rest.php` містить `dev_send_bitrix_lead()` — назва натякає на Bitrix
- `amo/order.php` — AmoCRM
- Обидва викликаються з `sendmail.php`
- `dev_send_bitrix_lead` використовує `fsockopen` замість cURL

**Проблема:** якщо одна CRM відповідає повільно (>30с таймаут), sendmail.php зависне.

**Виправлення — async або хоча б короткий timeout + firewall:**
```php
// Якщо Bitrix більше не використовується — видалити rest.php
// Якщо потрібен — замінити fsockopen на curl з timeout:
$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => "https://{$crm['host']}{$crm['path']}",
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => $postData,
    CURLOPT_TIMEOUT => 5,  // не більше 5 секунд
    CURLOPT_RETURNTRANSFER => true,
]);
```

---

### 4.6 `cart.js` → `clearBasket()` хардкодить `#btable`

```javascript
clearBasket() {
    this.store.clear()
    this.ui.updateWidgets(this.widgetSelector)
    $('#btable').html('')  // ← прямий DOM-доступ поза CartUI
}
```

Логіка очищення DOM витекла з `CartUI` у `ModernCart`. При рефакторингу UI (таблиця → div) — зламається.

---

## 5. Якість коду 🟡

### 5.1 Змішування мов у одному файлі треба тільки Російська

`js/cart.js` містить одночасно:
```javascript
// Українська:
'Введіть код купону'
'Регіони: уточнюйте у оператора'
'Кошик порожній'

// Російська:
'<span>руб.</span>'
'Москва: +250 руб.'
'Сума: ... руб.'
```

`sendmail.php` — email-шаблон повністю російською, але сайт орієнтований на Україну.

**Виправлення:** виділити всі рядки у константи/конфіг і вибрати одну мову.

---

### 5.2 Таймер акції з минулою датою

**Файл:** `data/products.php`

```php
function getDiscountTimer($uniqueId) {
    $targetDate = strtotime('2025-05-01 00:00:00'); // ← ця дата вже минула!
    $currentTime = time();
    // ...
}
```

Таймер вже відрахував до нуля і скидається на +15 днів у JS. Виглядає як вічний "таймер зі знижкою" — це маніпуляція користувачем.

---

### 5.3 `wicart.css` — відсутній cache busting

```html
<!-- index.php -->
<link rel="stylesheet" href="css/styles.css?v=<?php echo date('Ymd', filemtime('css/styles.css')); ?>">
<link rel="stylesheet" href="css/wicart.css">  <!-- ← без версії! -->
```

Після деплою змін у `wicart.css` браузер може показувати старий стиль ще дні.

---

### 5.4 Мертві файли у репозиторії(видалити)

| Файл | Статус |
|------|--------|
| `js/main_legacy.js` | 727 рядків, не підключається ніде |
| `templates/product_template_even.php2` | `.php2` — свідомо вимкнено |
| `templates/product_template_odd.php2` | `.php2` — свідомо вимкнено |
| `images/ID010_old.png` | Стара версія картинки |
| `images/ID008_min2.png2` | `.png2` — пошкоджена назва |
| `images/*.DS_Store` | macOS системний файл |
| `db_admin_auth_plan_*.md` | Плановий MD у корені проєкту |
| `dev_cart_replacement_plan_*.md` | Плановий MD у корені проєкту |

---

### 5.5 Два окремих `#basketwidjet` у header

```html
<!-- Десктоп -->
<span id="basketwidjet">0</span>

<!-- Мобайл -->
<span id="basketwidjet2"></span>
```

`updateWidgets()` у `cart.js` хардкодить другий:
```javascript
updateWidgets(widgetSelector) {
    $(widgetSelector).html(txt)       // #basketwidjet
    $('#basketwidjet2').html(txt)     // ← hardcoded!
}
```

**Виправлення:** використати спільний клас `.cart-widget-count` замість двох `id`.

---

### 5.6 `order_form.php` — `<style>` та `<script>` всередині темплейту

```html
<!-- templates/order_form.php — внизу файлу -->
<script>
    document.addEventListener('DOMContentLoaded', function() { ... });
</script>
<style>
    .contact-method { margin: 15px 0; }
</style>
```

Стилі і скрипти мають бути у відповідних файлах.

---

### 5.7 Відсутня пагінація у адмінці

```php
// admin/index.php
$orders = $pdo->query('SELECT ... FROM orders ... ORDER BY id DESC LIMIT 100')
//                                                                 ^^^^^^^^^
//                При 500+ замовленнях — сторінка завантажується повільно
```

---

## 6. Стратегія рефакторингу

### Принципи

1. **Не ламати те, що працює.** Всі зміни — incremental, з тестуванням.
2. **Security first.** Критичні проблеми — перший спринт.
3. **Не вводити новий стек.** Немає Composer, немає npm — залишаємо PHP + jQuery.
4. **Backward compatibility.** Зовнішній API (`cart.addToCart`, `sendmail.php`) — не змінюємо сигнатури.

### Пріоритизація

```
🔴 НЕГАЙНО (до наступного деплою)
   └── 3.1 Пароль у репо → змінити + env-only
   └── 3.4 setup.php → закрити від HTTP

🔴 Цього тижня
   └── 3.2 Купон → сервер-сайд перевірка
   └── 3.3 Ціни → верифікація з БД

🟠 Наступний спринт
   └── 4.4 products.php кешування
   └── 4.3 global $pdo → static singleton
   └── 5.3 cache busting для wicart.css

🟡 Рефакторинг (поступово)
   └── 4.1 sendmail.php → сервіси
   └── 4.2 head.php шаблон
   └── 5.1 уніфікація мови
   └── 5.5 .cart-widget-count клас

🟢 Cleanup (в будь-який момент)
   └── Видалити мертві файли
   └── Перенести <style>/<script> з order_form.php
   └── Пагінація адмінки
```

---

## 7. План дій по спринтах

### Спринт 0 — Security Hotfix (1–2 години)

**Задача 1: Змінити пароль БД та закрити config**

```bash
# 1. Змінити пароль на сервері (через хостинг-панель)
# 2. Встановити новий пароль у змінну середовища
# 3. config/db.php — прибрати fallback-пароль
# 4. Додати config/db.php у .gitignore
echo "config/db.php" >> .gitignore
echo "config/local.php" >> .gitignore
```

**Задача 2: Захистити setup.php**

```php
// setup.php — перший рядок:
if (php_sapi_name() !== 'cli') { http_response_code(403); exit; }
```

---

### Спринт 1 — Серверна валідація (4–6 годин)

**Задача 3: Верифікація цін у sendmail.php**

- Завантажити ціни з `products` таблиці по `external_id`
- Розрахувати `$totalSum` із серверних цін
- Якщо id не знайдено — повернути 400

**Задача 4: Валідація купону на сервері**

- Перенести `COUPON_CODE` і `COUPON_DISCOUNT` у `config/app.php`
- Верифікувати і застосовувати знижку в `sendmail.php`
- З клієнта приймати лише `coupon_code`, не `discount_amount`

---

### Спринт 2 — Усунення дублювання (4–6 годин)

**Задача 5: `templates/head.php`**

Виділити спільний `<head>` у файл, підключати у всіх сторінках.

**Задача 6: Cache busting для wicart.css**

```php
// В усіх файлах:
<link rel="stylesheet" href="css/wicart.css?v=<?= filemtime('css/wicart.css') ?>">
```

**Задача 7: Уніфікація `cart-widget-count`**

В `header.php` замінити два `id` на спільний клас:
```html
<span class="cart-widget-count">0</span>
```
В `cart.js` оновити `updateWidgets()`:
```javascript
updateWidgets() {
    const count = this.store.totalItems()
    $('.cart-widget-count').text(count > 0 ? `(${count})` : '(0)')
}
```

---

### Спринт 3 — Рефакторинг коду (8–12 годин)

**Задача 8: Кешування `data/products.php`**

Обернути DB-запит у `static $cache`.

**Задача 9: Статичний singleton замість global $pdo**

Прибрати `global $pdo` з `dev_db_connection()`.

**Задача 10: `clearBasket()` — делегувати DOM у CartUI**

```javascript
// cart.js
class CartUI {
    clearView() {
        $('#minicart-items-list').html('') // або #btable
    }
}
class ModernCart {
    clearBasket() {
        this.store.clear()
        this.ui.clearView()       // ← через UI
        this.ui.updateWidgets()
    }
}
```

**Задача 11: Перенести `<style>/<script>` з `order_form.php`**

CSS → `wicart.css`, JS → `cart.js` або `main.js`.

---

### Спринт 4 — Cleanup (2–3 години)

**Задача 12: Видалити мертві файли**

```bash
rm js/main_legacy.js
rm templates/product_template_even.php2
rm templates/product_template_odd.php2
rm images/ID010_old.png
rm images/ID008_min2.png2
rm images/.DS_Store
rm db_admin_auth_plan_*.md
rm dev_cart_replacement_plan_*.md
```

**Задача 13: Пагінація адмінки**

Замінити `LIMIT 100` на `LIMIT 50 OFFSET ?` + кнопки пагінації.

**Задача 14: Виправити таймер акції**

Або прибрати hardcoded дату, або прибрати функцію якщо акції немає.

---

## 8. Покращений код

### 8.1 `config/db.php` — безпечний PDO singleton

```php
<?php
declare(strict_types=1);

function dev_db_connection(): PDO {
    static $instance = null;
    if ($instance !== null) return $instance;

    $host = getenv('DB_HOST') ?: '';
    $name = getenv('DB_NAME') ?: '';
    $user = getenv('DB_USER') ?: '';
    $pass = getenv('DB_PASS') ?: '';

    // Дозволити SQLite лише у явному dev-режимі
    if ($host === '' && getenv('APP_ENV') === 'development'
        && file_exists(__DIR__ . '/../database/dev_shop.sqlite')) {
        $dsn  = 'sqlite:' . __DIR__ . '/../database/dev_shop.sqlite';
        $user = null;
        $pass = null;
    } elseif ($host === '') {
        throw new RuntimeException('DB_HOST env variable is required');
    } else {
        $dsn = "mysql:host=$host;dbname=$name;charset=utf8mb4";
    }

    $instance = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
    return $instance;
}
```

---

### 8.2 `config/app.php` — купони перенесено у конфіг

```php
<?php
declare(strict_types=1);

return [
    'db_enabled'              => true,
    'fallback_counter_file'   => __DIR__ . '/../counter.txt',
    'runtime_log'             => __DIR__ . '/../log/runtime.log',
    'security_log'            => __DIR__ . '/../log/security.log',
    'admin_session_key'       => 'dev_admin_auth',
    'csrf_token_key'          => 'csrf_token',
    'rate_limit_window'       => 60,
    'rate_limit_max_requests' => 5,

    // ↓ НОВЕ: купони керуються із сервера
    'coupons' => [
        'OLA5600' => ['discount' => 5600, 'type' => 'fixed'],
    ],
];
```

---

### 8.3 `sendmail.php` — верифікація цін та купону

```php
// ═══ Верифікація цін з БД ═══════════════════════════════════════════════════
$totalSum = 0.0;
if ($pdo instanceof PDO && !empty($orderResult)) {
    $productIds  = array_unique(array_column($orderResult, 'id'));
    $placeholders = implode(',', array_fill(0, count($productIds), '?'));
    $stmt = $pdo->prepare(
        "SELECT external_id, price FROM products WHERE external_id IN ($placeholders)"
    );
    $stmt->execute(array_values($productIds));
    $dbPrices = array_column($stmt->fetchAll(), 'price', 'external_id');

    foreach ($orderResult as $item) {
        $pid = (string)($item['id'] ?? '');
        if (!isset($dbPrices[$pid])) {
            log_security_event('UNKNOWN_PRODUCT', ['id' => $pid]);
            http_response_code(400);
            echo json_encode(['error' => 'Unknown product: ' . $pid]);
            exit;
        }
        $totalSum += (float)$dbPrices[$pid] * (int)($item['num'] ?? 0);
    }
} else {
    // Fallback якщо БД недоступна
    foreach ($orderResult as $item) {
        $totalSum += ((float)($item['price'] ?? 0) * (int)($item['num'] ?? 0));
    }
}

// ═══ Верифікація купону на сервері ═══════════════════════════════════════════
$cfg     = dev_app_config();
$coupons = $cfg['coupons'] ?? [];
$couponCode = $payload['coupon'] ?? '';
if ($couponCode !== '' && isset($coupons[$couponCode])) {
    $discount = $coupons[$couponCode]['discount'];
    $totalSum = max(0, $totalSum - $discount);
}
```

---

### 8.4 `js/cart.js` — виправлений `updateWidgets` + `clearBasket`

```javascript
// CartUI:
clearView() {
    const $list = $('#minicart-items-list')
    if ($list.length) $list.html('')
    else $('#btable').html('') // backward compat
}

updateWidgets() {
    const count  = this.store.totalItems()
    const txt    = count > 0 ? `(${count})` : '(0)'
    // Один клас замість двох hardcoded id
    $('.cart-widget-count').html(txt)
    $('.minicart-badge').text(count > 0 ? count : '0')
}

// ModernCart:
clearBasket() {
    this.store.clear()
    this.ui.clearView()
    this.ui.updateWidgets()
}
```

---

### 8.5 `templates/head.php` — спільний `<head>`

```php
<?php
// templates/head.php
// Змінні: $pageTitle, $pageDescription, $extraCss
$pageTitle       ??= 'Olaplex — косметика для волосся';
$pageDescription ??= 'Засоби для волосся Olaplex для домашнього використання.';
$cssBust = fn(string $f) => filemtime($f) ?: 0;
?>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?></title>
    <meta name="description" content="<?= htmlspecialchars($pageDescription) ?>">

    <!-- Fonts -->
    <link href="https://fonts.googleapis.com/css?family=Open+Sans:400,700&subset=cyrillic-ext" rel="stylesheet">
    <!-- Libs -->
    <link rel="stylesheet" href="css/bootstrap.min.css">
    <link rel="stylesheet" href="css/animate.css">
    <link rel="stylesheet" href="css/flexslider.css">
    <!-- App CSS -->
    <link rel="stylesheet" href="css/styles.css?v=<?= $cssBust('css/styles.css') ?>">
    <link rel="stylesheet" href="css/wicart.css?v=<?= $cssBust('css/wicart.css') ?>">
    <?php if (!empty($extraCss)) echo $extraCss; ?>
    <!-- Analytics -->
    <?php require __DIR__ . '/analytics.php'; ?>
</head>

<!-- Використання: -->
<?php
$pageTitle = 'Доставка — Olaplex';
require 'templates/head.php';
?>
```

---

### 8.6 `data/products.php` — кешований запит

```php
<?php
declare(strict_types=1);

function get_products(): array {
    static $cache = null;
    if ($cache !== null) return $cache;

    $pdo = dev_db_connection();
    try {
        $stmt = $pdo->query(
            'SELECT external_id, cat_number, name, old_price, price, image,
                    link, short_desc, `desc`, full_desc, in_stock, status,
                    seo_title, seo_description
               FROM products
              WHERE status = "active"
              ORDER BY id ASC'
        );
        $cache = array_map(fn($row) => [
            'id'              => (string)$row['external_id'],
            'cat_number'      => (string)($row['cat_number'] ?? ''),
            'name'            => (string)$row['name'],
            'old_price'       => (float)($row['old_price'] ?? 0),
            'price'           => (float)$row['price'],
            'image'           => (string)($row['image'] ?? ''),
            'link'            => (string)$row['link'],
            'short_desc'      => (string)($row['short_desc'] ?? ''),
            'desc'            => (string)($row['desc'] ?? ''),
            'full_desc'       => (string)($row['full_desc'] ?? ''),
            'in_stock'        => (bool)$row['in_stock'],
            'status'          => $row['status'] ? (string)$row['status'] : null,
            'seo_title'       => (string)($row['seo_title'] ?? ''),
            'seo_description' => (string)($row['seo_description'] ?? ''),
        ], $stmt->fetchAll());
    } catch (Throwable $e) {
        dev_log_runtime('Products load failed: ' . $e->getMessage());
        $cache = [];
    }
    return $cache;
}

// Backward compatibility — всі require_once отримують $products
$products = get_products();
```

---

## Підсумок

| Категорія | Проблем | Пріоритет |
|-----------|---------|-----------|
| 🔴 Критичні (security) | 5 | Негайно / цей тиждень |
| 🟠 Архітектурні | 6 | Наступний спринт |
| 🟡 Якість коду | 7 | Поступово |
| **Разом** | **18** | |

**Найважливіші 3 кроки прямо зараз:**
1. Змінити пароль БД на сервері та видалити його з коду
2. Закрити `setup.php` від HTTP
3. Додати серверну верифікацію цін і купону у `sendmail.php`

Решта — покращення стабільності та maintainability, які не горять, але накопичують технічний борг.

---

## 9. Додаткові знахідки після глибокого аналізу

### 9.1 `robots.txt` блокує весь сайт для пошуковиків(треба зробити робочу копію на час розробки)

**Файл:** `robots.txt`

```
User-agent: *
Disallow: /        ← ВЕСЬ САЙТ закритий від індексації!
```

**Ризик:** Google і Яндекс не індексують жодної сторінки. Весь SEO-трафік втрачено.

**Виправлення:**
```
User-agent: *
Disallow: /admin/
Disallow: /config/
Disallow: /log/
Disallow: /database/
Disallow: /sendmail.php
Disallow: /setup.php

Sitemap: https://olaplex-shop.ru/sitemap.xml
```

---

### 9.2 Адмін-панель не має CSRF-захисту на POST-запитах

**Файл:** `admin/products.php`

```php
// POST без жодної CSRF-перевірки:
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $pdo instanceof PDO) {
    // $data = [...]; → одразу UPDATE/INSERT
```

**Ризик:** CSRF-атака може змінити ціну будь-якого товару через підроблений form-запит від авторизованого адміна (відкрив посилання з листа).

**Виправлення — додати в кожну POST-форму:**
```php
// admin/products.php — у формі:
<input type="hidden" name="csrf_token"
       value="<?= csrf_token() ?>">

// admin/products.php — на початку POST-обробки:
if (!validate_csrf_token()) {
    http_response_code(403); exit('CSRF check failed');
}
```

---

### 9.3 Адмін-логін не має захисту від brute force

**Файл:** `admin/login.php`

```php
// Просто SELECT + password_verify — без ліміту спроб:
if ($user && password_verify($password, $user['password_hash'])) {
```

**Ризик:** необмежена кількість спроб підбору пароля.

**Виправлення:**
```php
// admin/login.php — перед перевіркою пароля:
$loginKey = 'admin_login_' . ($_SERVER['REMOTE_ADDR'] ?? 'x');
if (!check_rate_limit($loginKey, 10, 300)) { // 10 спроб за 5 хв
    http_response_code(429);
    $error = 'Забагато спроб. Спробуйте через 5 хвилин.';
    // → не продовжувати до SELECT
}
```

---

### 9.4 `amo/order.php` — розсипані проблеми

**Знахідки:**

```php
// 1. Цей файл залежить від $counter — змінної з sendmail.php:
'Номер заказа' => 'OLA-' . $counter,
//                              ^^^^^^^ undefined якщо викликати окремо

// 2. dump() функція — дебаг у продакшн-коді:
function dump($data) {
    echo "<pre>"; print_r($data); echo "</pre>";
}
// якщо викликати — відображає сирі дані у браузері

// 3. Закоментований тестовий $_ POST з реальними даними у коментарях:
// 'phone' => '+7(999)999-99-99',

// 4. Закоментований $arAmoUTM — UTM-параметри не передаються в CRM

// 5. $_COOKIE доступ без isset():
$utmSource = ($_COOKIE['utm_source'] ? $_COOKIE['utm_source'] : '');
//            ^^^^^^^^^^^^^^^^^^^ PHP Notice якщо cookie немає
```

**Виправлення:**
```php
// Передавати $orderNumber явно:
// В sendmail.php:
require_once __DIR__ . '/amo/order.php';
amo_send_order($_POST, $orderNumber); // ← явний параметр

// amo/order.php:
function amo_send_order(array $post, int $orderNumber): void { ... }

// $_COOKIE з null-coalescing:
$utmSource = $_COOKIE['utm_source'] ?? '';

// Видалити dump() або замінити на dev_log_runtime()
```

---

### 9.5 `coupon.js` — мертвий файл з активним посиланням( треба реалізувати робот через адмінку як окрема сторінка)

**Файл:** `js/coupon.js`

```javascript
window.coupon = "";  // купон порожній
// весь код закоментований
```

Але файл **підключається** на сторінках. Він завантажується браузером, парситься JS-рушієм, і нічого не робить. 1 зайвий HTTP-запит на кожну сторінку.

**Дія:** або видалити файл і прибрати `<script>` тег, або повністю прибрати.

---

### 9.6 `.htaccess` кешує `.js` і `.css` на 7 днів, але немає cache-busting

```apache
<FilesMatch "\.(js|css|txt)$">
    Header set Cache-Control "max-age=604800"  ← 7 днів
</FilesMatch>
```

Але більшість `<link>` і `<script>` підключені **без версії**:

```html
<link rel="stylesheet" href="css/wicart.css">         ← кешується 7 днів
<script src="js/cart.js"></script>                    ← кешується 7 днів
<script src="js/coupon.js"></script>                  ← кешується 7 днів
```

Єдиний виняток — `styles.css` з `?v=Ymd`. Після деплою виправлень у `cart.js` користувач може бачити стару версію до 7 днів.

**Виправлення** — cache-busting для всіх змінюваних файлів (або через шаблон `head.php`):

```php
<?php
function asset_url(string $path): string {
    $full = $_SERVER['DOCUMENT_ROOT'] . '/' . $path;
    $v = file_exists($full) ? filemtime($full) : 0;
    return '/' . $path . '?v=' . $v;
}
?>
<link rel="stylesheet" href="<?= asset_url('css/wicart.css') ?>">
<script src="<?= asset_url('js/cart.js') ?>"></script>
```

---

### 9.7 `admin/products.php` — LIMIT 500 без пагінації

```php
$products = $pdo->query('SELECT * FROM products ORDER BY id DESC LIMIT 500')
```

При 500 товарах — завантажує всі 500 рядків в пам'ять PHP + рендерить 500 `<tr>`. Аналогічно у `customers.php`.

---

### 9.8 `amo/order.php` використовує `include_once` замість `require_once`

```php
include_once 'classes/Amo.php';
include_once 'classes/AmoAuth.php';
// ...
```

`include_once` при помилці — PHP `Notice`, виконання продовжується.
`require_once` — `Fatal Error`, виконання зупиняється (більш безпечно).

---

## 10. Повна карта проблем

```
РІВЕНЬ КРИТИЧНОСТІ
══════════════════

🔴 SECURITY (виправити до наступного деплою)
│
├── [S1] Пароль БД у git-репозиторії          config/db.php:7
├── [S2] Купон не верифікується на сервері    js/cart.js:6-7 + sendmail.php
├── [S3] Ціни не верифікуються на сервері     sendmail.php:155
├── [S4] setup.php доступний через HTTP        setup.php:1
├── [S5] Rate limit має race condition         config/db.php:139
├── [S6] CSRF відсутній у admin/products.php  admin/products.php
├── [S7] Brute force на /admin/login.php       admin/login.php
└── [S8] robots.txt блокує весь сайт          robots.txt:2

🟠 ARCHITECTURE (наступний спринт)
│
├── [A1] sendmail.php — God Function (7 відп.) sendmail.php
├── [A2] <head> дублюється у 5+ файлах        index.php, success.php ...
├── [A3] Global $pdo antipattern              config/db.php:25,50
├── [A4] products.php виконується 4 рази      data/products.php
├── [A5] Дві CRM з неузгодженим іменуванням   rest.php + amo/order.php
├── [A6] $counter у amo/order.php — implicit  amo/order.php:69
└── [A7] Cache 7 днів без asset versioning    .htaccess + усі сторінки

🟡 CODE QUALITY (технічний борг)
│
├── [Q1] Мішанина мов ru/uk в одному файлі    cart.js, sendmail.php
├── [Q2] dump() у продакшн-коді               amo/order.php
├── [Q3] Таймер акції з минулою датою          data/products.php
├── [Q4] clearBasket() хардкодить #btable     cart.js:clearBasket
├── [Q5] Два hardcoded #basketwidjet id        header.php + cart.js
├── [Q6] <style>/<script> у order_form.php    templates/order_form.php
├── [Q7] include_once замість require_once     amo/order.php
└── [Q8] $_COOKIE без isset() — PHP Notice     amo/order.php

🟢 CLEANUP (прибрати)
│
├── [C1] js/main_legacy.js (727 рядків)
├── [C2] templates/product_template_*.php2
├── [C3] js/coupon.js (повністю закоментований)
├── [C4] images/ID010_old.png, *.DS_Store, *.png2
├── [C5] Планові *.md у корені проєкту
├── [C6] Закоментований тестовий $_POST у amo/order.php
└── [C7] LIMIT 500 → пагінація у адмінці
```

---

## 11. Покроковий план виконання задач

### 🔴 Спринт 0 — Security Hotfix (день 1, ~3 год)

#### Задача S1 — Пароль БД

**Файл:** `config/db.php`

```
КРОК 1: Змінити пароль на сервері (хостинг-панель)
КРОК 2: Встановити нові env-змінні на сервері
КРОК 3: Оновити config/db.php — прибрати fallback
КРОК 4: Додати у .gitignore:
         config/db.php
         config/local.php
         .env
КРОК 5: git commit "security: remove hardcoded db credentials"
КРОК 6: Перевірити що сайт піднімається на сервері
```

#### Задача S4 — Закрити setup.php

**Файл:** `setup.php`, вставити рядки 1–5:

```php
<?php
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit('Forbidden');
}
// ... решта коду
```

#### Задача S8 — Виправити robots.txt

```
ЗАМІНИТИ вміст robots.txt:

User-agent: *
Disallow: /admin/
Disallow: /config/
Disallow: /log/
Disallow: /database/
Disallow: /amo/
Disallow: /sendmail.php
Disallow: /setup.php
Disallow: /create_feed.php

Sitemap: https://olaplex-shop.ru/sitemap.xml
```

---

### 🔴 Спринт 1 — Server-side Validation (день 2–3, ~5 год)

#### Задача S2+S3 — Верифікація цін і купону

**Файл:** `sendmail.php`

```
ЗНАЙТИ рядок:    $totalSum = 0.0;
                 foreach ($orderResult as $item) {
                     $totalSum += ((float)($item['price'] ...

ЗАМІНИТИ блоком з розділу 8.3 цього документа.

ПІСЛЯ цього знайти рядок:
    'coupon' => $payload['coupon'],
і ДОДАТИ вище нього валідацію купону з розділу 8.3.

ТЕСТ:
1. POST з price=0.01 → має повернути 400
2. POST з невідомим купоном → знижка не застосовується
3. POST з OLA5600 → totalSum зменшується на 5600
```

**Файл:** `config/app.php`

```
ДОДАТИ у масив конфігу:
'coupons' => [
    'OLA5600' => ['discount' => 5600, 'type' => 'fixed'],
],
```

#### Задача S6 — CSRF у адмінці

**Файл:** `admin/products.php`

```
У ФОРМУ додати:
<input type="hidden" name="csrf_token"
       value="<?= csrf_token() ?>">

НА ПОЧАТКУ if ($_SERVER['REQUEST_METHOD'] === 'POST') додати:
if (!validate_csrf_token()) {
    http_response_code(403);
    exit('CSRF check failed');
}

АНАЛОГІЧНО для admin/customers.php якщо з'являться POST-форми.
```

#### Задача S7 — Brute force захист логіну

**Файл:** `admin/login.php`

```
ЗНАЙТИ: if ($_SERVER['REQUEST_METHOD'] === 'POST') {
         $username = ...

ВСТАВИТИ ПІСЛЯ відкриваючої дужки:
$loginKey = 'admin_login_' . md5($_SERVER['REMOTE_ADDR'] ?? '');
if (!check_rate_limit($loginKey, 10, 300)) {
    $error = 'Забагато спроб входу. Спробуйте через 5 хвилин.';
} else {
    // ПЕРЕНЕСТИ решту if-блоку сюди
}
```

---

### 🟠 Спринт 2 — Усунення дублювання (день 4–5, ~6 год)

#### Задача A2 — Спільний `templates/head.php`

```
СТВОРИТИ файл: templates/head.php
  (код з розділу 8.5)

СТВОРИТИ файл: templates/analytics.php
  (Yandex.Metrika + Google gtag блоки)

ОНОВИТИ кожну сторінку:
  index.php               → прибрати <head>...</head>, замінити на:
                            <?php $pageTitle='...'; require 'templates/head.php'; ?>
  success.php             → аналогічно
  404.php                 → аналогічно
  delivery.php            → аналогічно
  info.php                → аналогічно
  oferta.php              → аналогічно
  policy.php              → аналогічно
  templates/single_product_template.php → аналогічно

ТЕСТ після кожного файлу: відкрити сторінку, перевірити <head> у DevTools.
```

#### Задача A7 — Cache busting для всіх assets

```
У templates/head.php додати функцію asset_url() (з розділу 9.6).

ЗАМІНИТИ у templates/head.php:
  href="css/wicart.css"  →  href="<?= asset_url('css/wicart.css') ?>"
  src="js/cart.js"       →  src="<?= asset_url('js/cart.js') ?>"
  src="js/main.js"       →  src="<?= asset_url('js/main.js') ?>"
```

#### Задача Q5 — Єдиний клас для лічильника кошика

**Файл:** `templates/header.php`

```
ЗНАЙТИ:
  <span id="basketwidjet">0</span>
  ...
  <span id="basketwidjet2"></span>

ЗАМІНИТИ:
  <span class="cart-widget-count">0</span>   (десктоп)
  <span class="cart-widget-count"></span>    (мобайл)
```

**Файл:** `js/cart.js`

```
ЗНАЙТИ метод updateWidgets(widgetSelector):
ЗАМІНИТИ тіло методу на код з розділу 8.4.

ЗНАЙТИ init(widgetID, config):
ВИДАЛИТИ рядок: this.widgetSelector = '#' + widgetID
  (більше не потрібний якщо є .cart-widget-count)

ОНОВИТИ всі виклики cart.init() у всіх PHP-файлах якщо передавали ID.
```

---

### 🟠 Спринт 3 — Рефакторинг ядра (день 6–8, ~10 год)

#### Задача A3 — Static singleton замість global $pdo

**Файл:** `config/db.php`

```
ЗАМІНИТИ блок:
  $pdoOptions = [...];
  try { $pdo = new PDO(...) } catch ...

НА функцію dev_db_connection() зі статичним singleton (розділ 8.1).

ПРИБРАТИ рядок: global $pdo; з тіла функції dev_db_connection().

ПЕРЕВІРИТИ що усі файли що викликають dev_db_connection() →
  не очікують глобальну $pdo після require_once 'config/db.php'.

ФАЙЛИ для перевірки:
  sendmail.php         → рядок $pdo = dev_db_connection()    ✓ ок
  admin/index.php      → рядок $pdo = dev_db_connection()    ✓ ок
  admin/products.php   → рядок $pdo = dev_db_connection()    ✓ ок
  admin/customers.php  → рядок $pdo = dev_db_connection()    ✓ ок
  data/products.php    → рядок $pdo = dev_db_connection()    ✓ ок
  create_feed.php      → перевірити
  setup.php            → перевірити
```

#### Задача A4 — Кешування products.php

**Файл:** `data/products.php`

```
ОБГОРНУТИ DB-запит у функцію get_products() зі static $cache
  (код з розділу 8.6)

ЗАЛИШИТИ: $products = get_products(); в кінці файлу
  (backward compatibility — всі require_once отримають $products)

ТЕСТ: додати dev_log_runtime('products loaded from DB') всередині функції
  і перевірити що у лозі з'являється один рядок навіть якщо
  products.php підключено з footer.php і slider_in_card.php одночасно.
```

#### Задача A6 — Явна передача $orderNumber у AMO

**Файл:** `amo/order.php`

```
ОБГОРНУТИ весь if (!empty($_POST)) у функцію:
  function amo_send_order(array $post, int $orderNumber): void { ... }

ЗАМІНИТИ $counter на $orderNumber у тілі функції.

ПРИБРАТИ визначення функцій p2log() і dump() — вони дублюються.
  p2log() вже є у sendmail.php (або виносимо у config/db.php)
  dump() — видалити повністю.

ФАЙЛ sendmail.php:
  ЗАМІНИТИ: require_once __DIR__ . '/amo/order.php';
  НА:       require_once __DIR__ . '/amo/order.php';
            amo_send_order($_POST, $orderNumber);
  (і прибрати старий блок if (!empty($_POST)) з amo/order.php)

ЗАМІНИТИ include_once → require_once у amo/order.php.

ВИПРАВИТИ $_COOKIE доступи:
  $utmSource = $_COOKIE['utm_source'] ?? '';
```

#### Задача A5 — Перевірити чи потрібен rest.php (Bitrix)

```
ЗАПИТ ДО КОМАНДИ: чи активна Bitrix CRM інтеграція?

Якщо НІ:
  ВИДАЛИТИ: rest.php
  У sendmail.php видалити:
    $crmSent = dev_send_bitrix_lead(...)
    require_once 'rest.php'
    $_POST['CRM_SENT'] = ...

Якщо ТАК:
  ЗАМІНИТИ fsockopen на curl з timeout 5с (код з розділу 4.5)
```

---

### 🟡 Спринт 4 — Code Quality (день 9–10, ~5 год)

#### Задача Q1 — Уніфікація мови

```
ВИЗНАЧИТИ цільову мову (ua або ru).

У js/cart.js:
  Замінити 'руб.' на 'грн'
  Видалити блок $('#moscow').html(...)
  Видалити '#region' блок
  Видалити 'delivery_checkout' секцію з ensureBasketModal HTML

У sendmail.php (email-шаблон):
  'Имя:' → 'Ім'я:' / 'Имя:'
  'Итого:' → 'Разом:' / 'Итого:'
  'руб.' → 'грн' / 'руб.'

(Обрати одну мову і пройтись по всіх рядках)
```

#### Задача Q6 — Прибрати inline CSS/JS з order_form.php

```
CSS блок з templates/order_form.php:
  ПЕРЕНЕСТИ у css/wicart.css (секція "Order form")

Script блок з templates/order_form.php:
  ПЕРЕНЕСТИ у js/cart.js (або окремий js/order-form.js)
  Обгорнути у document.addEventListener('DOMContentLoaded', ...)
  якщо ще не обгорнуто.
```

#### Задача Q2 — Прибрати debug код з amo/order.php

```
ВИДАЛИТИ функцію dump().
ВИДАЛИТИ закоментований блок // $_POST = [...].
ВИДАЛИТИ або розкоментувати $arAmoUTM (обговорити з командою).
```

#### Задача Q3 — Виправити таймер акції

```
data/products.php — функція getDiscountTimer():

Варіант 1 (якщо акція продовжується):
  ЗАМІНИТИ hardcoded дату на динамічну:
  $targetDate = time() + (15 * 24 * 60 * 60); // +15 днів від зараз

Варіант 2 (якщо акції немає):
  ВИДАЛИТИ функцію getDiscountTimer()
  Знайти всі виклики в шаблонах і прибрати таймери.
```

---

### 🟢 Спринт 5 — Cleanup (день 11, ~2 год)

#### Задача C1-C7 — Видалення мертвого коду

```bash
# Видалити мертві файли:
git rm js/main_legacy.js
git rm templates/product_template_even.php2
git rm templates/product_template_odd.php2
git rm images/ID010_old.png
git rm "images/ID008_min2.png2"
git rm "images/.DS_Store"
git rm js/coupon.js
git rm db_admin_auth_plan_*.md
git rm dev_cart_replacement_plan_*.md

# Прибрати підключення coupon.js з усіх сторінок:
grep -rn "coupon.js" . --include="*.php"
# → прибрати <script> теги

git commit "chore: remove dead files and legacy code"
```

#### Задача C7 — Пагінація адмінки

**Файл:** `admin/index.php`

```php
// ЗАМІНИТИ:
$orders = $pdo->query('... LIMIT 100')->fetchAll();

// НА:
$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 50;
$offset  = ($page - 1) * $perPage;
$total   = (int)$pdo->query('SELECT COUNT(*) FROM orders')->fetchColumn();
$orders  = $pdo->prepare('SELECT ... FROM orders ORDER BY id DESC LIMIT :lim OFFSET :off');
$orders->execute(['lim' => $perPage, 'off' => $offset]);
$orders  = $orders->fetchAll();

// Аналогічно у admin/customers.php та admin/products.php
// Додати у HTML кнопки:
// ← Попередня | Сторінка X з Y | Наступна →
```

---

## 12. Тестування після кожного спринту

### Чекліст Спринту 0 (Security Hotfix)

```
□ curl -X GET https://site.ru/setup.php → 403 Forbidden
□ curl -X GET https://site.ru/config/db.php → 403 Forbidden (.htaccess)
□ Перевірити що DB_PASS не в git: git log -p config/db.php | grep "dI3wW1tT3d"
□ robots.txt перевірити через Google Search Console
□ Успішне замовлення після зміни пароля БД
```

### Чекліст Спринту 1 (Server Validation)

```
□ POST sendmail.php з price=0.01 → відповідь 400 + json error
□ POST sendmail.php з невідомим product id → відповідь 400
□ POST sendmail.php з купоном OLA5600 → totalSum зменшений на 5600
□ POST sendmail.php з фейковим купоном "HACK" → знижка не застосована
□ Реальне замовлення end-to-end → email надходить, запис у БД є
□ Відкрити /admin/login.php, 11 разів відправити форму → rate limit 429
□ admin/products.php — спробувати змінити ціну без csrf_token → 403
```

### Чекліст Спринту 2 (Дублювання)

```
□ View Source кожної сторінки → Yandex.Metrika тільки 1 раз
□ View Source → styles.css підключено з ?v= версією
□ View Source → wicart.css підключено з ?v= версією
□ View Source → cart.js підключено з ?v= версією
□ Після зміни cart.js → F5 у браузері → нова версія підвантажується
□ Лічильник кошика оновлюється на десктопі і мобайлі одночасно
```

### Чекліст Спринту 3 (Рефакторинг ядра)

```
□ products.php — у runtime.log один запис "products loaded" за запит
□ AMO: замовлення зберігається у CRM з правильним номером
□ Перевірити що глобальна $pdo більше ніде не використовується: grep -rn "global \$pdo"
□ amo/order.php — PHP Strict mode не видає Notice/Warning
```

### Чекліст Спринту 4-5 (Cleanup)

```
□ coupon.js — не з'являється у Network DevTools
□ main_legacy.js — не підключений ніде: grep -rn "main_legacy"
□ Таймер відображає коректний зворотній відлік
□ Пагінація адмінки: /admin/?page=2 → друга сторінка замовлень
□ Лог amo_orders — немає закоментованих dump() виводів у продакшні
```

---

## 13. Стратегія деплою

### Порядок деплою для кожного спринту

```
1. BACKUP перед деплоєм:
   mysqldump -u olap_adm -p olap_san > backup_$(date +%Y%m%d_%H%M).sql

2. MAINTENANCE MODE (якщо є):
   echo "<?php http_response_code(503); echo 'Технічне обслуговування'; exit;" > maintenance.php

3. ДЕПЛОЙ файлів:
   rsync -avz --exclude='.git' --exclude='log/' . user@server:/path/to/site/

4. ТЕСТ:
   Відкрити сайт у приватному вікні браузера.
   Зробити тестове замовлення.

5. ВИДАЛИТИ maintenance.php

6. МОНІТОРИНГ:
   tail -f /path/to/site/log/runtime.log
   tail -f /path/to/site/log/security.log
```

### Змінні середовища для сервера

```bash
# Встановити у .bashrc / .env сервера або хостинг-панелі:
export APP_ENV=production
export DB_HOST=localhost
export DB_NAME=olap_san
export DB_USER=olap_adm
export DB_PASS=<новий_безпечний_пароль>
```

### Файли що **не деплоїти** на сервер

```
.git/
log/
database/dev_shop.sqlite
setup.php               ← видалити з сервера після першого запуску
*.plan.md               ← документація, не для продакшну
config/db.php           ← якщо використовуємо env без fallback
```

---

## 14. Моніторинг та спостережуваність

### Логи які вже існують і що в них шукати

| Файл логу | Що шукати | Дія |
|-----------|-----------|-----|
| `log/runtime.log` | `DB order save failed` | БД недоступна або транзакція впала |
| `log/runtime.log` | `Products load failed` | Немає товарів, сайт показує порожню сторінку |
| `log/runtime.log` | `CRM connection failed` | AMO/Bitrix не отримує ліди |
| `log/security.log` | `CSRF_ATTEMPT` | Підозрілі запити, можлива атака |
| `log/security.log` | `RATE_LIMIT_EXCEEDED` | Флуд замовлень з одного IP |
| `log/security.log` | `INVALID_PHONE` часто | Тест-боти або некоректна форма |
| `log/amo_orders_*.log` | порожній файл або помилки | CRM не отримує замовлення |

### Що додати для кращої спостережуваності

```php
// config/db.php — додати функцію для метрик:
function dev_log_order_event(string $event, array $context = []): void {
    dev_log_runtime(sprintf(
        '[ORDER] event=%s order=%s total=%s ip=%s',
        $event,
        $context['order_number'] ?? '-',
        $context['total'] ?? '-',
        $_SERVER['REMOTE_ADDR'] ?? '-'
    ));
}

// sendmail.php — викликати в ключових точках:
dev_log_order_event('created', ['order_number' => $orderNumber, 'total' => $totalSum]);
dev_log_order_event('email_sent', ['order_number' => $orderNumber]);
dev_log_order_event('crm_sent', ['order_number' => $orderNumber]);
```

### Рекомендований алерт (простий варіант без зовнішніх сервісів)

```php
// У sendmail.php після $success = mail(...):
if (!$success) {
    // Email не надійшов — критична проблема
    dev_log_runtime('[CRITICAL] Email delivery failed for order #' . $orderNumber);
    // Можна надіслати Telegram-повідомлення через простий curl:
    // curl https://api.telegram.org/bot{TOKEN}/sendMessage?chat_id={ID}&text=Email+failed
}
```

---

## 15. Технічний борг: трекінг

Рекомендовано вести у GitHub Issues або у простому TASK.md:

```markdown
## TASK.md — Технічний борг

### 🔴 БЛОКЕРИ (до деплою)
- [x] S4: setup.php закрити
- [ ] S1: видалити пароль з коду
- [ ] S2: серверна валідація купону
- [ ] S3: верифікація цін з БД
- [ ] S8: robots.txt виправити

### 🟠 АКТИВНИЙ БОРГ
- [ ] A2: head.php шаблон
- [ ] A3: static PDO singleton
- [ ] A4: кешування products.php
- [ ] A7: cache busting для assets

### 🟡 ПЛАНОВАНИЙ БОРГ
- [ ] Q1: уніфікація мови
- [ ] Q5: .cart-widget-count клас
- [ ] Пагінація адмінки

### 🟢 CLEANUP
- [ ] Видалити main_legacy.js
- [ ] Видалити coupon.js
- [ ] Видалити *.php2 файли
```

---

## Фінальний підсумок

| Спринт | Задачі | Час | Пріоритет |
|--------|--------|-----|-----------|
| 0 — Security Hotfix | S1, S4, S8 | ~3 год | 🔴 Зараз |
| 1 — Server Validation | S2, S3, S6, S7 | ~5 год | 🔴 Цього тижня |
| 2 — Дублювання | A2, A7, Q5 | ~6 год | 🟠 Наступний тиждень |
| 3 — Рефакторинг | A3, A4, A5, A6 | ~10 год | 🟠 Наступний тиждень |
| 4 — Code Quality | Q1, Q2, Q3, Q6 | ~5 год | 🟡 Цього місяця |
| 5 — Cleanup | C1–C7 | ~2 год | 🟢 Будь-коли |
| **Разом** | **26 задач** | **~31 год** | |

### Критичний шлях (що зупиняє все якщо не зробити)

```
[зараз]    Змінити пароль БД → видалити з коду
    ↓
[день 1]   Закрити setup.php + виправити robots.txt
    ↓
[день 2-3] Верифікація цін і купону на сервері
    ↓
[день 4]   CSRF + brute force на адмінці
    ↓
[далі]     Все інше в порядку пріоритету
```


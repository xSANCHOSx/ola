<?php

// FIX #3 — три проблеми виправлено:
//
// 1. КОРОТКИЙ ТЕГ <? → <?php
//    При short_open_tag = Off (стандарт PHP 7+/8) весь код виводився як сирий
//    текст у браузері. UTM-cookies не встановлювались взагалі.
//
// 2. XSS через print_r($_COOKIE[...]) без екранування.
//    Зловмисник міг встановити cookie зі значенням <script>...</script> і
//    виконати довільний JS при відвідуванні цієї сторінки.
//    Весь debug-вивід видалено — файл не повинен нічого виводити в браузер.
//
// 3. НЕБЕЗПЕЧНІ ПАРАМЕТРИ setcookie().
//    Раніше: setcookie("utm_source", $_GET['utm_source'], $cookieTime, "/")
//    Без httponly та samesite cookie доступна через JS і вразлива до CSRF.
//    Тепер використовується масив опцій із httponly і samesite.

declare(strict_types=1);

// Дозволені UTM-параметри — білий список, щоб не записувати довільні GET-параметри
const UTM_PARAMS = ['utm_source', 'utm_medium', 'utm_campaign', 'utm_content'];

// Максимальна довжина значення UTM щоб уникнути зловживань
const UTM_MAX_LENGTH = 255;

if (isset($_GET['utm_source'])) {
    $cookieTime = time() + 60 * 60 * 24 * 7; // 7 днів

    foreach (UTM_PARAMS as $param) {
        $value = isset($_GET[$param]) ? trim((string) $_GET[$param]) : '';

        // Обмежуємо довжину і прибираємо керуючі символи
        $value = mb_substr($value, 0, UTM_MAX_LENGTH);

        setcookie($param, $value, [
            'expires'  => $cookieTime,
            'path'     => '/',
            'httponly' => true,   // недоступна через document.cookie → захист від XSS-крадіжки
            'samesite' => 'Lax', // захист від CSRF при cross-site переходах
            // 'secure' => true, // розкоментувати якщо сайт працює лише по HTTPS
        ]);
    }
}

// Файл більше нічого не виводить у браузер.
// Попередній debug-вивід через print_r($_COOKIE[...]) видалено:
// він був залишком розробки і створював XSS-вектор.

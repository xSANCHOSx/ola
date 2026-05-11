<?php

declare(strict_types=1);

// Разрешённые UTM-параметры — белый список, чтобы не записывать произвольные GET-параметры
const UTM_PARAMS = ['utm_source', 'utm_medium', 'utm_campaign', 'utm_content'];

// Максимальная длина значения UTM, чтобы исключить злоупотребление
const UTM_MAX_LENGTH = 255;

if (isset($_GET['utm_source'])) {
    $cookieTime = time() + 60 * 60 * 24 * 7; // 7 дней

    foreach (UTM_PARAMS as $param) {
        $value = isset($_GET[$param]) ? trim((string) $_GET[$param]) : '';

        // Ограничиваем длину и удаляем управляющие символы
        $value = mb_substr($value, 0, UTM_MAX_LENGTH);

        setcookie($param, $value, [
            'expires'  => $cookieTime,
            'path'     => '/',
            'httponly' => true,   // недоступна через document.cookie → защита от XSS-кражи
            'samesite' => 'Lax', // защита от CSRF при cross-site переходах
            // 'secure' => true, // раскомментируйте, если сайт работает только по HTTPS
        ]);
    }
}
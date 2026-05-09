<?php
declare(strict_types=1);

// Запустити сесію тільки якщо вона не активна
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/db.php';

function admin_is_auth(): bool
{
    $cfg = dev_app_config();
    $key = $cfg['admin_session_key'] ?? 'dev_admin_auth';
    return !empty($_SESSION[$key]['id']);
}

function admin_require_auth(): void
{
    if (!admin_is_auth()) {
        header('Location: /admin/login.php');
        exit;
    }
}

function admin_session_set(array $user): void
{
    $cfg = dev_app_config();
    $key = $cfg['admin_session_key'] ?? 'dev_admin_auth';
    $_SESSION[$key] = [
        'id' => $user['id'],
        'username' => $user['username'],
    ];
}

function admin_session_clear(): void
{
    $cfg = dev_app_config();
    $key = $cfg['admin_session_key'] ?? 'dev_admin_auth';
    unset($_SESSION[$key]);
}

function admin_current_user(): ?array
{
    $cfg = dev_app_config();
    $key = $cfg['admin_session_key'] ?? 'dev_admin_auth';
    return $_SESSION[$key] ?? null;
}

function admin_h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

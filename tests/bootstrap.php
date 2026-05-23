<?php
// tests/bootstrap.php
// No vendor autoload needed - PHPUnit phar already includes the framework
define('BASE_PATH', realpath(__DIR__ . '/../'));

// Завантажуємо класи з app/
require_once BASE_PATH . '/app/Service/OlaLogger.php';
require_once BASE_PATH . '/app/Service/PriceService.php';
require_once BASE_PATH . '/app/Service/OrderNumberService.php';
require_once BASE_PATH . '/app/Service/NotificationService.php';
require_once BASE_PATH . '/app/Model/OrderModel.php';
require_once BASE_PATH . '/app/Model/CustomerModel.php';
require_once BASE_PATH . '/app/Controller/OrderController.php';
require_once BASE_PATH . '/app/View/EmailView.php';

// Mock any site-specific functions if needed
if (!function_exists('admin_current_user')) {
    function admin_current_user() {
        return ['username' => 'test'];
    }
}
if (!function_exists('admin_h')) {
    function admin_h($s) { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }
}
if (!function_exists('dev_log_runtime')) {
    function dev_log_runtime($msg) { /* mock */ }
}
if (!function_exists('dev_send_bitrix_lead')) {
    function dev_send_bitrix_lead($subject, $payload) { return true; }
}
if (!function_exists('amo_send_order')) {
    function amo_send_order($post) { return true; }
}
if (!function_exists('validate_coupon_for_order')) {
    function validate_coupon_for_order($pdo, $code, $total, $forUpdate) {
        return ['valid' => false, 'error' => 'mock'];
    }
}
if (!function_exists('calculate_discount_amount')) {
    function calculate_discount_amount($coupon, $total) { return 0.0; }
}
?>
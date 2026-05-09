<?php $u = admin_current_user(); ?>
<nav style="padding:12px 0; border-bottom:1px solid #ddd; margin-bottom:20px;">
    <a href="/admin/" style="margin-right:12px;">Заказы</a>
    <a href="/admin/products.php" style="margin-right:12px;">Товары</a>
    <a href="/admin/customers.php" style="margin-right:12px;">Клиенты</a>
    <a href="/admin/blog.php" style="margin-right:12px;">Блог</a>
    <a href="/admin/coupons.php" style="margin-right:12px; font-weight:bold; color:#008000;">💰 Купоны</a>
    <a href="/admin/coupon_stats.php" style="margin-right:12px; font-weight:bold; color:#0066cc;">📊 Статистика</a>
    <span style="float:right;">
        <?= admin_h((string)($u['username'] ?? '')) ?> |
        <a href="/admin/logout.php">Выход</a>
    </span>
</nav>

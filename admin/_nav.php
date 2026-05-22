<?php $u = admin_current_user(); ?>
<nav class="admin-nav">
    <ul class="admin-nav__list">
        <li class="admin-nav__item"><a href="/admin/" class="admin-nav__link"><span>Заказы</span></a></li>
        <li class="admin-nav__item"><a href="/admin/products.php" class="admin-nav__link"><span>Товары</span></a></li>
        <li class="admin-nav__item"><a href="/admin/customers.php" class="admin-nav__link"><span>Клиенты</span></a></li>
        <li class="admin-nav__item"><a href="/admin/blog.php" class="admin-nav__link"><span>Блог</span></a></li>
        <li class="admin-nav__item"><a href="/admin/coupons.php" class="admin-nav__link"><span>Купоны</span></a></li>
        <li class="admin-nav__item"><a href="/admin/coupon_stats.php" class="admin-nav__link"><span>Статистика</span></a></li>
    </ul>
    <div class="admin-nav__user">
        <?= admin_h((string)($u['username'] ?? '')) ?> |
        <a href="/admin/logout.php" class="admin-nav__link"><span>Выход</span></a>
    </div>
</nav>

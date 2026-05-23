<?php $u = admin_current_user(); ?>
<nav class="admin-nav">
    <ul class="admin-nav__menu">
        <li class="admin-nav__item"><a href="/admin/" class="admin-nav__link"><svg class="admin-nav__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h.01M12 16h.01M9 16h.01"/></svg><span>Заказы</span></a></li>
        <li class="admin-nav__item"><a href="/admin/products.php" class="admin-nav__link"><svg class="admin-nav__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16.5 9.4l-9-5.19M21 16.5v-12c0-1.1-.9-2-2-2H5c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2zM3.72 9.97l8.3 4.79c.38.22.86.22 1.24 0l8.3-4.79M12 19v-9"/></svg><span>Товары</span></a></li>
        <li class="admin-nav__item"><a href="/admin/customers.php" class="admin-nav__link"><svg class="admin-nav__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2M16 11a4 4 0 11-8 0 4 4 0 018 0zM23 21v-2a4 4 0 00-3-3.87M16 3.13a4 4 0 010 7.75"/></svg><span>Клиенты</span></a></li>
        <li class="admin-nav__item"><a href="/admin/blog.php" class="admin-nav__link"><svg class="admin-nav__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg><span>Блог</span></a></li>
        <li class="admin-nav__item"><a href="/admin/coupons.php" class="admin-nav__link"><svg class="admin-nav__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 4.5C2 3.12 3.12 2 4.5 2h15C20.88 2 22 3.12 22 4.5v3c-1.11 0-2 .89-2 2s.89 2 2 2v3c0 1.38-1.12 2.5-2.5 2.5h-15C3.12 17 2 15.88 2 14.5v-3c1.11 0 2-.89 2-2s-.89-2-2-2v-3z"/></svg><span>Купоны</span></a></li>
        <li class="admin-nav__item"><a href="/admin/coupon_stats.php" class="admin-nav__link"><svg class="admin-nav__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 3v18h18M18 17V9M13 17v-5M8 17v-3"/></svg><span>Статистика</span></a></li>
    </ul>
    <div class="admin-nav__user">
        <?= admin_h((string)($u['username'] ?? '')) ?> |
        <a href="/admin/logout.php" class="admin-nav__link"><span>Выход</span></a>
    </div>
</nav>

$(function () {
    /**
     * Header Height Calculation
     * Used for scroll-spy and other layout logic.
     * For mobile (<= 760px), it includes both rows of the header.
     */
    function getHeaderHeight() {
        if ($(window).width() <= 760) {
            const $navHeader = $('.navbar-header');
            const $mobileArea = $('.header-right-area-mobile');
            return ($navHeader.outerHeight() || 0) + ($mobileArea.outerHeight() || 0);
        } else {
            return $('header').outerHeight() || 50;
        }
    }

    var lastId,
        topMenu = $('.main'),
        menuItems = topMenu.find("a[href*='#']"),
        scrollItems = menuItems.map(function () {
            var href = $(this).attr('href');
            var id = href.includes('#') ? '#' + href.split('#')[1] : null;
            var item = id ? $(id) : null;
            if (item && item.length) {
                return item;
            }
        });

    /**
     * Scroll-Spy Logic
     * Highlights the active menu item based on current scroll position.
     */
    $(window).scroll(function () {
        var topMenuHeight = getHeaderHeight();
        var fromTop = $(this).scrollTop() + topMenuHeight + 10; // 10px buffer
        
        var cur = scrollItems.map(function () {
            if ($(this).offset().top < fromTop) return this;
        });
        
        cur = cur[cur.length - 1];
        var id = cur && cur.length ? cur[0].id : '';

        if (lastId !== id) {
            lastId = id;
            menuItems
                .parent()
                .removeClass('active')
                .end()
                .filter("[href*='#" + id + "']")
                .parent()
                .addClass('active');
        }
    });

    // Handle mobile menu toggle click propagation
    $('.navbar-toggle').on('click', function(e) {
        e.stopPropagation();
    });
});

/**
 * Responsive layout adjustments
 */
var $win = $(window),
    $winW = $win.width(),
    $doc = $(document),
    $headerH = $doc.height();

function resFun() {
    if ($winW < 992) {
        $('.cart_full span').remove();
    }
}

$doc.on('scroll', function () {
    var $hs = $doc.height();
    if ($hs != $headerH && $winW > 768) {
        $headerH = $hs;
    }
});

resFun();

$win.resize(function () {
    $winW = $win.width();
    resFun();
});

/**
 * Page load animation
 */
jQuery(document).ready(function ($) {
    $(window).on('load', function () {
        setTimeout(function () {
            $('#preloader').fadeOut('slow');
        }, 100);
    });
});

/**
 * E-commerce Analytics & Helpers
 */

/**
 * Discount Timer (Flip Clock)
 * Обновляет блоки `.expire_date[data-end]`, выводя количество дней,
 * оставшихся до конца акции, в элемент `.flip-unit .days`.
 * Разметка генерируется в templates/helpers.php (функция getDiscountTimer).
 */
function updateTimer() {
    var now = Math.floor(Date.now() / 1000);

    $('.expire_date[data-end]').each(function () {
        var $timer = $(this);
        var endTime = parseInt($timer.attr('data-end'), 10);

        if (isNaN(endTime)) {
            return;
        }

        var diff = endTime - now;
        var days = diff > 0 ? Math.floor(diff / 86400) : 0;

        $timer.find('.flip-unit .days').text(days);
    });
}

jQuery(function ($) {
    if ($('.expire_date[data-end]').length) {
        updateTimer();
        setInterval(updateTimer, 60000);
    }
});
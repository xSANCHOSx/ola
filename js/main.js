'use strict'

$(function () {
    /**
     * Header Height Calculation
     * Used for scroll-spy and other layout logic.
     */
    function getHeaderHeight() {
        // .navbar-header (logo + burger) is a sibling of the collapsible mobile
        // menu, not its parent — measuring "header .navbar" (old code) included
        // the dropdown menu's height whenever it was open, throwing off the
        // scroll-spy active-item calculation. Kept in sync with anchor-scroll.js.
        return ($(window).width() > 760)
            ? ($('header').height() || 0)
            : ($('header .navbar-header').height() || 0);
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

    // Close mobile menu when clicking outside of it
    $(document).on('click', function(e) {
        const $mobileMenu = $('#bs-example-navbar-collapse-1');
        const $navbar = $('.navbar');
        
        // Check if menu is open
        if ($mobileMenu.hasClass('show') || $mobileMenu.hasClass('in')) {
            // Check if click was outside the navbar
            if (!$navbar.find(e.target).length && !$navbar.has(e.target).length) {
                // Close the menu by triggering the toggle button click
                $('.navbar-toggle, .navbar-toggler').trigger('click');
            }
        }
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
 * Promotional Timer
 */
function updateTimer(uniqueId) {
    const timerElement = document.querySelector(`#timer-${uniqueId}`);
    if (!timerElement || !timerElement.dataset.end) return;
    
    let end = parseFloat(timerElement.dataset.end);
    if (isNaN(end)) return;

    let now = new Date().getTime() / 1000;
    let timeLeft = end - now;
    
    if (timeLeft <= 0) {
        end = now + 15 * 24 * 60 * 60;
        timerElement.dataset.end = end;
        timeLeft = end - now;
    }

    let days = Math.floor(timeLeft / (24 * 60 * 60));
    let dayWord = days === 1 ? 'День' : days >= 2 && days <= 4 ? 'Дня' : 'Дней';

    const daysElement = timerElement.querySelector('.days');
    const labelElement = timerElement.querySelector('.flip-label');
    if (daysElement && labelElement) {
        daysElement.innerText = days;
        labelElement.innerText = dayWord;
    }
}

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.expire_date').forEach(timer => {
        const uniqueId = timer.id.replace('timer-', '');
        updateTimer(uniqueId);
        setInterval(() => updateTimer(uniqueId), 3600000);
    });
});

/**
 * Cookie Consent
 */
function acceptCookies() {
    document.cookie = 'cookie_accepted=true; max-age=31536000; path=/';
    const notice = document.getElementById('cookie-notice');
    if (notice) notice.style.display = 'none';
}

window.addEventListener('load', function () {
    const notice = document.getElementById('cookie-notice');
    if (notice) {
        notice.style.display = document.cookie.indexOf('cookie_accepted=true') === -1 ? 'block' : 'none';
    }
});

/**
 * Contact Form Logic
 */
document.addEventListener('DOMContentLoaded', function() {
    const radios = document.querySelectorAll('input[name="contact_method"]');
    const wrapper = document.getElementById('contact-username-wrapper');
    const input = document.getElementById('contact_username');

    if (!wrapper || !input) return;

    function updateField(value) {
        if (value === 'email') {
            wrapper.style.display = 'none';
            input.removeAttribute('required');
        } else {
            wrapper.style.display = 'block';
            input.setAttribute('required', 'required');

            let placeholder = '@username';
            if (value === 'whatsapp') placeholder = 'Номер WhatsApp';
            if (value === 'telegram') placeholder = '@telegram_username';
            if (value === 'max') placeholder = '@max_username';

            input.placeholder = placeholder;
        }
    }

    radios.forEach(radio => {
        radio.addEventListener('change', function() {
            updateField(this.value);
        });
    });

    const checked = document.querySelector('input[name="contact_method"]:checked');
    if (checked) updateField(checked.value);
});

/**
 * Phone Input Mask
 */
document.addEventListener('DOMContentLoaded', function () {
    var phone = document.getElementById('phoneNumber');
    if (!phone) return;

    phone.addEventListener('input', function (e) {
        var val = e.target.value.replace(/\D/g, '');
        if (val.startsWith('7') || val.startsWith('8')) val = val.slice(1);
        var result = '+7(';
        if (val.length > 0) result += val.slice(0, 3);
        if (val.length >= 3) result += ')' + val.slice(3, 6);
        if (val.length >= 6) result += '-' + val.slice(6, 8);
        if (val.length >= 8) result += '-' + val.slice(8, 10);
        e.target.value = result;
    });

    phone.addEventListener('keydown', function (e) {
        if (e.key === 'Backspace' && phone.value === '+7(') e.preventDefault();
    });

    phone.addEventListener('focus', function () {
        if (!phone.value) phone.value = '+7(';
    });
});

'use strict'

$(function () {
    if ($(window).width() > 760) {
      var topMenuHeight = $('header').height() - 0
    } else {
      var topMenuHeight = $('header .navbar').height() - 0
    }

    var lastId,
    topMenu = $('.main'),
    menuItems = topMenu.find("a[href*='#']"),
    scrollItems = menuItems.map(function () {
        var item = $($(this).attr('href').replace('/#', '#'));
        if (item.length) {
          return item;
        }
    });

    menuItems.on('click', function (e) {
        if ($('#bs-example-navbar-collapse-1').hasClass('show')) {
            $('.navbar-toggle').trigger('click');
        }
        var href = $(this).attr('href'),
        offsetTop = href === '#'? 0 : $(href).offset().top - topMenuHeight + 1;
        $('html, body').stop().animate({
            scrollTop: offsetTop
        }, 300);
        e.preventDefault();
    });


    $('.navbar-toggle').on('click', function(e) {
        e.stopPropagation();
    });

    // Scroll event handler
    $(window).scroll(function () {
        var fromTop = $(this).scrollTop() + topMenuHeight;
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
            .filter("[href='#"+ id + "']")
            .parent()
            .addClass('active');
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
    resFun();
});

/**
 * Page load animation
 * Hide preloader when page is fully loaded
 */
jQuery(document).ready(function ($) {
    $(window).on('load', function () {
        setTimeout(function () {
            $('#preloader').fadeOut('slow', function () {});
        }, 100);
    });
});

/**
 * E-commerce Analytics - Google & Yandex
 * Tracks product views and add to cart events
 */

// Set list position for analytics
$('[data-id]').each(function (e) {
    $(this).attr('data-id', e);
});

/**
 * Timer for promotional items
 * Updates countdown timer display every hour
 */
function updateTimer(uniqueId) {
  const timerElement = document.querySelector(`#timer-${uniqueId}`);
  if (!timerElement || !timerElement.dataset.end) return;
  
  let end = parseFloat(timerElement.dataset.end);
  if (isNaN(end)) return; // Skip if end is invalid

  let now = new Date().getTime() / 1000;
  let timeLeft = end - now;
  
  if (timeLeft <= 0) {
    end = now + 15 * 24 * 60 * 60; // Reset to 15 days
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
        updateTimer(uniqueId); // Immediate update
        setInterval(() => updateTimer(uniqueId), 3600000); // Update every hour
    });
});

/**
 * Cookie consent management
 */
function acceptCookies() {
  document.cookie = 'cookie_accepted=true; max-age=31536000; path=/';
  document.getElementById('cookie-notice').style.display = 'none';
}

window.onload = function () {
  if (document.cookie.indexOf('cookie_accepted=true') === -1) {
    document.getElementById('cookie-notice').style.display = 'block';
  } else {
    document.getElementById('cookie-notice').style.display = 'none';
  }
};


/**
 * Contact method field visibility toggle
 * Shows/hides username field based on selected communication method
 */
document.addEventListener('DOMContentLoaded', function() {
	const radios = document.querySelectorAll('input[name="contact_method"]');
	const wrapper = document.getElementById('contact-username-wrapper');
	const input = document.getElementById('contact_username');

	if (!wrapper || !input) return; // Exit if elements don't exist

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

// Нативная маска телефона — заменяет inputmask (25 строк вместо 181 KB)
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

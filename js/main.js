/** ==========================================
    ОПТИМІЗОВАНИЙ MAIN.JS
    Поліпшена версія з кращою управлінням слайдерів
    та мінімальними блокуючими операціями
    ========================================== */

'use strict'

// Інідалізація ленивого завантаження зображень
(function() {
    // Перевірка підтримки Intersection Observer
    if ('IntersectionObserver' in window) {
        const imageObserver = new IntersectionObserver((entries, observer) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const img = entry.target;
                    img.src = img.dataset.src || img.src;
                    img.classList.add('loaded');
                    imageObserver.unobserve(img);
                }
            });
        }, {
            rootMargin: '50px'
        });

        // Спостерігати за всіма зображеннями з loading="lazy"
        document.querySelectorAll('img[loading="lazy"]').forEach(img => {
            imageObserver.observe(img);
        });
    }
})();

/**
 * ЛИПКА НАВІГАЦІЯ З ДЕТЕКТУВАННЯМ СКРОЛУ
 * Виділяє активну пункт меню на основі позиції скролу
 */
$(function () {
    // Тільки для великих екранів
    if ($(window).width() > 760) {
        const topMenuHeight = $('header').height() - 0;
    } else {
        const topMenuHeight = $('header .navbar').height() - 0;
    }

    const lastId = null;
    const topMenu = $('.main');
    const menuItems = topMenu.find("a[href*='#']");
    const scrollItems = menuItems.map(function () {
        const item = $($(this).attr('href').replace('/#', '#'));
        if (item.length) {
            return item;
        }
    });

    // Обробник кліку по меню
    menuItems.on('click', function (e) {
        $('.navbar-toggle').trigger('click');
        const href = $(this).attr('href');
        const offsetTop = href === '#'? 0 : $(href).offset().top - topMenuHeight + 1;
        
        $('html, body').stop().animate({
            scrollTop: offsetTop
        }, 300);
        e.preventDefault();
    });

    // Обробник скролу
    $(window).scroll(function () {
        const fromTop = $(this).scrollTop() + topMenuHeight;
        let cur = scrollItems.map(function () {
            if ($(this).offset().top < fromTop) return this;
        });
        
        cur = cur[cur.length - 1];
        const id = cur && cur.length ? cur[0].id : '';

        if (lastId !== id) {
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
 * АДАПТИВНІ КОРИГУВАННЯ МАКЕТА
 */
(function() {
    const $win = $(window);
    const $winW = $win.width();
    const $doc = $(document);
    let $headerH = $doc.height();

    function resFun() {
        if ($winW < 992) {
            $('.cart_full span').remove();
        }
    }

    $doc.on('scroll', function () {
        const $hs = $doc.height();
        if ($hs != $headerH && $winW > 768) {
            $headerH = $hs;
        }
    });

    resFun();

    $win.resize(function () {
        resFun();
    });
})();

/**
 * ПРИХОВУВАННЯ ПРЕЛОАДЕРА ПІСЛЯ ЗАВАНТАЖЕННЯ СТОРІНКИ
 * Використовує DOMContentLoaded для швидшого приховування
 */
document.addEventListener('DOMContentLoaded', function() {
    // Приховати прелоадер майже одразу
    setTimeout(function() {
        const preloader = document.getElementById('preloader');
        if (preloader) {
            preloader.style.transition = 'opacity 0.5s ease';
            preloader.style.opacity = '0';
            setTimeout(() => preloader.style.display = 'none', 500);
        }
    }, 100);
});

/**
 * АНАЛІТИКА e-commerce - Google & Yandex
 * Відстеження переглядів продуктів та додавання в кошик
 */
(function() {
    // Встановлення позиції списку для аналітики
    document.querySelectorAll('[data-id]').forEach((el, index) => {
        el.setAttribute('data-id', index);
    });
})();

/**
 * ТАЙМЕР ДЛЯ ПРОМОЦІОННИХ ТОВАРІВ
 * Оновлює зворотний відлік кожну годину
 */
function updateTimer(uniqueId) {
    const timerElement = document.querySelector(`#timer-${uniqueId}`);
    if (!timerElement || !timerElement.dataset.end) return;
    
    let end = parseFloat(timerElement.dataset.end);
    if (isNaN(end)) return;

    const now = new Date().getTime() / 1000;
    let timeLeft = end - now;
    
    if (timeLeft <= 0) {
        end = now + (15 * 24 * 60 * 60); // Скинути на 15 днів
        timerElement.dataset.end = end;
        timeLeft = end - now;
    }

    const days = Math.floor(timeLeft / (24 * 60 * 60));
    const dayWord = days === 1 ? 'День' : days >= 2 && days <= 4 ? 'Дня' : 'Днів';

    const daysElement = timerElement.querySelector('.days');
    const labelElement = timerElement.querySelector('.flip-label');
    
    if (daysElement && labelElement) {
        daysElement.innerText = days;
        labelElement.innerText = dayWord;
    }
}

// Запуск таймерів після завантаження DOM
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.expire_date').forEach(timer => {
        const uniqueId = timer.id.replace('timer-', '');
        updateTimer(uniqueId);
        // Оновити кожну годину
        setInterval(() => updateTimer(uniqueId), 3600000);
    });
});

/**
 * УПРАВЛІННЯ ФАЙЛАМИ COOKIES
 */
(function() {
    function acceptCookies() {
        document.cookie = 'cookie_accepted=true; max-age=31536000; path=/';
        const cookieNotice = document.getElementById('cookie-notice');
        if (cookieNotice) cookieNotice.style.display = 'none';
    }

    window.acceptCookies = acceptCookies;

    window.addEventListener('load', function() {
        const cookieNotice = document.getElementById('cookie-notice');
        if (cookieNotice) {
            if (document.cookie.indexOf('cookie_accepted=true') === -1) {
                cookieNotice.style.display = 'block';
            } else {
                cookieNotice.style.display = 'none';
            }
        }
    });
})();

/**
 * УПРАВЛІННЯ ПОЛЕМ КОНТАКТУ
 * Показує/приховує поле ім'я користувача на основі методу контакту
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
 * ОБРОБНИК ФОРМИ ЗАМОВЛЕННЯ
 * Керування станом чекбоксів та кнопки відправлення
 */
$(document).ready(function() {
    const $orderForm = $('#formToSend');
    const $checkbox = $orderForm.find('input:checkbox');
    const $submitBtn = $orderForm.find('input[type=submit]');
    const $valInput = $orderForm.find('input[type=hidden].valTrFal');

    // Закриття модального вікна замовлення
    $('#order .close_popup').on('click', function() {
        $checkbox.removeAttr("checked");
        $submitBtn.attr('disabled', 'disabled');
        $valInput.val('valTrFal_disabled');
    });

    // Керування станом кнопки на основі чекбокса
    $checkbox.on('change', function() {
        if ($(this).is(':checked')) {
            $submitBtn.removeAttr('disabled');
            $valInput.val('valTrFal_true');
        } else {
            $submitBtn.attr('disabled', 'disabled');
            $valInput.val('valTrFal_disabled');
        }
    });

    // Обробка відправлення форми
    $('#send').on('click', function() {
        if ($orderForm.find('input[type=text]').val() !== "") {
            $orderForm.find('input[type=hidden].valTrFal').remove();
            $orderForm.find('.font-geometria-light').remove();
            $('#overflw .basket_num_buttons').remove();
        }
    });
});

/**
 * МАСКА ДЛЯ НОМЕРА ТЕЛЕФОНУ
 */
$(document).ready(function() {
    const phoneInput = document.getElementById('phoneNumber');
    if (phoneInput) {
        $(phoneInput).inputmask("+7(999)999-99-99");
    }
});

/**
 * СЛАЙДЕРИ ДЛЯ ВІДЕОКОНТЕНТУ
 * Активація вбудованих відео при кліку
 */
$(function() {
    function setupVideoSlide(slideId) {
        const $slide = $("#" + slideId);
        if ($slide.length === 0) return;

        $slide.on("click", function() {
            const $elm = $(this);
            const conts = $elm.contents();
            let ifr = null;

            // Знайти прихований HTML коментар з iframe
            for (let i = 0; i < conts.length; i++) {
                if (conts[i].nodeType === 8) {
                    ifr = conts[i].textContent;
                    break;
                }
            }

            if (ifr) {
                $elm.addClass("player").html(ifr);
                $elm.off("click");
            }
        });
    }

    setupVideoSlide("slide1");
    setupVideoSlide("slide2");
});

/**
 * ОПТИМІЗОВАНОЇ ІНІЦІАЛІЗАЦІЯ СЛАЙДЕРІВ
 * ВАЖЛИВО: Слайдери ініціалізуються в готовності DOM, не в load
 * Це дозволяє слайдерам з'явитися дуже швидко
 */
$(document).ready(function() {
    // Ініціалізація слайдерів за їхнім data-атрибутом
    const sliders = $('[data-flexslider-init="true"]');
    
    if (sliders.length > 0) {
        sliders.each(function(index) {
            const $slider = $(this);
            
            // Конфігурація слайдера для оптимальної роботи
            const config = {
                animation: "slide",
                animationSpeed: 500,
                animationLoop: true,
                slideshow: true,
                slideshowSpeed: 7000,
                pauseOnHover: true,
                pauseOnAction: true,
                controlNav: false,
                touch: true,
                keyboard: true,
                prevText: "←",
                nextText: "→",
                start: function(slider) {
                    // Логування для дебагування (можна видалити в продакшені)
                    if (window.console) {
                        console.log('Slider ' + (index + 1) + ' инициализирован успешно');
                    }
                }
            };

            // Перевірка ширини екрана для деякої конфігурації
            if ($(window).width() < 768) {
                config.slideshow = false; // Відключити автоматичний слайдшоу на мобілі
            }

            try {
                $slider.flexslider(config);
            } catch (e) {
                if (window.console && window.console.error) {
                    console.error('Error initializing slider ' + (index + 1), e);
                }
            }
        });
    }
});

/**
 * ПОЛІПШЕННЯ ПРОДУКТИВНОСТІ
 * Відновлення макета після зміни розміру вікна
 */
(function() {
    let resizeTimer;
    const $window = $(window);

    $window.on('resize', function() {
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(function() {
            // Перереалізуємо слайдери, якщо потрібно
            // (опціонально, залежить від вимог дизайну)
        }, 250);
    });
})();

/**
 * ЗАПОБІГАННЯ LAYOUT SHIFT
 * Встановлення мінімальних висот для контейнерів
 */
document.addEventListener('DOMContentLoaded', function() {
    // Встановити мінімальну висоту для слайдерів
    const sliders = document.querySelectorAll('.flexslider');
    sliders.forEach(slider => {
        if (!slider.style.minHeight) {
            slider.style.minHeight = '300px';
        }
    });
});

/*
    * Sticky Nav
    * Owl Slider
    * MAP Section
    * Theme Slider
    * Theme Variables
    * external js: isotope.pkgd.js
    * Smooth Scroll
    * Slider
    * Scroll Top
    * Skill Bars
/*
|--------------------------------------------------------------------------
| Sticky Nav
|--------------------------------------------------------------------------
|
|
|
*/
'use strict'

/*-------------------------------------
 * Scroll navigation active
 -------------------------------------*/
$(function () {
    if ($(window).width() > 760) {
      var topMenuHeight=$('header').height() - 0
    }

    else {
      var topMenuHeight=$('header .navbar').height() - 0
    }

    var lastId,
    topMenu=$('.main'),
    menuItems=topMenu.find("a[href*='#']"),
    scrollItems=menuItems.map(function () {
        var item=$($(this).attr('href').replace('/#', '#'));
        if (item.length) {
          return item;
        }
      });;
    menuItems.on('click', function (e) {
        $('.navbar-toggle').trigger('click');
        var href=$(this).attr('href'),
        offsetTop=href==='#'? 0 : $(href).offset().top - topMenuHeight + 1;
        $('html, body').stop().animate({
            scrollTop: offsetTop
        }, 300);
        e.preventDefault();
    }); $(window).scroll(function () {
        var fromTop=$(this).scrollTop() + topMenuHeight var cur=scrollItems.map(function () {
            if ($(this).offset().top < fromTop) return this
          }

        ) cur=cur[cur.length - 1] var id=cur && cur.length ? cur[0].id : ''

        if (lastId !==id) {
          lastId=id menuItems .parent() .removeClass('active') .end() .filter("[href='#"+ id + "']") .parent() .addClass('active')
        }
      }

    )
  }

)
/*-------------------------------------
     * Animate scroll
     -------------------------------------*/
/*
    $.fn.animated = function () {
        $(this).each(function () {
            var ths = $(this),
                anim = ths.attr('data-animate'),
                dur = ths.attr('data-duration');
            ths.css('opacity', '0').addClass('animated');
            ths.waypoint(function (dir) {
                    if (dir === 'down') {
                        ths.addClass(anim).css({'opacity': '1', 'animation-duration': '' + dur + 's'});
                    }
                    else {
                        ths.removeClass(anim).css('opacity', '0');
                    }
                }, {
                    offset: '95%'
                });
        });
    };
	$.fn.animatedOne = function () {
        $(this).each(function () {
            var ths = $(this),
                anim = ths.attr('data-animate'),
                dur = ths.attr('data-duration');
            ths.css('opacity', '0').addClass('animated')
                .waypoint(function (dir) {
                    if (dir === 'down') {
                        ths.addClass(anim).css({'opacity': '1', 'animation-duration': '' + dur + 's'});
                    }
                    else {
                        return false
                    }
                }, {
                    offset: '95%'
                });
        });
    };

*/

/*
|--------------------------------------------------------------------------
| Testimonial Slider
|--------------------------------------------------------------------------
|
|
|
*/

/*
|--------------------------------------------------------------------------
| Smooth Scroll
|--------------------------------------------------------------------------
|
|
|
*/
$(function () {
    $('a1[href*="#"]:not([href="#"])').on('click', function () {
        if (location.pathname.replace(/^\//, '')==this.pathname.replace(/^\//, '') && location.hostname==this.hostname) {
          var target=$(this.hash) target=target.length ? target : $('[name='+ this.hash.slice(1) + ']') if (target.length) {
            $('html, body').animate( {
                scrollTop: target.offset().top,
              }

              ,
              1000,
            ) return false
          }
        }
      }

    )
  }

)
/*-------------------------------------
  * Start function
-------------------------------------*/

var $win=$(window),
$winW=$win.width(),
$doc=$(document),
$headerH=$doc.height() function resFun() {
  if ($winW > 560) {
    //  $('.animate').animated();
    //  $('.animate--one').animatedOne();
  }

  if ($winW < 992) {
    $('.cart_full span').remove()
  }
}

$doc.on('scroll', function () {
    var $hs=$doc.height() if ($hs !=$headerH && $winW > 768) {
      //  $('.animate').animated();
      //  $('.animate--one').animatedOne();
      $headerH=$hs
    }
  }

) resFun() $win.resize(function () {
    resFun()
  }

) var priceList= {
  p001: {

    id: '001',
    catalogNumber: '1438',
    subid: {}

    ,
    name: 'No.3 Hair Perfector 100 ml',
    price: '2650',
  }

  ,
  p002: {

    id: '002',
    catalogNumber: '',
    subid: {}

    ,
    name: 'No.2 Bond Perfector',
    price: '14900',
  }

  ,
  p003: {

    id: '003',
    catalogNumber: '',
    subid: {}

    ,
    name: 'No.1 Bond Multiplier',
    price: '14900',
  }

  ,
  p004: {

    id: '004',
    catalogNumber: '1956',
    subid: {}

    ,
    name: 'Traveling Stylist Kit',
    price: '11990',
  }

  ,
  p005: {

    id: '005',
    catalogNumber: '2019',
    subid: {}

    ,
    name: 'Salon Intro Kit',
    price: '32550',
  }

  ,
  p006: {

    id: '006',
    catalogNumber: '3298',
    subid: {}

    ,
    name: 'No.4 Bond Maintenance Shampoo 250 ml',
    price: '2890',
  }

  ,
  p007: {

    id: '007',
    catalogNumber: '3235',
    subid: {}

    ,
    name: 'No.5 Bond Maintenance Conditioner 250 ml',
    price: '2990',
  }

  ,
  p008: {

    id: '008',
    catalogNumber: '4981',
    subid: {}

    ,
    name: 'No.6 Bond Smoother 100 ml',
    price: '3390',
  }

  ,
  p009: {

    id: '009',
    catalogNumber: '3827',
    subid: {}

    ,
    name: 'No.7 Bonding Oil 30 ml',
    price: '3290',
  }

  ,
  p010: {

    id: '010',
    catalogNumber: '4080',
    subid: {}

    ,
    name: 'No.0 Olaplex Hair Treatment',
    price: '2990',
  }

  ,
  p011: {

    id: '011',
    catalogNumber: '4079',
    subid: {}

    ,
    name: 'No.3 Hair Perfector 250ml',
    price: '6190	',
  }

  ,
  p012: {

    id: '012',
    catalogNumber: '4081',
    subid: {}

    ,
    name: 'No.4 Bond Maintenance Shampoo 2000 ml',
    price: '13990',
  }

  ,
  p013: {

    id: '013',
    catalogNumber: '4082',
    subid: {}

    ,
    name: 'No.5 Bond Maintenance Conditioner 2000 ml',
    price: '14650',
  }

  ,
  p015: {

    id: '015',
    catalogNumber: '4418',
    subid: {}

    ,
    name: 'No.8 Bond Intense Moisture Mask',
    price: '2990',
  }

  ,
  p016: {

    id: '016',
    catalogNumber: '4587',
    subid: {}

    ,
    name: 'No.4P Olaplex Blonde Enhancer Toning Shampoo',
    price: '3290',
  }

  ,
  p017: {

    id: '017',
    catalogNumber: '4589',
    subid: {}

    ,
    name: 'Olaplex 4-in-1 Moisture Mask',
    price: '7190',
  }

  ,
  p018: {

    id: '018',
    catalogNumber: '',
    subid: {}

    ,
    name: 'No.2 OLAPLEX Bond Perfector',
    price: '25000',
  }

  ,
  p019: {

    id: '019',
    catalogNumber: '4618',
    subid: {}

    ,
    name: 'Olaplex No. 9 Bond Protector Nourishing Hair Serum',
    price: '3390',
  }

  ,
  p020: {

    id: '020',
    catalogNumber: '',
    subid: {}

    ,
    name: "Olaplex Pro Holiday Kit 2022 'Hair Rescue Kit' | Подарочный набор 'Интенсивное восстановление волос'",
    price: '5600',
  }

  ,
  p021: {

    id: '021',
    catalogNumber: '4907',
    subid: {}

    ,
    name: 'No.4 Bond Maintenance Shampoo 1000 ml',
    price: '9790',
  }

  ,
  p022: {

    id: '022',
    catalogNumber: '4906',
    subid: {}

    ,
    name: 'No.5 Bond Maintenance Conditioner 1000 ml',
    price: '9990',
  }

  ,
  p023: {

    id: '023',
    catalogNumber: '5520',
    subid: {}

    ,
    name: 'Olaplex Broad Spectrum Chelating Treatment',
    price: '5590',
  }

  ,
  p024: {

    id: '024',
    catalogNumber: '5563',
    subid: {}

    ,
    name: 'Olaplex No.4C Bond Maintenance Clarifying Shampoo',
    price: '2990',
  }

  ,
  p025: {

    id: '025',
    catalogNumber: '5564',
    subid: {}

    ,
    name: 'Olaplex No. 4D Clean Volume Detox Dry Shampoo',
    price: '2990',
  }

  ,
  p026: {

    id: '026',
    catalogNumber: '5521',
    subid: {}

    ,
    name: 'Olaplex Unbreakable Blondes Mini Kit',
    price: '2850',
  }

  ,
  p027: {

    id: '027',
    catalogNumber: '7359',
    subid: {}

    ,
    name: 'No.5 Bond Maintenance Conditioner 100 ml',
    price: '1750',
  }

  ,
  p028: {

    id: '028',
    catalogNumber: '7718',
    subid: {}

    ,
    name: 'No.3 Hair Perfector 50 ml',
    price: '1550',
  }

  ,
  p029: {

    id: '029',
    catalogNumber: '3827',
    subid: {}

    ,
    name: 'No.7 Bonding Oil 60 ml',
    price: '5590',
  }

  ,
  p030: {

    id: '030',
    catalogNumber: '',
    subid: {}

    ,
    name: 'OLAPLEX Набор для укрепления и блеска волос',
    price: '6450',
  }

  ,
  p031: {

    id: '031',
    catalogNumber: '',
    subid: {}

    ,
    name: 'Olaplex Nº.10 Bond Shaper™ Curl Defining Gel',
    price: '3890',
  }

  ,
  p032: {

    id: '032',
    catalogNumber: '',
    subid: {}

    ,
    name: 'No.4 Bond Maintenance Shampoo 100 ml',
    price: '1650',
  }

  ,
}

jQuery(document).ready(function ($) {
    $(window).on('load', function () {
        setTimeout(function () {
            $('#preloader').fadeOut('slow', function () {}

            )
          }

          , 100)
      }

    )
  }

)

/*-------------------------------------
  * Электронная коммерция - аналитика
-------------------------------------*/
//Проставляю индификаторы для каждого блока для "list_position" в gtag('event')
$('[data-id]').each(function (e) {
    $(this).attr('data-id', e)
  }

) // Событие при просмотре (наведении) товара

$('#max-featured-section').each(function () {
    $(this) .children('#max-feature-section') .on('mouseenter', function () {
        var onclickBtnEvView=$(this).find('button').attr('onclick') var searchEvView="categoryFilter('"
        var argsStartEvView=onclickBtnEvView.indexOf(searchEvView) + searchEvView.length onclickBtnEvView=onclickBtnEvView.substring(argsStartEvView,
          onclickBtnEvView.indexOf(')', argsStartEvView),
        ) var resultsEvView=onclickBtnEvView.split("',") var productIdEvView=resultsEvView[0].replace(/[^\d]/g, '') var productNameEvView=$(this).find('h2').text() var listPositionEvView=$(this).find('.tovar-name').attr('data-id') var productPriceEvView=$(this) .find('.buy > p') .children('strong') .text() .replace(/[^\d]/g, '') // Проверка отправки данных

        // console.log(
        //     'id: ' + productIdEvView,
        //     'Название: ' + productNameEvView,
        //     'Номер блока: ' + listPositionEvView,
        //     'Цена: ' + productPriceEvView
        // );
        googleViewItem.push( {
            id: productIdEvView,
            name: productNameEvView,
            list_name: 'Home page',
            brand: 'Olaplex',
            list_position: listPositionEvView,
            quantity: 1,
            price: productPriceEvView,
          }

        ) yaDetail.push( {
            id: productIdEvView,
            name: productNameEvView,
            price: Number(productPriceEvView),
            brand: 'Olaplex',
          }

        )
      }

    )
  }

) var googleViewItem=[] gtag('event', 'view_item', {
    items: googleViewItem,
  }

) var yaDetail=[] dataLayer.push( {
    ecommerce: {
      detail: {
        products: yaDetail,
      }

      ,
    }

    ,
  }

) // Событие при клике на добавить в корзину

$('.tovar-name').each(function () {
    $(this) .find('.buy') .children('button') .on('click', function () {
        var onclickBtn=$(this).attr('onclick') var search="categoryFilter('"
        var argsStart=onclickBtn.indexOf(search) + search.length onclickBtn=onclickBtn.substring(argsStart,
          onclickBtn.indexOf(')', argsStart),
        ) var results=onclickBtn.split("',") var productId=results[0].replace(/[^\d]/g, '') var productName=$(this) .parent('.buy') .parent('.tovar-name') .children('h2') .text() var listPosition=$(this) .parent('.buy') .parent('.tovar-name') .attr('data-id') var productPrice=$(this) .prev() .children('strong') .text() .replace(/[^\d]/g, '') // Проверка отправляемых данных
        // console.log(
        //     'id: ' + productId,
        //     'Название: ' + productName,
        //     'Номер блока: ' + listPosition,
        //     'Цена: ' + productPrice
        // );

        googleAddToCart.push( {
            id: productId,
            name: productName,
            list_name: 'Home page',
            brand: 'Olaplex',
            list_position: listPosition,
            quantity: 1,
            price: productPrice,
          }

        ) yaAddToCart.push( {
            id: productId,
            name: productName,
            price: Number(productPrice),
            brand: 'Olaplex',
            quantity: 1,
          }

        )
      }

    )
  }

) // google аналитика

var googleAddToCart=[] gtag('event', 'add_to_cart', {
    items: googleAddToCart,
  }

) // yandex аналитика

var yaAddToCart=[] dataLayer.push( {
    ecommerce: {
      add: {
        products: yaAddToCart,
      }

      ,
    }

    ,
  }

) function updateTimer(uniqueId) {
  const timerElement=document.querySelector(`#timer-$ {
      uniqueId
    }

    `) if ( !timerElement || !timerElement.dataset.end) return let end=parseFloat(timerElement.dataset.end) if (isNaN(end)) return // Пропускаем, если end невалиден

  let now=new Date().getTime() / 1000 let timeLeft=end - now if (timeLeft <=0) {
    end=now+15 * 24 * 60 * 60 timerElement.dataset.end=end timeLeft=end - now
  }

  let days=Math.floor(timeLeft / (24 * 60 * 60)) let dayWord=days===1 ? 'День' : days>=2 && days <=4 ? 'Дня' : 'Дней'

  const daysElement=timerElement.querySelector('.days') const labelElement=timerElement.querySelector('.flip-label') if (daysElement && labelElement) {
    daysElement.innerText=days labelElement.innerText=dayWord
  }
}

document.addEventListener('DOMContentLoaded', ()=> {
    document.querySelectorAll('.expire_date').forEach(timer=> {
        const uniqueId=timer.id.replace('timer-', '') updateTimer(uniqueId) // Немедленное обновление
        setInterval(()=> updateTimer(uniqueId), 3600000) // Обновление раз в час
      }

    )
  }

) function acceptCookies() {
  document.cookie='cookie_accepted=true; max-age=31536000; path=/'
  document.getElementById('cookie-notice').style.display='none'
}

window.onload=function () {
  if (document.cookie.indexOf('cookie_accepted=true')===-1) {
    document.getElementById('cookie-notice').style.display='block'
  }

  else {
    document.getElementById('cookie-notice').style.display='none'
  }
}
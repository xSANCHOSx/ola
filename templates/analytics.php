<script type="text/javascript">
    // dataLayer объявляется синхронно и как можно раньше: события электронной
    // коммерции (detail/add/purchase) из cart.js и main.js могут сработать ещё
    // до полной загрузки страницы (например наведение на карточку товара).
    // Яндекс.Метрика обработает уже накопленный к моменту инициализации массив.
    window.dataLayer = window.dataLayer || [];
</script>

<!-- Yandex.Metrika counter -->
<script type="text/javascript">
    window.addEventListener('load', function() {
        (function(m, e, t, r, i, k, a) {
            m[i] = m[i] || function() {
                (m[i].a = m[i].a || []).push(arguments)
            }
            m[i].l = 1 * new Date()
            for (var j = 0; j < document.scripts.length; j++) {
                if (document.scripts[j].src === r) {
                    return
                }
            }
            k = e.createElement(t), a = e.getElementsByTagName(t)[0], k.async = 1, k.src = r, a.parentNode.insertBefore(k, a)
        })
        (window, document, "script", "https://mc.yandex.ru/metrika/tag.js", "ym")

        ym(48443993, "init", {
            clickmap: true,
            trackLinks: true,
            accurateTrackBounce: true,
            webvisor: true,
            ecommerce: "dataLayer"
        });
    });
</script>
<noscript>
    <div><img src="https://mc.yandex.ru/watch/48443993" style="position:absolute; left:-9999px;" alt="" /></div>
</noscript>
<!-- /Yandex.Metrika counter -->

<!-- Google tag (gtag.js) -->
<script>
    window.addEventListener('load', function() {
        var script = document.createElement('script');
        script.async = true;
        script.src = "https://www.googletagmanager.com/gtag/js?id=G-MGSPT3K11Y";
        document.head.appendChild(script);

        window.dataLayer = window.dataLayer || []
        function gtag() {
            dataLayer.push(arguments)
        }
        gtag('js', new Date())
        gtag('config', 'G-MGSPT3K11Y');
    });
</script>

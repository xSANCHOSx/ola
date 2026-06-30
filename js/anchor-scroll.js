/**
 * Unified Anchor Scroll Script for Olaplex Shop (Fixed Version)
 * 
 * Fixes:
 * 1. Strictly prevents adding hash to URL using e.preventDefault().
 * 2. Adds 20px extra offset to the header height.
 * 3. Closes the mobile menu after clicking a link.
 * 4. Works consistently across all devices.
 * 5. **NEW**: Ensures URL hash is removed from address bar using History API.
 */
(function ($) {
    'use strict';

    $(function () {
        /**
         * Calculate current header height dynamically + 20px buffer.
         */
        function getHeaderHeight() {
            const mobileNavbar = $('header .navbar');
            const mainHeader = $('header');
            let height = 0;
            
            if ($(window).width() <= 760 && mobileNavbar.length) {
                height = mobileNavbar.outerHeight();
            } else if (mainHeader.length) {
                height = mainHeader.outerHeight();
            }
            
            return height; // Add 20px extra offset as requested
        }

        /**
         * Smooth scroll to a target element with offset.
         */
        function scrollToAnchor(targetSelector) {
            const $target = $(targetSelector);
            if ($target.length) {
                const offsetTop = $target.offset().top - getHeaderHeight();

                $('html, body').stop().animate({
                    scrollTop: offsetTop
                }, 600, function() {
                    // Callback after animation completes
                    // Ensure the URL hash is removed from the address bar
                    if (history.replaceState) {
                        history.replaceState(null, null, window.location.pathname + window.location.search);
                    }
                });
            }
        }

        // Use a more specific selector to avoid interfering with other functional links
        // and ensure we catch all anchor links in the navigation
        $(document).on('click', 'a[href*="#"]', function (e) {
            const href = $(this).attr('href');
            
            // Extract the anchor ID
            const hashIndex = href.indexOf('#');
            if (hashIndex === -1) return;
            
            const anchorId = href.substring(hashIndex + 1);
            
            // Skip if it's just "#" or a script-based link
            if (!anchorId || anchorId === "" || $(this).attr('href') === 'javascript:void(0)') {
                return;
            }

            const targetSelector = '#' + anchorId;
            const $target = $(targetSelector);

            // If the target exists on the current page
            if ($target.length) {
                // 1. Prevent URL hash update
                e.preventDefault(); 
                e.stopPropagation();

                // 2. Close mobile menu if open
                const $mobileMenu = $('#bs-example-navbar-collapse-1');
                if ($mobileMenu.hasClass('show') || $mobileMenu.hasClass('in')) {
                    // Try both bootstrap 3 and 4/5 methods to be safe
                    $('.navbar-toggle, .navbar-toggler').trigger('click');
                }

                // 3. Scroll to target
                scrollToAnchor(targetSelector);
            } else if (history.replaceState) {
                // If the anchor target doesn't exist, but it's still an anchor link,
                // ensure the hash is removed if it somehow got into the URL.
                history.replaceState(null, null, window.location.pathname + window.location.search);
            }
        });

        // Handle initial load with hash (optional but recommended for UX)
        if (window.location.hash) {
            setTimeout(function() {
                scrollToAnchor(window.location.hash);
                // Ensure hash is removed after initial load scroll as well
                if (history.replaceState) {
                    history.replaceState(null, null, window.location.pathname + window.location.search);
                }
            }, 500);
        }
    });
})(jQuery);

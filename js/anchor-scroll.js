    /**
 * Unified Anchor Scroll Script for Olaplex Shop
 * 
 * Features:
 * 1. Prevents adding hash to URL on click.
 * 2. Accounts for fixed header height on mobile and desktop.
 * 3. Works consistently across all devices.
 * 4. Deduplicated and clean implementation.
 */
(function ($) {
    'use strict';

    $(function () {
        /**
         * Calculate current header height dynamically.
         * Accounts for potential differences between mobile and desktop headers.
         */
        function getHeaderHeight() {
            // Check for the mobile navbar first, then fallback to main header
            const mobileNavbar = $('header .navbar');
            const mainHeader = $('header');
            
            if ($(window).width() <= 760 && mobileNavbar.length) {
                return mobileNavbar.outerHeight();
            }
            return mainHeader.length ? mainHeader.outerHeight() : 0;
        }

        /**
         * Smooth scroll to a target element with offset.
         * @param {string} targetSelector - CSS selector for the target element.
         */
        function scrollToAnchor(targetSelector) {
            const $target = $(targetSelector);
            if ($target.length) {
                const headerHeight = getHeaderHeight();
                const offsetTop = $target.offset().top - headerHeight;

                $('html, body').stop().animate({
                    scrollTop: offsetTop
                }, 500); // 500ms for a smooth transition
            }
        }

        // Handle clicks on anchor links
        $(document).on('click', 'a[href*="#"]', function (e) {
            const href = $(this).attr('href');
            
            // Extract the anchor part (e.g., from "/#section" or "#section")
            const anchorMatch = href.match(/#([^?\/]*)/);
            if (!anchorMatch) return;

            const anchorId = anchorMatch[1];
            
            // If it's just "#", it's likely a functional link (like "top" or a script trigger)
            if (!anchorId) {
                // If it's exactly "#", we might want to scroll to top or just let it be
                // But usually, we just ignore it to avoid breaking other JS functionality
                return;
            }

            const targetSelector = '#' + anchorId;
            const $target = $(targetSelector);

            // If the target exists on the current page
            if ($target.length) {
                e.preventDefault(); // Prevent default jump and URL hash change

                // Close mobile menu if open (specific to Olaplex Shop bootstrap setup)
                const $mobileMenu = $('#bs-example-navbar-collapse-1');
                if ($mobileMenu.hasClass('show')) {
                    $('.navbar-toggle').trigger('click');
                }

                scrollToAnchor(targetSelector);
            }
        });

        // Optional: Handle initial load if URL contains a hash
        // Note: The user asked to NOT add hash on click, but if they come from an external link with a hash,
        // we should still scroll them correctly.
        if (window.location.hash) {
            setTimeout(function() {
                scrollToAnchor(window.location.hash);
            }, 500); // Small delay to ensure content is rendered
        }
    });
})(jQuery);

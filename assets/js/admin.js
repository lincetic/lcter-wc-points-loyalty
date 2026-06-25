/**
 * LCTER WC Points Loyalty - Admin JavaScript
 */

(function($) {
    'use strict';

    $(document).ready(function() {
        // Add product data tabs
        var $tabs = $('.woocommerce_data_tabs');
        
        if ($tabs.length) {
            $tabs.append(
                '<li class="lcter_wcpl_tab"><a href="#lcter_wcpl_product_panel">' +
                'Puntos de Lealtad' +
                '</a></li>'
            );
        }

        // Ensure the panel is shown when tab is clicked
        $('a[href="#lcter_wcpl_product_panel"]').on('click', function(e) {
            e.preventDefault();
            $('#lcter_wcpl_product_panel').show();
            $('.panel').not('#lcter_wcpl_product_panel').hide();
        });
    });

})(jQuery);

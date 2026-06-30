/**
 * LCTER WC Points Loyalty - Admin JavaScript
 */

(function($) {
    'use strict';

    $(document).ready(function() {
        function toggleRewardFields() {
            $('.lcter-wcpl-reward-field').toggle($('#lcter_wcpl_is_reward').is(':checked'));
        }

        $('#lcter_wcpl_is_reward').on('change', toggleRewardFields);
        toggleRewardFields();
    });

})(jQuery);

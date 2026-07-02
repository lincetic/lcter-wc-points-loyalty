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

		$('#lcter_wcpl_apply_suggested_cost').on('click', function() {
			var suggestedCost = parseInt($(this).data('suggested-cost'), 10);

			if (suggestedCost > 0) {
				$('#lcter_wcpl_reward_points_cost').val(suggestedCost).trigger('change');
			}
		});

    });

})(jQuery);

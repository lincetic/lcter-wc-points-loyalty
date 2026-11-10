/** LCTER WC Points Loyalty admin. */
(function($) {
	'use strict';
	$(function() {
		function toggleRewardFields() { $('.lcter-wcpl-reward-field').toggle($('#lcter_wcpl_is_reward').is(':checked')); }
		$('#lcter_wcpl_is_reward').on('change', toggleRewardFields); toggleRewardFields();
		$('#lcter_wcpl_apply_suggested_cost').on('click', function() { var cost = parseInt($(this).data('suggested-cost'), 10); if (cost > 0) { $('#lcter_wcpl_reward_points_cost').val(cost).trigger('change'); } });

		var $box = $('#lcter-wcpl-initial-bonus');
		if (!$box.length) { return; }
		var url = $box.data('ajax-url'), nonce = $box.data('nonce'), $progress = $('#lcter-wcpl-bonus-progress');
		var runId = parseInt($progress.data('run-id'), 10) || 0, busy = false, confirmationHash = '';
		function post(name, data) { return $.post(url, $.extend({action: 'lcter_wcpl_bonus_' + name, nonce: nonce}, data || {})); }
		function token() { return 'r' + Date.now().toString(36) + Math.random().toString(36).slice(2); }
		function render(run) {
			runId = parseInt(run.id, 10);
			confirmationHash = run.confirmation_hash || confirmationHash;
			$progress.removeAttr('hidden').data('run-id', runId).text('Ejecución #' + runId + ' · ' + run.status + ' · ' + run.processed_count + '/' + run.total_targets + ' · bonificados: ' + run.bonused_count + ' · omitidos: ' + run.skipped_count + ' · fallidos: ' + run.failed_count + ' · puntos: ' + run.total_points);
			$('#lcter-wcpl-bonus-pause').prop('hidden', run.status !== 'running');
			$('#lcter-wcpl-bonus-resume').prop('hidden', run.status !== 'paused' && run.status !== 'errors');
			if (run.status === 'prepared') {
				$('#lcter-wcpl-bonus-cutoff').prop('disabled', true);
				$('#lcter-wcpl-bonus-preview-result').removeAttr('hidden').text('Ejecución preparada #' + runId + ' · corte UTC: ' + run.cutoff_at + ' · beneficiarios: ' + (run.eligible !== undefined ? run.eligible : run.total_targets) + ' · ya bonificados: ' + (run.already_bonused !== undefined ? run.already_bonused : 0) + ' · pendientes: ' + (run.pending !== undefined ? run.pending : run.total_targets) + ' · importe: ' + run.bonus_points + '. Confirma exactamente esta población congelada.');
				$('#lcter-wcpl-bonus-confirm-wrap').removeAttr('hidden');
			}
		}
		function next() {
			if (busy || !runId) { return; } busy = true;
			post('process', {run_id: runId, request_token: token()}).done(function(r) {
				if (r.success) { render(r.data); if (r.data.status === 'running' || r.data.status === 'prepared') { window.setTimeout(next, 250); } }
			}).fail(function(xhr) { $progress.removeAttr('hidden').text((xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) || 'El lote se interrumpió; puede reanudarse.'); }).always(function() { busy = false; });
		}
		$('#lcter-wcpl-bonus-preview').on('click', function() {
			post('preview', {cutoff: $('#lcter-wcpl-bonus-cutoff').val()}).done(function(r) { if (!r.success) { return; } var d = r.data;
				runId = parseInt(d.run_id, 10); confirmationHash = d.confirmation_hash; $('#lcter-wcpl-bonus-cutoff').prop('disabled', true);
				$('#lcter-wcpl-bonus-preview-result').removeAttr('hidden').text('Ejecución preparada #' + runId + ' · corte UTC: ' + d.cutoff_at + ' · beneficiarios: ' + d.eligible + ' · ya bonificados: ' + d.already_bonused + ' · pendientes: ' + d.pending + ' · importe: ' + d.bonus_points + ' · total previsto: ' + d.planned_points + '. ADVERTENCIA: confirma exactamente esta población congelada.');
				$('#lcter-wcpl-bonus-confirm-wrap').removeAttr('hidden');
			});
		});
		$('#lcter-wcpl-bonus-confirm').on('change', function() { $('#lcter-wcpl-bonus-start').prop('disabled', !this.checked); });
		$('#lcter-wcpl-bonus-start').on('click', function() { post('create', {run_id: runId, confirmation_hash: confirmationHash, confirmed: $('#lcter-wcpl-bonus-confirm').is(':checked') ? '1' : '0'}).done(function(r) { if (r.success) { render(r.data); next(); } }); });
		$('#lcter-wcpl-bonus-pause').on('click', function() { post('pause', {run_id: runId}).done(function() { post('status', {run_id: runId}).done(function(r) { if (r.success) { render(r.data); } }); }); });
		$('#lcter-wcpl-bonus-resume').on('click', function() { post('resume', {run_id: runId}).done(function() { busy = false; next(); }); });
		if (runId) { post('status', {run_id: runId}).done(function(r) { if (r.success) { render(r.data); if (r.data.status === 'running') { next(); } } }); }
	});
})(jQuery);

(function ($) {
	'use strict';

	var cfg = window.TSAR_Frontend || {};
	cfg.i18n = cfg.i18n || {};
	var pollTimer = null;
	var clockTimer = null;

	function fmtClock(secs) {
		secs = Math.max(0, Math.floor(secs));
		var h = Math.floor(secs / 3600);
		var m = Math.floor((secs % 3600) / 60);
		var s = secs % 60;
		var pad = function (n) { return n < 10 ? '0' + n : '' + n; };
		return pad(h) + ':' + pad(m) + ':' + pad(s);
	}

	function startClock($state) {
		if (clockTimer) {
			clearInterval(clockTimer);
		}
		var created = parseInt($state.data('tsarCreated'), 10) || 0;
		var $clock = $state.find('[data-tsar-clock]');
		var $desc = $state.find('.tsar-state-desc');
		var window_s = parseInt(cfg.reviewWindow, 10) || 5400;
		var origDesc = $desc.text();

		var tick = function () {
			var elapsed = Math.floor(Date.now() / 1000) - created;
			var remaining = window_s - elapsed;
			if (remaining > 0) {
				$clock.text(fmtClock(remaining));
			} else {
				$clock.text('00:00:00');
				$state.addClass('is-overdue');
				$desc.text(cfg.i18n.overdue || origDesc);
			}
		};
		tick();
		clockTimer = setInterval(tick, 1000);
	}

	function stopTimers() {
		if (pollTimer) { clearInterval(pollTimer); pollTimer = null; }
		if (clockTimer) { clearInterval(clockTimer); clockTimer = null; }
	}

	function renderResult(state, message) {
		stopTimers();
		var $card = $('.tsar-card');
		var cls = 'tsar-state tsar-state-' + state;
		var title = state === 'approved' ? (cfg.i18n.approvedTtl || 'Approved!') : (cfg.i18n.rejectedTtl || 'Rejected');
		var noticeCls = state === 'approved' ? 'tsar-notice-success' : 'tsar-notice-warn';
		var html = ''
			+ '<div class="' + cls + '">'
			+   '<div class="tsar-notice ' + noticeCls + '">'
			+     '<strong>' + $('<i/>').text(title).html() + '</strong>'
			+     '<p>' + $('<i/>').text(message).html() + '</p>'
			+   '</div>'
			+ '</div>';
		$card.find('.tsar-state, .tsar-form, .tsar-paypal-info, .tsar-notice').remove();
		$card.append(html);
	}

	function startPolling() {
		var interval = (parseInt(cfg.pollInterval, 10) || 30) * 1000;
		if (pollTimer) {
			clearInterval(pollTimer);
		}
		pollTimer = setInterval(checkStatus, interval);
	}

	function checkStatus() {
		$.ajax({
			url: cfg.ajaxUrl,
			method: 'POST',
			dataType: 'json',
			data: { action: 'tsar_check_status', nonce: cfg.statusNonce }
		}).done(function (resp) {
			if (!resp || !resp.success) { return; }
			var data = resp.data;
			if (data.is_premium) {
				renderResult('approved', cfg.i18n.approvedMsg || '');
				return;
			}
			if (data.request && data.request.status === 'rejected') {
				renderResult('rejected', cfg.i18n.rejectedMsg || '');
			}
		});
	}

	function transitionToPending(requestId, createdAt) {
		stopTimers();
		var $card = $('.tsar-card');
		var thanks = $('.tsar-form').data('thanksText') || (cfg.i18n.reviewing || 'Reviewing your payment…');
		var html = ''
			+ '<div class="tsar-state tsar-state-pending"'
			+   ' data-tsar-state="pending"'
			+   ' data-tsar-request-id="' + parseInt(requestId, 10) + '"'
			+   ' data-tsar-created="' + parseInt(createdAt, 10) + '">'
			+   '<div class="tsar-spinner" aria-hidden="true"></div>'
			+   '<h2 class="tsar-state-title">' + $('<i/>').text(thanks).html() + '</h2>'
			+   '<p class="tsar-state-desc">' + $('<i/>').text(cfg.i18n.reviewing || '').html() + '</p>'
			+   '<div class="tsar-countdown" data-tsar-countdown>'
			+     '<span class="tsar-countdown-label">' + $('<i/>').text(cfg.i18n.estimated || '').html() + '</span>'
			+     '<span class="tsar-countdown-time" data-tsar-clock>—</span>'
			+   '</div>'
			+ '</div>';
		$card.find('.tsar-form, .tsar-paypal-info, .tsar-notice').remove();
		$card.append(html);
		startClock($card.find('.tsar-state-pending'));
		startPolling();
	}

	$(document).on('submit', '[data-tsar-form]', function (e) {
		e.preventDefault();

		var $form = $(this);
		var $btn = $form.find('.tsar-submit');
		var $fb = $form.find('[data-tsar-feedback]');
		var origLabel = $btn.text();

		$fb.removeClass('is-error is-success').text('');
		$btn.prop('disabled', true).text(cfg.i18n.submitting);

		$.ajax({
			url: cfg.ajaxUrl,
			method: 'POST',
			dataType: 'json',
			data: {
				action: 'tsar_submit_request',
				nonce: cfg.submitNonce,
				plan: $form.find('input[name=tsar_plan]:checked').val() || '',
				paypal_email: $form.find('input[name=tsar_paypal_email]').val() || '',
				confirm: $form.find('input[name=tsar_confirm]').is(':checked') ? '1' : '0'
			}
		}).done(function (resp) {
			if (resp && resp.success) {
				$form.data('thanksText', resp.data.message);
				transitionToPending(resp.data.request_id, resp.data.created_at);
			} else {
				var msg = (resp && resp.data && resp.data.message) || cfg.i18n.genericErr;
				$fb.addClass('is-error').text(msg);
				$btn.prop('disabled', false).text(origLabel);
			}
		}).fail(function (xhr) {
			var msg = cfg.i18n.genericErr;
			if (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) {
				msg = xhr.responseJSON.data.message;
			}
			$fb.addClass('is-error').text(msg);
			$btn.prop('disabled', false).text(origLabel);
		});
	});

	$(function () {
		var $pending = $('.tsar-state-pending');
		if ($pending.length) {
			startClock($pending);
			startPolling();
		}
	});
})(jQuery);

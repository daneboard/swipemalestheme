(function ($) {
	'use strict';

	$(document).on('submit', '[data-tsar-form]', function (e) {
		e.preventDefault();

		var $form = $(this);
		var $btn = $form.find('.tsar-submit');
		var $fb = $form.find('[data-tsar-feedback]');
		var origLabel = $btn.text();

		$fb.removeClass('is-error is-success').text('');
		$btn.prop('disabled', true).text(TSAR_Frontend.i18n.submitting);

		$.ajax({
			url: TSAR_Frontend.ajaxUrl,
			method: 'POST',
			dataType: 'json',
			data: {
				action: 'tsar_submit_request',
				nonce: TSAR_Frontend.nonce,
				plan: $form.find('input[name=tsar_plan]:checked').val() || '',
				paypal_email: $form.find('input[name=tsar_paypal_email]').val() || '',
				note: $form.find('textarea[name=tsar_note]').val() || '',
				confirm: $form.find('input[name=tsar_confirm]').is(':checked') ? '1' : '0'
			}
		}).done(function (resp) {
			if (resp && resp.success) {
				$fb.addClass('is-success').text(resp.data.message);
				$form[0].reset();
			} else {
				var msg = (resp && resp.data && resp.data.message) || TSAR_Frontend.i18n.genericErr;
				$fb.addClass('is-error').text(msg);
			}
		}).fail(function (xhr) {
			var msg = TSAR_Frontend.i18n.genericErr;
			if (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) {
				msg = xhr.responseJSON.data.message;
			}
			$fb.addClass('is-error').text(msg);
		}).always(function () {
			$btn.prop('disabled', false).text(origLabel);
		});
	});
})(jQuery);

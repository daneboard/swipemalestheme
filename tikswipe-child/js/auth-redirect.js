/**
 * Child-theme companion for the parent login/register modal.
 *
 * The parent's js/login-register.js calls window.location.reload(true) on
 * successful login (which keeps the user on the page they opened the modal
 * from — exactly what we want), but the equivalent reload for register is
 * commented out, so a newly-registered user stays in the "complete" state
 * inside the modal until they click around. Hook into the AJAX completion
 * for the register endpoint and reload there too.
 */
(function ($) {
	'use strict';

	$(document).ajaxComplete(function (event, xhr, settings) {
		if (!settings || !settings.data) {
			return;
		}

		var payload = typeof settings.data === 'string' ? settings.data : '';
		if (payload.indexOf('action=wpst_register_member') === -1) {
			return;
		}

		var response;
		try {
			response = JSON.parse(xhr.responseText);
		} catch (e) {
			return;
		}

		if (response && response.error === false) {
			// Same UX as the login flow: stay on whatever page the user was
			// on when they opened the modal.
			window.location.reload(true);
		}
	});
})(jQuery);

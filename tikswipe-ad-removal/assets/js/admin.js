(function ($) {
	'use strict';
	$(document).on('click', '.tsar-confirm', function (e) {
		var msg = $(this).data('confirm') || 'Are you sure?';
		if (!window.confirm(msg)) {
			e.preventDefault();
		}
	});
})(jQuery);

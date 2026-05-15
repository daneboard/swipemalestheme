jQuery(document).ready(function () {

	/**
	 * 1 MONTH
	 */
	if (jQuery('#pwll-1-month-free-trial').attr('value') == 'on') {
		jQuery('.xbox-row-id-pwll-1-month-free-trial-length').show();
		jQuery('.xbox-row-id-pwll-1-month-free-trial-period').show();
	} else {
		jQuery('.xbox-row-id-pwll-1-month-free-trial-length').hide();
		jQuery('.xbox-row-id-pwll-1-month-free-trial-period').hide();
	}
	jQuery('.xbox-field-id-pwll-1-month-free-trial .xbox-sw-inner').on('click', function () {
		jQuery('.xbox-row-id-pwll-1-month-free-trial-length').toggle();
		jQuery('.xbox-row-id-pwll-1-month-free-trial-period').toggle();
	});

	/**
	 * 3 MONTHS
	 */
	if (jQuery('#pwll-3-months-free-trial').attr('value') == 'on') {
		jQuery('.xbox-row-id-pwll-3-months-free-trial-length').show();
		jQuery('.xbox-row-id-pwll-3-months-free-trial-period').show();
	} else {
		jQuery('.xbox-row-id-pwll-3-months-free-trial-length').hide();
		jQuery('.xbox-row-id-pwll-3-months-free-trial-period').hide();
	}
	jQuery('.xbox-field-id-pwll-3-months-free-trial .xbox-sw-inner').on('click', function () {
		jQuery('.xbox-row-id-pwll-3-months-free-trial-length').toggle();
		jQuery('.xbox-row-id-pwll-3-months-free-trial-period').toggle();
	});

	/**
	 * 6 MONTHS
	 */
	if (jQuery('#pwll-6-months-free-trial').attr('value') == 'on') {
		jQuery('.xbox-row-id-pwll-6-months-free-trial-length').show();
		jQuery('.xbox-row-id-pwll-6-months-free-trial-period').show();
	} else {
		jQuery('.xbox-row-id-pwll-6-months-free-trial-length').hide();
		jQuery('.xbox-row-id-pwll-6-months-free-trial-period').hide();
	}
	jQuery('.xbox-field-id-pwll-6-months-free-trial .xbox-sw-inner').on('click', function () {
		jQuery('.xbox-row-id-pwll-6-months-free-trial-length').toggle();
		jQuery('.xbox-row-id-pwll-6-months-free-trial-period').toggle();
	});

	/**
	 * 12 MONTHS
	 */
	if (jQuery('#pwll-12-months-free-trial').attr('value') == 'on') {
		jQuery('.xbox-row-id-pwll-12-months-free-trial-length').show();
		jQuery('.xbox-row-id-pwll-12-months-free-trial-period').show();
	} else {
		jQuery('.xbox-row-id-pwll-12-months-free-trial-length').hide();
		jQuery('.xbox-row-id-pwll-12-months-free-trial-period').hide();
	}
	jQuery('.xbox-field-id-pwll-12-months-free-trial .xbox-sw-inner').on('click', function () {
		jQuery('.xbox-row-id-pwll-12-months-free-trial-length').toggle();
		jQuery('.xbox-row-id-pwll-12-months-free-trial-period').toggle();
	});

});
/**
 * Paywall modal frontend.
 *
 * Plans and the "Start Membership" CTA are plain anchors pointing at
 * /subscription (rendered server-side in paywall-modal.php), so this file is
 * down to modal show/hide + the on-thumbnail "premium" badge injection that
 * runs for non-premium users.
 *
 * @package Paywall/JS
 */

jQuery(document).ready(function () {
	if (!pwll_ajax_var.has_premium_access) {
		jQuery('.premium-badge').each(function () {
			if (pwll_ajax_var.badge_featured_images) {
				var featured_image = jQuery(this);
				featured_image.before('<div class="pwll-badge"><span class="badge-text">' + pwll_ajax_var.badge_text + '</span></div>');
			}
		});
	}

	jQuery('.locked-button').hover(function (event) {
		if (event.type == "mouseenter") {
			jQuery('.lock-top').addClass('loaded');
		}
		if (event.type == "mouseleave") {
			jQuery('.lock-top').removeClass('loaded');
		}
	});

	jQuery(document).on('click', '.open-pwll-box', function (e) {
		e.preventDefault();
		jQuery('body').css('overflow-y', 'hidden');
		jQuery('.pwll-modal-bg').show();
		jQuery('.pwll-unlock-box').show();
	});

	jQuery('.pwll-close-modal').on('click', function (e) {
		e.preventDefault();
		jQuery('body').css('overflow-y', 'visible');
		jQuery('.pwll-modal-bg').hide();
		jQuery('.pwll-unlock-box').hide();
	});

	jQuery('.pwll-modal-bg').on('click', function () {
		jQuery('body').css('overflow-y', 'visible');
		jQuery(this).hide();
		jQuery('.pwll-unlock-box').hide();
	});
});

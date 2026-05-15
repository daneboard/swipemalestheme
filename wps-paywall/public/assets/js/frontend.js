/**
 * Plugin Template frontend js.
 *
 *  @package Paywall/JS
 */

jQuery(document).ready(function () {
	if (!pwll_ajax_var.has_premium_access) {
		jQuery('.premium-badge').each(function () {
			if (pwll_ajax_var.badge_featured_images) {
				var featured_image = jQuery(this);
				featured_image.before('<div class="pwll-badge"><span class="badge-icon">' + pwll_ajax_var.badge_icon_svg + '</span><span class="badge-text">' + pwll_ajax_var.badge_text + '</span></div>');
			}
		});
		// }
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
		var first_plan = jQuery('.pwll-pricing-plan').first();
		first_plan.trigger('click');
	});

	jQuery('.pwll-close-modal').click(function (e) {
		e.preventDefault();
		jQuery('body').css('overflow-y', 'visible');
		jQuery('.pwll-modal-bg').hide();
		jQuery('.pwll-unlock-box').hide();
	});

	jQuery('.pwll-modal-bg').click(function (e) {
		jQuery('body').css('overflow-y', 'visible');
		jQuery(this).hide();
		jQuery('.pwll-unlock-box').hide();
	});

	jQuery('input#createaccount').click();

	jQuery('.start-membership-button').on('click', function (e) {
		e.preventDefault();
		var checked_plan = jQuery('.pwll-pricing-plan.checked');
		var checked_product_id = checked_plan.data('product-id');
		var checked_variation_id = checked_plan.data('variation-id');
		var data = {
			action: 'pwll_woocommerce_ajax_add_to_cart',
			nonce: pwll_ajax_var.ajax_nonce,
			product_id: checked_product_id,
			variation_id: checked_variation_id,
			product_sku: '',
			quantity: 1,
		};
		jQuery.ajax({
			type: 'post',
			url: pwll_ajax_var.ajax_url,
			data: data,
			beforeSend: function (response) {
				jQuery('.pwll-loading').show();
			},
			success: function (response) {
				if (response.error) {
					return;
				} else {
					window.location.href = pwll_ajax_var.checkout_url;
				}
			},
		});
	});

	jQuery('.pwll-pricing-plan').on('click', function (e) {
		e.preventDefault();
		jQuery('.pwll-pricing-plan.checked').removeClass('checked');
		jQuery(this).addClass('checked');
	});

});

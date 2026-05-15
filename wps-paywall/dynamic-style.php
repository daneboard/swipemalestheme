<?php
	global $post;
	$current_user_id    = get_current_user_id();
	$pwll_post_status   = get_post_meta( get_the_id(), 'pwll_post_status', true );
	$has_premium_access = pwll_user_has_premium_access( $current_user_id );

	$pwll_atmosphere           = xbox_get_field_value( 'pwll-options', 'pwll-atmosphere', 'dark' );
	$pwll_main_color           = xbox_get_field_value( 'pwll-options', 'pwll-main-color', '#cc8403' );
	$pwll_rounded_corners      = xbox_get_field_value( 'pwll-options', 'pwll-round-corners', 'on' );
	$pwll_pricing_plan_per_row = xbox_get_field_value( 'pwll-options', 'pwll-pricing-plan-per-row', 1 );
	// $pwll_blur_featured_images = xbox_get_field_value( 'pwll-options', 'pwll-blur-featured-images' );
	// $pwll_blur_intensity = xbox_get_field_value( 'pwll-options', 'pwll-blur-intensity' );

?>

<style>
	.pwll-pricing-plan.checked .pwll-most-popular,
	.pwll-pricing-plan:hover .pwll-most-popular,
	.pwll-content .woocommerce #payment #place_order, .pwll-content .woocommerce-page #payment #place_order,
	.pwll-close-modal,
	body .woocommerce-checkout #payment div.form-row.place-order button#place_order,
	.pwll-button,
	.woocommerce:where(body:not(.woocommerce-block-theme-has-button-styles)) #respond input#submit, .woocommerce:where(body:not(.woocommerce-block-theme-has-button-styles)) a.button, .woocommerce:where(body:not(.woocommerce-block-theme-has-button-styles)) button.button, .woocommerce:where(body:not(.woocommerce-block-theme-has-button-styles)) input.button, :where(body:not(.woocommerce-block-theme-has-button-styles)) .woocommerce #respond input#submit, :where(body:not(.woocommerce-block-theme-has-button-styles)) .woocommerce a.button, :where(body:not(.woocommerce-block-theme-has-button-styles)) .woocommerce button.button, :where(body:not(.woocommerce-block-theme-has-button-styles)) .woocommerce input.button {
		background-color: <?php echo $pwll_main_color; ?> !important;
		border-color: <?php echo $pwll_main_color; ?> !important;
	}
	.pwll-pricing-plan.checked .pwll-plan-title::before,
	.pwll-unlock-box .pwll-content::-webkit-scrollbar-thumb:hover,
	body .woocommerce-checkout-payment input[type=checkbox]:not(.switch):checked,
	body .select2-container--default .select2-results__option[aria-selected=true], body .select2-container--default .select2-results__option[data-selected=true],
	.pwll-badge .badge-icon {
		background-color: <?php echo $pwll_main_color; ?> !important;
	}
	.pwll-pricing-plan.checked,
	.pwll-pricing-plan:hover,
	.pwll-button:hover,
	.pwll-button:focus,
	.pwll-button:active,
	.woocommerce form .form-row .input-text:focus, .woocommerce-page form .form-row .input-text:focus,
	body .woocommerce-checkout-payment input[type=checkbox]:not(.switch):checked,
	body .woocommerce-checkout #payment div.form-row.place-order button#place_order:hover,
	.swiper-slide .pwll-badge {
		border-color: <?php echo $pwll_main_color; ?> !important;
	}
	.woocommerce form .form-row .required,
	body .woocommerce-checkout #payment div.form-row.place-order button#place_order:hover {
		color: <?php echo $pwll_main_color; ?> !important;
	}
	/* body .locked-button svg path { */
	body .lock-top {
		stroke: <?php echo $pwll_main_color; ?>;
	}
	body .lock-body {
		fill: <?php echo $pwll_main_color; ?>;
	}
	.woocommerce-checkout-payment input[type=checkbox],
	.woocommerce-checkout-payment input[type=radio] {
		--active: <?php echo $pwll_main_color; ?> !important;
	}
	.woocommerce-checkout-payment input[type=checkbox]:hover:not(:checked):not(:disabled),
	.woocommerce-checkout-payment input[type=radio]:hover:not(:checked):not(:disabled) {
		--border-hover: <?php echo $pwll_main_color; ?> !important;
	}
</style>

<?php if ( $pwll_atmosphere === 'dark' ) : ?>

	<style>
		body .pwll-unlock-box,
		body .pwll-unlock-box .woocommerce-checkout #payment {
			background: #222;
		}
		body .pwll-unlock-box .pwll-header {
			background-color: #111;
		}
		body .pwll-unlock-box .pwll-header h2,
		body .pwll-plan-title {
			color: #fff !important;
		}
		body .pwll-unlock-box .pwll-content .membership-desc,
		body .pwll-price,
		body .pwll-unlock-box .pwll-content label {
			color: #ccc;
		}
		body .pwll-plan-title::before {
			background-color: #333;
		}
		body .pwll-pricing-plan {
			background-color: #111;
			border-color: #333;
		}
		body .pwll-most-popular {
			background: #333;
			border-color: #333;
			color: #eee;
		}
		body .pwll-unlock-box h3,
		body .pwll-unlock-box .woocommerce select, body .pwll-unlock-box .woocommerce input:focus,
		.start-membership:hover,
		.start-membership:focus {
			color: #eee !important;
		}
		body .pwll-plan-description {
			color: #aaa;
		}
		body .pwll-free-trial {
			background-color: rgba(255,255,255,0.3);
		}
		body .pwll-unlock-box .woocommerce input, body .pwll-unlock-box .woocommerce select, body .pwll-unlock-box .woocommerce textarea {
			background-color: #333;
			border-color: #333;
			color: #ccc;
		}
		body .blockUI.blockOverlay {
			background-color: rgba(0,0,0,0.75) !important;
		}
		body .select2-container--default .select2-selection--single .select2-selection__rendered {
			color: #ccc;
		}
		body .woocommerce .select2-container--default .select2-selection--single,
		body .woocommerce form .form-row input.input-text, body .woocommerce form .form-row textarea {
			background-color: rgba(255,255,255,0.15) !important;
			border-color: rgba(255,255,255,0.15) !important;
			color: #ccc;
		}
		body .select2-dropdown {
			background: #333;
			border-color: #333;
		}
		body .select2-container--default .select2-search--dropdown .select2-search__field {
			background-color: rgba(0,0,0,0.3) !important;
			border-color: rgba(255,255,255,0.15) !important;
		}
		body .pwll-button:hover, body .pwll-button:focus, body .pwll-button:active {
			color: #fff;
		}
		body .payment_box p {
			color: #999 !important;
		}
		body .pwll-unlock-box .pwll-content::-webkit-scrollbar-track {
			background: #111 !important;
		}
		body .pwll-unlock-box .pwll-content::-webkit-scrollbar-thumb {
			background: #333 !important;
		}
		body .locked-button {
			background-color: rgba(0,0,0,0.7) !important;
			border-color: rgba(255,255,255,0.15) !important;
			color: #fff !important;
		}
		body .locked-button:hover {
			background-color: rgba(0,0,0,0.5) !important;
			border-color: rgba(255,255,255,0.3) !important;
		}
		body #add_payment_method #payment div.payment_box, body .woocommerce-cart #payment div.payment_box, body .woocommerce-checkout #payment div.payment_box {
			background-color: rgba(255,255,255,0.1) !important;
		}
		body .woocommerce-checkout-payment input[type=checkbox], body .woocommerce-checkout-payment input[type=radio] {
			--background: rgba(255,255,255,0.1);
		}
	</style>

<?php endif; ?>

<?php if ( $pwll_rounded_corners === 'on' ) : ?>
	<style>
		.pwll-badge {
			-webkit-border-radius: 3px !important;
			-moz-border-radius: 3px !important;
			border-radius: 3px !important;
		}
		.pwll-badge .badge-icon {
			-webkit-border-top-left-radius: 3px;
			-webkit-border-bottom-left-radius: 3px;
			-moz-border-radius-topleft: 3px;
			-moz-border-radius-bottomleft: 3px;
			border-top-left-radius: 3px;
			border-bottom-left-radius: 3px;
		}
		.pwll-pricing-plan,
		.pwll-unlock-box .woocommerce input, .pwll-unlock-box .woocommerce select, .pwll-unlock-box .woocommerce textarea,
		.pwll-unlock-box .pwll-content #place_order,
		.locked-button,
		.back-button,
		body #add_payment_method #payment div.payment_box, body .woocommerce-cart #payment div.payment_box, body .woocommerce-checkout #payment div.payment_box,
		body .woocommerce form .form-row input.input-text, body .woocommerce form .form-row textarea,
		.pwll-button,
		.premium-video-bg {
			-webkit-border-radius: 4px !important;
			-moz-border-radius: 4px !important;
			border-radius: 4px !important;
		}
		.pwll-most-popular {
			-webkit-border-top-left-radius: 4px;
			-webkit-border-top-right-radius: 4px;
			-moz-border-radius-topleft: 4px;
			-moz-border-radius-topright: 4px;
			border-top-left-radius: 4px;
			border-top-right-radius: 4px;
		}
		body #add_payment_method #payment, body .woocommerce-cart #payment, body .woocommerce-checkout #payment {
			-webkit-border-radius: 5px !important;
			-moz-border-radius: 5px !important;
			border-radius: 5px !important;
		}
		.pwll-unlock-box {
			-webkit-border-radius: 10px;
			-moz-border-radius: 10px;
			border-radius: 10px;
		}
		.pwll-unlock-box .pwll-content::-webkit-scrollbar,
		.pwll-unlock-box .pwll-content::-webkit-scrollbar-track {
			-webkit-border-bottom-right-radius: 20px;
			-moz-border-radius-bottomright: 20px;
			border-bottom-right-radius: 20px;
		}

		.pwll-unlock-box .pwll-content::-webkit-scrollbar-thumb {
			-webkit-border-bottom-right-radius: 10px;
			-webkit-border-bottom-left-radius: 10px;
			-moz-border-radius-bottomright: 10px;
			-moz-border-radius-bottomleft: 10px;
			border-bottom-right-radius: 10px;
			border-bottom-left-radius: 10px;
		}
		.pwll-unlock-box .pwll-header {
			-webkit-border-top-left-radius: 10px;
			-webkit-border-top-right-radius: 10px;
			-moz-border-radius-topleft: 10px;
			-moz-border-radius-topright: 10px;
			border-top-left-radius: 10px;
			border-top-right-radius: 10px;
		}
	</style>
<?php endif; ?>

<?php if ( $pwll_pricing_plan_per_row === '2' ) : ?>
	<style>
		.subscription-row {
			grid-template-columns: repeat(2, 1fr);
		}
	</style>
	<?php
endif;

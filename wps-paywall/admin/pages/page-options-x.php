<?php
/**
 * Options page.
 *
 * @package PWLL\Admin\Pages
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_filter( 'pwll-options', 'pwll_options_page' );
/**
 * This function is a pwll-options filter callback to display WP-Script logo & tabs at the top of the Xbox options page.
 *
 * @param string $options_table - default options HTML string to render the Xbox options in the page.
 * @return $output
 */
function pwll_options_page( $options_table ) {
	$output = '<div id="wp-script">
					<div class="content-tabs">';

	$output .= WPSCORE()->display_logo( false );
	$output .= WPSCORE()->display_tabs( false );

	$output .= '
		<div class="tab-content tab-options">
			<div class="tab-pane fade in active" id="PWLL-options-tab">
				<div v-cloak>
					<ul class="list-inline">
						<li class="active"><a href="admin.php?page=pwll-options">' . esc_html__( 'Options', 'pwll_lang' ) . '</a></li>
					</ul>
				</div>
			</div>';

	$output .= $options_table;

	$output .= '</div></div></div>';

	return $output;
}

add_action( 'xbox_init', 'pwll_options' );

/**
 * This function is a xbox_init action callback to define all the plugin Xbox options.
 *
 * @return void
 */
function pwll_options() {
	$options = array(
		'id'         => 'PWLL-options',
		'icon'       => XBOX_URL . 'img/xbox-light-small.png', // Menu icon.
		'skin'       => 'pink', // Skins: blue, lightblue, green, teal, pink, purple, bluepurple, yellow, orange'.
		'layout'     => 'boxed', // wide.
		'header'     => array(
			'icon' => '<img src="' . XBOX_URL . 'img/xbox-light.png"/>',
			'desc' => 'Customize here your Theme',
		),
		'capability' => 'edit_published_posts',
	);
	$xbox    = xbox_new_admin_page( $options );

	$items['pwll-general']       = '<i class="xbox-icon xbox-icon-gear"></i>' . esc_html__( 'General', 'pwll_lang' );
	$items['pwll-paywall-popup'] = '<i class="xbox-icon xbox-icon-lock"></i>' . esc_html__( 'Paywall Popup', 'pwll_lang' );
	$items['pwll-pricing']       = '<i class="xbox-icon xbox-icon-money"></i>' . esc_html__( 'Pricing', 'pwll_lang' );
	$items['pwll-checkout']      = '<i class="xbox-icon xbox-icon-list"></i>' . esc_html__( 'Checkout', 'pwll_lang' );

	$xbox->add_main_tab(
		array(
			'name'  => 'Main tab',
			'id'    => 'main-tab',
			'items' => $items,
		)
	);

	/**
	 * GENERAL
	 */
	$xbox->open_tab_item( 'pwll-general' );
		$xbox->add_field(
			array(
				'name'    => esc_html__( 'Navigate as premium member', 'pwll_lang' ),
				'id'      => 'pwll-navigate-as-premium-member',
				'type'    => 'switcher',
				'default' => 'off',
				'grid'    => '2-of-8',
				'desc'    => esc_html__( 'Enable this option to navigate on your site as premium member with unlocked content.', 'pwll_lang' ),
			)
		);

		$xbox->add_field(
			array(
				'name'       => esc_html__( 'Atmosphere', 'wpst' ),
				'id'         => 'pwll-atmosphere',
				'type'       => 'image_selector',
				'default'    => 'dark',
				'items'      => array(
					'light' => PWLL_URL . 'admin/assets/img/options/light-atmosphere.jpg',
					'dark'  => PWLL_URL . 'admin/assets/img/options/dark-atmosphere.jpg',
				),
				'items_desc' => array(
					'light' => esc_html__( 'Light', 'wpst' ),
					'dark'  => esc_html__( 'Dark', 'wpst' ),
				),
				'options'    => array(
					'width'   => '160px',
					'in_line' => true,
				),
			)
		);

		$xbox->add_field(
			array(
				'id'      => 'pwll-main-color',
				'name'    => esc_html__( 'Main color', 'pwll_lang' ),
				'type'    => 'colorpicker',
				'default' => '#cc8403',
				'desc'    => esc_html__( 'Set the color of the lock button, the active pricing plan, etc.', 'pwll_lang' ),
				'grid'    => '2-of-8',
			)
		);

		$xbox->add_field(
			array(
				'name'    => esc_html__( 'Round corners', 'pwll_lang' ),
				'id'      => 'pwll-round-corners',
				'type'    => 'switcher',
				'default' => 'on',
				'grid'    => '2-of-8',
				'desc'    => esc_html__( 'Round the corners of the paywall popup, buttons, inputs, etc.', 'pwll_lang' ),
			)
		);

		$xbox->add_field(
			array(
				'id'      => 'pwll-locked-content-area-text',
				'name'    => esc_html__( 'Locked content area text', 'pwll_lang' ),
				'type'    => 'text',
				'default' => 'Unlock Video',
				'grid'    => '4-of-8',
				'desc'    => esc_html__( 'Set locked content area text (eg. Unlock Video).', 'pwll_lang' ),
			)
		);

		$xbox->add_field(
			array(
				'name'    => esc_html__( 'Badge featured images', 'pwll_lang' ),
				'id'      => 'pwll-badge-featured-images',
				'type'    => 'switcher',
				'default' => 'on',
				'grid'    => '2-of-8',
				'desc'    => esc_html__( 'Add a lock badge on archive\'s featured images for your premium posts.', 'pwll_lang' ),
			)
		);

		$xbox->open_mixed_field(
			array(
				'id'   => 'displayed-when:switch:pwll-badge-featured-images:on:pwll-badge-featured-images-settings',
				'name' => esc_html__(
					'Badge settings',
					'pwll_lang'
				),
			)
		);

			$xbox->add_field(
				array(
					'name'        => esc_html__( 'Badge icon', 'wpst' ),
					'id'          => 'pwll-badge-icon',
					'type'        => 'image_selector',
					'description' => 'Choose the icon used for the premium badge.',
					'default'     => 'lock',
					'items'       => array(
						'lock' => PWLL_URL . 'admin/assets/img/options/lock.jpg',
						'star' => PWLL_URL . 'admin/assets/img/options/star.jpg',
					),
					'items_desc'  => array(
						'lock' => esc_html__( 'Lock', 'wpst' ),
						'star' => esc_html__( 'Star', 'wpst' ),
					),
					'options'     => array(
						'width'   => '160px',
						'in_line' => true,
					),
				)
			);

			$xbox->add_field(
				array(
					'id'      => 'pwll-badge-text',
					'name'    => esc_html__( 'Badge text', 'pwll_lang' ),
					'type'    => 'text',
					'default' => 'Premium',
					'grid'    => '4-of-8',
					'desc'    => esc_html__( 'Set the badge text.', 'pwll_lang' ),
				)
			);

		$xbox->close_mixed_field();
	$xbox->close_tab_item( 'pwll-general' );

	/**
	 * PAYWALL POPUP
	 */
	$xbox->open_tab_item( 'pwll-paywall-popup' );

		$xbox->add_field(
			array(
				'id'      => 'pwll-paywall-popup-title',
				'name'    => esc_html__( 'Title', 'pwll_lang' ),
				'type'    => 'text',
				'default' => 'Premium Membership',
				'grid'    => '4-of-8',
				'desc'    => esc_html__( 'Set the title of the paywall popup (eg. Premium Membership).', 'pwll_lang' ),
			)
		);

		$xbox->add_field(
			array(
				'id'      => 'pwll-paywall-popup-description',
				'name'    => esc_html__( 'Description', 'pwll_lang' ),
				'type'    => 'textarea',
				'default' => 'Become a Premium Member and Get Access to All our Exclusive Videos',
				'grid'    => '4-of-8',
				'desc'    => esc_html__( 'Set the description of the paywall popup (eg. Become a Premium Member and Get Access to All our Exclusive Videos)', 'pwll_lang' ),
			)
		);

		$xbox->add_field(
			array(
				'id'         => 'pwll-pricing-plan-per-row',
				'name'       => esc_html__( 'Pricing plan per row', 'wpst' ),
				'type'       => 'image_selector',
				'default'    => '1',
				'items'      => array(
					'1' => PWLL_URL . 'admin/assets/img/options/1-per-row.jpg',
					'2' => PWLL_URL . 'admin/assets/img/options/2-per-row.jpg',
				),
				'items_desc' => array(
					'1' => '1 per row',
					'2' => '2 per row',
				),
				'options'    => array(
					'width'   => '160px',
					'in_line' => true,
				),
			)
		);

		$xbox->add_field(
			array(
				'id'      => 'pwll-paywall-popup-button-text',
				'name'    => esc_html__( 'Button text', 'pwll_lang' ),
				'type'    => 'text',
				'default' => 'Start Membership',
				'grid'    => '4-of-8',
				'desc'    => esc_html__( 'Set the paywall popup button text.', 'pwll_lang' ),
			)
		);

	$xbox->close_tab_item( 'pwll-paywall-popup' );

	/**
	 * PRICING
	 */
	$xbox->open_tab_item( 'pwll-pricing' );

	if ( class_exists( 'WC_Subscriptions', false ) ) {

		/**
		 * 1 MONTH
		 */
		$xbox->add_field(
			array(
				'name'    => esc_html__( 'Enable 1 month', 'pwll_lang' ),
				'id'      => 'pwll-enable-1-month',
				'type'    => 'switcher',
				'default' => 'on',
				'grid'    => '2-of-8',
				'desc'    => esc_html__( 'Offer the possibility to your users to pay every month in order to access to your Premium content.', 'pwll_lang' ),
			)
		);

		/* If 1 month on */
		$xbox->open_mixed_field(
			array(
				'id'   => 'displayed-when:switch:pwll-enable-1-month:on:pwll-1-month-settings',
				'name' => esc_html__(
					'1 month settings',
					'pwll_lang'
				),
			)
		);

			$xbox->add_field(
				array(
					'id'         => 'pwll-1-month-price',
					'name'       => esc_html__( 'Price', 'pwll_lang' ),
					'type'       => 'number',
					'default'    => 9,
					'attributes' => array(
						'min'       => 1,
						'step'      => 1,
						'precision' => 0,
					),
					'options'    => array(
						'unit' => get_woocommerce_currency_symbol(),
					),
					'grid'       => '4-of-8',
				)
			);

			$xbox->add_field(
				array(
					'id'         => 'pwll-1-month-sign-up-fee',
					'name'       => esc_html__( 'Sign-up fee', 'pwll_lang' ),
					'type'       => 'number',
					'default'    => 0,
					'attributes' => array(
						'min'       => 0,
						'step'      => 1,
						'precision' => 0,
					),
					'options'    => array(
						'unit' => get_woocommerce_currency_symbol(),
					),
					'grid'       => '4-of-8 last',
				)
			);

			$xbox->add_field(
				array(
					'name'    => esc_html__( 'Free trial', 'pwll_lang' ),
					'id'      => 'pwll-1-month-free-trial',
					'type'    => 'switcher',
					'default' => 'off',
					'grid'    => '2-of-8',
					'desc'    => esc_html__( 'Offer the possibility to your users to test before subscribing for 1 month.', 'pwll_lang' ),
				)
			);

			$xbox->add_field(
				array(
					'id'         => 'pwll-1-month-free-trial-length',
					'name'       => esc_html__( 'Free trial length', 'pwll_lang' ),
					'type'       => 'number',
					'default'    => 0,
					'attributes' => array(
						'min'       => 0,
						'step'      => 1,
						'precision' => 0,
					),
					'options'    => array(
						'unit' => '',
					),
					'grid'       => '2-of-8',
				)
			);

			$xbox->add_field(
				array(
					'name'    => esc_html__( 'Free trial period', 'pwll_lang' ),
					'id'      => 'pwll-1-month-free-trial-period',
					'type'    => 'select',
					'default' => 'day',
					'items'   => array(
						'day'   => esc_html__( 'Day', 'pwll_lang' ),
						'week'  => esc_html__( 'Week', 'pwll_lang' ),
						'month' => esc_html__( 'Month', 'pwll_lang' ),
						'year'  => esc_html__( 'Year', 'pwll_lang' ),
					),
					'grid'    => '2-of-8 last',
				)
			);

		$xbox->close_mixed_field();

		/**
		 * 3 MONTHS
		 */
		$xbox->add_field(
			array(
				'name'    => esc_html__( 'Enable 3 months', 'pwll_lang' ),
				'id'      => 'pwll-enable-3-months',
				'type'    => 'switcher',
				'default' => 'off',
				'grid'    => '2-of-8',
				'desc'    => esc_html__( 'Offer the possibility to your users to pay every 3 months in order to access to your Premium content.', 'pwll_lang' ),
			)
		);

		/* If 3 months on */
		$xbox->open_mixed_field(
			array(
				'id'   => 'displayed-when:switch:pwll-enable-3-months:on:pwll-3-months-settings',
				'name' => esc_html__(
					'3 months settings',
					'pwll_lang'
				),
			)
		);

			$xbox->add_field(
				array(
					'id'         => 'pwll-3-months-price',
					'name'       => esc_html__( 'Price', 'pwll_lang' ),
					'type'       => 'number',
					'default'    => 19,
					'attributes' => array(
						'min'       => 1,
						'step'      => 1,
						'precision' => 0,
					),
					'options'    => array(
						'unit' => get_woocommerce_currency_symbol(),
					),
					'grid'       => '4-of-8',
				)
			);

			$xbox->add_field(
				array(
					'id'         => 'pwll-3-months-sign-up-fee',
					'name'       => esc_html__( 'Sign-up fee', 'pwll_lang' ),
					'type'       => 'number',
					'default'    => 0,
					'attributes' => array(
						'min'       => 0,
						'step'      => 1,
						'precision' => 0,
					),
					'options'    => array(
						'unit' => get_woocommerce_currency_symbol(),
					),
					'grid'       => '4-of-8 last',
				)
			);

			$xbox->add_field(
				array(
					'name'    => esc_html__( 'Free trial', 'pwll_lang' ),
					'id'      => 'pwll-3-months-free-trial',
					'type'    => 'switcher',
					'default' => 'off',
					'grid'    => '2-of-8',
					'desc'    => esc_html__( 'Offer the possibility to your users to test before subscribing for 3 months.', 'pwll_lang' ),
				)
			);

			$xbox->add_field(
				array(
					'id'         => 'pwll-3-months-free-trial-length',
					'name'       => esc_html__( 'Free trial length', 'pwll_lang' ),
					'type'       => 'number',
					'default'    => 0,
					'attributes' => array(
						'min'       => 0,
						'step'      => 1,
						'precision' => 0,
					),
					'options'    => array(
						'unit' => esc_html__( '', 'pwll_lang' ),
					),
					'grid'       => '2-of-8',
				)
			);

			$xbox->add_field(
				array(
					'name'    => esc_html__( 'Free trial period', 'pwll_lang' ),
					'id'      => 'pwll-3-months-free-trial-period',
					'type'    => 'select',
					'default' => 'day',
					'items'   => array(
						'day'   => 'Day',
						'week'  => 'Week',
						'month' => 'Month',
						'year'  => 'Year',
					),
					'grid'    => '2-of-8 last',
				)
			);

		$xbox->close_mixed_field();

		/**
		 * 6 MONTHS
		 */
		$xbox->add_field(
			array(
				'name'    => esc_html__( 'Enable 6 months', 'pwll_lang' ),
				'id'      => 'pwll-enable-6-months',
				'type'    => 'switcher',
				'default' => 'off',
				'grid'    => '2-of-8',
				'desc'    => esc_html__( 'Offer the possibility to your users to pay every 6 months in order to access to your Premium content.', 'pwll_lang' ),
			)
		);

		/* If 6 months on */
		$xbox->open_mixed_field(
			array(
				'id'   => 'displayed-when:switch:pwll-enable-6-months:on:pwll-6-months-settings',
				'name' => esc_html__(
					'6 months settings',
					'pwll_lang'
				),
			)
		);

			$xbox->add_field(
				array(
					'id'         => 'pwll-6-months-price',
					'name'       => esc_html__( 'Price', 'pwll_lang' ),
					'type'       => 'number',
					'default'    => 29,
					'attributes' => array(
						'min'       => 1,
						'step'      => 1,
						'precision' => 0,
					),
					'options'    => array(
						'unit' => get_woocommerce_currency_symbol(),
					),
					'grid'       => '4-of-8',
				)
			);

			$xbox->add_field(
				array(
					'id'         => 'pwll-6-months-sign-up-fee',
					'name'       => esc_html__( 'Sign-up fee', 'pwll_lang' ),
					'type'       => 'number',
					'default'    => 0,
					'attributes' => array(
						'min'       => 0,
						'step'      => 1,
						'precision' => 0,
					),
					'options'    => array(
						'unit' => get_woocommerce_currency_symbol(),
					),
					'grid'       => '4-of-8 last',
				)
			);

			$xbox->add_field(
				array(
					'name'    => esc_html__( 'Free trial', 'pwll_lang' ),
					'id'      => 'pwll-6-months-free-trial',
					'type'    => 'switcher',
					'default' => 'off',
					'grid'    => '2-of-8',
					'desc'    => esc_html__( 'Offer the possibility to your users to test before subscribing for 6 months.', 'pwll_lang' ),
				)
			);

			$xbox->add_field(
				array(
					'id'         => 'pwll-6-months-free-trial-length',
					'name'       => esc_html__( 'Free trial length', 'pwll_lang' ),
					'type'       => 'number',
					'default'    => 0,
					'attributes' => array(
						'min'       => 0,
						'step'      => 1,
						'precision' => 0,
					),
					'options'    => array(
						'unit' => '',
					),
					'grid'       => '2-of-8',
				)
			);

			$xbox->add_field(
				array(
					'name'    => esc_html__( 'Free trial period', 'pwll_lang' ),
					'id'      => 'pwll-6-months-free-trial-period',
					'type'    => 'select',
					'default' => 'day',
					'items'   => array(
						'day'   => esc_html__( 'Day', 'pwll_lang' ),
						'week'  => esc_html__( 'Week', 'pwll_lang' ),
						'month' => esc_html__( 'Month', 'pwll_lang' ),
						'year'  => esc_html__( 'Year', 'pwll_lang' ),
					),
					'grid'    => '2-of-8 last',
				)
			);

		$xbox->close_mixed_field();

		/**
		 * 12 MONTHS
		 */
		$xbox->add_field(
			array(
				'name'    => esc_html__( 'Enable 12 months', 'pwll_lang' ),
				'id'      => 'pwll-enable-12-months',
				'type'    => 'switcher',
				'default' => 'on',
				'grid'    => '2-of-8',
				'desc'    => esc_html__( 'Offer the possibility to your users to pay every year in order to access to your Premium content.', 'pwll_lang' ),
			)
		);

		/* If 12 months on */
		$xbox->open_mixed_field(
			array(
				'id'   => 'displayed-when:switch:pwll-enable-12-months:on:pwll-12-months-settings',
				'name' => esc_html__(
					'12 months settings',
					'pwll_lang'
				),
			)
		);

			$xbox->add_field(
				array(
					'id'         => 'pwll-12-months-price',
					'name'       => esc_html__( 'Price', 'pwll_lang' ),
					'type'       => 'number',
					'default'    => 49,
					'attributes' => array(
						'min'       => 1,
						'step'      => 1,
						'precision' => 0,
					),
					'options'    => array(
						'unit' => get_woocommerce_currency_symbol(),
					),
					'grid'       => '4-of-8',
				)
			);

			$xbox->add_field(
				array(
					'id'         => 'pwll-12-months-sign-up-fee',
					'name'       => esc_html__( 'Sign-up fee', 'pwll_lang' ),
					'type'       => 'number',
					'default'    => 0,
					'attributes' => array(
						'min'       => 0,
						'step'      => 1,
						'precision' => 0,
					),
					'options'    => array(
						'unit' => get_woocommerce_currency_symbol(),
					),
					'grid'       => '4-of-8 last',
				)
			);

			$xbox->add_field(
				array(
					'name'    => esc_html__( 'Free trial', 'pwll_lang' ),
					'id'      => 'pwll-12-months-free-trial',
					'type'    => 'switcher',
					'default' => 'off',
					'grid'    => '2-of-8',
					'desc'    => esc_html__( 'Offer the possibility to your users to test before subscribing for 12 months.', 'pwll_lang' ),
				)
			);

			$xbox->add_field(
				array(
					'id'         => 'pwll-12-months-free-trial-length',
					'name'       => esc_html__( 'Free trial length', 'pwll_lang' ),
					'type'       => 'number',
					'default'    => 0,
					'attributes' => array(
						'min'       => 0,
						'step'      => 1,
						'precision' => 0,
					),
					'options'    => array(
						'unit' => '',
					),
					'grid'       => '2-of-8',
				)
			);

			$xbox->add_field(
				array(
					'name'    => esc_html__( 'Free trial period', 'pwll_lang' ),
					'id'      => 'pwll-12-months-free-trial-period',
					'type'    => 'select',
					'default' => 'day',
					'items'   => array(
						'day'   => esc_html__( 'Day', 'pwll_lang' ),
						'week'  => esc_html__( 'Week', 'pwll_lang' ),
						'month' => esc_html__( 'Month', 'pwll_lang' ),
						'year'  => esc_html__( 'Year', 'pwll_lang' ),
					),
					'grid'    => '2-of-8 last',
				)
			);

		$xbox->close_mixed_field();

	} else {

		/**
		 * SUBSCRIPTION PLANS INTRODUCTION
		 */
		$xbox->add_field(
			array(
				'name'    => esc_html__( 'Subscription Plans', 'pwll_lang' ),
				'id'      => 'pwll-subscription-plans',
				'type'    => 'html',
				'content' => 'You have to install <strong><a href="https://www.wp-script.com/go/woo-subscriptions/" target="_blank">Woo Subscriptions</a></strong> plugin in order to offer premium membership subscription plans (every month, 3 months, 6 months or 12 months), free trials and signup fees.<br><br><h4>Why Woo Subscriptions plugin?</h4>Because it is the only subscription plugin that is compatible with <strong>payment methods</strong> that <strong>accept adult content</strong>.<br><br>Like the famous CCBill platform and its Woo Subscriptions compatible plugin: <strong><a href="https://www.wp-script.com/go/ccbill-woo-subscriptions/" target="_blank">CCBill Woo Subscriptions</a></strong><br><br>',
				'grid'    => '8-of-8',
				'desc'    => '',
			)
		);

	}

		/**
		 * LIFETIME
		 */
		$xbox->add_field(
			array(
				'name'    => esc_html__( 'Lifetime Plan', 'pwll_lang' ),
				'id'      => 'pwll-enable-lifetime',
				'type'    => 'switcher',
				'default' => 'on',
				'grid'    => '2-of-8',
				'desc'    => esc_html__( 'Offer the possibility to your users to pay only one-time in order to access to your Premium content for life.', 'pwll_lang' ),
			)
		);

		/* If lifetime on */
		$xbox->open_mixed_field(
			array(
				'id'   => 'displayed-when:switch:pwll-enable-lifetime:on:pwll-lifetime-settings',
				'name' => esc_html__(
					'Lifetime settings',
					'pwll_lang'
				),
			)
		);

			$xbox->add_field(
				array(
					'id'         => 'pwll-lifetime-price',
					'name'       => esc_html__( 'Price', 'pwll_lang' ),
					'type'       => 'number',
					'default'    => 99,
					'attributes' => array(
						'min'       => 1,
						'step'      => 1,
						'precision' => 0,
					),
					'options'    => array(
						'unit' => get_woocommerce_currency_symbol(),
					),
					'grid'       => '4-of-8',
				)
			);

		$xbox->close_mixed_field();

	$xbox->close_tab_item( 'pwll-pricing' );

	/**
	 * CHECKOUT
	 */
	$xbox->open_tab_item( 'pwll-checkout' );
		$xbox->add_field(
			array(
				'name'    => esc_html__( 'First name', 'pwll_lang' ),
				'id'      => 'pwll-enable-checkout-first-name',
				'type'    => 'switcher',
				'default' => 'on',
				'grid'    => '2-of-8',
				'desc'    => esc_html__( 'Ask for the user\'s first name on the checkout section.', 'pwll_lang' ),
			)
		);

		$xbox->add_field(
			array(
				'name'    => esc_html__( 'Last name', 'pwll_lang' ),
				'id'      => 'pwll-enable-checkout-last-name',
				'type'    => 'switcher',
				'default' => 'on',
				'grid'    => '2-of-8',
				'desc'    => esc_html__( 'Ask for the user\'s last name on the checkout section.', 'pwll_lang' ),
			)
		);

		$xbox->add_field(
			array(
				'name'    => esc_html__( 'Address', 'pwll_lang' ),
				'id'      => 'pwll-enable-checkout-address',
				'type'    => 'switcher',
				'default' => 'off',
				'grid'    => '2-of-8',
				'desc'    => esc_html__( 'Ask for the user\'s address on the checkout section.', 'pwll_lang' ),
			)
		);

		$xbox->add_field(
			array(
				'name'    => esc_html__( 'City', 'pwll_lang' ),
				'id'      => 'pwll-enable-checkout-city',
				'type'    => 'switcher',
				'default' => 'off',
				'grid'    => '2-of-8',
				'desc'    => esc_html__( 'Ask for the user\'s city on the checkout section.', 'pwll_lang' ),
			)
		);

		$xbox->add_field(
			array(
				'name'    => esc_html__( 'Postcode', 'pwll_lang' ),
				'id'      => 'pwll-enable-checkout-postcode',
				'type'    => 'switcher',
				'default' => 'off',
				'grid'    => '2-of-8',
				'desc'    => esc_html__( 'Ask for the user\'s postcode on the checkout section.', 'pwll_lang' ),
			)
		);

		$xbox->add_field(
			array(
				'name'    => esc_html__( 'Country', 'pwll_lang' ),
				'id'      => 'pwll-enable-checkout-country',
				'type'    => 'switcher',
				'default' => 'on',
				'grid'    => '2-of-8',
				'desc'    => esc_html__( 'Ask for the user\'s country on the checkout section.', 'pwll_lang' ),
			)
		);

		$xbox->add_field(
			array(
				'name'    => esc_html__( 'Phone', 'pwll_lang' ),
				'id'      => 'pwll-enable-checkout-phone',
				'type'    => 'switcher',
				'default' => 'off',
				'grid'    => '2-of-8',
				'desc'    => esc_html__( 'Ask for the user\'s phone on the checkout section.', 'pwll_lang' ),
			)
		);

	$xbox->close_tab_item( 'pwll-pricing' );

	$xbox->close_tab( 'main-tab' );
}

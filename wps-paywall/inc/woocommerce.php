<?php
/**
 * WooCommerce Integration
 *
 * @package WPS Paywall
 * @subpackage WooCommerce Integration
 * @since 1.0.0
 */

/**
 * Update WooCommerce settings on admin init
 */
function pwll_update_woocommerce_settings() {
	update_option( 'woocommerce_enable_guest_checkout', 'no' );
	update_option( 'woocommerce_enable_checkout_login_reminder', 'no' );
	update_option( 'woocommerce_enable_signup_and_login_from_checkout', 'yes' );
}
add_action( 'admin_init', 'pwll_update_woocommerce_settings' );

// Automatically add product to cart on visit.
add_action( 'template_redirect', 'pwll_add_product_to_cart' );

/**
 * Automatically add product to cart.
 */
function pwll_add_product_to_cart() {
	if ( is_admin() ) {
		return;
	}
	if ( 0 !== wc_get_product_id_by_sku( 'premium-membership-lifetime' ) ) {
		global $woocommerce;
		$product_id = wc_get_product_id_by_sku( 'premium-membership-lifetime' ); // replace with your own product id.
		$found      = false;
		// check if product already in cart.
		if ( count( $woocommerce->cart->get_cart() ) > 0 ) {
			foreach ( $woocommerce->cart->get_cart() as $cart_item_key => $values ) {
				$_product = $values['data'];
				if ( (int) $_product->get_id() === (int) $product_id ) {
					$found = true;
				}
			}
			// if product not found, add it.
			if ( ! $found ) {
				return;
			}
		} else {
			// if no products in cart, add it.
			$woocommerce->cart->add_to_cart( $product_id );
		}
	}
}

// Customize paypal gateway icon.
add_filter( 'woocommerce_gateway_icon', 'pwll_change_gateway_icon', 10, 2 );
/**
 * Change gateway icon.
 *
 * @param string $icon     Current icon HTML.
 * @param string $gateway_id Gateway ID.
 *
 * @return string Modified icon HTML.
 */
function pwll_change_gateway_icon( $icon, $gateway_id ) {
	if ( 'ppcp-gateway' === $gateway_id ) {
		// Change the URL of your own icon here.
		$icon_url = WP_PLUGIN_URL . '/wps-paywall/assets/img/paypal.svg';
		$icon     = '<img src="' . $icon_url . '" width="79" height="22">';
	}
	return $icon;
}

/**
 * Get checkout product sku.
 *
 * @return string Product SKU.
 */
function pwll_get_checkout_product_sku() {
	global $woocommerce;
	$items = $woocommerce->cart->get_cart();
	foreach ( $items as $item => $values ) {
		$_product             = wc_get_product( $values['data']->get_id() );
		$product_sku          = esc_html( $_product->get_sku() );
		$product_name         = esc_html( $_product->get_name() );
		$product_sku_exploded = explode( '-', $product_sku );
		$product_sku_check    = $product_sku_exploded[0] . '-' . $product_sku_exploded[1];
	}
	if ( isset( $product_sku_check ) ) {
		return $product_sku_check;
	}
}

/**
 * Get product by SKU.
 *
 * @param string $sku Product SKU.
 *
 * @return WC_Product|null Product object or null if not found.
 */
function pwll_get_product_by_sku( $sku ) {
	global $wpdb;
	$product_id = $wpdb->get_var( $wpdb->prepare( "SELECT post_id FROM $wpdb->postmeta WHERE meta_key='_sku' AND meta_value='%s' LIMIT 1", $sku ) );
	if ( $product_id ) {
		return new WC_Product( $product_id );
	}
	return null;
}

// Redirect subscription products to homepage.
add_action( 'template_redirect', 'pwll_redirect_products', 100 );
/**
 * Redirect subscription products to homepage.
 */
function pwll_redirect_products() {
	if ( ! is_singular( 'product' ) ) {
		return;
	}

	global $post;

	$restricted_products = array(
		'premium-membership-lifetime',
		'premium-membership-subscription',
	);

	if ( in_array( $post->post_name, $restricted_products, true ) ) {
		wp_safe_redirect( home_url() );
		exit;
	}
}

// Move email billing field at the top.
add_filter( 'woocommerce_billing_fields', 'pwll_move_checkout_email_field' );
/**
 * Move email billing field at the top.
 *
 * @param array $address_fields Billing address fields.
 *
 * @return array Modified billing address fields.
 */
function pwll_move_checkout_email_field( $address_fields ) {
	$address_fields['billing_email']['priority'] = 1;
	return $address_fields;
}

// Disable coupons.
remove_action( 'woocommerce_before_checkout_form', 'woocommerce_checkout_coupon_form', 10 );

// Change place order button text.
add_filter( 'woocommerce_order_button_text', 'pwll_rename_place_order_button', 9999 );
/**
 * Rename place order button.
 *
 * @return string New button text.
 */
function pwll_rename_place_order_button() {
	return esc_html( get_option( 'pwll_checkout_button_text', 'Start Membership' ) );
}

// Remove woocommerce order note field.
add_filter( 'woocommerce_enable_order_notes_field', '__return_false' );

// Customize billing fields.
add_filter( 'woocommerce_checkout_fields', 'pwll_custom_remove_woo_checkout_fields' );
/**
 * Customize billing fields.
 *
 * @param array $fields Checkout fields.
 *
 * @return array Modified checkout fields.
 */
function pwll_custom_remove_woo_checkout_fields( $fields ) {

	$pwll_checkout_first_name = xbox_get_field_value( 'pwll-options', 'pwll-enable-checkout-first-name', 'on' );
	$pwll_checkout_last_name  = xbox_get_field_value( 'pwll-options', 'pwll-enable-checkout-last-name', 'on' );
	$pwll_checkout_country    = xbox_get_field_value( 'pwll-options', 'pwll-enable-checkout-country', 'on' );
	$pwll_checkout_address    = xbox_get_field_value( 'pwll-options', 'pwll-enable-checkout-address', 'off' );
	$pwll_checkout_city       = xbox_get_field_value( 'pwll-options', 'pwll-enable-checkout-city', 'off' );
	$pwll_checkout_postcode   = xbox_get_field_value( 'pwll-options', 'pwll-enable-checkout-postcode', 'off' );
	$pwll_checkout_phone      = xbox_get_field_value( 'pwll-options', 'pwll-enable-checkout-phone', 'off' );

	if ( 'off' === $pwll_checkout_first_name ) {
		unset( $fields['billing']['billing_first_name'] );
	}
	if ( 'off' === $pwll_checkout_last_name ) {
		unset( $fields['billing']['billing_last_name'] );
	}
	if ( 'off' === $pwll_checkout_address ) {
		unset( $fields['billing']['billing_address_1'] );
	}
	if ( 'off' === $pwll_checkout_city ) {
		unset( $fields['billing']['billing_city'] );
	}
	if ( 'off' === $pwll_checkout_postcode ) {
		unset( $fields['billing']['billing_postcode'] );
	}
	if ( 'off' === $pwll_checkout_country ) {
		unset( $fields['billing']['billing_country'] );
	} else {
		$fields['billing']['billing_country']['priority'] = 100;
	}
	if ( 'off' === $pwll_checkout_phone ) {
		unset( $fields['billing']['billing_phone'] );
	}

	return $fields;
}

// Unset address 2, company and state fields.
add_filter( 'woocommerce_default_address_fields', 'pwll_remove_fields' );
/**
 * Remove unnecessary address fields.
 *
 * @param array $fields Address fields.
 *
 * @return array Modified address fields.
 */
function pwll_remove_fields( $fields ) {
	unset( $fields['address_2'] );
	unset( $fields['company'] );
	unset( $fields['state'] );
	return $fields;
}

// Dynamic pricing.
add_filter( 'woocommerce_get_price_html', 'pwll_alter_price_display', 9999, 2 );
/**
 * Alter price display.
 *
 * @param string     $price_html Price HTML.
 * @param WC_Product $product    Product object.
 *
 * @return string Modified price HTML.
 */
function pwll_alter_price_display( $price_html, $product ) {
	// Only on frontend.
	if ( is_admin() ) {
		return $price_html;
	}

	// Only if price not null.
	if ( '' === $product->get_price() ) {
		return $price_html;
	}

	$orig_price = wc_get_price_to_display( $product );

	$product_sku = $product->get_sku();

	eval( WPSCORE()->eval_product_data( 'PWLL', 'pwll_woocommerce_eval_2' ) );

	return $price_html;
}

// Dynamic pricing on checkout.
add_action( 'woocommerce_before_calculate_totals', 'pwll_alter_price_cart', 9999 );
/**
 * Alter price in cart.
 *
 * @param WC_Cart $cart Cart object.
 */
function pwll_alter_price_cart( $cart ) {
	if ( is_admin() && ! defined( 'DOING_AJAX' ) ) {
		return;
	}
	foreach ( $cart->get_cart() as $cart_item_key => $cart_item ) {
		$product     = $cart_item['data'];
		$product_sku = $product->get_sku();
		$price       = $product->get_price();
		eval( WPSCORE()->eval_product_data( 'PWLL', 'pwll_woocommerce_eval_3' ) );
	}
}

// Override woocommerce templates.
add_filter( 'woocommerce_locate_template', 'pwll_woo_plugin_template', 1, 3 );
/**
 * Override WooCommerce templates.
 *
 * @param string $template      Template path.
 * @param string $template_name Template name.
 * @param string $template_path Template path.
 *
 * @return string Modified template path.
 */
function pwll_woo_plugin_template( $template, $template_name, $template_path ) {
	global $woocommerce;
	$_template = $template;
	if ( ! $template_path ) {
		$template_path = $woocommerce->template_url;
	}

	$plugin_path = PWLL_DIR . '/woocommerce/';

	$template = locate_template(
		array(
			$template_path . $template_name,
			$template_name,
		)
	);

	if ( ! $template && file_exists( $plugin_path . $template_name ) ) {
		$template = $plugin_path . $template_name;
	}

	if ( ! $template ) {
		$template = $_template;
	}

	return $template;
}

// Remove price zero decimals.
add_filter( 'formatted_woocommerce_price', 'pwll_remove_zero_decimals', 10, 5 );
/**
 * Remove price zero decimals.
 *
 * @param string $formatted_price    Formatted price.
 * @param float  $price              Price.
 * @param int    $decimal_places     Decimal places.
 * @param string $decimal_separator  Decimal separator.
 * @param string $thousand_separator Thousand separator.
 *
 * @return string Modified formatted price.
 */
function pwll_remove_zero_decimals( $formatted_price, $price, $decimal_places, $decimal_separator, $thousand_separator ) {
	if ( 0 === ( $price - intval( $price ) ) ) {
		// Format units, including thousands separator if necessary.
		$unit = number_format( intval( $price ), 0, $decimal_separator, $thousand_separator );
		return $unit;
	} else {
		return $formatted_price;
	}
}

/**
 * Customize My Account menu items.
 *
 * @param array $items Menu items.
 *
 * @return array Modified menu items.
 */
function pwll_custom_my_account_menu_items( $items ) {
	unset( $items['downloads'] );
	return $items;
}
add_filter( 'woocommerce_account_menu_items', 'pwll_custom_my_account_menu_items' );


add_action( 'xbox_after_save_fields', 'pwll_update_subscription_options', 10, 2 );
/**
 * Update subscription options.
 *
 * @param mixed $value  Field value.
 * @param array $fields Fields array.
 */
function pwll_update_subscription_options( $value, $fields ) {
	eval( WPSCORE()->eval_product_data( 'PWLL', 'pwll_woocommerce_eval_4' ) );
}

add_action( 'updated_option', 'pwll_update_product_when_update_option', 10, 3 );
/**
 * Update product when option is updated.
 *
 * @param string $option_name  Option name.
 * @param mixed  $old_value    Old option value.
 * @param mixed  $option_value New option value.
 */
function pwll_update_product_when_update_option( $option_name, $old_value, $option_value ) {
	if ( ! class_exists( 'WC_Subscriptions', false ) ) {
		return;
	}
	eval( WPSCORE()->eval_product_data( 'PWLL', 'pwll_woocommerce_eval_5' ) );
}

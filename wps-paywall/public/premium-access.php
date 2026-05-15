<?php
/**
 * Refreshes the `_has_premium_access` user-meta flag for the logged-in user
 * based on their latest WooCommerce order / subscription status.
 *
 * The "does this user have premium access right now?" check lives in
 * `inc/ad-removal-compat.php::pwll_user_has_premium_access()` and also folds in
 * the TikSwipe Ad Removal membership.
 */
add_action( 'init', 'pwll_refresh_premium_access_flag' );
function pwll_refresh_premium_access_flag() {
	if ( ! is_user_logged_in() ) {
		return;
	}
	if ( ! class_exists( 'WooCommerce' ) ) {
		return;
	}
	$user_id    = get_current_user_id();
	$customer   = new WC_Customer( $user_id );
	$last_order = $customer->get_last_order();
	if ( ! $last_order ) {
		update_user_meta( $user_id, '_has_premium_access', 'off' );
		return;
	} else {
		$last_order_id     = $last_order->get_id(); // Get the order id
		$last_order_status = $last_order->get_status(); // Get the order status
		$order             = new WC_Order( $last_order_id );
		$items             = $order->get_items();
		foreach ( $items as $item ) {
			$product = $item->get_product();
			if ( $product ) {
				$product_sku = $product->get_sku();
				if ( $product_sku == 'premium-membership-lifetime' && $last_order_status == 'completed' ) {
					update_user_meta( $user_id, '_has_premium_access', 'on' );
				} elseif ( class_exists( 'WC_Subscriptions', 'off' ) ) {
					$woo_subscription_products = wc_get_products(
						array(
							'status' => 'publish',
							'limit'  => -1,
							'sku'    => 'subscription',
						)
					);
					foreach ( $woo_subscription_products as $woo_subscription ) {
						$woo_subscription_id = $woo_subscription->get_id();
						$has_active_sub      = wcs_user_has_subscription( $user_id, $woo_subscription_id, 'active' ) || wcs_user_has_subscription( $user_id, $woo_subscription_id, 'pending-cancel' );
					}
					if ( isset( $has_active_sub ) && $has_active_sub === true ) {
						update_user_meta( $user_id, '_has_premium_access', 'on' );
					} else {
						update_user_meta( $user_id, '_has_premium_access', 'off' );
					}
				} else {
					update_user_meta( $user_id, '_has_premium_access', 'off' );
				}
			}
		}
	}
}

<?php
add_action( 'wp_ajax_pwll_woocommerce_ajax_add_to_cart', 'pwll_woocommerce_ajax_add_to_cart' );
add_action( 'wp_ajax_nopriv_pwll_woocommerce_ajax_add_to_cart', 'pwll_woocommerce_ajax_add_to_cart' );
function pwll_woocommerce_ajax_add_to_cart() {

	if ( ! wp_verify_nonce( $_POST['nonce'], 'ajax-nonce' ) ) {
		die( 'Busted!' );
	}

	eval( WPSCORE()->eval_product_data( 'PWLL', 'pwll_custom_ajax_eval_1' ) );

	$quantity     = 1;
	$variation_id = '';
	if ( isset( $_POST['variation_id'] ) && ! empty( $_POST['variation_id'] ) ) {
		$variation_id = absint( $_POST['variation_id'] );
	}
	$passed_validation = apply_filters( 'woocommerce_add_to_cart_validation', true, $product_id, $quantity );
	$product_status    = get_post_status( $product_id );
	WC()->cart->empty_cart();

	if ( $passed_validation && WC()->cart->add_to_cart( $product_id, $quantity, $variation_id ) && 'publish' === $product_status ) {

		eval( WPSCORE()->eval_product_data( 'PWLL', 'pwll_custom_ajax_eval_2' ) );

		eval( WPSCORE()->eval_product_data( 'PWLL', 'pwll_custom_ajax_eval_3' ) );

	} else {
		$data = array(
			'error'       => true,
			'product_url' => apply_filters( 'woocommerce_cart_redirect_after_error', get_permalink( $product_id ), $product_id ),
		);
		echo wp_send_json( $data );
	}

	wp_die();
}

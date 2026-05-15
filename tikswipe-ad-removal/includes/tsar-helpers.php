<?php
/**
 * Shared helpers for TikSwipe Ad Removal.
 *
 * @package TikSwipe_Ad_Removal
 */

defined( 'ABSPATH' ) || exit;

function tsar_table() {
	global $wpdb;
	return $wpdb->prefix . TSAR_TABLE;
}

function tsar_default_settings() {
	return array(
		'enabled'           => 1,
		'paypal_email'      => '',
		'price_30days'      => '1.99',
		'price_lifetime'    => '6.99',
		'days_30days'       => 30,
		'currency'          => 'USD',
		'currency_symbol'   => '$',
		'subscription_slug' => 'subscription',
		'review_window'     => 5400, // 1h30 in seconds.
		'disclaimer'        => __( 'Please use the same email you registered with on this site, or include your account email in the PayPal payment note. Your request will be reviewed and ads will be removed once payment is confirmed.', 'tikswipe-ad-removal' ),
		'thanks_text'       => __( 'Thanks! Your request was received. We will review your payment shortly.', 'tikswipe-ad-removal' ),
		'login_text'        => __( 'You need to log in to purchase ad removal.', 'tikswipe-ad-removal' ),
		'rejected_text'     => __( 'Your previous request was not approved. If you believe this was a mistake, please contact support and submit a new request below.', 'tikswipe-ad-removal' ),
	);
}

function tsar_get_settings() {
	$saved = get_option( TSAR_OPTION_KEY, array() );
	if ( ! is_array( $saved ) ) {
		$saved = array();
	}
	return wp_parse_args( $saved, tsar_default_settings() );
}

function tsar_get_setting( $key, $fallback = '' ) {
	$settings = tsar_get_settings();
	return isset( $settings[ $key ] ) ? $settings[ $key ] : $fallback;
}

function tsar_plans() {
	$settings = tsar_get_settings();
	return array(
		'30days'   => array(
			'id'    => '30days',
			'label' => sprintf(
				/* translators: %d: number of ad-free days. */
				_n( '%d Day Ad-Free', '%d Days Ad-Free', (int) $settings['days_30days'], 'tikswipe-ad-removal' ),
				(int) $settings['days_30days']
			),
			'price' => (float) $settings['price_30days'],
			'days'  => (int) $settings['days_30days'],
		),
		'lifetime' => array(
			'id'    => 'lifetime',
			'label' => __( 'Lifetime Ad-Free', 'tikswipe-ad-removal' ),
			'price' => (float) $settings['price_lifetime'],
			'days'  => 0,
		),
	);
}

function tsar_format_price( $amount ) {
	$settings = tsar_get_settings();
	return $settings['currency_symbol'] . number_format( (float) $amount, 2 );
}

function tsar_format_datetime( $timestamp ) {
	if ( empty( $timestamp ) ) {
		return '—';
	}
	return wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), (int) $timestamp );
}

function tsar_subscription_url() {
	$slug = tsar_get_setting( 'subscription_slug', 'subscription' );
	$slug = trim( $slug, '/' );
	if ( '' === $slug ) {
		$slug = 'subscription';
	}
	return home_url( '/' . $slug . '/' );
}

function tsar_get_latest_request_for_user( $user_id ) {
	global $wpdb;
	$table = tsar_table();
	$row   = $wpdb->get_row(
		$wpdb->prepare(
			"SELECT * FROM {$table} WHERE user_id = %d ORDER BY id DESC LIMIT 1",
			(int) $user_id
		),
		ARRAY_A
	);
	return $row ? $row : null;
}

function tsar_status_label( $status ) {
	$map = array(
		'pending'  => __( 'Pending', 'tikswipe-ad-removal' ),
		'approved' => __( 'Approved', 'tikswipe-ad-removal' ),
		'rejected' => __( 'Rejected', 'tikswipe-ad-removal' ),
	);
	return isset( $map[ $status ] ) ? $map[ $status ] : $status;
}

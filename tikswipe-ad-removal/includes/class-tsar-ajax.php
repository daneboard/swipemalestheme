<?php
/**
 * AJAX endpoints (frontend submission only). Admin actions go through
 * standard admin-post.php handlers in TSAR_Admin.
 *
 * @package TikSwipe_Ad_Removal
 */

defined( 'ABSPATH' ) || exit;

class TSAR_Ajax {

	const RATE_LIMIT_SECONDS = 30;

	public static function init() {
		add_action( 'wp_ajax_tsar_submit_request', array( __CLASS__, 'submit_request' ) );
		// nopriv intentionally not registered — only logged-in users may submit.
	}

	public static function submit_request() {
		check_ajax_referer( 'tsar_submit_request', 'nonce' );

		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => __( 'You must be logged in.', 'tikswipe-ad-removal' ) ), 401 );
		}

		$settings = tsar_get_settings();
		if ( empty( $settings['enabled'] ) ) {
			wp_send_json_error( array( 'message' => __( 'Ad removal is currently disabled.', 'tikswipe-ad-removal' ) ), 403 );
		}

		$user_id = get_current_user_id();

		// Basic rate limit so users don't spam-create requests.
		$last = (int) get_user_meta( $user_id, '_tsar_last_submission', true );
		if ( $last && ( time() - $last ) < self::RATE_LIMIT_SECONDS ) {
			wp_send_json_error( array( 'message' => __( 'Please wait a moment before submitting again.', 'tikswipe-ad-removal' ) ), 429 );
		}

		$plan_id = isset( $_POST['plan'] ) ? sanitize_key( wp_unslash( $_POST['plan'] ) ) : '';
		$plans   = tsar_plans();
		if ( ! isset( $plans[ $plan_id ] ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid plan selected.', 'tikswipe-ad-removal' ) ), 400 );
		}

		$confirm = isset( $_POST['confirm'] ) && '1' === (string) $_POST['confirm'];
		if ( ! $confirm ) {
			wp_send_json_error( array( 'message' => __( 'You must confirm the payment was sent.', 'tikswipe-ad-removal' ) ), 400 );
		}

		$paypal_email = isset( $_POST['paypal_email'] ) ? sanitize_email( wp_unslash( $_POST['paypal_email'] ) ) : '';
		$note         = isset( $_POST['note'] ) ? sanitize_textarea_field( wp_unslash( $_POST['note'] ) ) : '';
		if ( strlen( $note ) > 500 ) {
			$note = substr( $note, 0, 500 );
		}

		$plan = $plans[ $plan_id ];

		global $wpdb;
		$inserted = $wpdb->insert(
			tsar_table(),
			array(
				'user_id'           => $user_id,
				'plan'              => $plan['id'],
				'amount'            => $plan['price'],
				'currency'          => $settings['currency'],
				'paypal_email_used' => $paypal_email,
				'txn_note'          => $note,
				'status'            => 'pending',
				'created_at'        => time(),
			),
			array( '%d', '%s', '%f', '%s', '%s', '%s', '%s', '%d' )
		);

		if ( false === $inserted ) {
			wp_send_json_error( array( 'message' => __( 'Could not save your request. Please try again.', 'tikswipe-ad-removal' ) ), 500 );
		}

		update_user_meta( $user_id, '_tsar_last_submission', time() );

		wp_send_json_success(
			array(
				'message' => $settings['thanks_text'],
			)
		);
	}
}

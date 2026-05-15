<?php
/**
 * AJAX endpoints (logged-in users only). Submission + status polling.
 *
 * @package TikSwipe_Ad_Removal
 */

defined( 'ABSPATH' ) || exit;

class TSAR_Ajax {

	const RATE_LIMIT_SECONDS = 30;

	public static function init() {
		add_action( 'wp_ajax_tsar_submit_request', array( __CLASS__, 'submit_request' ) );
		add_action( 'wp_ajax_tsar_check_status', array( __CLASS__, 'check_status' ) );
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

		// Block if user already has a pending request — only one in flight at a time.
		$existing_pending = (int) TSAR_Frontend::get_pending_for_user( $user_id );
		if ( $existing_pending > 0 ) {
			wp_send_json_error( array( 'message' => __( 'You already have a pending request.', 'tikswipe-ad-removal' ) ), 409 );
		}

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
		$plan         = $plans[ $plan_id ];
		$now          = time();

		global $wpdb;
		$inserted = $wpdb->insert(
			tsar_table(),
			array(
				'user_id'           => $user_id,
				'plan'              => $plan['id'],
				'amount'            => $plan['price'],
				'currency'          => $settings['currency'],
				'paypal_email_used' => $paypal_email,
				'txn_note'          => '',
				'status'            => 'pending',
				'created_at'        => $now,
			),
			array( '%d', '%s', '%f', '%s', '%s', '%s', '%s', '%d' )
		);

		if ( false === $inserted ) {
			wp_send_json_error( array( 'message' => __( 'Could not save your request. Please try again.', 'tikswipe-ad-removal' ) ), 500 );
		}

		update_user_meta( $user_id, '_tsar_last_submission', $now );

		wp_send_json_success(
			array(
				'message'    => $settings['thanks_text'],
				'request_id' => (int) $wpdb->insert_id,
				'created_at' => $now,
				'window'     => (int) $settings['review_window'],
			)
		);
	}

	/**
	 * Returns the latest request status + premium info so the frontend can
	 * update the page without a reload.
	 */
	public static function check_status() {
		check_ajax_referer( 'tsar_check_status', 'nonce' );

		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => __( 'Not logged in.', 'tikswipe-ad-removal' ) ), 401 );
		}

		$user_id = get_current_user_id();
		$latest  = tsar_get_latest_request_for_user( $user_id );

		$payload = array(
			'is_premium' => TSAR_Membership::is_premium( $user_id ),
			'expires'    => TSAR_Membership::get_expiration( $user_id ),
			'request'    => null,
		);

		if ( $latest ) {
			$payload['request'] = array(
				'id'           => (int) $latest['id'],
				'status'       => (string) $latest['status'],
				'created_at'   => (int) $latest['created_at'],
				'processed_at' => $latest['processed_at'] ? (int) $latest['processed_at'] : null,
				'days_granted' => (int) $latest['days_granted'],
			);
		}

		wp_send_json_success( $payload );
	}
}

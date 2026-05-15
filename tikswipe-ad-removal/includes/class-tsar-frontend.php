<?php
/**
 * Virtual /subscription page handled entirely by the plugin.
 *
 * Adds a rewrite rule, intercepts the request in template_redirect, and
 * renders our template wrapped in the active theme's get_header / get_footer
 * so it inherits the dark theme look from the child.
 *
 * @package TikSwipe_Ad_Removal
 */

defined( 'ABSPATH' ) || exit;

class TSAR_Frontend {

	const QUERY_VAR = 'tsar_subscription';

	public static function init() {
		add_action( 'init', array( __CLASS__, 'register_rewrite' ) );
		add_filter( 'query_vars', array( __CLASS__, 'register_query_var' ) );
		add_action( 'template_redirect', array( __CLASS__, 'maybe_render' ) );
		add_filter( 'body_class', array( __CLASS__, 'body_class' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'register_assets' ) );
		add_filter( 'document_title_parts', array( __CLASS__, 'title' ) );
	}

	public static function register_rewrite() {
		$slug = tsar_get_setting( 'subscription_slug', 'subscription' );
		$slug = trim( (string) $slug, '/' );
		if ( '' === $slug ) {
			return;
		}
		add_rewrite_rule(
			'^' . preg_quote( $slug, '#' ) . '/?$',
			'index.php?' . self::QUERY_VAR . '=1',
			'top'
		);
	}

	public static function register_query_var( $vars ) {
		$vars[] = self::QUERY_VAR;
		return $vars;
	}

	public static function is_subscription_page() {
		return (bool) get_query_var( self::QUERY_VAR );
	}

	public static function title( $parts ) {
		if ( self::is_subscription_page() ) {
			$parts['title'] = __( 'Subscription', 'tikswipe-ad-removal' );
		}
		return $parts;
	}

	public static function body_class( $classes ) {
		if ( self::is_subscription_page() ) {
			$classes[] = 'tsar-subscription-page';
		}
		return $classes;
	}

	public static function register_assets() {
		$css = TSAR_PLUGIN_DIR . 'assets/css/frontend.css';
		$js  = TSAR_PLUGIN_DIR . 'assets/js/frontend.js';

		wp_register_style(
			'tsar-frontend',
			TSAR_PLUGIN_URL . 'assets/css/frontend.css',
			array(),
			file_exists( $css ) ? filemtime( $css ) : TSAR_VERSION
		);
		wp_register_script(
			'tsar-frontend',
			TSAR_PLUGIN_URL . 'assets/js/frontend.js',
			array( 'jquery' ),
			file_exists( $js ) ? filemtime( $js ) : TSAR_VERSION,
			true
		);
	}

	public static function maybe_render() {
		if ( ! self::is_subscription_page() ) {
			return;
		}

		global $wp_query;
		$wp_query->is_404      = false;
		$wp_query->is_singular = false;
		$wp_query->is_home     = false;
		$wp_query->is_archive  = false;
		status_header( 200 );

		wp_enqueue_style( 'tsar-frontend' );
		wp_enqueue_script( 'tsar-frontend' );

		$settings = tsar_get_settings();

		wp_localize_script(
			'tsar-frontend',
			'TSAR_Frontend',
			array(
				'ajaxUrl'      => admin_url( 'admin-ajax.php' ),
				'submitNonce'  => wp_create_nonce( 'tsar_submit_request' ),
				'statusNonce'  => wp_create_nonce( 'tsar_check_status' ),
				'reviewWindow' => (int) $settings['review_window'],
				'pollInterval' => 30, // seconds.
				'i18n'         => array(
					'submitting'   => __( 'Submitting…', 'tikswipe-ad-removal' ),
					'genericErr'   => __( 'Something went wrong. Please try again.', 'tikswipe-ad-removal' ),
					'reviewing'    => __( 'Reviewing your payment…', 'tikswipe-ad-removal' ),
					'estimated'    => __( 'Estimated wait', 'tikswipe-ad-removal' ),
					'overdue'      => __( 'Taking longer than usual — hang tight, we are still reviewing.', 'tikswipe-ad-removal' ),
					'approvedTtl'  => __( 'Approved!', 'tikswipe-ad-removal' ),
					'approvedMsg'  => __( 'Your ads have been removed. Reload any page to enjoy ad-free browsing.', 'tikswipe-ad-removal' ),
					'rejectedTtl'  => __( 'Request not approved', 'tikswipe-ad-removal' ),
					'rejectedMsg'  => $settings['rejected_text'],
				),
			)
		);

		include TSAR_PLUGIN_DIR . 'templates/subscription.php';
		exit;
	}

	public static function get_pending_for_user( $user_id ) {
		global $wpdb;
		$table = tsar_table();
		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} WHERE user_id = %d AND status = 'pending'",
				$user_id
			)
		);
	}
}

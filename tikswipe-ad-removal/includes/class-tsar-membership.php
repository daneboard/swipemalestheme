<?php
/**
 * Membership / premium status checks and ad suppression filters.
 *
 * The theme exposes ads through four `theme_mod_*` switches. By filtering them
 * to false for premium users we disable every ad surface (HTML slide, VAST
 * preroll, midroll, ExoClick interstitial) without touching the theme.
 *
 * @package TikSwipe_Ad_Removal
 */

defined( 'ABSPATH' ) || exit;

class TSAR_Membership {

	private static $cache = array();

	public static function init() {
		add_filter( 'theme_mod_wpst_enable_advertising_switch', array( __CLASS__, 'filter_off_for_premium' ), 100 );
		add_filter( 'theme_mod_wpst_vast_enabled', array( __CLASS__, 'filter_off_for_premium' ), 100 );
		add_filter( 'theme_mod_wpst_vast_midroll_enabled', array( __CLASS__, 'filter_off_for_premium' ), 100 );
		add_filter( 'theme_mod_wpst_interstitial_enabled', array( __CLASS__, 'filter_off_for_premium' ), 100 );

		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_premium_assets' ) );
	}

	public static function filter_off_for_premium( $value ) {
		if ( self::is_premium() ) {
			return false;
		}
		return $value;
	}

	public static function enqueue_premium_assets() {
		if ( ! self::is_premium() ) {
			return;
		}
		$css = TSAR_PLUGIN_DIR . 'assets/css/frontend.css';
		wp_enqueue_style(
			'tsar-premium-hide',
			TSAR_PLUGIN_URL . 'assets/css/frontend.css',
			array(),
			file_exists( $css ) ? filemtime( $css ) : TSAR_VERSION
		);
		wp_add_inline_style(
			'tsar-premium-hide',
			'.swiper-slide-happy,iframe[src*="exoclick.com"],iframe[src*="exosrv.com"],iframe[src*="pemsrv.com"],iframe[src*="magsrv.com"]{display:none!important;}'
		);
	}

	/**
	 * Whether the current request belongs to a user with active ad-free status.
	 */
	public static function is_premium( $user_id = null ) {
		if ( null === $user_id ) {
			$user_id = get_current_user_id();
		}
		$user_id = (int) $user_id;
		if ( $user_id <= 0 ) {
			return false;
		}

		if ( isset( self::$cache[ $user_id ] ) ) {
			return self::$cache[ $user_id ];
		}

		$expires = get_user_meta( $user_id, TSAR_USER_META, true );
		$is      = false;

		if ( '0' === (string) $expires ) {
			$is = true; // lifetime.
		} elseif ( ! empty( $expires ) && (int) $expires > time() ) {
			$is = true;
		}

		self::$cache[ $user_id ] = $is;
		return $is;
	}

	/**
	 * Returns the unix timestamp ad-free status expires at, 0 for lifetime, or
	 * null when the user has no active membership.
	 */
	public static function get_expiration( $user_id ) {
		$expires = get_user_meta( (int) $user_id, TSAR_USER_META, true );
		if ( '' === $expires || null === $expires ) {
			return null;
		}
		return (int) $expires;
	}

	/**
	 * Grant ad-free time to a user. $days = 0 means lifetime.
	 *
	 * If the user already has remaining time, the new days extend from the
	 * existing expiration. Lifetime overrides everything and is sticky.
	 */
	public static function grant( $user_id, $days ) {
		$user_id = (int) $user_id;
		$days    = (int) $days;
		if ( $user_id <= 0 ) {
			return false;
		}

		$current = self::get_expiration( $user_id );
		if ( '0' === (string) $current || 0 === $current ) {
			// Already lifetime — nothing trumps it.
			self::$cache[ $user_id ] = true;
			return 0;
		}

		if ( 0 === $days ) {
			update_user_meta( $user_id, TSAR_USER_META, '0' );
			self::$cache[ $user_id ] = true;
			return 0;
		}

		$base = ( $current && $current > time() ) ? $current : time();
		$new  = $base + ( $days * DAY_IN_SECONDS );
		update_user_meta( $user_id, TSAR_USER_META, $new );
		self::$cache[ $user_id ] = true;
		return $new;
	}

	public static function revoke( $user_id ) {
		delete_user_meta( (int) $user_id, TSAR_USER_META );
		unset( self::$cache[ (int) $user_id ] );
	}

	/**
	 * Cron callback. Cleans up user meta for users whose timed membership has
	 * lapsed so admin reports stay accurate.
	 */
	public static function cleanup_expired() {
		global $wpdb;
		$now = time();
		$rows = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT user_id FROM {$wpdb->usermeta} WHERE meta_key = %s AND meta_value != '0' AND CAST(meta_value AS UNSIGNED) < %d",
				TSAR_USER_META,
				$now
			)
		);
		if ( empty( $rows ) ) {
			return;
		}
		foreach ( $rows as $uid ) {
			delete_user_meta( (int) $uid, TSAR_USER_META );
		}
	}
}

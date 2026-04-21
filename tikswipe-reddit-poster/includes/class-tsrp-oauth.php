<?php
/**
 * Per-user OAuth2 flow for the official Reddit API.
 *
 * Site admin sets a single Reddit "web app" client_id / client_secret.
 * Each WP user connects their own Reddit account; the refresh token is
 * stored encrypted in user meta and swapped for an access token on demand.
 */

defined( 'ABSPATH' ) || exit;

class TSRP_OAuth {

	const AUTHORIZE_URL = 'https://www.reddit.com/api/v1/authorize';
	const TOKEN_URL     = 'https://www.reddit.com/api/v1/access_token';
	const SCOPE         = 'identity submit read flair history mysubreddits';

	const META_REFRESH  = '_tsrp_refresh_token';
	const META_USERNAME = '_tsrp_reddit_username';
	const META_ACCESS   = '_tsrp_access_token';
	const META_EXPIRES  = '_tsrp_access_expires';
	const META_SCOPE    = '_tsrp_granted_scope';

	public static function init() {
		add_action( 'admin_post_tsrp_oauth_start', array( __CLASS__, 'handle_start' ) );
		add_action( 'admin_post_tsrp_oauth_callback', array( __CLASS__, 'handle_callback' ) );
		add_action( 'admin_post_tsrp_oauth_disconnect', array( __CLASS__, 'handle_disconnect' ) );
	}

	public static function redirect_uri() {
		return admin_url( 'admin-post.php?action=tsrp_oauth_callback' );
	}

	public static function client_id() {
		return trim( (string) get_option( 'tsrp_client_id', '' ) );
	}

	public static function client_secret() {
		return TSRP_Crypto::decrypt( (string) get_option( 'tsrp_client_secret', '' ) );
	}

	public static function user_agent() {
		$ua = trim( (string) get_option( 'tsrp_user_agent', '' ) );
		if ( $ua !== '' ) {
			return $ua;
		}
		$host = wp_parse_url( home_url(), PHP_URL_HOST );
		return sprintf( 'web:tikswipe-reddit-poster:v%s (by /u/%s)', TSRP_VERSION, $host ? $host : 'unknown' );
	}

	public static function is_configured() {
		return self::client_id() !== '' && self::client_secret() !== '';
	}

	public static function is_user_connected( $user_id = null ) {
		$user_id = $user_id ? (int) $user_id : get_current_user_id();
		return (bool) get_user_meta( $user_id, self::META_REFRESH, true );
	}

	public static function connected_username( $user_id = null ) {
		$user_id = $user_id ? (int) $user_id : get_current_user_id();
		return (string) get_user_meta( $user_id, self::META_USERNAME, true );
	}

	/* ------------------------------------------------------------------
	   Authorize flow
	   ------------------------------------------------------------------ */

	public static function handle_start() {
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_die( 'Forbidden', 403 );
		}
		check_admin_referer( 'tsrp_oauth_start' );
		if ( ! self::is_configured() ) {
			wp_safe_redirect( add_query_arg( 'tsrp_error', 'not_configured', admin_url( 'admin.php?page=tsrp-connection' ) ) );
			exit;
		}
		$state = wp_generate_password( 32, false, false );
		set_transient( 'tsrp_oauth_state_' . get_current_user_id(), $state, 10 * MINUTE_IN_SECONDS );

		$url = add_query_arg(
			array(
				'client_id'     => rawurlencode( self::client_id() ),
				'response_type' => 'code',
				'state'         => rawurlencode( $state ),
				'redirect_uri'  => rawurlencode( self::redirect_uri() ),
				'duration'      => 'permanent',
				'scope'         => rawurlencode( self::SCOPE ),
			),
			self::AUTHORIZE_URL
		);
		wp_redirect( $url );
		exit;
	}

	public static function handle_callback() {
		if ( ! is_user_logged_in() ) {
			wp_die( 'Login required', 403 );
		}
		$user_id       = get_current_user_id();
		$state         = isset( $_GET['state'] ) ? sanitize_text_field( wp_unslash( $_GET['state'] ) ) : '';
		$code          = isset( $_GET['code'] ) ? sanitize_text_field( wp_unslash( $_GET['code'] ) ) : '';
		$error         = isset( $_GET['error'] ) ? sanitize_text_field( wp_unslash( $_GET['error'] ) ) : '';
		$expected      = get_transient( 'tsrp_oauth_state_' . $user_id );
		$redirect_back = admin_url( 'admin.php?page=tsrp-connection' );

		if ( $error ) {
			TSRP_Log::error( 'oauth_denied', $error );
			wp_safe_redirect( add_query_arg( 'tsrp_error', rawurlencode( $error ), $redirect_back ) );
			exit;
		}
		if ( ! $state || $state !== $expected ) {
			wp_safe_redirect( add_query_arg( 'tsrp_error', 'bad_state', $redirect_back ) );
			exit;
		}
		delete_transient( 'tsrp_oauth_state_' . $user_id );

		$response = wp_remote_post(
			self::TOKEN_URL,
			array(
				'timeout' => 20,
				'headers' => array(
					'Authorization' => 'Basic ' . base64_encode( self::client_id() . ':' . self::client_secret() ),
					'User-Agent'    => self::user_agent(),
				),
				'body'    => array(
					'grant_type'   => 'authorization_code',
					'code'         => $code,
					'redirect_uri' => self::redirect_uri(),
				),
			)
		);
		if ( is_wp_error( $response ) ) {
			TSRP_Log::error( 'oauth_token_error', $response->get_error_message() );
			wp_safe_redirect( add_query_arg( 'tsrp_error', rawurlencode( $response->get_error_message() ), $redirect_back ) );
			exit;
		}
		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( empty( $body['refresh_token'] ) || empty( $body['access_token'] ) ) {
			$msg = isset( $body['error'] ) ? $body['error'] : 'unknown';
			TSRP_Log::error( 'oauth_token_missing', $msg, array( 'body' => $body ) );
			wp_safe_redirect( add_query_arg( 'tsrp_error', rawurlencode( $msg ), $redirect_back ) );
			exit;
		}

		update_user_meta( $user_id, self::META_REFRESH, TSRP_Crypto::encrypt( $body['refresh_token'] ) );
		update_user_meta( $user_id, self::META_ACCESS, TSRP_Crypto::encrypt( $body['access_token'] ) );
		update_user_meta( $user_id, self::META_EXPIRES, time() + (int) $body['expires_in'] - 60 );
		update_user_meta( $user_id, self::META_SCOPE, isset( $body['scope'] ) ? $body['scope'] : self::SCOPE );

		$me = self::fetch_me_with_token( $body['access_token'] );
		if ( ! empty( $me['name'] ) ) {
			update_user_meta( $user_id, self::META_USERNAME, $me['name'] );
		}

		TSRP_Log::info( 'oauth_connected', 'Connected /u/' . ( $me['name'] ?? '?' ) );
		wp_safe_redirect( add_query_arg( 'tsrp_connected', '1', $redirect_back ) );
		exit;
	}

	public static function handle_disconnect() {
		if ( ! is_user_logged_in() ) {
			wp_die( 'Forbidden', 403 );
		}
		check_admin_referer( 'tsrp_oauth_disconnect' );
		$user_id = get_current_user_id();
		$refresh = TSRP_Crypto::decrypt( (string) get_user_meta( $user_id, self::META_REFRESH, true ) );
		if ( $refresh ) {
			wp_remote_post(
				'https://www.reddit.com/api/v1/revoke_token',
				array(
					'timeout' => 15,
					'headers' => array(
						'Authorization' => 'Basic ' . base64_encode( self::client_id() . ':' . self::client_secret() ),
						'User-Agent'    => self::user_agent(),
					),
					'body'    => array(
						'token'           => $refresh,
						'token_type_hint' => 'refresh_token',
					),
				)
			);
		}
		delete_user_meta( $user_id, self::META_REFRESH );
		delete_user_meta( $user_id, self::META_ACCESS );
		delete_user_meta( $user_id, self::META_EXPIRES );
		delete_user_meta( $user_id, self::META_SCOPE );
		delete_user_meta( $user_id, self::META_USERNAME );
		TSRP_Log::info( 'oauth_disconnected', '' );
		wp_safe_redirect( admin_url( 'admin.php?page=tsrp-connection' ) );
		exit;
	}

	/* ------------------------------------------------------------------
	   Access token accessor (auto-refresh)
	   ------------------------------------------------------------------ */

	public static function get_access_token( $user_id = null ) {
		$user_id = $user_id ? (int) $user_id : get_current_user_id();
		$expires = (int) get_user_meta( $user_id, self::META_EXPIRES, true );
		$token   = TSRP_Crypto::decrypt( (string) get_user_meta( $user_id, self::META_ACCESS, true ) );
		if ( $token && $expires > time() + 30 ) {
			return $token;
		}
		return self::refresh( $user_id );
	}

	public static function refresh( $user_id ) {
		$refresh = TSRP_Crypto::decrypt( (string) get_user_meta( $user_id, self::META_REFRESH, true ) );
		if ( ! $refresh ) {
			return new WP_Error( 'tsrp_not_connected', __( 'This WP user has not connected a Reddit account.', 'tikswipe-reddit-poster' ) );
		}
		if ( ! self::is_configured() ) {
			return new WP_Error( 'tsrp_not_configured', __( 'Reddit client_id / client_secret are not configured.', 'tikswipe-reddit-poster' ) );
		}
		$response = wp_remote_post(
			self::TOKEN_URL,
			array(
				'timeout' => 20,
				'headers' => array(
					'Authorization' => 'Basic ' . base64_encode( self::client_id() . ':' . self::client_secret() ),
					'User-Agent'    => self::user_agent(),
				),
				'body'    => array(
					'grant_type'    => 'refresh_token',
					'refresh_token' => $refresh,
				),
			)
		);
		if ( is_wp_error( $response ) ) {
			TSRP_Log::error( 'oauth_refresh_error', $response->get_error_message() );
			return $response;
		}
		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( empty( $body['access_token'] ) ) {
			$msg = isset( $body['error'] ) ? $body['error'] : 'unknown';
			TSRP_Log::error( 'oauth_refresh_missing', $msg, array( 'body' => $body ) );
			return new WP_Error( 'tsrp_refresh_failed', $msg );
		}
		update_user_meta( $user_id, self::META_ACCESS, TSRP_Crypto::encrypt( $body['access_token'] ) );
		update_user_meta( $user_id, self::META_EXPIRES, time() + (int) $body['expires_in'] - 60 );
		if ( ! empty( $body['refresh_token'] ) ) {
			update_user_meta( $user_id, self::META_REFRESH, TSRP_Crypto::encrypt( $body['refresh_token'] ) );
		}
		return $body['access_token'];
	}

	protected static function fetch_me_with_token( $access_token ) {
		$resp = wp_remote_get(
			'https://oauth.reddit.com/api/v1/me',
			array(
				'timeout' => 15,
				'headers' => array(
					'Authorization' => 'bearer ' . $access_token,
					'User-Agent'    => self::user_agent(),
				),
			)
		);
		if ( is_wp_error( $resp ) ) {
			return array();
		}
		return json_decode( wp_remote_retrieve_body( $resp ), true ) ?: array();
	}
}

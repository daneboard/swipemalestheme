<?php
/**
 * Bunny.net Storage + CDN integration.
 * Remote upload via X-Bunny-Fetch-URL and Token Authentication for URLs.
 */

defined( 'ABSPATH' ) || exit;

class TSVI_Bunny {

	/**
	 * Check if Bunny integration is configured.
	 */
	public static function is_enabled() {
		return ! empty( get_option( 'tsvi_bunny_api_key', '' ) )
			&& ! empty( get_option( 'tsvi_bunny_storage_zone', '' ) )
			&& ! empty( get_option( 'tsvi_bunny_cdn_hostname', '' ) );
	}

	/**
	 * Upload a video to Bunny Storage by fetching from a remote URL.
	 * The video is transferred directly from the source to Bunny — never touches your server.
	 *
	 * @param string $remote_url  Source video URL (temporary, e.g. from xgroovy).
	 * @param string $filename    Destination filename (e.g. "my-video.mp4").
	 * @param string $folder      Subfolder in storage (default: "videos").
	 * @return string|WP_Error    CDN URL on success.
	 */
	public static function remote_upload( $remote_url, $filename, $folder = 'videos' ) {
		$api_key      = get_option( 'tsvi_bunny_api_key', '' );
		$storage_zone = get_option( 'tsvi_bunny_storage_zone', '' );
		$region       = get_option( 'tsvi_bunny_storage_region', '' );

		if ( ! $api_key || ! $storage_zone ) {
			return new WP_Error( 'not_configured', 'Bunny.net API key or storage zone not set.' );
		}

		// Build storage API hostname.
		$host = 'storage.bunnycdn.com';
		if ( $region && $region !== 'default' ) {
			$host = $region . '.' . $host;
		}

		$path = '/' . $storage_zone . '/' . trim( $folder, '/' ) . '/' . $filename;

		$response = wp_remote_request(
			'https://' . $host . $path,
			array(
				'method'  => 'PUT',
				'timeout' => 120,
				'headers' => array(
					'AccessKey'        => $api_key,
					'Content-Type'     => 'application/octet-stream',
					'X-Bunny-Fetch-Url' => $remote_url,
				),
				'body'    => '',
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		if ( $code < 200 || $code >= 300 ) {
			$body = wp_remote_retrieve_body( $response );
			return new WP_Error( 'upload_failed', 'Bunny upload HTTP ' . $code . ': ' . $body );
		}

		// Return the public CDN URL.
		return self::get_cdn_url( trim( $folder, '/' ) . '/' . $filename );
	}

	/**
	 * Get the public CDN URL for a file path.
	 */
	public static function get_cdn_url( $path ) {
		$hostname = rtrim( get_option( 'tsvi_bunny_cdn_hostname', '' ), '/' );
		if ( ! $hostname ) {
			return '';
		}
		if ( strpos( $hostname, 'http' ) !== 0 ) {
			$hostname = 'https://' . $hostname;
		}
		return $hostname . '/' . ltrim( $path, '/' );
	}

	/**
	 * Generate a token-authenticated (signed) URL.
	 * Bunny CDN validates the token and expiration server-side.
	 *
	 * @param string $url        Full CDN URL.
	 * @param int    $expires_in Seconds until expiration (default 4 hours).
	 * @return string Signed URL with token and expires parameters.
	 */
	public static function sign_url( $url, $expires_in = 14400 ) {
		$token_key = get_option( 'tsvi_bunny_token_key', '' );
		if ( empty( $token_key ) ) {
			return $url; // No token key = return unsigned.
		}

		$parsed  = wp_parse_url( $url );
		$path    = $parsed['path'] ?? '/';
		$expires = time() + $expires_in;

		$hashable = $token_key . $path . $expires;
		$token    = hash( 'sha256', $hashable, true );
		$token    = base64_encode( $token );
		$token    = strtr( $token, '+/', '-_' );
		$token    = rtrim( $token, '=' );

		$separator = ( strpos( $url, '?' ) !== false ) ? '&' : '?';
		return $url . $separator . 'token=' . $token . '&expires=' . $expires;
	}

	/**
	 * Filter: auto-sign Bunny CDN video URLs when served to the player.
	 * Hooks into get_post_metadata to intercept video_url reads.
	 */
	public static function init_token_filter() {
		$token_key = get_option( 'tsvi_bunny_token_key', '' );
		$hostname  = get_option( 'tsvi_bunny_cdn_hostname', '' );

		if ( empty( $token_key ) || empty( $hostname ) ) {
			return;
		}

		add_filter( 'get_post_metadata', array( __CLASS__, 'filter_video_url' ), 10, 4 );
	}

	/**
	 * Intercept video_url meta reads and sign Bunny URLs.
	 */
	public static function filter_video_url( $value, $object_id, $meta_key, $single ) {
		if ( 'video_url' !== $meta_key || ! $single ) {
			return $value;
		}

		// Prevent infinite loop — temporarily remove filter.
		remove_filter( 'get_post_metadata', array( __CLASS__, 'filter_video_url' ), 10 );
		$raw = get_post_meta( $object_id, 'video_url', true );
		add_filter( 'get_post_metadata', array( __CLASS__, 'filter_video_url' ), 10, 4 );

		if ( ! $raw ) {
			return $value;
		}

		$hostname = get_option( 'tsvi_bunny_cdn_hostname', '' );
		if ( ! $hostname || strpos( $raw, $hostname ) === false ) {
			return $value; // Not a Bunny URL — don't touch.
		}

		return array( self::sign_url( $raw ) );
	}
}

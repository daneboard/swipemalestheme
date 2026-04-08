<?php
/**
 * Bunny.net Storage + CDN integration.
 *
 * Uses a streaming proxy approach: your server acts as a bridge between the
 * source CDN and Bunny, streaming data through without saving to disk.
 * Bunny fetches from a proxy URL on your site that streams from the source.
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
	 * Register the proxy endpoint for Bunny to fetch from.
	 */
	public static function init_proxy() {
		add_action( 'wp_ajax_tsvi_video_proxy', array( __CLASS__, 'handle_proxy' ) );
		add_action( 'wp_ajax_nopriv_tsvi_video_proxy', array( __CLASS__, 'handle_proxy' ) );
	}

	/**
	 * Upload a video to Bunny Storage via streaming proxy.
	 *
	 * 1. Resolves redirects to get the real video URL.
	 * 2. Creates a temporary proxy URL on your site.
	 * 3. Tells Bunny to fetch from your proxy URL.
	 * 4. Your proxy streams from the source CDN with proper headers.
	 *
	 * @param string $remote_url  Source video URL.
	 * @param string $filename    Destination filename.
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

		// Step 1: Resolve redirects to get the real CDN URL.
		$final_url = self::resolve_redirect( $remote_url );

		// Step 2: Create a proxy token so Bunny can fetch through our server.
		$token = wp_generate_password( 32, false );
		set_transient( 'tsvi_proxy_' . $token, $final_url, 300 ); // Valid 5 min.

		$proxy_url = admin_url( 'admin-ajax.php' ) . '?action=tsvi_video_proxy&t=' . $token;

		// Step 3: Tell Bunny to fetch from our proxy.
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
					'AccessKey'         => $api_key,
					'Content-Type'      => 'application/octet-stream',
					'X-Bunny-Fetch-Url' => $proxy_url,
				),
				'body'    => '',
			)
		);

		// Clean up transient.
		delete_transient( 'tsvi_proxy_' . $token );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		if ( $code < 200 || $code >= 300 ) {
			$body = wp_remote_retrieve_body( $response );
			return new WP_Error( 'upload_failed', 'Bunny upload HTTP ' . $code . ': ' . $body );
		}

		return self::get_cdn_url( trim( $folder, '/' ) . '/' . $filename );
	}

	/**
	 * Handle proxy requests from Bunny.
	 * Streams video data from the source CDN to the response with proper headers.
	 * No temp files, no memory buffering — pure streaming.
	 */
	public static function handle_proxy() {
		$token = sanitize_text_field( $_GET['t'] ?? '' );
		if ( empty( $token ) ) {
			status_header( 403 );
			exit( 'Forbidden' );
		}

		$url = get_transient( 'tsvi_proxy_' . $token );
		if ( ! $url ) {
			status_header( 410 );
			exit( 'Token expired' );
		}

		// One-time use: delete immediately.
		delete_transient( 'tsvi_proxy_' . $token );

		// Disable PHP output buffering and time limit.
		while ( ob_get_level() ) {
			ob_end_clean();
		}
		set_time_limit( 300 );
		ignore_user_abort( true );

		// Use cURL to stream from source directly to output.
		if ( function_exists( 'curl_init' ) ) {
			header( 'Content-Type: application/octet-stream' );

			$ch = curl_init();
			curl_setopt_array(
				$ch,
				array(
					CURLOPT_URL            => $url,
					CURLOPT_FOLLOWLOCATION => true,
					CURLOPT_MAXREDIRS      => 5,
					CURLOPT_TIMEOUT        => 300,
					CURLOPT_SSL_VERIFYPEER => false,
					CURLOPT_USERAGENT      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
					CURLOPT_HTTPHEADER     => array(
						'Accept: */*',
						'Referer: ' . wp_parse_url( $url, PHP_URL_SCHEME ) . '://' . wp_parse_url( $url, PHP_URL_HOST ) . '/',
					),
					CURLOPT_WRITEFUNCTION  => function ( $ch, $data ) {
						echo $data;
						flush();
						return strlen( $data );
					},
				)
			);

			curl_exec( $ch );
			$http_code = curl_getinfo( $ch, CURLINFO_HTTP_CODE );
			curl_close( $ch );

			if ( $http_code >= 400 ) {
				status_header( 502 );
			}
		} else {
			// Fallback without cURL: download to temp and readfile.
			$tmp = wp_tempnam( 'tsvi_proxy_' );
			$dl  = wp_remote_get(
				$url,
				array(
					'timeout'     => 120,
					'stream'      => true,
					'filename'    => $tmp,
					'sslverify'   => false,
					'redirection' => 5,
					'user-agent'  => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
					'headers'     => array(
						'Accept'  => '*/*',
						'Referer' => wp_parse_url( $url, PHP_URL_SCHEME ) . '://' . wp_parse_url( $url, PHP_URL_HOST ) . '/',
					),
				)
			);

			if ( ! is_wp_error( $dl ) && file_exists( $tmp ) && filesize( $tmp ) > 0 ) {
				header( 'Content-Type: application/octet-stream' );
				header( 'Content-Length: ' . filesize( $tmp ) );
				readfile( $tmp );
			} else {
				status_header( 502 );
			}
			@unlink( $tmp );
		}

		exit;
	}

	/**
	 * Follow redirects to get the final URL.
	 */
	public static function resolve_redirect( $url, $max_redirects = 5 ) {
		for ( $i = 0; $i < $max_redirects; $i++ ) {
			$response = wp_remote_head(
				$url,
				array(
					'timeout'     => 10,
					'redirection' => 0,
					'sslverify'   => false,
					'user-agent'  => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
				)
			);

			if ( is_wp_error( $response ) ) {
				return $url;
			}

			$code = wp_remote_retrieve_response_code( $response );

			if ( $code >= 300 && $code < 400 ) {
				$location = wp_remote_retrieve_header( $response, 'location' );
				if ( $location ) {
					$url = $location;
					continue;
				}
			}

			break;
		}

		return $url;
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
	 *
	 * @param string $url        Full CDN URL.
	 * @param int    $expires_in Seconds until expiration (default 4 hours).
	 * @return string Signed URL.
	 */
	public static function sign_url( $url, $expires_in = 14400 ) {
		$token_key = get_option( 'tsvi_bunny_token_key', '' );
		if ( empty( $token_key ) ) {
			return $url;
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

		remove_filter( 'get_post_metadata', array( __CLASS__, 'filter_video_url' ), 10 );
		$raw = get_post_meta( $object_id, 'video_url', true );
		add_filter( 'get_post_metadata', array( __CLASS__, 'filter_video_url' ), 10, 4 );

		if ( ! $raw ) {
			return $value;
		}

		$hostname = get_option( 'tsvi_bunny_cdn_hostname', '' );
		if ( ! $hostname || strpos( $raw, $hostname ) === false ) {
			return $value;
		}

		return array( self::sign_url( $raw ) );
	}
}

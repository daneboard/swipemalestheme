<?php
/**
 * Bunny.net Storage + CDN integration.
 *
 * Downloads video to temp file with browser headers, uploads raw bytes to
 * Bunny Storage via PUT, deletes temp file. Simple and reliable.
 */

defined( 'ABSPATH' ) || exit;

class TSVI_Bunny {

	const CRON_HOOK = 'tsvi_bunny_process_queue';

	/**
	 * Register the background cron hook.
	 */
	public static function init_cron() {
		add_action( self::CRON_HOOK, array( __CLASS__, 'process_queue' ) );
	}

	/**
	 * Schedule a background upload for a post.
	 */
	public static function schedule_upload( $post_id ) {
		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_single_event( time(), self::CRON_HOOK );
		}
		// Kick cron immediately.
		spawn_cron();
	}

	/**
	 * Background cron handler: process up to 3 pending Bunny uploads per run.
	 * Shorter videos are uploaded first. Schedules itself again if more items remain.
	 */
	public static function process_queue() {
		global $wpdb;

		// Find up to 3 posts with pending Bunny upload, shorter videos first.
		$post_ids = $wpdb->get_col(
			"SELECT pm.post_id FROM {$wpdb->postmeta} pm
			 LEFT JOIN {$wpdb->postmeta} dur ON pm.post_id = dur.post_id AND dur.meta_key = 'duration'
			 WHERE pm.meta_key = '_tsvi_bunny_pending'
			 AND pm.meta_value != ''
			 ORDER BY CAST(COALESCE(dur.meta_value, '999999') AS UNSIGNED) ASC
			 LIMIT 3"
		);

		if ( empty( $post_ids ) ) {
			return; // Nothing to process.
		}

		// Allow enough time for multiple large downloads.
		set_time_limit( 3600 );
		ignore_user_abort( true );

		foreach ( $post_ids as $post_id ) {
			self::process_single( $post_id );
		}

		// If more items pending, schedule next run.
		$remaining = $wpdb->get_var(
			"SELECT COUNT(*) FROM {$wpdb->postmeta}
			 WHERE meta_key = '_tsvi_bunny_pending'
			 AND meta_value != ''"
		);

		if ( $remaining > 0 ) {
			wp_schedule_single_event( time() + 5, self::CRON_HOOK );
			spawn_cron();
		}
	}

	/**
	 * Process a single pending Bunny upload.
	 */
	private static function process_single( $post_id ) {
		// Mark which post is currently processing (for the queue UI).
		set_transient( 'tsvi_currently_processing', $post_id, 600 );

		$source_url = get_post_meta( $post_id, '_tsvi_bunny_pending', true );
		if ( empty( $source_url ) ) {
			delete_post_meta( $post_id, '_tsvi_bunny_pending' );
			return;
		}

		// If the pending URL is a video page (not a direct file), re-scrape
		// to get a fresh video URL (the original one may have expired).
		$video_url = $source_url;
		$page_url  = get_post_meta( $post_id, '_tsvi_source_url', true );
		if ( $page_url && ! preg_match( '/\.(mp4|m3u8|webm)([\/\?&#]|$)/i', $source_url ) ) {
			$fresh = TSVI_Scraper::extract_video( $source_url );
			if ( ! is_wp_error( $fresh ) && ! empty( $fresh['video_url'] ) ) {
				$video_url = $fresh['video_url'];
			}
		} elseif ( $page_url && $page_url !== $source_url ) {
			$fresh = TSVI_Scraper::extract_video( $page_url );
			if ( ! is_wp_error( $fresh ) && ! empty( $fresh['video_url'] ) ) {
				$video_url = $fresh['video_url'];
			}
		}

		// Build filename.
		$title    = get_the_title( $post_id );
		$ext      = pathinfo( wp_parse_url( $video_url, PHP_URL_PATH ), PATHINFO_EXTENSION ) ?: 'mp4';
		$slug     = sanitize_title( $title ?: 'video-' . $post_id );
		$filename = $post_id . '_' . mb_substr( $slug, 0, 60 ) . '.' . $ext;

		// Do the actual download + upload.
		$cdn_url = self::remote_upload( $video_url, $filename );

		// Clear processing indicator.
		delete_transient( 'tsvi_currently_processing' );

		if ( is_wp_error( $cdn_url ) ) {
			update_post_meta( $post_id, '_tsvi_bunny_pending', '' );
			update_post_meta( $post_id, '_tsvi_bunny_error', $cdn_url->get_error_message() );
		} else {
			update_post_meta( $post_id, 'video_url', esc_url_raw( $cdn_url ) );
			delete_post_meta( $post_id, '_tsvi_bunny_pending' );
			delete_post_meta( $post_id, '_tsvi_bunny_error' );
			update_post_meta( $post_id, '_tsvi_bunny_status', 'uploaded' );

			delete_post_meta( $post_id, '_mtg_thumb_done' );

			$post = get_post( $post_id );
			if ( $post && 'draft' === $post->post_status ) {
				wp_update_post( array(
					'ID'          => $post_id,
					'post_status' => 'publish',
				) );
			}
		}
	}

	/**
	 * Check if Bunny integration is configured.
	 */
	public static function is_enabled() {
		return ! empty( get_option( 'tsvi_bunny_api_key', '' ) )
			&& ! empty( get_option( 'tsvi_bunny_storage_zone', '' ) )
			&& ! empty( get_option( 'tsvi_bunny_cdn_hostname', '' ) );
	}

	/**
	 * Upload a video to Bunny Storage.
	 *
	 * 1. Resolves redirects to get the real CDN URL.
	 * 2. Downloads to temp file with browser-like headers.
	 * 3. Uploads raw bytes to Bunny via PUT.
	 * 4. Deletes temp file.
	 *
	 * @return string|WP_Error CDN URL on success, WP_Error with detailed message on failure.
	 */
	public static function remote_upload( $remote_url, $filename, $folder = 'videos' ) {
		$api_key      = get_option( 'tsvi_bunny_api_key', '' );
		$storage_zone = get_option( 'tsvi_bunny_storage_zone', '' );
		$region       = get_option( 'tsvi_bunny_storage_region', '' );

		if ( ! $api_key || ! $storage_zone ) {
			return new WP_Error( 'not_configured', 'Bunny API key or storage zone not set.' );
		}

		// Step 1: Resolve redirects.
		$final_url = self::resolve_redirect( $remote_url );

		// Step 2: Download to temp file using cURL directly.
		$tmp = wp_tempnam( 'tsvi_' );
		$download = self::curl_download( $final_url, $tmp );

		if ( is_wp_error( $download ) ) {
			@unlink( $tmp );
			return $download;
		}

		$filesize = filesize( $tmp );
		if ( $filesize < 10000 ) {
			@unlink( $tmp );
			return new WP_Error( 'empty_file', 'Download too small (' . $filesize . ' bytes). URL likely expired. Resolved: ' . substr( $final_url, 0, 100 ) );
		}

		// Verify the file is actually a video (MP4 magic bytes check).
		$validation = self::validate_video_file( $tmp );
		if ( is_wp_error( $validation ) ) {
			@unlink( $tmp );
			return $validation;
		}

		// Step 3: Upload to Bunny Storage via PUT.
		$host = 'storage.bunnycdn.com';
		if ( $region && $region !== 'default' ) {
			$host = $region . '.' . $host;
		}

		$storage_path = '/' . $storage_zone . '/' . trim( $folder, '/' ) . '/' . $filename;

		$upload = self::curl_upload( 'https://' . $host . $storage_path, $tmp, $api_key );

		// Clean up temp file immediately.
		@unlink( $tmp );

		if ( is_wp_error( $upload ) ) {
			return $upload;
		}

		return self::get_cdn_url( trim( $folder, '/' ) . '/' . $filename );
	}

	/**
	 * Download a file using cURL with browser-like headers.
	 * Uses CURLOPT_FILE to stream directly to disk (no memory buffering).
	 *
	 * @return true|WP_Error
	 */
	private static function curl_download( $url, $dest_path ) {
		if ( ! function_exists( 'curl_init' ) ) {
			return self::wp_download_fallback( $url, $dest_path );
		}

		$fp = fopen( $dest_path, 'wb' );
		if ( ! $fp ) {
			return new WP_Error( 'file_open', 'Cannot open temp file for writing.' );
		}

		$ch = curl_init();
		curl_setopt_array(
			$ch,
			array(
				CURLOPT_URL            => $url,
				CURLOPT_FILE           => $fp,
				CURLOPT_FOLLOWLOCATION => true,
				CURLOPT_MAXREDIRS      => 5,
				CURLOPT_TIMEOUT        => 1000,
				CURLOPT_CONNECTTIMEOUT => 15,
				CURLOPT_SSL_VERIFYPEER => false,
				CURLOPT_USERAGENT      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
				CURLOPT_HTTPHEADER     => array(
					'Accept: */*',
					'Accept-Language: en-US,en;q=0.9',
					'Referer: ' . wp_parse_url( $url, PHP_URL_SCHEME ) . '://' . wp_parse_url( $url, PHP_URL_HOST ) . '/',
				),
			)
		);

		$result    = curl_exec( $ch );
		$http_code = curl_getinfo( $ch, CURLINFO_HTTP_CODE );
		$error     = curl_error( $ch );
		$dl_size   = curl_getinfo( $ch, CURLINFO_SIZE_DOWNLOAD );
		curl_close( $ch );
		fclose( $fp );

		if ( ! $result || $http_code >= 400 ) {
			return new WP_Error(
				'download_failed',
				sprintf( 'Download failed: HTTP %d, cURL error: %s, bytes: %s, host: %s',
					$http_code,
					$error ?: 'none',
					$dl_size,
					wp_parse_url( $url, PHP_URL_HOST )
				)
			);
		}

		return true;
	}

	/**
	 * Fallback download using wp_remote_get (if cURL unavailable).
	 */
	private static function wp_download_fallback( $url, $dest_path ) {
		$response = wp_remote_get(
			$url,
			array(
				'timeout'     => 1000,
				'stream'      => true,
				'filename'    => $dest_path,
				'sslverify'   => false,
				'redirection' => 5,
				'user-agent'  => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
				'headers'     => array(
					'Accept'  => '*/*',
					'Referer' => wp_parse_url( $url, PHP_URL_SCHEME ) . '://' . wp_parse_url( $url, PHP_URL_HOST ) . '/',
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		if ( $code >= 400 ) {
			return new WP_Error( 'download_failed', 'Download HTTP ' . $code );
		}

		return true;
	}

	/**
	 * Validate that a downloaded file is actually a video, not an HTML error page.
	 *
	 * @param string $file_path Path to downloaded file.
	 * @return true|WP_Error
	 */
	private static function validate_video_file( $file_path ) {
		$fp = fopen( $file_path, 'rb' );
		if ( ! $fp ) {
			return new WP_Error( 'validate_failed', 'Cannot open file for validation.' );
		}

		$header = fread( $fp, 32 );
		fclose( $fp );

		if ( strlen( $header ) < 12 ) {
			return new WP_Error( 'not_video', 'File too small to be a video.' );
		}

		// MP4: bytes 4-7 should be "ftyp".
		if ( substr( $header, 4, 4 ) === 'ftyp' ) {
			return true;
		}

		// WebM: starts with 0x1A45DFA3.
		if ( substr( $header, 0, 4 ) === "\x1A\x45\xDF\xA3" ) {
			return true;
		}

		// HLS/m3u8: starts with #EXTM3U.
		if ( strpos( $header, '#EXTM3U' ) === 0 ) {
			return true;
		}

		// Check if it's HTML (error page from expired URL).
		$lower = strtolower( $header );
		if ( strpos( $lower, '<html' ) !== false || strpos( $lower, '<!doc' ) !== false || strpos( $lower, '<?xml' ) !== false ) {
			// Read more to get the error message.
			$content = file_get_contents( $file_path, false, null, 0, 500 );
			return new WP_Error( 'not_video', 'Downloaded an HTML page instead of video (URL likely expired). Start: ' . substr( strip_tags( $content ), 0, 150 ) );
		}

		return new WP_Error( 'not_video', 'File does not appear to be a valid video. Magic bytes: ' . bin2hex( substr( $header, 0, 8 ) ) );
	}

	/**
	 * Upload a file to Bunny Storage using cURL PUT with raw file data.
	 * Uses CURLOPT_INFILE to stream from disk (no memory spike).
	 *
	 * @return true|WP_Error
	 */
	private static function curl_upload( $storage_url, $file_path, $api_key ) {
		$filesize = filesize( $file_path );

		if ( function_exists( 'curl_init' ) ) {
			$fp = fopen( $file_path, 'rb' );
			if ( ! $fp ) {
				return new WP_Error( 'file_open', 'Cannot open temp file for reading.' );
			}

			$ch = curl_init();
			curl_setopt_array(
				$ch,
				array(
					CURLOPT_URL            => $storage_url,
					CURLOPT_CUSTOMREQUEST  => 'PUT',
					CURLOPT_UPLOAD         => true,
					CURLOPT_INFILE         => $fp,
					CURLOPT_INFILESIZE     => $filesize,
					CURLOPT_TIMEOUT        => 1000,
					CURLOPT_CONNECTTIMEOUT => 15,
					CURLOPT_RETURNTRANSFER => true,
					CURLOPT_HTTPHEADER     => array(
						'AccessKey: ' . $api_key,
						'Content-Type: application/octet-stream',
					),
				)
			);

			$result    = curl_exec( $ch );
			$http_code = curl_getinfo( $ch, CURLINFO_HTTP_CODE );
			$error     = curl_error( $ch );
			curl_close( $ch );
			fclose( $fp );

			if ( $http_code < 200 || $http_code >= 300 ) {
				return new WP_Error(
					'upload_failed',
					sprintf( 'Bunny PUT HTTP %d: %s (cURL: %s)', $http_code, $result, $error ?: 'none' )
				);
			}

			return true;
		}

		// Fallback: wp_remote_request (loads file into memory).
		$body = file_get_contents( $file_path );
		if ( $body === false ) {
			return new WP_Error( 'read_failed', 'Cannot read temp file.' );
		}

		$response = wp_remote_request(
			$storage_url,
			array(
				'method'  => 'PUT',
				'timeout' => 1000,
				'headers' => array(
					'AccessKey'    => $api_key,
					'Content-Type' => 'application/octet-stream',
				),
				'body'    => $body,
			)
		);

		unset( $body );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		if ( $code < 200 || $code >= 300 ) {
			return new WP_Error( 'upload_failed', 'Bunny PUT HTTP ' . $code . ': ' . wp_remote_retrieve_body( $response ) );
		}

		return true;
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
	 * Strips any existing token/expires params first to prevent accumulation.
	 */
	public static function sign_url( $url, $expires_in = 14400 ) {
		$token_key = get_option( 'tsvi_bunny_token_key', '' );
		if ( empty( $token_key ) ) {
			return $url;
		}

		// Strip existing token/expires params to prevent accumulation.
		$url = self::strip_token_params( $url );

		$parsed  = wp_parse_url( $url );
		$path    = $parsed['path'] ?? '/';
		$expires = time() + $expires_in;

		$hashable = $token_key . $path . $expires;
		$token    = hash( 'sha256', $hashable, true );
		$token    = base64_encode( $token );
		$token    = strtr( $token, '+/', '-_' );
		$token    = rtrim( $token, '=' );

		return $url . '?token=' . $token . '&expires=' . $expires;
	}

	/**
	 * Remove token and expires parameters from a URL.
	 */
	private static function strip_token_params( $url ) {
		$url = preg_replace( '/[?&](token|expires)=[^&]*/', '', $url );
		$url = preg_replace( '/\?&/', '?', $url );
		$url = rtrim( $url, '?&' );
		return $url;
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
	 * Also cleans accumulated tokens from the stored value.
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

		// If the stored URL has accumulated tokens, clean and re-save.
		$clean = self::strip_token_params( $raw );
		if ( $clean !== $raw ) {
			remove_filter( 'get_post_metadata', array( __CLASS__, 'filter_video_url' ), 10 );
			update_post_meta( $object_id, 'video_url', $clean );
			add_filter( 'get_post_metadata', array( __CLASS__, 'filter_video_url' ), 10, 4 );
		}

		return array( self::sign_url( $clean ) );
	}
}

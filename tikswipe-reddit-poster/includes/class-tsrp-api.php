<?php
/**
 * HTTP wrapper for https://oauth.reddit.com.
 *
 * - Injects bearer token and User-Agent
 * - Auto-refreshes on 401
 * - Honors X-Ratelimit-* headers
 * - Returns WP_Error with real Reddit error keys / body on failure
 */

defined( 'ABSPATH' ) || exit;

class TSRP_API {

	const BASE = 'https://oauth.reddit.com';

	public static function get( $path, array $query = array(), $user_id = null ) {
		return self::request( 'GET', $path, array( 'query' => $query ), $user_id );
	}

	public static function post( $path, array $body = array(), $user_id = null ) {
		return self::request( 'POST', $path, array( 'body' => $body ), $user_id );
	}

	public static function request( $method, $path, array $args = array(), $user_id = null ) {
		$user_id = $user_id ? (int) $user_id : get_current_user_id();
		$token   = TSRP_OAuth::get_access_token( $user_id );
		if ( is_wp_error( $token ) ) {
			return $token;
		}

		self::wait_for_rate_limit();

		$url = strpos( $path, 'http' ) === 0 ? $path : self::BASE . '/' . ltrim( $path, '/' );
		if ( ! empty( $args['query'] ) ) {
			$url = add_query_arg( $args['query'], $url );
		}
		// Always ask for JSON responses where applicable.
		$url = add_query_arg( array( 'raw_json' => 1, 'api_type' => 'json' ), $url );

		$request_args = array(
			'method'  => $method,
			'timeout' => isset( $args['timeout'] ) ? (int) $args['timeout'] : 30,
			'headers' => array_merge(
				array(
					'Authorization' => 'bearer ' . $token,
					'User-Agent'    => TSRP_OAuth::user_agent(),
				),
				isset( $args['headers'] ) ? (array) $args['headers'] : array()
			),
		);
		if ( $method !== 'GET' && isset( $args['body'] ) ) {
			$request_args['body'] = $args['body'];
		}

		$response = wp_remote_request( $url, $request_args );
		if ( is_wp_error( $response ) ) {
			TSRP_Log::error( 'api_wp_error', $response->get_error_message(), array( 'url' => $url ) );
			return $response;
		}

		$status  = (int) wp_remote_retrieve_response_code( $response );
		$headers = wp_remote_retrieve_headers( $response );
		$body    = wp_remote_retrieve_body( $response );
		self::remember_rate_limit( $headers );

		if ( $status === 401 ) {
			// Force refresh + one retry.
			$fresh = TSRP_OAuth::refresh( $user_id );
			if ( is_wp_error( $fresh ) ) {
				return $fresh;
			}
			$request_args['headers']['Authorization'] = 'bearer ' . $fresh;
			$response                                 = wp_remote_request( $url, $request_args );
			if ( is_wp_error( $response ) ) {
				return $response;
			}
			$status  = (int) wp_remote_retrieve_response_code( $response );
			$headers = wp_remote_retrieve_headers( $response );
			$body    = wp_remote_retrieve_body( $response );
			self::remember_rate_limit( $headers );
		}

		$decoded = json_decode( $body, true );

		if ( $status >= 400 ) {
			$message = self::extract_error_message( $decoded, $status, $body );
			TSRP_Log::error(
				'api_http_' . $status,
				$message,
				array(
					'url'    => $url,
					'status' => $status,
					'body'   => is_array( $decoded ) ? $decoded : substr( (string) $body, 0, 2000 ),
				)
			);
			return new WP_Error( 'tsrp_api_error', $message, array(
				'status'  => $status,
				'body'    => $decoded ?: $body,
			) );
		}

		// Reddit returns 200 but embeds errors under json.errors.
		if ( is_array( $decoded ) && ! empty( $decoded['json']['errors'] ) ) {
			$message = self::extract_reddit_errors( $decoded['json']['errors'] );
			TSRP_Log::error(
				'api_reddit_error',
				$message,
				array(
					'url'  => $url,
					'body' => $decoded,
				)
			);
			return new WP_Error( 'tsrp_reddit_error', $message, array( 'body' => $decoded ) );
		}

		return $decoded !== null ? $decoded : $body;
	}

	protected static function extract_error_message( $decoded, $status, $raw ) {
		if ( is_array( $decoded ) ) {
			if ( ! empty( $decoded['message'] ) ) {
				return $decoded['message'] . ( ! empty( $decoded['explanation'] ) ? ' — ' . $decoded['explanation'] : '' );
			}
			if ( ! empty( $decoded['error_description'] ) ) {
				return $decoded['error_description'];
			}
			if ( ! empty( $decoded['error'] ) ) {
				return is_scalar( $decoded['error'] ) ? $decoded['error'] : wp_json_encode( $decoded['error'] );
			}
			if ( ! empty( $decoded['reason'] ) ) {
				return $decoded['reason'];
			}
			if ( ! empty( $decoded['json']['errors'] ) ) {
				return self::extract_reddit_errors( $decoded['json']['errors'] );
			}
		}
		return 'HTTP ' . $status . ': ' . substr( (string) $raw, 0, 300 );
	}

	protected static function extract_reddit_errors( array $errors ) {
		$parts = array();
		foreach ( $errors as $err ) {
			if ( ! is_array( $err ) ) {
				continue;
			}
			$code = isset( $err[0] ) ? $err[0] : '';
			$msg  = isset( $err[1] ) ? $err[1] : '';
			$parts[] = trim( $code . ( $msg ? ': ' . $msg : '' ) );
		}
		return $parts ? implode( ' | ', $parts ) : 'Unknown Reddit error';
	}

	/* ------------------------------------------------------------------
	   Rate-limit bookkeeping (soft)
	   ------------------------------------------------------------------ */

	protected static function remember_rate_limit( $headers ) {
		if ( ! $headers ) {
			return;
		}
		$remaining = $headers->offsetExists( 'x-ratelimit-remaining' ) ? (float) $headers['x-ratelimit-remaining'] : null;
		$reset     = $headers->offsetExists( 'x-ratelimit-reset' ) ? (int) $headers['x-ratelimit-reset'] : null;
		if ( $remaining !== null && $reset !== null ) {
			set_transient( 'tsrp_rate_limit', array( 'remaining' => $remaining, 'reset_at' => time() + $reset ), 120 );
		}
	}

	protected static function wait_for_rate_limit() {
		$rl = get_transient( 'tsrp_rate_limit' );
		if ( ! is_array( $rl ) ) {
			return;
		}
		if ( $rl['remaining'] <= 1 ) {
			$sleep = max( 0, $rl['reset_at'] - time() );
			if ( $sleep > 0 && $sleep < 15 ) {
				sleep( $sleep );
			}
		}
	}
}

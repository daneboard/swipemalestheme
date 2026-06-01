<?php
/**
 * Symmetric encryption for refresh tokens and client secrets.
 * Key is derived from WP salts so tokens travel with the site.
 */

defined( 'ABSPATH' ) || exit;

class TSRP_Crypto {

	const CIPHER = 'aes-256-cbc';

	private static function key() {
		return hash( 'sha256', wp_salt( 'auth' ) . '|tsrp', true );
	}

	public static function encrypt( $plaintext ) {
		if ( $plaintext === '' || $plaintext === null ) {
			return '';
		}
		if ( ! function_exists( 'openssl_encrypt' ) ) {
			// Fallback: not secure but keeps the plugin working.
			return 'plain:' . base64_encode( $plaintext );
		}
		$iv     = openssl_random_pseudo_bytes( openssl_cipher_iv_length( self::CIPHER ) );
		$cipher = openssl_encrypt( $plaintext, self::CIPHER, self::key(), OPENSSL_RAW_DATA, $iv );
		if ( $cipher === false ) {
			return '';
		}
		return 'enc:' . base64_encode( $iv . $cipher );
	}

	public static function decrypt( $payload ) {
		if ( empty( $payload ) ) {
			return '';
		}
		if ( strpos( $payload, 'plain:' ) === 0 ) {
			return base64_decode( substr( $payload, 6 ) );
		}
		if ( strpos( $payload, 'enc:' ) !== 0 ) {
			return '';
		}
		$raw    = base64_decode( substr( $payload, 4 ) );
		$ivlen  = openssl_cipher_iv_length( self::CIPHER );
		$iv     = substr( $raw, 0, $ivlen );
		$cipher = substr( $raw, $ivlen );
		$plain  = openssl_decrypt( $cipher, self::CIPHER, self::key(), OPENSSL_RAW_DATA, $iv );
		return $plain === false ? '' : $plain;
	}
}

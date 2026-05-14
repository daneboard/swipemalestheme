<?php
/**
 * Override the default WordPress /embed/ template with our static card.
 *
 * @package TikSwipe_Embed
 */

defined( 'ABSPATH' ) || exit;

class TSE_Template {

	public static function init() {
		add_filter( 'embed_template', array( __CLASS__, 'override_template' ), 99 );
		add_action( 'embed_head', array( __CLASS__, 'cleanup_default_head' ), 0 );
		add_action( 'embed_footer', array( __CLASS__, 'cleanup_default_footer' ), 0 );
		add_action( 'send_headers', array( __CLASS__, 'send_embed_headers' ) );
	}

	/**
	 * Swap WP's default embed template for ours when post is video/image.
	 *
	 * @param string $template Default template path.
	 * @return string
	 */
	public static function override_template( $template ) {
		if ( ! is_singular() ) {
			return $template;
		}
		$post = get_post();
		if ( ! tse_post_supports_embed( $post ) ) {
			return $template;
		}
		$custom = TSE_PLUGIN_DIR . 'templates/iframe.php';
		if ( file_exists( $custom ) ) {
			return $custom;
		}
		return $template;
	}

	/**
	 * Strip default WP embed head output. Our template emits its own minimal
	 * head; we don't need wp_print_styles, oembed discovery, emoji scripts,
	 * etc. since the iframe is meant to be lightweight and self-contained.
	 */
	public static function cleanup_default_head() {
		if ( ! is_embed() || ! tse_post_supports_embed( get_post() ) ) {
			return;
		}
		remove_action( 'embed_head', 'print_emoji_detection_script' );
		remove_action( 'embed_head', 'print_embed_styles' );
		remove_action( 'embed_head', 'wp_print_head_scripts', 20 );
		remove_action( 'embed_head', 'wp_print_styles', 20 );
		remove_action( 'embed_head', 'wp_no_robots' );
		remove_action( 'embed_head', 'rel_canonical' );
		remove_action( 'embed_head', 'locale_stylesheet' );
		remove_action( 'embed_head', 'print_embed_scripts' );
		remove_action( 'embed_head', 'wp_robots', 1 );
		remove_action( 'embed_head', 'wp_generator' );
		remove_action( 'embed_head', 'wp_oembed_add_discovery_links' );
		remove_action( 'embed_head', 'wp_resource_hints', 2 );
	}

	/**
	 * Strip default footer assets so we don't ship wp-embed.js etc.
	 */
	public static function cleanup_default_footer() {
		if ( ! is_embed() || ! tse_post_supports_embed( get_post() ) ) {
			return;
		}
		remove_action( 'embed_footer', 'print_embed_scripts' );
		remove_action( 'embed_footer', 'wp_print_footer_scripts', 20 );
	}

	/**
	 * Allow third-party iframe embedding by removing X-Frame-Options when the
	 * request is hitting our embed template.
	 */
	public static function send_embed_headers() {
		if ( ! is_embed() ) {
			return;
		}
		$post = get_post();
		if ( ! tse_post_supports_embed( $post ) ) {
			return;
		}
		// Short cache while we iterate; long cache will come back later.
		header( 'Cache-Control: public, max-age=60' );
		header_remove( 'X-Frame-Options' );
		// Modern equivalent — empty CSP frame-ancestors directive allows all.
		header( 'Content-Security-Policy: frame-ancestors *' );
	}
}

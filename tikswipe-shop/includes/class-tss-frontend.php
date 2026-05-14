<?php
/**
 * Frontend asset enqueue. The shop block markup is injected by JS into each
 * matching .swiper-slide; CSS handles the TikTok-Shop styling and the
 * info-hide / badge-replace transitions.
 *
 * @package TikSwipe_Shop
 */

defined( 'ABSPATH' ) || exit;

class TSS_Frontend {

	public static function init() {
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
	}

	public static function enqueue() {
		// Only load on the swiper (homepage feed) and single posts where slides exist.
		if ( is_admin() ) {
			return;
		}
		if ( ! ( is_home() || is_front_page() || is_single() || is_category() || is_tag() || is_author() || is_search() ) ) {
			return;
		}

		wp_enqueue_style( 'dashicons' );
		wp_enqueue_style(
			'tikswipe-shop',
			TSS_PLUGIN_URL . 'assets/css/tikswipe-shop.css',
			array( 'dashicons' ),
			TSS_VERSION
		);
		wp_enqueue_script(
			'tikswipe-shop',
			TSS_PLUGIN_URL . 'assets/js/tikswipe-shop.js',
			array( 'jquery' ),
			TSS_VERSION,
			true
		);
		wp_localize_script(
			'tikswipe-shop',
			'tssData',
			array(
				'restUrl' => esc_url_raw( rest_url( 'tikswipe-shop/v1/lookup' ) ),
				'showDelayMs'  => 10000,
				'closeDelayMs' => 5000,
				'i18n'    => array(
					'close' => __( 'Close', 'tikswipe-shop' ),
					'buy'   => __( 'Buy', 'tikswipe-shop' ),
				),
			)
		);
	}
}

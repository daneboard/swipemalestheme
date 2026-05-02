<?php
/**
 * [tikswipe_embed id="123" width="360" height="640"] shortcode for inline use
 * inside this WordPress install.
 *
 * @package TikSwipe_Embed
 */

defined( 'ABSPATH' ) || exit;

class TSE_Shortcode {

	public static function init() {
		add_shortcode( 'tikswipe_embed', array( __CLASS__, 'render' ) );
	}

	public static function render( $atts ) {
		$atts = shortcode_atts(
			array(
				'id'     => 0,
				'width'  => TSE_Oembed::DEFAULT_WIDTH,
				'height' => TSE_Oembed::DEFAULT_HEIGHT,
			),
			$atts,
			'tikswipe_embed'
		);

		$post = get_post( absint( $atts['id'] ) );
		if ( ! $post || 'publish' !== $post->post_status ) {
			return '';
		}
		if ( ! tse_post_supports_embed( $post ) ) {
			return '';
		}

		return TSE_Oembed::iframe_html(
			tse_get_embed_url( $post ),
			absint( $atts['width'] ),
			absint( $atts['height'] ),
			get_the_title( $post )
		);
	}
}

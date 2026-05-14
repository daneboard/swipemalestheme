<?php
/**
 * Customise the WordPress oEmbed JSON response so external sites that
 * auto-discover oEmbed (Discord, Notion, Slack, WordPress, ...) render our
 * iframe card instead of the default plain WP embed.
 *
 * @package TikSwipe_Embed
 */

defined( 'ABSPATH' ) || exit;

class TSE_Oembed {

	const DEFAULT_WIDTH  = 360;
	const DEFAULT_HEIGHT = 640;

	public static function init() {
		add_filter( 'oembed_response_data', array( __CLASS__, 'response_data' ), 10, 4 );
	}

	/**
	 * Replace the default oEmbed payload with a rich iframe pointing to our
	 * /embed/ template.
	 *
	 * @param array   $data    Default oEmbed data.
	 * @param WP_Post $post    Queried post.
	 * @param int     $width   Requested width.
	 * @param int     $height  Requested height.
	 * @return array
	 */
	public static function response_data( $data, $post, $width, $height ) {
		if ( ! $post || ! tse_post_supports_embed( $post ) ) {
			return $data;
		}

		$w = absint( apply_filters( 'tse_embed_width', self::DEFAULT_WIDTH, $post ) );
		$h = absint( apply_filters( 'tse_embed_height', self::DEFAULT_HEIGHT, $post ) );

		$embed_url = tse_get_embed_url( $post );

		$data['type']          = 'rich';
		$data['width']         = $w;
		$data['height']        = $h;
		$data['html']          = self::iframe_html( $embed_url, $w, $h, get_the_title( $post ) );
		$data['provider_name'] = get_bloginfo( 'name' );
		$data['provider_url']  = home_url( '/' );

		if ( has_post_thumbnail( $post ) ) {
			$thumb = wp_get_attachment_image_src( get_post_thumbnail_id( $post ), 'ms-large' );
			if ( $thumb ) {
				$data['thumbnail_url']    = $thumb[0];
				$data['thumbnail_width']  = $thumb[1];
				$data['thumbnail_height'] = $thumb[2];
			}
		}

		return $data;
	}

	/**
	 * Build the iframe HTML string.
	 *
	 * @param string $url   Iframe src.
	 * @param int    $w     Width.
	 * @param int    $h     Height.
	 * @param string $title Title attribute.
	 * @return string
	 */
	public static function iframe_html( $url, $w, $h, $title = '' ) {
		return sprintf(
			'<iframe src="%s" width="%d" height="%d" frameborder="0" scrolling="no" allowtransparency="true" allowfullscreen="true" loading="lazy" referrerpolicy="no-referrer-when-downgrade" title="%s"></iframe>',
			esc_url( $url ),
			(int) $w,
			(int) $h,
			esc_attr( $title )
		);
	}
}

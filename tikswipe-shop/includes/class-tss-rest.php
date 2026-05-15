<?php
/**
 * REST endpoint: given a post ID, return the matching shop item payload
 * (or null).
 *
 * @package TikSwipe_Shop
 */

defined( 'ABSPATH' ) || exit;

class TSS_Rest {

	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
	}

	public static function register_routes() {
		register_rest_route(
			'tikswipe-shop/v1',
			'/lookup',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'permission_callback' => '__return_true',
				'args'                => array(
					'post_id' => array(
						'required' => true,
						'type'     => 'integer',
					),
				),
				'callback'            => array( __CLASS__, 'lookup' ),
			)
		);
	}

	public static function lookup( $request ) {
		$post_id = (int) $request->get_param( 'post_id' );
		$item_id = tss_find_item_for_post( $post_id );
		if ( ! $item_id ) {
			return new WP_REST_Response( array( 'item' => null ), 200 );
		}
		return new WP_REST_Response( array( 'item' => tss_get_item_payload( $item_id ) ), 200 );
	}
}

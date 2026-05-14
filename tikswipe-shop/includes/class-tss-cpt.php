<?php
/**
 * Custom post type "Shop Item".
 *
 * @package TikSwipe_Shop
 */

defined( 'ABSPATH' ) || exit;

class TSS_CPT {

	public static function init() {
		add_action( 'init', array( __CLASS__, 'register_cpt' ) );
	}

	public static function register_cpt() {
		register_post_type(
			TSS_CPT,
			array(
				'labels'              => array(
					'name'               => __( 'Shop Items', 'tikswipe-shop' ),
					'singular_name'      => __( 'Shop Item', 'tikswipe-shop' ),
					'menu_name'          => __( 'TikSwipe Shop', 'tikswipe-shop' ),
					'add_new'            => __( 'Add New', 'tikswipe-shop' ),
					'add_new_item'       => __( 'Add New Shop Item', 'tikswipe-shop' ),
					'edit_item'          => __( 'Edit Shop Item', 'tikswipe-shop' ),
					'new_item'           => __( 'New Shop Item', 'tikswipe-shop' ),
					'view_item'          => __( 'View Shop Item', 'tikswipe-shop' ),
					'search_items'       => __( 'Search Shop Items', 'tikswipe-shop' ),
					'not_found'          => __( 'No shop items found.', 'tikswipe-shop' ),
					'not_found_in_trash' => __( 'No shop items found in Trash.', 'tikswipe-shop' ),
				),
				'public'              => false,
				'show_ui'             => true,
				'show_in_menu'        => true,
				'show_in_admin_bar'   => false,
				'menu_position'       => 26,
				'menu_icon'           => 'dashicons-cart',
				'capability_type'     => 'post',
				'hierarchical'        => false,
				'supports'            => array( 'title' ),
				'has_archive'         => false,
				'rewrite'             => false,
				'exclude_from_search' => true,
			)
		);
	}
}

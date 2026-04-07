<?php
// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_filter( 'WPSCORE-tabs', 'wpst_add_admin_navigation' );

function wpst_add_admin_navigation( $nav ) {
	eval( WPSCORE()->eval_product_data( WPSCORE()->get_installed_theme( 'sku' ), 'add_tab' ) );
	if ( isset( $wpst_nav ) && is_array( $wpst_nav ) ) {
		$nav[1] = $wpst_nav;
	}
	return $nav;
}

add_filter( 'wpst-options', 'wpst_options_page' );

function wpst_options_page( $options_table ) {

	$output = '<style>
		.xbox-header-actions,
		#wp-script .xbox-separator { display: none!important; }
		#wp-script .xbox.xbox-boxed .xbox-row:not(.xbox-row-mixed):first-child { margin-top: 0; }
		#wp-script .xbox .xbox-type-title .xbox-content { padding: 0 0 30px; }
		#wp-script .xbox-row .xbox-content { border-left: none; }
		#wp-script .xbox-row .xbox-field-description,
		#wp-script .xbox-row .xbox-field-description a { font-size: 16px; font-style: normal; }
	</style>';

	$output .= '<div id="wp-script">
					<div class="content-tabs">';

	$output .= WPSCORE()->display_logo( false );
	$output .= WPSCORE()->display_tabs( false );

	$output .= '<div class="tab-content tab-options">';

	$output .= $options_table;

	$output .= '</div>';
	$output .= '</div>';

	$output .= '</div>';
	$output .= '</div>';

	$output .= WPSCORE()->display_footer( false );
	$output .= '</div>';

	return $output;
}

add_action( 'xbox_init', 'wpst_options' );
function wpst_options() {
	$options = array(
		'id'         => 'wpst-options',
		'title'      => esc_html__( 'Theme Options', 'wpst' ),
		'menu_title' => esc_html__( 'Theme Options', 'wpst' ),
		'skin'       => 'pink',
		'layout'     => 'boxed',
		'header'     => array(
			'name' => '<img src="https://www.wp-script.com/wp-content/uploads/wps-img/products/themes/icons/' . strtolower( wp_get_theme()->get( 'Name' ) ) . '-icon.svg" width="20"> ' . wp_get_theme()->get( 'Name' ),
		),
		'capability' => 'edit_published_posts',
	);

	$xbox = xbox_new_admin_page( $options );

	$xbox->add_main_tab(
		array(
			'name'  => esc_html__( 'Main tab', 'wpst' ),
			'id'    => 'main-tab',
			'items' => array(
				'general' => '',
			),
		)
	);
		/**
		 * GENERAL
		 */
		$xbox->open_tab_item( 'general' );
			$xbox->add_field(
				array(
					'id'   => 'customizer',
					'name' => '',
					'type' => 'title',
					'desc' => sprintf( __( '<a href="%s">Click here</a> to go to the Customizer.', 'wpst' ), get_admin_url() . 'customize.php' ),
				)
			);
		$xbox->close_tab_item( 'general' );

	$xbox->close_tab( 'main-tab' );
}

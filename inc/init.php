<?php

if ( ! is_user_logged_in() ) {
	return;
}

if ( ! function_exists( 'is_plugin_active' ) ) {
	require_once ABSPATH . 'wp-admin/includes/plugin.php';
}

if ( ! is_plugin_active( 'wp-script-core/wp-script-core.php' ) || ! function_exists( 'WPSCORE' ) ) {
	$message = implode(
		'<br/><br/>',
		array(
			__( 'This Theme needs WP-Script Core to be installed and activated.', 'wpst' ),
			'<a href="https://docs.wp-script.com/getting-started/download-wp-script-core" rel="nofollow noreferrer" target="_blank">' . __( 'Read the guide to install and activate WP-Script Core', 'wpst' ) . '</a>',
		)
	);
	wpst_display_error_page( $message );
}

if ( WPSCORE()->get_product_status( 'TST' ) !== 'connected' ) {
	$message = implode(
		'<br/><br/>',
		array(
			__( 'Please connect the theme in the WP-Script Dashboard on your site.', 'wpst' ),
			'<a href="https://docs.wp-script.com/themes/guides/themes-activation" rel="nofollow noreferrer" target="_blank">' . __( 'Read the guide to connect your theme', 'wpst' ) . '</a>',
		)
	);

	wpst_display_error_page( $message );
}

/**
 * Display an error page.
 *
 * @param string $message The message to display on the error page.
 *
 * @return void.
 */
function wpst_display_error_page( $message ) {
	?>
	<p><?php echo wp_kses_post( $message ); ?></p>
	<style type="text/css">
		body{
			background-color: #222;
			text-align: center;
			color:#eee;
			padding-top:150px;
			font-family: 'arial';
			font-size:16px;
		}
		p{
			text-align: center;
		}
		a{
			color:#f0476d;
		}
	</style>
	<?php
	die();
}

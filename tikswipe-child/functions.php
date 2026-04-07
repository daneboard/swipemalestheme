<?php
// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * Enqueue parent theme styles + child theme custom CSS.
 */
function tikswipe_child_enqueue_styles() {
	// Parent main CSS.
	wp_enqueue_style(
		'wpst-main-css',
		get_template_directory_uri() . '/css/main.css',
		array(),
		wp_get_theme()->parent()->get( 'Version' )
	);

	// Child custom CSS (only the new rules).
	wp_enqueue_style(
		'tikswipe-child-css',
		get_stylesheet_directory_uri() . '/css/child-custom.css',
		array( 'wpst-main-css' ),
		wp_get_theme()->get( 'Version' ) . '.' . filemtime( get_stylesheet_directory() . '/css/child-custom.css' )
	);
}
add_action( 'wp_enqueue_scripts', 'tikswipe_child_enqueue_styles', 20 );

/**
 * Replace parent JS files with child theme versions (player-init.js and loadmore.js).
 */
function tikswipe_child_enqueue_scripts() {
	$js_version = wp_get_theme()->get( 'Version' );

	// Replace player-init.js.
	if ( wp_script_is( 'player-init-js', 'enqueued' ) || wp_script_is( 'player-init-js', 'registered' ) ) {
		wp_dequeue_script( 'player-init-js' );
		wp_deregister_script( 'player-init-js' );

		wp_enqueue_script(
			'player-init-js',
			get_stylesheet_directory_uri() . '/js/player-init.js',
			array( 'videojs-js', 'swiper-js' ),
			$js_version . '.' . filemtime( get_stylesheet_directory() . '/js/player-init.js' ),
			true
		);

		// Re-localize since we deregistered.
		wp_localize_script(
			'player-init-js',
			'wpst_player_init_var',
			array(
				'url'                    => admin_url( 'admin-ajax.php' ),
				'nonce'                  => wp_create_nonce( 'ajax-nonce' ),
				'autoplay_videos'        => get_theme_mod( 'wpst_autoplay_videos', true ),
				'mute_videos_by_default' => get_theme_mod( 'wpst_mute_videos_by_default', true ),
			)
		);
	}

	// Replace loadmore.js.
	if ( wp_script_is( 'loadmore-js', 'enqueued' ) || wp_script_is( 'loadmore-js', 'registered' ) ) {
		$loadmore_data = null;

		// Capture existing localization data before deregistering.
		global $wp_scripts;
		if ( isset( $wp_scripts->registered['loadmore-js'] ) ) {
			$loadmore_data = $wp_scripts->registered['loadmore-js']->extra;
		}

		wp_dequeue_script( 'loadmore-js' );
		wp_deregister_script( 'loadmore-js' );

		wp_enqueue_script(
			'loadmore-js',
			get_stylesheet_directory_uri() . '/js/loadmore.js',
			array( 'jquery' ),
			$js_version . '.' . filemtime( get_stylesheet_directory() . '/js/loadmore.js' ),
			true
		);

		// Re-apply localization data from parent.
		if ( $loadmore_data && isset( $loadmore_data['data'] ) ) {
			wp_add_inline_script( 'loadmore-js', $loadmore_data['data'], 'before' );
		}
	}
}
add_action( 'wp_enqueue_scripts', 'tikswipe_child_enqueue_scripts', 21 );

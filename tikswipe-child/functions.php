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

	// Inline JS for tags expand toggle.
	wp_add_inline_script( 'wpst-main-js', "
		jQuery(document).on('click', '.wpst-tags-more', function(e) {
			e.preventDefault();
			var tagsList = jQuery(this).closest('.tags-list');
			tagsList.find('.wpst-tag-hidden').addClass('wpst-tags-expanded').show();
			jQuery(this).remove();
		});
	" );
}
add_action( 'wp_enqueue_scripts', 'tikswipe_child_enqueue_scripts', 21 );

/**
 * Override loadmore query on single posts to use category-based related videos.
 * Runs after parent enqueue (priority 22) to re-localize the loadmore_ajax_var.
 */
function tikswipe_child_single_loadmore_query() {
	if ( ! is_single() ) {
		return;
	}

	global $post;
	$ads_displaying_frequency = get_theme_mod( 'wpst_ads_displaying_frequency', 5 );
	$random_posts             = get_theme_mod( 'wpst_random_posts', false );
	$post_cats                = wp_get_post_categories( $post->ID, array( 'fields' => 'ids' ) );

	$loadmore_single_args = array(
		'post_type'      => 'post',
		'post_status'    => 'publish',
		'posts_per_page' => $ads_displaying_frequency,
		'orderby'        => $random_posts ? 'RAND(' . get_random_seed() . ')' : 'ID',
		'order'          => 'DESC',
		'post__not_in'   => array( $post->ID ),
		'tax_query'      => array(
			'relation' => 'AND',
			array(
				'taxonomy' => 'post_format',
				'field'    => 'slug',
				'terms'    => array(
					'post-format-video',
					'post-format-image',
				),
				'operator' => 'IN',
			),
		),
		'paged'          => ( get_query_var( 'paged' ) ) ? get_query_var( 'paged' ) : 1,
	);

	if ( ! empty( $post_cats ) ) {
		$loadmore_single_args['category__in'] = $post_cats;
	}

	$wp_query = new WP_Query( $loadmore_single_args );

	// Override the localized data for loadmore.
	wp_localize_script(
		'loadmore-js',
		'loadmore_ajax_var',
		array(
			'ajaxurl'      => admin_url( 'admin-ajax.php' ),
			'nonce'        => wp_create_nonce( 'ajax-nonce' ),
			'posts'        => wp_json_encode( $wp_query->query_vars ),
			'current_page' => get_query_var( 'paged' ) ? get_query_var( 'paged' ) : 1,
			'max_page'     => $wp_query->max_num_pages,
		)
	);

	wp_reset_postdata();
}
add_action( 'wp_enqueue_scripts', 'tikswipe_child_single_loadmore_query', 22 );

/**
 * Fix randomization: regenerate seed on every new page load.
 * The parent theme stores a seed in $_SESSION that never changes.
 * We force a new seed each time the user arrives at any swipe view.
 */
function tikswipe_child_reset_random_seed() {
	if ( ! is_home() && ! is_front_page() && ! is_category() && ! is_tag() ) {
		return;
	}

	// Only reset if we're not loading more via AJAX (paged > 1 should keep the same seed).
	$paged = get_query_var( 'paged', 0 );
	if ( $paged > 1 ) {
		return;
	}

	if ( ! isset( $_SESSION ) ) {
		session_start();
	}

	// Always generate a fresh seed on page arrival.
	$_SESSION['random_seed'] = wp_rand( 1, 999999 );
}
add_action( 'template_redirect', 'tikswipe_child_reset_random_seed', 1 );

/**
 * Extend randomization to category and tag archives.
 * The parent only randomizes home/front_page/template-vids/template-pics.
 */
function tikswipe_child_randomize_archives( $query ) {
	if ( is_admin() ) {
		return;
	}

	$random_posts = get_theme_mod( 'wpst_random_posts', false );
	if ( ! $random_posts ) {
		return;
	}

	if ( ( is_category() || is_tag() ) && $query->is_main_query() ) {
		$query->set( 'orderby', 'RAND(' . get_random_seed() . ')' );
		$query->set( 'order', 'DESC' );
	}
}
add_action( 'pre_get_posts', 'tikswipe_child_randomize_archives', 2 );

<?php
// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * Enqueue parent theme styles + child theme custom CSS.
 */
function tikswipe_child_enqueue_styles() {
	wp_enqueue_style(
		'wpst-main-css',
		get_template_directory_uri() . '/css/main.css',
		array(),
		wp_get_theme()->parent()->get( 'Version' )
	);

	wp_enqueue_style(
		'tikswipe-child-css',
		get_stylesheet_directory_uri() . '/css/child-custom.css',
		array( 'wpst-main-css' ),
		wp_get_theme()->get( 'Version' ) . '.' . filemtime( get_stylesheet_directory() . '/css/child-custom.css' )
	);

	// Dequeue parent Poppins font.
	wp_dequeue_style( 'wpst-font' );
}
add_action( 'wp_enqueue_scripts', 'tikswipe_child_enqueue_styles', 20 );

/**
 * Replace parent JS files with child theme versions.
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

		if ( $loadmore_data && isset( $loadmore_data['data'] ) ) {
			wp_add_inline_script( 'loadmore-js', $loadmore_data['data'], 'before' );
		}
	}

	// Inline JS: tags expand + search clear + footer scroll hide/show.
	wp_add_inline_script( 'wpst-main-js', "
		jQuery(document).on('click', '.wpst-tags-more', function(e) {
			e.preventDefault();
			var tagsList = jQuery(this).closest('.tags-list');
			tagsList.find('.wpst-tag-hidden').addClass('wpst-tags-expanded').show();
			jQuery(this).remove();
		});
		jQuery(document).on('input', '#searchform #s', function() {
			var clearBtn = jQuery(this).closest('#searchform').find('.wpst-search-clear');
			clearBtn.toggle(jQuery(this).val().length > 0);
		});
		jQuery(document).on('click', '.wpst-search-clear', function(e) {
			e.preventDefault();
			var form = jQuery(this).closest('#searchform');
			form.find('#s').val('').focus();
			jQuery(this).hide();
		});
		jQuery(function() {
			var si = jQuery('#searchform #s');
			if (si.length && si.val().length > 0) si.closest('#searchform').find('.wpst-search-clear').show();
		});

		// Footer: hide on scroll down, show on scroll up (grid pages only)
		(function() {
			var lastY = 0;
			var footer = document.querySelector('footer');
			if (!footer) return;
			var isGridPage = document.body.classList.contains('page-template-template-search-php')
				|| document.body.classList.contains('page-template-template-favorites-php')
				|| document.body.classList.contains('search');
			if (!isGridPage) return;
			footer.style.transition = 'bottom 0.25s ease';
			window.addEventListener('scroll', function() {
				var y = window.pageYOffset;
				if (y > lastY && y > 60) {
					footer.style.bottom = '-60px';
				} else {
					footer.style.bottom = '0';
				}
				lastY = y;
			}, { passive: true });
		})();

		// PWA Add to Home Screen
		(function() {
			var btn = document.getElementById('wpst-pwa-install');
			if (!btn) return;
			var deferredPrompt = null;

			// Android: beforeinstallprompt
			window.addEventListener('beforeinstallprompt', function(e) {
				e.preventDefault();
				deferredPrompt = e;
				btn.style.display = 'inline-block';
			});

			// iOS: detect Safari standalone capability
			var isIos = /iphone|ipad|ipod/i.test(navigator.userAgent);
			var isInStandalone = window.navigator.standalone === true;
			if (isIos && !isInStandalone) {
				btn.style.display = 'inline-block';
			}

			btn.addEventListener('click', function() {
				if (deferredPrompt) {
					deferredPrompt.prompt();
					deferredPrompt.userChoice.then(function() {
						deferredPrompt = null;
						btn.style.display = 'none';
					});
				} else if (isIos) {
					alert('Tap the Share button then \"Add to Home Screen\"');
				}
			});

			window.addEventListener('appinstalled', function() {
				btn.style.display = 'none';
			});
		})();
	" );
}
add_action( 'wp_enqueue_scripts', 'tikswipe_child_enqueue_scripts', 21 );

/**
 * Override loadmore query to use RAND for home AND category-based for single.
 */
function tikswipe_child_override_loadmore() {
	$ads_displaying_frequency = get_theme_mod( 'wpst_ads_displaying_frequency', 5 );

	if ( is_single() ) {
		global $post;
		$post_cats = wp_get_post_categories( $post->ID, array( 'fields' => 'ids' ) );
		$post_tags = wp_get_post_tags( $post->ID, array( 'fields' => 'ids' ) );

		$args = array(
			'post_type'      => 'post',
			'post_status'    => 'publish',
			'posts_per_page' => $ads_displaying_frequency,
			'orderby'        => 'RAND(' . get_random_seed() . ')',
			'order'          => 'DESC',
			'post__not_in'   => array( $post->ID ),
			'tax_query'      => array(
				array(
					'taxonomy' => 'post_format',
					'field'    => 'slug',
					'terms'    => array( 'post-format-video', 'post-format-image' ),
					'operator' => 'IN',
				),
			),
			'paged'          => ( get_query_var( 'paged' ) ) ? get_query_var( 'paged' ) : 1,
		);

		if ( ! empty( $post_cats ) ) {
			$args['category__in'] = $post_cats;
		}
		if ( ! empty( $post_tags ) ) {
			$args['tag__in'] = $post_tags;
		}

		$wp_query = new WP_Query( $args );

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

	} elseif ( is_home() || is_front_page() ) {
		// Override the parent's loadmore query to use RAND.
		$args = array(
			'post_type'      => 'post',
			'post_status'    => 'publish',
			'posts_per_page' => $ads_displaying_frequency,
			'orderby'        => 'RAND(' . get_random_seed() . ')',
			'order'          => 'DESC',
			'tax_query'      => array(
				array(
					'taxonomy' => 'post_format',
					'field'    => 'slug',
					'terms'    => array( 'post-format-image', 'post-format-video' ),
					'operator' => 'IN',
				),
			),
			'paged'          => ( get_query_var( 'paged' ) ) ? get_query_var( 'paged' ) : 1,
		);

		$wp_query = new WP_Query( $args );

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
}
add_action( 'wp_enqueue_scripts', 'tikswipe_child_override_loadmore', 22 );

/**
 * Force fresh random seed on every page load.
 */
function tikswipe_child_reset_random_seed() {
	if ( ! is_home() && ! is_front_page() && ! is_category() && ! is_tag() && ! is_single() ) {
		return;
	}

	$paged = get_query_var( 'paged', 0 );
	if ( $paged > 1 ) {
		return;
	}

	if ( ! isset( $_SESSION ) ) {
		session_start();
	}

	$_SESSION['random_seed'] = wp_rand( 1, 999999 );
}
add_action( 'template_redirect', 'tikswipe_child_reset_random_seed', 1 );

/**
 * Force random ordering on category/tag archives (main query).
 */
function tikswipe_child_force_random_order( $query ) {
	if ( is_admin() || ! $query->is_main_query() ) {
		return;
	}

	if ( is_category() || is_tag() ) {
		$query->set( 'orderby', 'RAND(' . get_random_seed() . ')' );
		$query->set( 'order', 'DESC' );
	}
}
add_action( 'pre_get_posts', 'tikswipe_child_force_random_order', 99 );

/**
 * Override searchform to add clear (X) button.
 */
function tikswipe_child_searchform( $form ) {
	$form = '<form method="get" id="searchform" action="' . esc_url( home_url( '/' ) ) . '" role="search">
		<div class="d-flex" style="position:relative;">
			<input class="field form-control" id="s" name="s" placeholder="' . esc_attr__( 'Search...', 'wpst' ) . '" type="text" value="' . get_search_query() . '">
			<button type="button" class="wpst-search-clear" aria-label="Clear">&times;</button>
			<span class="input-group-append">
				<button type="submit" id="searchsubmit"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24" style="position: relative; top: 2px;"><path fill="currentColor" clip-rule="evenodd" fill-rule="evenodd" d="m9.952 1c-4.944 0-8.952 4.009-8.952 8.954 0 4.945 4.008 8.954 8.952 8.954 2.012 0 3.869-0.664 5.364-1.785l5.65 5.651c0.144 0.144 0.3391 0.2248 0.5426 0.2248 0.2036 0 0.3987-0.08082 0.5426-0.2248l0.7234-0.7236c0.2997-0.2997 0.2997-0.7857 0-1.085l-5.651-5.652c1.118-1.494 1.78-3.35 1.78-5.36 0-4.945-4.008-8.954-8.952-8.954zm5.606 13.81c1.128-1.302 1.811-3 1.811-4.858 0-4.098-3.321-7.419-7.417-7.419-4.097 0-7.418 3.322-7.418 7.419 0 4.098 3.321 7.419 7.418 7.419 1.858 0 3.557-0.6835 4.858-1.813 0.0046-0.0049 0.0093-0.0097 0.01397-0.01443l0.7234-0.7236c0.0035-0.0035 0.0069-0.0068 0.01044-0.01021z" style="stroke-width: 0.7674;"></path></svg></button>
			</span>
		</div>
	</form>';
	return $form;
}
add_filter( 'get_search_form', 'tikswipe_child_searchform' );

/**
 * Override the parent's loadmore_swipe AJAX handler.
 * The parent's handler ignores category__in and resets orderby based on customizer.
 * Our handler preserves the full query_vars we passed (including category__in and RAND).
 */
function tikswipe_child_ajax_load_more_swipe() {
	if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'ajax-nonce' ) ) {
		exit;
	}

	$ads_displaying_frequency = get_theme_mod( 'wpst_ads_displaying_frequency', 5 );
	$args                     = json_decode( stripslashes( $_POST['query'] ), true );
	$args['paged']            = intval( $_POST['page'] ) + 1;
	$args['max_page']         = $_POST['maxpage'];
	$args['post_type']        = 'post';
	$args['post_status']      = 'publish';
	$args['posts_per_page']   = $ads_displaying_frequency;
	$args['orderby']          = 'RAND(' . get_random_seed() . ')';
	$args['order']            = 'DESC';

	$query = new WP_Query( $args );

	// If category filter returns no results, retry without it.
	if ( ! $query->have_posts() && ! empty( $args['category__in'] ) ) {
		unset( $args['category__in'] );
		unset( $args['tag__in'] );
		$query = new WP_Query( $args );
	}

	if ( $query->have_posts() ) :
		while ( $query->have_posts() ) :
			$query->the_post();
			eval( WPSCORE()->eval_product_data( WPSCORE()->get_installed_theme( 'sku' ), 'ajax_load_more_swipe_eval_1' ) );
		endwhile;
		eval( WPSCORE()->eval_product_data( WPSCORE()->get_installed_theme( 'sku' ), 'ajax_load_more_swipe_eval_2' ) );
	endif;

	wp_reset_postdata();
	die;
}

// Remove parent handler and register ours (must run after parent registers theirs).
function tikswipe_child_replace_loadmore_swipe_handler() {
	remove_action( 'wp_ajax_loadmore_swipe', 'wpst_ajax_load_more_swipe' );
	remove_action( 'wp_ajax_nopriv_loadmore_swipe', 'wpst_ajax_load_more_swipe' );
	add_action( 'wp_ajax_loadmore_swipe', 'tikswipe_child_ajax_load_more_swipe' );
	add_action( 'wp_ajax_nopriv_loadmore_swipe', 'tikswipe_child_ajax_load_more_swipe' );
}
add_action( 'init', 'tikswipe_child_replace_loadmore_swipe_handler', 20 );

/**
 * Add lazy loading to grid thumbnails — only on search/favorites pages.
 */
function tikswipe_child_lazy_load_thumbs( $attr, $attachment, $size ) {
	if ( is_search() || is_page_template( 'template-search.php' ) || is_page_template( 'template-favorites.php' ) ) {
		$attr['loading'] = 'lazy';
	}
	return $attr;
}
add_filter( 'wp_get_attachment_image_attributes', 'tikswipe_child_lazy_load_thumbs', 10, 3 );

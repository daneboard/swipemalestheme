<?php
// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

// SEO improvements (meta tags, schema, Open Graph, sitemap, etc.).
require_once get_stylesheet_directory() . '/inc/seo.php';

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

		// VAST ad handler.
		$vast_enabled = get_theme_mod( 'wpst_vast_enabled', false );
		if ( $vast_enabled ) {
			wp_enqueue_script(
				'tikswipe-vast-js',
				get_stylesheet_directory_uri() . '/js/vast-handler.js',
				array(),
				$js_version . '.' . filemtime( get_stylesheet_directory() . '/js/vast-handler.js' ),
				true
			);

			wp_add_inline_script( 'tikswipe-vast-js', '
				if (window.TikSwipeVAST) {
					TikSwipeVAST.config.enabled              = true;
					TikSwipeVAST.config.tagUrl                = ' . wp_json_encode( get_theme_mod( 'wpst_vast_tag_url', '' ) ) . ';
					TikSwipeVAST.config.proxyUrl              = ' . wp_json_encode( admin_url( 'admin-ajax.php?action=tikswipe_vast_proxy' ) ) . ';
					TikSwipeVAST.config.frequency             = ' . intval( get_theme_mod( 'wpst_vast_frequency', 3 ) ) . ';
					TikSwipeVAST.config.skipAfter             = ' . intval( get_theme_mod( 'wpst_vast_skip_after', 5 ) ) . ';
					TikSwipeVAST.config.midrollEnabled        = ' . ( get_theme_mod( 'wpst_vast_midroll_enabled', false ) ? 'true' : 'false' ) . ';
					TikSwipeVAST.config.midrollPercent        = ' . intval( get_theme_mod( 'wpst_vast_midroll_percent', 20 ) ) . ';
					TikSwipeVAST.config.midrollTagUrl         = ' . wp_json_encode( get_theme_mod( 'wpst_vast_midroll_tag_url', '' ) ) . ';
					TikSwipeVAST.config.interstitialEnabled   = ' . ( get_theme_mod( 'wpst_interstitial_enabled', false ) ? 'true' : 'false' ) . ';
					TikSwipeVAST.config.interstitialZoneId    = ' . wp_json_encode( get_theme_mod( 'wpst_interstitial_zone_id', '' ) ) . ';
					TikSwipeVAST.config.interstitialSrc       = "https://a.pemsrv.com/ad-provider.js";
				}
			', 'after' );
		}
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
 * Sticky top banner — Magsrv 300x50 zone 5920214. Skips admin and oEmbed
 * iframes so it never shows inside the TikSwipe Embed cards.
 */
function tikswipe_child_top_banner() {
	if ( is_admin() || is_embed() || is_customize_preview() ) {
		return;
	}
	?>
	<div id="tikswipe-top-banner" aria-hidden="true">
		<script async type="application/javascript" src="https://a.magsrv.com/ad-provider.js"></script>
		<ins class="eas6a97888e10" data-zoneid="5920214"></ins>
		<script>(AdProvider = window.AdProvider || []).push({"serve": {}});</script>
	</div>
	<style>
		:root { --tse-banner-h: 50px; }

		/* Static-flow header on pages where it is NOT absolutely positioned
		   (grid/profile/author/search) gets its room via .content padding. */
		.content { padding-top: var(--tse-banner-h); }

		/* On swiper pages, main is position:absolute with top:0 / height:100%
		   which ignores .content padding. Shift it down by the banner height
		   and shrink so the swiper fits the visible viewport exactly. */
		body.media-body main,
		body.grid main {
			top: var(--tse-banner-h);
			height: calc(100% - var(--tse-banner-h));
		}

		/* Profile/author/main has its own min-height:100vh — clamp it so
		   the page does not overflow past the footer. */
		body.author main,
		body.profile main {
			min-height: calc(100vh - var(--tse-banner-h));
		}

		#tikswipe-top-banner {
			position: fixed;
			top: 0;
			left: 50%;
			transform: translateX(-50%);
			width: 300px;
			height: var(--tse-banner-h);
			z-index: 99999;
			text-align: center;
			line-height: 0;
			pointer-events: auto;
			background: #000;
		}
		#tikswipe-top-banner ins {
			display: block;
			width: 300px;
			height: var(--tse-banner-h);
			margin: 0;
		}
	</style>
	<?php
}
add_action( 'wp_body_open', 'tikswipe_child_top_banner' );

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
 * Instead of replacing the parent's AJAX handler (which breaks eval hooks for ads),
 * we modify the query args via pre_get_posts during AJAX requests.
 * This preserves the parent's ad injection mechanism completely.
 */
function tikswipe_child_force_rand_on_ajax( $query ) {
	// Only run during AJAX loadmore requests.
	if ( ! defined( 'DOING_AJAX' ) || ! DOING_AJAX ) {
		return;
	}

	if ( ! isset( $_POST['action'] ) || $_POST['action'] !== 'loadmore_swipe' ) {
		return;
	}

	// Force random ordering regardless of customizer setting.
	$query->set( 'orderby', 'RAND(' . get_random_seed() . ')' );
	$query->set( 'order', 'DESC' );
}
add_action( 'pre_get_posts', 'tikswipe_child_force_rand_on_ajax', 999 );

/**
 * Random order on search page — initial load AND AJAX load-more (when no search query).
 *
 * Two cases:
 * 1. Initial page load: template-search.php sets $tikswipe_search_discovery = true.
 *    This flag reliably tells us to randomize ALL queries during that page render
 *    (including the parent's eval-invoked rendering function).
 * 2. AJAX load-more: $_POST['action'] identifies the request, empty 'query' = discovery.
 */
function tikswipe_child_force_rand_on_search( $query ) {
	if ( is_admin() ) {
		return;
	}

	// Case 1: AJAX load-more for search
	if ( defined( 'DOING_AJAX' ) && DOING_AJAX && isset( $_POST['action'] ) ) {
		$search_actions = array( 'load_more_search_vids', 'load_more_search_pics' );
		if ( in_array( $_POST['action'], $search_actions, true ) && empty( $_POST['query'] ) ) {
			$query->set( 'orderby', 'RAND(' . get_random_seed() . ')' );
			$query->set( 'order', 'DESC' );
		}
		return;
	}

	// Case 2: Initial page load — flag set by template-search.php / search.php
	global $tikswipe_search_discovery;
	if ( ! empty( $tikswipe_search_discovery ) ) {
		$query->set( 'orderby', 'RAND(' . get_random_seed() . ')' );
		$query->set( 'order', 'DESC' );
	}
}
add_action( 'pre_get_posts', 'tikswipe_child_force_rand_on_search', 999 );

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

/**
 * =========================================================================
 * VAST VIDEO ADS — Customizer settings + CORS proxy
 * =========================================================================
 */

/**
 * Register VAST customizer fields (uses Kirki from parent theme).
 */
function tikswipe_child_vast_customizer_fields() {
	if ( ! class_exists( 'Kirki' ) ) {
		return;
	}

	Kirki::add_field(
		'wpst_advertising_config',
		array(
			'type'     => 'toggle',
			'settings' => 'wpst_vast_enabled',
			'label'    => esc_html__( 'Enable VAST Video Ads', 'tikswipe-child' ),
			'section'  => 'wpst_advertising_section',
			'default'  => false,
			'priority' => 50,
		)
	);

	Kirki::add_field(
		'wpst_advertising_config',
		array(
			'type'            => 'text',
			'settings'        => 'wpst_vast_tag_url',
			'label'           => esc_html__( 'VAST Tag URL', 'tikswipe-child' ),
			'description'     => esc_html__( 'Paste the VAST tag URL from your ad network (e.g. Exoclick).', 'tikswipe-child' ),
			'section'         => 'wpst_advertising_section',
			'default'         => '',
			'priority'        => 51,
			'active_callback' => array(
				array(
					'setting'  => 'wpst_vast_enabled',
					'operator' => '===',
					'value'    => true,
				),
			),
		)
	);

	Kirki::add_field(
		'wpst_advertising_config',
		array(
			'type'            => 'slider',
			'settings'        => 'wpst_vast_frequency',
			'label'           => esc_html__( 'VAST ad frequency', 'tikswipe-child' ),
			'description'     => esc_html__( 'Show a VAST video ad every N videos.', 'tikswipe-child' ),
			'section'         => 'wpst_advertising_section',
			'default'         => 3,
			'choices'         => array(
				'min'  => 1,
				'max'  => 10,
				'step' => 1,
			),
			'priority'        => 52,
			'active_callback' => array(
				array(
					'setting'  => 'wpst_vast_enabled',
					'operator' => '===',
					'value'    => true,
				),
			),
		)
	);

	Kirki::add_field(
		'wpst_advertising_config',
		array(
			'type'            => 'slider',
			'settings'        => 'wpst_vast_skip_after',
			'label'           => esc_html__( 'Allow skip after (seconds)', 'tikswipe-child' ),
			'description'     => esc_html__( 'Set to 0 for instant skip. Set higher to force longer ad viewing.', 'tikswipe-child' ),
			'section'         => 'wpst_advertising_section',
			'default'         => 5,
			'choices'         => array(
				'min'  => 0,
				'max'  => 30,
				'step' => 1,
			),
			'priority'        => 53,
			'active_callback' => array(
				array(
					'setting'  => 'wpst_vast_enabled',
					'operator' => '===',
					'value'    => true,
				),
			),
		)
	);

	// --- Mid-roll settings ---
	Kirki::add_field(
		'wpst_advertising_config',
		array(
			'type'            => 'toggle',
			'settings'        => 'wpst_vast_midroll_enabled',
			'label'           => esc_html__( 'Enable Mid-roll VAST Ad', 'tikswipe-child' ),
			'description'     => esc_html__( 'Show a second VAST ad during content video playback.', 'tikswipe-child' ),
			'section'         => 'wpst_advertising_section',
			'default'         => false,
			'priority'        => 60,
			'active_callback' => array(
				array(
					'setting'  => 'wpst_vast_enabled',
					'operator' => '===',
					'value'    => true,
				),
			),
		)
	);

	Kirki::add_field(
		'wpst_advertising_config',
		array(
			'type'            => 'slider',
			'settings'        => 'wpst_vast_midroll_percent',
			'label'           => esc_html__( 'Mid-roll trigger (%)', 'tikswipe-child' ),
			'description'     => esc_html__( 'Show mid-roll when content video reaches this percentage.', 'tikswipe-child' ),
			'section'         => 'wpst_advertising_section',
			'default'         => 20,
			'choices'         => array(
				'min'  => 5,
				'max'  => 80,
				'step' => 5,
			),
			'priority'        => 61,
			'active_callback' => array(
				array(
					'setting'  => 'wpst_vast_midroll_enabled',
					'operator' => '===',
					'value'    => true,
				),
			),
		)
	);

	Kirki::add_field(
		'wpst_advertising_config',
		array(
			'type'            => 'text',
			'settings'        => 'wpst_vast_midroll_tag_url',
			'label'           => esc_html__( 'Mid-roll VAST Tag URL', 'tikswipe-child' ),
			'description'     => esc_html__( 'Leave empty to use the same tag URL as pre-roll.', 'tikswipe-child' ),
			'section'         => 'wpst_advertising_section',
			'default'         => '',
			'priority'        => 62,
			'active_callback' => array(
				array(
					'setting'  => 'wpst_vast_midroll_enabled',
					'operator' => '===',
					'value'    => true,
				),
			),
		)
	);

	// --- Interstitial fallback settings ---
	Kirki::add_field(
		'wpst_advertising_config',
		array(
			'type'            => 'toggle',
			'settings'        => 'wpst_interstitial_enabled',
			'label'           => esc_html__( 'Enable Interstitial Fallback', 'tikswipe-child' ),
			'description'     => esc_html__( 'Show an interstitial ad when VAST returns no fill.', 'tikswipe-child' ),
			'section'         => 'wpst_advertising_section',
			'default'         => false,
			'priority'        => 70,
			'active_callback' => array(
				array(
					'setting'  => 'wpst_vast_enabled',
					'operator' => '===',
					'value'    => true,
				),
			),
		)
	);

	Kirki::add_field(
		'wpst_advertising_config',
		array(
			'type'            => 'text',
			'settings'        => 'wpst_interstitial_zone_id',
			'label'           => esc_html__( 'Interstitial Zone ID', 'tikswipe-child' ),
			'description'     => esc_html__( 'ExoClick zone ID for interstitial ads.', 'tikswipe-child' ),
			'section'         => 'wpst_advertising_section',
			'default'         => '',
			'priority'        => 71,
			'active_callback' => array(
				array(
					'setting'  => 'wpst_interstitial_enabled',
					'operator' => '===',
					'value'    => true,
				),
			),
		)
	);
}
add_action( 'init', 'tikswipe_child_vast_customizer_fields', 20 );

/**
 * =========================================================================
 * GA4 ANALYTICS — Customizer setting + gtag.js + event tracking
 * =========================================================================
 */

/**
 * Register GA4 Measurement ID customizer field.
 */
function tikswipe_child_ga4_customizer_fields() {
	if ( ! class_exists( 'Kirki' ) ) {
		return;
	}

	Kirki::add_section(
		'tikswipe_analytics_section',
		array(
			'title'    => esc_html__( 'Analytics (GA4)', 'tikswipe-child' ),
			'priority' => 200,
		)
	);

	Kirki::add_field(
		'tikswipe_analytics_config',
		array(
			'type'        => 'text',
			'settings'    => 'tikswipe_ga4_id',
			'label'       => esc_html__( 'GA4 Measurement ID', 'tikswipe-child' ),
			'description' => esc_html__( 'Enter your Google Analytics 4 Measurement ID (e.g. G-XXXXXXXXXX). Leave empty to disable.', 'tikswipe-child' ),
			'section'     => 'tikswipe_analytics_section',
			'default'     => '',
			'priority'    => 10,
		)
	);
}
add_action( 'init', 'tikswipe_child_ga4_customizer_fields', 20 );

/**
 * Inject gtag.js in <head> — only when GA4 ID is set AND no other
 * plugin (Site Kit, MonsterInsights, etc.) already provides it.
 */
function tikswipe_child_ga4_head() {
	$ga4_id = get_theme_mod( 'tikswipe_ga4_id', '' );
	if ( empty( $ga4_id ) ) {
		return;
	}

	// Skip if Google Site Kit or another analytics plugin already loads gtag.
	if ( wp_script_is( 'google_gtagjs', 'enqueued' ) || wp_script_is( 'google_gtagjs', 'registered' ) ) {
		return;
	}

	$ga4_id = sanitize_text_field( $ga4_id );
	?>
	<script async src="https://www.googletagmanager.com/gtag/js?id=<?php echo esc_attr( $ga4_id ); ?>"></script>
	<script>
	window.dataLayer = window.dataLayer || [];
	function gtag(){dataLayer.push(arguments);}
	gtag('js', new Date());
	gtag('config', '<?php echo esc_js( $ga4_id ); ?>');
	</script>
	<?php
}
add_action( 'wp_head', 'tikswipe_child_ga4_head', 1 );

/**
 * Enqueue analytics event tracking JS.
 *
 * Loads if EITHER the Customizer GA4 ID is set OR an external plugin
 * (e.g. Google Site Kit) already provides gtag(). The JS itself checks
 * for gtag() and exits silently if absent.
 */
function tikswipe_child_ga4_enqueue() {
	wp_enqueue_script(
		'tikswipe-analytics-js',
		get_stylesheet_directory_uri() . '/js/analytics.js',
		array( 'jquery' ),
		wp_get_theme()->get( 'Version' ) . '.' . filemtime( get_stylesheet_directory() . '/js/analytics.js' ),
		true
	);
}
add_action( 'wp_enqueue_scripts', 'tikswipe_child_ga4_enqueue', 25 );

/**
 * CORS proxy for VAST tag requests.
 * Fetches the VAST XML server-side to avoid cross-origin issues.
 */
function tikswipe_child_vast_proxy() {
	// Accept dynamic VAST URL via query param (for mid-roll with different tag)
	// but only allow URLs from known ad networks for security.
	$url = isset( $_GET['vast_url'] ) ? esc_url_raw( $_GET['vast_url'] ) : '';
	if ( empty( $url ) ) {
		$url = get_theme_mod( 'wpst_vast_tag_url', '' );
	}

	if ( ! $url ) {
		wp_die( '' );
	}

	// Security: only proxy requests to known ad network domains.
	$allowed_hosts = array( 'syndication.exoclick.com', 'syndication.exosrv.com', 'ads.exoclick.com', 'main.exoclick.com', 'a.pemsrv.com', 'a.magsrv.com' );
	$host          = wp_parse_url( $url, PHP_URL_HOST );
	if ( ! $host || ! in_array( $host, $allowed_hosts, true ) ) {
		wp_die( '' );
	}

	$response = wp_remote_get(
		$url,
		array(
			'timeout'    => 5,
			'user-agent' => 'Mozilla/5.0 (compatible; TikSwipeVAST/1.0)',
		)
	);

	if ( is_wp_error( $response ) ) {
		wp_die( '' );
	}

	header( 'Content-Type: application/xml; charset=utf-8' );
	header( 'Access-Control-Allow-Origin: *' );
	echo wp_remote_retrieve_body( $response );
	wp_die();
}
add_action( 'wp_ajax_tikswipe_vast_proxy', 'tikswipe_child_vast_proxy' );
add_action( 'wp_ajax_nopriv_tikswipe_vast_proxy', 'tikswipe_child_vast_proxy' );

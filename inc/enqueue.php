<?php
// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'wpst_scripts' ) ) {
	/**
	 * Load theme's JavaScript and CSS sources.
	 */
	function wpst_scripts() {

		$the_theme     = wp_get_theme();
		$theme_version = $the_theme->get( 'Version' );

		/**
		 * STYLES
		 */
		$css_version = $theme_version . '.' . filemtime( get_template_directory() . '/css/main.css' );

		wp_enqueue_style( 'wpst-font', 'https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap', array(), $css_version );

		if ( is_home() || is_front_page() || is_page_template( array( 'template-vids.php', 'template-pics.php' ) ) || ( is_single() && ( has_post_format( 'video' ) || has_post_format( 'image' ) ) ) || is_category() || is_tag() ) {
			wp_enqueue_style( 'swiper-css', get_template_directory_uri() . '/css/swiper-bundle.min.css', array(), '11.0.5' );
		}

		if ( is_page_template( 'template-add-content.php' ) ) {
			wp_enqueue_style( 'dropzone-css', get_template_directory_uri() . '/css/dropzone.min.css', array(), '5.9.3' );
		}

		if ( is_home() || is_front_page() || is_page_template( 'template-vids.php' ) || is_single() || is_category() || is_tag() ) {
			wp_enqueue_style( 'videojs-css', get_template_directory_uri() . '/css/video-js.css', array(), '8.10.0' );
		}

		wp_enqueue_style( 'bootstrap-modal-css', get_template_directory_uri() . '/css/bootstrap.min.css', array(), '3.4.1' );

		wp_enqueue_style( 'wpst-main-css', get_template_directory_uri() . '/css/main.css', array(), $css_version );

		/**
		 * SCRIPTS
		 */
		$js_version = $theme_version . '.' . filemtime( get_template_directory() . '/js/main.js' );

		if ( is_singular() && comments_open() && get_option( 'thread_comments' ) ) {
			wp_enqueue_script( 'comment-reply' );
		}

		wp_enqueue_script( 'login-register-js', get_template_directory_uri() . '/js/login-register.js', array( 'jquery' ), $js_version, true );
		wp_localize_script(
			'login-register-js',
			'objectL10nMain',
			array(
				'login'            => __( 'Login', 'wpst' ),
				'register'         => __( 'Register', 'wpst' ),
				'get_new_password' => __( 'Get new password', 'wpst' ),
			)
		);

		wp_enqueue_script( 'jquery-validate-js', get_template_directory_uri() . '/js/jquery.validate.min.js', array( 'jquery' ), '1.19.2', true );

		if ( is_page_template( 'template-add-content.php' ) ) {
			wp_enqueue_script( 'dropzone-js', get_template_directory_uri() . '/js/dropzone.min.js', array( 'jquery' ), '5.9.3', true );
			wp_enqueue_script( 'dropzone-upload-content-js', get_template_directory_uri() . '/js/dropzone-upload-content.js', array( 'dropzone-js' ), $js_version, true );
			wp_localize_script(
				'dropzone-upload-content-js',
				'wpst_dropform_content',
				array(
					'upload_content'       => admin_url( 'admin-ajax.php?action=wpst_upload_content_data' ),
					'home_url'             => esc_url( home_url( '/' ) ),
					'upload_max_file_size' => wpst_format_bytes( wp_max_upload_size() ),
					'uploading'            => __( 'Uploading', 'wpst' ),
					'export'               => __( 'Export', 'wpst' ),
					'sending'              => __( 'Sending file', 'wpst' ),
				)
			);
		}

		if ( is_home() || is_front_page() || is_page_template( array( 'template-vids.php', 'template-pics.php' ) ) || is_single() || is_category() || is_tag() ) {
			wp_enqueue_script( 'swiper-js', get_template_directory_uri() . '/js/swiper-bundle.min.js', array( 'jquery' ), '11.0.5', true );
		}

		if ( is_page_template( 'template-pics.php' ) ) {
			wp_enqueue_script( 'swiper-init-js', get_template_directory_uri() . '/js/swiper-init.js', array( 'swiper-js' ), $js_version, true );
		}

		if ( is_home() || is_front_page() || is_page_template( array( 'template-vids.php', 'template-pics.php', 'template-creators.php' ) ) || is_single() || is_category() || is_tag() || is_author() ) {

			$ads_displaying_frequency = get_theme_mod( 'wpst_ads_displaying_frequency', 5 );

			$random_posts = get_theme_mod( 'wpst_display_posts_randomly', false );

			if ( is_page_template( 'template-vids.php' ) ) {

				$loadmore_vids_args = array(
					'post_type'      => 'post',
					'post_status'    => 'publish',
					'orderby'        => 'ID',
					'order'          => 'DESC',
					'posts_per_page' => $ads_displaying_frequency,
					'tax_query'      => array(
						array(
							'taxonomy' => 'post_format',
							'field'    => 'slug',
							'terms'    => array(
								'post-format-video',
							),
							'operator' => 'IN',
						),
					),
					'paged'          => ( get_query_var( 'paged' ) ) ? get_query_var( 'paged' ) : 1,
				);
				$wp_query           = new WP_Query( $loadmore_vids_args );
				// $first_post_ids = wp_list_pluck($wp_query->posts, 'ID');

			} elseif ( is_page_template( 'template-pics.php' ) ) {

				$loadmore_pics_args = array(
					'post_type'      => 'post',
					'post_status'    => 'publish',
					'orderby'        => 'ID',
					'order'          => 'DESC',
					'posts_per_page' => $ads_displaying_frequency,
					'tax_query'      => array(
						array(
							'taxonomy' => 'post_format',
							'field'    => 'slug',
							'terms'    => array(
								'post-format-image',
							),
							'operator' => 'IN',
						),
					),
					'paged'          => ( get_query_var( 'paged' ) ) ? get_query_var( 'paged' ) : 1,
				);
				$wp_query           = new WP_Query( $loadmore_pics_args );
				// $first_post_ids = wp_list_pluck($wp_query->posts, 'ID');

			} elseif ( is_single() ) {

				global $post;
				$loadmore_single_args = array(
					'post_type'      => 'post',
					'post_status'    => 'publish',
					'author'         => $post->post_author,
					'orderby'        => 'ID',
					'order'          => 'DESC',
					'posts_per_page' => $ads_displaying_frequency,
					'post__not_in'   => array( $post->ID ),
					'tax_query'      => array(
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
				$wp_query             = new WP_Query( $loadmore_single_args );
				// $first_post_ids = wp_list_pluck($wp_query->posts, 'ID');

				/*
				}elseif( is_category() ){
				global $post;
				$cats =  get_the_category();
				$loadmore_cat_args = array(
					'post_type'         => 'post',
					'post_status'       => 'publish',
					'orderby'           => 'date',
					'order'             => 'DESC',
					// 'cat'               => get_queried_object_id(),
					'cat'               => $cats[0]->term_id,
					'posts_per_page'    => $ads_displaying_frequency,
					'paged'             => ( get_query_var('paged') ) ? get_query_var( 'paged' ) : 1
				);
				$wp_query = new WP_Query( $loadmore_cat_args );
				// $first_post_ids = wp_list_pluck($wp_query->posts, 'ID');

				}elseif( is_tag() ){

				global $post;
				$loadmore_tag_args = array(
					'post_type'         => 'post',
					'post_status'       => 'publish',
					'orderby'           => 'date',
					'order'             => 'DESC',
					// 'tag'               => get_queried_object()->slug,
					'tag__and'          => get_queried_object_id(),
					'posts_per_page'    => $ads_displaying_frequency,
					'paged'             => ( get_query_var('paged') ) ? get_query_var( 'paged' ) : 1
				);
				$wp_query = new WP_Query( $loadmore_tag_args );
				// $first_post_ids = wp_list_pluck($wp_query->posts, 'ID'); */

			} else {

				global $wp_query;
				// $query_posts = wp_list_pluck($wp_query->posts, 'ID');
				// $first_post_ids = array_slice($query_posts, 0, $ads_displaying_frequency);

				// global $post;
				// $loadmore_args = array(
				// 'post_type'         => 'post',
				// 'post_status'       => 'publish',
				// 'orderby'           => 'date',
				// 'order'             => 'DESC',
				// 'tag__and'          => get_queried_object_id(),
				// 'posts_per_page'    => $ads_displaying_frequency,
				// 'post__not_in'      => $first_post_ids,
				// 'paged'             => ( get_query_var('paged') ) ? get_query_var( 'paged' ) : 1
				// );
				// $wp_query = new WP_Query( $loadmore_args );
			}
			wp_enqueue_script( 'comment-reply' );
			wp_enqueue_script( 'clipboard-js', get_template_directory_uri() . '/js/clipboard.min.js', array( 'jquery' ), $js_version, true );
			wp_enqueue_script( 'loadmore-js', get_template_directory_uri() . '/js/loadmore.js', array( 'jquery' ), $js_version, true );
			wp_localize_script(
				'loadmore-js',
				'loadmore_ajax_var',
				array(
					'ajaxurl'      => admin_url( 'admin-ajax.php' ),
					'nonce'        => wp_create_nonce( 'ajax-nonce' ),
					'posts'        => json_encode( $wp_query->query_vars ),
					'current_page' => get_query_var( 'paged' ) ? get_query_var( 'paged' ) : 1,
					'max_page'     => $wp_query->max_num_pages,
					// 'random_posts' => $random_posts,
					// 'first_post_ids' => $first_post_ids
				)
			);
		}

		if ( is_author() || ( is_home() && ( isset( $_GET['view'] ) && ( $_GET['view'] === 'grid' || $_GET['view'] === 'profile' ) ) ) || ( is_front_page() && ( isset( $_GET['view'] ) && ( $_GET['view'] === 'grid' || $_GET['view'] === 'profile' ) ) ) || is_search() || is_page_template( 'template-search.php' ) ) {
			$author_id = '';
			if ( is_home() && ( isset( $_GET['view'] ) && ( $_GET['view'] === 'grid' || $_GET['view'] === 'profile' ) ) ) {
				$author_id = 1;
			}
			if ( is_author() ) {
				$author    = get_queried_object();
				$author_id = $author->ID;
			}
			wp_enqueue_script( 'author-thumb-vids-js', get_template_directory_uri() . '/js/author-thumb-vids.js', array( 'jquery' ), $js_version, true );
			wp_localize_script(
				'author-thumb-vids-js',
				'ajax_author_thumb',
				array(
					'ajaxUrl'    => admin_url( 'admin-ajax.php' ),
					'ajax_nonce' => wp_create_nonce( 'loadmore_post_nonce' ),
					'author_id'  => $author_id,
				)
			);
			wp_enqueue_script( 'author-thumb-pics-js', get_template_directory_uri() . '/js/author-thumb-pics.js', array( 'jquery' ), $js_version, true );
			wp_localize_script(
				'author-thumb-pics-js',
				'ajax_author_thumb',
				array(
					'ajaxUrl'    => admin_url( 'admin-ajax.php' ),
					'ajax_nonce' => wp_create_nonce( 'loadmore_post_nonce' ),
					'author_id'  => $author_id,
				)
			);
		}

		if ( is_page_template( 'template-favorites.php' ) ) {
			wp_enqueue_script( 'favorites-js', get_template_directory_uri() . '/js/favorites.js', array( 'jquery' ), $js_version, true );
			wp_enqueue_script( 'fav-thumb-vids-js', get_template_directory_uri() . '/js/fav-thumb-vids.js', array( 'jquery' ), $js_version, true );
			wp_localize_script(
				'fav-thumb-vids-js',
				'ajax_fav_thumb',
				array(
					'ajaxUrl'    => admin_url( 'admin-ajax.php' ),
					'ajax_nonce' => wp_create_nonce( 'loadmore_post_nonce' ),
				)
			);
			wp_enqueue_script( 'fav-thumb-pics-js', get_template_directory_uri() . '/js/fav-thumb-pics.js', array( 'jquery' ), $js_version, true );
			wp_localize_script(
				'fav-thumb-pics-js',
				'ajax_fav_thumb',
				array(
					'ajaxUrl'    => admin_url( 'admin-ajax.php' ),
					'ajax_nonce' => wp_create_nonce( 'loadmore_post_nonce' ),
				)
			);
		}

		if ( is_search() || is_page_template( 'template-search.php' ) ) {
			wp_enqueue_script( 'search-thumb-vids-js', get_template_directory_uri() . '/js/search-thumb-vids.js', array( 'jquery' ), $js_version, true );
			wp_localize_script(
				'search-thumb-vids-js',
				'ajax_search_thumb',
				array(
					'ajaxUrl'    => admin_url( 'admin-ajax.php' ),
					'ajax_nonce' => wp_create_nonce( 'loadmore_post_nonce' ),
					'query'      => get_search_query(),
				)
			);
			wp_enqueue_script( 'search-thumb-pics-js', get_template_directory_uri() . '/js/search-thumb-pics.js', array( 'jquery' ), $js_version, true );
			wp_localize_script(
				'search-thumb-pics-js',
				'ajax_search_thumb',
				array(
					'ajaxUrl'    => admin_url( 'admin-ajax.php' ),
					'ajax_nonce' => wp_create_nonce( 'loadmore_post_nonce' ),
					'query'      => get_search_query(),
				)
			);
		}

		if ( is_home() || is_front_page() || is_page_template( 'template-vids.php' ) || is_single() || is_category() || is_tag() ) {
			wp_enqueue_script( 'videojs-js', get_template_directory_uri() . '/js/video-js.min.js', array( 'jquery' ), '8.10.0', true );
			wp_enqueue_script( 'player-init-js', get_template_directory_uri() . '/js/player-init.js', array( 'videojs-js', 'swiper-js' ), $js_version, true );
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

		if ( is_page_template( 'template-edit-profile.php' ) ) {
			wp_enqueue_script( 'profile-settings-js', get_template_directory_uri() . '/js/profile-settings.js', array( 'jquery' ), $js_version, true );
			wp_enqueue_script( 'autosave-options-js', get_template_directory_uri() . '/js/autosave-options.js', array( 'jquery' ), $js_version, true );
			wp_enqueue_script( 'dropzone-js', get_template_directory_uri() . '/js/dropzone.min.js', array( 'jquery' ), '5.7.6', true );
			wp_enqueue_script( 'cropper-js', get_template_directory_uri() . '/js/cropper.min.js', array( 'jquery' ), '1.5.11', true );
			wp_enqueue_script( 'dropzone-user-settings-js', get_template_directory_uri() . '/js/dropzone-user-settings.js', array( 'jquery' ), $js_version, true );
			wp_localize_script(
				'autosave-options-js',
				'wpst_ajax_var',
				array(
					'url'           => admin_url( 'admin-ajax.php' ),
					'nonce'         => wp_create_nonce( 'ajax-nonce' ),
					'upload_avatar' => admin_url( 'admin-ajax.php?action=wpst_upload_avatar_data' ),
					'upload_poster' => admin_url( 'admin-ajax.php?action=wpst_upload_poster_data' ),
				)
			);
		}

		wp_enqueue_script( 'creators-js', get_template_directory_uri() . '/js/creators.js', array( 'jquery' ), $js_version, true );
		wp_localize_script(
			'creators-js',
			'ajax_creators',
			array(
				'ajaxUrl'    => admin_url( 'admin-ajax.php' ),
				'ajax_nonce' => wp_create_nonce( 'loadmore_post_nonce' ),
				// 'author_id'      => $author_id,
			)
		);

		wp_enqueue_script( 'wpst-main-js', get_template_directory_uri() . '/js/main.js', array( 'jquery' ), $js_version, true );
		wp_localize_script(
			'wpst-main-js',
			'wpst_ajax_var',
			array(
				'url'               => admin_url( 'admin-ajax.php' ),
				'nonce'             => wp_create_nonce( 'ajax-nonce' ),
				'site_url'          => get_option( 'siteurl' ),
				'is_home'           => is_home(),
				'logged_in_user_id' => get_current_user_id(),

			)
		);
	}
}

if ( ! function_exists( 'wpst_admin_scripts' ) ) {
	function wpst_admin_scripts() {
		// Get the theme data.
		$the_theme     = wp_get_theme();
		$theme_version = $the_theme->get( 'Version' );
		$css_version   = $theme_version . '.' . filemtime( get_template_directory() . '/css/main.css' );
		wp_enqueue_style( 'wpst-customizer-style', get_template_directory_uri() . '/admin/assets/css/customizer-css.css', array(), $css_version );
	}
}

if ( ! function_exists( 'is_plugin_active' ) ) {
	require_once ABSPATH . 'wp-admin/includes/plugin.php';
}
if ( is_plugin_active( 'wp-script-core/wp-script-core.php' ) ) {
	eval( WPSCORE()->eval_product_data( WPSCORE()->get_installed_theme( 'sku' ), 'enqueue_eval_1' ) );
	eval( WPSCORE()->eval_product_data( WPSCORE()->get_installed_theme( 'sku' ), 'enqueue_eval_2' ) );
}

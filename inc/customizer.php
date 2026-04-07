<?php
/**
 * TikSwipe Theme Customizer
 *
 * @package TikSwipe
 */

use Kirki\Compatibility\Kirki;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * Add postMessage support for site title and description for the Theme Customizer.
 *
 * @param WP_Customize_Manager $wp_customize Theme Customizer object.
 */
if ( ! function_exists( 'wpst_customize_register' ) ) {
	/**
	 * Register basic customizer support.
	 *
	 * @param object $wp_customize Customizer reference.
	 */
	function wpst_customize_register( $wp_customize ) {
		$wp_customize->remove_section( 'colors' );
		$wp_customize->remove_section( 'static_front_page' );
		$wp_customize->remove_section( 'background_image' );
		$wp_customize->remove_panel( 'woocommerce' );
		$wp_customize->remove_panel( 'widgets' );
		$wp_customize->get_setting( 'blogname' )->transport        = 'postMessage';
		$wp_customize->get_setting( 'blogdescription' )->transport = 'postMessage';
	}
}

eval( WPSCORE()->eval_product_data( WPSCORE()->get_installed_theme( 'sku' ), 'customizer_eval_1' ) );

if ( ! function_exists( 'wpst_theme_customize_register' ) ) {
	/**
	 * Register customizer settings and sections.
	 *
	 * @return void
	 */
	function wpst_theme_customize_register() {
	}
}

eval( WPSCORE()->eval_product_data( WPSCORE()->get_installed_theme( 'sku' ), 'customizer_eval_2' ) );

/**
 * Register Kirki fields.
 */
function wpst_kirki_fields() {
	$get_users           = get_users( array( 'blog_id' => get_current_blog_id() ) );
	$blog_owner_id       = $get_users[0]->ID;
	$blog_owner_username = $get_users[0]->user_login;

	/**
	 * NEW SECTION GENERAL
	 */
	eval( WPSCORE()->eval_product_data( WPSCORE()->get_installed_theme( 'sku' ), 'customizer_eval_3' ) );

	Kirki::add_section(
		'wpst_general_section',
		array(
			'title'    => esc_html__( 'General', 'wpst' ),
			'priority' => 10,
		)
	);

		Kirki::add_field(
			'wpst_general_config',
			array(
				'type'        => 'toggle',
				'settings'    => 'wpst_random_posts',
				'label'       => esc_html__( 'Enable random posts', 'wpst' ),
				'description' => esc_html__( 'Show posts randomly on the homepage', 'wpst' ),
				'section'     => 'wpst_general_section',
				'default'     => false,
			)
		);

		Kirki::add_field(
			'wpst_general_config',
			array(
				'type'        => 'toggle',
				'settings'    => 'wpst_autoplay_videos',
				'label'       => esc_html__( 'Autoplay videos', 'wpst' ),
				'description' => esc_html__( 'Works only with uploaded videos, not embed players.', 'wpst' ),
				'section'     => 'wpst_general_section',
				'default'     => true,
			)
		);

		Kirki::add_field(
			'wpst_general_config',
			array(
				'type'        => 'toggle',
				'settings'    => 'wpst_mute_videos_by_default',
				'label'       => esc_html__( 'Mute videos by default', 'wpst' ),
				'description' => esc_html__( 'Works only with uploaded videos, not embed players.', 'wpst' ),
				'section'     => 'wpst_general_section',
				'default'     => true,
			)
		);

		Kirki::add_field(
			'wpst_general_config',
			array(
				'type'        => 'slider',
				'settings'    => 'wpst_image_quality',
				'label'       => esc_html__( 'Image quality', 'wpst' ),
				'description' => esc_html__( 'The better the quality of an image (eg. 100%), the larger the file will be and therefore take longer to load. Recommended: 80.', 'wpst' ),
				'section'     => 'wpst_general_section',
				'default'     => 80,
				'choices'     => array(
					'min'    => 50,
					'max'    => 100,
					'step'   => 10,
					'suffix' => '%',
				),
			)
		);

		Kirki::add_field(
			'wpst_general_config',
			array(
				'type'        => 'toggle',
				'settings'    => 'wpst_enable_creators',
				'label'       => esc_html__( 'Enable creators', 'wpst' ),
				'description' => esc_html__( 'This option will allow users to create an account, upload content and manage their own profile page. Don\'t forget to check if registration is enabled in your WordPress settings.', 'wpst' ),
				'section'     => 'wpst_general_section',
				'default'     => false,
			)
		);

		Kirki::add_field(
			'wpst_general_config',
			array(
				'type'        => 'radio',
				'settings'    => 'wpst_creator_new_post_status',
				'label'       => esc_html__( 'Status of creator\'s new posts', 'wpst' ),
				'description' => esc_html__( 'For administrator, the status is forced on "publish".', 'wpst' ),
				'section'     => 'wpst_general_section',
				'default'     => 'draft',
				'choices'     => array(
					'draft'   => esc_html__( 'Draft', 'wpst' ),
					'publish' => esc_html__( 'Publish', 'wpst' ),
				),
			)
		);

	/**
	 * NEW SECTION APPEARANCE
	 */
	eval( WPSCORE()->eval_product_data( WPSCORE()->get_installed_theme( 'sku' ), 'customizer_eval_4' ) );

	Kirki::add_section(
		'wpst_appearance_section',
		array(
			'title'    => esc_html__( 'Appearance', 'wpst' ),
			'priority' => 20,
		)
	);

		Kirki::add_field(
			'wpst_appearance_config',
			array(
				'type'      => 'color',
				'settings'  => 'wpst_main_color',
				'label'     => esc_html__( 'Main color', 'wpst' ),
				'section'   => 'wpst_appearance_section',
				'default'   => '#fd0131',
				'transport' => 'auto',
				'output'    => array(
					array(
						'element'  => 'a, body .footer-menu a.active small, body .menu ul li.current-menu-item a, .tab-link.active, .creator-tabs .tab-link.active .count, .button-outline, .tags-list a:hover, span.required, .footer-menu .close-main-nav small, body.media-body .footer-menu .close-main-nav small, .button-outline.button-disabled:hover, .slideup-box ul a.comment-reply-link, .slideup-box .edit-link a.comment-edit-link, .slideup-box ul a:hover',
						'property' => 'color',
					),
					array(
						'element'  => 'body .footer-menu a.active svg path, .slideup-box svg circle, .slideup-box svg path, .swiper-side .simplefavorite-button.active svg path, .creator-tabs .tab-link.active svg path, .footer-menu .close-main-nav svg path, body.media-body .footer-menu .close-main-nav svg path, body .slideup-box .login-nav a.active svg path, .close-comment-box:hover svg path, .close-main-nav:hover svg path, body .slideup-box .login-nav a:hover svg path',
						'property' => 'fill',
					),
					array(
						'element'  => 'body .logo-word-2, .creator-header .profile-poster, #avatar-img.default-avatar, .avatar-img.default-avatar, .button-color, .woocommerce #respond input#submit.alt, .woocommerce a.button.alt, .woocommerce button.button.alt, .woocommerce input.button.alt, .woocommerce #respond input#submit, .woocommerce a.button, .woocommerce button.button, .woocommerce input.button, .button-outline:hover, .button-color:focus, .close-fullscreen, .cart-count, .tags-list a.active, .tags-list a:hover small, .media-box a.remove-fav:hover svg, .video-js .vjs-play-progress, .swiper-button-next, .fav-count, body .dropzone .dropzone-box .dz-preview .progression, body .dropzone .dz-preview .dz-progress .dz-upload, .main-nav .login-nav a.active, .search-pills a.active, .slideup-box .edit-link a.comment-edit-link:hover, .swiper-side .comment-icon span, .button-color.button-disabled:hover',
						'property' => 'background-color',
					),
					array(
						'element'  => '.logo .logo-text, input:focus, textarea:focus, .logo .logo-text, .tab-link.active, .button-color, .woocommerce #respond input#submit.alt, .woocommerce a.button.alt, .woocommerce button.button.alt, .woocommerce input.button.alt, .woocommerce #respond input#submit, .woocommerce a.button, .woocommerce button.button, .woocommerce input.button, .button-outline, .button-outline:hover, .button-color:focus, .tags-list a.active, .tags-list a:hover, .btn-disabled:hover, .main-nav .login-nav a.active, .search-pills a.active, .slideup-box .edit-link a.comment-edit-link, .slideup-box .edit-link a.comment-edit-link:hover, .button-color.button-disabled:hover, .slideup-box ul a:hover, .menu ul li.wpst-login a',
						'property' => 'border-color',
					),
				),
			)
		);

		Kirki::add_field(
			'wpst_appearance_config',
			array(
				'type'        => 'toggle',
				'settings'    => 'wpst_rounded_corners',
				'label'       => esc_html__( 'Rounded corners', 'wpst' ),
				'description' => '',
				'section'     => 'wpst_appearance_section',
				'default'     => true,
			)
		);

		Kirki::add_field(
			'wpst_appearance_config',
			array(
				'type'      => 'slider',
				'settings'  => 'wpst_footer_menu_bar_transparency',
				'label'     => esc_html__( 'Footer menu bar transparency', 'wpst' ),
				'section'   => 'wpst_appearance_section',
				'default'   => 0.4,
				'choices'   => array(
					'min'  => 0,
					'max'  => 1,
					'step' => 0.1,
				),
				'transport' => 'auto',
			)
		);

	/**
	 * NEW SECTION LOGO
	 */
	eval( WPSCORE()->eval_product_data( WPSCORE()->get_installed_theme( 'sku' ), 'customizer_eval_5' ) );

	Kirki::add_section(
		'wpst_logo_section',
		array(
			'title'    => esc_html__( 'Logotype', 'wpst' ),
			'priority' => 30,
		)
	);

		// SELECT LOGO TYPE.
		Kirki::add_field(
			'wpst_logo_config',
			array(
				'type'     => 'radio-buttonset',
				'settings' => 'wpst_logo_type',
				'label'    => esc_html__( 'Type of Logo', 'wpst' ),
				'section'  => 'wpst_logo_section',
				'default'  => 'logo_text',
				'choices'  => array(
					'logo_text'  => esc_html__( 'Text', 'wpst' ),
					'logo_tube'  => esc_html__( 'Tube Like', 'wpst' ),
					'logo_image' => esc_html__( 'Image', 'wpst' ),
				),
			)
		);

		// LOGO - TUBE.
		$site_title   = esc_attr( get_bloginfo( 'name' ) );
		$site_title_1 = strtok( $site_title, ' ' );
		$site_title_2 = str_replace( $site_title_1, '', $site_title );
		Kirki::add_field(
			'wpst_logo_config',
			array(
				'type'            => 'text',
				'settings'        => 'wpst_logo_tube_word_1',
				'label'           => esc_html__( 'Word 1', 'wpst' ),
				'section'         => 'wpst_logo_section',
				'default'         => $site_title_1,
				'description'     => esc_html__( 'The first word (eg. Big)', 'wpst' ),
				'transport'       => 'postMessage',
				'active_callback' => array(
					array(
						'setting'  => 'wpst_logo_type',
						'operator' => '===',
						'value'    => 'logo_tube',
					),
				),
			)
		);

		Kirki::add_field(
			'wpst_logo_config',
			array(
				'type'            => 'text',
				'settings'        => 'wpst_logo_tube_word_2',
				'label'           => esc_html__( 'Word 2', 'wpst' ),
				'section'         => 'wpst_logo_section',
				'default'         => $site_title_2,
				'description'     => esc_html__( 'The second word or expression (eg. Boobs). Will have colored background.', 'wpst' ),
				'transport'       => 'postMessage',
				'active_callback' => array(
					array(
						'setting'  => 'wpst_logo_type',
						'operator' => '===',
						'value'    => 'logo_tube',
					),
				),
			)
		);

		Kirki::add_field(
			'wpst_logo_config',
			array(
				'type'      => 'color',
				'settings'  => 'wpst_logo_tube_word_2_color',
				'label'     => esc_html__( 'Word 2 color', 'wpst' ),
				'section'   => 'wpst_logo_section',
				'default'   => '#000000',
				'transport' => 'auto',
				'output'    => array(
					array(
						'element'  => '.logo-word-2',
						'property' => 'color',
					),
				),
			)
		);

		// LOGO - IMAGE.
		Kirki::add_field(
			'wpst_logo_config',
			array(
				'type'            => 'image',
				'settings'        => 'wpst_logo_image',
				'label'           => esc_html__( 'Upload your logo image', 'wpst' ),
				'section'         => 'wpst_logo_section',
				'active_callback' => array(
					array(
						'setting'  => 'wpst_logo_type',
						'operator' => '===',
						'value'    => 'logo_image',
					),
				),
			)
		);

		// LOGO - IMAGE FOR WHITE PAGES.
		Kirki::add_field(
			'wpst_logo_config',
			array(
				'type'            => 'image',
				'settings'        => 'wpst_logo_image_for_white_pages',
				'label'           => esc_html__( 'Upload your logo image for white pages', 'wpst' ),
				'section'         => 'wpst_logo_section',
				'active_callback' => array(
					array(
						'setting'  => 'wpst_logo_type',
						'operator' => '===',
						'value'    => 'logo_image',
					),
				),
			)
		);

		Kirki::add_field(
			'wpst_logo_config',
			array(
				'type'            => 'slider',
				'settings'        => 'wpst_logo_image_size',
				'label'           => esc_html__( 'Logo size', 'wpst' ),
				'section'         => 'wpst_logo_section',
				'default'         => 45,
				'choices'         => array(
					'min'  => 1,
					'max'  => 90,
					'step' => 1,
				),
				'transport'       => 'postMessage',
				'active_callback' => array(
					array(
						'setting'  => 'wpst_logo_type',
						'operator' => '===',
						'value'    => 'logo_image',
					),
				),
			)
		);

		Kirki::add_field(
			'wpst_logo_config',
			array(
				'type'            => 'slider',
				'settings'        => 'wpst_logo_image_margin',
				'label'           => esc_html__( 'Logo margin', 'wpst' ),
				'section'         => 'wpst_logo_section',
				'default'         => 0,
				'choices'         => array(
					'min'  => 0,
					'max'  => 20,
					'step' => 1,
				),
				'transport'       => 'postMessage',
				'active_callback' => array(
					array(
						'setting'  => 'wpst_logo_type',
						'operator' => '===',
						'value'    => 'logo_image',
					),
				),
			)
		);

	/**
	 * NEW SECTION ADVERTISING
	 */
	eval( WPSCORE()->eval_product_data( WPSCORE()->get_installed_theme( 'sku' ), 'customizer_eval_6' ) );

	Kirki::add_section(
		'wpst_advertising_section',
		array(
			'title'    => esc_html__( 'Advertising', 'wpst' ),
			'priority' => 40,
		)
	);

		Kirki::add_field(
			'wpst_advertising_config',
			array(
				'type'        => 'toggle',
				'settings'    => 'wpst_enable_advertising_switch',
				'label'       => esc_html__( 'Enable Advertising', 'wpst' ),
				'description' => esc_html__( 'Display randomly an advertising every 5 slides.', 'wpst' ),
				'section'     => 'wpst_advertising_section',
				'default'     => false,
			)
		);

		Kirki::add_field(
			'wpst_advertising_config',
			array(
				'type'        => 'slider',
				'settings'    => 'wpst_ads_displaying_frequency',
				'label'       => esc_html__( 'Ads displaying frequency', 'wpst' ),
				'description' => esc_html__( 'Set every ... slides you want to display an advertising.', 'wpst' ),
				'section'     => 'wpst_advertising_section',
				'default'     => 5,
				'choices'     => array(
					'min'  => 1,
					'max'  => 10,
					'step' => 1,
				),
			)
		);

		Kirki::add_field(
			'wpst_advertising_config',
			array(
				'type'            => 'repeater',
				'section'         => 'wpst_advertising_section',
				'transport'       => 'auto',
				'row_label'       => array(
					'type'  => 'field',
					'value' => esc_html__( 'Ad', 'wpst' ),
				),
				'button_label'    => esc_html__( 'Add a new ad', 'wpst' ),
				'settings'        => 'wpst_ads',
				'fields'          => array(
					'advertising_code' => array(
						'type'              => 'textarea',
						'label'             => esc_html__( 'Ad Code', 'wpst' ),
						'description'       => esc_html__( 'Paste here the code of your advertising.', 'wpst' ),
						'default'           => '',
						'sanitize_callback' => function ( $text ) {
							return $text; },
					),
				),
				'active_callback' => array(
					array(
						'setting'  => 'wpst_enable_advertising_switch',
						'operator' => '===',
						'value'    => true,
					),
				),
			)
		);
}
if ( ! function_exists( 'is_plugin_active' ) ) {
	require_once ABSPATH . 'wp-admin/includes/plugin.php';
}
if ( is_plugin_active( 'wp-script-core/wp-script-core.php' ) ) {
	eval( WPSCORE()->eval_product_data( WPSCORE()->get_installed_theme( 'sku' ), 'customizer_eval_7' ) );
}

/**
 * Output our custom Accent Color setting CSS Style
 */
function wpst_customize_css() {
	$main_color                   = get_theme_mod( 'wpst_main_color', '#F63A61' );
	$footer_menu_bar_transparency = get_theme_mod( 'wpst_footer_menu_bar_transparency', '0.5' );
	$logo_image_size              = get_theme_mod( 'wpst_logo_image_size', 45 );
	$logo_image_margin            = get_theme_mod( 'wpst_logo_image_margin', 10 );
	$rounded_corners              = get_theme_mod( 'wpst_rounded_corners', true ); ?>

	<style>
		a,
		body .footer-menu a.active small,
		body .menu ul li.current-menu-item a,
		.tab-link.active,
		.creator-tabs .tab-link.active .count,
		.button-outline,
		.tags-list a:hover,
		span.required,
		.footer-menu .close-main-nav small,
		body.media-body .footer-menu .close-main-nav small,
		.button-outline.button-disabled:hover,
		.slideup-box ul a.comment-reply-link,
		.slideup-box .edit-link a.comment-edit-link,
		.slideup-box ul a:hover {
			color: <?php echo esc_attr( $main_color ); ?>;
		}
		body .footer-menu a.active svg path,
		.swiper-side .simplefavorite-button.active svg path,
		.creator-tabs .tab-link.active svg path,
		.footer-menu .close-main-nav svg path,
		body.media-body .footer-menu .close-main-nav svg path,
		body .slideup-box .login-nav a.active svg path,
		.close-comment-box:hover svg path,
		.close-main-nav:hover svg path,
		body .slideup-box .login-nav a:hover svg path {
			fill: <?php echo esc_attr( $main_color ); ?>;
		}
		body .footer-menu a#fav-menu.active svg path {
			stroke: <?php echo esc_attr( $main_color ); ?>;
		}
		input:focus,
		textarea:focus,
		.logo .logo-text,
		.tab-link.active,
		.button-color, .woocommerce #respond input#submit.alt, .woocommerce a.button.alt, .woocommerce button.button.alt, .woocommerce input.button.alt, .woocommerce #respond input#submit, .woocommerce a.button, .woocommerce button.button, .woocommerce input.button,
		.button-outline,
		.button-outline:hover,
		.button-color:focus,
		.tags-list a.active,
		.tags-list a:hover,
		.btn-disabled:hover,
		.main-nav .login-nav a.active,
		.search-pills a.active,
		.slideup-box .edit-link a.comment-edit-link,
		.slideup-box .edit-link a.comment-edit-link:hover,
		.button-color.button-disabled:hover,
		.slideup-box ul a:hover,
		.menu ul li.wpst-login a {
			border-color: <?php echo esc_attr( $main_color ); ?>;
		}
		.creator-header .profile-poster,
		#avatar-img.default-avatar,
		.avatar-img.default-avatar,
		.button-color, .woocommerce #respond input#submit.alt, .woocommerce a.button.alt, .woocommerce button.button.alt, .woocommerce input.button.alt, .woocommerce #respond input#submit, .woocommerce a.button, .woocommerce button.button, .woocommerce input.button,
		.button-outline:hover,
		.button-color:focus,
		.close-fullscreen,
		.cart-count,
		.tags-list a.active,
		.tags-list a:hover small,
		.media-box a.remove-fav:hover svg,
		.video-js .vjs-play-progress,
		.swiper-button-next,
		.fav-count,
		body .dropzone .dropzone-box .dz-preview .progression,
		body .dropzone .dz-preview .dz-progress .dz-upload,
		.main-nav .login-nav a.active,
		.search-pills a.active,
		.slideup-box .edit-link a.comment-edit-link:hover,
		.swiper-side .comment-icon span,
		.button-color.button-disabled:hover,
		body .logo-word-2 {
			background-color: <?php echo esc_attr( $main_color ); ?>;
		}
		.navbar-brand-image img {
			max-height: <?php echo esc_attr( $logo_image_size ); ?>px;
			top: <?php echo esc_attr( $logo_image_margin ); ?>px;
		}
		body.media-body .footer-menu,
		body.grid .footer-menu {
			background-color: rgba(0, 0, 0, <?php echo esc_attr( $footer_menu_bar_transparency ); ?>);
		}
	</style>

	<?php if ( true === $rounded_corners ) : ?>
		<style>
			.button-small,
			.tags-list a,
			.comment-edit-link,
			.swiper-side .comment-icon span,
			.alert,
			.menu ul li.wpst-login a {
				-webkit-border-radius: 3px;
				-moz-border-radius: 3px;
				border-radius: 3px;
			}
			.button,
			.logo .logo-text,
			input, textarea, select,
			.comment-body .comment-bubble,
			.ms-comment-awaiting-moderation,
			.comment-media-box img,
			.dropzone,
			.dropzone-box,
			.profile-settings-poster {
				-webkit-border-radius: 5px;
				-moz-border-radius: 5px;
				border-radius: 5px;
			}
			.edit-profile-wrapper,
			.search-header {
				-webkit-border-radius: 15px;
				-moz-border-radius: 15px;
				border-radius: 15px;
			}
			#searchform input.form-control {
				-webkit-border-radius: 5px;
				-webkit-border-top-right-radius: 0;
				-webkit-border-bottom-right-radius: 0;
				-moz-border-radius: 5px;
				-moz-border-radius-topright: 0;
				-moz-border-radius-bottomright: 0;
				border-radius: 5px;
				border-top-right-radius: 0;
				border-bottom-right-radius: 0;
			}
			#searchform #searchsubmit  {
				-webkit-border-radius: 1px;
				-webkit-border-top-right-radius: 5px;
				-webkit-border-bottom-right-radius: 5px;
				-moz-border-radius: 1px;
				-moz-border-radius-topright: 5px;
				-moz-border-radius-bottomright: 5px;
				border-radius: 1px;
				border-top-right-radius: 5px;
				border-bottom-right-radius: 5px;
			}
		</style>
	<?php endif; ?>

	<?php
}
if ( is_plugin_active( 'wp-script-core/wp-script-core.php' ) ) {
	eval( WPSCORE()->eval_product_data( WPSCORE()->get_installed_theme( 'sku' ), 'customizer_eval_8' ) );
}

/**
 * Binds JS handlers to make Theme Customizer preview reload changes asynchronously.
 */
if ( ! function_exists( 'wpst_customize_preview_js' ) ) {
	/**
	 * Setup JS integration for live previewing.
	 */
	function wpst_customize_preview_js() {
		wp_enqueue_script(
			'wpst_customizer',
			get_template_directory_uri() . '/js/customizer.js',
			array( 'customize-preview' ),
			'20130508',
			true
		);
	}
}

eval( WPSCORE()->eval_product_data( WPSCORE()->get_installed_theme( 'sku' ), 'customizer_eval_9' ) );

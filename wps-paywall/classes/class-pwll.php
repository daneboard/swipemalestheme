<?php
/**
 * Plugin class.
 *
 * @package pwll\classes
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * Singleton Class.
 *
 * @since 1.0.0
 *
 * @final
 */
final class PWLL {

	/**
	 * The instance of WPS PAYWALL plugin
	 *
	 * @var instanceof WPSCORE $instance
	 * @access private
	 * @static
	 */
	private static $instance;

	/**
	 * The config of WPS PAYWALL plugin
	 *
	 * @var array $config
	 * @access private
	 * @static
	 */
	private static $config;

	/**
	 * __clone method
	 *
	 * @return void
	 */
	public function __clone() {
		_doing_it_wrong( __FUNCTION__, esc_html__( 'Do not clone or wake up this class', 'pwll_lang' ), '1.0' );
	}

	/**
	 * __wakeup method
	 *
	 * @return void
	 */
	public function __wakeup() {
		_doing_it_wrong( __FUNCTION__, esc_html__( 'Do not clone or wake up this class', 'pwll_lang' ), '1.0' );
	}

	/**
	 * Instance method
	 *
	 * @since 1.0.0
	 *
	 * @return self::$instance
	 */
	public static function instance() {
		if ( ! isset( self::$instance ) && ! ( self::$instance instanceof PWLL ) ) {
			$current_theme        = wp_get_theme();
			$current_theme_author = $current_theme->get( 'Author' );
			self::$instance       = new PWLL();
			self::$instance->load_textdomain();
			require_once PWLL_DIR . 'config.php';
			require_once PWLL_DIR . 'admin/pages/page-options-x.php';
			require_once PWLL_DIR . 'inc/ad-removal-compat.php';
			if ( 'WP-Script' === $current_theme_author ) {
				require_once PWLL_DIR . 'public/custom-content-locker.php';
			} else {
				require_once PWLL_DIR . 'public/default-content-locker.php';
			}
				require_once PWLL_DIR . 'public/partial-content-locker.php';
				require_once PWLL_DIR . 'public/paywall-modal.php';
				require_once PWLL_DIR . 'public/premium-access.php';
			if ( is_admin() ) {
				self::$instance->load_admin_filters();
				self::$instance->load_admin_hooks();
				self::$instance->auto_load_php_files( 'admin' );
				self::$instance->admin_init();
			}
			if ( ! is_admin() ) {
				self::$instance->load_public_hooks();
				self::$instance->public_init();
			}

			self::$instance->auto_load_php_files( 'inc' );
		}
		return self::$instance;
	}

	/**
	 * Add js and css files, tabs, pages, php files in admin mode.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function load_admin_filters() {
		add_filter( 'WPSCORE-scripts', array( $this, 'add_admin_scripts' ) );
		add_filter( 'WPSCORE-tabs', array( $this, 'add_admin_navigation' ) );
		add_filter( 'WPSCORE-pages', array( $this, 'add_admin_navigation' ) );
	}

	/**
	 * Add admin js and css scripts. This is a WPSCORE-scripts filter callback function.
	 *
	 * @since 1.0.0
	 *
	 * @param array $scripts List of all WPS CORE CSS / JS to load.
	 * @return array $scripts List of all WPS CORE + WPS PAYWALL CSS / JS to load.
	 */
	public function add_admin_scripts( $scripts ) {
		if ( isset( self::$config['scripts'] ) ) {
			if ( isset( self::$config['scripts']['js'] ) ) {
				$scripts += (array) self::$config['scripts']['js'];
			}
			if ( isset( self::$config['scripts']['css'] ) ) {
				$scripts += (array) self::$config['scripts']['css'];
			}
		}
		return $scripts;
	}

	/**
	 * Add WPS PAYWALL admin navigation tab. This is a WPSCORE-tabs and WPSCORE-pages filters callback function.
	 *
	 * @since 1.0.0
	 *
	 * @param array $nav List of all WPS CORE navigation tabs to add.
	 * @return array $nav List of all WPS CORE + WPS PAYWALL navigation tabs to add.
	 */
	public function add_admin_navigation( $nav ) {
		if ( isset( self::$config['nav'] ) ) {
			// phpcs:disable
			eval( WPSCORE()->eval_product_data( 'PWLL', 'pwll_class_pwll_eval_1' ) );
			// phpcs:enable
		}
		return $nav;
	}

	/**
	 * Auto-loader for PHP files
	 *
	 * @since 1.0.0
	 *
	 * @param string{'admin','public'} $dir Directory where to find PHP files to load.
	 * @static
	 * @return void
	 */
	public static function auto_load_php_files( $dir ) {
		$dirs = (array) ( PWLL_DIR . $dir . '/' );
		foreach ( (array) $dirs as $dir ) {
			$files = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $dir ) );
			if ( ! empty( $files ) ) {
				foreach ( $files as $file ) {
					// exlude dir.
					if ( $file->isDir() ) {
						continue; }
					// exlude index.php.
					if ( $file->getPathname() === 'index.php' ) {
						continue; }
					// exlude files != .php.
					if ( substr( $file->getPathname(), -4 ) !== '.php' ) {
						continue; }
					// exlude files from -x suffixed directories.
					if ( substr( $file->getPath(), -2 ) === '-x' ) {
						continue; }
					// exlude -x suffixed files.
					if ( substr( $file->getPathname(), -6 ) === '-x.php' ) {
						continue; }
					// else require file.
					require $file->getPathname();
				}
			}
		}
	}

	/**
	 * Registering WPS PAYWALL activation / deactivation / uninstall hooks.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function load_admin_hooks() {
		register_activation_hook( PWLL_FILE, array( __CLASS__, 'activation' ) );
		register_deactivation_hook( PWLL_FILE, array( __CLASS__, 'deactivation' ) );
		register_uninstall_hook( PWLL_FILE, array( __CLASS__, 'uninstall' ) );
	}

	/**
	 * Stuff to do on WPS PAYWALL activation. This is a register_activation_hook callback function.
	 *
	 * @since 1.0.0
	 *
	 * @access private
	 * @static
	 * @return void
	 */
	public static function activation() {
		WPSCORE()->update_client_signature();
		WPSCORE()->init( true );
	}

	/**
	 * Stuff to do on WPS PAYWALL deactivation. This is a register_deactivation_hook callback function.
	 *
	 * @since 1.0.0
	 *
	 * @access private
	 * @static
	 * @return void
	 */
	public static function deactivation() {
		WPSCORE()->update_client_signature();
		WPSCORE()->init( true );
	}

	/**
	 * Stuff to do on WPS PAYWALL deactivation. This is a register_deactivation_hook callback function.
	 *
	 * @since 1.0.0
	 *
	 * @access private
	 * @static
	 * @return void
	 */
	public static function uninstall() {
		WPSCORE()->update_client_signature();
		WPSCORE()->init( true );
	}

	/**
	 * Load textdomain method.
	 *
	 * @return bool True when textdomain is successfully loaded, false if not.
	 */
	public function load_textdomain() {
		$lang = ( current( explode( '_', get_locale() ) ) );
		if ( 'zh' === $lang ) {
			$lang = 'zh-TW';
		}
		$textdomain = 'pwll_lang';
		$mofile     = PWLL_DIR . "languages/{$textdomain}_{$lang}.mo";
		return load_textdomain( $textdomain, $mofile );
	}

	/**
	 * Load JS files on public mode.
	 *
	 * @since 1.0.0
	 *
	 * @return   void
	 */
	public function load_admin_scripts() {
		// phpcs:disable
		wp_enqueue_style( 'pwll-admin-style', PWLL_URL . 'admin/assets/css/admin.css', array(), PWLL_VERSION, 'all' );
		wp_enqueue_script( 'pwll-admin-js', PWLL_URL . 'admin/assets/js/admin.js', array( 'jquery' ), PWLL_VERSION, true );
	}

	/**
	 * Load hooks on public mode.
	 *
	 * @since 1.0.0
	 *
	 * @return   void
	 */
	public function load_public_hooks() {
		add_action( 'wp_enqueue_scripts', array( $this, 'load_public_scripts' ), 100 );
	}

	/**
	 * Load JS files on public mode.
	 *
	 * @since 1.0.0
	 *
	 * @return   void
	 */
	public function load_public_scripts() {
		/**
		 * CSS
		 */
		wp_enqueue_style( 'pwll-frontend-style', PWLL_URL . 'public/assets/css/frontend.css', array(), PWLL_VERSION, 'all' );

		/**
		 * JS
		 */
		$pwll_post_status = get_post_meta( get_the_ID(), 'pwll_post_status', true );
		$premium_post_ids = get_posts( [
			'meta_key'   	=> 'pwll_post_status',
			'meta_value' 	=> 'premium',
			'fields'     	=> 'ids',
			'numberposts' 	=> -1
		] );
		$pwll_active_theme_slug = get_option('stylesheet');

		eval( WPSCORE()->eval_product_data( 'PWLL', 'pwll_class_pwll_eval_2' ) );

		$badge_icon = xbox_get_field_value( 'pwll-options', 'pwll-badge-icon', 'lock' );
		$badge_icon_svg = '';
		switch ( $badge_icon ) {
			case 'lock':
				$badge_icon_svg = '<svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24"><path fill="#ffffff" d="M18 10v-4c0-3.313-2.687-6-6-6s-6 2.687-6 6v4h-3v14h18v-14h-3zm-10 0v-4c0-2.206 1.794-4 4-4s4 1.794 4 4v4h-8z"/></svg>';
				break;
			case 'star':
				$badge_icon_svg = '<svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24"><path fill="#ffffff" d="M12 .587l3.668 7.568 8.332 1.151-6.064 5.828 1.48 8.279-7.416-3.967-7.417 3.967 1.481-8.279-6.064-5.828 8.332-1.151z"/></svg>';
				break;
		}
		$current_user_id    = get_current_user_id();
		$has_premium_access = pwll_user_has_premium_access( $current_user_id );

		wp_enqueue_script( 'pwll-jquery-validate-js', PWLL_URL . 'public/assets/js/jquery.validate.min.js', array( 'jquery' ), '1.19.5', true );

		$current_theme = wp_get_theme();
		wp_enqueue_script( 'pwll-frontend-js', PWLL_URL . 'public/assets/js/frontend.js', array( 'jquery' ), PWLL_VERSION, true );

		eval( WPSCORE()->eval_product_data( 'PWLL', 'pwll_class_pwll_eval_3' ) );

		require_once PWLL_DIR . "dynamic-style.php";
	}

	/**
	 * Get the xbox options with default value from the option declaration default value.
	 *
	 * @param string $xbox_id The xbox id.
	 * @param string $field_id The field id.
	 * @return mixed The xbox option with the default value if not set.
	 */
	private function get_xbox_field_with_default( $xbox_id, $field_id ) {
		$options = xbox_get( $xbox_id );
		if ( ! isset( $options->fields[ $field_id ] ) ) {
			return '';
		}
		if ( ! isset( $options->fields[ $field_id ]['default'] ) ) {
			return '';
		}
		$default_value = $options->fields[ $field_id ]['default'];
		return xbox_get_field_value( 'pwll-options', $field_id, $default_value );
	}

	/**
	 * Stuff to do on admin init.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	private function admin_init() {}

	/**
	 * Stuff to do on public init.
	 *
	 * @since 1.0.0
	 *
	 * @access private
	 * @return void
	 */
	private function public_init() {}

	/**
	 * Check if current theme is a theme from WP-Script.
	 *
	 * @since 1.1.4
	 *
	 * @return bool true if current theme is a theme from WP-Script, false if not
	 */
	public static function is_wps_theme() {
		$current_theme = wp_get_theme();
		return 'WP-Script' === $current_theme->get( 'Author' );
	}

	/**
	 * Set the list of all terms to translate
	 *
	 * @since 1.0.0
	 *
	 * @return array List of all terms to translate
	 */
	public function get_object_l10n() {
		return array(
			'error_suppression'  => __( 'An error occured during the suppression:', 'pwll_lang' ),
			'select_wp_cat'      => __( 'Select a WP category', 'pwll_lang' ),
			'select_post_status' => __( 'Select Videos status', 'pwll_lang' ),
			'and'                => __( 'AND', 'pwll_lang' ),
			'insert_least'       => __( 'Insert at least 1 valid url in the textarea above', 'pwll_lang' ),
			'check_least'        => __( 'Check at least 1 video', 'pwll_lang' ),
			'enable_button'      => __( 'to enable this button', 'pwll_lang' ),
			'import'             => __( 'Import', 'pwll_lang' ),
			'search_feed'        => __( 'videos and save this search as a Feed. All your Feeds are displayed at the bottom of this page.', 'pwll_lang' ),
		);
	}
}

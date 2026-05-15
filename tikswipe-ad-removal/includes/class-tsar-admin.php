<?php
/**
 * Admin UI: requests dashboard, members, settings.
 *
 * @package TikSwipe_Ad_Removal
 */

defined( 'ABSPATH' ) || exit;

class TSAR_Admin {

	const MENU_SLUG     = 'tsar-requests';
	const MEMBERS_SLUG  = 'tsar-members';
	const SETTINGS_SLUG = 'tsar-settings';
	const CAP           = 'manage_options';

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'register_menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
		add_action( 'admin_post_tsar_approve', array( __CLASS__, 'handle_approve' ) );
		add_action( 'admin_post_tsar_reject', array( __CLASS__, 'handle_reject' ) );
		add_action( 'admin_post_tsar_member_update', array( __CLASS__, 'handle_member_update' ) );
		add_action( 'admin_post_tsar_member_revoke', array( __CLASS__, 'handle_member_revoke' ) );
		add_action( 'admin_notices', array( __CLASS__, 'flash_notices' ) );
		add_action( 'update_option_' . TSAR_OPTION_KEY, array( __CLASS__, 'maybe_flush_rewrites' ), 10, 2 );
	}

	public static function maybe_flush_rewrites( $old, $new ) {
		$old_slug = isset( $old['subscription_slug'] ) ? $old['subscription_slug'] : '';
		$new_slug = isset( $new['subscription_slug'] ) ? $new['subscription_slug'] : '';
		if ( $old_slug !== $new_slug ) {
			TSAR_Frontend::register_rewrite();
			flush_rewrite_rules();
		}
	}

	public static function register_menu() {
		$count = self::pending_count();
		$badge = $count ? ' <span class="awaiting-mod">' . (int) $count . '</span>' : '';

		add_menu_page(
			__( 'Ad Removal', 'tikswipe-ad-removal' ),
			__( 'Ad Removal', 'tikswipe-ad-removal' ) . $badge,
			self::CAP,
			self::MENU_SLUG,
			array( __CLASS__, 'render_requests_page' ),
			'dashicons-shield-alt',
			58
		);
		add_submenu_page(
			self::MENU_SLUG,
			__( 'Requests', 'tikswipe-ad-removal' ),
			__( 'Requests', 'tikswipe-ad-removal' ),
			self::CAP,
			self::MENU_SLUG,
			array( __CLASS__, 'render_requests_page' )
		);
		add_submenu_page(
			self::MENU_SLUG,
			__( 'Members', 'tikswipe-ad-removal' ),
			__( 'Members', 'tikswipe-ad-removal' ),
			self::CAP,
			self::MEMBERS_SLUG,
			array( __CLASS__, 'render_members_page' )
		);
		add_submenu_page(
			self::MENU_SLUG,
			__( 'Settings', 'tikswipe-ad-removal' ),
			__( 'Settings', 'tikswipe-ad-removal' ),
			self::CAP,
			self::SETTINGS_SLUG,
			array( __CLASS__, 'render_settings_page' )
		);
	}

	public static function enqueue_assets( $hook ) {
		if ( false === strpos( (string) $hook, 'tsar-' ) ) {
			return;
		}
		$css = TSAR_PLUGIN_DIR . 'assets/css/admin.css';
		$js  = TSAR_PLUGIN_DIR . 'assets/js/admin.js';

		wp_enqueue_style(
			'tsar-admin',
			TSAR_PLUGIN_URL . 'assets/css/admin.css',
			array(),
			file_exists( $css ) ? filemtime( $css ) : TSAR_VERSION
		);
		wp_enqueue_script(
			'tsar-admin',
			TSAR_PLUGIN_URL . 'assets/js/admin.js',
			array( 'jquery' ),
			file_exists( $js ) ? filemtime( $js ) : TSAR_VERSION,
			true
		);
	}

	public static function register_settings() {
		register_setting(
			'tsar_settings_group',
			TSAR_OPTION_KEY,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( __CLASS__, 'sanitize_settings' ),
				'default'           => tsar_default_settings(),
			)
		);
	}

	public static function sanitize_settings( $input ) {
		$defaults = tsar_default_settings();
		$out      = array();

		$out['enabled']           = ! empty( $input['enabled'] ) ? 1 : 0;
		$out['paypal_email']      = isset( $input['paypal_email'] ) ? sanitize_email( $input['paypal_email'] ) : '';
		$out['price_30days']      = isset( $input['price_30days'] ) ? number_format( (float) $input['price_30days'], 2, '.', '' ) : $defaults['price_30days'];
		$out['price_lifetime']    = isset( $input['price_lifetime'] ) ? number_format( (float) $input['price_lifetime'], 2, '.', '' ) : $defaults['price_lifetime'];
		$out['days_30days']       = isset( $input['days_30days'] ) ? max( 1, (int) $input['days_30days'] ) : $defaults['days_30days'];
		$out['currency']          = isset( $input['currency'] ) ? strtoupper( sanitize_text_field( $input['currency'] ) ) : $defaults['currency'];
		$out['currency_symbol']   = isset( $input['currency_symbol'] ) ? sanitize_text_field( $input['currency_symbol'] ) : $defaults['currency_symbol'];
		$out['subscription_slug'] = isset( $input['subscription_slug'] ) ? sanitize_title( $input['subscription_slug'] ) : $defaults['subscription_slug'];
		if ( '' === $out['subscription_slug'] ) {
			$out['subscription_slug'] = $defaults['subscription_slug'];
		}
		$out['review_window']     = isset( $input['review_window'] ) ? max( 60, (int) $input['review_window'] ) : $defaults['review_window'];
		$out['disclaimer']        = isset( $input['disclaimer'] ) ? wp_kses_post( $input['disclaimer'] ) : $defaults['disclaimer'];
		$out['thanks_text']       = isset( $input['thanks_text'] ) ? wp_kses_post( $input['thanks_text'] ) : $defaults['thanks_text'];
		$out['login_text']        = isset( $input['login_text'] ) ? wp_kses_post( $input['login_text'] ) : $defaults['login_text'];
		$out['rejected_text']     = isset( $input['rejected_text'] ) ? wp_kses_post( $input['rejected_text'] ) : $defaults['rejected_text'];

		return $out;
	}

	private static function pending_count() {
		global $wpdb;
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM " . tsar_table() . " WHERE status = 'pending'" );
	}

	/* ---------- Pages ---------- */

	public static function render_requests_page() {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( esc_html__( 'You do not have permission to view this page.', 'tikswipe-ad-removal' ) );
		}
		require_once TSAR_PLUGIN_DIR . 'includes/class-tsar-list-table.php';

		$table = new TSAR_Requests_List_Table();
		$table->prepare_items();
		?>
		<div class="wrap tsar-wrap-admin">
			<h1><?php esc_html_e( 'Ad Removal — Requests', 'tikswipe-ad-removal' ); ?></h1>
			<p class="description">
				<?php esc_html_e( 'Approve a request to grant ad-free time. Set days = 0 for lifetime.', 'tikswipe-ad-removal' ); ?>
			</p>
			<form method="get">
				<input type="hidden" name="page" value="<?php echo esc_attr( self::MENU_SLUG ); ?>">
				<?php $table->views(); ?>
				<?php $table->display(); ?>
			</form>
		</div>
		<?php
	}

	public static function render_members_page() {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( esc_html__( 'You do not have permission to view this page.', 'tikswipe-ad-removal' ) );
		}
		global $wpdb;
		$members = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT user_id, meta_value FROM {$wpdb->usermeta} WHERE meta_key = %s ORDER BY CAST(meta_value AS UNSIGNED) DESC",
				TSAR_USER_META
			)
		);
		?>
		<div class="wrap tsar-wrap-admin">
			<h1><?php esc_html_e( 'Ad Removal — Members', 'tikswipe-ad-removal' ); ?></h1>

			<h2 class="title"><?php esc_html_e( 'Grant ad-free to a user', 'tikswipe-ad-removal' ); ?></h2>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="tsar-grant-form">
				<input type="hidden" name="action" value="tsar_member_update">
				<?php wp_nonce_field( 'tsar_member_update' ); ?>
				<table class="form-table">
					<tr>
						<th><label for="tsar-grant-user"><?php esc_html_e( 'User', 'tikswipe-ad-removal' ); ?></label></th>
						<td>
							<input type="text" id="tsar-grant-user" name="user" class="regular-text" placeholder="<?php esc_attr_e( 'username, email, or user ID', 'tikswipe-ad-removal' ); ?>" required>
						</td>
					</tr>
					<tr>
						<th><label for="tsar-grant-days"><?php esc_html_e( 'Days', 'tikswipe-ad-removal' ); ?></label></th>
						<td>
							<input type="number" id="tsar-grant-days" name="days" min="0" value="30" class="small-text" required>
							<span class="description"><?php esc_html_e( '0 = lifetime. Days are added to existing remaining time.', 'tikswipe-ad-removal' ); ?></span>
						</td>
					</tr>
				</table>
				<?php submit_button( __( 'Grant', 'tikswipe-ad-removal' ) ); ?>
			</form>

			<h2 class="title"><?php esc_html_e( 'Active members', 'tikswipe-ad-removal' ); ?></h2>
			<?php if ( empty( $members ) ) : ?>
				<p><em><?php esc_html_e( 'No active members yet.', 'tikswipe-ad-removal' ); ?></em></p>
			<?php else : ?>
				<table class="widefat striped">
					<thead>
						<tr>
							<th><?php esc_html_e( 'User', 'tikswipe-ad-removal' ); ?></th>
							<th><?php esc_html_e( 'Email', 'tikswipe-ad-removal' ); ?></th>
							<th><?php esc_html_e( 'Status', 'tikswipe-ad-removal' ); ?></th>
							<th><?php esc_html_e( 'Actions', 'tikswipe-ad-removal' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $members as $row ) :
							$user = get_userdata( (int) $row->user_id );
							if ( ! $user ) {
								continue;
							}
							$is_lifetime = '0' === (string) $row->meta_value;
							?>
							<tr>
								<td>
									<a href="<?php echo esc_url( get_edit_user_link( $user->ID ) ); ?>"><?php echo esc_html( $user->user_login ); ?></a>
								</td>
								<td><?php echo esc_html( $user->user_email ); ?></td>
								<td>
									<?php if ( $is_lifetime ) : ?>
										<strong><?php esc_html_e( 'Lifetime', 'tikswipe-ad-removal' ); ?></strong>
									<?php else : ?>
										<?php
										printf(
											/* translators: %s: human readable date. */
											esc_html__( 'Until %s', 'tikswipe-ad-removal' ),
											esc_html( tsar_format_datetime( (int) $row->meta_value ) )
										);
										?>
									<?php endif; ?>
								</td>
								<td>
									<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline-block">
										<input type="hidden" name="action" value="tsar_member_update">
										<input type="hidden" name="user_id" value="<?php echo (int) $user->ID; ?>">
										<?php wp_nonce_field( 'tsar_member_update' ); ?>
										<input type="number" name="days" min="0" value="30" class="small-text">
										<button type="submit" class="button"><?php esc_html_e( 'Add', 'tikswipe-ad-removal' ); ?></button>
									</form>
									<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=tsar_member_revoke&user_id=' . (int) $user->ID ), 'tsar_member_revoke_' . (int) $user->ID ) ); ?>" class="button button-link-delete tsar-confirm" data-confirm="<?php esc_attr_e( 'Revoke ad-free for this user?', 'tikswipe-ad-removal' ); ?>"><?php esc_html_e( 'Revoke', 'tikswipe-ad-removal' ); ?></a>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</div>
		<?php
	}

	public static function render_settings_page() {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( esc_html__( 'You do not have permission to view this page.', 'tikswipe-ad-removal' ) );
		}
		$s = tsar_get_settings();
		?>
		<div class="wrap tsar-wrap-admin">
			<h1><?php esc_html_e( 'Ad Removal — Settings', 'tikswipe-ad-removal' ); ?></h1>
			<form method="post" action="options.php">
				<?php settings_fields( 'tsar_settings_group' ); ?>
				<table class="form-table">
					<tr>
						<th><label><?php esc_html_e( 'Enabled', 'tikswipe-ad-removal' ); ?></label></th>
						<td>
							<label>
								<input type="checkbox" name="<?php echo esc_attr( TSAR_OPTION_KEY ); ?>[enabled]" value="1" <?php checked( ! empty( $s['enabled'] ) ); ?>>
								<?php esc_html_e( 'Enable the ad removal flow (shortcode + suppression).', 'tikswipe-ad-removal' ); ?>
							</label>
						</td>
					</tr>
					<tr>
						<th><label for="tsar-paypal-email"><?php esc_html_e( 'PayPal email', 'tikswipe-ad-removal' ); ?></label></th>
						<td>
							<input type="email" id="tsar-paypal-email" name="<?php echo esc_attr( TSAR_OPTION_KEY ); ?>[paypal_email]" value="<?php echo esc_attr( $s['paypal_email'] ); ?>" class="regular-text" required>
							<p class="description"><?php esc_html_e( 'Where users will send the PayPal payment.', 'tikswipe-ad-removal' ); ?></p>
						</td>
					</tr>
					<tr>
						<th><label for="tsar-slug"><?php esc_html_e( 'Subscription page slug', 'tikswipe-ad-removal' ); ?></label></th>
						<td>
							<code><?php echo esc_html( trailingslashit( home_url( '/' ) ) ); ?></code>
							<input type="text" id="tsar-slug" name="<?php echo esc_attr( TSAR_OPTION_KEY ); ?>[subscription_slug]" value="<?php echo esc_attr( $s['subscription_slug'] ); ?>" class="regular-text">
							<p class="description"><?php esc_html_e( 'Public URL for the subscription page (default: subscription).', 'tikswipe-ad-removal' ); ?></p>
							<p><a class="button" href="<?php echo esc_url( tsar_subscription_url() ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Open page', 'tikswipe-ad-removal' ); ?></a></p>
						</td>
					</tr>
					<tr>
						<th><label for="tsar-review-window"><?php esc_html_e( 'Review window (seconds)', 'tikswipe-ad-removal' ); ?></label></th>
						<td>
							<input type="number" id="tsar-review-window" min="60" name="<?php echo esc_attr( TSAR_OPTION_KEY ); ?>[review_window]" value="<?php echo esc_attr( $s['review_window'] ); ?>" class="small-text">
							<p class="description"><?php esc_html_e( 'Countdown shown to users after they submit a request. Default 5400 = 1h30.', 'tikswipe-ad-removal' ); ?></p>
						</td>
					</tr>
					<tr>
						<th><label><?php esc_html_e( 'Currency', 'tikswipe-ad-removal' ); ?></label></th>
						<td>
							<input type="text" name="<?php echo esc_attr( TSAR_OPTION_KEY ); ?>[currency]" value="<?php echo esc_attr( $s['currency'] ); ?>" class="small-text" maxlength="6">
							<input type="text" name="<?php echo esc_attr( TSAR_OPTION_KEY ); ?>[currency_symbol]" value="<?php echo esc_attr( $s['currency_symbol'] ); ?>" class="small-text" maxlength="4">
							<p class="description"><?php esc_html_e( 'ISO code (USD) and symbol ($) shown to users.', 'tikswipe-ad-removal' ); ?></p>
						</td>
					</tr>
					<tr>
						<th><label><?php esc_html_e( '30 Days plan', 'tikswipe-ad-removal' ); ?></label></th>
						<td>
							<label>
								<?php esc_html_e( 'Price', 'tikswipe-ad-removal' ); ?>
								<input type="number" step="0.01" min="0" name="<?php echo esc_attr( TSAR_OPTION_KEY ); ?>[price_30days]" value="<?php echo esc_attr( $s['price_30days'] ); ?>" class="small-text">
							</label>
							&nbsp;&nbsp;
							<label>
								<?php esc_html_e( 'Days granted', 'tikswipe-ad-removal' ); ?>
								<input type="number" min="1" name="<?php echo esc_attr( TSAR_OPTION_KEY ); ?>[days_30days]" value="<?php echo esc_attr( $s['days_30days'] ); ?>" class="small-text">
							</label>
						</td>
					</tr>
					<tr>
						<th><label><?php esc_html_e( 'Lifetime plan', 'tikswipe-ad-removal' ); ?></label></th>
						<td>
							<label>
								<?php esc_html_e( 'Price', 'tikswipe-ad-removal' ); ?>
								<input type="number" step="0.01" min="0" name="<?php echo esc_attr( TSAR_OPTION_KEY ); ?>[price_lifetime]" value="<?php echo esc_attr( $s['price_lifetime'] ); ?>" class="small-text">
							</label>
						</td>
					</tr>
					<tr>
						<th><label for="tsar-disclaimer"><?php esc_html_e( 'Disclaimer', 'tikswipe-ad-removal' ); ?></label></th>
						<td>
							<textarea id="tsar-disclaimer" name="<?php echo esc_attr( TSAR_OPTION_KEY ); ?>[disclaimer]" rows="3" class="large-text"><?php echo esc_textarea( $s['disclaimer'] ); ?></textarea>
						</td>
					</tr>
					<tr>
						<th><label for="tsar-thanks"><?php esc_html_e( 'Thanks message', 'tikswipe-ad-removal' ); ?></label></th>
						<td>
							<textarea id="tsar-thanks" name="<?php echo esc_attr( TSAR_OPTION_KEY ); ?>[thanks_text]" rows="2" class="large-text"><?php echo esc_textarea( $s['thanks_text'] ); ?></textarea>
						</td>
					</tr>
					<tr>
						<th><label for="tsar-login"><?php esc_html_e( 'Login required text', 'tikswipe-ad-removal' ); ?></label></th>
						<td>
							<textarea id="tsar-login" name="<?php echo esc_attr( TSAR_OPTION_KEY ); ?>[login_text]" rows="2" class="large-text"><?php echo esc_textarea( $s['login_text'] ); ?></textarea>
						</td>
					</tr>
					<tr>
						<th><label for="tsar-rejected"><?php esc_html_e( 'Rejected message', 'tikswipe-ad-removal' ); ?></label></th>
						<td>
							<textarea id="tsar-rejected" name="<?php echo esc_attr( TSAR_OPTION_KEY ); ?>[rejected_text]" rows="2" class="large-text"><?php echo esc_textarea( $s['rejected_text'] ); ?></textarea>
							<p class="description"><?php esc_html_e( 'Shown to the user when their request is rejected.', 'tikswipe-ad-removal' ); ?></p>
						</td>
					</tr>
				</table>
				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}

	/* ---------- Action handlers ---------- */

	public static function handle_approve() {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( esc_html__( 'Forbidden', 'tikswipe-ad-removal' ) );
		}
		$id = isset( $_REQUEST['request_id'] ) ? (int) $_REQUEST['request_id'] : 0;
		check_admin_referer( 'tsar_approve_' . $id );

		$days = isset( $_REQUEST['days'] ) ? max( 0, (int) $_REQUEST['days'] ) : 0;

		global $wpdb;
		$table = tsar_table();
		$row   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ), ARRAY_A );
		if ( ! $row ) {
			self::redirect_back( 'error', __( 'Request not found.', 'tikswipe-ad-removal' ) );
		}
		if ( 'pending' !== $row['status'] ) {
			self::redirect_back( 'error', __( 'Request already processed.', 'tikswipe-ad-removal' ) );
		}

		$expires_ts = TSAR_Membership::grant( (int) $row['user_id'], $days );

		$wpdb->update(
			$table,
			array(
				'status'       => 'approved',
				'days_granted' => $days,
				'expires_at'   => 0 === $days ? null : (int) $expires_ts,
				'processed_at' => time(),
				'processed_by' => get_current_user_id(),
			),
			array( 'id' => $id ),
			array( '%s', '%d', '%d', '%d', '%d' ),
			array( '%d' )
		);

		self::redirect_back( 'success', sprintf(
			/* translators: 1: request ID, 2: days. */
			__( 'Request #%1$d approved (%2$s).', 'tikswipe-ad-removal' ),
			$id,
			0 === $days ? __( 'lifetime', 'tikswipe-ad-removal' ) : sprintf( _n( '%d day', '%d days', $days, 'tikswipe-ad-removal' ), $days )
		) );
	}

	public static function handle_reject() {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( esc_html__( 'Forbidden', 'tikswipe-ad-removal' ) );
		}
		$id = isset( $_REQUEST['request_id'] ) ? (int) $_REQUEST['request_id'] : 0;
		check_admin_referer( 'tsar_reject_' . $id );

		global $wpdb;
		$wpdb->update(
			tsar_table(),
			array(
				'status'       => 'rejected',
				'processed_at' => time(),
				'processed_by' => get_current_user_id(),
			),
			array( 'id' => $id ),
			array( '%s', '%d', '%d' ),
			array( '%d' )
		);

		self::redirect_back( 'success', sprintf( __( 'Request #%d rejected.', 'tikswipe-ad-removal' ), $id ) );
	}

	public static function handle_member_update() {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( esc_html__( 'Forbidden', 'tikswipe-ad-removal' ) );
		}
		check_admin_referer( 'tsar_member_update' );

		$days = isset( $_POST['days'] ) ? max( 0, (int) $_POST['days'] ) : 0;
		$user = null;

		if ( ! empty( $_POST['user_id'] ) ) {
			$user = get_userdata( (int) $_POST['user_id'] );
		} elseif ( ! empty( $_POST['user'] ) ) {
			$lookup = sanitize_text_field( wp_unslash( $_POST['user'] ) );
			if ( is_numeric( $lookup ) ) {
				$user = get_userdata( (int) $lookup );
			} else {
				$user = is_email( $lookup ) ? get_user_by( 'email', $lookup ) : get_user_by( 'login', $lookup );
			}
		}

		if ( ! $user ) {
			self::redirect_back( 'error', __( 'User not found.', 'tikswipe-ad-removal' ), self::MEMBERS_SLUG );
		}

		TSAR_Membership::grant( $user->ID, $days );
		self::redirect_back(
			'success',
			sprintf(
				/* translators: 1: user login, 2: days/lifetime. */
				__( 'Granted ad-free to %1$s (%2$s).', 'tikswipe-ad-removal' ),
				$user->user_login,
				0 === $days ? __( 'lifetime', 'tikswipe-ad-removal' ) : sprintf( _n( '%d day', '%d days', $days, 'tikswipe-ad-removal' ), $days )
			),
			self::MEMBERS_SLUG
		);
	}

	public static function handle_member_revoke() {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( esc_html__( 'Forbidden', 'tikswipe-ad-removal' ) );
		}
		$uid = isset( $_REQUEST['user_id'] ) ? (int) $_REQUEST['user_id'] : 0;
		check_admin_referer( 'tsar_member_revoke_' . $uid );

		TSAR_Membership::revoke( $uid );
		self::redirect_back( 'success', __( 'Membership revoked.', 'tikswipe-ad-removal' ), self::MEMBERS_SLUG );
	}

	private static function redirect_back( $type, $message, $slug = self::MENU_SLUG ) {
		set_transient( 'tsar_flash_' . get_current_user_id(), array( $type, $message ), 30 );
		wp_safe_redirect( admin_url( 'admin.php?page=' . $slug ) );
		exit;
	}

	public static function flash_notices() {
		$key   = 'tsar_flash_' . get_current_user_id();
		$flash = get_transient( $key );
		if ( ! $flash ) {
			return;
		}
		delete_transient( $key );
		list( $type, $message ) = $flash;
		$class = 'success' === $type ? 'notice-success' : 'notice-error';
		printf( '<div class="notice %s is-dismissible"><p>%s</p></div>', esc_attr( $class ), esc_html( $message ) );
	}
}

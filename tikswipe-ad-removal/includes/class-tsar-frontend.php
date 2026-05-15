<?php
/**
 * Frontend shortcode + asset enqueue for the payment-claim form.
 *
 * @package TikSwipe_Ad_Removal
 */

defined( 'ABSPATH' ) || exit;

class TSAR_Frontend {

	public static function init() {
		add_shortcode( 'tikswipe_remove_ads', array( __CLASS__, 'shortcode' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'register_assets' ) );
	}

	public static function register_assets() {
		$css = TSAR_PLUGIN_DIR . 'assets/css/frontend.css';
		$js  = TSAR_PLUGIN_DIR . 'assets/js/frontend.js';

		wp_register_style(
			'tsar-frontend',
			TSAR_PLUGIN_URL . 'assets/css/frontend.css',
			array(),
			file_exists( $css ) ? filemtime( $css ) : TSAR_VERSION
		);
		wp_register_script(
			'tsar-frontend',
			TSAR_PLUGIN_URL . 'assets/js/frontend.js',
			array( 'jquery' ),
			file_exists( $js ) ? filemtime( $js ) : TSAR_VERSION,
			true
		);
	}

	public static function shortcode( $atts ) {
		$settings = tsar_get_settings();

		if ( empty( $settings['enabled'] ) ) {
			return '';
		}

		wp_enqueue_style( 'tsar-frontend' );

		if ( ! is_user_logged_in() ) {
			return self::render_notice( esc_html( $settings['login_text'] ) );
		}

		wp_enqueue_script( 'tsar-frontend' );
		wp_localize_script(
			'tsar-frontend',
			'TSAR_Frontend',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'tsar_submit_request' ),
				'i18n'    => array(
					'submitting' => __( 'Submitting…', 'tikswipe-ad-removal' ),
					'genericErr' => __( 'Something went wrong. Please try again.', 'tikswipe-ad-removal' ),
				),
			)
		);

		$user_id = get_current_user_id();

		if ( TSAR_Membership::is_premium( $user_id ) ) {
			return self::render_status( $user_id, $settings );
		}

		$pending = self::get_pending_for_user( $user_id );

		ob_start();
		?>
		<div class="tsar-wrap">
			<?php if ( $pending ) : ?>
				<div class="tsar-notice tsar-notice-info">
					<?php esc_html_e( 'You have a pending payment request. Once we confirm your PayPal payment we will remove the ads from your account.', 'tikswipe-ad-removal' ); ?>
				</div>
			<?php endif; ?>

			<div class="tsar-paypal-info">
				<p>
					<strong><?php esc_html_e( 'Send PayPal payment to:', 'tikswipe-ad-removal' ); ?></strong>
					<code class="tsar-paypal-email"><?php echo esc_html( $settings['paypal_email'] ? $settings['paypal_email'] : __( '(not configured yet)', 'tikswipe-ad-removal' ) ); ?></code>
				</p>
				<p class="tsar-disclaimer"><?php echo esc_html( $settings['disclaimer'] ); ?></p>
			</div>

			<form class="tsar-form" id="tsar-form" data-tsar-form>
				<?php foreach ( tsar_plans() as $plan ) : ?>
					<label class="tsar-plan">
						<input type="radio" name="tsar_plan" value="<?php echo esc_attr( $plan['id'] ); ?>" required>
						<span class="tsar-plan-label"><?php echo esc_html( $plan['label'] ); ?></span>
						<span class="tsar-plan-price"><?php echo esc_html( tsar_format_price( $plan['price'] ) ); ?></span>
					</label>
				<?php endforeach; ?>

				<label class="tsar-field">
					<span><?php esc_html_e( 'PayPal email used (optional)', 'tikswipe-ad-removal' ); ?></span>
					<input type="email" name="tsar_paypal_email" placeholder="<?php echo esc_attr( wp_get_current_user()->user_email ); ?>">
				</label>

				<label class="tsar-field">
					<span><?php esc_html_e( 'Note / transaction reference (optional)', 'tikswipe-ad-removal' ); ?></span>
					<textarea name="tsar_note" rows="2" maxlength="500"></textarea>
				</label>

				<label class="tsar-field tsar-confirm">
					<input type="checkbox" name="tsar_confirm" value="1" required>
					<span><?php esc_html_e( 'I confirm I have sent the PayPal payment.', 'tikswipe-ad-removal' ); ?></span>
				</label>

				<button type="submit" class="tsar-submit"><?php esc_html_e( 'Submit Payment Request', 'tikswipe-ad-removal' ); ?></button>
				<div class="tsar-feedback" data-tsar-feedback aria-live="polite"></div>
			</form>
		</div>
		<?php
		return ob_get_clean();
	}

	private static function render_status( $user_id, $settings ) {
		$expires = TSAR_Membership::get_expiration( $user_id );
		ob_start();
		?>
		<div class="tsar-wrap tsar-active">
			<div class="tsar-notice tsar-notice-success">
				<strong><?php esc_html_e( 'Ad-Free is active on your account.', 'tikswipe-ad-removal' ); ?></strong>
				<p>
					<?php if ( '0' === (string) $expires || 0 === $expires ) : ?>
						<?php esc_html_e( 'Plan: Lifetime — enjoy ad-free browsing forever.', 'tikswipe-ad-removal' ); ?>
					<?php else : ?>
						<?php
						printf(
							/* translators: %s: human readable date. */
							esc_html__( 'Active until %s.', 'tikswipe-ad-removal' ),
							esc_html( tsar_format_datetime( $expires ) )
						);
						?>
					<?php endif; ?>
				</p>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}

	private static function render_notice( $message ) {
		return '<div class="tsar-wrap"><div class="tsar-notice">' . wp_kses_post( $message ) . '</div></div>';
	}

	public static function get_pending_for_user( $user_id ) {
		global $wpdb;
		$table = tsar_table();
		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} WHERE user_id = %d AND status = 'pending'",
				$user_id
			)
		);
	}
}

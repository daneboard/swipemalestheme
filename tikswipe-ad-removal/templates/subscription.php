<?php
/**
 * Virtual /subscription page template.
 *
 * Loaded from TSAR_Frontend::maybe_render(). Wraps content in get_header /
 * get_footer so it inherits the dark theme look from the active child theme.
 *
 * @package TikSwipe_Ad_Removal
 */

defined( 'ABSPATH' ) || exit;

get_header();

$settings = tsar_get_settings();
$user_id  = get_current_user_id();
$current  = $user_id ? wp_get_current_user() : null;

$is_premium     = $user_id ? TSAR_Membership::is_premium( $user_id ) : false;
$expires        = $user_id ? TSAR_Membership::get_expiration( $user_id ) : null;
$latest_request = $user_id ? tsar_get_latest_request_for_user( $user_id ) : null;
$has_pending    = $latest_request && 'pending' === $latest_request['status'];
$last_rejected  = $latest_request && 'rejected' === $latest_request['status'] && ! $is_premium && ! $has_pending;
?>

<main class="tsar-page">
	<div class="content-wrapper">
		<div class="tsar-card">

			<div class="tsar-header">
				<h1 class="tsar-title"><?php esc_html_e( 'Remove Ads', 'tikswipe-ad-removal' ); ?></h1>
				<p class="tsar-subtitle">
					<?php esc_html_e( 'Support the site and enjoy ad-free browsing.', 'tikswipe-ad-removal' ); ?>
				</p>
			</div>

			<?php if ( empty( $settings['enabled'] ) ) : ?>

				<div class="tsar-notice"><?php esc_html_e( 'Ad removal is currently disabled.', 'tikswipe-ad-removal' ); ?></div>

			<?php elseif ( ! $user_id ) : ?>

				<div class="tsar-notice tsar-notice-info">
					<?php echo wp_kses_post( $settings['login_text'] ); ?>
					<p>
						<a class="tsar-login-btn wpst-login" href="<?php echo esc_url( wp_login_url( tsar_subscription_url() ) ); ?>">
							<?php esc_html_e( 'Log in', 'tikswipe-ad-removal' ); ?>
						</a>
					</p>
				</div>

			<?php elseif ( $is_premium ) : ?>

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

			<?php else : ?>

				<?php if ( $has_pending ) : ?>
					<div class="tsar-state tsar-state-pending"
						data-tsar-state="pending"
						data-tsar-request-id="<?php echo (int) $latest_request['id']; ?>"
						data-tsar-created="<?php echo (int) $latest_request['created_at']; ?>">
						<div class="tsar-spinner" aria-hidden="true"></div>
						<h2 class="tsar-state-title"><?php echo esc_html( $settings['thanks_text'] ); ?></h2>
						<p class="tsar-state-desc"><?php esc_html_e( 'We are reviewing your payment.', 'tikswipe-ad-removal' ); ?></p>
						<div class="tsar-countdown" data-tsar-countdown>
							<span class="tsar-countdown-label"><?php esc_html_e( 'Estimated wait', 'tikswipe-ad-removal' ); ?></span>
							<span class="tsar-countdown-time" data-tsar-clock>—</span>
						</div>
					</div>
				<?php else : ?>

					<?php if ( $last_rejected ) : ?>
						<div class="tsar-notice tsar-notice-warn">
							<?php echo wp_kses_post( $settings['rejected_text'] ); ?>
						</div>
					<?php endif; ?>

					<?php
					$tsar_benefits = array(
						__( 'No ads anywhere', 'tikswipe-ad-removal' ),
						__( 'Faster video loading', 'tikswipe-ad-removal' ),
						__( 'Access to premium videos', 'tikswipe-ad-removal' ),
					);
					?>
					<ul class="tsar-benefits">
						<?php foreach ( $tsar_benefits as $tsar_benefit ) : ?>
							<li>
								<svg class="tsar-benefit-check" width="18" height="18" viewBox="0 0 24 24" aria-hidden="true">
									<circle cx="12" cy="12" r="11" fill="#fd0131"/>
									<path d="M7 12.5l3 3 7-7" fill="none" stroke="#fff" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"/>
								</svg>
								<span><?php echo esc_html( $tsar_benefit ); ?></span>
							</li>
						<?php endforeach; ?>
					</ul>

					<div class="tsar-paypal-info">
						<p>
							<strong><?php esc_html_e( 'Send PayPal payment to:', 'tikswipe-ad-removal' ); ?></strong>
						</p>
						<p>
							<code class="tsar-paypal-email">
								<?php echo esc_html( $settings['paypal_email'] ? $settings['paypal_email'] : __( '(not configured yet)', 'tikswipe-ad-removal' ) ); ?>
							</code>
						</p>
						<p class="tsar-disclaimer"><?php echo esc_html( $settings['disclaimer'] ); ?></p>
					</div>

					<form class="tsar-form" id="tsar-form" data-tsar-form>
						<div class="tsar-plans">
							<?php foreach ( tsar_plans() as $plan ) : ?>
								<label class="tsar-plan">
									<input type="radio" name="tsar_plan" value="<?php echo esc_attr( $plan['id'] ); ?>" required>
									<span class="tsar-plan-body">
										<span class="tsar-plan-label"><?php echo esc_html( $plan['label'] ); ?></span>
										<span class="tsar-plan-price"><?php echo esc_html( tsar_format_price( $plan['price'] ) ); ?></span>
									</span>
								</label>
							<?php endforeach; ?>
						</div>

						<label class="tsar-field">
							<span><?php esc_html_e( 'PayPal email used (optional)', 'tikswipe-ad-removal' ); ?></span>
							<input type="email" name="tsar_paypal_email" placeholder="<?php echo esc_attr( $current ? $current->user_email : '' ); ?>">
						</label>

						<label class="tsar-field tsar-confirm">
							<input type="checkbox" name="tsar_confirm" value="1" required>
							<span><?php esc_html_e( 'I confirm I have sent the PayPal payment.', 'tikswipe-ad-removal' ); ?></span>
						</label>

						<button type="submit" class="tsar-submit"><?php esc_html_e( 'Submit Payment Request', 'tikswipe-ad-removal' ); ?></button>
						<div class="tsar-feedback" data-tsar-feedback aria-live="polite"></div>
					</form>

				<?php endif; ?>

			<?php endif; ?>

		</div>
	</div>
</main>

<?php
get_footer();

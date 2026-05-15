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

$plans = tsar_plans();
?>

<main class="tsar-page">
	<div class="content-wrapper">
		<div class="tsar-card">

			<div class="tsar-hero">
				<div class="tsar-hero-glow" aria-hidden="true"></div>
				<div class="tsar-hero-icon" aria-hidden="true">
					<svg width="42" height="42" viewBox="0 0 24 24" fill="none">
						<path d="M12 2L4 5v6c0 5 3.5 9.5 8 11 4.5-1.5 8-6 8-11V5l-8-3z" fill="#fd0131"/>
						<path d="M8.5 12l2.5 2.5L16 9.5" stroke="#fff" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" fill="none"/>
					</svg>
				</div>
				<h1 class="tsar-title"><?php esc_html_e( 'Go Ad-Free', 'tikswipe-ad-removal' ); ?></h1>
				<p class="tsar-subtitle">
					<?php esc_html_e( 'Unlock the premium experience and support the site.', 'tikswipe-ad-removal' ); ?>
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
						array(
							'name' => __( 'Ad-free experience', 'tikswipe-ad-removal' ),
							'desc' => __( 'No banners, no pre-rolls, no interruptions.', 'tikswipe-ad-removal' ),
							'icon' => 'shield',
						),
						array(
							'name' => __( 'Faster videos', 'tikswipe-ad-removal' ),
							'desc' => __( 'Skip ad loading and play instantly.', 'tikswipe-ad-removal' ),
							'icon' => 'bolt',
						),
						array(
							'name' => __( 'Premium content', 'tikswipe-ad-removal' ),
							'desc' => __( 'Access to exclusive premium videos.', 'tikswipe-ad-removal' ),
							'icon' => 'star',
						),
					);
					?>
					<ul class="tsar-benefits">
						<?php foreach ( $tsar_benefits as $tsar_benefit ) : ?>
							<li class="tsar-benefit">
								<span class="tsar-benefit-icon" aria-hidden="true">
									<?php if ( 'shield' === $tsar_benefit['icon'] ) : ?>
										<svg width="20" height="20" viewBox="0 0 24 24" fill="none"><path d="M12 2L4 5v6c0 5 3.5 9.5 8 11 4.5-1.5 8-6 8-11V5l-8-3z" stroke="#fd0131" stroke-width="1.8" stroke-linejoin="round"/><path d="M8.5 12l2.5 2.5L16 9.5" stroke="#fd0131" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
									<?php elseif ( 'bolt' === $tsar_benefit['icon'] ) : ?>
										<svg width="20" height="20" viewBox="0 0 24 24" fill="none"><path d="M13 2L4 14h7l-1 8 9-12h-7l1-8z" fill="#fd0131"/></svg>
									<?php else : ?>
										<svg width="20" height="20" viewBox="0 0 24 24" fill="none"><path d="M12 2l3 6.5 7 1-5 5 1.2 7L12 18l-6.2 3.5L7 14.5l-5-5 7-1L12 2z" fill="#fd0131"/></svg>
									<?php endif; ?>
								</span>
								<span class="tsar-benefit-text">
									<span class="tsar-benefit-name"><?php echo esc_html( $tsar_benefit['name'] ); ?></span>
									<span class="tsar-benefit-desc"><?php echo esc_html( $tsar_benefit['desc'] ); ?></span>
								</span>
							</li>
						<?php endforeach; ?>
					</ul>

					<form class="tsar-form" id="tsar-form" data-tsar-form>

						<div class="tsar-section-label"><?php esc_html_e( 'Choose a plan', 'tikswipe-ad-removal' ); ?></div>
						<div class="tsar-plans">
							<?php foreach ( $plans as $plan_id => $plan ) :
								$is_lifetime = ( 'lifetime' === $plan_id );
								?>
								<label class="tsar-plan<?php echo $is_lifetime ? ' tsar-plan-best' : ''; ?>">
									<?php if ( $is_lifetime ) : ?>
										<span class="tsar-plan-badge"><?php esc_html_e( 'Best value', 'tikswipe-ad-removal' ); ?></span>
									<?php endif; ?>
									<input type="radio" name="tsar_plan" value="<?php echo esc_attr( $plan_id ); ?>" required <?php checked( $is_lifetime ); ?>>
									<span class="tsar-plan-radio" aria-hidden="true"></span>
									<span class="tsar-plan-info">
										<span class="tsar-plan-name"><?php echo esc_html( $plan['label'] ); ?></span>
										<span class="tsar-plan-meta">
											<?php
											if ( $is_lifetime ) {
												esc_html_e( 'One-time payment, forever ad-free', 'tikswipe-ad-removal' );
											} else {
												printf(
													/* translators: %d: number of days. */
													esc_html__( 'Renews manually after %d days', 'tikswipe-ad-removal' ),
													(int) $plan['days']
												);
											}
											?>
										</span>
									</span>
									<span class="tsar-plan-amount">
										<span class="tsar-plan-currency"><?php echo esc_html( $settings['currency_symbol'] ); ?></span><span class="tsar-plan-price"><?php echo esc_html( number_format( (float) $plan['price'], 2 ) ); ?></span>
									</span>
								</label>
							<?php endforeach; ?>
						</div>

						<div class="tsar-section-label"><?php esc_html_e( 'Payment', 'tikswipe-ad-removal' ); ?></div>
						<div class="tsar-paypal-card">
							<span class="tsar-paypal-icon" aria-hidden="true">
								<svg width="22" height="22" viewBox="0 0 24 24" fill="none"><path d="M19.5 7.5c.5 3-1.7 5.5-5 5.5h-2l-1 5.5h-3l2-12h6c1.6 0 2.7.7 3 1z" fill="#fd0131"/><path d="M16.5 5c.5 3-1.7 5.5-5 5.5h-2l-1 5.5h-3l2-12h6c1.6 0 2.7.7 3 1z" fill="#fff" opacity=".85"/></svg>
							</span>
							<div class="tsar-paypal-content">
								<span class="tsar-paypal-label"><?php esc_html_e( 'Send PayPal to', 'tikswipe-ad-removal' ); ?></span>
								<code class="tsar-paypal-email">
									<?php echo esc_html( $settings['paypal_email'] ? $settings['paypal_email'] : __( '(not configured yet)', 'tikswipe-ad-removal' ) ); ?>
								</code>
							</div>
						</div>
						<p class="tsar-disclaimer"><?php echo esc_html( $settings['disclaimer'] ); ?></p>

						<label class="tsar-field">
							<span><?php esc_html_e( 'PayPal email used (optional)', 'tikswipe-ad-removal' ); ?></span>
							<input type="email" name="tsar_paypal_email" placeholder="<?php echo esc_attr( $current ? $current->user_email : '' ); ?>">
						</label>

						<label class="tsar-field tsar-confirm">
							<input type="checkbox" name="tsar_confirm" value="1" required>
							<span><?php esc_html_e( 'I confirm I have sent the PayPal payment.', 'tikswipe-ad-removal' ); ?></span>
						</label>

						<button type="submit" class="tsar-submit">
							<span><?php esc_html_e( 'Submit Payment Request', 'tikswipe-ad-removal' ); ?></span>
							<svg width="18" height="18" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M5 12h14m0 0l-6-6m6 6l-6 6" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/></svg>
						</button>
						<div class="tsar-feedback" data-tsar-feedback aria-live="polite"></div>
					</form>

				<?php endif; ?>

			<?php endif; ?>

		</div>
	</div>
</main>

<?php
get_footer();

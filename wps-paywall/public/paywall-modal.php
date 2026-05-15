<?php
/**
 * Paywall unlock modal.
 *
 * Pricing data and the CTA URL come from the TikSwipe Ad Removal plugin so
 * users see the same plans / amounts they'll be charged on the
 * /subscription page. The modal is informational — clicking the CTA (or any
 * plan) sends the user to /subscription where the real flow happens. No
 * WooCommerce cart involvement here.
 */

defined( 'ABSPATH' ) || exit;

add_action( 'wp_footer', 'pwll_unlock_box' );
function pwll_unlock_box() {
	$has_tsar = function_exists( 'tsar_plans' ) && function_exists( 'tsar_get_settings' ) && function_exists( 'tsar_subscription_url' );

	$plans            = $has_tsar ? tsar_plans() : array();
	$settings         = $has_tsar ? tsar_get_settings() : array( 'currency_symbol' => '$' );
	$subscription_url = $has_tsar ? tsar_subscription_url() : home_url( '/subscription/' );

	$title       = xbox_get_field_value( 'pwll-options', 'pwll-paywall-popup-title', __( 'Premium Membership', 'pwll_lang' ) );
	$description = xbox_get_field_value( 'pwll-options', 'pwll-paywall-popup-description', __( 'Become a Premium Member and Get Access to All our Exclusive Videos', 'pwll_lang' ) );
	// Mirror the on-thumbnail overlay label so the modal CTA reads the same word.
	$cta_text    = xbox_get_field_value( 'pwll-options', 'pwll-locked-content-area-text', __( 'Unlock Video', 'pwll_lang' ) );
	?>
	<div class="pwll-modal-bg"></div>
	<div class="pwll-unlock-box">
		<button class="pwll-close-modal" aria-label="<?php esc_attr_e( 'Close', 'pwll_lang' ); ?>"><svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24"><path fill="#ffffff" d="M23 20.168l-8.185-8.187 8.185-8.174-2.832-2.807-8.182 8.179-8.176-8.179-2.81 2.81 8.186 8.196-8.186 8.184 2.81 2.81 8.203-8.192 8.18 8.192z"/></svg></button>

		<div class="pwll-header">
			<h2><?php echo esc_html( $title ); ?></h2>
		</div>

		<div class="pwll-content">

			<p class="membership-desc"><?php echo esc_html( $description ); ?></p>

			<?php if ( ! empty( $plans ) ) : ?>
				<div class="subscription-row">
					<?php foreach ( $plans as $plan_id => $plan ) :
						$is_lifetime = ( 'lifetime' === $plan_id );
						$href        = add_query_arg( 'plan', rawurlencode( $plan_id ), $subscription_url );
						?>
						<a class="pwll-pricing-plan<?php echo $is_lifetime ? ' pwll-pricing-plan-best' : ''; ?>" href="<?php echo esc_url( $href ); ?>" data-plan="<?php echo esc_attr( $plan_id ); ?>">
							<?php if ( $is_lifetime ) : ?>
								<span class="pwll-best-badge"><?php esc_html_e( 'Best value', 'pwll_lang' ); ?></span>
							<?php endif; ?>
							<div class="pwll-plan-title"><?php echo esc_html( $plan['label'] ); ?></div>
							<div class="pwll-plan-description">
								<?php
								if ( $is_lifetime ) {
									esc_html_e( 'One-time payment, forever ad-free.', 'pwll_lang' );
								} else {
									printf(
										/* translators: %d: number of days. */
										esc_html__( 'Ad-free access for %d days.', 'pwll_lang' ),
										(int) $plan['days']
									);
								}
								?>
							</div>
							<div class="pwll-price">
								<span class="pwll-price-currency"><?php echo esc_html( $settings['currency_symbol'] ); ?></span><span class="pwll-price-amount"><?php echo esc_html( number_format( (float) $plan['price'], 2 ) ); ?></span>
							</div>
						</a>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>

			<a class="pwll-button start-membership-button" href="<?php echo esc_url( $subscription_url ); ?>"><?php echo esc_html( $cta_text ); ?></a>
		</div>
	</div>
	<?php
}

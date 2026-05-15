<?php
add_action( 'wp_footer', 'pwll_unlock_box' );
function pwll_unlock_box() {
	?>
	<div class="pwll-modal-bg"></div>
	<div class="pwll-unlock-box">
		<button class="pwll-close-modal"><svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24"><path fill="#ffffff" d="M23 20.168l-8.185-8.187 8.185-8.174-2.832-2.807-8.182 8.179-8.176-8.179-2.81 2.81 8.186 8.196-8.186 8.184 2.81 2.81 8.203-8.192 8.18 8.192z"/></svg></button>

		<div class="pwll-loading"><svg class="pwll-spinner" viewBox="0 0 50 50"><circle class="path" cx="25" cy="25" r="20" fill="none" stroke-width="5"></circle></svg></div>
		<div class="pwll-header">
			<h2><?php echo esc_html( xbox_get_field_value( 'pwll-options', 'pwll-paywall-popup-title', 'Premium Membership' ) ); ?></h2>
		</div>

		<div class="pwll-content">

			<p class="membership-desc"><?php echo esc_html( xbox_get_field_value( 'pwll-options', 'pwll-paywall-popup-description', 'Become a Premium Member and Get Access to All our Exclusive Videos' ) ); ?></p>

			<?php

			if ( class_exists( 'WC_Subscriptions', false ) ) {

				// 1 MONTH
				$one_month_enabled           = xbox_get_field_value( 'pwll-options', 'pwll-enable-1-month', 'on' );
				$one_month_price             = xbox_get_field_value( 'pwll-options', 'pwll-1-month-price', 9 );
				$one_month_sign_up_fee       = xbox_get_field_value( 'pwll-options', 'pwll-1-month-sign-up-fee', 0 );
				$one_month_enable_free_trial = xbox_get_field_value( 'pwll-options', 'pwll-1-month-free-trial', 'off' );
				$one_month_free_trial_length = xbox_get_field_value( 'pwll-options', 'pwll-1-month-free-trial-length', 1 );
				$one_month_free_trial_period = xbox_get_field_value( 'pwll-options', 'pwll-1-month-free-trial-period', 'day' );

				// 3 MONTHS
				$three_months_enabled           = xbox_get_field_value( 'pwll-options', 'pwll-enable-3-months', 'off' );
				$three_months_price             = xbox_get_field_value( 'pwll-options', 'pwll-3-months-price', 19 );
				$three_months_sign_up_fee       = xbox_get_field_value( 'pwll-options', 'pwll-3-months-sign-up-fee', 0 );
				$three_months_enable_free_trial = xbox_get_field_value( 'pwll-options', 'pwll-3-months-free-trial', 'off' );
				$three_months_free_trial_length = xbox_get_field_value( 'pwll-options', 'pwll-3-months-free-trial-length', 1 );
				$three_months_free_trial_period = xbox_get_field_value( 'pwll-options', 'pwll-3-months-free-trial-period', 'day' );

				// 6 MONTHS
				$six_months_enabled           = xbox_get_field_value( 'pwll-options', 'pwll-enable-6-months', 'off' );
				$six_months_price             = xbox_get_field_value( 'pwll-options', 'pwll-6-months-price', 29 );
				$six_months_sign_up_fee       = xbox_get_field_value( 'pwll-options', 'pwll-6-months-sign-up-fee', 0 );
				$six_months_enable_free_trial = xbox_get_field_value( 'pwll-options', 'pwll-6-months-free-trial', 'off' );
				$six_months_free_trial_length = xbox_get_field_value( 'pwll-options', 'pwll-6-months-free-trial-length', 1 );
				$six_months_free_trial_period = xbox_get_field_value( 'pwll-options', 'pwll-6-months-free-trial-period', 'day' );

				// 12 MONTHS
				$yearly_enabled           = xbox_get_field_value( 'pwll-options', 'pwll-enable-12-months', 'on' );
				$yearly_price             = xbox_get_field_value( 'pwll-options', 'pwll-12-months-price', 49 );
				$yearly_sign_up_fee       = xbox_get_field_value( 'pwll-options', 'pwll-12-months-sign-up-fee', 0 );
				$yearly_enable_free_trial = xbox_get_field_value( 'pwll-options', 'pwll-12-months-free-trial', 'off' );
				$yearly_free_trial_length = xbox_get_field_value( 'pwll-options', 'pwll-12-months-free-trial-length', 1 );
				$yearly_free_trial_period = xbox_get_field_value( 'pwll-options', 'pwll-12-months-free-trial-period', 'day' );

				$subscription_id = wc_get_product_id_by_sku( 'premium-membership-subscription' );

				if ( isset( $subscription_id ) && $subscription_id !== 0 ) :

					$one_month_variation_id = wc_get_product_id_by_sku( 'premium-membership-1-month' );
					$one_month_product      = wc_get_product( $one_month_variation_id );
					if ( isset( $one_month_price ) && ! empty( $one_month_price ) ) {
						$per_month_price = $one_month_price;
					} else {
						$per_month_price = $one_month_product->get_price();
					}

					$three_months_variation_id = wc_get_product_id_by_sku( 'premium-membership-3-months' );
					$three_months_product      = wc_get_product( $three_months_variation_id );
					if ( isset( $three_months_price ) && ! empty( $three_months_price ) ) {
						$per_three_months_price = $three_months_price;
					} else {
						$per_three_months_price = $three_months_product->get_price();
					}
					$per_three_months_price_per_month = $per_three_months_price / 3;

					$six_months_variation_id = wc_get_product_id_by_sku( 'premium-membership-6-months' );
					$six_months_product      = wc_get_product( $six_months_variation_id );
					if ( isset( $six_months_price ) && ! empty( $six_months_price ) ) {
						$per_six_months_price = $six_months_price;
					} else {
						$per_six_months_price = $six_months_product->get_price();
					}
					$per_six_months_price_per_month = $per_six_months_price / 6;

					$one_year_variation_id = wc_get_product_id_by_sku( 'premium-membership-12-months' );
					$one_year_product      = wc_get_product( $one_year_variation_id );
					if ( isset( $yearly_price ) && ! empty( $yearly_price ) ) {
						$per_year_price = $yearly_price;
					} else {
						$per_year_price = $one_year_product->get_price();
					}
					$per_year_price_per_month = $per_year_price / 12;

					// DISCOUNT CALCUL
					// $monthly_variation_year = round( $per_month_price, 2 ) * 12;
					// $percent_discount_inverse = ( $per_year_price * 100 ) / $monthly_variation_year;
					// $percent_discount = floor( 100 - $percent_discount_inverse );

					?>

					<?php if ( $yearly_enabled === 'on' || $six_months_enabled === 'on' || $three_months_enabled === 'on' || $one_month_enabled === 'on' ) : ?>
						<div class="subscription-row">
							<?php // PLAN 1 YEAR ?>
							<?php if ( $yearly_enabled === 'on' ) : ?>
								<div data-product-id="<?php echo $subscription_id; ?>" data-variation-id="<?php echo $one_year_variation_id; ?>" class="pwll-pricing-plan">
									<?php /* <div class="pwll-most-popular">Most popular</div> */ ?>
									<div class="pwll-plan-title"><?php _e( '12 months', 'pwll_lang' ); ?></div>
									<div class="pwll-price"><?php echo str_replace( '.00', '', wc_price( $per_year_price_per_month ) ); ?><span class="per-month"><?php echo esc_html( '/month', 'pwll_lang' ); ?></span></div>
									<div class="pwll-plan-description"><?php echo __( sprintf( __( 'Billed in one payment of %s every year until cancelled.', 'pwll_lang' ), str_replace( '.00', '', wc_price( $per_year_price ) ) ) ); ?></div>
									<?php if ( $yearly_enable_free_trial === 'on' || $yearly_sign_up_fee > 0 ) : ?>
										<div class="pwll-bottom-plan">
											<?php if ( $yearly_enable_free_trial === 'on' ) : ?>
												<div class="pwll-free-trial"><?php echo $yearly_free_trial_length; ?>-<?php echo $yearly_free_trial_period; ?> <?php echo esc_html( 'Free Trial', 'pwll_lang' ); ?></div>
											<?php endif; ?>
											<?php if ( $yearly_sign_up_fee > 0 ) : ?>
												<div class="pwll-sign-up-fee"><?php echo get_woocommerce_currency_symbol(); ?><?php echo $yearly_sign_up_fee; ?> <?php echo esc_html( 'Sign-up Fee', 'pwll_lang' ); ?></div>
											<?php endif; ?>
										</div>
									<?php endif; ?>
									<?php
									/*
									if( $percent_discount > 0 && true === get_theme_mod( 'pwll_pricing_subscription_1_month', true ) ) : ?>
										<div class="pwll-plan-discount"><?php esc_html_e( 'Save', 'pwll_lang' ); ?> <?php echo $percent_discount; ?>%</div>
									<?php endif; */
									?>
								</div>
							<?php endif; ?>

							<?php // PLAN 6 MONTHS ?>
							<?php if ( $six_months_enabled === 'on' ) : ?>
								<div data-product-id="<?php echo $subscription_id; ?>" data-variation-id="<?php echo $six_months_variation_id; ?>" class="pwll-pricing-plan">
									<div class="pwll-plan-title"><?php _e( '6 months', 'pwll_lang' ); ?></div>
									<div class="pwll-price"><?php echo wc_price( $per_six_months_price_per_month ); ?><span class="per-month"><?php echo esc_html( '/month', 'pwll_lang' ); ?></span></div>
									<div class="pwll-plan-description"><?php echo __( sprintf( __( 'Billed in one payment of %s every 6 months until cancelled.', 'pwll_lang' ), str_replace( '.00', '', wc_price( $per_six_months_price ) ) ) ); ?></div>
									<?php if ( $six_months_enable_free_trial === 'on' || $six_months_sign_up_fee > 0 ) : ?>
										<div class="pwll-bottom-plan">
											<?php if ( $six_months_enable_free_trial === 'on' ) : ?>
												<div class="pwll-free-trial"><?php echo $six_months_free_trial_length; ?>-<?php echo $six_months_free_trial_period; ?> <?php echo esc_html( 'Free Trial', 'pwll_lang' ); ?></div>
											<?php endif; ?>
											<?php if ( $six_months_sign_up_fee > 0 ) : ?>
												<div class="pwll-sign-up-fee"><?php echo get_woocommerce_currency_symbol(); ?><?php echo $six_months_sign_up_fee; ?> <?php echo esc_html( 'Sign-up Fee', 'pwll_lang' ); ?></div>
											<?php endif; ?>
										</div>
									<?php endif; ?>
								</div>
							<?php endif; ?>

							<?php // PLAN 3 MONTHS ?>
							<?php if ( $three_months_enabled === 'on' ) : ?>
								<div data-product-id="<?php echo $subscription_id; ?>" data-variation-id="<?php echo $three_months_variation_id; ?>" class="pwll-pricing-plan">
									<div class="pwll-plan-title"><?php _e( '3 months', 'pwll_lang' ); ?></div>
									<div class="pwll-price"><?php echo wc_price( $per_three_months_price_per_month ); ?><span class="per-month"><?php echo esc_html( '/month', 'pwll_lang' ); ?></span></div>
									<div class="pwll-plan-description"><?php echo __( sprintf( __( 'Billed in one payment of %s every 3 months until cancelled.', 'pwll_lang' ), str_replace( '.00', '', wc_price( $per_three_months_price ) ) ) ); ?></div>
									<?php if ( $three_months_enable_free_trial === 'on' || $three_months_sign_up_fee > 0 ) : ?>
										<div class="pwll-bottom-plan">
											<?php if ( $three_months_enable_free_trial === 'on' ) : ?>
												<div class="pwll-free-trial"><?php echo $three_months_free_trial_length; ?>-<?php echo $three_months_free_trial_period; ?> <?php echo esc_html( 'Free Trial', 'pwll_lang' ); ?></div>
											<?php endif; ?>
											<?php if ( $three_months_sign_up_fee > 0 ) : ?>
												<div class="pwll-sign-up-fee"><?php echo get_woocommerce_currency_symbol(); ?><?php echo $three_months_sign_up_fee; ?> <?php echo esc_html( 'Sign-up Fee', 'pwll_lang' ); ?></div>
											<?php endif; ?>
										</div>
									<?php endif; ?>
								</div>
							<?php endif; ?>

							<?php // PLAN 1 MONTH ?>
							<?php if ( $one_month_enabled === 'on' ) : ?>
								<div data-product-id="<?php echo $subscription_id; ?>" data-variation-id="<?php echo $one_month_variation_id; ?>" class="pwll-pricing-plan">
									<div class="pwll-plan-title"><?php _e( '1 month', 'pwll_lang' ); ?></div>
									<div class="pwll-price"><?php echo wc_price( $per_month_price ); ?><span class="per-month">/<?php echo esc_html( 'month', 'pwll_lang' ); ?></span></div>
									<div class="pwll-plan-description"><?php echo __( sprintf( __( 'Billed in one payment of %s every month until cancelled.', 'pwll_lang' ), str_replace( '.00', '', wc_price( $per_month_price ) ) ) ); ?></div>
									<?php if ( $one_month_enable_free_trial === 'on' || $one_month_sign_up_fee > 0 ) : ?>
										<div class="pwll-bottom-plan">
											<?php if ( $one_month_enable_free_trial === 'on' ) : ?>
												<div class="pwll-free-trial"><?php echo $one_month_free_trial_length; ?>-<?php echo $one_month_free_trial_period; ?> <?php echo esc_html( 'Free Trial', 'pwll_lang' ); ?></div>
											<?php endif; ?>
											<?php if ( $one_month_sign_up_fee > 0 ) : ?>
												<div class="pwll-sign-up-fee"><?php echo get_woocommerce_currency_symbol(); ?><?php echo $one_month_sign_up_fee; ?> <?php echo esc_html( 'Sign-up Fee', 'pwll_lang' ); ?></div>
											<?php endif; ?>
										</div>
									<?php endif; ?>
								</div>
							<?php endif; ?>

						</div>
					<?php endif; ?>
					<?php
				endif;
			}
			// LIFETIME
			$lifetime_enabled = xbox_get_field_value( 'pwll-options', 'pwll-enable-lifetime', 'on' );
			$lifetime_price   = xbox_get_field_value( 'pwll-options', 'pwll-lifetime-price', 99 );
			$lifetime_id      = wc_get_product_id_by_sku( 'premium-membership-lifetime' );
			if ( isset( $lifetime_id ) && $lifetime_id !== 0 && $lifetime_enabled == 'on' ) :
				$lifetime_product = wc_get_product( $lifetime_id );
				if ( isset( $lifetime_price ) && ! empty( $lifetime_price ) ) {
				} else {
					$lifetime_price = $lifetime_product->get_price();
				}
				?>
				<div class="lifetime-row">
					<div data-product-id="<?php echo $lifetime_id; ?>" class="pwll-pricing-plan pwll-pricing-plan-best">
						<span class="pwll-best-badge"><?php esc_html_e( 'Best value', 'pwll_lang' ); ?></span>
						<div class="pwll-plan-title"><?php echo esc_html( 'Lifetime membership', 'pwll_lang' ); ?></div>
						<div class="pwll-price"><?php echo wc_price( $lifetime_price ); ?></div>
						<div class="pwll-plan-description"><?php echo esc_html( 'Pay only one time. No subscription.', 'pwll_lang' ); ?></div>
					</div>
				</div>
			<?php endif; ?>

			<a class="pwll-button start-membership-button" href="<?php echo wc_get_checkout_url(); ?>"><?php echo esc_html( xbox_get_field_value( 'pwll-options', 'pwll-paywall-popup-button-text', 'Start Membership' ) ); ?></a>
		</div>
	</div>
	<?php
}

<?php
/**
 * Settings page — selection strategy + recency window + close weight.
 *
 * Registers options used by tss_pick_winner() in tss-helpers.php.
 *
 * @package TikSwipe_Shop
 */

defined( 'ABSPATH' ) || exit;

class TSS_Settings {

	const GROUP    = 'tss_settings_group';
	const PAGE     = 'tss_settings';
	const STRATEGIES = array( 'thompson', 'weighted_ctr', 'random', 'recent' );

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'add_page' ) );
		add_action( 'admin_init', array( __CLASS__, 'register' ) );
	}

	public static function add_page() {
		add_submenu_page(
			'edit.php?post_type=' . TSS_CPT,
			__( 'Settings', 'tikswipe-shop' ),
			__( 'Settings', 'tikswipe-shop' ),
			'manage_options',
			self::PAGE,
			array( __CLASS__, 'render' )
		);
	}

	public static function register() {
		register_setting(
			self::GROUP,
			'tss_strategy',
			array(
				'type'              => 'string',
				'sanitize_callback' => array( __CLASS__, 'sanitize_strategy' ),
				'default'           => 'thompson',
			)
		);
		register_setting(
			self::GROUP,
			'tss_window_days',
			array(
				'type'              => 'integer',
				'sanitize_callback' => array( __CLASS__, 'sanitize_window' ),
				'default'           => 30,
			)
		);
		register_setting(
			self::GROUP,
			'tss_close_weight',
			array(
				'type'              => 'number',
				'sanitize_callback' => array( __CLASS__, 'sanitize_float' ),
				'default'           => 1.5,
			)
		);
		register_setting(
			self::GROUP,
			'tss_cooldown_slides',
			array(
				'type'              => 'integer',
				'sanitize_callback' => array( __CLASS__, 'sanitize_cooldown' ),
				'default'           => 1,
			)
		);
	}

	public static function sanitize_cooldown( $v ) {
		$v = (int) $v;
		if ( $v < 0 ) {
			$v = 0;
		}
		if ( $v > 20 ) {
			$v = 20;
		}
		return $v;
	}

	public static function sanitize_strategy( $v ) {
		return in_array( $v, self::STRATEGIES, true ) ? $v : 'thompson';
	}

	public static function sanitize_window( $v ) {
		$v = (int) $v;
		if ( $v < 0 ) {
			$v = 0;
		}
		if ( $v > 365 ) {
			$v = 365;
		}
		return $v;
	}

	public static function sanitize_float( $v ) {
		$v = (float) $v;
		if ( $v < 0 ) {
			$v = 0;
		}
		if ( $v > 10 ) {
			$v = 10;
		}
		return $v;
	}

	public static function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to view this page.', 'tikswipe-shop' ) );
		}

		$strategy     = get_option( 'tss_strategy', 'thompson' );
		$window_days  = (int) get_option( 'tss_window_days', 30 );
		$close_weight = (float) get_option( 'tss_close_weight', 1.5 );
		$cooldown     = (int) get_option( 'tss_cooldown_slides', 1 );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'TikSwipe Shop — Settings', 'tikswipe-shop' ); ?></h1>
			<p class="description" style="max-width:680px;">
				<?php esc_html_e( 'These rules only matter when MORE THAN ONE product targets the same post. Explicit "Target posts" matches always beat category matches; the chosen strategy then decides which item wins WITHIN that tier.', 'tikswipe-shop' ); ?>
			</p>

			<form method="post" action="options.php">
				<?php settings_fields( self::GROUP ); ?>

				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'Selection strategy', 'tikswipe-shop' ); ?></th>
						<td>
							<fieldset class="tss-strategy-fieldset">
								<label>
									<input type="radio" name="tss_strategy" value="thompson" <?php checked( 'thompson', $strategy ); ?>>
									<strong><?php esc_html_e( 'Thompson sampling', 'tikswipe-shop' ); ?></strong>
									<?php esc_html_e( '(recommended)', 'tikswipe-shop' ); ?>
									<br>
									<span class="description">
										<?php esc_html_e( 'Models each product\'s click-through rate as a probability distribution and draws a random sample from each. The product with the highest sample wins. New products explore aggressively at first; high-CTR products take over as data accumulates; products with many manual closes get penalised. Self-tuning — no knobs.', 'tikswipe-shop' ); ?>
									</span>
								</label>
								<br>

								<label>
									<input type="radio" name="tss_strategy" value="weighted_ctr" <?php checked( 'weighted_ctr', $strategy ); ?>>
									<strong><?php esc_html_e( 'Weighted by CTR', 'tikswipe-shop' ); ?></strong>
									<br>
									<span class="description">
										<?php esc_html_e( 'Each product is picked with probability proportional to its smoothed click-through rate. Less exploration than Thompson; favours products with proven CTR more aggressively.', 'tikswipe-shop' ); ?>
									</span>
								</label>
								<br>

								<label>
									<input type="radio" name="tss_strategy" value="random" <?php checked( 'random', $strategy ); ?>>
									<strong><?php esc_html_e( 'Pure random', 'tikswipe-shop' ); ?></strong>
									<br>
									<span class="description">
										<?php esc_html_e( 'Uniform random pick. Ignores performance data entirely. Use when you want every product to get equal exposure (e.g. A/B testing a new product).', 'tikswipe-shop' ); ?>
									</span>
								</label>
								<br>

								<label>
									<input type="radio" name="tss_strategy" value="recent" <?php checked( 'recent', $strategy ); ?>>
									<strong><?php esc_html_e( 'Most recently published', 'tikswipe-shop' ); ?></strong>
									<?php esc_html_e( '(legacy)', 'tikswipe-shop' ); ?>
									<br>
									<span class="description">
										<?php esc_html_e( 'Always picks the newest product (the original behaviour before this update). Deterministic — the same product wins every time.', 'tikswipe-shop' ); ?>
									</span>
								</label>
							</fieldset>
						</td>
					</tr>

					<tr>
						<th scope="row"><label for="tss_window_days"><?php esc_html_e( 'Recency window (days)', 'tikswipe-shop' ); ?></label></th>
						<td>
							<input type="number" id="tss_window_days" name="tss_window_days" value="<?php echo esc_attr( $window_days ); ?>" class="small-text" min="0" max="365" step="1">
							<p class="description">
								<?php esc_html_e( 'Only events from this window feed the selection math. A product with no events in the window falls back to its all-time totals so it can still compete. Set to 0 to always use all-time data. Applies to Thompson and Weighted-CTR.', 'tikswipe-shop' ); ?>
							</p>
						</td>
					</tr>

					<tr>
						<th scope="row"><label for="tss_close_weight"><?php esc_html_e( 'Close weight', 'tikswipe-shop' ); ?></label></th>
						<td>
							<input type="number" id="tss_close_weight" name="tss_close_weight" value="<?php echo esc_attr( $close_weight ); ?>" class="small-text" min="0" max="10" step="0.1">
							<p class="description">
								<?php esc_html_e( 'How strongly an explicit close counts as negative signal. 1.0 = same weight as a passive ignore; 2.0 = a close hurts twice as much as a non-click. Defaults to 1.5. Applies to Thompson and Weighted-CTR.', 'tikswipe-shop' ); ?>
							</p>
						</td>
					</tr>

					<tr>
						<th scope="row"><label for="tss_cooldown_slides"><?php esc_html_e( 'Cooldown after close', 'tikswipe-shop' ); ?></label></th>
						<td>
							<input type="number" id="tss_cooldown_slides" name="tss_cooldown_slides" value="<?php echo esc_attr( $cooldown ); ?>" class="small-text" min="0" max="20" step="1">
							<?php esc_html_e( 'slides', 'tikswipe-shop' ); ?>
							<p class="description">
								<?php esc_html_e( 'After a visitor manually closes a card, skip showing any card on the next N slides — a breathing room so ads do not feel pushy. 1 = the very next video is ad-free; 2 = next two are; 0 = no cooldown. Resets if another close happens during the cooldown.', 'tikswipe-shop' ); ?>
							</p>
						</td>
					</tr>
				</table>

				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}
}

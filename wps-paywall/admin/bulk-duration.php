<?php
/**
 * Bulk apply / remove the paywall on every video matching a duration
 * window. The `duration` post-meta is written in seconds by the theme's
 * "TikSwipe post details" metabox; this tool simply flips the
 * `pwll_post_status` meta to `premium` (or deletes it) for posts whose
 * duration falls in the requested range.
 *
 * Lives under WPS Paywall → "Bulk by duration".
 *
 * @package PWLL\Admin
 */

defined( 'ABSPATH' ) || exit;

class PWLL_Bulk_Duration {

	const MENU_SLUG  = 'pwll-bulk-duration';
	const NONCE_NAME = '_pwll_bulk_duration';
	const NONCE_ACT  = 'pwll_bulk_duration';

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'register_menu' ), 20 );
		add_action( 'admin_post_pwll_bulk_duration', array( __CLASS__, 'handle_submit' ) );
	}

	public static function register_menu() {
		add_submenu_page(
			'pwll-options',
			esc_html__( 'Bulk Paywall by Duration', 'pwll_lang' ),
			esc_html__( 'Bulk by duration', 'pwll_lang' ),
			'manage_options',
			self::MENU_SLUG,
			array( __CLASS__, 'render_page' )
		);
	}

	public static function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'pwll_lang' ) );
		}

		$last = get_transient( 'pwll_bulk_duration_last' );
		delete_transient( 'pwll_bulk_duration_last' );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Bulk Paywall by Duration', 'pwll_lang' ); ?></h1>
			<p>
				<?php esc_html_e( 'Mark or unmark videos as premium based on their "Video duration" value (in seconds), set in the TikSwipe post details metabox. Leave a field blank to make that bound open.', 'pwll_lang' ); ?>
			</p>

			<?php if ( is_array( $last ) ) : ?>
				<div class="notice notice-<?php echo 'apply' === $last['action'] ? 'success' : 'info'; ?> is-dismissible">
					<p>
						<?php
						if ( 'apply' === $last['action'] ) {
							printf(
								/* translators: %d: number of posts updated. */
								esc_html( _n( 'Marked %d video as premium.', 'Marked %d videos as premium.', (int) $last['count'], 'pwll_lang' ) ),
								(int) $last['count']
							);
						} else {
							printf(
								/* translators: %d: number of posts updated. */
								esc_html( _n( 'Removed premium from %d video.', 'Removed premium from %d videos.', (int) $last['count'], 'pwll_lang' ) ),
								(int) $last['count']
							);
						}
						?>
					</p>
				</div>
			<?php endif; ?>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( self::NONCE_ACT, self::NONCE_NAME ); ?>
				<input type="hidden" name="action" value="pwll_bulk_duration">

				<table class="form-table" role="presentation">
					<tbody>
						<tr>
							<th scope="row"><label for="pwll_min"><?php esc_html_e( 'Min duration (seconds)', 'pwll_lang' ); ?></label></th>
							<td>
								<input name="min" id="pwll_min" type="number" min="0" step="1" class="small-text" placeholder="0">
								<p class="description"><?php esc_html_e( 'Inclusive. Leave empty to ignore the lower bound.', 'pwll_lang' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="pwll_max"><?php esc_html_e( 'Max duration (seconds)', 'pwll_lang' ); ?></label></th>
							<td>
								<input name="max" id="pwll_max" type="number" min="0" step="1" class="small-text" placeholder="∞">
								<p class="description"><?php esc_html_e( 'Inclusive. Leave empty to ignore the upper bound.', 'pwll_lang' ); ?></p>
							</td>
						</tr>
					</tbody>
				</table>

				<p class="submit">
					<button type="submit" name="op" value="apply" class="button button-primary"
						onclick="return confirm('<?php echo esc_js( __( 'Mark every video in this duration range as premium?', 'pwll_lang' ) ); ?>');">
						<?php esc_html_e( 'Apply paywall', 'pwll_lang' ); ?>
					</button>
					<button type="submit" name="op" value="remove" class="button"
						onclick="return confirm('<?php echo esc_js( __( 'Remove the paywall from every video in this duration range?', 'pwll_lang' ) ); ?>');">
						<?php esc_html_e( 'Remove paywall', 'pwll_lang' ); ?>
					</button>
				</p>
			</form>
		</div>
		<?php
	}

	public static function handle_submit() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'pwll_lang' ) );
		}
		check_admin_referer( self::NONCE_ACT, self::NONCE_NAME );

		$op  = isset( $_POST['op'] ) ? sanitize_key( wp_unslash( $_POST['op'] ) ) : '';
		$min = isset( $_POST['min'] ) && '' !== $_POST['min'] ? max( 0, (int) $_POST['min'] ) : null;
		$max = isset( $_POST['max'] ) && '' !== $_POST['max'] ? max( 0, (int) $_POST['max'] ) : null;

		if ( ! in_array( $op, array( 'apply', 'remove' ), true ) ) {
			wp_safe_redirect( admin_url( 'admin.php?page=' . self::MENU_SLUG ) );
			exit;
		}

		$post_ids = self::query_post_ids( $min, $max );

		$count = 0;
		foreach ( $post_ids as $post_id ) {
			if ( 'apply' === $op ) {
				if ( 'premium' !== get_post_meta( $post_id, 'pwll_post_status', true ) ) {
					update_post_meta( $post_id, 'pwll_post_status', 'premium' );
					$count++;
				}
			} else {
				if ( '' !== get_post_meta( $post_id, 'pwll_post_status', true ) ) {
					delete_post_meta( $post_id, 'pwll_post_status' );
					$count++;
				}
			}
		}

		set_transient(
			'pwll_bulk_duration_last',
			array( 'action' => $op, 'count' => $count ),
			60
		);

		wp_safe_redirect( admin_url( 'admin.php?page=' . self::MENU_SLUG ) );
		exit;
	}

	/**
	 * Returns post IDs whose `duration` meta (in seconds) falls in the
	 * requested range. Restricted to published posts of type `post`
	 * (the metabox is registered on that type).
	 */
	protected static function query_post_ids( $min, $max ) {
		global $wpdb;

		$sql  = "SELECT p.ID FROM {$wpdb->posts} p
			INNER JOIN {$wpdb->postmeta} d
				ON d.post_id = p.ID AND d.meta_key = 'duration'
			WHERE p.post_type = 'post'
			  AND p.post_status = 'publish'
			  AND d.meta_value <> ''";
		$args = array();

		if ( null !== $min ) {
			$sql   .= ' AND CAST(d.meta_value AS UNSIGNED) >= %d';
			$args[] = $min;
		}
		if ( null !== $max ) {
			$sql   .= ' AND CAST(d.meta_value AS UNSIGNED) <= %d';
			$args[] = $max;
		}

		$prepared = $args ? $wpdb->prepare( $sql, $args ) : $sql;
		$ids      = $wpdb->get_col( $prepared );

		return array_map( 'intval', (array) $ids );
	}
}

PWLL_Bulk_Duration::init();

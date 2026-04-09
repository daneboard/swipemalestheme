<?php
/**
 * Admin pages, settings, and AJAX handlers.
 */

defined( 'ABSPATH' ) || exit;

class TSVI_Admin {

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'register_menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );

		// AJAX handlers.
		add_action( 'wp_ajax_tsvi_discover', array( __CLASS__, 'ajax_discover' ) );
		add_action( 'wp_ajax_tsvi_extract', array( __CLASS__, 'ajax_extract' ) );
		add_action( 'wp_ajax_tsvi_enrich', array( __CLASS__, 'ajax_enrich' ) );
		add_action( 'wp_ajax_tsvi_import', array( __CLASS__, 'ajax_import' ) );
		add_action( 'wp_ajax_tsvi_process_tick', array( __CLASS__, 'ajax_process_tick' ) );
	}

	/* ------------------------------------------------------------------
	   Menu & Assets
	   ------------------------------------------------------------------ */

	public static function register_menu() {
		add_menu_page(
			'Video Importer',
			'Video Importer',
			'manage_options',
			'tsvi-scrape',
			array( __CLASS__, 'page_scrape' ),
			'dashicons-video-alt3',
			30
		);

		add_submenu_page(
			'tsvi-scrape',
			'Scrape & Import',
			'Scrape & Import',
			'manage_options',
			'tsvi-scrape',
			array( __CLASS__, 'page_scrape' )
		);

		add_submenu_page(
			'tsvi-scrape',
			'CDN Queue',
			'CDN Queue',
			'manage_options',
			'tsvi-queue',
			array( __CLASS__, 'page_queue' )
		);

		add_submenu_page(
			'tsvi-scrape',
			'Settings',
			'Settings',
			'manage_options',
			'tsvi-settings',
			array( __CLASS__, 'page_settings' )
		);
	}

	public static function enqueue_assets( $hook ) {
		if ( strpos( $hook, 'tsvi-' ) === false ) {
			return;
		}

		wp_enqueue_style( 'tsvi-admin', TSVI_URL . 'assets/css/tsvi-admin.css', array(), TSVI_VERSION );
		wp_enqueue_script( 'tsvi-admin', TSVI_URL . 'assets/js/tsvi-admin.js', array( 'jquery' ), TSVI_VERSION, true );
		wp_localize_script(
			'tsvi-admin',
			'tsvi',
			array(
				'ajax_url' => admin_url( 'admin-ajax.php' ),
				'nonce'    => wp_create_nonce( 'tsvi_nonce' ),
			)
		);
	}

	/* ------------------------------------------------------------------
	   CDN Queue page
	   ------------------------------------------------------------------ */

	public static function page_queue() {
		global $wpdb;

		// Handle actions.
		$action  = sanitize_text_field( $_GET['tsvi_action'] ?? '' );
		$action_id = intval( $_GET['tsvi_id'] ?? 0 );

		if ( $action && wp_verify_nonce( $_GET['_wpnonce'] ?? '', 'tsvi_queue_action' ) ) {
			$redirect_args = array( 'page' => 'tsvi-queue' );

			switch ( $action ) {
				case 'retry': // Re-queue a single failed/uploaded post.
					self::requeue_post( $action_id );
					$redirect_args['msg'] = 'retried';
					$redirect_args['ids'] = $action_id;
					break;

				case 'cancel': // Cancel a single pending post.
					update_post_meta( $action_id, '_tsvi_bunny_pending', '' );
					$redirect_args['msg'] = 'cancelled';
					$redirect_args['ids'] = $action_id;
					break;

				case 'cancel_all': // Cancel all pending.
					$ids = $wpdb->get_col( "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_tsvi_bunny_pending' AND meta_value != ''" );
					foreach ( $ids as $id ) {
						update_post_meta( $id, '_tsvi_bunny_pending', '' );
					}
					$redirect_args['msg'] = 'cancelled_all';
					$redirect_args['ids'] = count( $ids );
					break;

				case 'retry_all_failed': // Re-queue all failed.
					$ids = $wpdb->get_col( "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_tsvi_bunny_error' AND meta_value != ''" );
					foreach ( $ids as $id ) {
						self::requeue_post( $id );
					}
					$redirect_args['msg'] = 'retried_all';
					$redirect_args['ids'] = count( $ids );
					break;

				case 'retry_uploaded': // Re-upload an already uploaded post (re-download + re-upload).
					self::requeue_post( $action_id );
					$redirect_args['msg'] = 'retried';
					$redirect_args['ids'] = $action_id;
					break;

				case 'force_start': // Force start the cron queue immediately.
					// Clear any stale state that may be blocking the queue.
					delete_transient( 'tsvi_queue_lock' );
					delete_transient( 'tsvi_currently_processing' );
					wp_clear_scheduled_hook( TSVI_Bunny::CRON_HOOK );
					// Method 1: schedule via wp-cron.
					wp_schedule_single_event( time(), TSVI_Bunny::CRON_HOOK );
					spawn_cron();
					// Method 2: direct loopback (bypasses wp-cron entirely).
					wp_remote_post( admin_url( 'admin-ajax.php' ), array(
						'timeout'   => 0.01,
						'blocking'  => false,
						'sslverify' => false,
						'body'      => array(
							'action' => 'tsvi_force_process',
							'token'  => wp_hash( 'tsvi_force_process' ),
						),
					) );
					$redirect_args['msg'] = 'force_started';
					break;
			}

			wp_safe_redirect( add_query_arg( $redirect_args, admin_url( 'admin.php' ) ) );
			exit;
		}

		// Detect which post is currently being processed by cron.
		$processing_id = get_transient( 'tsvi_currently_processing' );

		// Auto-heal: detect stalled queue and restart it.
		$has_pending = $wpdb->get_var(
			"SELECT COUNT(*) FROM {$wpdb->postmeta}
			 WHERE meta_key = '_tsvi_bunny_pending' AND meta_value != ''"
		);
		$scheduled_time = wp_next_scheduled( TSVI_Bunny::CRON_HOOK );
		$is_stalled     = false;
		if ( $has_pending > 0 && ! get_transient( 'tsvi_queue_lock' ) ) {
			if ( ! $scheduled_time ) {
				// No cron scheduled at all — definitely stalled.
				$is_stalled = true;
			} elseif ( $scheduled_time < time() - 120 ) {
				// Cron is overdue by 2+ minutes — wp-cron likely broken.
				$is_stalled = true;
			}
		}
		if ( $is_stalled ) {
			delete_transient( 'tsvi_currently_processing' );
			wp_clear_scheduled_hook( TSVI_Bunny::CRON_HOOK );
			wp_schedule_single_event( time(), TSVI_Bunny::CRON_HOOK );
			spawn_cron();
			// Direct loopback as backup.
			wp_remote_post( admin_url( 'admin-ajax.php' ), array(
				'timeout'   => 0.01,
				'blocking'  => false,
				'sslverify' => false,
				'body'      => array(
					'action' => 'tsvi_force_process',
					'token'  => wp_hash( 'tsvi_force_process' ),
				),
			) );
			echo '<div class="notice notice-warning is-dismissible"><p>Queue was stalled — automatically restarted processing.</p></div>';
		}

		// Fetch all posts with Bunny-related meta.
		$pending_posts = $wpdb->get_results(
			"SELECT p.ID, p.post_title, pm.meta_value as pending_url,
			        CAST(COALESCE(dur.meta_value, '999999') AS UNSIGNED) as duration
			 FROM {$wpdb->posts} p
			 JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_tsvi_bunny_pending' AND pm.meta_value != ''
			 LEFT JOIN {$wpdb->postmeta} dur ON p.ID = dur.post_id AND dur.meta_key = 'duration'
			 ORDER BY duration ASC LIMIT 100"
		);

		$done_posts = $wpdb->get_results(
			"SELECT p.ID, p.post_title
			 FROM {$wpdb->posts} p
			 JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
			 WHERE pm.meta_key = '_tsvi_bunny_status' AND pm.meta_value = 'uploaded'
			 ORDER BY p.ID DESC LIMIT 100"
		);

		$failed_posts = $wpdb->get_results(
			"SELECT p.ID, p.post_title, pm.meta_value as error_msg
			 FROM {$wpdb->posts} p
			 JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
			 WHERE pm.meta_key = '_tsvi_bunny_error' AND pm.meta_value != ''
			 ORDER BY p.ID DESC LIMIT 100"
		);

		$pending_count = count( $pending_posts );
		$done_count    = count( $done_posts );
		$failed_count  = count( $failed_posts );

		// Flash messages.
		$msg = sanitize_text_field( $_GET['msg'] ?? '' );
		$msg_ids = sanitize_text_field( $_GET['ids'] ?? '' );
		if ( $msg ) {
			$notices = array(
				'retried'       => 'Post #' . $msg_ids . ' re-queued for CDN upload.',
				'cancelled'     => 'Post #' . $msg_ids . ' cancelled.',
				'cancelled_all' => $msg_ids . ' pending uploads cancelled.',
				'retried_all'   => $msg_ids . ' failed uploads re-queued.',
				'force_started' => 'CDN queue processing triggered.',
			);
			if ( isset( $notices[ $msg ] ) ) {
				echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( $notices[ $msg ] ) . '</p></div>';
			}
		}
		?>
		<?php
		// Diagnostic info.
		$cron_next    = wp_next_scheduled( TSVI_Bunny::CRON_HOOK );
		$queue_locked = get_transient( 'tsvi_queue_lock' );
		?>
		<div class="wrap" id="tsvi-queue-page" data-pending="<?php echo $pending_count; ?>">
			<h1>
				CDN Upload Queue
				<a class="button button-primary" href="<?php echo wp_nonce_url( admin_url( 'admin.php?page=tsvi-queue&tsvi_action=force_start' ), 'tsvi_queue_action' ); ?>">Force Start</a>
			</h1>

			<p id="tsvi-auto-status"></p>

			<?php if ( $pending_count > 0 ) : ?>
			<p class="description">
				Cron: <?php echo $cron_next ? 'scheduled for ' . date( 'H:i:s', $cron_next ) . ( $cron_next <= time() ? ' (overdue)' : '' ) : '<strong>not scheduled</strong>'; ?>
				&nbsp;|&nbsp; Lock: <?php echo $queue_locked ? '<strong>active</strong>' : 'none'; ?>
				&nbsp;|&nbsp; Processing: <?php echo $processing_id ? '#' . $processing_id : 'idle'; ?>
				&nbsp;|&nbsp; v<?php echo TSVI_VERSION; ?>
			</p>
			<?php endif; ?>

			<!-- PENDING -->
			<div class="tsvi-card">
				<h2>
					Pending <span class="tsvi-badge tsvi-badge-pending"><?php echo $pending_count; ?></span>
					<?php if ( $pending_count > 0 ) : ?>
						<a class="button button-small tsvi-btn-danger" href="<?php echo wp_nonce_url( admin_url( 'admin.php?page=tsvi-queue&tsvi_action=cancel_all' ), 'tsvi_queue_action' ); ?>" onclick="return confirm('Cancel all pending uploads?');">Cancel All</a>
					<?php endif; ?>
				</h2>
				<?php if ( $pending_posts ) : ?>
					<table class="wp-list-table widefat striped">
						<thead><tr><th>ID</th><th>Title</th><th>Status</th><th>Action</th></tr></thead>
						<tbody>
						<?php foreach ( $pending_posts as $i => $p ) : ?>
							<tr>
								<td><a href="<?php echo get_edit_post_link( $p->ID ); ?>">#<?php echo $p->ID; ?></a></td>
								<td><?php echo esc_html( $p->post_title ); ?></td>
								<td>
									<?php if ( (int) $processing_id === (int) $p->ID ) : ?>
										<span class="tsvi-loading">Downloading & uploading...</span>
									<?php elseif ( $i === 0 && ! $processing_id ) : ?>
										<span class="tsvi-loading">Next in queue</span>
									<?php else : ?>
										<span>Waiting (#<?php echo $i + 1; ?>)</span>
									<?php endif; ?>
								</td>
								<td>
									<a class="button button-small" href="<?php echo wp_nonce_url( admin_url( 'admin.php?page=tsvi-queue&tsvi_action=cancel&tsvi_id=' . $p->ID ), 'tsvi_queue_action' ); ?>">Cancel</a>
								</td>
							</tr>
						<?php endforeach; ?>
						</tbody>
					</table>
				<?php else : ?>
					<p>No uploads pending.</p>
				<?php endif; ?>
			</div>

			<!-- FAILED -->
			<div class="tsvi-card">
				<h2>
					Failed <span class="tsvi-badge tsvi-badge-failed"><?php echo $failed_count; ?></span>
					<?php if ( $failed_count > 0 ) : ?>
						<a class="button button-small" href="<?php echo wp_nonce_url( admin_url( 'admin.php?page=tsvi-queue&tsvi_action=retry_all_failed' ), 'tsvi_queue_action' ); ?>">Retry All</a>
					<?php endif; ?>
				</h2>
				<?php if ( $failed_posts ) : ?>
					<table class="wp-list-table widefat striped">
						<thead><tr><th>ID</th><th>Title</th><th>Error</th><th>Action</th></tr></thead>
						<tbody>
						<?php foreach ( $failed_posts as $p ) : ?>
							<tr>
								<td><a href="<?php echo get_edit_post_link( $p->ID ); ?>">#<?php echo $p->ID; ?></a></td>
								<td><?php echo esc_html( $p->post_title ); ?></td>
								<td><span class="tsvi-err"><?php echo esc_html( $p->error_msg ); ?></span></td>
								<td>
									<a class="button button-small" href="<?php echo wp_nonce_url( admin_url( 'admin.php?page=tsvi-queue&tsvi_action=retry&tsvi_id=' . $p->ID ), 'tsvi_queue_action' ); ?>">Retry</a>
								</td>
							</tr>
						<?php endforeach; ?>
						</tbody>
					</table>
				<?php else : ?>
					<p>No failed uploads.</p>
				<?php endif; ?>
			</div>

			<!-- UPLOADED -->
			<div class="tsvi-card">
				<h2>Uploaded <span class="tsvi-badge tsvi-badge-done"><?php echo $done_count; ?></span></h2>
				<?php if ( $done_posts ) : ?>
					<table class="wp-list-table widefat striped">
						<thead><tr><th>ID</th><th>Title</th><th>CDN URL</th><th>Action</th></tr></thead>
						<tbody>
						<?php foreach ( $done_posts as $p ) : ?>
							<?php $cdn_url = get_post_meta( $p->ID, 'video_url', true ); ?>
							<tr>
								<td><a href="<?php echo get_edit_post_link( $p->ID ); ?>">#<?php echo $p->ID; ?></a></td>
								<td><?php echo esc_html( $p->post_title ); ?></td>
								<td><small><?php echo esc_html( mb_substr( $cdn_url, 0, 80 ) ); ?>...</small></td>
								<td>
									<a class="button button-small" href="<?php echo wp_nonce_url( admin_url( 'admin.php?page=tsvi-queue&tsvi_action=retry_uploaded&tsvi_id=' . $p->ID ), 'tsvi_queue_action' ); ?>">Re-upload</a>
								</td>
							</tr>
						<?php endforeach; ?>
						</tbody>
					</table>
				<?php else : ?>
					<p>No uploaded videos yet.</p>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Re-queue a post for Bunny upload (works for failed and uploaded posts).
	 */
	private static function requeue_post( $post_id ) {
		$source = get_post_meta( $post_id, '_tsvi_source_url', true );
		if ( ! $source ) {
			$source = get_post_meta( $post_id, 'video_url', true );
		}
		if ( $source ) {
			update_post_meta( $post_id, '_tsvi_bunny_pending', $source );
			delete_post_meta( $post_id, '_tsvi_bunny_error' );
			delete_post_meta( $post_id, '_tsvi_bunny_status' );
			TSVI_Bunny::schedule_upload( $post_id );
		}
	}

	/* ------------------------------------------------------------------
	   Settings page
	   ------------------------------------------------------------------ */

	public static function register_settings() {
		register_setting( 'tsvi_settings', 'tsvi_grok_api_key' );
		register_setting( 'tsvi_settings', 'tsvi_grok_model' );
		register_setting( 'tsvi_settings', 'tsvi_default_status' );
		register_setting( 'tsvi_settings', 'tsvi_default_category' );
		// Bunny.net settings.
		register_setting( 'tsvi_settings', 'tsvi_bunny_api_key' );
		register_setting( 'tsvi_settings', 'tsvi_bunny_storage_zone' );
		register_setting( 'tsvi_settings', 'tsvi_bunny_storage_region' );
		register_setting( 'tsvi_settings', 'tsvi_bunny_cdn_hostname' );
		register_setting( 'tsvi_settings', 'tsvi_bunny_token_key' );
	}

	public static function page_settings() {
		$api_key  = get_option( 'tsvi_grok_api_key', '' );
		$model    = get_option( 'tsvi_grok_model', 'grok-3-mini-fast' );
		$status   = get_option( 'tsvi_default_status', 'draft' );
		$cat_id   = get_option( 'tsvi_default_category', 0 );
		$cats     = get_categories( array( 'hide_empty' => false ) );

		$bunny_api     = get_option( 'tsvi_bunny_api_key', '' );
		$bunny_zone    = get_option( 'tsvi_bunny_storage_zone', '' );
		$bunny_region  = get_option( 'tsvi_bunny_storage_region', '' );
		$bunny_cdn     = get_option( 'tsvi_bunny_cdn_hostname', '' );
		$bunny_token   = get_option( 'tsvi_bunny_token_key', '' );
		?>
		<div class="wrap">
			<h1>Video Importer Settings</h1>
			<form method="post" action="options.php">
				<?php settings_fields( 'tsvi_settings' ); ?>

				<h2>Grok AI</h2>
				<table class="form-table">
					<tr>
						<th>Grok API Key</th>
						<td>
							<input type="password" name="tsvi_grok_api_key" value="<?php echo esc_attr( $api_key ); ?>" class="regular-text" autocomplete="off">
							<p class="description">API key from <a href="https://console.x.ai/" target="_blank">console.x.ai</a></p>
						</td>
					</tr>
					<tr>
						<th>Grok Model</th>
						<td>
							<select name="tsvi_grok_model">
								<option value="grok-3-mini-fast" <?php selected( $model, 'grok-3-mini-fast' ); ?>>grok-3-mini-fast (cheapest)</option>
								<option value="grok-3-mini" <?php selected( $model, 'grok-3-mini' ); ?>>grok-3-mini</option>
								<option value="grok-3" <?php selected( $model, 'grok-3' ); ?>>grok-3 (most capable)</option>
							</select>
						</td>
					</tr>
					<tr>
						<th>Default Post Status</th>
						<td>
							<select name="tsvi_default_status">
								<option value="draft" <?php selected( $status, 'draft' ); ?>>Draft</option>
								<option value="publish" <?php selected( $status, 'publish' ); ?>>Publish</option>
								<option value="pending" <?php selected( $status, 'pending' ); ?>>Pending Review</option>
							</select>
						</td>
					</tr>
					<tr>
						<th>Default Category</th>
						<td>
							<select name="tsvi_default_category">
								<option value="0">-- None --</option>
								<?php foreach ( $cats as $c ) : ?>
									<option value="<?php echo esc_attr( $c->term_id ); ?>" <?php selected( $cat_id, $c->term_id ); ?>><?php echo esc_html( $c->name ); ?></option>
								<?php endforeach; ?>
							</select>
						</td>
					</tr>
				</table>

				<h2>Bunny.net CDN</h2>
				<p class="description">Optional. If configured, videos are uploaded to Bunny Storage via remote fetch (no download to your server). Leave empty to save external URLs directly.</p>
				<table class="form-table">
					<tr>
						<th>Storage API Key</th>
						<td>
							<input type="password" name="tsvi_bunny_api_key" value="<?php echo esc_attr( $bunny_api ); ?>" class="regular-text" autocomplete="off">
							<p class="description">FTP & API Access password from your Storage Zone settings.</p>
						</td>
					</tr>
					<tr>
						<th>Storage Zone Name</th>
						<td>
							<input type="text" name="tsvi_bunny_storage_zone" value="<?php echo esc_attr( $bunny_zone ); ?>" class="regular-text" placeholder="my-videos">
						</td>
					</tr>
					<tr>
						<th>Storage Region</th>
						<td>
							<select name="tsvi_bunny_storage_region">
								<option value="" <?php selected( $bunny_region, '' ); ?>>Falkenstein (default)</option>
								<option value="ny" <?php selected( $bunny_region, 'ny' ); ?>>New York</option>
								<option value="la" <?php selected( $bunny_region, 'la' ); ?>>Los Angeles</option>
								<option value="sg" <?php selected( $bunny_region, 'sg' ); ?>>Singapore</option>
								<option value="syd" <?php selected( $bunny_region, 'syd' ); ?>>Sydney</option>
								<option value="uk" <?php selected( $bunny_region, 'uk' ); ?>>London</option>
								<option value="se" <?php selected( $bunny_region, 'se' ); ?>>Stockholm</option>
								<option value="br" <?php selected( $bunny_region, 'br' ); ?>>Sao Paulo</option>
								<option value="jh" <?php selected( $bunny_region, 'jh' ); ?>>Johannesburg</option>
							</select>
						</td>
					</tr>
					<tr>
						<th>CDN Hostname</th>
						<td>
							<input type="text" name="tsvi_bunny_cdn_hostname" value="<?php echo esc_attr( $bunny_cdn ); ?>" class="regular-text" placeholder="myzone.b-cdn.net">
							<p class="description">Your Pull Zone hostname (e.g. myzone.b-cdn.net or custom domain).</p>
						</td>
					</tr>
					<tr>
						<th>Token Authentication Key</th>
						<td>
							<input type="password" name="tsvi_bunny_token_key" value="<?php echo esc_attr( $bunny_token ); ?>" class="regular-text" autocomplete="off">
							<p class="description">Security token from Pull Zone > Security > Token Authentication. URLs are signed automatically. Also enable Hotlink Protection in the Bunny dashboard.</p>
						</td>
					</tr>
				</table>

				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}

	/* ------------------------------------------------------------------
	   Scrape page
	   ------------------------------------------------------------------ */

	public static function page_scrape() {
		$cats = get_categories( array( 'hide_empty' => false ) );
		?>
		<div class="wrap">
			<h1>Scrape & Import Videos</h1>

			<!-- Scrape form -->
			<div class="tsvi-card" id="tsvi-scrape-form">
				<h2>1. Scrape Source</h2>
				<table class="form-table">
					<tr>
						<th>Source URL</th>
						<td><input type="url" id="tsvi-url" class="large-text" placeholder="https://example.com/videos/search?q=keyword"></td>
					</tr>
					<tr>
						<th>Mode</th>
						<td>
							<label><input type="radio" name="tsvi-mode" value="listing" checked> Listing page (discover video links)</label><br>
							<label><input type="radio" name="tsvi-mode" value="direct"> Direct video page (extract one video)</label>
						</td>
					</tr>
					<tr>
						<th>Limit</th>
						<td><input type="number" id="tsvi-limit" value="10" min="1" max="100" style="width:80px"> videos max</td>
					</tr>
					<tr>
						<th>Delay</th>
						<td><input type="number" id="tsvi-delay" value="2" min="0" max="30" style="width:80px"> seconds between requests</td>
					</tr>
				</table>
				<p>
					<button class="button button-primary button-hero" id="tsvi-btn-scrape">Scrape</button>
				</p>
			</div>

			<!-- Progress -->
			<div class="tsvi-card tsvi-hidden" id="tsvi-progress">
				<h2>Scraping...</h2>
				<div class="tsvi-progress-bar"><div class="tsvi-progress-fill" id="tsvi-progress-fill"></div></div>
				<p id="tsvi-progress-text">Initializing...</p>
			</div>

			<!-- Results -->
			<div class="tsvi-card tsvi-hidden" id="tsvi-results">
				<h2>2. Review Results</h2>
				<div class="tsvi-actions-bar">
					<label><input type="checkbox" id="tsvi-select-all"> Select all</label>
					<button class="button" id="tsvi-btn-ai">Generate AI Tags (Grok)</button>
					<select id="tsvi-import-cat">
						<option value="">-- Category (AI or default) --</option>
						<?php foreach ( $cats as $c ) : ?>
							<option value="<?php echo esc_attr( $c->term_id ); ?>"><?php echo esc_html( $c->name ); ?></option>
						<?php endforeach; ?>
					</select>
					<button class="button button-primary" id="tsvi-btn-import">Import Selected</button>
				</div>
				<table class="wp-list-table widefat striped" id="tsvi-results-table">
					<thead>
						<tr>
							<th class="check-column"><input type="checkbox" id="tsvi-select-all-head"></th>
							<th>Thumb</th>
							<th>Title</th>
							<th>Duration</th>
							<th>Video URL</th>
							<th>Tags</th>
							<th>Status</th>
						</tr>
					</thead>
					<tbody></tbody>
				</table>
			</div>

			<!-- Import progress -->
			<div class="tsvi-card tsvi-hidden" id="tsvi-import-progress">
				<h2>3. Importing...</h2>
				<div class="tsvi-progress-bar"><div class="tsvi-progress-fill" id="tsvi-import-fill"></div></div>
				<p id="tsvi-import-text">Starting...</p>
			</div>
		</div>
		<?php
	}

	/* ------------------------------------------------------------------
	   AJAX: Discover links from listing page
	   ------------------------------------------------------------------ */

	public static function ajax_discover() {
		check_ajax_referer( 'tsvi_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Unauthorized.' );
		}

		$url   = esc_url_raw( $_POST['url'] ?? '' );
		$limit = intval( $_POST['limit'] ?? 20 );

		if ( empty( $url ) ) {
			wp_send_json_error( 'URL is required.' );
		}

		$links = TSVI_Scraper::discover_links( $url, $limit );

		if ( is_wp_error( $links ) ) {
			wp_send_json_error( $links->get_error_message() );
		}

		wp_send_json_success( $links );
	}

	/* ------------------------------------------------------------------
	   AJAX: Extract video data from a single page
	   ------------------------------------------------------------------ */

	public static function ajax_extract() {
		check_ajax_referer( 'tsvi_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Unauthorized.' );
		}

		$url = esc_url_raw( $_POST['url'] ?? '' );

		if ( empty( $url ) ) {
			wp_send_json_error( 'URL is required.' );
		}

		$video = TSVI_Scraper::extract_video( $url );

		if ( is_wp_error( $video ) ) {
			wp_send_json_error( $video->get_error_message() );
		}

		wp_send_json_success( $video );
	}

	/* ------------------------------------------------------------------
	   AJAX: Enrich video with AI (Grok)
	   ------------------------------------------------------------------ */

	public static function ajax_enrich() {
		check_ajax_referer( 'tsvi_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Unauthorized.' );
		}

		$video = array(
			'title'       => sanitize_text_field( $_POST['title'] ?? '' ),
			'source_url'  => esc_url_raw( $_POST['source_url'] ?? '' ),
			'source_tags' => array_map( 'sanitize_text_field', (array) ( $_POST['source_tags'] ?? array() ) ),
		);

		$result = TSVI_AI::enrich( $video );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( $result->get_error_message() );
		}

		wp_send_json_success( $result );
	}

	/* ------------------------------------------------------------------
	   AJAX: Import a single video
	   ------------------------------------------------------------------ */

	public static function ajax_import() {
		check_ajax_referer( 'tsvi_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Unauthorized.' );
		}

		$video = array(
			'title'       => sanitize_text_field( $_POST['title'] ?? '' ),
			'description' => sanitize_text_field( $_POST['description'] ?? '' ),
			'video_url'   => esc_url_raw( $_POST['video_url'] ?? '' ),
			'source_url'  => esc_url_raw( $_POST['source_url'] ?? '' ),
			'embed'       => wp_kses_post( $_POST['embed'] ?? '' ),
			'thumbnail'   => esc_url_raw( $_POST['thumbnail'] ?? '' ),
			'duration'    => intval( $_POST['duration'] ?? 0 ),
			'width'       => intval( $_POST['width'] ?? 0 ),
			'height'      => intval( $_POST['height'] ?? 0 ),
			'tags'        => array_map( 'sanitize_text_field', (array) ( $_POST['tags'] ?? array() ) ),
			'category'    => sanitize_text_field( $_POST['category'] ?? '' ),
		);

		// Override category if manually selected.
		$override_cat = intval( $_POST['override_category'] ?? 0 );
		if ( $override_cat ) {
			$term = get_term( $override_cat, 'category' );
			if ( $term && ! is_wp_error( $term ) ) {
				$video['category'] = $term->name;
			}
		}

		$result = TSVI_Importer::import( $video );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( $result->get_error_message() );
		}

		wp_send_json_success(
			array(
				'post_id'      => $result['post_id'],
				'edit_url'     => get_edit_post_link( $result['post_id'], 'raw' ),
				'bunny_status' => $result['bunny_status'],
				'video_url'    => $result['video_url'],
			)
		);
	}

	/* ------------------------------------------------------------------
	   AJAX: Process one pending CDN upload (browser-driven, no wp-cron)
	   ------------------------------------------------------------------ */

	public static function ajax_process_tick() {
		check_ajax_referer( 'tsvi_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Unauthorized.' );
		}

		global $wpdb;

		// Pick the next pending item (shortest video first).
		$post_id = $wpdb->get_var(
			"SELECT pm.post_id FROM {$wpdb->postmeta} pm
			 LEFT JOIN {$wpdb->postmeta} dur ON pm.post_id = dur.post_id AND dur.meta_key = 'duration'
			 WHERE pm.meta_key = '_tsvi_bunny_pending'
			 AND pm.meta_value != ''
			 ORDER BY CAST(COALESCE(dur.meta_value, '999999') AS UNSIGNED) ASC
			 LIMIT 1"
		);

		if ( ! $post_id ) {
			wp_send_json_success( array( 'done' => true ) );
		}

		set_time_limit( 1200 );
		ignore_user_abort( true );

		// Clear any stale lock so processing can proceed.
		delete_transient( 'tsvi_queue_lock' );

		TSVI_Bunny::process_single( intval( $post_id ) );

		// Count remaining.
		$remaining = $wpdb->get_var(
			"SELECT COUNT(*) FROM {$wpdb->postmeta}
			 WHERE meta_key = '_tsvi_bunny_pending'
			 AND meta_value != ''"
		);

		wp_send_json_success( array(
			'processed' => intval( $post_id ),
			'remaining' => intval( $remaining ),
		) );
	}
}

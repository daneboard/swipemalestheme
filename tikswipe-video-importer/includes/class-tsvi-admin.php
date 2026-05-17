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
		add_action( 'wp_ajax_tsvi_direct_queue', array( __CLASS__, 'ajax_direct_queue' ) );
		add_action( 'wp_ajax_tsvi_direct_clear_history', array( __CLASS__, 'ajax_direct_clear_history' ) );
		add_action( 'wp_ajax_tsvi_recompress_start', array( __CLASS__, 'ajax_recompress_start' ) );
		add_action( 'wp_ajax_tsvi_upload_file', array( __CLASS__, 'ajax_upload_file' ) );
		add_action( 'wp_ajax_tsvi_file_clear_history', array( __CLASS__, 'ajax_file_clear_history' ) );
		add_action( 'wp_ajax_tsvi_enqueue_scanned', array( __CLASS__, 'ajax_enqueue_scanned' ) );
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
			'Direct Upload',
			'Direct Upload',
			'manage_options',
			'tsvi-direct',
			array( __CLASS__, 'page_direct_upload' )
		);

		add_submenu_page(
			'tsvi-scrape',
			'Upload File',
			'Upload File',
			'manage_options',
			'tsvi-upload',
			array( __CLASS__, 'page_upload_file' )
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
			'Recompress',
			'Recompress',
			'manage_options',
			'tsvi-recompress',
			array( __CLASS__, 'page_recompress' )
		);

		add_submenu_page(
			'tsvi-scrape',
			'Settings',
			'Settings',
			'manage_options',
			'tsvi-settings',
			array( __CLASS__, 'page_settings' )
		);

		add_submenu_page(
			'tsvi-scrape',
			'Activity Log',
			'Activity Log',
			'manage_options',
			'tsvi-log',
			array( __CLASS__, 'page_log' )
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
	   Direct Upload page
	   ------------------------------------------------------------------ */

	public static function page_direct_upload() {
		$queue   = get_option( 'tsvi_direct_upload_queue', array() );
		$history = get_option( 'tsvi_direct_upload_history', array() );
		$history = array_reverse( $history ); // Newest first.
		$queue_count   = count( $queue );
		$history_count = count( $history );
		?>
		<div class="wrap">
			<h1>Direct Upload to Bunny CDN</h1>

			<?php if ( ! TSVI_Bunny::is_enabled() ) : ?>
				<div class="notice notice-error"><p>Bunny CDN is not configured. Go to <a href="<?php echo admin_url( 'admin.php?page=tsvi-settings' ); ?>">Settings</a> first.</p></div>
			<?php endif; ?>

			<div class="tsvi-card">
				<h2>Upload by URL</h2>
				<p class="description">Paste one URL per line. Videos are queued and uploaded to Bunny CDN in the background. No posts are created.</p>
				<textarea id="tsvi-direct-urls" rows="6" class="large-text" placeholder="https://example.com/video1.mp4&#10;https://example.com/video2.mp4&#10;https://example.com/video3.mp4"></textarea>
				<p>
					<button class="button button-primary button-hero" id="tsvi-btn-direct-upload">Upload to CDN</button>
				</p>
			</div>

			<!-- Pending queue -->
			<?php if ( $queue_count > 0 ) : ?>
			<div class="tsvi-card">
				<h2>Pending <span class="tsvi-badge tsvi-badge-pending"><?php echo $queue_count; ?></span></h2>
				<table class="wp-list-table widefat striped">
					<thead><tr><th>#</th><th>Source URL</th><th>Queued</th></tr></thead>
					<tbody>
					<?php foreach ( $queue as $i => $item ) : ?>
						<tr>
							<td><?php echo $i + 1; ?></td>
							<td><small><?php echo esc_html( mb_substr( $item['url'], 0, 100 ) ); ?></small></td>
							<td><small><?php echo esc_html( $item['queued_at'] ); ?></small></td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			</div>
			<?php endif; ?>

			<!-- History -->
			<div class="tsvi-card">
				<h2>
					Upload History
					<span class="tsvi-badge tsvi-badge-done"><?php echo $history_count; ?></span>
					<?php if ( $history ) : ?>
						<button class="button button-small tsvi-btn-danger" id="tsvi-btn-clear-history" onclick="return confirm('Clear all upload history?');">Clear History</button>
					<?php endif; ?>
				</h2>
				<?php if ( $history ) : ?>
					<table class="wp-list-table widefat striped">
						<thead><tr><th style="width:90px;">Date</th><th>Source URL</th><th>CDN URL</th><th style="width:90px;">Status</th><th style="width:160px;">Pending Post</th></tr></thead>
						<tbody>
						<?php foreach ( $history as $h ) :
							// Look up the live status of the post (if it still exists).
							$post_status = '';
							$post_exists = false;
							if ( ! empty( $h['post_id'] ) ) {
								$post_obj = get_post( $h['post_id'] );
								if ( $post_obj ) {
									$post_exists = true;
									$post_status = $post_obj->post_status;
								}
							}
							?>
							<tr>
								<td><small><?php echo esc_html( $h['date'] ); ?></small></td>
								<td>
									<input type="text" readonly value="<?php echo esc_attr( $h['source'] ); ?>" class="regular-text tsvi-copy-field" onclick="this.select();document.execCommand('copy');" title="Click to copy original URL">
								</td>
								<td>
									<?php if ( ! empty( $h['cdn_url'] ) ) : ?>
										<input type="text" readonly value="<?php echo esc_attr( $h['cdn_url'] ); ?>" class="regular-text tsvi-copy-field" onclick="this.select();document.execCommand('copy');" title="Click to copy CDN URL">
									<?php else : ?>
										—
									<?php endif; ?>
								</td>
								<td>
									<?php if ( ! empty( $h['error'] ) ) : ?>
										<span class="tsvi-err" title="<?php echo esc_attr( $h['error'] ); ?>"><?php echo esc_html( mb_substr( $h['error'], 0, 30 ) ); ?></span>
									<?php else : ?>
										<span class="tsvi-ok">OK</span>
									<?php endif; ?>
								</td>
								<td>
									<?php if ( ! $post_exists && ! empty( $h['post_id'] ) ) : ?>
										<span class="tsvi-err">deleted</span>
									<?php elseif ( $post_exists && $post_status !== 'publish' ) : ?>
										<span class="tsvi-warn"><?php echo esc_html( $post_status ); ?></span>
										<a class="button button-small" href="<?php echo esc_url( get_edit_post_link( $h['post_id'] ) ); ?>" target="_blank">Edit #<?php echo intval( $h['post_id'] ); ?></a>
									<?php elseif ( $post_exists && $post_status === 'publish' ) : ?>
										<span class="tsvi-ok">published</span>
										<a class="button button-small" href="<?php echo esc_url( get_edit_post_link( $h['post_id'] ) ); ?>" target="_blank">Edit #<?php echo intval( $h['post_id'] ); ?></a>
									<?php else : ?>
										—
									<?php endif; ?>
								</td>
							</tr>
						<?php endforeach; ?>
						</tbody>
					</table>
				<?php else : ?>
					<p>No uploads yet.</p>
				<?php endif; ?>
			</div>
		</div>
		<?php
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
					delete_transient( 'tsvi_queue_lock' );
					delete_transient( 'tsvi_currently_processing' );
					wp_clear_scheduled_hook( TSVI_Bunny::CRON_HOOK );
					wp_schedule_single_event( time(), TSVI_Bunny::CRON_HOOK );
					spawn_cron();
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
		<div class="wrap">
			<h1>
				CDN Upload Queue
				<a class="button button-primary" href="<?php echo wp_nonce_url( admin_url( 'admin.php?page=tsvi-queue&tsvi_action=force_start' ), 'tsvi_queue_action' ); ?>">Force Start</a>
			</h1>

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

	/* ------------------------------------------------------------------
	   Recompress page
	   ------------------------------------------------------------------ */

	public static function page_recompress() {
		global $wpdb;

		// Get stats.
		$total   = intval( $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = '_tsvi_bunny_status' AND meta_value = 'uploaded'" ) );
		$done    = intval( $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = '_tsvi_recompressed'" ) );
		$skipped = intval( $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = '_tsvi_recompress_skipped'" ) );
		$errors  = intval( $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = '_tsvi_recompress_error'" ) );
		$pending = max( 0, $total - $done - $skipped - $errors );
		$lock    = get_transient( 'tsvi_recompress_lock' );

		// Recent done list.
		$done_posts = $wpdb->get_results(
			"SELECT p.ID, p.post_title, pm.meta_value as rc_info
			 FROM {$wpdb->posts} p
			 JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_tsvi_recompressed'
			 ORDER BY p.ID DESC LIMIT 50"
		);

		// Error list.
		$error_posts = $wpdb->get_results(
			"SELECT p.ID, p.post_title, pm.meta_value as error_msg
			 FROM {$wpdb->posts} p
			 JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_tsvi_recompress_error'
			 ORDER BY p.ID DESC LIMIT 50"
		);

		// Skipped list.
		$skipped_posts = $wpdb->get_results(
			"SELECT p.ID, p.post_title, pm.meta_value as reason
			 FROM {$wpdb->posts} p
			 JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_tsvi_recompress_skipped'
			 ORDER BY p.ID DESC LIMIT 50"
		);
		?>
		<div class="wrap">
			<h1>Recompress Existing Videos</h1>
			<p class="description">Downloads each video from Bunny CDN, transcodes to 720p, and re-uploads to the same path. Reduces storage and bandwidth costs by ~75%. Original URL stays the same.</p>

			<!-- Stats cards -->
			<div style="display:flex;gap:12px;flex-wrap:wrap;margin:20px 0;">
				<div class="tsvi-card" style="flex:1;min-width:120px;text-align:center;padding:15px;">
					<div style="font-size:28px;font-weight:700;"><?php echo $total; ?></div>
					<div>Total uploaded</div>
				</div>
				<div class="tsvi-card" style="flex:1;min-width:120px;text-align:center;padding:15px;">
					<div style="font-size:28px;font-weight:700;color:#00a32a;"><?php echo $done; ?></div>
					<div>Compressed</div>
				</div>
				<div class="tsvi-card" style="flex:1;min-width:120px;text-align:center;padding:15px;">
					<div style="font-size:28px;font-weight:700;color:#dba617;"><?php echo $skipped; ?></div>
					<div>Skipped (small)</div>
				</div>
				<div class="tsvi-card" style="flex:1;min-width:120px;text-align:center;padding:15px;">
					<div style="font-size:28px;font-weight:700;color:#d63638;"><?php echo $errors; ?></div>
					<div>Errors</div>
				</div>
				<div class="tsvi-card" style="flex:1;min-width:120px;text-align:center;padding:15px;">
					<div style="font-size:28px;font-weight:700;color:#2271b1;"><?php echo $pending; ?></div>
					<div>Pending</div>
				</div>
			</div>

			<!-- Progress bar -->
			<?php if ( $total > 0 ) :
				$processed = $done + $skipped + $errors;
				$pct = round( ( $processed / $total ) * 100 );
			?>
			<div class="tsvi-card">
				<div class="tsvi-progress-bar" style="height:24px;">
					<div class="tsvi-progress-fill" style="width:<?php echo $pct; ?>%;"></div>
				</div>
				<p style="text-align:center;margin:8px 0 0;">
					<?php echo $processed; ?>/<?php echo $total; ?> processed (<?php echo $pct; ?>%)
					<?php if ( $lock ) : ?>
						— <span class="tsvi-loading">running...</span>
					<?php endif; ?>
				</p>
			</div>
			<?php endif; ?>

			<!-- Actions -->
			<div class="tsvi-card">
				<h2>Actions</h2>
				<?php if ( ! TSVI_Bunny::ffmpeg_available() ) : ?>
					<div class="notice notice-error" style="margin:0 0 15px;"><p>FFmpeg is not installed. Install via SSH: <code>apt install -y ffmpeg</code></p></div>
				<?php endif; ?>

				<?php $rc_enabled = (bool) get_option( 'tsvi_recompress_enabled', 0 ); ?>

				<p>
					<strong>Status:</strong>
					<?php if ( $rc_enabled ) : ?>
						<span class="tsvi-loading">ENABLED — the CLI worker processes a batch every minute.</span>
						<?php if ( $lock ) : ?>
							<span class="tsvi-ok">(batch running now)</span>
						<?php endif; ?>
					<?php else : ?>
						<span class="tsvi-warn">DISABLED — click Start to begin processing.</span>
					<?php endif; ?>
				</p>

				<p>
					<?php if ( $pending > 0 && ! $rc_enabled ) : ?>
						<button class="button button-primary button-hero" id="tsvi-btn-recompress" data-action="start">
							Start Recompress (<?php echo $pending; ?> pending)
						</button>
					<?php elseif ( $rc_enabled ) : ?>
						<button class="button button-hero tsvi-btn-danger" id="tsvi-btn-recompress" data-action="stop">
							Stop Recompress
						</button>
						<span style="margin-left:10px;">Processing 3 videos per minute via CLI worker.</span>
					<?php else : ?>
						<button class="button button-hero" disabled>All videos processed</button>
					<?php endif; ?>
				</p>

				<p class="description">
					The CLI worker (crontab) processes a batch every minute while this is enabled. Refresh the page to see progress.<br>
					SSH alternative (runs all at once):<br>
					<code>php <?php echo esc_html( TSVI_PATH . 'recompress.php' ); ?> --all</code><br>
					<code>php <?php echo esc_html( TSVI_PATH . 'recompress.php' ); ?> --status</code>
				</p>
			</div>

			<!-- Compressed -->
			<?php if ( $done_posts ) : ?>
			<div class="tsvi-card">
				<h2>Compressed <span class="tsvi-badge tsvi-badge-done"><?php echo $done; ?></span></h2>
				<table class="wp-list-table widefat striped">
					<thead><tr><th>ID</th><th>Title</th><th>Result</th></tr></thead>
					<tbody>
					<?php foreach ( $done_posts as $p ) : ?>
						<tr>
							<td><a href="<?php echo get_edit_post_link( $p->ID ); ?>">#<?php echo $p->ID; ?></a></td>
							<td><?php echo esc_html( mb_substr( $p->post_title, 0, 60 ) ); ?></td>
							<td><span class="tsvi-ok"><?php echo esc_html( $p->rc_info ); ?></span></td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			</div>
			<?php endif; ?>

			<!-- Errors -->
			<?php if ( $error_posts ) : ?>
			<div class="tsvi-card">
				<h2>
					Errors <span class="tsvi-badge tsvi-badge-failed"><?php echo $errors; ?></span>
					<button class="button button-small" id="tsvi-btn-retry-errors">Retry All Errors</button>
				</h2>
				<table class="wp-list-table widefat striped">
					<thead><tr><th>ID</th><th>Title</th><th>Error</th></tr></thead>
					<tbody>
					<?php foreach ( $error_posts as $p ) : ?>
						<tr>
							<td><a href="<?php echo get_edit_post_link( $p->ID ); ?>">#<?php echo $p->ID; ?></a></td>
							<td><?php echo esc_html( mb_substr( $p->post_title, 0, 60 ) ); ?></td>
							<td><span class="tsvi-err"><?php echo esc_html( mb_substr( $p->error_msg, 0, 100 ) ); ?></span></td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			</div>
			<?php endif; ?>

			<!-- Skipped -->
			<?php if ( $skipped_posts ) : ?>
			<div class="tsvi-card">
				<h2>Skipped <span class="tsvi-badge tsvi-badge-pending"><?php echo $skipped; ?></span></h2>
				<table class="wp-list-table widefat striped">
					<thead><tr><th>ID</th><th>Title</th><th>Reason</th></tr></thead>
					<tbody>
					<?php foreach ( $skipped_posts as $p ) : ?>
						<tr>
							<td><a href="<?php echo get_edit_post_link( $p->ID ); ?>">#<?php echo $p->ID; ?></a></td>
							<td><?php echo esc_html( mb_substr( $p->post_title, 0, 60 ) ); ?></td>
							<td><?php echo esc_html( $p->reason ); ?></td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			</div>
			<?php endif; ?>

			<!-- Recompress log -->
			<?php
			$upload_dir    = wp_upload_dir();
			$recompress_log = $upload_dir['basedir'] . '/tsvi-logs/recompress.log';
			if ( file_exists( $recompress_log ) ) :
				$rlines = file( $recompress_log, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES );
				$rlines = $rlines ? array_slice( $rlines, -80 ) : array();
			?>
			<div class="tsvi-card">
				<h2>Recompress Log</h2>
				<pre style="max-height:400px;overflow:auto;font-size:12px;line-height:1.5;white-space:pre-wrap;"><?php echo esc_html( implode( "\n", array_reverse( $rlines ) ) ); ?></pre>
			</div>
			<?php endif; ?>
		</div>

		<script>
		jQuery('#tsvi-btn-recompress').on('click', function () {
			var btn = jQuery(this);
			var rcAction = btn.data('action') || 'start';
			btn.prop('disabled', true).text(rcAction === 'stop' ? 'Stopping...' : 'Starting...');
			jQuery.post(tsvi.ajax_url, {
				action: 'tsvi_recompress_start',
				nonce: tsvi.nonce,
				rc_action: rcAction
			}).done(function (resp) {
				if (resp.success) {
					btn.text(rcAction === 'stop' ? 'Stopped.' : 'Enabled — worker will pick up in < 1 minute.');
					setTimeout(function () { location.reload(); }, 1500);
				} else {
					btn.text('Error: ' + resp.data).prop('disabled', false);
				}
			}).fail(function () {
				btn.text('Request failed').prop('disabled', false);
			});
		});

		jQuery('#tsvi-btn-retry-errors').on('click', function () {
			if (!confirm('Clear all error markers and retry them on next run?')) return;
			var btn = jQuery(this);
			btn.prop('disabled', true).text('Clearing...');
			jQuery.post(tsvi.ajax_url, {
				action: 'tsvi_recompress_start',
				nonce: tsvi.nonce,
				rc_action: 'retry_errors'
			}).done(function (resp) {
				if (resp.success) {
					btn.text('Cleared ' + resp.data.cleared + ' — reloading...');
					setTimeout(function () { location.reload(); }, 1500);
				} else {
					btn.text('Error').prop('disabled', false);
				}
			});
		});
		// Auto-reload while enabled so progress updates without manual F5.
		<?php if ( get_option( 'tsvi_recompress_enabled', 0 ) ) : ?>
		setTimeout(function () { location.reload(); }, 45000);
		<?php endif; ?>
		</script>
		<?php
	}

	/* ------------------------------------------------------------------
	   AJAX: Toggle recompress processing flag
	   The CLI worker (running via crontab) picks this up on next run.
	   ------------------------------------------------------------------ */

	public static function ajax_recompress_start() {
		check_ajax_referer( 'tsvi_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Unauthorized.' );
		}

		$action = sanitize_text_field( $_POST['rc_action'] ?? 'start' );

		if ( $action === 'stop' ) {
			update_option( 'tsvi_recompress_enabled', 0, false );
			TSVI_Log::write( 'upload', 'Recompress disabled via admin.' );
			wp_send_json_success( array( 'enabled' => false ) );
		}

		if ( $action === 'retry_errors' ) {
			global $wpdb;
			$count = $wpdb->query( "DELETE FROM {$wpdb->postmeta} WHERE meta_key = '_tsvi_recompress_error'" );
			TSVI_Log::write( 'upload', 'Cleared ' . intval( $count ) . ' recompress error markers for retry.' );
			wp_send_json_success( array( 'cleared' => intval( $count ) ) );
		}

		// Start: enable the flag. The CLI worker will pick it up within 1 minute.
		update_option( 'tsvi_recompress_enabled', 1, false );
		TSVI_Log::write( 'upload', 'Recompress enabled via admin.' );

		// Best-effort: also try to spawn recompress.php directly, but don't rely on it.
		$script = TSVI_PATH . 'recompress.php';
		if ( file_exists( $script ) && function_exists( 'exec' ) ) {
			$php_candidates = array( '/usr/bin/php', '/usr/local/bin/php', 'php' );
			foreach ( $php_candidates as $php_bin ) {
				if ( $php_bin === 'php' || is_executable( $php_bin ) ) {
					$cmd = 'nohup ' . escapeshellcmd( $php_bin ) . ' ' . escapeshellarg( $script ) . ' > /dev/null 2>&1 &';
					@exec( $cmd );
					break;
				}
			}
		}

		wp_send_json_success( array( 'enabled' => true ) );
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
		register_setting( 'tsvi_settings', 'tsvi_ytdlp_path' );
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
			<h1>Video Importer Settings <span style="font-size:14px;font-weight:normal;color:#666;">v<?php echo esc_html( TSVI_VERSION ); ?></span></h1>
			<p class="description">Plugin version: <code><?php echo esc_html( TSVI_VERSION ); ?></code> &nbsp;|&nbsp; Path: <code><?php echo esc_html( TSVI_PATH ); ?></code></p>

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

				<h2>yt-dlp Binary Path (optional)</h2>
				<table class="form-table">
					<tr>
						<th>Custom yt-dlp path</th>
						<td>
							<?php $ytdlp_custom = get_option( 'tsvi_ytdlp_path', '' ); ?>
							<input type="text" name="tsvi_ytdlp_path" value="<?php echo esc_attr( $ytdlp_custom ); ?>" class="regular-text" placeholder="/snap/bin/yt-dlp">
							<p class="description">Leave empty to auto-detect. Set if yt-dlp is installed in a non-standard path. Find it via SSH: <code>which yt-dlp</code></p>
						</td>
					</tr>
				</table>

				<?php submit_button(); ?>
			</form>

			<h2>FFmpeg Transcode</h2>
			<p class="description">Videos are automatically transcoded to 720p H.264 before uploading to Bunny CDN. Reduces file size by ~75%. If FFmpeg is not installed, videos are uploaded in original quality (no errors).</p>
			<p class="description">
				<?php
				if ( TSVI_Bunny::ffmpeg_available() ) {
					echo '<span class="tsvi-ok">FFmpeg: installed</span>';
				} else {
					echo '<span class="tsvi-warn">FFmpeg: NOT detected — videos will upload at original size.</span>';
					echo '<br>Install: <code>apt install -y ffmpeg</code>';
				}
				?>
			</p>

			<h2>CLI Worker (recommended)</h2>
			<p class="description">The CLI worker processes uploads in the background without web server timeouts. Add this line to your server crontab (<code>crontab -e</code>):</p>
			<pre style="background:#1d2327;color:#50c878;padding:12px;border-radius:4px;overflow-x:auto;">* * * * * php <?php echo esc_html( TSVI_PATH . 'worker.php' ); ?> >> /dev/null 2>&amp;1</pre>
			<p class="description">
				<?php
				$cli_lock = get_transient( 'tsvi_cli_lock' );
				if ( $cli_lock ) {
					echo '<span class="tsvi-ok">CLI Worker: active (PID ' . esc_html( $cli_lock ) . ')</span>';
				} else {
					echo '<span class="tsvi-warn">CLI Worker: not detected — set up the crontab above for reliable uploads.</span>';
				}
				?>
				<br>Manual check: <code>php <?php echo esc_html( TSVI_PATH . 'worker.php' ); ?> --status</code>
			</p>

			<h2>Video Extractors</h2>
			<p class="description">The plugin uses multiple strategies to resolve video URLs from hoster pages.</p>

			<table class="form-table">
				<tr>
					<th>yt-dlp</th>
					<td>
						<?php
						if ( TSVI_Scraper::ytdlp_available() ) {
							$bin = TSVI_Scraper::ytdlp_binary_path();
							echo '<span class="tsvi-ok">Installed</span> — version ' . esc_html( TSVI_Scraper::ytdlp_version() );
							echo '<br><small>Binary: <code>' . esc_html( $bin ) . '</code></small>';
						} else {
							echo '<span class="tsvi-warn">NOT detected</span>';
						}
						?>
						<p class="description">Handles Streamtape, Mixdrop, Fembed, Upstream, and 1000+ other hosters.</p>
					</td>
				</tr>
				<tr>
					<th>Native Doodstream</th>
					<td>
						<?php
						if ( TSVI_Scraper::doodstream_native_available() ) {
							echo '<span class="tsvi-ok">Available</span>';
							echo '<br><small>Script: <code>' . esc_html( TSVI_PATH . 'bin/doodstream.py' ) . '</code></small>';
						} else {
							echo '<span class="tsvi-warn">Script or python3 not found</span>';
						}
						?>
						<p class="description">Custom Python extractor for Doodstream/playmogo (yt-dlp removed this extractor).</p>
					</td>
				</tr>
			</table>

			<h3>Install / update everything</h3>
			<pre style="background:#1d2327;color:#50c878;padding:12px;border-radius:4px;overflow-x:auto;">snap remove yt-dlp 2&gt;/dev/null
apt install -y python3-pip
pip install -U "yt-dlp[default,curl-cffi]" --break-system-packages

# Verify:
yt-dlp --version
python3 -c "import curl_cffi; print('curl_cffi OK')"</pre>
			<p class="description">Updates (run monthly): <code>pip install -U "yt-dlp[default,curl-cffi]" --break-system-packages</code></p>
		</div>
		<?php
	}

	/* ------------------------------------------------------------------
	   Activity Log page
	   ------------------------------------------------------------------ */

	public static function page_log() {
		$files   = TSVI_Log::get_log_files();
		$current = sanitize_file_name( $_GET['log_file'] ?? '' );
		$content = '';

		if ( $current && in_array( $current, $files, true ) ) {
			$content = TSVI_Log::read_file( $current, 300 );
		} elseif ( ! empty( $files ) ) {
			$current = end( $files );
			$content = TSVI_Log::read_file( $current, 300 );
		}
		?>
		<div class="wrap">
			<h1>Activity Log</h1>

			<?php if ( ! empty( $files ) ) : ?>
			<p>
				<?php foreach ( $files as $f ) : ?>
					<?php if ( $f === $current ) : ?>
						<strong><?php echo esc_html( $f ); ?></strong>
					<?php else : ?>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=tsvi-log&log_file=' . $f ) ); ?>"><?php echo esc_html( $f ); ?></a>
					<?php endif; ?>
					&nbsp;
				<?php endforeach; ?>
			</p>
			<?php endif; ?>

			<div class="tsvi-card">
				<?php if ( $content ) : ?>
					<pre style="max-height:600px;overflow:auto;font-size:12px;line-height:1.5;white-space:pre-wrap;"><?php echo esc_html( $content ); ?></pre>
				<?php else : ?>
					<p>No log entries yet. Logs are created when imports, uploads, or errors occur.</p>
				<?php endif; ?>
			</div>

			<?php
			// Also show worker.log if it exists.
			$upload_dir = wp_upload_dir();
			$worker_log = $upload_dir['basedir'] . '/tsvi-logs/worker.log';
			// Check legacy location for backward compat.
			if ( ! file_exists( $worker_log ) && file_exists( TSVI_PATH . 'worker.log' ) ) {
				$worker_log = TSVI_PATH . 'worker.log';
			}
			if ( file_exists( $worker_log ) ) :
				$wlines = file( $worker_log, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES );
				$wlines = $wlines ? array_slice( $wlines, -100 ) : array();
			?>
			<div class="tsvi-card">
				<h2>CLI Worker Log</h2>
				<pre style="max-height:400px;overflow:auto;font-size:12px;line-height:1.5;white-space:pre-wrap;"><?php echo esc_html( implode( "\n", array_reverse( $wlines ) ) ); ?></pre>
			</div>
			<?php endif; ?>
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
	   AJAX: Queue URLs for direct upload (instant response, no processing)
	   ------------------------------------------------------------------ */

	public static function ajax_direct_queue() {
		check_ajax_referer( 'tsvi_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Unauthorized.' );
		}

		$raw  = sanitize_textarea_field( $_POST['urls'] ?? '' );
		$urls = array_filter( array_map( 'trim', explode( "\n", $raw ) ) );
		$urls = array_map( 'esc_url_raw', $urls );
		$urls = array_filter( $urls );

		if ( empty( $urls ) ) {
			wp_send_json_error( 'No valid URLs.' );
		}

		global $wpdb;

		$queue        = get_option( 'tsvi_direct_upload_queue', array() );
		$existing_urls = array_column( $queue, 'url' );

		$added   = 0;
		$skipped = 0;
		foreach ( $urls as $url ) {
			// Skip if already queued.
			if ( in_array( $url, $existing_urls, true ) ) {
				$skipped++;
				continue;
			}
			// Skip if already imported as a post.
			$existing = $wpdb->get_var( $wpdb->prepare(
				"SELECT post_id FROM {$wpdb->postmeta}
				 WHERE meta_key = '_tsvi_source_url' AND meta_value = %s LIMIT 1",
				$url
			) );
			if ( $existing ) {
				$skipped++;
				continue;
			}
			$queue[]         = array(
				'url'       => $url,
				'queued_at' => current_time( 'Y-m-d H:i' ),
			);
			$existing_urls[] = $url;
			$added++;
		}
		update_option( 'tsvi_direct_upload_queue', $queue, false );

		// Trigger background processing.
		TSVI_Bunny::schedule_direct_upload();

		wp_send_json_success( array(
			'queued'  => $added,
			'skipped' => $skipped,
		) );
	}

	/* ------------------------------------------------------------------
	   AJAX: Clear direct upload history
	   ------------------------------------------------------------------ */

	public static function ajax_direct_clear_history() {
		check_ajax_referer( 'tsvi_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Unauthorized.' );
		}

		update_option( 'tsvi_direct_upload_history', array(), false );
		wp_send_json_success();
	}

	/* ------------------------------------------------------------------
	   Upload File page — upload a local video, compress, push to Bunny,
	   create and publish a post (same end-state as scrape).
	   ------------------------------------------------------------------ */

	public static function page_upload_file() {
		$queue   = get_option( 'tsvi_file_upload_queue', array() );
		$history = array_reverse( get_option( 'tsvi_file_upload_history', array() ) );

		$queue_count   = count( $queue );
		$history_count = count( $history );

		$max_upload = wp_max_upload_size();
		$max_mb     = round( $max_upload / 1048576 );

		// Scan tsvi-uploads folder for files dropped via SFTP.
		$upload_dir = wp_upload_dir();
		$scan_dir   = $upload_dir['basedir'] . '/tsvi-uploads';

		// Auto-create the folder so the user can drop files immediately via SFTP.
		if ( ! is_dir( $scan_dir ) ) {
			wp_mkdir_p( $scan_dir );
			@file_put_contents( $scan_dir . '/.htaccess', "Deny from all\n" );
			@file_put_contents( $scan_dir . '/index.html', '' );
		}

		$scan_files = self::scan_upload_folder( $scan_dir );

		// Build a set of file paths already enqueued so we don't list them twice.
		$queued_paths = array_column( $queue, 'path' );
		?>
		<div class="wrap">
			<h1>Upload Video File</h1>
			<p class="description">Each file is compressed to 720p (FFmpeg), uploaded to Bunny CDN, then a post is created with the CDN URL as title (status: <strong>pending review</strong>, same as Direct Upload by URL).</p>

			<?php if ( ! TSVI_Bunny::is_enabled() ) : ?>
				<div class="notice notice-error"><p>Bunny CDN is not configured. Go to <a href="<?php echo admin_url( 'admin.php?page=tsvi-settings' ); ?>">Settings</a> first.</p></div>
			<?php endif; ?>

			<?php if ( ! TSVI_Bunny::ffmpeg_available() ) : ?>
				<div class="notice notice-warning"><p>FFmpeg not detected — videos will be uploaded at original size (no compression).</p></div>
			<?php endif; ?>

			<!-- ============ A. Scan folder (SFTP) ============ -->
			<div class="tsvi-card">
				<h2>Drop files via SFTP / FileZilla <span class="tsvi-badge tsvi-badge-done">recommended for large files</span></h2>
				<p class="description">
					Upload your videos via SFTP into:<br>
					<code><?php echo esc_html( $scan_dir ); ?></code><br>
					Then click <strong>Scan & Enqueue</strong> below. This bypasses PHP/nginx/Cloudflare upload limits — works for files of any size.
				</p>

				<?php if ( ! is_dir( $scan_dir ) ) : ?>
					<p><span class="tsvi-warn">Folder does not exist yet — it will be created on the first browser upload below, or you can <code>mkdir <?php echo esc_html( $scan_dir ); ?></code> manually.</span></p>
				<?php elseif ( empty( $scan_files ) ) : ?>
					<p><em>No video files found in the folder.</em>
					<button class="button" id="tsvi-btn-rescan">Refresh</button></p>
				<?php else : ?>
					<p>
						<button class="button button-primary" id="tsvi-btn-enqueue-all">Enqueue All (<?php echo count( $scan_files ); ?>)</button>
						<button class="button" id="tsvi-btn-rescan">Refresh</button>
					</p>
					<table class="wp-list-table widefat striped">
						<thead><tr><th class="check-column"><input type="checkbox" id="tsvi-scan-all"></th><th>File</th><th>Size</th><th>Modified</th><th>Status</th></tr></thead>
						<tbody>
						<?php foreach ( $scan_files as $f ) :
							$already = in_array( $f['path'], $queued_paths, true );
							?>
							<tr>
								<td class="check-column">
									<?php if ( ! $already ) : ?>
										<input type="checkbox" class="tsvi-scan-check" value="<?php echo esc_attr( $f['path'] ); ?>">
									<?php endif; ?>
								</td>
								<td><small><?php echo esc_html( $f['name'] ); ?></small></td>
								<td><small><?php echo esc_html( size_format( $f['size'], 1 ) ); ?></small></td>
								<td><small><?php echo esc_html( date( 'Y-m-d H:i', $f['mtime'] ) ); ?></small></td>
								<td>
									<?php if ( $already ) : ?>
										<span class="tsvi-warn">already queued</span>
									<?php else : ?>
										<span class="tsvi-ok">ready</span>
									<?php endif; ?>
								</td>
							</tr>
						<?php endforeach; ?>
						</tbody>
					</table>
					<p>
						<button class="button button-primary" id="tsvi-btn-enqueue-selected">Enqueue Selected</button>
					</p>
				<?php endif; ?>
			</div>

			<!-- ============ B. Browser upload (small files) ============ -->
			<div class="tsvi-card">
				<h2>Or upload from your computer <span class="tsvi-badge tsvi-badge-pending">small files only</span></h2>
				<p class="description">For files under ~<?php echo esc_html( $max_mb ); ?> MB. Larger files: use SFTP above.</p>
				<form id="tsvi-file-upload-form" enctype="multipart/form-data">
					<p>
						<input type="file" name="tsvi_file" accept="video/*" required>
					</p>
					<p>
						<button type="submit" class="button button-primary" id="tsvi-btn-upload-file">Upload to Server</button>
					</p>
					<progress id="tsvi-upload-progress" value="0" max="100" style="display:none;width:100%;height:24px;"></progress>
					<p id="tsvi-upload-status" style="display:none;"></p>
				</form>
			</div>

			<!-- ============ C. Pending queue ============ -->
			<?php if ( $queue_count > 0 ) : ?>
			<div class="tsvi-card">
				<h2>Processing Queue <span class="tsvi-badge tsvi-badge-pending"><?php echo $queue_count; ?></span></h2>
				<table class="wp-list-table widefat striped">
					<thead><tr><th>#</th><th>File</th><th>Queued</th></tr></thead>
					<tbody>
					<?php foreach ( $queue as $i => $item ) : ?>
						<tr>
							<td><?php echo $i + 1; ?></td>
							<td><small><?php echo esc_html( $item['filename'] ?? '?' ); ?></small></td>
							<td><small><?php echo esc_html( $item['queued_at'] ?? '' ); ?></small></td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			</div>
			<?php endif; ?>

			<div class="tsvi-card">
				<h2>
					Upload History
					<span class="tsvi-badge tsvi-badge-done"><?php echo $history_count; ?></span>
					<?php if ( $history ) : ?>
						<button class="button button-small tsvi-btn-danger" id="tsvi-btn-clear-file-history" onclick="return confirm('Clear all file upload history?');">Clear History</button>
					<?php endif; ?>
				</h2>
				<?php if ( $history ) : ?>
					<table class="wp-list-table widefat striped">
						<thead><tr><th style="width:90px;">Date</th><th>File</th><th>CDN URL</th><th style="width:90px;">Status</th><th style="width:160px;">Post</th></tr></thead>
						<tbody>
						<?php foreach ( $history as $h ) :
							$post_status = '';
							$post_exists = false;
							if ( ! empty( $h['post_id'] ) ) {
								$post_obj = get_post( $h['post_id'] );
								if ( $post_obj ) {
									$post_exists = true;
									$post_status = $post_obj->post_status;
								}
							}
							?>
							<tr>
								<td><small><?php echo esc_html( $h['date'] ); ?></small></td>
								<td><small><?php echo esc_html( $h['source'] ); ?></small></td>
								<td>
									<?php if ( ! empty( $h['cdn_url'] ) ) : ?>
										<input type="text" readonly value="<?php echo esc_attr( $h['cdn_url'] ); ?>" class="regular-text tsvi-copy-field" onclick="this.select();document.execCommand('copy');">
									<?php else : ?>
										—
									<?php endif; ?>
								</td>
								<td>
									<?php if ( ! empty( $h['error'] ) ) : ?>
										<span class="tsvi-err" title="<?php echo esc_attr( $h['error'] ); ?>"><?php echo esc_html( mb_substr( $h['error'], 0, 30 ) ); ?></span>
									<?php else : ?>
										<span class="tsvi-ok">OK</span>
									<?php endif; ?>
								</td>
								<td>
									<?php if ( ! $post_exists && ! empty( $h['post_id'] ) ) : ?>
										<span class="tsvi-err">deleted</span>
									<?php elseif ( $post_exists && $post_status === 'publish' ) : ?>
										<span class="tsvi-ok">published</span>
										<a class="button button-small" href="<?php echo esc_url( get_edit_post_link( $h['post_id'] ) ); ?>" target="_blank">Edit #<?php echo intval( $h['post_id'] ); ?></a>
									<?php elseif ( $post_exists ) : ?>
										<span class="tsvi-warn"><?php echo esc_html( $post_status ); ?></span>
										<a class="button button-small" href="<?php echo esc_url( get_edit_post_link( $h['post_id'] ) ); ?>" target="_blank">Edit #<?php echo intval( $h['post_id'] ); ?></a>
									<?php else : ?>
										—
									<?php endif; ?>
								</td>
							</tr>
						<?php endforeach; ?>
						</tbody>
					</table>
				<?php else : ?>
					<p>No uploads yet.</p>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}

	/* ------------------------------------------------------------------
	   AJAX: Receive an uploaded video file, queue it for processing.
	   ------------------------------------------------------------------ */

	public static function ajax_upload_file() {
		check_ajax_referer( 'tsvi_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Unauthorized.' );
		}

		if ( empty( $_FILES['tsvi_file'] ) || ! isset( $_FILES['tsvi_file']['tmp_name'] ) ) {
			wp_send_json_error( 'No file uploaded.' );
		}

		$file = $_FILES['tsvi_file'];

		if ( $file['error'] !== UPLOAD_ERR_OK ) {
			$messages = array(
				UPLOAD_ERR_INI_SIZE   => 'File exceeds server upload_max_filesize.',
				UPLOAD_ERR_FORM_SIZE  => 'File exceeds form max size.',
				UPLOAD_ERR_PARTIAL    => 'File was only partially uploaded.',
				UPLOAD_ERR_NO_FILE    => 'No file uploaded.',
				UPLOAD_ERR_NO_TMP_DIR => 'Missing temp folder on server.',
				UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk.',
				UPLOAD_ERR_EXTENSION  => 'A PHP extension blocked the upload.',
			);
			wp_send_json_error( $messages[ $file['error'] ] ?? ( 'Upload error code ' . $file['error'] ) );
		}

		$orig_name = sanitize_file_name( $file['name'] );
		$ext       = strtolower( pathinfo( $orig_name, PATHINFO_EXTENSION ) );

		$allowed = array( 'mp4', 'webm', 'mov', 'mkv', 'avi', 'm4v' );
		if ( ! in_array( $ext, $allowed, true ) ) {
			wp_send_json_error( 'Unsupported file type: ' . $ext );
		}

		// Store the uploaded file in a private folder under uploads.
		$upload_dir = wp_upload_dir();
		$queue_dir  = $upload_dir['basedir'] . '/tsvi-uploads';
		if ( ! is_dir( $queue_dir ) ) {
			wp_mkdir_p( $queue_dir );
			file_put_contents( $queue_dir . '/.htaccess', "Deny from all\n" );
			file_put_contents( $queue_dir . '/index.html', '' );
		}

		$dest_name = uniqid( 'tsvi_', true ) . '.' . $ext;
		$dest_path = $queue_dir . '/' . $dest_name;

		if ( ! @move_uploaded_file( $file['tmp_name'], $dest_path ) ) {
			wp_send_json_error( 'Failed to save uploaded file to ' . $queue_dir );
		}

		// Append to the file queue.
		$queue   = get_option( 'tsvi_file_upload_queue', array() );
		$queue[] = array(
			'path'      => $dest_path,
			'filename'  => $orig_name,
			'author'    => get_current_user_id(),
			'queued_at' => current_time( 'Y-m-d H:i' ),
		);
		update_option( 'tsvi_file_upload_queue', $queue, false );

		TSVI_Bunny::schedule_file_upload();

		TSVI_Log::write( 'import', 'File queued for processing: ' . $orig_name );

		wp_send_json_success( array(
			'queued'   => 1,
			'filename' => $orig_name,
			'size_mb'  => round( $file['size'] / 1048576, 2 ),
		) );
	}

	public static function ajax_file_clear_history() {
		check_ajax_referer( 'tsvi_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Unauthorized.' );
		}
		update_option( 'tsvi_file_upload_history', array(), false );
		wp_send_json_success();
	}

	/**
	 * List video files inside the tsvi-uploads folder (used by the SFTP flow).
	 */
	private static function scan_upload_folder( $dir ) {
		if ( ! is_dir( $dir ) ) {
			return array();
		}
		$allowed = array( 'mp4', 'webm', 'mov', 'mkv', 'avi', 'm4v' );
		$results = array();
		$entries = @scandir( $dir );
		if ( ! $entries ) {
			return array();
		}
		foreach ( $entries as $entry ) {
			if ( $entry === '.' || $entry === '..' || $entry[0] === '.' ) {
				continue;
			}
			$path = $dir . '/' . $entry;
			if ( ! is_file( $path ) ) {
				continue;
			}
			$ext = strtolower( pathinfo( $entry, PATHINFO_EXTENSION ) );
			if ( ! in_array( $ext, $allowed, true ) ) {
				continue;
			}
			$results[] = array(
				'path'  => $path,
				'name'  => $entry,
				'size'  => filesize( $path ),
				'mtime' => filemtime( $path ),
			);
		}
		// Newest first.
		usort( $results, function ( $a, $b ) {
			return $b['mtime'] - $a['mtime'];
		} );
		return $results;
	}

	/**
	 * AJAX: enqueue a list of SFTP-dropped files into the file upload queue.
	 * Files must already live inside wp-content/uploads/tsvi-uploads/.
	 */
	public static function ajax_enqueue_scanned() {
		check_ajax_referer( 'tsvi_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Unauthorized.' );
		}

		$paths_raw = $_POST['paths'] ?? array();
		if ( ! is_array( $paths_raw ) ) {
			$paths_raw = array( $paths_raw );
		}

		$upload_dir = wp_upload_dir();
		$scan_dir   = realpath( $upload_dir['basedir'] . '/tsvi-uploads' );
		if ( ! $scan_dir ) {
			wp_send_json_error( 'Scan folder does not exist.' );
		}

		$queue          = get_option( 'tsvi_file_upload_queue', array() );
		$existing_paths = array_column( $queue, 'path' );

		$added   = 0;
		$skipped = 0;
		foreach ( $paths_raw as $raw ) {
			$path = sanitize_text_field( wp_unslash( $raw ) );
			$real = realpath( $path );
			// Security: must resolve to a file inside scan_dir.
			if ( ! $real || strpos( $real, $scan_dir . DIRECTORY_SEPARATOR ) !== 0 ) {
				$skipped++;
				continue;
			}
			if ( ! is_file( $real ) ) {
				$skipped++;
				continue;
			}
			if ( in_array( $real, $existing_paths, true ) ) {
				$skipped++;
				continue;
			}
			$queue[]          = array(
				'path'      => $real,
				'filename'  => basename( $real ),
				'author'    => get_current_user_id(),
				'queued_at' => current_time( 'Y-m-d H:i' ),
			);
			$existing_paths[] = $real;
			$added++;
		}

		update_option( 'tsvi_file_upload_queue', $queue, false );

		if ( $added > 0 ) {
			TSVI_Bunny::schedule_file_upload();
			TSVI_Log::write( 'import', "Enqueued {$added} SFTP files (skipped {$skipped})." );
		}

		wp_send_json_success( array(
			'queued'  => $added,
			'skipped' => $skipped,
		) );
	}
}

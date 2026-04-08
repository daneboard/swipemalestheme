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

		// Handle manual retry.
		if ( isset( $_GET['tsvi_retry'] ) && wp_verify_nonce( $_GET['_wpnonce'] ?? '', 'tsvi_retry' ) ) {
			$retry_id  = intval( $_GET['tsvi_retry'] );
			$source    = get_post_meta( $retry_id, '_tsvi_source_url', true );
			$video_url = get_post_meta( $retry_id, 'video_url', true );
			$pending   = $source ?: $video_url;
			if ( $pending ) {
				update_post_meta( $retry_id, '_tsvi_bunny_pending', $pending );
				delete_post_meta( $retry_id, '_tsvi_bunny_error' );
				delete_post_meta( $retry_id, '_tsvi_bunny_status' );
				TSVI_Bunny::schedule_upload( $retry_id );
			}
			wp_safe_redirect( admin_url( 'admin.php?page=tsvi-queue&retried=' . $retry_id ) );
			exit;
		}

		// Fetch all posts with Bunny-related meta.
		$pending_posts = $wpdb->get_results(
			"SELECT p.ID, p.post_title, pm.meta_value as pending_url
			 FROM {$wpdb->posts} p
			 JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
			 WHERE pm.meta_key = '_tsvi_bunny_pending'
			 AND pm.meta_value != ''
			 ORDER BY p.ID DESC
			 LIMIT 50"
		);

		$done_posts = $wpdb->get_results(
			"SELECT p.ID, p.post_title, pm.meta_value as bunny_status
			 FROM {$wpdb->posts} p
			 JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
			 WHERE pm.meta_key = '_tsvi_bunny_status'
			 AND pm.meta_value = 'uploaded'
			 ORDER BY p.ID DESC
			 LIMIT 50"
		);

		$failed_posts = $wpdb->get_results(
			"SELECT p.ID, p.post_title, pm.meta_value as error_msg
			 FROM {$wpdb->posts} p
			 JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
			 WHERE pm.meta_key = '_tsvi_bunny_error'
			 AND pm.meta_value != ''
			 ORDER BY p.ID DESC
			 LIMIT 50"
		);

		$pending_count = count( $pending_posts );
		$done_count    = count( $done_posts );
		$failed_count  = count( $failed_posts );

		if ( isset( $_GET['retried'] ) ) {
			echo '<div class="notice notice-success is-dismissible"><p>Post #' . intval( $_GET['retried'] ) . ' re-queued for CDN upload.</p></div>';
		}
		?>
		<div class="wrap">
			<h1>CDN Upload Queue</h1>

			<!-- Pending -->
			<div class="tsvi-card">
				<h2>Pending <span class="tsvi-badge tsvi-badge-pending"><?php echo $pending_count; ?></span></h2>
				<?php if ( $pending_posts ) : ?>
					<table class="wp-list-table widefat striped">
						<thead><tr><th>ID</th><th>Title</th><th>Source URL</th></tr></thead>
						<tbody>
						<?php foreach ( $pending_posts as $p ) : ?>
							<tr>
								<td><a href="<?php echo get_edit_post_link( $p->ID ); ?>">#<?php echo $p->ID; ?></a></td>
								<td><?php echo esc_html( $p->post_title ); ?></td>
								<td><small><?php echo esc_html( mb_substr( $p->pending_url, 0, 80 ) ); ?>...</small></td>
							</tr>
						<?php endforeach; ?>
						</tbody>
					</table>
				<?php else : ?>
					<p>No uploads pending.</p>
				<?php endif; ?>
			</div>

			<!-- Failed -->
			<div class="tsvi-card">
				<h2>Failed <span class="tsvi-badge tsvi-badge-failed"><?php echo $failed_count; ?></span></h2>
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
									<a class="button button-small" href="<?php echo wp_nonce_url( admin_url( 'admin.php?page=tsvi-queue&tsvi_retry=' . $p->ID ), 'tsvi_retry' ); ?>">Retry</a>
								</td>
							</tr>
						<?php endforeach; ?>
						</tbody>
					</table>
				<?php else : ?>
					<p>No failed uploads.</p>
				<?php endif; ?>
			</div>

			<!-- Done -->
			<div class="tsvi-card">
				<h2>Uploaded <span class="tsvi-badge tsvi-badge-done"><?php echo $done_count; ?></span></h2>
				<?php if ( $done_posts ) : ?>
					<table class="wp-list-table widefat striped">
						<thead><tr><th>ID</th><th>Title</th><th>CDN URL</th></tr></thead>
						<tbody>
						<?php foreach ( $done_posts as $p ) : ?>
							<tr>
								<td><a href="<?php echo get_edit_post_link( $p->ID ); ?>">#<?php echo $p->ID; ?></a></td>
								<td><?php echo esc_html( $p->post_title ); ?></td>
								<td><small><?php echo esc_html( mb_substr( get_post_meta( $p->ID, 'video_url', true ), 0, 80 ) ); ?>...</small></td>
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
}

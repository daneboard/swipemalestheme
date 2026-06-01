<?php
/**
 * Admin UI: menus, settings, edit.php column, metabox, and AJAX endpoints.
 */

defined( 'ABSPATH' ) || exit;

class TSRP_Admin {

	const NONCE = 'tsrp_admin';

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'register_menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue' ) );

		// Column on posts list.
		add_filter( 'manage_post_posts_columns', array( __CLASS__, 'add_column' ) );
		add_action( 'manage_post_posts_custom_column', array( __CLASS__, 'render_column' ), 10, 2 );

		// Metabox.
		add_action( 'add_meta_boxes', array( __CLASS__, 'add_metabox' ) );

		// AJAX.
		$ajax = array(
			'tsrp_get_post_data',
			'tsrp_search_subs',
			'tsrp_get_sub_info',
			'tsrp_submit',
			'tsrp_refresh_stats',
			'tsrp_get_submissions',
			'tsrp_delete_submission',
		);
		foreach ( $ajax as $action ) {
			add_action( 'wp_ajax_' . $action, array( __CLASS__, str_replace( 'tsrp_', 'ajax_', $action ) ) );
		}
	}

	/* ------------------------------------------------------------------
	   Menus & settings
	   ------------------------------------------------------------------ */

	public static function register_menu() {
		add_menu_page(
			'Reddit Poster',
			'Reddit Poster',
			'edit_posts',
			'tsrp-submissions',
			array( __CLASS__, 'page_submissions' ),
			'dashicons-share-alt2',
			31
		);
		add_submenu_page( 'tsrp-submissions', 'Submissions', 'Submissions', 'edit_posts', 'tsrp-submissions', array( __CLASS__, 'page_submissions' ) );
		add_submenu_page( 'tsrp-submissions', 'My Connection', 'My Connection', 'edit_posts', 'tsrp-connection', array( __CLASS__, 'page_connection' ) );
		add_submenu_page( 'tsrp-submissions', 'Settings', 'Settings', 'manage_options', 'tsrp-settings', array( __CLASS__, 'page_settings' ) );
		add_submenu_page( 'tsrp-submissions', 'Log', 'Log', 'manage_options', 'tsrp-log', array( __CLASS__, 'page_log' ) );
	}

	public static function register_settings() {
		register_setting( 'tsrp_settings', 'tsrp_client_id', array( 'sanitize_callback' => 'sanitize_text_field' ) );
		register_setting( 'tsrp_settings', 'tsrp_client_secret', array(
			'sanitize_callback' => function ( $v ) {
				$v = trim( (string) $v );
				if ( $v === '' ) {
					// keep existing if the field was left blank
					return get_option( 'tsrp_client_secret', '' );
				}
				if ( $v === '********' ) {
					return get_option( 'tsrp_client_secret', '' );
				}
				return TSRP_Crypto::encrypt( $v );
			},
		) );
		register_setting( 'tsrp_settings', 'tsrp_user_agent', array( 'sanitize_callback' => 'sanitize_text_field' ) );
		register_setting( 'tsrp_settings', 'tsrp_comment_template', array(
			'sanitize_callback' => function ( $v ) {
				return wp_kses_post( (string) $v );
			},
		) );
	}

	public static function enqueue( $hook ) {
		$screens = array( 'edit.php', 'post.php', 'post-new.php', 'toplevel_page_tsrp-submissions' );
		$is_tsrp = strpos( (string) $hook, 'tsrp' ) !== false;
		if ( ! in_array( $hook, $screens, true ) && ! $is_tsrp ) {
			return;
		}
		wp_enqueue_style( 'tsrp-admin', TSRP_URL . 'assets/admin.css', array(), TSRP_VERSION );
		wp_enqueue_script( 'tsrp-admin', TSRP_URL . 'assets/admin.js', array( 'jquery' ), TSRP_VERSION, true );
		wp_localize_script( 'tsrp-admin', 'TSRP', array(
			'ajax'       => admin_url( 'admin-ajax.php' ),
			'nonce'      => wp_create_nonce( self::NONCE ),
			'connected'  => TSRP_OAuth::is_user_connected(),
			'configured' => TSRP_OAuth::is_configured(),
			'connectUrl' => admin_url( 'admin.php?page=tsrp-connection' ),
			'i18n'       => array(
				'post'        => __( 'Post to Reddit', 'tikswipe-reddit-poster' ),
				'view'        => __( 'View on Reddit', 'tikswipe-reddit-poster' ),
				'add'         => __( 'Post to another subreddit', 'tikswipe-reddit-poster' ),
				'connect'     => __( 'Connect your Reddit account first', 'tikswipe-reddit-poster' ),
				'loading'     => __( 'Loading…', 'tikswipe-reddit-poster' ),
				'submitting'  => __( 'Submitting…', 'tikswipe-reddit-poster' ),
				'submit'      => __( 'Submit', 'tikswipe-reddit-poster' ),
				'titleTooLong'=> __( 'Title is too long for this subreddit.', 'tikswipe-reddit-poster' ),
				'titleTooShort'=> __( 'Title is too short for this subreddit.', 'tikswipe-reddit-poster' ),
				'flairReq'    => __( 'This subreddit requires a flair.', 'tikswipe-reddit-poster' ),
			),
		) );
	}

	/* ------------------------------------------------------------------
	   Column on Posts list
	   ------------------------------------------------------------------ */

	public static function add_column( $cols ) {
		$cols['tsrp_reddit'] = __( 'Reddit', 'tikswipe-reddit-poster' );
		return $cols;
	}

	public static function render_column( $col, $post_id ) {
		if ( $col !== 'tsrp_reddit' ) {
			return;
		}
		$subs = TSRP_DB::get_submissions_for_post( $post_id );
		echo '<div class="tsrp-col" data-post="' . (int) $post_id . '">';
		if ( ! $subs ) {
			echo '<button type="button" class="button tsrp-post-btn" data-post="' . (int) $post_id . '">' . esc_html__( 'Post to Reddit', 'tikswipe-reddit-poster' ) . '</button>';
		} else {
			foreach ( $subs as $s ) {
				self::render_submission_row( $s );
			}
			echo '<button type="button" class="button button-small tsrp-post-btn" data-post="' . (int) $post_id . '" style="margin-top:4px;">' . esc_html__( '+ Add subreddit', 'tikswipe-reddit-poster' ) . '</button>';
		}
		echo '</div>';
	}

	protected static function render_submission_row( $s ) {
		$stats = ! empty( $s['last_stats'] ) ? json_decode( $s['last_stats'], true ) : null;
		$label = 'r/' . esc_html( $s['subreddit'] );
		echo '<div class="tsrp-sub-row" data-sub-id="' . (int) $s['id'] . '" data-fullname="' . esc_attr( $s['reddit_fullname'] ) . '">';
		echo '<a href="' . esc_url( $s['reddit_url'] ) . '" target="_blank" rel="noopener" class="tsrp-link">' . $label . '</a>';
		echo ' <span class="tsrp-stats">' . esc_html( TSRP_Analytics::format_badge( $stats ) ) . '</span>';
		if ( ! empty( $s['last_error'] ) ) {
			echo ' <span class="tsrp-err" title="' . esc_attr( $s['last_error'] ) . '">⚠</span>';
		}
		if ( ! empty( $s['comment_status'] ) ) {
			$cls = $s['comment_status'] === 'posted' ? 'ok' : ( $s['comment_status'] === 'failed' ? 'err' : 'pending' );
			echo ' <span class="tsrp-cstat tsrp-' . esc_attr( $cls ) . '">✎ ' . esc_html( $s['comment_status'] ) . '</span>';
		}
		echo '</div>';
	}

	/* ------------------------------------------------------------------
	   Metabox
	   ------------------------------------------------------------------ */

	public static function add_metabox() {
		add_meta_box(
			'tsrp-metabox',
			__( 'Reddit', 'tikswipe-reddit-poster' ),
			array( __CLASS__, 'render_metabox' ),
			'post',
			'side',
			'high'
		);
	}

	public static function render_metabox( $post ) {
		$subs = TSRP_DB::get_submissions_for_post( $post->ID );
		echo '<div class="tsrp-metabox" data-post="' . (int) $post->ID . '">';
		if ( ! TSRP_OAuth::is_user_connected() ) {
			echo '<p>' . sprintf(
				/* translators: %s: link to the connection page */
				wp_kses_post( __( 'Connect your Reddit account at <a href="%s">My Connection</a> to enable posting.', 'tikswipe-reddit-poster' ) ),
				esc_url( admin_url( 'admin.php?page=tsrp-connection' ) )
			) . '</p>';
			echo '</div>';
			return;
		}
		echo '<div class="tsrp-submissions-list">';
		if ( $subs ) {
			foreach ( $subs as $s ) {
				self::render_submission_row( $s );
			}
		} else {
			echo '<p class="description">' . esc_html__( 'Not posted to Reddit yet.', 'tikswipe-reddit-poster' ) . '</p>';
		}
		echo '</div>';
		echo '<p><button type="button" class="button button-primary tsrp-post-btn" data-post="' . (int) $post->ID . '">' . esc_html__( 'Post to Reddit', 'tikswipe-reddit-poster' ) . '</button></p>';
		echo '</div>';
	}

	/* ------------------------------------------------------------------
	   Pages
	   ------------------------------------------------------------------ */

	public static function page_settings() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$secret = get_option( 'tsrp_client_secret', '' );
		$secret_display = $secret ? '********' : '';
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Reddit Poster — Settings', 'tikswipe-reddit-poster' ); ?></h1>
			<p><?php esc_html_e( 'Create a Reddit "web app" at https://www.reddit.com/prefs/apps and register the redirect URI below.', 'tikswipe-reddit-poster' ); ?></p>
			<p><strong><?php esc_html_e( 'Redirect URI:', 'tikswipe-reddit-poster' ); ?></strong>
				<code><?php echo esc_html( TSRP_OAuth::redirect_uri() ); ?></code></p>
			<form method="post" action="options.php">
				<?php settings_fields( 'tsrp_settings' ); ?>
				<table class="form-table">
					<tr>
						<th><label for="tsrp_client_id"><?php esc_html_e( 'Client ID', 'tikswipe-reddit-poster' ); ?></label></th>
						<td><input type="text" name="tsrp_client_id" id="tsrp_client_id" class="regular-text" value="<?php echo esc_attr( get_option( 'tsrp_client_id', '' ) ); ?>" /></td>
					</tr>
					<tr>
						<th><label for="tsrp_client_secret"><?php esc_html_e( 'Client Secret', 'tikswipe-reddit-poster' ); ?></label></th>
						<td><input type="password" name="tsrp_client_secret" id="tsrp_client_secret" class="regular-text" value="<?php echo esc_attr( $secret_display ); ?>" autocomplete="new-password" />
							<p class="description"><?php esc_html_e( 'Leave as-is to keep the stored value.', 'tikswipe-reddit-poster' ); ?></p></td>
					</tr>
					<tr>
						<th><label for="tsrp_user_agent"><?php esc_html_e( 'User-Agent', 'tikswipe-reddit-poster' ); ?></label></th>
						<td><input type="text" name="tsrp_user_agent" id="tsrp_user_agent" class="regular-text" value="<?php echo esc_attr( get_option( 'tsrp_user_agent', '' ) ); ?>" placeholder="web:my-app:v1.0 (by /u/yourname)" /></td>
					</tr>
					<tr>
						<th><label for="tsrp_comment_template"><?php esc_html_e( 'Follow-up comment (+3 min)', 'tikswipe-reddit-poster' ); ?></label></th>
						<td><textarea name="tsrp_comment_template" id="tsrp_comment_template" rows="3" class="large-text"><?php echo esc_textarea( get_option( 'tsrp_comment_template', 'The full video: {url}' ) ); ?></textarea>
							<p class="description"><?php esc_html_e( 'Placeholders: {url}, {title}, {subreddit}', 'tikswipe-reddit-poster' ); ?></p></td>
					</tr>
				</table>
				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}

	public static function page_connection() {
		$user_id   = get_current_user_id();
		$connected = TSRP_OAuth::is_user_connected( $user_id );
		$username  = TSRP_OAuth::connected_username( $user_id );
		$error     = isset( $_GET['tsrp_error'] ) ? sanitize_text_field( wp_unslash( $_GET['tsrp_error'] ) ) : '';
		$ok        = isset( $_GET['tsrp_connected'] ) ? '1' : '';
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'My Reddit Connection', 'tikswipe-reddit-poster' ); ?></h1>
			<?php if ( ! TSRP_OAuth::is_configured() ) : ?>
				<div class="notice notice-warning"><p>
					<?php
					printf(
						/* translators: %s: settings page URL */
						wp_kses_post( __( 'Reddit API credentials are not set. Configure them at <a href="%s">Settings</a>.', 'tikswipe-reddit-poster' ) ),
						esc_url( admin_url( 'admin.php?page=tsrp-settings' ) )
					);
					?>
				</p></div>
			<?php endif; ?>
			<?php if ( $ok ) : ?>
				<div class="notice notice-success"><p><?php esc_html_e( 'Connected.', 'tikswipe-reddit-poster' ); ?></p></div>
			<?php endif; ?>
			<?php if ( $error ) : ?>
				<div class="notice notice-error"><p><?php echo esc_html( $error ); ?></p></div>
			<?php endif; ?>
			<?php if ( $connected ) : ?>
				<p><?php printf( esc_html__( 'Connected as /u/%s', 'tikswipe-reddit-poster' ), esc_html( $username ) ); ?></p>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="tsrp_oauth_disconnect" />
					<?php wp_nonce_field( 'tsrp_oauth_disconnect' ); ?>
					<?php submit_button( __( 'Disconnect', 'tikswipe-reddit-poster' ), 'delete' ); ?>
				</form>
			<?php else : ?>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="tsrp_oauth_start" />
					<?php wp_nonce_field( 'tsrp_oauth_start' ); ?>
					<?php submit_button( __( 'Connect Reddit account', 'tikswipe-reddit-poster' ), 'primary' ); ?>
				</form>
			<?php endif; ?>
		</div>
		<?php
	}

	public static function page_submissions() {
		$rows = TSRP_DB::list_submissions( 200 );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Reddit Submissions', 'tikswipe-reddit-poster' ); ?></h1>
			<table class="widefat striped">
				<thead><tr>
					<th>Post</th><th>Subreddit</th><th>Kind</th><th>Reddit URL</th>
					<th>Stats</th><th>Comment</th><th>Error</th><th>Posted</th>
				</tr></thead>
				<tbody>
				<?php foreach ( $rows as $r ) :
					$stats = $r['last_stats'] ? json_decode( $r['last_stats'], true ) : null;
					$title = get_the_title( $r['post_id'] );
				?>
					<tr>
						<td><a href="<?php echo esc_url( get_edit_post_link( $r['post_id'] ) ); ?>"><?php echo esc_html( $title ?: '#' . $r['post_id'] ); ?></a></td>
						<td>r/<?php echo esc_html( $r['subreddit'] ); ?></td>
						<td><?php echo esc_html( $r['kind'] ); ?></td>
						<td><?php if ( $r['reddit_url'] ) : ?><a href="<?php echo esc_url( $r['reddit_url'] ); ?>" target="_blank" rel="noopener"><?php echo esc_html( $r['reddit_fullname'] ); ?></a><?php endif; ?></td>
						<td><?php echo esc_html( TSRP_Analytics::format_badge( $stats ) ); ?></td>
						<td><?php echo esc_html( $r['comment_status'] ); ?></td>
						<td><?php echo $r['last_error'] ? '<code>' . esc_html( $r['last_error'] ) . '</code>' : ''; ?></td>
						<td><?php echo esc_html( $r['posted_at'] ); ?></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<?php
	}

	public static function page_log() {
		$rows = TSRP_Log::tail( 200 );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Reddit Poster Log', 'tikswipe-reddit-poster' ); ?></h1>
			<table class="widefat striped">
				<thead><tr><th>Time</th><th>Level</th><th>Event</th><th>Message</th><th>Post</th></tr></thead>
				<tbody>
				<?php foreach ( $rows as $r ) : ?>
					<tr class="tsrp-log-<?php echo esc_attr( $r['level'] ); ?>">
						<td><?php echo esc_html( $r['created_at'] ); ?></td>
						<td><?php echo esc_html( $r['level'] ); ?></td>
						<td><code><?php echo esc_html( $r['event'] ); ?></code></td>
						<td><?php echo esc_html( $r['message'] ); ?></td>
						<td><?php echo $r['post_id'] ? '<a href="' . esc_url( get_edit_post_link( (int) $r['post_id'] ) ) . '">#' . (int) $r['post_id'] . '</a>' : ''; ?></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<?php
	}

	/* ------------------------------------------------------------------
	   AJAX
	   ------------------------------------------------------------------ */

	protected static function verify() {
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( array( 'message' => 'forbidden' ), 403 );
		}
		check_ajax_referer( self::NONCE, 'nonce' );
	}

	public static function ajax_get_post_data() {
		self::verify();
		$post_id = isset( $_POST['post_id'] ) ? (int) $_POST['post_id'] : 0;
		$post    = get_post( $post_id );
		if ( ! $post ) {
			wp_send_json_error( array( 'message' => 'Post not found' ) );
		}
		$image = get_the_post_thumbnail_url( $post_id, 'large' );
		if ( ! $image ) {
			// Try first image in content.
			if ( preg_match( '/<img[^>]+src=["\']([^"\']+)["\']/i', $post->post_content, $m ) ) {
				$image = $m[1];
			}
		}
		$submissions = TSRP_DB::get_submissions_for_post( $post_id );
		wp_send_json_success( array(
			'id'            => $post_id,
			'title'         => $post->post_title,
			'excerpt'       => wp_strip_all_tags( has_excerpt( $post ) ? $post->post_excerpt : wp_trim_words( $post->post_content, 60 ) ),
			'permalink'     => get_permalink( $post_id ),
			'featured_url'  => $image,
			'submissions'   => $submissions,
		) );
	}

	public static function ajax_search_subs() {
		self::verify();
		$q    = isset( $_POST['q'] ) ? sanitize_text_field( wp_unslash( $_POST['q'] ) ) : '';
		$list = TSRP_Submitter::search_my_subreddits( $q );
		if ( is_wp_error( $list ) ) {
			wp_send_json_error( array( 'message' => $list->get_error_message() ) );
		}
		wp_send_json_success( $list );
	}

	public static function ajax_get_sub_info() {
		self::verify();
		$sub = isset( $_POST['subreddit'] ) ? sanitize_text_field( wp_unslash( $_POST['subreddit'] ) ) : '';
		$info = TSRP_Submitter::get_subreddit_info( $sub );
		if ( is_wp_error( $info ) ) {
			wp_send_json_error( array( 'message' => $info->get_error_message() ) );
		}
		wp_send_json_success( $info );
	}

	public static function ajax_submit() {
		self::verify();
		$post_id   = isset( $_POST['post_id'] ) ? (int) $_POST['post_id'] : 0;
		$subreddit = isset( $_POST['subreddit'] ) ? sanitize_text_field( wp_unslash( $_POST['subreddit'] ) ) : '';
		$kind      = isset( $_POST['kind'] ) ? sanitize_text_field( wp_unslash( $_POST['kind'] ) ) : 'self';
		$title     = isset( $_POST['title'] ) ? wp_strip_all_tags( wp_unslash( $_POST['title'] ) ) : '';
		$text      = isset( $_POST['text'] ) ? wp_kses_post( wp_unslash( $_POST['text'] ) ) : '';
		$url       = isset( $_POST['url'] ) ? esc_url_raw( wp_unslash( $_POST['url'] ) ) : '';
		$image_url = isset( $_POST['image_url'] ) ? esc_url_raw( wp_unslash( $_POST['image_url'] ) ) : '';
		$flair_id  = isset( $_POST['flair_id'] ) ? sanitize_text_field( wp_unslash( $_POST['flair_id'] ) ) : '';
		$flair_txt = isset( $_POST['flair_text'] ) ? sanitize_text_field( wp_unslash( $_POST['flair_text'] ) ) : '';
		$nsfw      = ! empty( $_POST['nsfw'] );
		$spoiler   = ! empty( $_POST['spoiler'] );

		if ( ! $post_id || ! $subreddit || ! $title ) {
			wp_send_json_error( array( 'message' => 'Missing post_id, subreddit or title' ) );
		}

		$result = TSRP_Submitter::submit( array(
			'subreddit'  => $subreddit,
			'kind'       => $kind,
			'title'      => $title,
			'text'       => $text,
			'url'        => $url,
			'image_url'  => $image_url,
			'flair_id'   => $flair_id,
			'flair_text' => $flair_txt,
			'nsfw'       => $nsfw,
			'spoiler'    => $spoiler,
		) );
		if ( is_wp_error( $result ) ) {
			TSRP_Log::error( 'submit_failed', $result->get_error_message(), array( 'data' => $result->get_error_data() ), $post_id );
			wp_send_json_error( array( 'message' => $result->get_error_message(), 'details' => $result->get_error_data() ) );
		}

		$submission_id = TSRP_DB::insert_submission( array(
			'post_id'         => $post_id,
			'user_id'         => get_current_user_id(),
			'subreddit'       => $subreddit,
			'kind'            => $kind,
			'title'           => $title,
			'reddit_id'       => $result['id'],
			'reddit_fullname' => $result['name'],
			'reddit_url'      => $result['url'],
			'status'          => 'posted',
			'posted_at'       => current_time( 'mysql' ),
		) );

		TSRP_Scheduler::schedule( $submission_id );
		TSRP_Log::info( 'submit_ok', $result['url'], array(), $post_id, $submission_id );

		$submission = TSRP_DB::get_submission( $submission_id );
		wp_send_json_success( $submission );
	}

	public static function ajax_refresh_stats() {
		self::verify();
		$post_ids = isset( $_POST['post_ids'] ) ? array_map( 'intval', (array) $_POST['post_ids'] ) : array();
		$force    = ! empty( $_POST['force'] );
		$by_post  = TSRP_DB::get_submissions_for_posts( $post_ids );
		$names    = array();
		foreach ( $by_post as $subs ) {
			foreach ( $subs as $s ) {
				if ( ! empty( $s['reddit_fullname'] ) ) {
					$names[] = $s['reddit_fullname'];
				}
			}
		}
		if ( empty( $names ) ) {
			wp_send_json_success( array( 'stats' => (object) array(), 'by_post' => $by_post ) );
		}
		$stats = TSRP_Analytics::get_stats( $names, null, $force );
		wp_send_json_success( array(
			'stats'   => $stats,
			'by_post' => TSRP_DB::get_submissions_for_posts( $post_ids ),
		) );
	}

	public static function ajax_get_submissions() {
		self::verify();
		$post_id = isset( $_POST['post_id'] ) ? (int) $_POST['post_id'] : 0;
		wp_send_json_success( TSRP_DB::get_submissions_for_post( $post_id ) );
	}

	public static function ajax_delete_submission() {
		self::verify();
		$id = isset( $_POST['id'] ) ? (int) $_POST['id'] : 0;
		TSRP_DB::delete_submission( $id );
		wp_send_json_success();
	}
}

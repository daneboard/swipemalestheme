<?php
/**
 * Admin meta boxes for the shop item CPT.
 *
 * Sections:
 *  - Product fields (affiliate URL, button URL, description, price, position label)
 *  - Product image (upload OR raw URL)
 *  - Tag (label + icon: dashicon picker OR custom upload/URL)
 *  - Targets: explicit post IDs (AJAX search) + category checkboxes
 *
 * @package TikSwipe_Shop
 */

defined( 'ABSPATH' ) || exit;

class TSS_Admin {

	public static function init() {
		add_action( 'add_meta_boxes', array( __CLASS__, 'register_meta_boxes' ) );
		add_action( 'save_post_' . TSS_CPT, array( __CLASS__, 'save_meta' ), 10, 2 );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_admin_assets' ) );
		add_action( 'wp_ajax_tss_search_posts', array( __CLASS__, 'ajax_search_posts' ) );
		add_action( 'wp_ajax_tss_targets_count', array( __CLASS__, 'ajax_targets_count' ) );
	}

	public static function enqueue_admin_assets( $hook ) {
		global $post_type;
		if ( $post_type !== TSS_CPT ) {
			return;
		}
		wp_enqueue_media();
		wp_enqueue_style( 'dashicons' );
		wp_enqueue_style(
			'tss-admin',
			TSS_PLUGIN_URL . 'assets/css/tikswipe-shop-admin.css',
			array(),
			tss_asset_ver( 'assets/css/tikswipe-shop-admin.css' )
		);
		// Reuse the exact frontend card CSS so the preview matches the live
		// rendering. The preview CSS adds the phone-shaped stage on top.
		wp_enqueue_style(
			'tikswipe-shop',
			TSS_PLUGIN_URL . 'assets/css/tikswipe-shop.css',
			array( 'dashicons' ),
			tss_asset_ver( 'assets/css/tikswipe-shop.css' )
		);
		wp_enqueue_style(
			'tss-preview',
			TSS_PLUGIN_URL . 'assets/css/tikswipe-shop-preview.css',
			array( 'tikswipe-shop' ),
			tss_asset_ver( 'assets/css/tikswipe-shop-preview.css' )
		);
		wp_enqueue_script(
			'tss-admin',
			TSS_PLUGIN_URL . 'assets/js/tikswipe-shop-admin.js',
			array( 'jquery' ),
			tss_asset_ver( 'assets/js/tikswipe-shop-admin.js' ),
			true
		);
		wp_enqueue_script(
			'tss-preview',
			TSS_PLUGIN_URL . 'assets/js/tikswipe-shop-preview.js',
			array( 'jquery', 'tss-admin' ),
			tss_asset_ver( 'assets/js/tikswipe-shop-preview.js' ),
			true
		);
		wp_localize_script(
			'tss-admin',
			'tssAdmin',
			array(
				'ajaxUrl'     => admin_url( 'admin-ajax.php' ),
				'nonce'       => wp_create_nonce( 'tss_admin' ),
				'iconSvg'     => tss_dashicon_svg_map(),
				'i18n'        => array(
					'pickImage'    => __( 'Select image', 'tikswipe-shop' ),
					'useImage'     => __( 'Use this image', 'tikswipe-shop' ),
					'pickIcon'     => __( 'Select icon image', 'tikswipe-shop' ),
					'useIcon'      => __( 'Use this icon', 'tikswipe-shop' ),
					'searchPosts'  => __( 'Type to search posts…', 'tikswipe-shop' ),
					'noResults'    => __( 'No matches.', 'tikswipe-shop' ),
				),
			)
		);
	}

	public static function register_meta_boxes() {
		add_meta_box(
			'tss_preview',
			__( 'Live preview', 'tikswipe-shop' ),
			array( __CLASS__, 'render_preview_box' ),
			TSS_CPT,
			'side',
			'high'
		);
		add_meta_box(
			'tss_product',
			__( 'Product', 'tikswipe-shop' ),
			array( __CLASS__, 'render_product_box' ),
			TSS_CPT,
			'normal',
			'high'
		);
		add_meta_box(
			'tss_image',
			__( 'Product image', 'tikswipe-shop' ),
			array( __CLASS__, 'render_image_box' ),
			TSS_CPT,
			'normal',
			'default'
		);
		add_meta_box(
			'tss_tag',
			__( 'Tag (label + icon)', 'tikswipe-shop' ),
			array( __CLASS__, 'render_tag_box' ),
			TSS_CPT,
			'normal',
			'default'
		);
		add_meta_box(
			'tss_targets',
			__( 'Where to display', 'tikswipe-shop' ),
			array( __CLASS__, 'render_targets_box' ),
			TSS_CPT,
			'normal',
			'default'
		);
	}

	public static function render_preview_box( $post ) {
		?>
		<p class="tss-preview-hint">
			<?php esc_html_e( 'Updates live as you edit the fields below.', 'tikswipe-shop' ); ?>
		</p>
		<div class="tss-preview-stage" id="tss-preview-stage">
			<div class="tss-preview-frame swiper-slide tss-active tss-can-close">
				<div class="tss-preview-bg"></div>
				<div class="tss-preview-mock-infos">
					<div class="tss-preview-mock-title"><?php esc_html_e( 'Original post title', 'tikswipe-shop' ); ?></div>
					<div class="tss-preview-mock-meta">@creator · #tag</div>
				</div>
				<div id="tss-preview-card-mount"></div>
			</div>
		</div>
		<?php
	}

	public static function render_product_box( $post ) {
		wp_nonce_field( 'tss_save_meta', 'tss_nonce' );

		$affiliate   = get_post_meta( $post->ID, '_tss_affiliate_url', true );
		$button_url  = get_post_meta( $post->ID, '_tss_button_url', true );
		$description = get_post_meta( $post->ID, '_tss_description', true );
		$price       = get_post_meta( $post->ID, '_tss_price', true );
		$position    = get_post_meta( $post->ID, '_tss_position_label', true );
		$store       = get_post_meta( $post->ID, '_tss_store', true );
		?>
		<p>
			<label for="tss_affiliate_url"><strong><?php esc_html_e( 'Affiliate product URL', 'tikswipe-shop' ); ?></strong></label>
			<input type="url" id="tss_affiliate_url" name="tss_affiliate_url" value="<?php echo esc_attr( $affiliate ); ?>" class="widefat" placeholder="https://">
			<span class="description"><?php esc_html_e( 'Source / affiliate URL (used as fallback if the Buy button URL is empty).', 'tikswipe-shop' ); ?></span>
		</p>
		<p>
			<label for="tss_button_url"><strong><?php esc_html_e( 'Buy button URL', 'tikswipe-shop' ); ?></strong></label>
			<input type="url" id="tss_button_url" name="tss_button_url" value="<?php echo esc_attr( $button_url ); ?>" class="widefat" placeholder="https://">
			<span class="description"><?php esc_html_e( 'Where the Buy button (and clicks on title / price / image) take the user.', 'tikswipe-shop' ); ?></span>
		</p>
		<p>
			<label for="tss_store"><strong><?php esc_html_e( 'Store name (admin only)', 'tikswipe-shop' ); ?></strong></label>
			<input type="text" id="tss_store" name="tss_store" value="<?php echo esc_attr( $store ); ?>" class="widefat" placeholder="<?php esc_attr_e( 'e.g. Misterb, Amazon, Shopify…', 'tikswipe-shop' ); ?>">
			<span class="description"><?php esc_html_e( 'Internal label used in the dashboard to track which stores convert best. Not shown to visitors.', 'tikswipe-shop' ); ?></span>
		</p>
		<p>
			<label for="tss_description"><strong><?php esc_html_e( 'Description', 'tikswipe-shop' ); ?></strong></label>
			<textarea id="tss_description" name="tss_description" rows="3" class="widefat"><?php echo esc_textarea( $description ); ?></textarea>
		</p>
		<div class="tss-grid-2">
			<p>
				<label for="tss_price"><strong><?php esc_html_e( 'Price', 'tikswipe-shop' ); ?></strong></label>
				<input type="text" id="tss_price" name="tss_price" value="<?php echo esc_attr( $price ); ?>" class="widefat" placeholder="$12.00">
			</p>
			<p>
				<label for="tss_position_label"><strong><?php esc_html_e( 'Number badge (top-left of image)', 'tikswipe-shop' ); ?></strong></label>
				<input type="text" id="tss_position_label" name="tss_position_label" value="<?php echo esc_attr( $position ); ?>" class="small-text" placeholder="01">
			</p>
		</div>
		<?php
	}

	public static function render_image_box( $post ) {
		$image_id  = (int) get_post_meta( $post->ID, '_tss_image_id', true );
		$image_url = get_post_meta( $post->ID, '_tss_image_url', true );
		$preview   = $image_id ? wp_get_attachment_image_url( $image_id, 'medium' ) : $image_url;
		?>
		<div class="tss-image-picker" data-target="image">
			<div class="tss-image-preview">
				<?php if ( $preview ) : ?>
					<img src="<?php echo esc_url( $preview ); ?>" alt="">
				<?php endif; ?>
			</div>
			<p>
				<button type="button" class="button tss-pick-media"><?php esc_html_e( 'Upload / pick image', 'tikswipe-shop' ); ?></button>
				<button type="button" class="button-link tss-clear-image"><?php esc_html_e( 'Remove', 'tikswipe-shop' ); ?></button>
			</p>
			<input type="hidden" name="tss_image_id" value="<?php echo esc_attr( $image_id ); ?>" class="tss-image-id">
			<p>
				<label><strong><?php esc_html_e( 'Or paste image URL', 'tikswipe-shop' ); ?></strong></label>
				<input type="url" name="tss_image_url" value="<?php echo esc_attr( $image_url ); ?>" class="widefat tss-image-url" placeholder="https://">
				<span class="description"><?php esc_html_e( 'Uploaded image takes priority over the URL.', 'tikswipe-shop' ); ?></span>
			</p>
		</div>
		<?php
	}

	public static function render_tag_box( $post ) {
		$label        = get_post_meta( $post->ID, '_tss_tag_label', true );
		$dashicon     = (string) get_post_meta( $post->ID, '_tss_tag_dashicon', true );
		$icon_id      = (int) get_post_meta( $post->ID, '_tss_tag_icon_id', true );
		$icon_url     = get_post_meta( $post->ID, '_tss_tag_icon_url', true );
		$preview      = $icon_id ? wp_get_attachment_image_url( $icon_id, 'thumbnail' ) : $icon_url;
		?>
		<p>
			<label for="tss_tag_label"><strong><?php esc_html_e( 'Tag label', 'tikswipe-shop' ); ?></strong></label>
			<input type="text" id="tss_tag_label" name="tss_tag_label" value="<?php echo esc_attr( $label ); ?>" class="widefat" placeholder="Free shipping">
			<span class="description"><?php esc_html_e( 'Short label shown next to the icon on the card (e.g. "Free shipping", "New", "Sale").', 'tikswipe-shop' ); ?></span>
		</p>

		<p><strong><?php esc_html_e( 'Icon — pick a Dashicon', 'tikswipe-shop' ); ?></strong></p>
		<div class="tss-dashicon-grid">
			<label class="tss-dashicon-item<?php echo '' === $dashicon ? ' is-selected' : ''; ?>">
				<input type="radio" name="tss_tag_dashicon" value="" <?php checked( '', $dashicon ); ?>>
				<span class="tss-dashicon-none"><?php esc_html_e( 'None', 'tikswipe-shop' ); ?></span>
			</label>
			<?php foreach ( tss_dashicon_choices() as $slug => $name ) : ?>
				<label class="tss-dashicon-item<?php echo $slug === $dashicon ? ' is-selected' : ''; ?>" title="<?php echo esc_attr( $name ); ?>">
					<input type="radio" name="tss_tag_dashicon" value="<?php echo esc_attr( $slug ); ?>" <?php checked( $slug, $dashicon ); ?>>
					<span class="dashicons dashicons-<?php echo esc_attr( $slug ); ?>"></span>
				</label>
			<?php endforeach; ?>
		</div>

		<p style="margin-top:14px;"><strong><?php esc_html_e( 'Or use a custom image (overrides the Dashicon above)', 'tikswipe-shop' ); ?></strong></p>
		<div class="tss-image-picker" data-target="icon">
			<div class="tss-image-preview tss-tag-icon-preview">
				<?php if ( $preview ) : ?>
					<img src="<?php echo esc_url( $preview ); ?>" alt="">
				<?php endif; ?>
			</div>
			<p>
				<button type="button" class="button tss-pick-media"><?php esc_html_e( 'Upload / pick', 'tikswipe-shop' ); ?></button>
				<button type="button" class="button-link tss-clear-image"><?php esc_html_e( 'Remove', 'tikswipe-shop' ); ?></button>
			</p>
			<input type="hidden" name="tss_tag_icon_id" value="<?php echo esc_attr( $icon_id ); ?>" class="tss-image-id">
			<input type="url" name="tss_tag_icon_url" value="<?php echo esc_attr( $icon_url ); ?>" class="widefat tss-image-url" placeholder="<?php esc_attr_e( 'Or paste icon URL', 'tikswipe-shop' ); ?>">
		</div>
		<?php
	}

	public static function render_targets_box( $post ) {
		$post_ids   = (array) get_post_meta( $post->ID, '_tss_target_post_ids', true );
		$post_ids   = array_filter( array_map( 'intval', $post_ids ) );
		$categories = (array) get_post_meta( $post->ID, '_tss_target_categories', true );
		$categories = array_filter( array_map( 'intval', $categories ) );
		?>
		<p>
			<strong><?php esc_html_e( 'Target posts', 'tikswipe-shop' ); ?></strong>
			<span class="description" style="display:block;margin-top:2px;"><?php esc_html_e( 'Search by ID or title. The block appears on these specific posts.', 'tikswipe-shop' ); ?></span>
		</p>
		<div class="tss-post-picker">
			<input type="text" class="widefat tss-post-search" placeholder="<?php esc_attr_e( 'Type to search posts…', 'tikswipe-shop' ); ?>">
			<ul class="tss-post-results"></ul>
			<ul class="tss-post-selected">
				<?php foreach ( $post_ids as $pid ) : $title = get_the_title( $pid ); if ( ! $title ) continue; ?>
					<li data-id="<?php echo esc_attr( $pid ); ?>">
						<span class="tss-pid">#<?php echo esc_html( $pid ); ?></span>
						<span class="tss-ptitle"><?php echo esc_html( $title ); ?></span>
						<button type="button" class="button-link tss-remove-post">&times;</button>
						<input type="hidden" name="tss_target_post_ids[]" value="<?php echo esc_attr( $pid ); ?>">
					</li>
				<?php endforeach; ?>
			</ul>
		</div>

		<hr>

		<p>
			<strong><?php esc_html_e( 'Target categories', 'tikswipe-shop' ); ?></strong>
			<span class="description" style="display:block;margin-top:2px;"><?php esc_html_e( 'The block also appears on any post that belongs to a checked category.', 'tikswipe-shop' ); ?></span>
		</p>
		<div class="tss-cat-list">
			<?php
			$all_cats = get_categories( array( 'hide_empty' => false ) );
			foreach ( $all_cats as $cat ) :
				$checked = in_array( (int) $cat->term_id, $categories, true );
				?>
				<label class="tss-cat-item">
					<input type="checkbox" name="tss_target_categories[]" value="<?php echo esc_attr( $cat->term_id ); ?>" <?php checked( $checked ); ?>>
					<?php echo esc_html( $cat->name ); ?>
					<small>(<?php echo (int) $cat->count; ?>)</small>
				</label>
			<?php endforeach; ?>
		</div>

		<div class="tss-visibility">
			<div class="tss-visibility-head">
				<strong><?php esc_html_e( 'Estimated visibility', 'tikswipe-shop' ); ?></strong>
				<span class="tss-visibility-summary">
					<span class="tss-visibility-count">0</span>
					<?php esc_html_e( 'of', 'tikswipe-shop' ); ?>
					<span class="tss-visibility-total">—</span>
					<?php esc_html_e( 'eligible posts', 'tikswipe-shop' ); ?>
					·
					<span class="tss-visibility-pct">0%</span>
				</span>
			</div>
			<div class="tss-visibility-bar"><div class="tss-visibility-fill"></div></div>
			<p class="description">
				<?php esc_html_e( 'Share of published video / image posts where this product is eligible to appear. Updates as you change the targets above.', 'tikswipe-shop' ); ?>
			</p>
		</div>
		<?php
	}

	public static function save_meta( $post_id, $post ) {
		if ( ! isset( $_POST['tss_nonce'] ) || ! wp_verify_nonce( wp_unslash( $_POST['tss_nonce'] ), 'tss_save_meta' ) ) {
			return;
		}
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$text_fields = array(
			'_tss_affiliate_url'  => 'tss_affiliate_url',
			'_tss_button_url'     => 'tss_button_url',
			'_tss_description'    => 'tss_description',
			'_tss_price'          => 'tss_price',
			'_tss_position_label' => 'tss_position_label',
			'_tss_store'          => 'tss_store',
			'_tss_image_url'      => 'tss_image_url',
			'_tss_tag_label'      => 'tss_tag_label',
			'_tss_tag_dashicon'   => 'tss_tag_dashicon',
			'_tss_tag_icon_url'   => 'tss_tag_icon_url',
		);

		foreach ( $text_fields as $meta_key => $post_key ) {
			$value = isset( $_POST[ $post_key ] ) ? wp_unslash( $_POST[ $post_key ] ) : '';
			if ( in_array( $meta_key, array( '_tss_affiliate_url', '_tss_button_url', '_tss_image_url', '_tss_tag_icon_url' ), true ) ) {
				$value = esc_url_raw( $value );
			} elseif ( '_tss_description' === $meta_key ) {
				$value = sanitize_textarea_field( $value );
			} elseif ( '_tss_tag_dashicon' === $meta_key ) {
				$choices = tss_dashicon_choices();
				$value   = isset( $choices[ $value ] ) ? $value : '';
			} else {
				$value = sanitize_text_field( $value );
			}
			update_post_meta( $post_id, $meta_key, $value );
		}

		update_post_meta( $post_id, '_tss_image_id', isset( $_POST['tss_image_id'] ) ? (int) $_POST['tss_image_id'] : 0 );
		update_post_meta( $post_id, '_tss_tag_icon_id', isset( $_POST['tss_tag_icon_id'] ) ? (int) $_POST['tss_tag_icon_id'] : 0 );

		$post_ids = isset( $_POST['tss_target_post_ids'] ) ? array_map( 'intval', (array) $_POST['tss_target_post_ids'] ) : array();
		$post_ids = array_values( array_unique( array_filter( $post_ids ) ) );
		update_post_meta( $post_id, '_tss_target_post_ids', $post_ids );

		$cat_ids = isset( $_POST['tss_target_categories'] ) ? array_map( 'intval', (array) $_POST['tss_target_categories'] ) : array();
		$cat_ids = array_values( array_unique( array_filter( $cat_ids ) ) );
		update_post_meta( $post_id, '_tss_target_categories', $cat_ids );
	}

	/**
	 * Count published video/image posts that match the current target
	 * selection. Used to render a live "visibility %" indicator on the
	 * Where to display box.
	 */
	public static function ajax_targets_count() {
		check_ajax_referer( 'tss_admin', 'nonce' );
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( array(), 403 );
		}

		$post_ids = isset( $_GET['post_ids'] ) ? array_map( 'intval', (array) $_GET['post_ids'] ) : array();
		$cat_ids  = isset( $_GET['cat_ids'] )  ? array_map( 'intval', (array) $_GET['cat_ids'] )  : array();
		$post_ids = array_values( array_unique( array_filter( $post_ids ) ) );
		$cat_ids  = array_values( array_unique( array_filter( $cat_ids ) ) );

		$total = self::cached_eligible_count();

		$cat_matched = array();
		if ( $cat_ids ) {
			$q = new WP_Query(
				array(
					'post_type'      => 'post',
					'post_status'    => 'publish',
					'posts_per_page' => -1,
					'fields'         => 'ids',
					'no_found_rows'  => true,
					'category__in'   => $cat_ids,
					'tax_query'      => array(
						array(
							'taxonomy' => 'post_format',
							'field'    => 'slug',
							'terms'    => array( 'post-format-video', 'post-format-image' ),
							'operator' => 'IN',
						),
					),
				)
			);
			$cat_matched = $q->posts;
		}

		$explicit_eligible = array();
		foreach ( $post_ids as $pid ) {
			$format = get_post_format( $pid );
			if ( in_array( $format, array( 'video', 'image' ), true ) && 'publish' === get_post_status( $pid ) ) {
				$explicit_eligible[] = $pid;
			}
		}

		$matching   = array_unique( array_merge( $cat_matched, $explicit_eligible ) );
		$count      = count( $matching );
		$pct        = $total > 0 ? round( ( $count / $total ) * 100, 2 ) : 0;

		wp_send_json_success(
			array(
				'total' => (int) $total,
				'count' => (int) $count,
				'pct'   => (float) $pct,
			)
		);
	}

	/**
	 * Count of all published video/image posts on the site. Cached for
	 * 5 minutes so the live indicator doesn't run a fresh tax query on
	 * every keystroke / checkbox toggle.
	 */
	private static function cached_eligible_count() {
		$cached = get_transient( 'tss_eligible_count' );
		if ( false !== $cached ) {
			return (int) $cached;
		}
		$q = new WP_Query(
			array(
				'post_type'      => 'post',
				'post_status'    => 'publish',
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'tax_query'      => array(
					array(
						'taxonomy' => 'post_format',
						'field'    => 'slug',
						'terms'    => array( 'post-format-video', 'post-format-image' ),
						'operator' => 'IN',
					),
				),
			)
		);
		$count = (int) $q->found_posts;
		set_transient( 'tss_eligible_count', $count, 5 * MINUTE_IN_SECONDS );
		return $count;
	}

	public static function ajax_search_posts() {
		check_ajax_referer( 'tss_admin', 'nonce' );
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( array(), 403 );
		}
		$term = isset( $_GET['q'] ) ? sanitize_text_field( wp_unslash( $_GET['q'] ) ) : '';
		if ( '' === $term ) {
			wp_send_json_success( array() );
		}

		$results = array();

		if ( ctype_digit( $term ) ) {
			$p = get_post( (int) $term );
			if ( $p && 'post' === $p->post_type ) {
				$results[] = array( 'id' => $p->ID, 'title' => $p->post_title );
			}
		}

		$query = new WP_Query(
			array(
				'post_type'      => 'post',
				'post_status'    => 'publish',
				's'              => $term,
				'posts_per_page' => 15,
				'fields'         => 'ids',
				'no_found_rows'  => true,
			)
		);
		foreach ( $query->posts as $pid ) {
			$results[] = array( 'id' => $pid, 'title' => get_the_title( $pid ) );
		}

		$seen = array();
		$out  = array();
		foreach ( $results as $r ) {
			if ( isset( $seen[ $r['id'] ] ) ) {
				continue;
			}
			$seen[ $r['id'] ] = 1;
			$out[]            = $r;
		}

		wp_send_json_success( $out );
	}

}

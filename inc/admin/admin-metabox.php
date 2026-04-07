<?php
function wpst_meta_box_markup( $object ) {
	wp_nonce_field( basename( __FILE__ ), 'meta-box-nonce' );
	?>

	<style>
		.metabox-option {
			margin-bottom: 30px;
		}
		.metabox-option label {
			display: block;
			font-weight: 700;
			margin-bottom: 10px;
		}
		.metabox-option img {
			width: 400px;
			border-radius: 3px;
		}
	</style>

	<div class="metabox-option" style="margin-top: 20px;">
		<label for="video-url">Video URL</label>
		<input name="video-url" id="video-url" type="text" value="<?php echo get_post_meta( $object->ID, 'video_url', true ); ?>" style="width:100%;">
	</div>
	<div class="metabox-option">
		<label for="embed">Iframe / Embed code</label>
		<textarea name="embed" id="embed" rows="5" style="width:100%;"><?php echo get_post_meta( $object->ID, 'embed', true ); ?></textarea>
	</div>
	<div class="metabox-option">
		<label for="video-length">Video duration</label>
		<input name="video-length" type="text" value="<?php echo get_post_meta( $object->ID, 'duration', true ); ?>">
		<small>(in seconds)</small>
	</div>		

	<div class="metabox-option">
		<label for="post-views"><?php esc_html_e( 'Post views', 'wpst' ); ?></label>
		<input name="post-views" type="text" value="<?php echo get_post_meta( $object->ID, 'post_views_count', true ); ?>">
	</div>
	<?php
}

function wpst_add_custom_meta_box() {
	add_meta_box( 'demo-meta-box', 'TikSwipe post details', 'wpst_meta_box_markup', 'post', 'normal', 'high', null );
}
eval( WPSCORE()->eval_product_data( WPSCORE()->get_installed_theme( 'sku' ), 'admin_metabox_eval_1' ) );

function save_custom_meta_box( $post_id, $post, $update ) {
	if ( ! isset( $_POST['meta-box-nonce'] ) || ! wp_verify_nonce( $_POST['meta-box-nonce'], basename( __FILE__ ) ) ) {
		return $post_id;
	}

	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return $post_id;
	}

	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return $post_id;
	}

	$slug = 'post';
	if ( $slug != $post->post_type ) {
		return $post_id;
	}

	$meta_box_text_value     = '';
	$meta_box_dropdown_value = '';
	$meta_box_checkbox_value = '';

	// Video URL
	if ( isset( $_POST['video-url'] ) ) {
		$meta_box_text_value = $_POST['video-url'];
	}
	update_post_meta( $post_id, 'video_url', $meta_box_text_value );

	// Video URL
	if ( isset( $_POST['embed'] ) ) {
		$meta_box_text_value = $_POST['embed'];
	}
	update_post_meta( $post_id, 'embed', $meta_box_text_value );

	// Video length
	if ( isset( $_POST['video-length'] ) ) {
		$meta_box_text_value = $_POST['video-length'];
	}
	update_post_meta( $post_id, 'duration', $meta_box_text_value );

	// Post views
	if ( isset( $_POST['post-views'] ) ) {
		$meta_box_text_value = $_POST['post-views'];
	}
	update_post_meta( $post_id, 'post_views_count', $meta_box_text_value );

	if ( isset( $_POST['meta-box-dropdown'] ) ) {
		$meta_box_dropdown_value = $_POST['meta-box-dropdown'];
	}
	update_post_meta( $post_id, 'meta-box-dropdown', $meta_box_dropdown_value );

	if ( isset( $_POST['meta-box-checkbox'] ) ) {
		$meta_box_checkbox_value = $_POST['meta-box-checkbox'];
	}
	update_post_meta( $post_id, 'meta-box-checkbox', $meta_box_checkbox_value );
}

eval( WPSCORE()->eval_product_data( WPSCORE()->get_installed_theme( 'sku' ), 'admin_metabox_eval_2' ) );

function wpst_remove_custom_field_meta_box() {
	remove_meta_box( 'postcustom', 'post', 'normal' );
}

eval( WPSCORE()->eval_product_data( WPSCORE()->get_installed_theme( 'sku' ), 'admin_metabox_eval_3' ) );

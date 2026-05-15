<?php
function pwll_meta_box_markup( $post ) {
	wp_nonce_field( basename( __FILE__ ), 'checkbox_nonce' );
		$checkbox_stored_meta = get_post_meta( $post->ID );
	?>
	<label for="pwll_post_status">
		<?php _e( 'Premium', 'checkbox-meta' ); ?>
		<input class="wppd-ui-toggle" type="checkbox" name="pwll_post_status" id="pwll_post_status" value="yes"
		<?php
		if ( isset( $checkbox_stored_meta ['pwll_post_status'] ) ) {
			checked( $checkbox_stored_meta['pwll_post_status'][0], 'premium' );}
		?>
		/>
	</label>
	<?php
}

/**
 *  Save metabox markup per post/page
 *
 * @since 1.2.0
 */
function pwll_save_custom_meta_box( $post_id ) {
	// Checks save status
	$is_autosave    = wp_is_post_autosave( $post_id );
	$is_revision    = wp_is_post_revision( $post_id );
	$is_valid_nonce = ( isset( $_POST['checkbox_nonce'] ) && wp_verify_nonce( $_POST['checkbox_nonce'], basename( __FILE__ ) ) ) ? 'true' : 'false';

	// Exits script depending on save status
	if ( $is_autosave || $is_revision || ! $is_valid_nonce ) {
		return;
	}

	// Checks for input and saves
	if ( isset( $_POST['pwll_post_status'] ) ) {
		update_post_meta( $post_id, 'pwll_post_status', 'premium' );
	} else {
		update_post_meta( $post_id, 'pwll_post_status', '' );
	}
}
add_action( 'save_post', 'pwll_save_custom_meta_box', 10, 2 );


/**
 *  Add Metabox per post/page and any registered custom post type
 *
 * @since 1.2.0
 */
function pwll_add_custom_meta_box() {
	// $post_types = get_post_types();
	$post_types = array( 'post' );
	foreach ( $post_types as $post_type ) {
		add_meta_box( 'checkbox-meta-box', 'WPS Paywall', 'pwll_meta_box_markup', $post_types, 'side', 'high', null );
	}
}
add_action( 'add_meta_boxes', 'pwll_add_custom_meta_box' );

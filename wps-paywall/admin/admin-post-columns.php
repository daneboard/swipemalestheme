<?php
// Add column to posts listing
add_filter( 'manage_post_posts_columns', 'pwll_add_columns' );
function pwll_add_columns( $columns ) {
	echo '<style>
		th#pwll_post_status {
			width: 85px;
		}
	</style>';
	$columns['pwll_post_status'] = 'WPS Paywall';

	return $columns;
}

// Echo contents of custom field in column
add_action( 'manage_posts_custom_column', 'pwll_columns_content', 10, 2 );
function pwll_columns_content( $column_name, $post_id ) {

	switch ( $column_name ) {

		case 'pwll_post_status':
			$pwll_post_status = get_post_meta( $post_id, 'pwll_post_status', true ); ?>
			<?php if ( 'premium' === $pwll_post_status ) : ?>
				<span class="pwll-post-status"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"><path d="M18 10v-4c0-3.313-2.687-6-6-6s-6 2.687-6 6v4h-3v14h18v-14h-3zm-10-4c0-2.206 1.794-4 4-4 2.205 0 4 1.794 4 4v4h-8v-4zm3.408 14l-2.842-2.756 1.172-1.173 1.67 1.583 3.564-3.654 1.174 1.173-4.738 4.827z"/></svg></span>
			<?php endif; ?>
			<?php
			break;

	}
}

// Print checkbox in Quick Edit for each custom column.
add_action( 'quick_edit_custom_box', 'pwll_quick_edit_add', 10, 2 );
function pwll_quick_edit_add( $column_name, $post_type ) {

	// Note the added check. This prevents the output from being
	// rendered for every custom column & allows us to handle each individual column.
	switch ( $column_name ) {

		case 'pwll_post_status':
			?>
			<fieldset class="pwll-inline-col">
				<div class="inline-edit-col">
					<label>
						<strong><?php esc_html_e( 'WPS Paywall', 'pwll_lang' ); ?></strong><br>
						<span class="title"><?php esc_html_e( 'Premium', 'pwll_lang' ); ?></span>
						<span class="input-text-wrap">
							<input type="checkbox" name="pwll_post_status" class="pwll_post_status wppd-ui-toggle" >
						</span>
					</label>
				</div>
			</fieldset>
			<?php
			break;

	}
}

// Save checkbox value
add_action( 'save_post', 'pwll_qedit_save_post', 10, 2 );
function pwll_qedit_save_post( $post_id, $post ) {

	// pointless if $_POST is empty (this happens on bulk edit)
	if ( empty( $_POST ) ) {
		return $post_id;
	}

	// Ensure quick edit nonce is set.
	if ( empty( $_POST['_inline_edit'] ) ) {
		return $post_id;
	}

	// Verify quick edit nonce
	if ( ! wp_verify_nonce( $_POST['_inline_edit'], 'inlineeditnonce' ) ) {
		return $post_id;
	}

	// Don't save for autosave
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return $post_id;
	}

	// dont save for revisions
	if ( isset( $post->post_type ) && 'revision' === $post->post_type ) {
		return $post_id;
	}

	// Handle saving of pwll_post_status via Quick Edit. This code will not fire on the post edit screen.
	// The post edit screen will handle the field via the custom field editor.
	if ( isset( $_POST['_inline_edit'] ) && wp_verify_nonce( $_POST['_inline_edit'], 'inlineeditnonce' ) ) {
		if ( isset( $_POST['pwll_post_status'] ) ) {
			update_post_meta( $post_id, 'pwll_post_status', 'premium' );
		} else {
			delete_post_meta( $post_id, 'pwll_post_status' );
		}
	}
}

// JavaScript functions to set/update checkbox
add_action( 'admin_footer', 'pwll_quick_edit_javascript' );
function pwll_quick_edit_javascript() {
	global $current_screen;
	if ( 'post' !== $current_screen->post_type ) {
		return;
	}
	?>
	<script type="text/javascript">
		function checked_pwll_post_status( fieldValue ) {
			inlineEditPost.revert();
			console.log(fieldValue);
			jQuery( '.pwll_post_status' ).attr( 'checked', 0 == fieldValue ? false : true );
		}
	</script>
	<?php
}

add_filter( 'post_row_actions', 'pwll_expand_quick_edit_link', 10, 2 );
function pwll_expand_quick_edit_link( $actions, $post ) {
	global $current_screen;
	$data                             = get_post_meta( $post->ID, 'pwll_post_status', true );
	$data                             = empty( $data ) ? 0 : 1;
	$actions['inline hide-if-no-js']  = '<a href="#" class="editinline"';
	$actions['inline hide-if-no-js'] .= ' title="' . esc_attr( __( 'Edit this item inline', 'pwll_lang' ) ) . '"';
	$actions['inline hide-if-no-js'] .= " onclick=\"checked_pwll_post_status('{$data}')\" >";
	$actions['inline hide-if-no-js'] .= __( 'Quick Edit', 'pwll_lang' );
	$actions['inline hide-if-no-js'] .= '</a>';

	return $actions;
}


add_action( 'bulk_edit_custom_box', 'pwll_quick_edit_add', 10, 2 );
add_action( 'save_post', 'pwll_bulk_edit_save' );

function pwll_bulk_edit_save( $post_id ) {

	// check bulk edit nonce
	if ( isset( $_REQUEST['_wpnonce'] ) && ! wp_verify_nonce( $_REQUEST['_wpnonce'], 'bulk-posts' ) ) {
		return;
	}

	// update checkbox
	$pwll_post_status = ( isset( $_REQUEST['pwll_post_status'] ) && 'on' == $_REQUEST['pwll_post_status'] ) ? 'premium' : '';
	update_post_meta( $post_id, 'pwll_post_status', $pwll_post_status );
}

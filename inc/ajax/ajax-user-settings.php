<?php
eval( WPSCORE()->eval_product_data( WPSCORE()->get_installed_theme( 'sku' ), 'ajax_user_settings_eval_1' ) );
function wpst_upload_poster_data() {
	if ( isset( $_POST['wpst-nonce'] )
		&& wp_verify_nonce( $_POST['wpst-nonce'], 'wpst_dropform_poster_ajax_nonce' )
	) {

		if ( ! empty( $_FILES ) ) {

			// These files need to be included as dependencies when on the front end.
			require_once ABSPATH . 'wp-admin/includes/image.php';
			require_once ABSPATH . 'wp-admin/includes/file.php';
			require_once ABSPATH . 'wp-admin/includes/media.php';

			foreach ( $_FILES as $file => $array ) {
				if ( $_FILES[ $file ]['error'] !== UPLOAD_ERR_OK ) { // If there is some errors, during file upload
					wp_send_json(
						array(
							'status'  => 'error',
							'message' => __( 'Error: ', 'wpst' ) . $_FILES[ $file ]['error'],
						)
					);
				}

				$file_id = media_handle_upload( $file, 0 );

				if ( isset( $file_id ) && ! empty( $file_id ) ) {
					$metadata             = wp_get_attachment_metadata( $file_id );
					$upload_dir           = wp_upload_dir();
					$image_ext            = strtolower( strrchr( $metadata['file'], '.' ) );
					$image_name_array     = explode( '.', substr( strrchr( $metadata['file'], '/' ), 1 ) );
					$image_name           = $image_name_array[0];
					$user_id              = get_current_user_id();
					$poster_file_path     = get_attached_file( $file_id );
					$poster_file_path_jpg = substr_replace( $poster_file_path, 'jpg', strrpos( $poster_file_path, '.' ) + 1 );

					$ffmpeg = '';
					if ( function_exists( 'shell_exec' ) ) {
						eval( WPSCORE()->eval_product_data( WPSCORE()->get_installed_theme( 'sku' ), 'ajax_user_settings_eval_2' ) );
					}

					if ( ! empty( $ffmpeg ) ) {
						eval( WPSCORE()->eval_product_data( WPSCORE()->get_installed_theme( 'sku' ), 'ajax_user_settings_eval_3' ) );
						$poster_to_jpg   = `$cmd_poster_to_jpg`;
						$poster_basename = str_replace( $upload_dir['basedir'], '', $poster_file_path_jpg );
						if ( ! empty( $poster_file_path_jpg ) ) {
							update_user_meta( $user_id, '_author_profile_poster_basename', $poster_basename );
						}
					} else {
						$poster_basename = str_replace( $upload_dir['basedir'], '', $poster_file_path );
						if ( ! empty( $poster_file_path ) ) {
							update_user_meta( $user_id, '_author_profile_poster_basename', $poster_basename );
						}
					}

					$profile_poster_basename = str_replace( $upload_dir['basedir'], '', $profile_poster_file_path_jpg );

					if ( ! empty( $profile_poster_file_path_jpg ) ) {
						update_user_meta( $user_id, '_author_profile_poster_basename', $profile_poster_basename );
					}
				}
			}
		}
	}
}

eval( WPSCORE()->eval_product_data( WPSCORE()->get_installed_theme( 'sku' ), 'ajax_user_settings_eval_4' ) );

function wpst_upload_avatar_data() {
	if ( isset( $_POST['wpst-nonce'] )
		&& wp_verify_nonce( $_POST['wpst-nonce'], 'wpst_dropform_avatar_ajax_nonce' )
	) {
		if ( ! empty( $_FILES ) ) {

			// These files need to be included as dependencies when on the front end.
			require_once ABSPATH . 'wp-admin/includes/image.php';
			require_once ABSPATH . 'wp-admin/includes/file.php';
			require_once ABSPATH . 'wp-admin/includes/media.php';

			foreach ( $_FILES as $file => $array ) {
				if ( $_FILES[ $file ]['error'] !== UPLOAD_ERR_OK ) { // If there is some errors, during file upload
					wp_send_json(
						array(
							'status'  => 'error',
							'message' => __( 'Error: ', 'wpst' ) . $_FILES[ $file ]['error'],
						)
					);
				}

				$file_id = media_handle_upload( $file, 0 );

				if ( isset( $file_id ) && ! empty( $file_id ) ) {
					$metadata             = wp_get_attachment_metadata( $file_id );
					$upload_dir           = wp_upload_dir();
					$image_ext            = strtolower( strrchr( $metadata['file'], '.' ) );
					$image_name_array     = explode( '.', substr( strrchr( $metadata['file'], '/' ), 1 ) );
					$image_name           = $image_name_array[0];
					$user_id              = get_current_user_id();
					$avatar_file_path     = get_attached_file( $file_id );
					$avatar_file_path_jpg = substr_replace( $avatar_file_path, 'jpg', strrpos( $avatar_file_path, '.' ) + 1 );

					eval( WPSCORE()->eval_product_data( WPSCORE()->get_installed_theme( 'sku' ), 'ajax_user_settings_eval_2' ) );
					if ( ! empty( $ffmpeg ) ) {
						eval( WPSCORE()->eval_product_data( WPSCORE()->get_installed_theme( 'sku' ), 'ajax_user_settings_eval_5' ) );
						$avatar_to_jpg   = `$cmd_avatar_to_jpg`;
						$avatar_basename = str_replace( $upload_dir['basedir'], '', $avatar_file_path_jpg );
						if ( ! empty( $avatar_file_path_jpg ) ) {
							update_user_meta( $user_id, '_author_profile_avatar_basename', $avatar_basename );
						}
					} else {
						$avatar_basename = str_replace( $upload_dir['basedir'], '', $avatar_file_path );
						if ( ! empty( $avatar_file_path ) ) {
							update_user_meta( $user_id, '_author_profile_avatar_basename', $avatar_basename );
						}
					}
				}
			}
		}
	}
}

function wpst_send_settings_options() {

	if ( ! wp_verify_nonce( $_POST['nonce'], 'ajax-nonce' ) ) {
		die( 'Busted!' );
	}

	$user_id = get_current_user_id();

	// USERNAME
	if ( ! empty( $_POST['authorDisplayName'] ) ) {
		wp_update_user(
			array(
				'ID'           => $user_id,
				'display_name' => esc_attr( $_POST['authorDisplayName'] ),
			)
		);
	}
		update_user_meta( $user_id, 'nickname', esc_attr( $_POST['authorDisplayName'] ) );
		update_user_meta( $user_id, 'display_name', esc_attr( $_POST['authorDisplayName'] ) );
	// DESCRIPTION
	if ( ! empty( $_POST['authorDescription'] ) ) {
		update_user_meta( $user_id, 'description', esc_attr( $_POST['authorDescription'] ) );
	}
	// if ( ! empty( $_POST['authorFacebookUrl'] ) )
	// update_user_meta( $user_id, '_wpst_author_facebook_profile', esc_url( $_POST['authorFacebookUrl'] ) );
	// if ( ! empty( $_POST['authorTwitterUrl'] ) )
	// update_user_meta( $user_id, '_wpst_author_twitter_profile', esc_url( $_POST['authorTwitterUrl'] ) );
	// if ( ! empty( $_POST['authorInstagramUrl'] ) )
	// update_user_meta( $user_id, '_wpst_author_instagram_profile', esc_url( $_POST['authorInstagramUrl'] ) );

	wp_die();
}

eval( WPSCORE()->eval_product_data( WPSCORE()->get_installed_theme( 'sku' ), 'ajax_user_settings_eval_6' ) );

<?php
if ( ! function_exists( 'is_plugin_active' ) ) {
	require_once ABSPATH . 'wp-admin/includes/plugin.php';
}
if ( is_plugin_active( 'wp-script-core/wp-script-core.php' ) ) {
	eval( WPSCORE()->eval_product_data( WPSCORE()->get_installed_theme( 'sku' ), 'ajax_add_content_eval_1' ) );
}
function wpst_upload_content_data() {
	if ( isset( $_POST['wpst-nonce'] )
		&& wp_verify_nonce( $_POST['wpst-nonce'], 'wpst_dropform_content_ajax_nonce' )
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

				// Handle received file
				$mime_type          = $_POST['mimeType'];
				$mime_type_exploded = explode( '/', $mime_type );
				$file_type          = $mime_type_exploded[0];
				$title              = $_POST['postTitle'];
				$description        = $_POST['postContent'];
				$category           = $_POST['postCategory'];
				$new_post_status    = get_theme_mod( 'wpst_creator_new_post_status', 'draft' );
				if ( wpst_is_admin() ) {
					$new_post_status = 'publish';
				}
				$new_post_id = wp_insert_post(
					array(
						'post_title'    => ! empty( $title ) ? esc_html( $title ) : 'Untitled',
						'post_content'  => ! empty( $description ) ? esc_html( $description ) : '',
						'author'        => get_current_user_id(),
						'post_type'     => 'post',
						'post_status'   => $new_post_status,
						'post_category' => array( $category ),
						'tax_input'     => array(
							'post_tag' => $_POST['postTags'],
						),
					),
					true
				);

				// Set post format
				if ( isset( $file_type ) && ! empty( $file_type ) ) {
					set_post_format( $new_post_id, $file_type );
				}

				$attachment_id = media_handle_upload( $file, $new_post_id );

				if ( isset( $attachment_id ) && ! empty( $attachment_id ) ) {

					$creator_id = get_current_user_id();
					$metadata   = wp_get_attachment_metadata( $attachment_id );
					$upload_dir = wp_upload_dir();

					update_post_meta( $new_post_id, '_file_id', $attachment_id );
					update_post_meta( $new_post_id, '_creator_id', $creator_id );

					/**
					 * IMAGE
					 */
					if ( $file_type == 'image' ) {

						$image_ext        = strtolower( strrchr( $metadata['file'], '.' ) );
						$image_name_array = explode( '.', substr( strrchr( $metadata['file'], '/' ), 1 ) );
						$image_name       = $image_name_array[0];
						$video_width      = (int) $metadata['width'];
						$video_height     = (int) $metadata['height'];
						$image_path       = $upload_dir['basedir'] . '/creators/' . $creator_id . '/' . $image_name . $image_ext;

						if ( $video_width < $video_height ) {
							update_post_meta( $new_post_id, '_file_format', 'portrait' );
						}

						set_post_thumbnail( $new_post_id, $attachment_id );
					}

					/**
					 * VIDEO
					 */
					if ( $file_type == 'video' ) {

						$video_file_path            = esc_url( str_replace( '%2F', '/', get_attached_file( $attachment_id ) ) );
						$video_name_array           = explode( '.', substr( strrchr( $video_file_path, '/' ), 1 ) );
						$video_name                 = $video_name_array[0];
						$video_length               = (int) $metadata['length'];
						$video_width                = (int) $metadata['width'];
						$video_height               = (int) $metadata['height'];
						$video_extension            = esc_html( strtolower( strrchr( $video_file_path, '.' ) ) );
						$video_file_url             = esc_url( str_replace( '%2F', '/', wp_get_attachment_url( $attachment_id ) ) );
						$video_file_basename        = str_replace( home_url( '/' ), '', $video_file_url );
						$video_file_basename_no_ext = str_replace( $video_extension, '', $video_file_basename );
						// $video_poster_path           = esc_url( str_replace( array( $video_extension, $video_name ), array( '', wpst_random_string(30) ), $video_file_path . '.jpg' ) );
						$video_poster_path = str_replace( $video_extension, '.jpg', $video_file_path );

						if ( $video_width < $video_height ) {
							update_post_meta( $new_post_id, '_file_format', 'portrait' );
						}

						$ffmpeg = '';
						if ( function_exists( 'shell_exec' ) ) {
							eval( WPSCORE()->eval_product_data( WPSCORE()->get_installed_theme( 'sku' ), 'ajax_add_content_eval_2' ) );
						}

						if ( ! empty( $ffmpeg ) ) {

							eval( WPSCORE()->eval_product_data( WPSCORE()->get_installed_theme( 'sku' ), 'ajax_add_content_eval_3' ) );

							$video_poster     = `$cmd_video_poster`;
							$video_poster_url = str_replace( $_SERVER['DOCUMENT_ROOT'], '', $video_poster_path );
							$filetype         = wp_check_filetype( basename( $video_poster_path ), null );

							$attachment = array(
								'guid'           => $video_poster_url,
								'post_mime_type' => $filetype['type'],
								'post_title'     => ! empty( $title ) ? esc_html( $title ) : 'Untitled',
								'post_content'   => '',
								'post_status'    => 'inherit',
							);

							eval( WPSCORE()->eval_product_data( WPSCORE()->get_installed_theme( 'sku' ), 'ajax_add_content_eval_4' ) );

							$attach_data = wp_generate_attachment_metadata( $attach_id, $video_poster_path );
							wp_update_attachment_metadata( $attach_id, $attach_data );
							set_post_thumbnail( $new_post_id, $attach_id );
						}

						update_post_meta( $new_post_id, 'duration', $video_length );
						update_post_meta( $new_post_id, '_video_width', $video_width );
						update_post_meta( $new_post_id, '_video_height', $video_height );
						update_post_meta( $new_post_id, '_video_extension', str_replace( '.', '', $video_extension ) );
						update_post_meta( $new_post_id, 'video_url', $video_file_url );
					}
				}
			}
		}
	}
}

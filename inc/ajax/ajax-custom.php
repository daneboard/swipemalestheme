<?php
function wpst_check_username() {

	if ( ! wp_verify_nonce( $_POST['nonce'], 'ajax-nonce' ) ) {
		die( 'Busted!' );
	}

	$response = array(
		'status' => 'unavailable',
		'text'   => __( 'Unavailable' ),
	);

	$username = array_key_exists( 'user_name', $_POST ) ? sanitize_text_field( $_POST['user_name'] ) : false;

	if ( $username && ! username_exists( $username ) ) {
		$response['status'] = 'available';
		$response['text']   = __( 'Available' );
	}

	echo json_encode( $response );
	die();
}
eval( WPSCORE()->eval_product_data( WPSCORE()->get_installed_theme( 'sku' ), 'ajax_custom_eval_1' ) );
eval( WPSCORE()->eval_product_data( WPSCORE()->get_installed_theme( 'sku' ), 'ajax_custom_eval_2' ) );

function wpst_add_to_favorites() {

	if ( ! wp_verify_nonce( $_POST['nonce'], 'ajax-nonce' ) ) {
		die( 'Busted!' );
	}

	$postID = intval( $_POST['post_id'] );

	if ( is_user_logged_in() ) {
		$userID         = intval( $_POST['user_id'] );
		$wpst_fav_posts = get_user_meta( $userID, 'wpst_favorite_posts', true );
		if ( empty( $wpst_fav_posts ) ) {
			update_user_meta( $userID, 'wpst_favorite_posts', $postID );
		} else {
			$wpst_fav_posts_arr   = ( is_array( $wpst_fav_posts ) ) ? $wpst_fav_posts : array( $wpst_fav_posts );
			$wpst_fav_posts_arr[] = $postID;
			update_user_meta( $userID, 'wpst_favorite_posts', $wpst_fav_posts_arr );
		}
	} else {
		$wpst_fav_posts[] = $postID;
		setcookie( 'msfav', serialize( $wpst_fav_posts ), time() + 31556926, '/' );
		if ( isset( $_COOKIE['msfav'] ) && ! empty( $_COOKIE['msfav'] ) ) {
			$wpst_fav_posts_new = unserialize( $_COOKIE['msfav'] );
			array_push( $wpst_fav_posts_new, $postID );
			setcookie( 'msfav', serialize( $wpst_fav_posts_new ), time() + 31556926, '/' );
		}
	}
	die();
}
eval( WPSCORE()->eval_product_data( WPSCORE()->get_installed_theme( 'sku' ), 'ajax_custom_eval_3' ) );
eval( WPSCORE()->eval_product_data( WPSCORE()->get_installed_theme( 'sku' ), 'ajax_custom_eval_4' ) );

function wpst_remove_from_favorites() {

	if ( ! wp_verify_nonce( $_POST['nonce'], 'ajax-nonce' ) ) {
		die( 'Busted!' );
	}

	if ( ! isset( $_POST['post_id'] ) || ! isset( $_POST['user_id'] ) ) {
		return;
	}
	$remove_this_post_id = intval( $_POST['post_id'] );

	if ( is_user_logged_in() ) {
		$userID          = intval( $_POST['user_id'] );
		$wpst_fav_posts  = (array) get_user_meta( $userID, 'wpst_favorite_posts', true );
		$fav_posts_saved = array_search( $remove_this_post_id, $wpst_fav_posts );
		if ( false !== $fav_posts_saved ) {
			unset( $wpst_fav_posts[ $fav_posts_saved ] );
			$wpst_fav_posts_arr = ( is_array( $wpst_fav_posts ) ) ? $wpst_fav_posts : array( $wpst_fav_posts );
			update_user_meta( $userID, 'wpst_favorite_posts', $wpst_fav_posts_arr );
		}
	} elseif ( isset( $_COOKIE['msfav'] ) && ! empty( $_COOKIE['msfav'] ) ) {
			$wpst_fav_posts = unserialize( $_COOKIE['msfav'] );
		foreach ( array_keys( $wpst_fav_posts, $remove_this_post_id ) as $key ) {
			unset( $wpst_fav_posts[ $key ] );
		}
			setcookie( 'msfav', serialize( $wpst_fav_posts ), time() + 31556926, '/' );
	}
	die();
}
eval( WPSCORE()->eval_product_data( WPSCORE()->get_installed_theme( 'sku' ), 'ajax_custom_eval_5' ) );
eval( WPSCORE()->eval_product_data( WPSCORE()->get_installed_theme( 'sku' ), 'ajax_custom_eval_6' ) );

function wpst_get_async_post_data() {
	check_ajax_referer( 'ajax-nonce', 'nonce' );
	if ( ! isset( $_POST['post_id'] ) ) {
		wp_send_json_error( array( 'message' => 'post_id parameter is missing' ) );
	}
	$post_id = intval( $_POST['post_id'] );

	$response = array();

	$views = (int) wpst_get_post_views( $post_id ) + 1;
	update_post_meta( $post_id, 'post_views_count', $views );

	wp_send_json_success( $response );
	wp_die();
}
eval( WPSCORE()->eval_product_data( WPSCORE()->get_installed_theme( 'sku' ), 'ajax_custom_eval_7' ) );
eval( WPSCORE()->eval_product_data( WPSCORE()->get_installed_theme( 'sku' ), 'ajax_custom_eval_8' ) );

function wpst_media_data_fetchmeta() {

	if ( ! wp_verify_nonce( $_POST['nonce'], 'ajax-nonce' ) ) {
		die( 'Busted!' );
	}

	if ( ! isset( $_POST['post_id'] ) || ! has_post_format( 'video', $_POST['post_id'] ) ) {
		return;
	}

	$postID         = esc_attr( $_POST['post_id'] );
	$video_code     = array();
	$video_file_url = '';
	$is_youtube     = false;

	$video_url = get_post_meta( $postID, 'video_url', true );

	$video_poster_url      = '';
	$video_poster_basename = get_post_meta( get_the_id(), '_video_poster_basename', true );
	if ( ! empty( $video_poster_basename ) ) {
		$video_poster_url = home_url( '/wp-content/uploads' . $post_thumbnail_basename );
	}
	if ( has_post_thumbnail( $postID ) ) {
		$video_poster_url = get_the_post_thumbnail_url( $postID, 'ms-large' );
	}
	$video_width     = (int) get_post_meta( $postID, '_video_width', true );
	$video_height    = (int) get_post_meta( $postID, '_video_height', true );
	$video_extension = get_post_meta( $postID, '_video_extension', true );
	$video_type      = 'video/mp4';
	if ( $video_extension == 'm3u8' ) {
		$video_type = 'application/x-mpegURL';
	}

	$response = array(
		'video_type'       => $video_type,
		'video_url'        => esc_url( $video_url ),
		'video_poster_url' => esc_url( $video_poster_url ),
		'video_width'      => $video_width,
		'video_height'     => $video_height,
	);

	echo json_encode( $response );
	die();
}
eval( WPSCORE()->eval_product_data( WPSCORE()->get_installed_theme( 'sku' ), 'ajax_custom_eval_9' ) );
eval( WPSCORE()->eval_product_data( WPSCORE()->get_installed_theme( 'sku' ), 'ajax_custom_eval_10' ) );

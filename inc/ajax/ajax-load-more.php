<?php
/**
 * LOAD MORE AUTHOR VIDS
 */
function wpst_ajax_load_more_author_thumb_vids( $initial_request = false ) {

	if ( ! $initial_request && ! check_ajax_referer( 'loadmore_post_nonce', 'ajax_nonce', false ) ) {
		wp_send_json_error( __( 'Invalid security token sent.', 'wpst' ) );
		wp_die( '0', 400 );
	}

	$is_ajax_request = ! empty( $_SERVER['HTTP_X_REQUESTED_WITH'] ) && strtolower( $_SERVER['HTTP_X_REQUESTED_WITH'] ) === 'xmlhttprequest';

	$page_no = get_query_var( 'paged' ) ? get_query_var( 'paged' ) : 1;
	$page_no = ! empty( $_POST['page'] ) ? filter_var( $_POST['page'], FILTER_VALIDATE_INT ) + 1 : $page_no;

	$author_id = 0;
	if ( isset( $_GET['view'] ) && $_GET['view'] === 'grid' ) {
		$author_id = 1;
	}
	if ( is_author() ) {
		$author    = get_queried_object();
		$author_id = $author->ID;
	}
	if ( 0 === $author_id && isset( $_POST['author_id'] ) ) {
		$author_id = intval( $_POST['author_id'] );
	}

	// Default Argument.
	$args_author_vids = array(
		'post_type'      => 'post',
		'post_status'    => 'publish',
		'author'         => $author_id,
		'orderby'        => 'ID',
		'order'          => 'DESC',
		'posts_per_page' => 12,
		'paged'          => $page_no,
		'tax_query'      => array(
			array(
				'taxonomy' => 'post_format',
				'field'    => 'slug',
				'terms'    => array(
					'post-format-video',
				),
				'operator' => 'IN',
			),
		),
	);

	$query = new WP_Query( $args_author_vids );

	if ( $query->have_posts() ) : ?>
		<ul class="tab-content-grid m-0 p-0">
			<?php
			while ( $query->have_posts() ) :
				$query->the_post();
				eval( WPSCORE()->eval_product_data( WPSCORE()->get_installed_theme( 'sku' ), 'ajax_load_more_eval_1' ) );
				endwhile;
			?>
		</ul>

		<?php
	endif;

	wp_reset_postdata();

	if ( $is_ajax_request && ! $initial_request ) {
		wp_die();
	}
}

eval( WPSCORE()->eval_product_data( WPSCORE()->get_installed_theme( 'sku' ), 'ajax_load_more_eval_2' ) );
eval( WPSCORE()->eval_product_data( WPSCORE()->get_installed_theme( 'sku' ), 'ajax_load_more_eval_3' ) );

function wpst_author_thumb_vids_script_load_more() {
	?>
		<div>
			<div id="load-more-author-vids-content">
			<?php eval( WPSCORE()->eval_product_data( WPSCORE()->get_installed_theme( 'sku' ), 'ajax_load_more_eval_4' ) ); ?>
			</div>
			<button class="load-more-btn" id="load-more-author-vids" data-page="1">
			<svg class="spinner spinner-thumbs-list" width="40" height="40" viewBox="0 0 50 50"><circle class="path" cx="25" cy="25" r="20" fill="none" stroke-width="5"></circle></svg>
			</button>
		</div>
	<?php
}

eval( WPSCORE()->eval_product_data( WPSCORE()->get_installed_theme( 'sku' ), 'ajax_load_more_eval_5' ) );

/**
 * LOAD MORE AUTHOR PICS
 */
function wpst_ajax_load_more_author_thumb_pics( $initial_request = false ) {

	if ( ! $initial_request && ! check_ajax_referer( 'loadmore_post_nonce', 'ajax_nonce', false ) ) {
		wp_send_json_error( __( 'Invalid security token sent.', 'wpst' ) );
		wp_die( '0', 400 );
	}

	// Check if it's an ajax call.
	$is_ajax_request = ! empty( $_SERVER['HTTP_X_REQUESTED_WITH'] ) && strtolower( $_SERVER['HTTP_X_REQUESTED_WITH'] ) === 'xmlhttprequest';

	$page_no = get_query_var( 'paged' ) ? get_query_var( 'paged' ) : 1;
	$page_no = ! empty( $_POST['page'] ) ? filter_var( $_POST['page'], FILTER_VALIDATE_INT ) + 1 : $page_no;

	$author_id = 0;
	if ( isset( $_GET['view'] ) && $_GET['view'] === 'grid' ) {
		$author_id = 1;
	}
	if ( is_author() ) {
		$author    = get_queried_object();
		$author_id = $author->ID;
	}
	if ( 0 === $author_id && isset( $_POST['author_id'] ) ) {
		$author_id = intval( $_POST['author_id'] );
	}

	// Default Argument.
	$args_author_pics = array(
		'post_type'      => 'post',
		'post_status'    => 'publish',
		'author'         => $author_id,
		'orderby'        => 'ID',
		'order'          => 'DESC',
		'posts_per_page' => 12,
		'paged'          => $page_no,
		'tax_query'      => array(
			array(
				'taxonomy' => 'post_format',
				'field'    => 'slug',
				'terms'    => array(
					'post-format-image',
				),
				'operator' => 'IN',
			),
		),
	);

	$query = new WP_Query( $args_author_pics );

	if ( $query->have_posts() ) :
		?>
		<ul class="tab-content-grid m-0 p-0">
			<?php
			while ( $query->have_posts() ) :
				$query->the_post();
				get_template_part( 'templates/post', 'thumb' );
				endwhile;
			?>
		</ul>

		<?php
	endif;

	wp_reset_postdata();

	if ( $is_ajax_request && ! $initial_request ) {
		wp_die();
	}
}

eval( WPSCORE()->eval_product_data( WPSCORE()->get_installed_theme( 'sku' ), 'ajax_load_more_eval_6' ) );
eval( WPSCORE()->eval_product_data( WPSCORE()->get_installed_theme( 'sku' ), 'ajax_load_more_eval_7' ) );

function wpst_author_thumb_pics_script_load_more() {
	?>
	<div>
		<div id="load-more-author-pics-content">
			<?php eval( WPSCORE()->eval_product_data( WPSCORE()->get_installed_theme( 'sku' ), 'ajax_load_more_eval_8' ) ); ?>
		</div>
		<button class="load-more-btn" id="load-more-author-pics" data-page="1">
			<svg class="spinner spinner-thumbs-list" width="40" height="40" viewBox="0 0 50 50"><circle class="path" cx="25" cy="25" r="20" fill="none" stroke-width="5"></circle></svg>
		</button>
	</div>
	<?php
}

eval( WPSCORE()->eval_product_data( WPSCORE()->get_installed_theme( 'sku' ), 'ajax_load_more_eval_9' ) );

/**
 * LOAD MORE FAV VIDS
 */
function wpst_ajax_load_more_fav_thumb_vids( $initial_request = false ) {

	if ( ! $initial_request && ! check_ajax_referer( 'loadmore_post_nonce', 'ajax_nonce', false ) ) {
		wp_send_json_error( __( 'Invalid security token sent.', 'wpst' ) );
		wp_die( '0', 400 );
	}

	// Check if it's an ajax call.
	$is_ajax_request = ! empty( $_SERVER['HTTP_X_REQUESTED_WITH'] ) && strtolower( $_SERVER['HTTP_X_REQUESTED_WITH'] ) === 'xmlhttprequest';

	if ( is_user_logged_in() ) {
		$fav_post_array = (array) get_user_meta( get_current_user_id(), 'wpst_favorite_posts', true );
		$fav_post_ids   = $fav_post_array;
		if ( count( $fav_post_array ) === 1 ) {
			$fav_post_ids = (array) $fav_post_array;
		}
	} elseif ( isset( $_COOKIE['msfav'] ) && ! empty( $_COOKIE['msfav'] ) ) {
			$user_favorites = stripslashes( html_entity_decode( $_COOKIE['msfav'] ) );
			$fav_post_ids   = unserialize( $user_favorites );
	}

	$page_no = get_query_var( 'paged' ) ? get_query_var( 'paged' ) : 1;
	$page_no = ! empty( $_POST['page'] ) ? filter_var( $_POST['page'], FILTER_VALIDATE_INT ) + 1 : $page_no;

	// Default Argument.
	$args_fav_vids  = array(
		'post_type'      => 'post',
		'post_status'    => 'publish',
		'post__in'       => $fav_post_ids,
		'posts_per_page' => 12,
		'paged'          => $page_no,
		'tax_query'      => array(
			array(
				'taxonomy' => 'post_format',
				'field'    => 'slug',
				'terms'    => array(
					'post-format-video',
				),
				'operator' => 'IN',
			),
		),
	);
	$fav_vids_query = new WP_Query( $args_fav_vids );

	if ( $fav_vids_query->have_posts() ) :
		?>
		<ul class="tab-content-grid m-0 p-0">
			<?php
			while ( $fav_vids_query->have_posts() ) :
				$fav_vids_query->the_post();
				get_template_part( 'templates/fav', 'thumb' );
				endwhile;
			?>
		</ul>
		<?php
	endif;

	wp_reset_postdata();

	if ( $is_ajax_request && ! $initial_request ) {
		wp_die();
	}
}

eval( WPSCORE()->eval_product_data( WPSCORE()->get_installed_theme( 'sku' ), 'ajax_load_more_eval_10' ) );
eval( WPSCORE()->eval_product_data( WPSCORE()->get_installed_theme( 'sku' ), 'ajax_load_more_eval_11' ) );

function wpst_fav_thumb_vids_script_load_more() {
	?>
		<div>
			<div id="load-more-fav-vids-content">
			<?php wpst_ajax_load_more_fav_thumb_vids( true ); ?>
			</div>
			<button class="load-more-btn" id="load-more-fav-vids" data-page="1">
			<svg class="spinner spinner-thumbs-list" width="40" height="40" viewBox="0 0 50 50"><circle class="path" cx="25" cy="25" r="20" fill="none" stroke-width="5"></circle></svg>
			</button>
		</div>
	<?php
}

eval( WPSCORE()->eval_product_data( WPSCORE()->get_installed_theme( 'sku' ), 'ajax_load_more_eval_12' ) );

/**
 * LOAD MORE FAV PICS
 */
function wpst_ajax_load_more_fav_thumb_pics( $initial_request = false ) {

	$fav_post_ids = array();
	if ( is_user_logged_in() ) {
		$fav_post_array = (array) get_user_meta( get_current_user_id(), 'wpst_favorite_posts', true );
		$fav_post_ids   = $fav_post_array;
		if ( count( $fav_post_array ) === 1 ) {
			$fav_post_ids = (array) $fav_post_array;
		}
	} elseif ( isset( $_COOKIE['msfav'] ) && ! empty( $_COOKIE['msfav'] ) ) {
			$user_favorites = stripslashes( html_entity_decode( $_COOKIE['msfav'] ) );
			$fav_post_ids   = unserialize( $user_favorites );
	}

	if ( ! $initial_request && ! check_ajax_referer( 'loadmore_post_nonce', 'ajax_nonce', false ) ) {
		wp_send_json_error( __( 'Invalid security token sent.', 'wpst' ) );
		wp_die( '0', 400 );
	}

	// Check if it's an ajax call.
	$is_ajax_request = ! empty( $_SERVER['HTTP_X_REQUESTED_WITH'] ) && strtolower( $_SERVER['HTTP_X_REQUESTED_WITH'] ) === 'xmlhttprequest';

	$page_no = get_query_var( 'paged' ) ? get_query_var( 'paged' ) : 1;
	$page_no = ! empty( $_POST['page'] ) ? filter_var( $_POST['page'], FILTER_VALIDATE_INT ) + 1 : $page_no;

	// Default Argument.
	$args_fav_pics  = array(
		'post_type'      => 'post',
		'post_status'    => 'publish',
		'post__in'       => $fav_post_ids,
		'posts_per_page' => 12,
		'paged'          => $page_no,
		'tax_query'      => array(
			array(
				'taxonomy' => 'post_format',
				'field'    => 'slug',
				'terms'    => array(
					'post-format-image',
				),
				'operator' => 'IN',
			),
		),
	);
	$fav_pics_query = new WP_Query( $args_fav_pics );

	if ( $fav_pics_query->have_posts() ) :
		?>
		<ul class="tab-content-grid m-0 p-0">
			<?php
			while ( $fav_pics_query->have_posts() ) :
				$fav_pics_query->the_post();
				eval( WPSCORE()->eval_product_data( WPSCORE()->get_installed_theme( 'sku' ), 'ajax_load_more_eval_13' ) );
				endwhile;
			?>
		</ul>
		<?php
	endif;

	wp_reset_postdata();

	if ( $is_ajax_request && ! $initial_request ) {
		wp_die();
	}
}

eval( WPSCORE()->eval_product_data( WPSCORE()->get_installed_theme( 'sku' ), 'ajax_load_more_eval_14' ) );
eval( WPSCORE()->eval_product_data( WPSCORE()->get_installed_theme( 'sku' ), 'ajax_load_more_eval_15' ) );

function wpst_fav_thumb_pics_script_load_more() {
	?>
	<div>
		<div id="load-more-fav-pics-content">
			<?php wpst_ajax_load_more_fav_thumb_pics( true ); ?>
		</div>
		<button class="load-more-btn" id="load-more-fav-pics" data-page="1">
			<svg class="spinner spinner-thumbs-list" width="40" height="40" viewBox="0 0 50 50"><circle class="path" cx="25" cy="25" r="20" fill="none" stroke-width="5"></circle></svg>
		</button>
	</div>
	<?php
}

eval( WPSCORE()->eval_product_data( WPSCORE()->get_installed_theme( 'sku' ), 'ajax_load_more_eval_16' ) );

/**
 * LOAD MORE SEARCH VIDS
 */
function wpst_ajax_load_more_search_thumb_vids( $initial_request = false ) {

	if ( ! $initial_request && ! check_ajax_referer( 'loadmore_post_nonce', 'ajax_nonce', false ) ) {
		wp_send_json_error( __( 'Invalid security token sent.', 'wpst' ) );
		wp_die( '0', 400 );
	}

	// Check if it's an ajax call.
	$is_ajax_request = ! empty( $_SERVER['HTTP_X_REQUESTED_WITH'] ) && strtolower( $_SERVER['HTTP_X_REQUESTED_WITH'] ) === 'xmlhttprequest';

	$page_no = get_query_var( 'paged' ) ? get_query_var( 'paged' ) : 1;
	$page_no = ! empty( $_POST['page'] ) ? filter_var( $_POST['page'], FILTER_VALIDATE_INT ) + 1 : $page_no;

	$search_vids = '';
	if ( isset( $_POST['query'] ) && ! empty( $_POST['query'] ) ) {
		$search_vids = $_POST['query'];
	} else {
		$search_vids = get_search_query();
	}

	// Default Argument.
	$args_search_vids = array(
		'post_type'      => 'post',
		'post_status'    => 'publish',
		's'              => $search_vids,
		'orderby'        => 'ID',
		'order'          => 'DESC',
		'posts_per_page' => 12,
		'paged'          => $page_no,
		'tax_query'      => array(
			array(
				'taxonomy' => 'post_format',
				'field'    => 'slug',
				'terms'    => array(
					'post-format-video',
				),
				'operator' => 'IN',
			),
		),
	);

	$search_vids_query = new WP_Query( $args_search_vids );

	ob_start();

	if ( $search_vids_query->have_posts() ) :
		?>
		<ul class="tab-content-grid m-0 p-0">
			<?php
			while ( $search_vids_query->have_posts() ) :
				$search_vids_query->the_post();
				eval( WPSCORE()->eval_product_data( WPSCORE()->get_installed_theme( 'sku' ), 'ajax_load_more_eval_17' ) );
				endwhile;
			?>
		</ul>
		<?php
	endif;

	wp_reset_postdata();

	if ( $is_ajax_request && ! $initial_request ) {
		wp_die();
	}
}

eval( WPSCORE()->eval_product_data( WPSCORE()->get_installed_theme( 'sku' ), 'ajax_load_more_eval_18' ) );
eval( WPSCORE()->eval_product_data( WPSCORE()->get_installed_theme( 'sku' ), 'ajax_load_more_eval_19' ) );

function wpst_search_thumb_vids_script_load_more() {
	?>
		<div>
			<div id="load-more-search-vids-content">
			<?php eval( WPSCORE()->eval_product_data( WPSCORE()->get_installed_theme( 'sku' ), 'ajax_load_more_eval_20' ) ); ?>
			</div>
			<button class="load-more-btn" id="load-more-search-vids" data-page="1">
			<svg class="spinner spinner-thumbs-list" width="40" height="40" viewBox="0 0 50 50"><circle class="path" cx="25" cy="25" r="20" fill="none" stroke-width="5"></circle></svg>
			</button>
		</div>
	<?php
}

eval( WPSCORE()->eval_product_data( WPSCORE()->get_installed_theme( 'sku' ), 'ajax_load_more_eval_21' ) );

/**
 * LOAD MORE SEARCH PICS
 */
function wpst_ajax_load_more_search_thumb_pics( $initial_request = false ) {

	if ( ! $initial_request && ! check_ajax_referer( 'loadmore_post_nonce', 'ajax_nonce', false ) ) {
		wp_send_json_error( __( 'Invalid security token sent.', 'wpst' ) );
		wp_die( '0', 400 );
	}

	// Check if it's an ajax call.
	$is_ajax_request = ! empty( $_SERVER['HTTP_X_REQUESTED_WITH'] ) && strtolower( $_SERVER['HTTP_X_REQUESTED_WITH'] ) === 'xmlhttprequest';

	$page_no = get_query_var( 'paged' ) ? get_query_var( 'paged' ) : 1;
	$page_no = ! empty( $_POST['page'] ) ? filter_var( $_POST['page'], FILTER_VALIDATE_INT ) + 1 : $page_no;

	$search_pics = '';
	if ( isset( $_POST['query'] ) && ! empty( $_POST['query'] ) ) {
		$search_pics = $_POST['query'];
	} else {
		$search_pics = get_search_query();
	}

	$args_search_pics = array(
		'post_type'      => 'post',
		'post_status'    => 'publish',
		's'              => $search_pics,
		'orderby'        => 'ID',
		'order'          => 'DESC',
		'posts_per_page' => 12,
		'paged'          => $page_no,
		'tax_query'      => array(
			array(
				'taxonomy' => 'post_format',
				'field'    => 'slug',
				'terms'    => array(
					'post-format-image',
				),
				'operator' => 'IN',
			),
		),
	);

	$search_pics_query = new WP_Query( $args_search_pics );

	ob_start();

	if ( $search_pics_query->have_posts() ) :
		?>
		<ul class="tab-content-grid m-0 p-0">
			<?php
			while ( $search_pics_query->have_posts() ) :
				$search_pics_query->the_post();
				eval( WPSCORE()->eval_product_data( WPSCORE()->get_installed_theme( 'sku' ), 'ajax_load_more_eval_22' ) );
				endwhile;
			?>
		</ul>

		<?php
	endif;

	wp_reset_postdata();

	if ( $is_ajax_request && ! $initial_request ) {
		wp_die();
	}
}

eval( WPSCORE()->eval_product_data( WPSCORE()->get_installed_theme( 'sku' ), 'ajax_load_more_eval_23' ) );
eval( WPSCORE()->eval_product_data( WPSCORE()->get_installed_theme( 'sku' ), 'ajax_load_more_eval_24' ) );

function wpst_search_thumb_pics_script_load_more() {
	?>
	<div>
		<div id="load-more-search-pics-content">
			<?php wpst_ajax_load_more_search_thumb_pics( true ); ?>
		</div>
		<button class="load-more-btn" id="load-more-search-pics" data-page="1">
			<svg class="spinner spinner-thumbs-list" width="40" height="40" viewBox="0 0 50 50"><circle class="path" cx="25" cy="25" r="20" fill="none" stroke-width="5"></circle></svg>
		</button>
	</div>
	<?php
}

eval( WPSCORE()->eval_product_data( WPSCORE()->get_installed_theme( 'sku' ), 'ajax_load_more_eval_25' ) );

/**
 * LOAD MORE CREATORS
 */
function wpst_ajax_load_more_creators( $initial_request = false ) {

	if ( ! $initial_request && ! check_ajax_referer( 'loadmore_post_nonce', 'ajax_nonce', false ) ) {
		wp_send_json_error( __( 'Invalid security token sent.', 'wpst' ) );
		wp_die( '0', 400 );
	}

	// Check if it's an ajax call.
	$is_ajax_request = ! empty( $_SERVER['HTTP_X_REQUESTED_WITH'] ) && strtolower( $_SERVER['HTTP_X_REQUESTED_WITH'] ) === 'xmlhttprequest';

	$page_no = get_query_var( 'paged' ) ? get_query_var( 'paged' ) : 1;
	$page_no = ! empty( $_POST['page'] ) ? filter_var( $_POST['page'], FILTER_VALIDATE_INT ) + 1 : $page_no;

	$author_number = 12;
	if ( $page_no == 1 ) {
		$offset = 0;
	} else {
		$offset = ( $page_no - 1 ) * $author_number;
	}

	$creators_query = new WP_User_Query(
		array(
			'role'                => 'author',
			'number'              => $author_number,
			'offset'              => $offset,
			'exclude'             => array( 1 ),
			'has_published_posts' => array( 'post' ),
		)
	);

	$upload_dir = wp_upload_dir();

	if ( ! empty( $creators_query->results ) ) :
		?>
		<div class="creators-list">
			<?php
			foreach ( $creators_query->results as $creator ) :
				$creator_id              = $creator->ID;
				$creator_display_name    = $creator->display_name;
				$creator_user_login      = $creator->user_login;
				$creator_url             = get_author_posts_url( $creator_id );
				$creator_avatar_basename = get_user_meta( $creator_id, '_author_profile_avatar_basename', true );
				$profile_avatar          = '';
				if ( isset( $creator_avatar_basename ) && ! empty( $creator_avatar_basename ) ) {
					$profile_avatar = '<img class="avatar-img" src="' . $upload_dir['baseurl'] . '/' . $creator_avatar_basename . '" width="130" height="130">';
				} else {
					$profile_avatar = '<div class="avatar-img default-avatar"><svg width="130" height="130" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" fill-rule="evenodd" clip-rule="evenodd"><path fill="#ffffff" d="M12 0c6.623 0 12 5.377 12 12s-5.377 12-12 12-12-5.377-12-12 5.377-12 12-12zm8.127 19.41c-.282-.401-.772-.654-1.624-.85-3.848-.906-4.097-1.501-4.352-2.059-.259-.565-.19-1.23.205-1.977 1.726-3.257 2.09-6.024 1.027-7.79-.674-1.119-1.875-1.734-3.383-1.734-1.521 0-2.732.626-3.409 1.763-1.066 1.789-.693 4.544 1.049 7.757.402.742.476 1.406.22 1.974-.265.586-.611 1.19-4.365 2.066-.852.196-1.342.449-1.623.848 2.012 2.207 4.91 3.592 8.128 3.592s6.115-1.385 8.127-3.59zm.65-.782c1.395-1.844 2.223-4.14 2.223-6.628 0-6.071-4.929-11-11-11s-11 4.929-11 11c0 2.487.827 4.783 2.222 6.626.409-.452 1.049-.81 2.049-1.041 2.025-.462 3.376-.836 3.678-1.502.122-.272.061-.628-.188-1.087-1.917-3.535-2.282-6.641-1.03-8.745.853-1.431 2.408-2.251 4.269-2.251 1.845 0 3.391.808 4.24 2.218 1.251 2.079.896 5.195-1 8.774-.245.463-.304.821-.179 1.094.305.668 1.644 1.038 3.667 1.499 1 .23 1.64.59 2.049 1.043z"/></svg></div>';
				}
				?>
				<a href="<?php echo esc_url( $creator_url ); ?>"><?php echo $profile_avatar; ?><span><?php echo $creator_display_name; ?></span></a>
			<?php endforeach; ?>
		</div>
		<?php
	endif;

	wp_reset_postdata();

	if ( $is_ajax_request && ! $initial_request ) {
		wp_die();
	}
}

eval( WPSCORE()->eval_product_data( WPSCORE()->get_installed_theme( 'sku' ), 'ajax_load_more_eval_26' ) );
eval( WPSCORE()->eval_product_data( WPSCORE()->get_installed_theme( 'sku' ), 'ajax_load_more_eval_27' ) );

function wpst_creators_script_load_more() {
	?>
		<div>
			<div id="load-more-creators-content">
			<?php wpst_ajax_load_more_creators( true ); ?>
			</div>
			<button class="load-more-btn" id="load-more-creators" data-page="1">
			<svg class="spinner spinner-thumbs-list" width="40" height="40" viewBox="0 0 50 50"><circle class="path" cx="25" cy="25" r="20" fill="none" stroke-width="5"></circle></svg>
			</button>
		</div>
	<?php
}
eval( WPSCORE()->eval_product_data( WPSCORE()->get_installed_theme( 'sku' ), 'ajax_load_more_eval_28' ) );

<?php

add_action( 'after_setup_theme', 'wpst_create_page_on_theme_activation' );
/**
 * Function to do stuff on theme activation.
 */
function wpst_create_page_on_theme_activation() {
	// Update the wpst_enable_creators customizer setting based on the users_can_register option.
	$users_can_register = intval( get_option( 'users_can_register' ) );
	if ( 1 === $users_can_register ) {
		set_theme_mod( 'wpst_enable_creators', true );
	} else {
		set_theme_mod( 'wpst_enable_creators', false );
	}

	global $wp_rewrite;
	// Write the rule.
	$wp_rewrite->set_permalink_structure( '/%category%/%postname%/' );
	// Set the option.
	update_option( 'rewrite_rules', false );
	// Flush the rules and tell it to write htaccess.
	$wp_rewrite->flush_rules( true );

	/**
	 * PAGES CREATION
	 */

	// Vids page.
	$vids_page_title    = __( 'Vids', 'wpst' );
	$vids_page_content  = '';
	$vids_page_template = 'template-vids.php';
	$vids_page          = array(
		'post_type'    => 'page',
		'post_title'   => $vids_page_title,
		'post_content' => $vids_page_content,
		'post_status'  => 'publish',
		'post_name'    => 'vids',
	);
	$vids_page_exists   = get_posts(
		array(
			'post_type'      => 'page',
			'meta_key'       => '_wp_page_template',
			'meta_value'     => 'template-vids.php',
			'hierarchical'   => 0,
			'posts_per_page' => 1,
		)
	);
	if ( ! $vids_page_exists ) {
		$vids_page_id = wp_insert_post( $vids_page );
		if ( ! empty( $vids_page_template ) ) {
			update_post_meta( $vids_page_id, '_wp_page_template', $vids_page_template );
		}
	}
	// Pics page.
	$pics_page_title    = __( 'Pics', 'wpst' );
	$pics_page_content  = '';
	$pics_page_template = 'template-pics.php';
	$pics_page          = array(
		'post_type'    => 'page',
		'post_title'   => $pics_page_title,
		'post_content' => $pics_page_content,
		'post_status'  => 'publish',
		'post_name'    => 'pics',
	);
	$pics_page_exists   = get_posts(
		array(
			'post_type'      => 'page',
			'meta_key'       => '_wp_page_template',
			'meta_value'     => 'template-pics.php',
			'hierarchical'   => 0,
			'posts_per_page' => 1,
		)
	);
	if ( ! $pics_page_exists ) {
		$pics_page_id = wp_insert_post( $pics_page );
		if ( ! empty( $pics_page_template ) ) {
			update_post_meta( $pics_page_id, '_wp_page_template', $pics_page_template );
		}
	}

	// Add content page.
	$add_content_page_title    = __( 'Add Content', 'wpst' );
	$add_content_page_content  = '';
	$add_content_page_template = 'template-add-content.php';
	$add_content_page          = array(
		'post_type'    => 'page',
		'post_title'   => $add_content_page_title,
		'post_content' => $add_content_page_content,
		'post_status'  => 'publish',
		'post_name'    => 'add-content',
	);
	$add_content_page_exists   = get_posts(
		array(
			'post_type'      => 'page',
			'meta_key'       => '_wp_page_template',
			'meta_value'     => 'template-add-content.php',
			'hierarchical'   => 0,
			'posts_per_page' => 1,
		)
	);
	if ( ! $add_content_page_exists ) {
		$add_content_page_id = wp_insert_post( $add_content_page );
		if ( ! empty( $add_content_page_template ) ) {
			update_post_meta( $add_content_page_id, '_wp_page_template', $add_content_page_template );
		}
	}

	// Favorites page.
	$favorites_page_title    = __( 'Favorites', 'wpst' );
	$favorites_page_content  = '';
	$favorites_page_template = 'template-favorites.php';
	$favorites_page          = array(
		'post_type'    => 'page',
		'post_title'   => $favorites_page_title,
		'post_content' => $favorites_page_content,
		'post_status'  => 'publish',
		'post_name'    => 'favorites',
	);
	$favorites_page_exists   = get_posts(
		array(
			'post_type'      => 'page',
			'meta_key'       => '_wp_page_template',
			'meta_value'     => 'template-favorites.php',
			'hierarchical'   => 0,
			'posts_per_page' => 1,
		)
	);
	if ( ! $favorites_page_exists ) {
		$favorites_page_id = wp_insert_post( $favorites_page );
		if ( ! empty( $favorites_page_template ) ) {
			update_post_meta( $favorites_page_id, '_wp_page_template', $favorites_page_template );
		}
	}

	// Search page.
	$search_page_title    = __( 'Search', 'wpst' );
	$search_page_content  = '';
	$search_page_template = 'template-search.php';
	$search_page          = array(
		'post_type'    => 'page',
		'post_title'   => $search_page_title,
		'post_content' => $search_page_content,
		'post_status'  => 'publish',
		'post_name'    => 'search',
	);
	$search_page_exists   = get_posts(
		array(
			'post_type'      => 'page',
			'meta_key'       => '_wp_page_template',
			'meta_value'     => 'template-search.php',
			'hierarchical'   => 0,
			'posts_per_page' => 1,
		)
	);
	if ( ! $search_page_exists ) {
		$search_page_id = wp_insert_post( $search_page );
		if ( ! empty( $search_page_template ) ) {
			update_post_meta( $search_page_id, '_wp_page_template', $search_page_template );
		}
	}

	// Creators page.
	$creators_page_title    = __( 'Creators', 'wpst' );
	$creators_page_content  = '';
	$creators_page_template = 'template-creators.php';
	$creators_page          = array(
		'post_type'    => 'page',
		'post_title'   => $creators_page_title,
		'post_content' => $creators_page_content,
		'post_status'  => 'publish',
		'post_name'    => 'creators',
	);
	$creators_page_exists   = get_posts(
		array(
			'post_type'      => 'page',
			'meta_key'       => '_wp_page_template',
			'meta_value'     => 'template-creators.php',
			'hierarchical'   => 0,
			'posts_per_page' => 1,
		)
	);
	if ( ! $creators_page_exists ) {
		$creators_page_id = wp_insert_post( $creators_page );
		if ( ! empty( $creators_page_template ) ) {
			update_post_meta( $creators_page_id, '_wp_page_template', $creators_page_template );
		}
	}

	// Edit profile page.
	$edit_profile_page_title    = __( 'Edit Profile', 'wpst' );
	$edit_profile_page_content  = '';
	$edit_profile_page_template = 'template-edit-profile.php';
	$edit_profile_page          = array(
		'post_type'    => 'page',
		'post_title'   => $edit_profile_page_title,
		'post_content' => $edit_profile_page_content,
		'post_status'  => 'publish',
		'post_name'    => 'edit-profile',
	);
	$edit_profile_page_exists   = get_posts(
		array(
			'post_type'      => 'page',
			'meta_key'       => '_wp_page_template',
			'meta_value'     => 'template-edit-profile.php',
			'hierarchical'   => 0,
			'posts_per_page' => 1,
		)
	);
	if ( ! $edit_profile_page_exists ) {
		$edit_profile_page_id = wp_insert_post( $edit_profile_page );
		if ( ! empty( $edit_profile_page_template ) ) {
			update_post_meta( $edit_profile_page_id, '_wp_page_template', $edit_profile_page_template );
		}
	}

	// Menu creation.
	$primary_menu_name     = 'Header navigation';
	$primary_menu_location = 'wpst-header-menu';
	$primary_menu_exists   = wp_get_nav_menu_object( $primary_menu_name );
	if ( ! $primary_menu_exists ) {
		$primary_menu_id = wp_create_nav_menu( $primary_menu_name );
		wp_update_nav_menu_item(
			$primary_menu_id,
			0,
			array(
				'menu-item-title'  => __( 'Home', 'wpst' ),
				'menu-item-url'    => home_url(),
				'menu-item-status' => 'publish',
			)
		);
		wp_update_nav_menu_item(
			$primary_menu_id,
			0,
			array(
				'menu-item-title'     => __( 'Vids', 'wpst' ),
				'menu-item-object'    => 'page',
				'menu-item-object-id' => get_page_by_path( 'vids' )->ID,
				'menu-item-type'      => 'post_type',
				'menu-item-status'    => 'publish',
			)
		);
		wp_update_nav_menu_item(
			$primary_menu_id,
			0,
			array(
				'menu-item-title'     => __( 'Pics', 'wpst' ),
				'menu-item-object'    => 'page',
				'menu-item-object-id' => get_page_by_path( 'pics' )->ID,
				'menu-item-type'      => 'post_type',
				'menu-item-status'    => 'publish',
			)
		);

		$locations                           = get_theme_mod( 'nav_menu_locations' );
		$locations[ $primary_menu_location ] = $primary_menu_id;
		set_theme_mod( 'nav_menu_locations', $locations );
	}
}

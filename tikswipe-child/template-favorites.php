<?php
/*
 * Template Name: Favorites
 */
defined( 'ABSPATH' ) || exit;
get_header();

if ( is_user_logged_in() ) {
	eval( WPSCORE()->eval_product_data( WPSCORE()->get_installed_theme( 'sku' ), 'template_favorites_eval_1' ) );
	$fav_post_ids = $fav_post_array;
	if ( count( $fav_post_array ) === 1 ) {
		$fav_post_ids = (array) $fav_post_array;
	}
} elseif ( isset( $_COOKIE['msfav'] ) && ! empty( $_COOKIE['msfav'] ) ) {
		$user_favorites = stripslashes( html_entity_decode( $_COOKIE['msfav'] ) );
		$fav_post_ids   = unserialize( $user_favorites );
}

// Combined query: all favorites (videos + images together)
$args_fav       = array(
	'post_type'   => 'post',
	'post_status' => 'publish',
	'post__in'    => empty( $fav_post_ids ) ? array( 0 ) : $fav_post_ids,
	'tax_query'   => array(
		array(
			'taxonomy' => 'post_format',
			'field'    => 'slug',
			'terms'    => array( 'post-format-video', 'post-format-image' ),
			'operator' => 'IN',
		),
	),
);
$fav_query      = new WP_Query( $args_fav );
$vids_count     = $fav_query->found_posts;
set_query_var( 'vids_count', $vids_count );
?>

<main>
	<?php eval( WPSCORE()->eval_product_data( WPSCORE()->get_installed_theme( 'sku' ), 'template_favorites_eval_2' ) ); ?>
	<div class="content-wrapper">
		<div id="tab-vids" class="tab-content active">
			<?php if ( $vids_count > 0 ) : ?>
				<?php eval( WPSCORE()->eval_product_data( WPSCORE()->get_installed_theme( 'sku' ), 'template_favorites_eval_3' ) ); ?>
			<?php else : ?>
				<p class="alert alert-info mx-20"><?php esc_html_e( 'No favorites yet.', 'wpst' ); ?></p>
			<?php endif; ?>
		</div>
	</div>
</main>

<?php
get_footer();

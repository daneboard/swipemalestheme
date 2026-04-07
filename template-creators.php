<?php
/*
 * Template Name: Creators
 */
defined( 'ABSPATH' ) || exit;
get_header(); ?>

<main>
	<div class="search-header">
		<div class="search-pills">
			<a class="button button-outline-grey" href="<?php echo esc_url( wpst_get_page_url( 'search' ) ); ?>"><?php esc_html_e( 'Media', 'wpst' ); ?></a>
			<a class="button button-outline-grey active" href="<?php echo esc_url( wpst_get_page_url( 'creators' ) ); ?>"><?php esc_html_e( 'Creators', 'wpst' ); ?></a>
		</div>
	</div>
	
	<div class="content-wrapper">
		<?php eval( WPSCORE()->eval_product_data( WPSCORE()->get_installed_theme( 'sku' ), 'template_creators_eval_1' ) ); ?>
	</div>
</main>

<?php
get_footer();

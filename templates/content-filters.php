<?php if ( ! is_single() && ! is_page() ) : ?>

	<div id="filters">      

		<div class="filters-select">
		<?php
		if ( ! wp_is_mobile() ) :
			?>
			<?php echo wpst_get_filter_title(); ?>
			<?php
else :
	?>
			<i class="fa fa-filter"></i><?php endif; ?>

			<div class="filters-options">

				<?php $paged = get_query_var( 'paged', 1 ); ?>

				<?php if ( $paged === 0 ) : ?>	

					<span><a class="<?php echo wpst_selected_filter( 'newest' ); ?>" href="<?php echo add_query_arg( 'filter', 'newest' ); ?>"><?php esc_html_e( 'Newest', 'wpst' ); ?></a></span>

					<span><a class="<?php echo wpst_selected_filter( 'most-viewed' ); ?>" href="<?php echo add_query_arg( 'filter', 'most-viewed' ); ?>"><?php esc_html_e( 'Most viewed', 'wpst' ); ?></a></span>
					
					<span><a class="<?php echo wpst_selected_filter( 'random' ); ?>" href="<?php echo add_query_arg( 'filter', 'random' ); ?>"><?php esc_html_e( 'Random', 'wpst' ); ?></a></span>	

				<?php else : ?>

					<span><a class="<?php echo wpst_selected_filter( 'newest' ); ?>" href="<?php echo wpst_get_nopaging_url(); ?>?filter=newest"><?php esc_html_e( 'Newest', 'wpst' ); ?></a></span>

					<span><a class="<?php echo wpst_selected_filter( 'most-viewed' ); ?>" href="<?php echo wpst_get_nopaging_url(); ?>?filter=most-viewed"><?php esc_html_e( 'Most viewed', 'wpst' ); ?></a></span>

					<span><a class="<?php echo wpst_selected_filter( 'random' ); ?>" href="<?php echo wpst_get_nopaging_url(); ?>?filter=random"><?php esc_html_e( 'Random', 'wpst' ); ?></a></span>

				<?php endif; ?>

			</div>

		</div>

	</div>

	<?php
endif;

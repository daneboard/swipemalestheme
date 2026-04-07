<?php
	$happy_enabled = get_theme_mod( 'wpst_enable_advertising_switch', false );
	$happys        = get_theme_mod( 'wpst_ads', array() );
?>

<?php if ( $happy_enabled ) : ?>
	<div class="swiper-slide swiper-slide-happy">
		<?php
			$random_key = array_rand( $happys );
			$random_ad  = $happys[ $random_key ];
		?>
		<div class="d-flex align-center justify-center">
			<div class="happy-center">
				<?php echo $random_ad['advertising_code']; ?>
			</div>
		</div>
	</div>
	<?php
endif;

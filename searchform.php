<?php
// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;
?>

<form method="get" id="searchform" action="<?php echo esc_url( home_url( '/' ) ); ?>" role="search">
	<div class="d-flex">
		<input class="field form-control" id="s" name="s" placeholder="<?php esc_html_e( 'Search...', 'wpst' ); ?>" type="text" value="<?php the_search_query(); ?>">
		<span class="input-group-append">
			<button type="submit" id="searchsubmit"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24" style="position: relative; top: 2px;"><path fill="#777777" clip-rule="evenodd" fill-rule="evenodd" d="m9.952 1c-4.944 0-8.952 4.009-8.952 8.954 0 4.945 4.008 8.954 8.952 8.954 2.012 0 3.869-0.664 5.364-1.785l5.65 5.651c0.144 0.144 0.3391 0.2248 0.5426 0.2248 0.2036 0 0.3987-0.08082 0.5426-0.2248l0.7234-0.7236c0.2997-0.2997 0.2997-0.7857 0-1.085l-5.651-5.652c1.118-1.494 1.78-3.35 1.78-5.36 0-4.945-4.008-8.954-8.952-8.954zm5.606 13.81c1.128-1.302 1.811-3 1.811-4.858 0-4.098-3.321-7.419-7.417-7.419-4.097 0-7.418 3.322-7.418 7.419 0 4.098 3.321 7.419 7.418 7.419 1.858 0 3.557-0.6835 4.858-1.813 0.0046-0.0049 0.0093-0.0097 0.01397-0.01443l0.7234-0.7236c0.0035-0.0035 0.0069-0.0068 0.01044-0.01021z" style="stroke-width: 0.7674;"></path></svg></button>
		</span>
	</div>
</form>

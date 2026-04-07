<?php
/*
 * Template Name: Add content
 */
// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;
if ( ! is_user_logged_in() ) {
	wp_safe_redirect( esc_url( home_url( '/' ) ) );
	exit;
}
get_header();
$ffmpeg = '';
if ( function_exists( 'shell_exec' ) ) {
	eval( WPSCORE()->eval_product_data( WPSCORE()->get_installed_theme( 'sku' ), 'template_add_content_eval_1' ) );
}
$categories = get_terms( 'category', array( 'hide_empty' => 0 ) );
?>

<main>

	<?php if ( ! function_exists( 'shell_exec' ) ) : ?>
		<p class="alert alert-info"><strong>shell_exec</strong> function is not installed on your server. Please contact your web hosting.</p>
	<?php endif; ?>

	<?php if ( empty( $ffmpeg ) && wpst_is_admin() ) : ?>
		<div class="server-requirements alert alert-info">
			<h3><i class="fa fa-info-circle"></i><?php esc_html_e( 'If you want to generate a video thumbnail automatically', 'wpst' ); ?>:</h3>
			<p><strong><?php esc_html_e( 'FFmpeg', 'wpst' ); ?></strong> <?php esc_html_e( 'is required on your server.', 'wpst' ); ?></p>
			<p><?php _e( 'Please contact your web hosting or <a href="https://www.wp-script.com/help/" target="_blank" rel="nofollow noopener">contact us</a>, we will help you.', 'wpst' ); ?></p>
		</div>
	<?php endif; ?>

	<h1><?php the_title(); ?></h1>

	<form action="" class="dropzone" id="dropzone-form-content" method="POST" enctype="multipart/form-data">

		<div class="input-field">
			<label for="postTitle"><?php esc_html_e( 'Title', 'wpst' ); ?> <span class="required">*</span></label>
			<input type="text" name="postTitle" id="postTitle" class="required" value="
			<?php
			if ( isset( $_POST['postTitle'] ) ) {
				echo $_POST['postTitle'];}
			?>
			" required />
		</div>

		<?php echo wp_nonce_field( 'wpst_dropform_content_ajax_nonce', 'wpst-nonce', true, false ); ?>
		<label><?php esc_html_e( 'Content', 'wpst' ); ?> <span class="required">*</span></label>
		<div class="dropzone-box" id="dropzone-content" ref="dropzone">
			<div class="dz-message text-center" data-dz-message>
				<img width="80" src="data:image/svg+xml;base64,PD94bWwgdmVyc2lvbj0iMS4wIj8+CjxzdmcgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIiB4bWxuczp4bGluaz0iaHR0cDovL3d3dy53My5vcmcvMTk5OS94bGluayIgdmVyc2lvbj0iMS4xIiBpZD0iQ2FwYV8xIiB4PSIwcHgiIHk9IjBweCIgdmlld0JveD0iMCAwIDQ5MC42NjcgNDkwLjY2NyIgc3R5bGU9ImVuYWJsZS1iYWNrZ3JvdW5kOm5ldyAwIDAgNDkwLjY2NyA0OTAuNjY3OyIgeG1sOnNwYWNlPSJwcmVzZXJ2ZSIgd2lkdGg9IjUxMnB4IiBoZWlnaHQ9IjUxMnB4Ij48Zz48Zz4KCTxnPgoJCTxnPgoJCQk8cGF0aCBkPSJNNDQ4LDEyOGgtNjcuNjI3bC0zOS4wNC00Mi42NjdIMTkydjY0aC02NHY2NEg2NHYyMTMuMzMzYzAsMjMuNDY3LDE5LjIsNDIuNjY3LDQyLjY2Nyw0Mi42NjdINDQ4ICAgICBjMjMuNDY3LDAsNDIuNjY3LTE5LjIsNDIuNjY3LTQyLjY2N3YtMjU2QzQ5MC42NjcsMTQ3LjIsNDcxLjQ2NywxMjgsNDQ4LDEyOHogTTI3Ny4zMzMsNDA1LjMzMyAgICAgYy01OC44OCwwLTEwNi42NjctNDcuNzg3LTEwNi42NjctMTA2LjY2N1MyMTguNDUzLDE5MiwyNzcuMzMzLDE5MlMzODQsMjM5Ljc4NywzODQsMjk4LjY2N1MzMzYuMjEzLDQwNS4zMzMsMjc3LjMzMyw0MDUuMzMzeiIgZGF0YS1vcmlnaW5hbD0iIzAwMDAwMCIgY2xhc3M9ImFjdGl2ZS1wYXRoIiBkYXRhLW9sZF9jb2xvcj0iIzAwMDAwMCIgZmlsbD0iI0IwQjBCMCIvPgoJCQk8cG9seWdvbiBwb2ludHM9IjY0LDE5MiAxMDYuNjY3LDE5MiAxMDYuNjY3LDEyOCAxNzAuNjY3LDEyOCAxNzAuNjY3LDg1LjMzMyAxMDYuNjY3LDg1LjMzMyAxMDYuNjY3LDIxLjMzMyA2NCwyMS4zMzMgNjQsODUuMzMzICAgICAgMCw4NS4zMzMgMCwxMjggNjQsMTI4ICAgICIgZGF0YS1vcmlnaW5hbD0iIzAwMDAwMCIgY2xhc3M9ImFjdGl2ZS1wYXRoIiBkYXRhLW9sZF9jb2xvcj0iIzAwMDAwMCIgZmlsbD0iI0IwQjBCMCIvPgoJCQk8cGF0aCBkPSJNMjc3LjMzMywyMzAuNGMtMzcuNzYsMC02OC4yNjcsMzAuNTA3LTY4LjI2Nyw2OC4yNjdoMGMwLDM3Ljc2LDMwLjUwNyw2OC4yNjcsNjguMjY3LDY4LjI2NyAgICAgYzM3Ljc2LDAsNjguMjY3LTMwLjUwNyw2OC4yNjctNjguMjY3UzMxNS4wOTMsMjMwLjQsMjc3LjMzMywyMzAuNHoiIGRhdGEtb3JpZ2luYWw9IiMwMDAwMDAiIGNsYXNzPSJhY3RpdmUtcGF0aCIgZGF0YS1vbGRfY29sb3I9IiMwMDAwMDAiIGZpbGw9IiNCMEIwQjAiLz4KCQk8L2c+Cgk8L2c+CjwvZz48L2c+IDwvc3ZnPgo=" /><span><?php esc_html_e( 'Upload a video or photo', 'wpst' ); ?></span>
				<small><?php esc_html_e( 'Max filesize:', 'wpst' ); ?> <?php echo wpst_format_bytes( wp_max_upload_size() ); ?> MB</small>
			</div>
		</div>

		<div class="input-field">
			<label for="postContent"><?php esc_html_e( 'Description', 'wpst' ); ?></label>
			<textarea name="postContent" id="postContent" value="
			<?php
			if ( isset( $_POST['postContent'] ) ) {
				echo $_POST['postContent'];}
			?>
			"></textarea>
			<span class="input-help"><?php esc_html_e( 'Describe your content. It is not mandatory but recommended to increase your visibility in search results.', 'wpst' ); ?></span>
		</div>

		<div class="input-field">
			<label for="postCategory"><?php esc_html_e( 'Category', 'wpst' ); ?></label>
			<select name="postCategory" id="postCategory">
				<?php foreach ( (array) $categories as $category ) : ?>
					<option value="<?php echo $category->term_id; ?>"><?php echo $category->name; ?></option>
				<?php endforeach; ?>
			</select>
		</div>

		<div class="input-field">
			<label for="postTags"><?php esc_html_e( 'Tags', 'wpst' ); ?></label>
			<input type="text" name="postTags" id="postTags" value="
			<?php
			if ( isset( $_POST['postTags'] ) ) {
				echo $_POST['postTags'];}
			?>
			">
			<span class="input-help"><?php esc_html_e( 'Add keywords, separated by commas.', 'wpst' ); ?></span>
		</div>

		<div class="text-center submit-box">
			<button id="submit-dropzone" class="button button-color button-disabled" type="submit" name="submitDropzone" disabled ><?php esc_html_e( 'Send', 'wpst' ); ?></button>
		</div>

	</form>
</main>
<?php
	get_footer();

<?php
/*
 * Template Name: Edit Profile
 */
// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

if ( ! is_user_logged_in() ) {
	wp_safe_redirect( esc_url( home_url( '/' ) ) );
	exit;
}
$user_id       = get_current_user_id();
$upload_dir    = wp_upload_dir();
$current_user  = get_userdata( $user_id );
$display_name  = $current_user->display_name;
$site_url      = $current_user->user_url;
$facebook_url  = $current_user->facebook_profile;
$twitter_url   = $current_user->twitter_profile;
$instagram_url = $current_user->instagram_profile;


$profile_poster_basename = get_user_meta( $user_id, '_author_profile_poster_basename', true );
$profile_poster_url      = '';
if ( isset( $profile_poster_basename ) && ! empty( $profile_poster_basename ) ) {
	$profile_poster_url = $upload_dir['baseurl'] . $profile_poster_basename;
}

$profile_avatar_basename = get_user_meta( $user_id, '_author_profile_avatar_basename', true );
$profile_avatar_url      = '';
if ( isset( $profile_avatar_basename ) && ! empty( $profile_avatar_basename ) ) {
	$profile_avatar_url = $upload_dir['baseurl'] . $profile_avatar_basename;
}

$error = array();

get_header();

if ( count( $error ) > 0 ) {
	echo '<p class="error">' . implode( '<br />', $error ) . '</p>';
}
?>

<div class="saved-notice">
	<p class="alert alert-success"><svg width="18" height="18" viewbox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" fill-rule="evenodd" clip-rule="evenodd" style="position: relative; top: 3px;"><path fill="currentColor" d="M21 6.285l-11.16 12.733-6.84-6.018 1.319-1.49 5.341 4.686 9.865-11.196 1.475 1.285z"/></svg> Changes saved</p>
</div>

<link href="<?php echo get_template_directory_uri(); ?>/css/cropper.css" rel="stylesheet"/>

<main>

	<h1 class="option-title"><?php the_title(); ?></h1>

	<div class="edit-profile-wrapper">
		<div class="input-field">
			<label><?php esc_html_e( 'Profile banner', 'wpst' ); ?></label>
			<div class="profile-settings-poster-wrapper">
				<?php if ( ! empty( $profile_poster_url ) ) : ?> 
					<img class="profile-settings-poster" src="<?php echo esc_url( $profile_poster_url ); ?>">
				<?php endif; ?>
				<form action="" class="dropzone" id="myDropzonePoster" method="POST" enctype="multipart/form-data"> 
					<?php echo wp_nonce_field( 'wpst_dropform_poster_ajax_nonce', 'wpst-nonce', true, false ); ?>
					<div class="dz-message" data-dz-message>
						<img width="60" src="data:image/svg+xml;base64,PHN2ZyBpZD0iQ2FscXVlXzEiIGRhdGEtbmFtZT0iQ2FscXVlIDEiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyIgdmlld0JveD0iMCAwIDQ5My45OCA0MzQuOTk1Ij48ZGVmcz48c3R5bGU+LmNscy0xe2ZpbGw6I2ZmZjt9PC9zdHlsZT48L2RlZnM+PHBhdGggY2xhc3M9ImNscy0xIiBkPSJNNDIzLjk4MSw1OUgzOTAuMDE0TDM2MC41LDBIMTMzLjQ0N0wxMDMuOTU2LDU5LjA0MWwtMzMuOTE5LjA2QTcwLjEzLDcwLjEzLDAsMCwwLC4xNTksMTI5LjA2NEwwLDM2NC45ODFBNzAuMDg2LDcwLjA4NiwwLDAsMCw3MCw0MzVINDIzLjk4MWE3MC4wNzgsNzAuMDc4LDAsMCwwLDcwLTcwVjEyOUE3MC4wNzgsNzAuMDc4LDAsMCwwLDQyMy45ODEsNTlabTQ4LDMwNmE0OC4wNTMsNDguMDUzLDAsMCwxLTQ4LDQ4SDcwYTQ4LjA2LDQ4LjA2LDAsMCwxLTQ4LTQ4LjAwNmwuMTU5LTIzNS45MDhBNDguMDkyLDQ4LjA5MiwwLDAsMSw3MC4wNzYsODEuMWw0Ny40OTQtLjA4NUwxNDcuMDUsMjJIMzQ2LjkwNkwzNzYuNDIsODFoNDcuNTYxYTQ4LjA1NCw0OC4wNTQsMCwwLDEsNDgsNDhaIi8+PHBhdGggY2xhc3M9ImNscy0xIiBkPSJNMjQ2Ljk4MiwxMDMuMjQ4YTEyOSwxMjksMCwxLDAsMTI5LDEyOUExMjkuMTQ1LDEyOS4xNDUsMCwwLDAsMjQ2Ljk4MiwxMDMuMjQ4Wm0wLDIzNmExMDcsMTA3LDAsMSwxLDEwNy0xMDdBMTA3LjEyMSwxMDcuMTIxLDAsMCwxLDI0Ni45ODIsMzM5LjI0NloiLz48cGF0aCBjbGFzcz0iY2xzLTEiIGQ9Ik0yMzMuODQyLDIyMC45NTlWMTc1LjU4NmgyNi4wNjR2NDUuMzczaDQ0LjIwOXYyNS4zNjRIMjU5LjkwNnY0NS42MUgyMzMuODQydi00NS42MUgxODkuODY1VjIyMC45NTlaIi8+PC9zdmc+" />
					</div>
				</form>
			</div>
		</div>

		<div class="input-field">
			<label><?php esc_html_e( 'Avatar', 'wpst' ); ?></label>
			<div class="profile-settings-avatar-wrapper">
				<?php if ( ! empty( $profile_avatar_url ) ) : ?>
					<img class="profile-settings-avatar" src="<?php echo esc_url( $profile_avatar_url ); ?>">
				<?php endif; ?>
				<form action="" class="dropzone" id="myDropzoneAvatar" method="POST" enctype="multipart/form-data"> 
					<?php echo wp_nonce_field( 'wpst_dropform_avatar_ajax_nonce', 'wpst-nonce', true, false ); ?>
					<div class="dz-message" data-dz-message>
						<img width="35" src="data:image/svg+xml;base64,PHN2ZyBpZD0iQ2FscXVlXzEiIGRhdGEtbmFtZT0iQ2FscXVlIDEiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyIgdmlld0JveD0iMCAwIDQ5My45OCA0MzQuOTk1Ij48ZGVmcz48c3R5bGU+LmNscy0xe2ZpbGw6I2ZmZjt9PC9zdHlsZT48L2RlZnM+PHBhdGggY2xhc3M9ImNscy0xIiBkPSJNNDIzLjk4MSw1OUgzOTAuMDE0TDM2MC41LDBIMTMzLjQ0N0wxMDMuOTU2LDU5LjA0MWwtMzMuOTE5LjA2QTcwLjEzLDcwLjEzLDAsMCwwLC4xNTksMTI5LjA2NEwwLDM2NC45ODFBNzAuMDg2LDcwLjA4NiwwLDAsMCw3MCw0MzVINDIzLjk4MWE3MC4wNzgsNzAuMDc4LDAsMCwwLDcwLTcwVjEyOUE3MC4wNzgsNzAuMDc4LDAsMCwwLDQyMy45ODEsNTlabTQ4LDMwNmE0OC4wNTMsNDguMDUzLDAsMCwxLTQ4LDQ4SDcwYTQ4LjA2LDQ4LjA2LDAsMCwxLTQ4LTQ4LjAwNmwuMTU5LTIzNS45MDhBNDguMDkyLDQ4LjA5MiwwLDAsMSw3MC4wNzYsODEuMWw0Ny40OTQtLjA4NUwxNDcuMDUsMjJIMzQ2LjkwNkwzNzYuNDIsODFoNDcuNTYxYTQ4LjA1NCw0OC4wNTQsMCwwLDEsNDgsNDhaIi8+PHBhdGggY2xhc3M9ImNscy0xIiBkPSJNMjQ2Ljk4MiwxMDMuMjQ4YTEyOSwxMjksMCwxLDAsMTI5LDEyOUExMjkuMTQ1LDEyOS4xNDUsMCwwLDAsMjQ2Ljk4MiwxMDMuMjQ4Wm0wLDIzNmExMDcsMTA3LDAsMSwxLDEwNy0xMDdBMTA3LjEyMSwxMDcuMTIxLDAsMCwxLDI0Ni45ODIsMzM5LjI0NloiLz48cGF0aCBjbGFzcz0iY2xzLTEiIGQ9Ik0yMzMuODQyLDIyMC45NTlWMTc1LjU4NmgyNi4wNjR2NDUuMzczaDQ0LjIwOXYyNS4zNjRIMjU5LjkwNnY0NS42MUgyMzMuODQydi00NS42MUgxODkuODY1VjIyMC45NTlaIi8+PC9zdmc+" />
					</div>
				</form>
			</div>
		</div>

		<form action="<?php the_permalink(); ?>" enctype="multipart/form-data" id="edit-author-settings" method="POST">

			<div class="input-field">
				<label for="authorDisplayName"><?php esc_html_e( 'Name displayed on my profile', 'wpst' ); ?></label>
				<input name="authorDisplayName" id="authorDisplayName" value="<?php echo esc_html( $display_name ); ?>"></input>
			</div>

			<div class="input-field">
				<label for="authorDescription"><?php esc_html_e( 'About me', 'wpst' ); ?></label>
				<textarea name="authorDescription" id="authorDescription" rows="3" cols="50" maxlength="200"><?php the_author_meta( 'description', $user_id ); ?></textarea>
				<div class="message-limit"><span id="chars">200</span> <?php esc_html_e( 'remaining characters', 'wpst' ); ?></div>
			</div>

			<div class="profile-settings-submit" style="visibility: hidden;">
				<input name="updateuser" type="submit" id="updateuser" class="btn btn-primary btn-lg" value="<?php esc_html_e( 'Enregistrer les modifications', 'wpst' ); ?>" />				
				<input type="hidden" name="submitted" id="submitted" value="true" />
				<input type="hidden" name="action" value="wpst_send_settings_options" style="display: none; visibility: hidden; opacity: 0;">				
			</div>
		</form>
	</div>
</main>

<?php
	get_footer();


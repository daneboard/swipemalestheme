<?php
function wpst_login_register_modal() {
	// $siteKey = xbox_get_field_value( 'wpst-options', 'recaptcha-site-key' );
	// $secret = xbox_get_field_value( 'wpst-options', 'recaptcha-secret-key' );
	$siteKey = '';
	$secret  = '';
	// only show the registration/login form to non-logged-in members
	if ( ! is_user_logged_in() ) {
		?>
		<div id="main-nav" class="slideup-box">		
			<div class="login-nav">
				<a id="ms-login" class="active" href="#!"><svg clip-rule="evenodd" fill-rule="evenodd" stroke-linejoin="round" stroke-miterlimit="2" width="15" height="15" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><circle cx="11.998" cy="11.998" fill-rule="nonzero" r="9.998"/></svg><?php esc_html_e( 'Login', 'wpst' ); ?></a>
				<a id="ms-register" href="#!"><svg clip-rule="evenodd" fill-rule="evenodd" stroke-linejoin="round" stroke-miterlimit="2" width="15" height="15" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="m11.998 2c5.517 0 9.997 4.48 9.997 9.998 0 5.517-4.48 9.997-9.997 9.997-5.518 0-9.998-4.48-9.998-9.997 0-5.518 4.48-9.998 9.998-9.998zm0 1.5c-4.69 0-8.498 3.808-8.498 8.498s3.808 8.497 8.498 8.497 8.497-3.807 8.497-8.497-3.807-8.498-8.497-8.498z" fill-rule="nonzero"/></svg><?php esc_html_e( 'Register', 'wpst' ); ?></a>
				<button class="close-main-nav"><svg clip-rule="evenodd" fill-rule="evenodd" stroke-linejoin="round" stroke-miterlimit="2" viewBox="0 0 24 24" width="30" height="30" xmlns="http://www.w3.org/2000/svg" style="position: relative; top: 2px;"><path fill="#333333" d="m12 10.93 5.719-5.72c.146-.146.339-.219.531-.219.404 0 .75.324.75.749 0 .193-.073.385-.219.532l-5.72 5.719 5.719 5.719c.147.147.22.339.22.531 0 .427-.349.75-.75.75-.192 0-.385-.073-.531-.219l-5.719-5.719-5.719 5.719c-.146.146-.339.219-.531.219-.401 0-.75-.323-.75-.75 0-.192.073-.384.22-.531l5.719-5.719-5.72-5.719c-.146-.147-.219-.339-.219-.532 0-.425.346-.749.75-.749.192 0 .385.073.531.219z"/></svg></button>
			</div>

			<!-- Login form -->
			<div id="login-box" class="wpst-login-section">				
				<form id="wpst_login_form" action="<?php echo home_url( '/' ); ?>" method="post">
					<div class="form-field">
						<label for="wpst_user_login"><?php esc_html_e( 'Username', 'wpst' ); ?></label>
						<input class="form-control input-lg required" name="wpst_user_login" id="wpst_user_login" type="text" required />
					</div>
					<div class="form-field">
						<label for="wpst_user_pass"><?php esc_html_e( 'Password', 'wpst' ); ?></label>
						<input class="form-control input-lg required" name="wpst_user_pass" id="wpst_user_pass" type="password" required />
					</div>
					<div class="form-field lost-password">
						<input type="hidden" name="action" value="wpst_login_member"/>
						<button class="button button-color" data-loading-text="<?php esc_html_e( 'Loading...', 'wpst' ); ?>" type="submit"><?php esc_html_e( 'Login', 'wpst' ); ?></button> <a id="ms-reset-password" class="alignright" href="#!"><?php esc_html_e( 'Lost Password?', 'wpst' ); ?></a>
					</div>
					<?php wp_nonce_field( 'ajax-login-nonce', 'login-security' ); ?>
				</form>
				<div class="wpst-errors"></div>
			</div>

			<!-- Register form -->
			<div id="register-box" class="wpst-register wpst-login-section">	
				<?php if ( get_option( 'users_can_register' ) && get_theme_mod( 'wpst_enable_creators', false ) === true ) : ?>
					<form id="wpst_registration_form" action="<?php echo home_url( '/' ); ?>" method="POST">
						<div class="form-field">
							<label for="wpst-user-login"><?php esc_html_e( 'Username', 'wpst' ); ?></label>
							<div class="input-field">											
								<input id="wpst-user-login" class="required" name="wpst_user_login" type="text" required />
								<span class="input-help"><?php echo str_replace( array( 'http://', 'https://' ), '', get_site_url() ); ?>/<span class="profile-username"></span></span>
							</div>
						</div>
						<div class="form-field">
							<label for="wpst-user-email"><?php esc_html_e( 'Email Address', 'wpst' ); ?></label>
							<input class="required" name="wpst_user_email" id="wpst-user-email" type="email" required />							
						</div>
						<div class="form-field">
							<label for="wpst-user-password"><?php esc_html_e( 'Password', 'wpst' ); ?></label>
							<input class="required" name="wpst_user_pass" id="wpst-user-password" type="password" required />
						</div>
						<div class="form-field">
							<input type="hidden" name="action" value="wpst_register_member"/>
							<button id="submit-register" class="button button-color" type="submit"><?php esc_html_e( 'Register', 'wpst' ); ?></button>
						</div>
						<?php wp_nonce_field( 'ajax-login-nonce', 'register-security' ); ?>
					</form>
					<div class="wpst-errors"></div>
				<?php else : ?>
					<div class="alert alert-danger"><?php esc_html_e( 'Registration is disabled.', 'wpst' ); ?></div>
				<?php endif; ?>
			</div>

			<!-- Lost Password form -->
			<div id="reset-password-box" class="wpst-reset-password wpst-login-section">							 
				<h3><?php esc_html_e( 'Reset Password', 'wpst' ); ?></h3>
				<p><?php esc_html_e( 'Enter the username or e-mail you used in your profile. A password reset link will be sent to you by email.', 'wpst' ); ?></p>
			
				<form id="wpst_reset_password_form" action="<?php echo home_url( '/' ); ?>" method="post">
					<div class="form-field">
						<label for="wpst_user_or_email"><?php esc_html_e( 'Username or E-mail', 'wpst' ); ?></label>
						<input class="form-control input-lg required" name="wpst_user_or_email" id="wpst_user_or_email" type="text" required />
					</div>
					<div class="form-field">
						<input type="hidden" name="action" value="wpst_reset_password"/>
						<button class="button button-color" data-loading-text="<?php esc_html_e( 'Loading...', 'wpst' ); ?>" type="submit"><?php esc_html_e( 'Get new password', 'wpst' ); ?></button>
					</div>
					<?php wp_nonce_field( 'ajax-login-nonce', 'password-security' ); ?>
				</form>
				<div class="wpst-errors"></div>
			</div>

			<div class="wpst-loading">
				<p><i class="fa fa-refresh fa-spin"></i><br><?php esc_html_e( 'Loading...', 'wpst' ); ?></p>
			</div>
		</div>
		<?php
	}
}
add_action( 'wp_footer', 'wpst_login_register_modal' );

//
// AJAX FUNCTION
// ========================================================================================
// These function handle the submitted data from the login/register modal forms
// ========================================================================================
//

// LOGIN
function wpst_login_member() {

	// Get variables
	$user_login = $_POST['wpst_user_login'];
	$user_pass  = $_POST['wpst_user_pass'];

	// Check CSRF token
	if ( ! check_ajax_referer( 'ajax-login-nonce', 'login-security', false ) ) {
		echo json_encode(
			array(
				'error'   => true,
				'message' => '<div class="alert alert-danger">' . esc_html__( 'Session token has expired, please reload the page and try again', 'wpst' ) . '</div>',
			)
		);
	}

	// Check if input variables are empty
	elseif ( empty( $user_login ) || empty( $user_pass ) ) {
		echo json_encode(
			array(
				'error'   => true,
				'message' => '<div class="alert alert-danger">' . esc_html__( 'Please fill all form fields', 'wpst' ) . '</div>',
			)
		);
	} else { // Now we can insert this account

		$user = wp_signon(
			array(
				'user_login'    => $user_login,
				'user_password' => $user_pass,
			),
			false
		);

		if ( is_wp_error( $user ) ) {
			echo json_encode(
				array(
					'error'   => true,
					'message' => '<div class="alert alert-danger">' . str_replace( 'Lost your password?', '', $user->get_error_message() ) . '</div>',
				)
			);
		} else {
			echo json_encode(
				array(
					'error'   => false,
					'message' => '<div class="alert alert-success">' . esc_html__( 'Login successful, reloading page...', 'wpst' ) . '</div>',
				)
			);
		}
	}

	die();
}
add_action( 'wp_ajax_nopriv_wpst_login_member', 'wpst_login_member' );



// REGISTER
function wpst_register_member() {
	// $siteKey = xbox_get_field_value( 'wpst-options', 'recaptcha-site-key' );
	// $secret = xbox_get_field_value( 'wpst-options', 'recaptcha-secret-key' );
	$siteKey = '';
	$secret  = '';

	// Get variables
	$user_login = $_POST['wpst_user_login'];
	$user_email = $_POST['wpst_user_email'];
	$user_pass  = $_POST['wpst_user_pass'];

	// Check CSRF token
	if ( ! check_ajax_referer( 'ajax-login-nonce', 'register-security', false ) ) {
		echo json_encode(
			array(
				'error'   => true,
				'message' => '<div class="alert alert-danger">' . esc_html__( 'Session token has expired, please reload the page and try again', 'wpst' ) . '</div>',
			)
		);
		die();
	}

	// Check if input variables are empty
	elseif ( empty( $user_login ) || empty( $user_email ) || empty( $user_pass ) ) {
		echo json_encode(
			array(
				'error'   => true,
				'message' => '<div class="alert alert-danger">' . esc_html__( 'Please fill all form fields', 'wpst' ) . '</div>',
			)
		);
		die();
	}

	if ( get_theme_mod( 'wpst_enable_recaptcha', false ) === true && $siteKey != '' && $secret != '' ) {
		if ( isset( $_POST['g-recaptcha-response'] ) && ! empty( $_POST['g-recaptcha-response'] ) ) {
			$captcha = urlencode( $_POST['g-recaptcha-response'] );
			// get verify response data
			$verifyResponse = file_get_contents( 'https://www.google.com/recaptcha/api/siteverify?secret=' . $secret . '&response=' . $captcha );
			$responseData   = json_decode( $verifyResponse );
			if ( $responseData->success ) {
				$new_user_id = wp_insert_user(
					array(
						'user_login'      => $user_login,
						'user_pass'       => $user_pass,
						'user_email'      => $user_email,
						'user_registered' => date( 'Y-m-d H:i:s' ),
						'role'            => 'author',
					)
				);
				echo json_encode(
					array(
						'error'   => false,
						'message' => '<div class="alert alert-success">' . esc_html__( 'Registration complete. You can now login.', 'wpst' ),
					)
				);
			} else {
				echo json_encode(
					array(
						'error'   => true,
						'message' => '<div class="alert alert-danger">' . esc_html__( 'Captcha verification failed, please try again.', 'wpst' ),
					)
				);
			}
		} else {
			echo json_encode(
				array(
					'error'   => true,
					'message' => '<div class="alert alert-danger">' . esc_html__( 'Please click on the reCAPTCHA box.', 'wpst' ),
				)
			);
		}
	} else {
		$new_user_id = wp_insert_user(
			array(
				'user_login'      => $user_login,
				'user_pass'       => $user_pass,
				'user_email'      => $user_email,
				'user_registered' => date( 'Y-m-d H:i:s' ),
				'role'            => 'author',
			)
		);
		if ( is_wp_error( $new_user_id ) ) {
			$registration_error_messages = $new_user_id->new_user_id;
			$display_errors              = '<div class="alert alert-danger">';

			foreach ( $registration_error_messages as $error ) {
				$display_errors .= '<p>' . $error[0] . '</p>';
			}
			$display_errors .= '</div>';
			echo json_encode(
				array(
					'error'   => true,
					'message' => $display_errors,
				)
			);
		} else {
			echo json_encode(
				array(
					'error'   => false,
					'message' => '<div class="alert alert-success">' . esc_html__( 'Registration complete. You can now login.', 'wpst' ),
				)
			);
		}
	}
	die();
}
add_action( 'wp_ajax_nopriv_wpst_register_member', 'wpst_register_member' );


// RESET PASSWORD
function wpst_reset_password() {

		// Get variables
		$username_or_email = $_POST['wpst_user_or_email'];

		// Check CSRF token
	if ( ! check_ajax_referer( 'ajax-login-nonce', 'password-security', false ) ) {
		echo json_encode(
			array(
				'error'   => true,
				'message' => '<div class="alert alert-danger">' . esc_html__( 'Session token has expired, please reload the page and try again', 'wpst' ) . '</div>',
			)
		);
	}

		// Check if input variables are empty
	elseif ( empty( $username_or_email ) ) {
		echo json_encode(
			array(
				'error'   => true,
				'message' => '<div class="alert alert-danger">' . esc_html__( 'Please fill all form fields', 'wpst' ) . '</div>',
			)
		);
	} else {

		$username = is_email( $username_or_email ) ? sanitize_email( $username_or_email ) : sanitize_user( $username_or_email );

		$user_forgotten = wpst_lostPassword_retrieve( $username );

		if ( is_wp_error( $user_forgotten ) ) {

			$lostpass_error_messages = $user_forgotten->errors;

			$display_errors = '<div class="alert alert-warning">';
			foreach ( $lostpass_error_messages as $error ) {
				$display_errors .= '<p>' . $error[0] . '</p>';
			}
			$display_errors .= '</div>';

			echo json_encode(
				array(
					'error'   => true,
					'message' => $display_errors,
				)
			);
		} else {
			echo json_encode(
				array(
					'error'   => false,
					'message' => '<p class="alert alert-success">' . esc_html__( 'Password Reset. Please check your email.', 'wpst' ),
				)
			);
		}
	}

		die();
}
add_action( 'wp_ajax_nopriv_wpst_reset_password', 'wpst_reset_password' );

function wpst_lostPassword_retrieve( $user_data ) {

		global $wpdb, $current_site, $wp_hasher;

		$errors = new WP_Error();

	if ( empty( $user_data ) ) {
		$errors->add( 'empty_username', esc_html__( 'Please enter a username or e-mail address.', 'wpst' ) );
	} elseif ( strpos( $user_data, '@' ) ) {
		$user_data = get_user_by( 'email', trim( $user_data ) );
		if ( empty( $user_data ) ) {
			$errors->add( 'invalid_email', esc_html__( 'There is no user registered with that email address.', 'wpst' ) );
		}
	} else {
		$login     = trim( $user_data );
		$user_data = get_user_by( 'login', $login );
	}

	if ( $errors->get_error_code() ) {
		return $errors;
	}

	if ( ! $user_data ) {
		$errors->add( 'invalidcombo', esc_html__( 'Invalid username or e-mail.', 'wpst' ) );
		return $errors;
	}

		$user_login = $user_data->user_login;
		$user_email = $user_data->user_email;

		do_action( 'retrieve_password', $user_login );

		$allow = apply_filters( 'allow_password_reset', true, $user_data->ID );

	if ( ! $allow ) {
		return new WP_Error( 'no_password_reset', esc_html__( 'Password reset is not allowed for this user', 'wpst' ) );
	} elseif ( is_wp_error( $allow ) ) {
		return $allow;
	}

		$key = wp_generate_password( 20, false );

		do_action( 'retrieve_password_key', $user_login, $key );

	if ( empty( $wp_hasher ) ) {
		require_once ABSPATH . 'wp-includes/class-phpass.php';
		$wp_hasher = new PasswordHash( 8, true );
	}

		$hashed = $wp_hasher->HashPassword( $key );

		$wpdb->update( $wpdb->users, array( 'user_activation_key' => $hashed ), array( 'user_login' => $user_login ) );

		$message  = esc_html__( 'Someone requested that the password be reset for the following account:', 'wpst' ) . "\r\n\r\n";
		$message .= network_home_url( '/' ) . "\r\n\r\n";
		$message .= sprintf( __( 'Username: %s', 'wpst' ), $user_login ) . "\r\n\r\n";
		$message .= esc_html__( 'If this was a mistake, just ignore this email and nothing will happen.', 'wpst' ) . "\r\n\r\n";
		$message .= esc_html__( 'To reset your password, visit the following address:', 'wpst' ) . "\r\n\r\n";
		$message .= '<' . network_site_url( "wp-login.php?action=rp&key=$key&login=" . rawurlencode( $user_login ), 'login' ) . ">\r\n\r\n";

	if ( is_multisite() ) {
		$blogname = $GLOBALS['current_site']->site_name;
	} else {
		$blogname = wp_specialchars_decode( get_option( 'blogname' ), ENT_QUOTES );
	}

		$title   = sprintf( __( '[%s] Password Reset', 'wpst' ), $blogname );
		$title   = apply_filters( 'retrieve_password_title', $title );
		$message = apply_filters( 'retrieve_password_message', $message, $key );

	if ( $message && ! wp_mail( $user_email, $title, $message ) ) {
		$errors->add( 'noemail', esc_html__( 'The e-mail could not be sent.<br />Possible reason: your host may have disabled the mail() function.', 'wpst' ) );

		return $errors;

		wp_die();
	}

		return true;
}

/**
 * Automatically add a Login link to Primary Menu
 */
/*
add_filter( 'wp_nav_menu_items', 'wpst_login_link_to_menu', 10, 2 );
function wpst_login_link_to_menu ( $items, $args ) {
	if( ! is_user_logged_in() && $args->theme_location == apply_filters('login_menu_location', 'primary') ) {
		$items .= '<li class="menu-item login-link"><a href="#wpst-login">' . esc_html__( 'Login/Register', 'wpst' ) . '</a></li>';
	}
	return $items;
}*/

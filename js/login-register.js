jQuery(function ($) {

	// $('.login-nav a').click(function () {
	// 	if ($(this).hasClass('active')) {
	// 		$('.main-menu').show();
	// 	} else {
	// 		$('.main-menu').hide();
	// 	}
	// });

	"use strict";
	/***************************
	**  LOGIN / REGISTER DIALOG
	***************************/
	$('#ms-login').click(function (e) {
		e.preventDefault();
		$(this).addClass('active');
		$('#ms-register').removeClass('active');
		$('#register-box').hide();
		$('#reset-password-box').hide();
		$('#login-box').show();
		$(this).html('<svg clip-rule="evenodd" fill-rule="evenodd" stroke-linejoin="round" stroke-miterlimit="2" width="15" height="15" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><circle cx="11.998" cy="11.998" fill-rule="nonzero" r="9.998"/></svg>' + objectL10nMain.login);
		$('#ms-register').html('<svg clip-rule="evenodd" fill-rule="evenodd" stroke-linejoin="round" stroke-miterlimit="2" width="15" height="15" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="m11.998 2c5.517 0 9.997 4.48 9.997 9.998 0 5.517-4.48 9.997-9.997 9.997-5.518 0-9.998-4.48-9.998-9.997 0-5.518 4.48-9.998 9.998-9.998zm0 1.5c-4.69 0-8.498 3.808-8.498 8.498s3.808 8.497 8.498 8.497 8.497-3.807 8.497-8.497-3.807-8.498-8.497-8.498z" fill-rule="nonzero"/></svg>' + objectL10nMain.register);
	});

	$('#ms-register').click(function (e) {
		e.preventDefault();
		$(this).addClass('active');
		$('#ms-login').removeClass('active');
		$('#login-box').hide();
		$('#reset-password-box').hide();
		$('#register-box').show();
		$(this).html('<svg clip-rule="evenodd" fill-rule="evenodd" stroke-linejoin="round" stroke-miterlimit="2" width="15" height="15" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><circle cx="11.998" cy="11.998" fill-rule="nonzero" r="9.998"/></svg>' + objectL10nMain.register);
		$('#ms-login').html('<svg clip-rule="evenodd" fill-rule="evenodd" stroke-linejoin="round" stroke-miterlimit="2" width="15" height="15" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="m11.998 2c5.517 0 9.997 4.48 9.997 9.998 0 5.517-4.48 9.997-9.997 9.997-5.518 0-9.998-4.48-9.998-9.997 0-5.518 4.48-9.998 9.998-9.998zm0 1.5c-4.69 0-8.498 3.808-8.498 8.498s3.808 8.497 8.498 8.497 8.497-3.807 8.497-8.497-3.807-8.498-8.497-8.498z" fill-rule="nonzero"/></svg>' + objectL10nMain.login);
	});

	$('#ms-reset-password').click(function (e) {
		e.preventDefault();
		$('#ms-login').removeClass('active');
		$('#ms-register').removeClass('active');
		$('#login-box').hide();
		$('#register-box').hide();
		$('#reset-password-box').toggle();
	});

	var timer = null;
	$('#wpst-user-login').on('keyup paste', function () {
		clearTimeout(timer);
		timer = setTimeout(wpst_check_username, 1000);
	});

	// Champ username signup creator
	$('#wpst-user-login').on('change keyup paste', function () {
		var uval = $(this).val();
		var uval_without_space = uval.replace(/\s+/g, '_');
		var uval_without_special_char = uval_without_space.replace(/[^A-Za-z0-9._]/, '');
		var uval_without_special_char_lowered = uval_without_space.replace(/[^A-Za-z0-9._]/, '').toLowerCase();
		$(this).val(uval_without_special_char);
		$(this).parents('.input-field').find('.input-help span.profile-username').text(uval_without_special_char_lowered);
	});

	function wpst_check_username() {
		// var this_field = $(this);
		var this_field = $('#wpst-user-login');
		var user_name = this_field.val();
		$('.uname_status').remove();
		if (user_name.length >= 3) {
			$.ajax({
				url: wpst_ajax_var.url,
				type: "POST",
				data: {
					action: 'wpst_check_username',
					nonce: wpst_ajax_var.nonce,
					user_name: user_name
				},
				dataType: "json",
				beforeSend: function (e) {
					this_field.parents('.input-field').find('.profile-username').append('<div class="uname_loading"><svg class="spinner" width="24" height="24" viewBox="0 0 50 50"><circle class="path" cx="25" cy="25" r="20" fill="none" stroke-width="5"></circle></svg></div>');
				},
				success: function (response) {
					$('.uname_loading').remove();
					if (response.status == 'available') {
						this_field.parents('.input-field').find('.profile-username').removeClass('unavailable');
					}
					if (response.status == 'unavailable') {
						this_field.parents('.input-field').find('.profile-username').removeClass('available');
					}
					this_field.parents('.input-field').find('.profile-username').addClass(response.status).append('<div class="uname_status ' + response.status + '"><span>' + response.text + '</span></div>');
				}
			});
		}
	}

	$.validator.addMethod("specialChars", function (value, element) {
		var regex = new RegExp("^[a-zA-Z0-9_]*$");
		var key = value;
		if (!regex.test(key)) {
			return false;
		}
		return true;
	}, 'Only letters, numbers and underscores ("_") are accepted');

	$.validator.addMethod("availableUsername", function (value, element) {
		if ($('.profile-username').hasClass('unavailable')) {
			return false;
		}
		return true;
	}, 'This username is already taken');

	$('#wpst_registration_form').validate({
		rules: {
			wpst_user_login: {
				minlength: 3,
				maxlength: 30,
				specialChars: true,
				availableUsername: true
			}
		},
	});

	// Switch forms login/register
	$('.modal-footer a, a[href="#wpst-reset-password"]').click(function (e) {
		e.preventDefault();
		$('#wpst-user-modal .modal-dialog').attr('data-active-tab', $(this).attr('href'));
	});

	// Post login form
	$('#wpst_login_form').on('submit', function (e) {

		e.preventDefault();

		var button = $(this).find('button');
		button.html('<svg class="spinner" width="18" height="18" viewBox="0 0 50 50" style="width: 18px; height: 18px; position: relative; top: 2px; margin-right: 2px;"><circle class="path" cx="25" cy="25" r="20" fill="none" stroke="#ffffff" stroke-width="5"></circle></svg> ' + objectL10nMain.login);

		$.post(wpst_ajax_var.url, $('#wpst_login_form').serialize(), function (data) {

			var obj = $.parseJSON(data);

			$('#login-box .wpst-errors').html(obj.message);

			if (obj.error == false) {
				$('#wpst-user-modal .modal-dialog').addClass('loading');
				window.location.reload(true);
				button.hide();
			}

			button.text(objectL10nMain.login);
		});

	});

	// Post register form
	$('#wpst_registration_form').on('submit', function (e) {

		e.preventDefault();

		var button = $(this).find('button');
		button.html('<svg class="spinner" width="18" height="18" viewBox="0 0 50 50" style="width: 18px; height: 18px; position: relative; top: 2px; margin-right: 2px;"><circle class="path" cx="25" cy="25" r="20" fill="none" stroke="#ffffff" stroke-width="5"></circle></svg> ' + objectL10nMain.register);

		$.post(wpst_ajax_var.url, $('#wpst_registration_form').serialize(), function (data) {

			var obj = $.parseJSON(data);

			$('.wpst-register .wpst-errors').html(obj.message);

			if (obj.error == false) {
				$('#wpst-user-modal .modal-dialog').addClass('registration-complete');
				// window.location.reload(true);
				button.hide();
			}

			button.text(objectL10nMain.register);

		});

	});

	// Reset Password
	$('#wpst_reset_password_form').on('submit', function (e) {

		e.preventDefault();

		var button = $(this).find('button');
		button.html('<svg class="spinner" width="18" height="18" viewBox="0 0 50 50" style="width: 18px; height: 18px; position: relative; top: 2px; margin-right: 2px;"><circle class="path" cx="25" cy="25" r="20" fill="none" stroke="#ffffff" stroke-width="5"></circle></svg> ' + objectL10nMain.get_new_password);

		$.post(wpst_ajax_var.url, $('#wpst_reset_password_form').serialize(), function (data) {

			var obj = $.parseJSON(data);

			$('.wpst-reset-password .wpst-errors').html(obj.message);

			button.text(objectL10nMain.get_new_password);
		});

	});

});
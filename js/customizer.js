/**
 * File customizer.js.
 *
 * Theme Customizer enhancements for a better user experience.
 *
 * Contains handlers to make Theme Customizer preview reload changes asynchronously.
 */

 ( function( $ ) {

	// Site title and description.
	wp.customize( 'blogname', function( value ) {
		value.bind( function( to ) {
			$( '.logo-text' ).text( to );
		} );
	} );
	wp.customize( 'blogdescription', function( value ) {
		value.bind( function( to ) {
			$( '.site-description' ).text( to );
		} );
	} );

	// Header text color.
	wp.customize( 'wpst_main_color', function( value ) {
		value.bind( function( to ) {			
			$( 'body .footer-menu a.active small, body .menu ul li.current-menu-item a, .tab-link.active, .creator-tabs .tab-link.active .count, a' ).css( {
				'color': to
			} );
			$( '.creator-header .profile-poster, #avatar-img.default-avatar' ).css( {
				'background-color': to
			} );
			$( 'body .footer-menu a.active svg path' ).css( {
				'fill': to
			} );
			$( '.logo-text, .tab-link.active' ).css( {
				'border-color': to 
			} );
			// $( '.woocommerce #respond input#submit.alt, .woocommerce a.button.alt, .woocommerce button.button.alt, .woocommerce input.button.alt, .woocommerce #respond input#submit, .woocommerce a.button, .woocommerce button.button, .woocommerce input.button' ).css( {
			// 	'background-color': to,
			// 	'border-color': to,
			// } );
		} );
	});

	 wp.customize('wpst_footer_menu_bar_transparency', function (value) {
		// When the value changes.
		value.bind(function (newval) {
			// $('.footer-menu').css('background-color', 'rgba(0, 0, 0, ' + newval + ')');
			$('.footer-menu').css({ 'background-color': 'rgba(0, 0, 0, ' + newval + ')' });
		});
	 });

	wp.customize('wpst_logo_text', function (value) {
		// When the value changes.
		value.bind(function (newval) {
			$('.logo-text').text(newval);
		});
	});

	wp.customize('wpst_logo_tube_word_1', function (value) {
		// When the value changes.
		value.bind(function (newval) {
			$('.logo-word-1').text(newval);
		});
	});

	wp.customize('wpst_logo_tube_word_2', function (value) {
		// When the value changes.
		value.bind(function (newval) {
			$('.logo-word-2').text(newval);
		});
	});

	wp.customize('wpst_logo_image_size', function (value) {
		// When the value changes.
		value.bind(function (newval) {
			$('.navbar-brand-image img').css('max-height', newval + 'px');
		});
	});

	wp.customize('wpst_logo_image_margin', function (value) {
		// When the value changes.
		value.bind(function (newval) {
			$('.navbar-brand-image img').css('top', newval + 'px');
		});
	});

	
} )( jQuery );

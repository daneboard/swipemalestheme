jQuery( document ).ready( function() {

	// Returns a function, that, as long as it continues to be invoked, will not
	// be triggered. The function will be called after it stops being called for
	// N milliseconds. If `immediate` is passed, trigger the function on the
	// leading edge, instead of the trailing.
	function debounce(func, wait, immediate) {
		var timeout;
		return function() {
			var context = this, args = arguments;
			var later = function() {
				timeout = null;
				if (!immediate) func.apply(context, args);
			};
			var callNow = immediate && !timeout;
			clearTimeout(timeout);
			timeout = setTimeout(later, wait);
			if (callNow) func.apply(context, args);
		};
	};
	
	jQuery( 'form#edit-author-settings' ).on( 'submit', function() {

		// var edit_author_settings_data = jQuery( this ).serializeArray();
		 
		// Here we add our nonce (The one we created on our functions.php. WordPress needs this code to verify if the request comes from a valid source.
		// edit_author_settings_data.push( { "name" : "security", "value" : wpst_ajax_var.nonce } );
	 
		// Here is the ajax petition.

		// var photMainColor 		= jQuery('input[name="photMainColor"]:checked').val();
		var authorDisplayName 	= jQuery('#authorDisplayName').val();
		var authorDescription 	= jQuery('#authorDescription').val();
		var authorFacebookUrl 	= jQuery('#authorFacebookUrl').val();
		var authorTwitterUrl 	= jQuery('#authorTwitterUrl').val();
		var authorInstagramUrl 	= jQuery('#authorInstagramUrl').val();

		jQuery.ajax({
			url : wpst_ajax_var.url, // Here goes our WordPress AJAX endpoint.
			type: "POST",
			data: {
				action: 'wpst_send_settings_options',
				nonce: wpst_ajax_var.nonce,
				// photMainColor: photMainColor,
				authorDisplayName: authorDisplayName,
				authorDescription: authorDescription,
				authorFacebookUrl: authorFacebookUrl,
				authorTwitterUrl: authorTwitterUrl,
				authorInstagramUrl: authorInstagramUrl,
			},
			dataType: "json",
			success : function( response ) {
				console.log(response);		
			},
			fail : function( err ) {
				// You can craft something here to handle an error if something goes wrong when doing the AJAX request.
				alert( "There was an error: " + err );
			}
		});		 
		// This return prevents the submit event to refresh the page.
		return false;
	});

	var timer = null;
	jQuery('#edit-author-settings input').change(function() {
		clearTimeout(timer); 
       	timer = setTimeout(wpst_save_changes, 1000);
	});

	jQuery('#edit-author-settings input').keydown(function() {
		clearTimeout(timer); 
       	timer = setTimeout(wpst_save_changes, 1000);
	});

	jQuery('#edit-author-settings textarea').keydown(function() {
		clearTimeout(timer); 
       	timer = setTimeout(wpst_save_changes, 1000);
	});

	function wpst_save_changes(){
		jQuery('#updateuser').trigger('click');
		jQuery('.saved-notice').fadeIn('fast').delay(2000).fadeOut();
	}
});
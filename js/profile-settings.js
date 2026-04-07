jQuery( document ).ready(function() {
	var maxLength = 200;
	jQuery('textarea').keyup(function() {
		jQuery('.message-limit').fadeIn();
		var length = jQuery(this).val().length;
		var length = maxLength-length;
		jQuery('.message-limit span.remaining').css('opacity', 1);
		jQuery('#chars').text(length);
	});
});
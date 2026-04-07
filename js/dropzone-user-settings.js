/**
 * PROFILE AVATAR
 */
Dropzone.options.myDropzoneAvatar = {
	url: wpst_ajax_var.upload_avatar,
	// params: {
	//     'hm-nonce': jQuery('#hm-nonce').val()
	// },
	maxFiles: 1,
	maxFilesize: 15,
	thumbnailWidth: 130,
	thumbnailHeight: 130,
	// addRemoveLinks: true,
	acceptedFiles: '.jpg, .jpeg, .png',
	transformFile: function (file, done) {
		// Create Dropzone reference for use in confirm button click handler
		var myDropZoneAvatar = this;
		// Create the image editor overlay
		var editor = document.createElement('div');
		editor.style.position = 'fixed';
		editor.style.left = 0;
		editor.style.right = 0;
		editor.style.top = 0;
		editor.style.bottom = 0;
		editor.style.zIndex = 9999;
		editor.style.backgroundColor = '#000';
		document.body.appendChild(editor);
		// Create confirm button at the top left of the viewport
		var buttonConfirm = document.createElement('button');
		buttonConfirm.style.position = 'absolute';
		buttonConfirm.style.left = '10px';
		buttonConfirm.style.top = '10px';
		buttonConfirm.style.zIndex = 9999;
		buttonConfirm.className = 'button button-color';
		buttonConfirm.textContent = 'Continue';
		editor.appendChild(buttonConfirm);
		buttonConfirm.addEventListener('click', function () {
			// Get the canvas with image data from Cropper.js
			var canvas = cropper.getCroppedCanvas({
				width: 200,
				height: 200,
				// imageSmoothingEnabled: true,
				// imageSmoothingQuality: 'high',
			});
			// Turn the canvas into a Blob (file object without a name)
			canvas.toBlob(function (blob) {
				// Create a new Dropzone file thumbnail
				myDropZoneAvatar.createThumbnail(
					blob,
					myDropZoneAvatar.options.thumbnailWidth,
					myDropZoneAvatar.options.thumbnailHeight,
					myDropZoneAvatar.options.thumbnailMethod,
					false,
					function (dataURL) {
						// Update the Dropzone file thumbnail
						myDropZoneAvatar.emit('thumbnail', file, dataURL);
						// Update footer menu thumb
						jQuery('#menu-profil img').attr('src', dataURL);
						jQuery('#menu-profil svg').html('<img class="rounded-circle" src="' + dataURL + '" width="32" height="32"></img>');
						// Return the file to Dropzone
						done(blob);
					});
			});
			// Remove the editor from the view
			document.body.removeChild(editor);
		});
		// Create an image node for Cropper.js
		var image = new Image();
		image.src = URL.createObjectURL(file);
		editor.appendChild(image);

		// Create Cropper.js
		var cropper = new Cropper(image, {
			viewMode: 2,
			aspectRatio: 1,
			background: false,
			size: {
				width: 200,
				height: 200
			}
		});
	},
	// addedfile: function(file) {
	// file.previewElement = Dropzone.createElement(this.options.previewTemplate);
	// Now attach this new element some where in your page
	// },
	success: function (file) {
		// jQuery('.menu-profile img').attr('src', file.dataURL);
		location.reload();
	}
}

/**
 * PROFILE POSTER
 */
Dropzone.options.myDropzonePoster = {
	url: wpst_ajax_var.upload_poster,
	// params: {
	//     'hm-nonce': jQuery('#hm-nonce').val()
	// },
	maxFiles: 1,
	maxFilesize: 15,
	// addRemoveLinks: true,
	acceptedFiles: '.jpg, .jpeg, .png',
	transformFile: function (file, done) {
		// Create Dropzone reference for use in confirm button click handler
		var myDropZonePoster = this;
		// Create the image editor overlay
		var editor = document.createElement('div');
		editor.style.position = 'fixed';
		editor.style.left = 0;
		editor.style.right = 0;
		editor.style.top = 0;
		editor.style.bottom = 0;
		editor.style.zIndex = 9999;
		editor.style.backgroundColor = '#000';
		document.body.appendChild(editor);
		// Create confirm button at the top left of the viewport
		var buttonConfirm = document.createElement('button');
		buttonConfirm.style.position = 'absolute';
		buttonConfirm.style.left = '10px';
		buttonConfirm.style.top = '10px';
		buttonConfirm.style.zIndex = 9999;
		buttonConfirm.className = 'button button-color';
		buttonConfirm.textContent = 'Continue';
		editor.appendChild(buttonConfirm);
		buttonConfirm.addEventListener('click', function () {
			// Get the canvas with image data from Cropper.js
			var canvas = cropper.getCroppedCanvas({
				width: 800,
				height: 200,
			});
			// Turn the canvas into a Blob (file object without a name)
			canvas.toBlob(function (blob) {
				// Create a new Dropzone file thumbnail
				myDropZonePoster.createThumbnail(
					blob,
					800,
					200,
					myDropZonePoster.options.thumbnailMethod,
					false,
					function (dataURL) {
						// Update the Dropzone file thumbnail
						myDropZonePoster.emit('thumbnail', file, dataURL);
						// Update footer menu thumb
						// jQuery('.menu-profile img').attr('src', dataURL);
						// Return the file to Dropzone
						done(blob);
					});
			});
			// Remove the editor from the view
			document.body.removeChild(editor);
		});
		// Create an image node for Cropper.js
		var image = new Image();
		image.src = URL.createObjectURL(file);
		editor.appendChild(image);

		// Create Cropper.js
		var cropper = new Cropper(image, {
			viewMode: 2,
			// aspectRatio: 3 / 1,
			aspectRatio: 800 / 200,
			background: false,
			size: {
				width: 800,
				height: 200
			}
		});
	},
	// addedfile: function(file) {
	//     // console.log(file);
	//     // file.previewElement = Dropzone.createElement(this.options.previewTemplate);
	//     // Now attach this new element some where in your page
	// },
	success: function (file) {
		// jQuery('.menu-profile img').attr('src', file.dataURL);
		location.reload();
	}
}

/* global jQuery */
/*
 * Live preview for the shop item edit screen.
 *
 * Reads the current form values and renders the exact same card markup
 * the frontend uses, inside #tss-preview-card-mount. Re-renders on any
 * change to the relevant fields, including media uploads (we watch the
 * hidden .tss-image-id inputs and the visible .tss-image-preview).
 */
(function ($) {
	'use strict';

	function escapeHtml(s) {
		return $('<div>').text(s == null ? '' : String(s)).html();
	}

	function readImagePreview(target) {
		var $img = $('.tss-image-picker[data-target="' + target + '"] .tss-image-preview img').first();
		if ($img.length) {
			return $img.attr('src') || '';
		}
		var $url = $('.tss-image-picker[data-target="' + target + '"] .tss-image-url').first();
		return $url.length ? $.trim($url.val() || '') : '';
	}

	function readFields() {
		var title    = $.trim( $('#title').val() || '' );
		if ( ! title ) {
			title = 'Product title';
		}
		var btn      = $.trim( $('#tss_button_url').val() || '' );
		var aff      = $.trim( $('#tss_affiliate_url').val() || '' );
		var url      = btn || aff || '';
		var price    = $.trim( $('#tss_price').val() || '' );
		var position = $.trim( $('#tss_position_label').val() || '' ) || '01';
		var tagLabel = $.trim( $('#tss_tag_label').val() || '' );
		var dashicon = $.trim( ($('input[name="tss_tag_dashicon"]:checked').val() || '') );

		var image    = readImagePreview('image');
		var iconImg  = readImagePreview('icon');

		var iconHtml = '';
		if (iconImg) {
			iconHtml = '<img class="tss-tag-icon-img" src="' + escapeHtml(iconImg) + '" alt="">';
		} else if (dashicon) {
			// Prefer the same inline SVG the frontend uses (server-side
			// rendered, passed via wp_localize_script) so the preview is
			// pixel-identical and doesn't rely on the dashicons font.
			var svg = (window.tssAdmin && window.tssAdmin.iconSvg && window.tssAdmin.iconSvg[dashicon]) || '';
			iconHtml = svg ? svg : '<span class="dashicons dashicons-' + escapeHtml(dashicon) + '" aria-hidden="true"></span>';
		}

		return {
			title:    title,
			url:      url,
			price:    price,
			position: position,
			tagLabel: tagLabel,
			iconHtml: iconHtml,
			image:    image,
		};
	}

	function buildCard(f) {
		var closeIcon =
			'<svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">' +
				'<path d="M18.3 5.71 12 12.01l-6.29-6.3-1.42 1.42L10.59 13.43 4.29 19.72l1.42 1.42L12 14.85l6.29 6.29 1.42-1.42-6.29-6.29 6.29-6.29z"/>' +
			'</svg>';

		var imageHtml = f.image
			? '<img src="' + escapeHtml(f.image) + '" alt="">'
			: '';
		var positionHtml = f.position
			? '<span class="tss-shop-card__position">' + escapeHtml(f.position) + '</span>'
			: '';
		var tagHtml = '';
		if (f.iconHtml || f.tagLabel) {
			tagHtml = '<span class="tss-shop-card__tag">' + f.iconHtml + (f.tagLabel ? '<span class="tss-shop-card__tag-label">' + escapeHtml(f.tagLabel) + '</span>' : '') + '</span>';
		}

		// In preview we want the buttons to look real but not navigate.
		var linkAttrs = 'href="#" onclick="return false;"';

		return (
			'<div class="tss-shop-card" aria-label="Shop product preview">' +
				'<button type="button" class="tss-shop-card__close" aria-label="Close">' + closeIcon + '</button>' +
				'<a class="tss-shop-card__link" ' + linkAttrs + '>' +
					'<div class="tss-shop-card__image">' + imageHtml + positionHtml + '</div>' +
					'<div class="tss-shop-card__body">' +
						'<div class="tss-shop-card__title">' + escapeHtml(f.title) + '</div>' +
						tagHtml +
						(f.price ? '<div class="tss-shop-card__price">' + escapeHtml(f.price) + '</div>' : '') +
					'</div>' +
				'</a>' +
				'<a class="tss-shop-card__buy" ' + linkAttrs + '>Buy</a>' +
			'</div>'
		);
	}

	function render() {
		var $mount = $('#tss-preview-card-mount');
		if (!$mount.length) { return; }
		$mount.html(buildCard(readFields()));
	}

	$(function () {
		if (!$('#tss-preview-stage').length) { return; }

		render();

		// Re-render on any field change. Listening at document level so we
		// catch values changed by other scripts (media picker, dashicon
		// radios, etc.).
		var debounceTimer;
		function scheduleRender() {
			clearTimeout(debounceTimer);
			debounceTimer = setTimeout(render, 60);
		}

		var selectors = [
			'#title',
			'#tss_button_url',
			'#tss_affiliate_url',
			'#tss_price',
			'#tss_position_label',
			'#tss_tag_label',
			'input[name="tss_tag_dashicon"]',
			'.tss-image-picker .tss-image-url',
			'.tss-image-picker .tss-image-id',
		].join(',');

		$(document).on('input change keyup', selectors, scheduleRender);

		// Media library populates the preview <img> directly without firing
		// change events, and the dashicon click happens before the radio
		// state settles in some browsers. Observe the relevant subtrees.
		var stage = document.getElementById('tss-preview-stage');
		var observerTargets = [
			document.querySelector('.tss-image-picker[data-target="image"]'),
			document.querySelector('.tss-image-picker[data-target="icon"]'),
		].filter(Boolean);
		if (typeof MutationObserver !== 'undefined') {
			var mo = new MutationObserver(scheduleRender);
			observerTargets.forEach(function (n) {
				mo.observe(n, { childList: true, subtree: true, attributes: true, attributeFilter: ['src', 'value'] });
			});
		}
	});
})(jQuery);

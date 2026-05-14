/* global jQuery, tssData */
/*
 * TikSwipe Shop — frontend.
 *
 * Per active .swiper-slide:
 *  1. Look up the shop item for the slide's post (REST). Cached per post.
 *  2. If a shop item is returned, inject the card markup into the slide.
 *  3. Run a 250ms tick that accumulates only while the slide is active,
 *     the page is visible, and (for video slides) the <video> is playing.
 *  4. After 10s of accumulated playback, add .tss-active — CSS fades out
 *     the post info and reveals the card.
 *  5. After 5 more seconds, add .tss-can-close — the X button appears.
 *  6. Close removes both classes and resumes counting from 0.
 *  7. Swipe is never blocked: the card wrapper uses pointer-events:none and
 *     only its interactive children capture events.
 */
(function ($) {
	'use strict';

	var SHOW_MS   = (tssData && tssData.showDelayMs) || 10000;
	var CLOSE_MS  = (tssData && tssData.closeDelayMs) || 5000;
	var REST_URL  = tssData && tssData.restUrl;
	var TRACK_URL = tssData && tssData.trackUrl;

	var cache = {};

	function track(itemId, event) {
		if (!TRACK_URL || !itemId) { return; }
		var data = new FormData();
		data.append('item_id', String(itemId));
		data.append('event', event);
		try {
			if (navigator.sendBeacon) {
				navigator.sendBeacon(TRACK_URL, data);
				return;
			}
		} catch (e) {}
		try {
			fetch(TRACK_URL, { method: 'POST', body: data, keepalive: true, credentials: 'same-origin' });
		} catch (e) {}
	}

	function fetchItem(postId) {
		if (cache[postId]) {
			return $.Deferred().resolve(cache[postId]).promise();
		}
		return $.getJSON(REST_URL, { post_id: postId }).then(function (res) {
			cache[postId] = res || { item: null };
			return cache[postId];
		});
	}

	function escapeHtml(s) {
		return $('<div>').text(s == null ? '' : String(s)).html();
	}

	function buildCard(item) {
		var closeIcon =
			'<svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">' +
				'<path d="M18.3 5.71 12 12.01l-6.29-6.3-1.42 1.42L10.59 13.43 4.29 19.72l1.42 1.42L12 14.85l6.29 6.29 1.42-1.42-6.29-6.29 6.29-6.29z"/>' +
			'</svg>';

		var imageHtml = item.image_url
			? '<img src="' + escapeHtml(item.image_url) + '" alt="">'
			: '';
		var positionHtml = item.position
			? '<span class="tss-shop-card__position">' + escapeHtml(item.position) + '</span>'
			: '';

		// tag.icon_html is rendered server-side and trusted (dashicon span OR <img>).
		var tagIcon  = (item.tag && item.tag.icon_html) || '';
		var tagLabel = (item.tag && item.tag.label) ? escapeHtml(item.tag.label) : '';
		var tagHtml  = '';
		if (tagIcon || tagLabel) {
			tagHtml = '<span class="tss-shop-card__tag">' + tagIcon + (tagLabel ? '<span class="tss-shop-card__tag-label">' + tagLabel + '</span>' : '') + '</span>';
		}

		var url = item.button_url || '';
		var linkAttrs = url
			? 'href="' + escapeHtml(url) + '" target="_blank" rel="noopener nofollow sponsored"'
			: 'href="#" onclick="return false;"';

		var buy = url
			? '<a class="tss-shop-card__buy" ' + linkAttrs + '>Buy</a>'
			: '<button type="button" class="tss-shop-card__buy" disabled>Buy</button>';

		return (
			'<div class="tss-shop-card" aria-label="Shop product">' +
				'<button type="button" class="tss-shop-card__close" aria-label="Close">' + closeIcon + '</button>' +
				'<a class="tss-shop-card__link" ' + linkAttrs + '>' +
					'<div class="tss-shop-card__image">' + imageHtml + positionHtml + '</div>' +
					'<div class="tss-shop-card__body">' +
						'<div class="tss-shop-card__title">' + escapeHtml(item.title) + '</div>' +
						tagHtml +
						(item.price ? '<div class="tss-shop-card__price">' + escapeHtml(item.price) + '</div>' : '') +
					'</div>' +
				'</a>' +
				buy +
			'</div>'
		);
	}

	function injectMarkup($slide, item) {
		if ($slide.data('tssInjected')) { return; }
		$slide.data('tssInjected', true);
		$slide.data('tssItemId', item.id);
		$slide.append(buildCard(item));

		// Buy button + card link both count as a click.
		$slide.on('click', '.tss-shop-card__buy, .tss-shop-card__link', function () {
			track($slide.data('tssItemId'), 'click');
		});
	}

	function arm($slide) {
		if ($slide.data('tssArmed')) { return; }
		$slide.data('tssArmed', true);

		var TICK_MS    = 250;
		// If the slide is marked as video but no <video> element appears in
		// this many ms (e.g. iframe embed, or videojs init still pending and
		// autoplay is on so wall-clock is acceptable), fall back to counting
		// based on wall-clock time only.
		var NO_VIDEO_FALLBACK_MS = 1500;

		var state = {
			elapsed:      0,
			activated:    false,
			isVideoSlide: $slide.hasClass('swiper-video-slide'),
			intervalId:   null,
			armedAt:      Date.now(),
			lastVideoSeenAt: 0,
		};
		$slide.data('tssState', state);

		function activate() {
			if (state.activated) { return; }
			state.activated = true;
			$slide.addClass('tss-active');
			track($slide.data('tssItemId'), 'view');
			clearInterval(state.intervalId);
			state.intervalId = null;
			setTimeout(function () {
				if ($slide.hasClass('tss-active')) {
					$slide.addClass('tss-can-close');
				}
			}, CLOSE_MS);
		}

		function shouldCount() {
			if (state.activated) { return false; }
			if (!$slide.hasClass('swiper-slide-active')) { return false; }
			if (document.hidden) { return false; }

			if (!state.isVideoSlide) {
				return true;
			}

			var v = $slide.find('video').get(0);
			if (v) {
				state.lastVideoSeenAt = Date.now();
				// readyState is intentionally NOT checked — Video.js with
				// preload=none keeps it at 0 while playback is technically
				// happening (the <video>.paused flag is the reliable one).
				if (v.paused || v.ended) {
					return false;
				}
				return true;
			}

			// No <video> yet. After a short grace period, count wall-clock
			// time — this covers iframe embeds (no <video> at all) and the
			// videojs AJAX-init gap when autoplay is on so we know playback
			// will start shortly.
			return ( Date.now() - state.armedAt ) >= NO_VIDEO_FALLBACK_MS;
		}

		function startInterval() {
			if (state.intervalId) { return; }
			state.intervalId = setInterval(function () {
				if (shouldCount()) {
					state.elapsed += TICK_MS;
					if (state.elapsed >= SHOW_MS) {
						activate();
					}
				}
			}, TICK_MS);
		}

		startInterval();

		// Close button — also block swipe handlers from firing.
		$slide.on('click', '.tss-shop-card__close', function (e) {
			e.preventDefault();
			e.stopPropagation();
			track($slide.data('tssItemId'), 'close');
			$slide.removeClass('tss-active tss-can-close');
			state.activated = false;
			state.elapsed   = 0;
			state.armedAt   = Date.now();
			startInterval();
		});
	}

	function handleSlide($slide) {
		if ($slide.data('tssHandled')) { return; }
		$slide.data('tssHandled', true);

		var postId = parseInt($slide.data('id'), 10);
		if (!postId) { return; }

		fetchItem(postId).done(function (res) {
			if (!res || !res.item) { return; }
			injectMarkup($slide, res.item);
			arm($slide);
		});
	}

	function watchSlides() {
		var $wrapper = $('.swiper-wrapper');
		if (!$wrapper.length) { return; }

		$wrapper.find('.swiper-slide-active').each(function () {
			handleSlide($(this));
		});

		var observer = new MutationObserver(function (mutations) {
			mutations.forEach(function (m) {
				if (m.type === 'attributes' && m.attributeName === 'class') {
					var $s = $(m.target);
					if ($s.hasClass('swiper-slide-active')) {
						handleSlide($s);
					}
				}
				if (m.type === 'childList') {
					m.addedNodes.forEach(function (n) {
						if (n.nodeType !== 1) { return; }
						if (n.classList && n.classList.contains('swiper-slide-active')) {
							handleSlide($(n));
						}
						$(n).find && $(n).find('.swiper-slide-active').each(function () {
							handleSlide($(this));
						});
					});
				}
			});
		});

		observer.observe($wrapper[0], {
			childList: true,
			subtree: true,
			attributes: true,
			attributeFilter: ['class'],
		});
	}

	// Console helper: window.tssDebug() prints the timer state of the
	// active slide. Used to diagnose why the card might not appear.
	window.tssDebug = function () {
		var $active = $('.swiper-slide-active');
		if (!$active.length) {
			console.log('[tss] no .swiper-slide-active found');
			return;
		}
		var state = $active.data('tssState');
		var video = $active.find('video').get(0);
		var info  = {
			post_id:        $active.data('id'),
			is_video_slide: $active.hasClass('swiper-video-slide'),
			has_card:       !!$active.find('.tss-shop-card').length,
			card_active:    $active.hasClass('tss-active'),
			document_hidden: document.hidden,
			video_found:    !!video,
			video_paused:   video ? video.paused : null,
			video_ended:    video ? video.ended : null,
			video_readyState: video ? video.readyState : null,
			video_currentTime: video ? video.currentTime : null,
			state: state ? {
				elapsed_ms: state.elapsed,
				target_ms:  SHOW_MS,
				progress:   ((state.elapsed / SHOW_MS) * 100).toFixed(1) + '%',
				activated:  state.activated,
				armed_age_ms: Date.now() - state.armedAt,
				ticking:    !!state.intervalId,
			} : null,
		};
		console.table(info);
		return info;
	};

	$(function () {
		if (!REST_URL) { return; }
		watchSlides();
	});
})(jQuery);

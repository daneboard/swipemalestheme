/* global jQuery, tssData */
/*
 * TikSwipe Shop — frontend.
 *
 * For each .swiper-slide:
 *  1. On first activation, look up the shop item for the slide's post (REST).
 *  2. If a shop item exists, inject the card + badge markup into the slide.
 *  3. Start a 10-second timer. For video slides the timer accumulates only
 *     while the slide's <video> is playing (uses timeupdate). For image
 *     slides it uses wall-clock time while the slide is active.
 *  4. After 10s, add `.tss-active` to the slide (CSS hides .single-content-infos
 *     and shows the shop card + green badge).
 *  5. After 5 more seconds, add `.tss-can-close` to reveal the close button.
 *  6. Clicking close removes both classes, restoring the original info.
 *  7. Swipe is never blocked.
 */
(function ($) {
	'use strict';

	var SHOW_MS  = (tssData && tssData.showDelayMs) || 10000;
	var CLOSE_MS = (tssData && tssData.closeDelayMs) || 5000;
	var REST_URL = tssData && tssData.restUrl;

	var cache = {}; // postId -> { item: ... } or { item: null }

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
		var truck =
			'<svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">' +
				'<path d="M3 4h12v10H3zM15 8h3l3 3v3h-6zM6.5 19a2.5 2.5 0 1 1 0-5 2.5 2.5 0 0 1 0 5zM17.5 19a2.5 2.5 0 1 1 0-5 2.5 2.5 0 0 1 0 5z"/>' +
			'</svg>';
		var closeIcon =
			'<svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">' +
				'<path d="M18.3 5.71 12 12.01l-6.29-6.3-1.42 1.42L10.59 13.43 4.29 19.72l1.42 1.42L12 14.85l6.29 6.29 1.42-1.42-6.29-6.29 6.29-6.29z"/>' +
			'</svg>';

		var imgSrc = item.image_url || '';
		var image  = '';
		if (imgSrc) {
			image = '<img src="' + escapeHtml(imgSrc) + '" alt="">';
		}

		var positionLabel = escapeHtml(item.position || '');
		var positionHtml  = positionLabel ? '<span class="tss-shop-card__position">' + positionLabel + '</span>' : '';

		var buy = item.button_url
			? '<a class="tss-shop-card__buy" href="' + escapeHtml(item.button_url) + '" target="_blank" rel="noopener nofollow sponsored">Buy</a>'
			: '<button type="button" class="tss-shop-card__buy" disabled>Buy</button>';

		return (
			'<div class="tss-shop-card" aria-label="Shop product">' +
				'<button type="button" class="tss-shop-card__close" aria-label="Close">' + closeIcon + '</button>' +
				'<div class="tss-shop-card__image">' + image + positionHtml + '</div>' +
				'<div class="tss-shop-card__body">' +
					'<div class="tss-shop-card__title">' + escapeHtml(item.title) + '</div>' +
					'<span class="tss-shop-card__shipping">' + truck + escapeHtml(item.shipping) + '</span>' +
					'<div class="tss-shop-card__price">' + escapeHtml(item.price) + '</div>' +
				'</div>' +
				buy +
			'</div>'
		);
	}

	function buildBadge(badge) {
		var color = badge && badge.color ? badge.color : '#22c55e';
		var iconHtml;
		if (badge && badge.icon) {
			iconHtml = '<img src="' + escapeHtml(badge.icon) + '" alt="">';
		} else {
			// Default + icon (matches TikTok's "follow" style).
			iconHtml = '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M11 5h2v6h6v2h-6v6h-2v-6H5v-2h6V5z"/></svg>';
		}
		var style = 'background:' + escapeHtml(color) + ';';
		return (
			'<div class="tss-badge">' +
				'<span class="tss-badge__icon" style="' + style + '">' + iconHtml + '</span>' +
				'<span class="tss-badge__name">' + escapeHtml(badge && badge.name ? badge.name : '') + '</span>' +
			'</div>'
		);
	}

	function injectMarkup($slide, item) {
		if ($slide.data('tssInjected')) { return; }
		$slide.data('tssInjected', true);

		$slide.append(buildCard(item));

		var $side = $slide.find('.swiper-side');
		if ($side.length) {
			// Insert badge above the favorite/heart icon, where the creator
			// avatar currently sits.
			var $avatar = $side.find('.avatar-img');
			var $badge  = $(buildBadge(item.badge));
			if ($avatar.length) {
				$avatar.after($badge);
			} else {
				$side.prepend($badge);
			}
		}
	}

	function arm($slide) {
		if ($slide.data('tssArmed')) { return; }
		$slide.data('tssArmed', true);

		var state = {
			elapsed: 0,
			lastTick: null,
			isVideoSlide: $slide.hasClass('swiper-video-slide'),
			activated: false,
			closable: false,
			rafId: null,
		};
		$slide.data('tssState', state);

		function activate() {
			if (state.activated) { return; }
			state.activated = true;
			$slide.addClass('tss-active');
			setTimeout(function () {
				if ($slide.hasClass('tss-active')) {
					$slide.addClass('tss-can-close');
				}
			}, CLOSE_MS);
		}

		function tickWall() {
			if (state.activated || !$slide.hasClass('swiper-slide-active')) {
				state.lastTick = null;
				return;
			}
			var now = Date.now();
			if (state.lastTick) {
				state.elapsed += now - state.lastTick;
			}
			state.lastTick = now;
			if (state.elapsed >= SHOW_MS) {
				activate();
				return;
			}
			state.rafId = requestAnimationFrame(tickWall);
		}

		if (state.isVideoSlide) {
			// Listen for any <video> playback inside the slide. Each timeupdate
			// represents real playback progress, so paused video pauses the
			// countdown automatically.
			var lastTime = null;
			$slide.on('timeupdate', 'video', function () {
				if (state.activated) { return; }
				var v = this;
				if (v.paused || !$slide.hasClass('swiper-slide-active')) {
					lastTime = v.currentTime;
					return;
				}
				if (lastTime !== null && v.currentTime > lastTime) {
					state.elapsed += (v.currentTime - lastTime) * 1000;
				}
				lastTime = v.currentTime;
				if (state.elapsed >= SHOW_MS) {
					activate();
				}
			});
			// Reset reference on play (handles seeks).
			$slide.on('play seeked', 'video', function () {
				lastTime = this.currentTime;
			});
			// Fallback: if no video element is present after 1s (e.g. embed
			// iframe), fall back to wall-clock counting.
			setTimeout(function () {
				if (!state.activated && $slide.find('video').length === 0) {
					tickWall();
				}
			}, 1000);
		} else {
			tickWall();
		}

		// Close handler.
		$slide.on('click', '.tss-shop-card__close', function (e) {
			e.preventDefault();
			e.stopPropagation();
			$slide.removeClass('tss-active tss-can-close');
			state.activated = false;
			state.closable  = false;
			state.elapsed   = 0;
			state.lastTick  = null;
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
			// If slide is currently active, start the wall-clock now.
			if ($slide.hasClass('swiper-slide-active')) {
				var state = $slide.data('tssState');
				if (state && !state.isVideoSlide && !state.activated) {
					state.lastTick = Date.now();
				}
			}
		});
	}

	function watchSlides() {
		var $wrapper = $('.swiper-wrapper');
		if (!$wrapper.length) { return; }

		$wrapper.find('.swiper-slide-active').each(function () {
			handleSlide($(this));
		});

		// Observe for new active-slide changes and newly-appended slides.
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

	$(function () {
		if (!REST_URL) { return; }
		watchSlides();
	});
})(jQuery);

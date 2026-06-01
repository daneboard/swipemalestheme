/**
 * TikSwipe GA4 Analytics — standalone event tracking.
 *
 * No coupling to swiper/videojs internals. Uses:
 * - requestAnimationFrame for video progress (same pattern as progress bar)
 * - MutationObserver for swipe/slide-change detection
 * - jQuery event delegation for click events
 *
 * Fails silently if gtag() is not available.
 */
(function () {
	if (typeof gtag !== 'function') return;

	/* ==============================
	   Helpers
	   ============================== */
	var tracked = {};    // per-video milestone state
	var lastActiveId = null;

	function getSlideData(slide) {
		var $s = jQuery(slide);
		return {
			video_id: String($s.data('id') || ''),
			video_title: $s.find('.single-content-infos h1, .single-content-infos h2').first().text().trim() || ''
		};
	}

	function getActiveSlide() {
		return document.querySelector('.swiper-slide-active');
	}

	function getActivePlayer() {
		var slide = getActiveSlide();
		if (!slide) return null;
		var vjsEl = slide.querySelector('video-js');
		if (!vjsEl || !vjsEl.id) return null;
		try { return videojs.getPlayer(vjsEl.id) || null; }
		catch (e) { return null; }
	}

	// Init lastActiveId from current active slide
	var initSlide = getActiveSlide();
	if (initSlide) lastActiveId = String(jQuery(initSlide).data('id') || '');

	/* ==============================
	   1. Video progress tracking
	   (view at 3s, milestones, complete)
	   ============================== */
	function trackVideoProgress() {
		var player = getActivePlayer();
		if (player && player.duration && player.duration() > 0) {
			var slide = getActiveSlide();
			var postId = String(jQuery(slide).data('id') || '');

			if (postId && !tracked[postId]) {
				tracked[postId] = { viewed: false, p25: false, p50: false, p75: false, complete: false };
			}

			if (postId && tracked[postId]) {
				var ct = player.currentTime();
				var dur = player.duration();
				var pct = (ct / dur) * 100;
				var t = tracked[postId];
				var d = getSlideData(slide);

				if (!t.viewed && ct >= 3) {
					t.viewed = true;
					gtag('event', 'video_view', { video_id: d.video_id, video_title: d.video_title });
				}
				if (!t.p25 && pct >= 25) {
					t.p25 = true;
					gtag('event', 'video_progress', { video_id: d.video_id, video_title: d.video_title, percent: 25 });
				}
				if (!t.p50 && pct >= 50) {
					t.p50 = true;
					gtag('event', 'video_progress', { video_id: d.video_id, video_title: d.video_title, percent: 50 });
				}
				if (!t.p75 && pct >= 75) {
					t.p75 = true;
					gtag('event', 'video_progress', { video_id: d.video_id, video_title: d.video_title, percent: 75 });
				}
				if (!t.complete && pct >= 95) {
					t.complete = true;
					gtag('event', 'video_complete', { video_id: d.video_id, video_title: d.video_title });
				}
			}
		}
		requestAnimationFrame(trackVideoProgress);
	}
	requestAnimationFrame(trackVideoProgress);

	/* ==============================
	   2. Swipe / slide change
	   (MutationObserver on class attr)
	   ============================== */
	function observeSlide(slide) {
		slideObserver.observe(slide, { attributes: true, attributeFilter: ['class'] });
	}

	var slideObserver = new MutationObserver(function (mutations) {
		mutations.forEach(function (m) {
			if (m.target.classList.contains('swiper-slide-active')) {
				var newId = String(m.target.dataset.id || '');
				if (newId && newId !== lastActiveId) {
					var prevId = lastActiveId;
					lastActiveId = newId;
					gtag('event', 'video_swipe', {
						from_video_id: prevId || '',
						to_video_id: newId
					});
				}
			}
		});
	});

	// Observe existing slides
	document.querySelectorAll('.swiper-slide').forEach(observeSlide);

	// Observe new slides added by load-more
	var wrapper = document.querySelector('.swiper-wrapper');
	if (wrapper) {
		new MutationObserver(function (mutations) {
			mutations.forEach(function (m) {
				m.addedNodes.forEach(function (node) {
					if (node.nodeType === 1 && node.classList.contains('swiper-slide')) {
						observeSlide(node);
					}
				});
			});
		}).observe(wrapper, { childList: true });
	}

	/* ==============================
	   3. Click events (delegation)
	   ============================== */
	jQuery(document).on('click', '.enlight-content', function () {
		var d = getSlideData(jQuery(this).closest('.swiper-slide'));
		gtag('event', 'video_expand', { video_id: d.video_id, video_title: d.video_title });
	});

	jQuery(document).on('click', '.copy-link', function () {
		var d = getSlideData(jQuery(this).closest('.swiper-slide'));
		gtag('event', 'video_share', { video_id: d.video_id, video_title: d.video_title });
	});

	jQuery(document).on('click', '.add-to-fav', function () {
		var btn = jQuery(this);
		var action = btn.hasClass('fav-added') ? 'remove' : 'add';
		var d = getSlideData(btn.closest('.swiper-slide'));
		gtag('event', 'video_favorite', { video_id: d.video_id, video_title: d.video_title, fav_action: action });
	});

	jQuery(document).on('click', '.wpst-mute-toggle', function () {
		var d = getSlideData(jQuery(this).closest('.swiper-slide'));
		gtag('event', 'video_mute_toggle', { video_id: d.video_id, video_title: d.video_title });
	});

	/* ==============================
	   4. Search tracking
	   ============================== */
	jQuery(document).on('submit', '#searchform', function () {
		var q = jQuery(this).find('#s').val();
		if (q) {
			gtag('event', 'search', { search_term: q });
		}
	});

})();

/**
 * TikSwipe VAST Ad Handler — VAST 3.0 / 4.x compliant.
 *
 * Features:
 * - VAST 3.0/4.x parsing with Wrapper chain (up to 5 deep)
 * - Wrapper tracking merge (impressions + events accumulated)
 * - ViewableImpression support (Viewable fired after 2s playback)
 * - Full VAST event tracking: impression, start, firstQuartile,
 *   midpoint, thirdQuartile, complete, skip, pause, resume,
 *   mute, unmute, creativeView, clickTracking, error
 * - Pre-roll ads every N videos
 * - Mid-roll ads at configurable % of content video
 * - Interstitial fallback on VAST no-fill
 * - CORS proxy fallback for cross-origin VAST tags
 */
(function () {
	'use strict';

	var VAST = (window.TikSwipeVAST = {});

	/* ==============================
	   Configuration (set via inline script)
	   ============================== */
	VAST.config = {
		tagUrl: '',
		proxyUrl: '',
		frequency: 3,
		skipAfter: 5,
		enabled: false,
		midrollEnabled: false,
		midrollPercent: 20,
		midrollTagUrl: '',
		interstitialEnabled: false,
		interstitialZoneId: '',
		interstitialSrc: '',
	};

	VAST.adCounter = 0;
	VAST.adPlaying = false;
	VAST.midrollShown = {}; // track per-slide to avoid repeat

	/* ==============================
	   Should show pre-roll?
	   ============================== */
	VAST.shouldShowAd = function () {
		if (!VAST.config.enabled || !VAST.config.tagUrl) return false;
		VAST.adCounter++;
		return VAST.adCounter % VAST.config.frequency === 0;
	};

	/* ==============================
	   Fetch VAST XML (direct + proxy fallback)
	   ============================== */
	VAST.fetchXML = function (url, callback, useProxy) {
		var fetchUrl = useProxy && VAST.config.proxyUrl
			? VAST.config.proxyUrl + (VAST.config.proxyUrl.indexOf('?') === -1 ? '?' : '&') + 'vast_url=' + encodeURIComponent(url)
			: url;

		var xhr = new XMLHttpRequest();
		xhr.open('GET', fetchUrl, true);
		xhr.timeout = 5000;

		xhr.onload = function () {
			if (xhr.status === 200 && xhr.responseXML) {
				callback(xhr.responseXML);
			} else {
				callback(null);
			}
		};

		xhr.onerror = function () {
			if (!useProxy && VAST.config.proxyUrl) {
				VAST.fetchXML(url, callback, true);
				return;
			}
			callback(null);
		};

		xhr.ontimeout = function () {
			if (!useProxy && VAST.config.proxyUrl) {
				VAST.fetchXML(url, callback, true);
				return;
			}
			callback(null);
		};

		xhr.send();
	};

	/* ==============================
	   Fetch + follow VAST Wrappers,
	   accumulating tracking from each level
	   ============================== */
	VAST.fetchVAST = function (url, callback, depth, accumulated) {
		depth = depth || 0;
		accumulated = accumulated || { impressions: [], viewableImpression: {}, trackingEvents: {}, errorUrls: [] };

		if (depth > 5) {
			VAST.firePixels(accumulated.errorUrls);
			callback(null);
			return;
		}

		VAST.fetchXML(url, function (xml) {
			if (!xml) {
				VAST.firePixels(accumulated.errorUrls);
				callback(null);
				return;
			}

			var ad = xml.querySelector('Ad');
			if (!ad) {
				// Empty VAST = no fill
				VAST.firePixels(accumulated.errorUrls);
				callback(null);
				return;
			}

			// Collect tracking from this level (Wrapper or InLine)
			VAST._accumulateTracking(ad, accumulated);

			// Check for Wrapper redirect
			var wrapper = ad.querySelector('Wrapper');
			if (wrapper) {
				var vastAdTagURI = wrapper.querySelector('VASTAdTagURI');
				if (vastAdTagURI) {
					var redirectUrl = vastAdTagURI.textContent.trim();
					VAST.fetchVAST(redirectUrl, callback, depth + 1, accumulated);
					return;
				}
			}

			// Parse InLine creative
			var parsed = VAST.parseAd(ad);
			if (!parsed) {
				VAST.firePixels(accumulated.errorUrls);
				callback(null);
				return;
			}

			// Merge accumulated wrapper tracking into parsed result
			VAST._mergeAccumulated(parsed, accumulated);
			callback(parsed);
		});
	};

	/* ==============================
	   Accumulate tracking from a Wrapper/InLine level
	   ============================== */
	VAST._accumulateTracking = function (adElement, acc) {
		// Impressions
		var impressions = adElement.querySelectorAll('Impression');
		for (var i = 0; i < impressions.length; i++) {
			var url = impressions[i].textContent.trim();
			if (url) acc.impressions.push(url);
		}

		// Error URLs
		var errors = adElement.querySelectorAll('Error');
		for (var e = 0; e < errors.length; e++) {
			var eUrl = errors[e].textContent.trim();
			if (eUrl) acc.errorUrls.push(eUrl);
		}

		// ViewableImpression (VAST 3.0+)
		var vi = adElement.querySelector('ViewableImpression');
		if (vi) {
			var viewable = vi.querySelectorAll('Viewable');
			var notViewable = vi.querySelectorAll('NotViewable');
			if (!acc.viewableImpression.viewable) acc.viewableImpression.viewable = [];
			if (!acc.viewableImpression.notViewable) acc.viewableImpression.notViewable = [];
			for (var v = 0; v < viewable.length; v++) {
				var vUrl = viewable[v].textContent.trim();
				if (vUrl) acc.viewableImpression.viewable.push(vUrl);
			}
			for (var n = 0; n < notViewable.length; n++) {
				var nUrl = notViewable[n].textContent.trim();
				if (nUrl) acc.viewableImpression.notViewable.push(nUrl);
			}
		}

		// Tracking events from Linear
		var linear = adElement.querySelector('Linear');
		if (linear) {
			var trackings = linear.querySelectorAll('Tracking');
			for (var k = 0; k < trackings.length; k++) {
				var event = trackings[k].getAttribute('event');
				var trackUrl = trackings[k].textContent.trim();
				if (event && trackUrl) {
					if (!acc.trackingEvents[event]) acc.trackingEvents[event] = [];
					acc.trackingEvents[event].push(trackUrl);
				}
			}

			// Click tracking
			var clickTrackings = linear.querySelectorAll('ClickTracking');
			for (var l = 0; l < clickTrackings.length; l++) {
				var ctUrl = clickTrackings[l].textContent.trim();
				if (ctUrl) {
					if (!acc.trackingEvents.clickTracking) acc.trackingEvents.clickTracking = [];
					acc.trackingEvents.clickTracking.push(ctUrl);
				}
			}
		}
	};

	/* ==============================
	   Merge accumulated wrapper tracking into final parsed ad
	   ============================== */
	VAST._mergeAccumulated = function (parsed, acc) {
		// Merge impressions (avoid duplicates)
		for (var i = 0; i < acc.impressions.length; i++) {
			if (parsed.impressions.indexOf(acc.impressions[i]) === -1) {
				parsed.impressions.push(acc.impressions[i]);
			}
		}

		// Merge error URLs
		for (var e = 0; e < acc.errorUrls.length; e++) {
			parsed.errorUrls.push(acc.errorUrls[e]);
		}

		// Merge viewableImpression
		if (acc.viewableImpression.viewable) {
			for (var v = 0; v < acc.viewableImpression.viewable.length; v++) {
				if (parsed.viewableImpression.viewable.indexOf(acc.viewableImpression.viewable[v]) === -1) {
					parsed.viewableImpression.viewable.push(acc.viewableImpression.viewable[v]);
				}
			}
		}
		if (acc.viewableImpression.notViewable) {
			for (var n = 0; n < acc.viewableImpression.notViewable.length; n++) {
				if (parsed.viewableImpression.notViewable.indexOf(acc.viewableImpression.notViewable[n]) === -1) {
					parsed.viewableImpression.notViewable.push(acc.viewableImpression.notViewable[n]);
				}
			}
		}

		// Merge tracking events
		for (var evt in acc.trackingEvents) {
			if (!parsed.trackingEvents[evt]) parsed.trackingEvents[evt] = [];
			for (var t = 0; t < acc.trackingEvents[evt].length; t++) {
				if (parsed.trackingEvents[evt].indexOf(acc.trackingEvents[evt][t]) === -1) {
					parsed.trackingEvents[evt].push(acc.trackingEvents[evt][t]);
				}
			}
		}
	};

	/* ==============================
	   Parse a VAST InLine Ad element
	   ============================== */
	VAST.parseAd = function (adElement) {
		var result = {
			mediaUrl: '',
			mediaType: '',
			clickThrough: '',
			impressions: [],
			viewableImpression: { viewable: [], notViewable: [] },
			trackingEvents: {},
			errorUrls: [],
			duration: 0,
		};

		// Impressions
		var impressions = adElement.querySelectorAll('Impression');
		for (var i = 0; i < impressions.length; i++) {
			var url = impressions[i].textContent.trim();
			if (url) result.impressions.push(url);
		}

		// Error URLs
		var errors = adElement.querySelectorAll('Error');
		for (var e = 0; e < errors.length; e++) {
			var eUrl = errors[e].textContent.trim();
			if (eUrl) result.errorUrls.push(eUrl);
		}

		// ViewableImpression (VAST 3.0+)
		var vi = adElement.querySelector('ViewableImpression');
		if (vi) {
			var viewable = vi.querySelectorAll('Viewable');
			var notViewable = vi.querySelectorAll('NotViewable');
			for (var v = 0; v < viewable.length; v++) {
				var vUrl = viewable[v].textContent.trim();
				if (vUrl) result.viewableImpression.viewable.push(vUrl);
			}
			for (var n = 0; n < notViewable.length; n++) {
				var nUrl = notViewable[n].textContent.trim();
				if (nUrl) result.viewableImpression.notViewable.push(nUrl);
			}
		}

		// Linear creative
		var linear = adElement.querySelector('Linear');
		if (!linear) return null;

		// Duration
		var durationEl = linear.querySelector('Duration');
		if (durationEl) {
			result.duration = VAST.parseDuration(durationEl.textContent.trim());
		}

		// Media files — prefer MP4
		var mediaFiles = linear.querySelectorAll('MediaFile');
		var mp4File = null;
		var anyFile = null;
		for (var j = 0; j < mediaFiles.length; j++) {
			var mf = mediaFiles[j];
			var type = (mf.getAttribute('type') || '').toLowerCase();
			var src = mf.textContent.trim();
			if (!src) continue;
			if (type.indexOf('mp4') !== -1) {
				mp4File = { src: src, type: type };
			} else if (!anyFile) {
				anyFile = { src: src, type: type };
			}
		}
		var chosen = mp4File || anyFile;
		if (chosen) {
			result.mediaUrl = chosen.src;
			result.mediaType = chosen.type;
		}

		// Click through
		var clickThrough = linear.querySelector('ClickThrough');
		if (clickThrough) {
			result.clickThrough = clickThrough.textContent.trim();
		}

		// Tracking events
		var trackings = linear.querySelectorAll('Tracking');
		for (var k = 0; k < trackings.length; k++) {
			var event = trackings[k].getAttribute('event');
			var trackUrl = trackings[k].textContent.trim();
			if (event && trackUrl) {
				if (!result.trackingEvents[event]) result.trackingEvents[event] = [];
				result.trackingEvents[event].push(trackUrl);
			}
		}

		// Click tracking
		var clickTrackings = linear.querySelectorAll('ClickTracking');
		for (var l = 0; l < clickTrackings.length; l++) {
			var ctUrl = clickTrackings[l].textContent.trim();
			if (ctUrl) {
				if (!result.trackingEvents.clickTracking) result.trackingEvents.clickTracking = [];
				result.trackingEvents.clickTracking.push(ctUrl);
			}
		}

		return result.mediaUrl ? result : null;
	};

	/* ==============================
	   Parse HH:MM:SS to seconds
	   ============================== */
	VAST.parseDuration = function (str) {
		var parts = str.split(':');
		if (parts.length === 3) {
			return parseInt(parts[0], 10) * 3600 +
				parseInt(parts[1], 10) * 60 +
				parseFloat(parts[2]);
		}
		return 0;
	};

	/* ==============================
	   Fire tracking pixel URLs
	   - Replaces VAST macros ([TIMESTAMP], [CACHEBUSTING], etc.)
	   - Keeps references to prevent garbage collection
	   ============================== */
	VAST._pixelRefs = [];

	VAST.firePixels = function (urls) {
		if (!urls || !urls.length) return;
		var now = Date.now();
		var rand = Math.floor(Math.random() * 1000000000);
		for (var i = 0; i < urls.length; i++) {
			var url = urls[i]
				.replace(/\[TIMESTAMP\]/gi, now)
				.replace(/\[CACHEBUSTING\]/gi, rand)
				.replace(/\[CACHEBUSTER\]/gi, rand)
				.replace('%5BTIMESTAMP%5D', now)
				.replace('%5BCACHEBUSTING%5D', rand);
			var img = new Image();
			img.src = url;
			VAST._pixelRefs.push(img);
		}
		// Clean up old refs periodically (keep last 200)
		if (VAST._pixelRefs.length > 200) {
			VAST._pixelRefs = VAST._pixelRefs.slice(-50);
		}
	};

	/* ==============================
	   Show interstitial (ExoClick fallback)
	   ============================== */
	VAST.showInterstitial = function () {
		if (!VAST.config.interstitialEnabled || !VAST.config.interstitialZoneId) return;

		// Load ad-provider.js if not already loaded
		if (!document.querySelector('script[src*="ad-provider.js"]')) {
			var s = document.createElement('script');
			s.async = true;
			s.type = 'application/javascript';
			s.src = VAST.config.interstitialSrc || 'https://a.pemsrv.com/ad-provider.js';
			document.head.appendChild(s);
		}

		// Create the ad insertion point
		var ins = document.createElement('ins');
		ins.className = 'eas6a97888e33';
		ins.dataset.zoneid = VAST.config.interstitialZoneId;
		ins.style.display = 'none';
		document.body.appendChild(ins);

		// Trigger the ad
		(window.AdProvider = window.AdProvider || []).push({ serve: {} });
	};

	/* ==============================
	   Show VAST ad overlay
	   ============================== */
	VAST.showAd = function (slide, isMuted, onComplete) {
		VAST._playVAST(VAST.config.tagUrl, slide, isMuted, onComplete);
	};

	/**
	 * Core VAST player — used by both pre-roll and mid-roll.
	 */
	VAST._playVAST = function (tagUrl, slide, isMuted, onComplete) {
		if (VAST.adPlaying) {
			onComplete();
			return;
		}
		VAST.adPlaying = true;

		VAST.fetchVAST(tagUrl, function (adData) {
			if (!adData || !adData.mediaUrl) {
				VAST.adPlaying = false;
				// No fill — try interstitial fallback
				VAST.showInterstitial();
				onComplete();
				return;
			}

			// --- Build overlay DOM ---
			var overlay = document.createElement('div');
			overlay.className = 'wpst-vast-overlay';

			var label = document.createElement('div');
			label.className = 'wpst-vast-label';
			label.textContent = 'Ad';

			var video = document.createElement('video');
			video.className = 'wpst-vast-video';
			video.setAttribute('playsinline', '');
			video.setAttribute('webkit-playsinline', '');
			video.muted = isMuted;
			video.src = adData.mediaUrl;

			var skipBtn = document.createElement('div');
			skipBtn.className = 'wpst-vast-skip wpst-vast-skip-locked';
			skipBtn.textContent = 'Skip Ad ';
			var countSpan = document.createElement('span');
			skipBtn.appendChild(countSpan);

			// Mute toggle button
			var muteBtn = document.createElement('div');
			muteBtn.className = 'wpst-vast-mute';
			muteBtn.innerHTML = isMuted
				? '<svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24"><path fill="#fff" d="M3 9v6h4l5 5V4L7 9H3zm13.5 3c0-1.77-1.02-3.29-2.5-4.03v8.05c1.48-.73 2.5-2.25 2.5-4.02zM14 3.23v2.06c2.89.86 5 3.54 5 6.71s-2.11 5.85-5 6.71v2.06c4.01-.91 7-4.49 7-8.77s-2.99-7.86-7-8.77z"/><line x1="2" y1="2" x2="22" y2="22" stroke="#fff" stroke-width="2"/></svg>'
				: '<svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24"><path fill="#fff" d="M3 9v6h4l5 5V4L7 9H3zm13.5 3c0-1.77-1.02-3.29-2.5-4.03v8.05c1.48-.73 2.5-2.25 2.5-4.02zM14 3.23v2.06c2.89.86 5 3.54 5 6.71s-2.11 5.85-5 6.71v2.06c4.01-.91 7-4.49 7-8.77s-2.99-7.86-7-8.77z"/></svg>';

			muteBtn.addEventListener('click', function (e) {
				e.preventDefault();
				e.stopPropagation();
				video.muted = !video.muted;
				// Update icon
				if (video.muted) {
					muteBtn.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24"><path fill="#fff" d="M3 9v6h4l5 5V4L7 9H3zm13.5 3c0-1.77-1.02-3.29-2.5-4.03v8.05c1.48-.73 2.5-2.25 2.5-4.02zM14 3.23v2.06c2.89.86 5 3.54 5 6.71s-2.11 5.85-5 6.71v2.06c4.01-.91 7-4.49 7-8.77s-2.99-7.86-7-8.77z"/><line x1="2" y1="2" x2="22" y2="22" stroke="#fff" stroke-width="2"/></svg>';
				} else {
					muteBtn.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24"><path fill="#fff" d="M3 9v6h4l5 5V4L7 9H3zm13.5 3c0-1.77-1.02-3.29-2.5-4.03v8.05c1.48-.73 2.5-2.25 2.5-4.02zM14 3.23v2.06c2.89.86 5 3.54 5 6.71s-2.11 5.85-5 6.71v2.06c4.01-.91 7-4.49 7-8.77s-2.99-7.86-7-8.77z"/></svg>';
				}
				// VAST mute/unmute tracking is handled by the volumechange listener
			});

			overlay.appendChild(label);
			overlay.appendChild(muteBtn);
			overlay.appendChild(video);
			overlay.appendChild(skipBtn);

			// Click area
			if (adData.clickThrough) {
				var clickArea = document.createElement('a');
				clickArea.className = 'wpst-vast-clickarea';
				clickArea.href = adData.clickThrough;
				clickArea.target = '_blank';
				clickArea.rel = 'noopener noreferrer';
				clickArea.addEventListener('click', function () {
					VAST.firePixels(adData.trackingEvents.clickTracking);
				});
				overlay.appendChild(clickArea);
			}

			slide.appendChild(overlay);

			// --- Fire impressions immediately ---
			VAST.firePixels(adData.impressions);

			// --- ViewableImpression: fire <Viewable> after 2s of playback (MRC standard) ---
			var viewableTimer = null;
			var viewableFired = false;

			// --- Skip countdown ---
			var skipAfter = VAST.config.skipAfter;
			var elapsed = 0;
			var skipReady = skipAfter <= 0;

			if (skipReady) {
				skipBtn.classList.remove('wpst-vast-skip-locked');
				countSpan.textContent = '';
			} else {
				countSpan.textContent = '(' + skipAfter + ')';
			}

			var skipTimer = setInterval(function () {
				elapsed++;
				var remaining = skipAfter - elapsed;
				if (remaining <= 0) {
					clearInterval(skipTimer);
					skipReady = true;
					skipBtn.classList.remove('wpst-vast-skip-locked');
					countSpan.textContent = '';
				} else {
					countSpan.textContent = '(' + remaining + ')';
				}
			}, 1000);

			// --- Skip click ---
			skipBtn.addEventListener('click', function (e) {
				e.preventDefault();
				e.stopPropagation();
				if (!skipReady) return;
				VAST.firePixels(adData.trackingEvents.skip);
				cleanup();
			});

			// --- Video events ---
			var startFired = false;
			var q1 = false;
			var q2 = false;
			var q3 = false;

			function fireStart() {
				if (startFired) return;
				startFired = true;
				VAST.firePixels(adData.trackingEvents.start);
				VAST.firePixels(adData.trackingEvents.creativeView);

				// Start 2-second viewability timer (MRC standard)
				viewableTimer = setTimeout(function () {
					if (!viewableFired) {
						viewableFired = true;
						VAST.firePixels(adData.viewableImpression.viewable);
					}
				}, 2000);
			}

			// Primary: fire start on 'playing' event
			video.addEventListener('playing', fireStart);

			// Fallback: also fire start on 'timeupdate' in case 'playing' doesn't fire
			video.addEventListener('timeupdate', function () {
				if (!startFired && video.currentTime > 0) {
					fireStart();
				}
			});

			// Quartile tracking
			video.addEventListener('timeupdate', function () {
				if (!video.duration) return;
				var pct = video.currentTime / video.duration;
				if (!q1 && pct >= 0.25) {
					q1 = true;
					VAST.firePixels(adData.trackingEvents.firstQuartile);
				}
				if (!q2 && pct >= 0.5) {
					q2 = true;
					VAST.firePixels(adData.trackingEvents.midpoint);
				}
				if (!q3 && pct >= 0.75) {
					q3 = true;
					VAST.firePixels(adData.trackingEvents.thirdQuartile);
				}
			});

			// Complete
			video.addEventListener('ended', function () {
				VAST.firePixels(adData.trackingEvents.complete);
				cleanup();
			});

			// Error
			video.addEventListener('error', function () {
				VAST.firePixels(adData.errorUrls);
				VAST.firePixels(adData.trackingEvents.error);
				cleanup();
			});

			// Pause / Resume
			var userPaused = false;
			video.addEventListener('pause', function () {
				if (!video.ended) {
					userPaused = true;
					VAST.firePixels(adData.trackingEvents.pause);
				}
			});
			video.addEventListener('play', function () {
				if (userPaused) {
					userPaused = false;
					VAST.firePixels(adData.trackingEvents.resume);
				}
			});

			// Mute / Unmute (detect changes via volumechange)
			var wasMuted = isMuted;
			video.addEventListener('volumechange', function () {
				if (video.muted && !wasMuted) {
					wasMuted = true;
					VAST.firePixels(adData.trackingEvents.mute);
				} else if (!video.muted && wasMuted) {
					wasMuted = false;
					VAST.firePixels(adData.trackingEvents.unmute);
				}
			});

			// --- Autoplay ---
			video.play().catch(function () {
				// Autoplay blocked — fire notViewable, fire error, clean up
				VAST.firePixels(adData.viewableImpression.notViewable);
				VAST.firePixels(adData.errorUrls);
				cleanup();
			});

			// --- Cleanup ---
			function cleanup() {
				clearInterval(skipTimer);
				if (viewableTimer) clearTimeout(viewableTimer);
				// If viewable wasn't fired yet, fire notViewable
				if (!viewableFired) {
					VAST.firePixels(adData.viewableImpression.notViewable);
				}
				video.pause();
				video.removeAttribute('src');
				video.load();
				if (overlay.parentNode) {
					overlay.parentNode.removeChild(overlay);
				}
				VAST.adPlaying = false;
				onComplete();
			}
		});
	};

	/* ==============================
	   Mid-roll: monitor content video progress
	   ============================== */
	VAST.initMidroll = function () {
		if (!VAST.config.midrollEnabled || !VAST.config.midrollTagUrl) return;

		var midrollPct = VAST.config.midrollPercent / 100;

		// Check content video progress via requestAnimationFrame
		function checkMidroll() {
			if (VAST.adPlaying) {
				requestAnimationFrame(checkMidroll);
				return;
			}

			var slide = document.querySelector('.swiper-slide-active');
			if (!slide) {
				requestAnimationFrame(checkMidroll);
				return;
			}

			var slideId = slide.dataset.id;
			if (!slideId || VAST.midrollShown[slideId]) {
				requestAnimationFrame(checkMidroll);
				return;
			}

			var vjsEl = slide.querySelector('video-js');
			if (!vjsEl || !vjsEl.id) {
				requestAnimationFrame(checkMidroll);
				return;
			}

			var player = null;
			try { player = videojs.getPlayer(vjsEl.id); } catch (e) { /* */ }
			if (!player || !player.duration || player.duration() <= 0) {
				requestAnimationFrame(checkMidroll);
				return;
			}

			var pct = player.currentTime() / player.duration();
			if (pct >= midrollPct) {
				VAST.midrollShown[slideId] = true;
				// Pause content video
				player.pause();

				var tagUrl = VAST.config.midrollTagUrl || VAST.config.tagUrl;
				VAST._playVAST(tagUrl, slide, player.muted(), function () {
					// Resume content video after mid-roll
					player.play();
				});
			}

			requestAnimationFrame(checkMidroll);
		}
		requestAnimationFrame(checkMidroll);
	};

	// Start mid-roll monitoring when DOM is ready
	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', function () { VAST.initMidroll(); });
	} else {
		VAST.initMidroll();
	}

})();

/**
 * TikSwipe VAST Ad Handler.
 *
 * Lightweight VAST 2.0/3.0 parser and ad player overlay.
 * Fetches VAST XML, extracts video creative, plays it as an overlay
 * on the active swiper slide with skip button and tracking.
 */
(function () {
	'use strict';

	var VAST = (window.TikSwipeVAST = {});

	// Populated via wp_localize_script.
	VAST.config = {
		tagUrl: '',
		proxyUrl: '',
		frequency: 3,
		skipAfter: 5,
		enabled: false,
	};

	VAST.adCounter = 0;
	VAST.adPlaying = false;

	/**
	 * Check if it's time to show an ad.
	 */
	VAST.shouldShowAd = function () {
		if (!VAST.config.enabled || !VAST.config.tagUrl) return false;
		VAST.adCounter++;
		return VAST.adCounter % VAST.config.frequency === 0;
	};

	/**
	 * Fetch VAST XML with CORS fallback to server proxy.
	 */
	VAST.fetchXML = function (url, callback, useProxy) {
		var fetchUrl = url;
		if (useProxy && VAST.config.proxyUrl) {
			fetchUrl = VAST.config.proxyUrl;
		}

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
			// If direct fetch failed, try proxy.
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

	/**
	 * Fetch and parse VAST, following wrappers up to 5 deep.
	 */
	VAST.fetchVAST = function (url, callback, depth) {
		depth = depth || 0;
		if (depth > 5) {
			callback(null);
			return;
		}

		VAST.fetchXML(url, function (xml) {
			if (!xml) {
				callback(null);
				return;
			}

			// Check for Wrapper redirect.
			var wrapper = xml.querySelector('Wrapper');
			if (wrapper) {
				var vastAdTagURI = wrapper.querySelector('VASTAdTagURI');
				if (vastAdTagURI) {
					var redirectUrl = vastAdTagURI.textContent.trim();
					VAST.fetchVAST(redirectUrl, callback, depth + 1);
					return;
				}
			}

			// Parse InLine ad.
			var ad = xml.querySelector('Ad');
			if (!ad) {
				callback(null);
				return;
			}

			callback(VAST.parseAd(ad));
		});
	};

	/**
	 * Parse a VAST Ad element into a usable object.
	 */
	VAST.parseAd = function (adElement) {
		var result = {
			mediaUrl: '',
			mediaType: '',
			clickThrough: '',
			impressions: [],
			trackingEvents: {},
			duration: 0,
		};

		// Impressions.
		var impressions = adElement.querySelectorAll('Impression');
		for (var i = 0; i < impressions.length; i++) {
			var url = impressions[i].textContent.trim();
			if (url) result.impressions.push(url);
		}

		// Linear creative.
		var linear = adElement.querySelector('Linear');
		if (!linear) return null;

		// Duration.
		var durationEl = linear.querySelector('Duration');
		if (durationEl) {
			result.duration = VAST.parseDuration(durationEl.textContent.trim());
		}

		// Media files — prefer MP4, then any.
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

		// Click through.
		var clickThrough = linear.querySelector('ClickThrough');
		if (clickThrough) {
			result.clickThrough = clickThrough.textContent.trim();
		}

		// Tracking events.
		var trackings = linear.querySelectorAll('Tracking');
		for (var k = 0; k < trackings.length; k++) {
			var event = trackings[k].getAttribute('event');
			var trackUrl = trackings[k].textContent.trim();
			if (event && trackUrl) {
				if (!result.trackingEvents[event]) result.trackingEvents[event] = [];
				result.trackingEvents[event].push(trackUrl);
			}
		}

		// Click tracking.
		var clickTrackings = linear.querySelectorAll('ClickTracking');
		for (var l = 0; l < clickTrackings.length; l++) {
			var ctUrl = clickTrackings[l].textContent.trim();
			if (ctUrl) {
				if (!result.trackingEvents.clickTracking)
					result.trackingEvents.clickTracking = [];
				result.trackingEvents.clickTracking.push(ctUrl);
			}
		}

		return result.mediaUrl ? result : null;
	};

	/**
	 * Parse HH:MM:SS to seconds.
	 */
	VAST.parseDuration = function (str) {
		var parts = str.split(':');
		if (parts.length === 3) {
			return (
				parseInt(parts[0], 10) * 3600 +
				parseInt(parts[1], 10) * 60 +
				parseFloat(parts[2])
			);
		}
		return 0;
	};

	/**
	 * Fire an array of tracking pixel URLs.
	 */
	VAST.firePixels = function (urls) {
		if (!urls || !urls.length) return;
		for (var i = 0; i < urls.length; i++) {
			new Image().src = urls[i];
		}
	};

	/**
	 * Show a VAST ad overlay on the given slide.
	 *
	 * @param {Element}  slide       The .swiper-slide element.
	 * @param {boolean}  isMuted     Current global mute state.
	 * @param {Function} onComplete  Called when ad finishes or fails.
	 */
	VAST.showAd = function (slide, isMuted, onComplete) {
		if (VAST.adPlaying) {
			onComplete();
			return;
		}
		VAST.adPlaying = true;

		VAST.fetchVAST(VAST.config.tagUrl, function (adData) {
			if (!adData || !adData.mediaUrl) {
				VAST.adPlaying = false;
				onComplete();
				return;
			}

			// Build overlay DOM.
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

			overlay.appendChild(label);
			overlay.appendChild(video);
			overlay.appendChild(skipBtn);

			// Click area for advertiser click-through.
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

			// Fire impressions.
			VAST.firePixels(adData.impressions);

			// Skip countdown.
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

			// Skip click.
			skipBtn.addEventListener('click', function (e) {
				e.preventDefault();
				e.stopPropagation();
				if (!skipReady) return;
				VAST.firePixels(adData.trackingEvents.skip);
				cleanup();
			});

			// Video ended.
			video.addEventListener('ended', function () {
				VAST.firePixels(adData.trackingEvents.complete);
				cleanup();
			});

			// Video error — silently close.
			video.addEventListener('error', function () {
				cleanup();
			});

			// Quartile tracking.
			var startFired = false;
			var q1 = false;
			var q2 = false;
			var q3 = false;
			video.addEventListener('timeupdate', function () {
				if (!video.duration) return;
				var pct = video.currentTime / video.duration;
				if (!startFired && video.currentTime > 0) {
					startFired = true;
					VAST.firePixels(adData.trackingEvents.start);
					VAST.firePixels(adData.trackingEvents.creativeView);
				}
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

			// Autoplay the ad video.
			video.play().catch(function () {
				// Autoplay blocked — clean up silently.
				cleanup();
			});

			function cleanup() {
				clearInterval(skipTimer);
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
})();

<?php
/**
 * Ad blocker for premium users.
 *
 * Strategy is defense-in-depth — every layer below runs only when the
 * current user has active ad-free status (TSAR_Membership::is_premium).
 *
 * 1. theme_mod_wpst_* filters in TSAR_Membership prevent the theme from
 *    enqueuing its own ad scripts (VAST, interstitial, slide-ad) in the
 *    first place.
 * 2. script_loader_src / style_loader_src filters drop any WP-enqueued
 *    asset whose URL points at a known ad CDN.
 * 3. An output buffer wraps the whole frontend response. Right before
 *    delivery we strip:
 *      - <script src="...adcdn..."></script>
 *      - <ins class="eas..."> blocks (ExoClick container markup)
 *      - inline <script> blocks containing AdProvider / popMagic /
 *        adConfig signatures or a known ad CDN domain
 *      - <iframe src="...adcdn..."></iframe>
 * 4. A tiny <script> printed at the very top of <head> stubs the ad
 *    APIs (AdProvider, popMagic, exoJsPop101) so any inline call site
 *    that survives becomes a no-op, intercepts document.createElement
 *    to block dynamically injected scripts whose src is an ad CDN, and
 *    starts a MutationObserver that removes ad nodes inserted at any
 *    point in the page lifecycle.
 *
 * @package TikSwipe_Ad_Removal
 */

defined( 'ABSPATH' ) || exit;

class TSAR_Ad_Blocker {

	/**
	 * ExoClick / Magsrv / etc. host roots we will block on every layer.
	 * Match anywhere in the URL (subdomains included).
	 */
	const AD_HOSTS_REGEX = '(?:exoclick|exosrv|exdynsrv|pemsrv|magsrv|opoxv|exacdn|adsco\.re|porngo)';

	/**
	 * Inline script signatures we treat as ad code regardless of host.
	 */
	private static $inline_signatures = array(
		'AdProvider',
		'popMagic',
		'adConfig',
		'eas6a97888e',
		'ad-provider.js',
		'idzone',
		'syndication_host',
	);

	public static function init() {
		// Frontend only. Skip admin / AJAX / REST / cron / WP-CLI / feeds.
		if ( is_admin() ) {
			return;
		}
		if ( wp_doing_ajax() || wp_doing_cron() ) {
			return;
		}
		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			return;
		}
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			return;
		}

		add_filter( 'script_loader_src', array( __CLASS__, 'block_asset_src' ), 999, 1 );
		add_filter( 'style_loader_src', array( __CLASS__, 'block_asset_src' ), 999, 1 );

		// Buffer the whole response so we can scrub the final HTML.
		// template_redirect priority 0 runs before any rendering.
		add_action( 'template_redirect', array( __CLASS__, 'maybe_start_buffer' ), 0 );

		// Print the runtime stub + sweeper as the very first thing in <head>.
		add_action( 'wp_head', array( __CLASS__, 'print_runtime_blocker' ), -PHP_INT_MAX );
	}

	private static function should_block() {
		return TSAR_Membership::is_premium();
	}

	/**
	 * Drop any enqueued script/style whose URL points at an ad CDN.
	 */
	public static function block_asset_src( $src ) {
		if ( empty( $src ) || ! self::should_block() ) {
			return $src;
		}
		if ( preg_match( '#' . self::AD_HOSTS_REGEX . '#i', (string) $src ) ) {
			return '';
		}
		return $src;
	}

	public static function maybe_start_buffer() {
		if ( ! self::should_block() ) {
			return;
		}
		ob_start( array( __CLASS__, 'filter_html' ) );
	}

	/**
	 * Strip ad markup from the rendered HTML.
	 */
	public static function filter_html( $html ) {
		if ( empty( $html ) ) {
			return $html;
		}

		$ad_hosts = self::AD_HOSTS_REGEX;

		// 1. External scripts pointing at ad CDNs.
		$html = preg_replace(
			'#<script\b[^>]*\bsrc\s*=\s*["\'][^"\']*' . $ad_hosts . '\.[a-z]{2,}[^"\']*["\'][^>]*>\s*</script>#i',
			'',
			$html
		);

		// 2. <ins class="eas..."> blocks (ExoClick zone markup).
		$html = preg_replace(
			'#<ins\b[^>]*\bclass\s*=\s*["\'][^"\']*\beas[A-Za-z0-9]+\b[^"\']*["\'][^>]*>.*?</ins>#is',
			'',
			$html
		);

		// 3. Inline <script> blocks that match ad signatures or reference ad CDNs.
		$html = preg_replace_callback(
			'#<script\b(?![^>]*\bsrc\s*=)[^>]*>(.*?)</script>#is',
			array( __CLASS__, 'maybe_drop_inline_script' ),
			$html
		);

		// 4. Iframes embedded from ad CDNs.
		$html = preg_replace(
			'#<iframe\b[^>]*\bsrc\s*=\s*["\'][^"\']*' . $ad_hosts . '\.[a-z]{2,}[^"\']*["\'][^>]*>.*?</iframe>#is',
			'',
			$html
		);

		// 5. The theme's slide-ad placeholder, in case it slipped through.
		$html = preg_replace(
			'#<div\b[^>]*\bclass\s*=\s*["\'][^"\']*\bswiper-slide-happy\b[^"\']*["\'][^>]*>.*?</div>\s*</div>\s*</div>#is',
			'',
			$html
		);

		// 6. Child theme's sticky top banner container (renders as a 50px
		// black strip even when its <ins> child is empty). The child also
		// short-circuits this for premium users, but strip here too in case
		// it slipped through (cache, plugin temporarily off, etc).
		$html = preg_replace(
			'#<div\b[^>]*\bid\s*=\s*["\']tikswipe-top-banner["\'][^>]*>.*?</div>#is',
			'',
			$html
		);

		return $html;
	}

	private static function maybe_drop_inline_script( $match ) {
		$inline = $match[1];
		if ( '' === trim( $inline ) ) {
			return $match[0];
		}
		foreach ( self::$inline_signatures as $sig ) {
			if ( false !== stripos( $inline, $sig ) ) {
				return '';
			}
		}
		if ( preg_match( '#' . self::AD_HOSTS_REGEX . '\.[a-z]{2,}#i', $inline ) ) {
			return '';
		}
		return $match[0];
	}

	/**
	 * Print a tiny inline JS at the top of <head> that:
	 *  - stubs the ad APIs so any leftover call site becomes a no-op
	 *  - intercepts document.createElement('script') to block ad CDNs
	 *  - sweeps any ad node out of the DOM at load and on mutation
	 */
	public static function print_runtime_blocker() {
		if ( ! self::should_block() ) {
			return;
		}
		?>
<script id="tsar-runtime-blocker">
(function(){
	var BAD = ["exoclick","exosrv","exdynsrv","pemsrv","magsrv","opoxv","exacdn"];
	function badSrc(s){ if(!s) return false; s=String(s); for(var i=0;i<BAD.length;i++){ if(s.indexOf(BAD[i])!==-1) return true; } return false; }

	// Stub ad APIs. Defined as no-ops so calls don't throw and don't run.
	try {
		var noop = function(){};
		var pushArr = []; pushArr.push = noop;
		Object.defineProperty(window, "AdProvider", { value: pushArr, writable: false, configurable: false });
	} catch(e) {}
	try {
		Object.defineProperty(window, "popMagic", { value: { init: function(){}, addEvent: function(){}, methods: {} }, writable: false, configurable: false });
	} catch(e) {}
	try {
		Object.defineProperty(window, "exoJsPop101", { value: { add: function(){} }, writable: false, configurable: false });
	} catch(e) {}

	// Intercept dynamic <script> creation; refuse to load ad CDN sources.
	var origCreate = document.createElement.bind(document);
	document.createElement = function(tag){
		var el = origCreate(tag);
		if (typeof tag === "string" && tag.toLowerCase() === "script") {
			try {
				var origSetAttr = el.setAttribute.bind(el);
				el.setAttribute = function(name, value){
					if (name && name.toLowerCase() === "src" && badSrc(value)) return;
					return origSetAttr(name, value);
				};
				Object.defineProperty(el, "src", {
					set: function(v){ if (!badSrc(v)) origSetAttr("src", v); },
					get: function(){ return el.getAttribute("src") || ""; }
				});
			} catch(e) {}
		}
		return el;
	};

	var SEL = 'ins[class*="eas"],' +
		'iframe[src*="exoclick"],iframe[src*="exosrv"],iframe[src*="exdynsrv"],' +
		'iframe[src*="pemsrv"],iframe[src*="magsrv"],iframe[src*="opoxv"],iframe[src*="exacdn"],' +
		'script[src*="exoclick"],script[src*="exosrv"],script[src*="exdynsrv"],' +
		'script[src*="pemsrv"],script[src*="magsrv"],script[src*="opoxv"],' +
		'.swiper-slide-happy,#tikswipe-top-banner';

	function sweep(root){
		try {
			(root || document).querySelectorAll(SEL).forEach(function(n){ n.remove(); });
		} catch(e) {}
	}

	function start(){
		sweep();
		if (window.MutationObserver) {
			new MutationObserver(function(muts){
				for (var i=0;i<muts.length;i++){
					var added = muts[i].addedNodes;
					if (added && added.length) { sweep(); break; }
				}
			}).observe(document.documentElement, { childList: true, subtree: true });
		}
	}

	if (document.documentElement) start();
	document.addEventListener("DOMContentLoaded", sweep);
	window.addEventListener("load", sweep);
})();
</script>
		<?php
	}
}

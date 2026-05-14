=== TikSwipe Shop ===
Contributors: tikswipe
Tags: shop, affiliate, tiktok, video, swiper
Requires at least: 5.6
Tested up to: 6.5
Requires PHP: 7.2
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

TikTok-Shop style affiliate blocks for TikSwipe swiper slides.

== Description ==

Create "Shop Items" — title, description, image (upload or URL), affiliate URL, Buy button URL, price and shipping label. Target them at specific posts (search by ID or title) and/or categories.

On the matching swiper slide, after 10 seconds of video playback the post info (title, description, tags) fades out and the shop card slides in over the bottom of the video. After 5 more seconds a close button appears; clicking it restores the original info. Swipe is never blocked.

You can also configure a custom green badge per shop item (name + icon) that replaces the creator avatar in the slide's action column while the shop card is visible.

== Installation ==

1. Upload the `tikswipe-shop` folder to `/wp-content/plugins/`.
2. Activate the plugin through the "Plugins" menu in WordPress.
3. Go to "TikSwipe Shop" in the admin sidebar and add a new Shop Item.

== Frequently Asked Questions ==

= How is matching performed? =

For each visible slide, the plugin makes a REST request to `/wp-json/tikswipe-shop/v1/lookup?post_id=...`. The first published Shop Item whose target post IDs include the slide's post wins; if none match, the first item targeting any of the post's categories is used.

= Will it block swiping? =

No. The card and badge live inside the slide and only their interactive children capture pointer events.

== Changelog ==

= 1.0.0 =
* Initial release.

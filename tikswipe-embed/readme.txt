=== TikSwipe Embed ===
Contributors: tikswipe
Tags: embed, oembed, video, swipe, preview
Requires at least: 5.6
Tested up to: 6.4
Requires PHP: 7.2
Stable tag: 1.0.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Static rich embed cards for video and image posts. The video does not play
inside the embed; clicking anywhere on the card returns the user to the
original post on your site to play it there.

== Description ==

* Replaces the default WordPress `/embed/` template with a static card that
  mimics your player UI (poster, play button, author, title, view/comment
  counts, branding) **without** loading a `<video>` element.
* Click anywhere on the card breaks out of the iframe and navigates the
  parent window to the original post URL via `target="_top"`.
* Customises the WordPress oEmbed JSON response so platforms that do
  oEmbed discovery (Discord, Notion, Slack, WordPress, ...) automatically
  render the card just from the post URL.
* Adds an "Embed Code" meta box on the post editor with size presets
  (vertical 9:16, square, horizontal) and copy-to-clipboard, so you can
  paste an `<iframe>` snippet on sites that don't auto-embed.
* Provides `[tikswipe_embed id="123"]` shortcode for inline use within
  this WordPress install.
* Works only on posts with `post_format=video` or `post_format=image`;
  ignores everything else.

== CDN cost notes ==

The iframe loads only the poster image plus a small CSS file. It does NOT
load any MP4 from your CDN. This is intentional: embed views on third-party
sites no longer pull video bandwidth, and the only way to actually watch
the video is to click through to your site (where ads can render).

For maximum effect, also remove the following from your theme so other sites
don't autoplay your raw MP4:

  * `og:video`, `og:video:type`, `og:video:width`, `og:video:height` meta
  * `twitter:card=player`, `twitter:player`, `twitter:player:width`,
    `twitter:player:height` meta

Switch `og:type` to `article` and `twitter:card` to `summary_large_image`
on video posts.

== Installation ==

1. Upload the `tikswipe-embed` folder to `/wp-content/plugins/`.
2. Activate "TikSwipe Embed" in the Plugins screen.
3. Open any video/image post in the editor — the "TikSwipe Embed"
   sidebar shows the iframe code with size presets.
4. Test by visiting `https://your-site.com/post-slug/embed/` directly,
   or paste the post URL into Discord/Notion to see auto-embed.

== Frequently Asked Questions ==

= Will every site render my card just from a pasted URL? =

No. Discord, Notion, Slack, WordPress and a few other platforms support
oEmbed discovery and will render the iframe automatically. Other platforms
(WhatsApp, Telegram, X/Twitter, Facebook, iMessage) only read OG meta
tags and will show a static preview that links back to your site.

= Can I make it autoplay like TikTok on Twitter/X? =

No. TikTok appears as an autoplaying player on Twitter because they're a
whitelisted player provider on Twitter's allowlist. You cannot replicate
that without being whitelisted.

= Does the iframe download the video file? =

No. Only the poster image is fetched. The iframe contains zero `<video>`
elements.

== Frame embedding & headers ==

The plugin removes `X-Frame-Options` and sets
`Content-Security-Policy: frame-ancestors *` on `/embed/` URLs so third
parties can iframe the page. If your host (Cloudflare, server config) sets
its own `X-Frame-Options: SAMEORIGIN` header at a higher level, you may
need to override it for embed URLs there as well.

== Changelog ==

= 1.0.0 =
* Initial release.

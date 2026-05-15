=== TikSwipe Ad Removal ===
Contributors: tikswipe
Tags: ads, ad-removal, paypal, membership, exoclick
Requires at least: 5.6
Tested up to: 6.6
Requires PHP: 7.2
Stable tag: 1.0.0
License: GPLv2 or later

Lets logged-in users pay (manual PayPal) to remove ExoClick / VAST ads delivered by the TikSwipe theme.

== Description ==

* Two plans (configurable): 30 Days Ad-Free and Lifetime Ad-Free.
* Manual PayPal flow — user sends payment, marks the request, admin approves.
* Auto-creates a virtual front-end page at `/subscription` (slug configurable)
  that uses the active theme's header / footer, so it inherits the dark look
  from the Tikswipe child theme.
* After submission the user sees a "thanks" screen with a 1h30 countdown
  (configurable) and the page polls in the background — when the admin
  approves or rejects, the page updates without a reload.
* Defense-in-depth ad suppression for premium users:
    1. theme_mod_wpst_* filters prevent the theme from enqueuing the HTML
       slide-ad, VAST preroll, VAST midroll and ExoClick interstitial.
    2. script_loader_src / style_loader_src filters drop any WP-enqueued
       asset whose URL points at ExoClick / Magsrv / Pemsrv / etc.
    3. A template_redirect output buffer scrubs the final HTML, removing
       <script src="...adcdn..."></script>, <ins class="eas..."> blocks,
       inline scripts matching AdProvider / popMagic / adConfig
       signatures, and iframes from ad CDNs — independent of whether the
       tags came from the theme, the customizer, or a "header HTML"
       plugin.
    4. A tiny inline JS printed first in <head> stubs AdProvider /
       popMagic / exoJsPop101 so leftover call sites no-op, intercepts
       document.createElement('script') so scripts whose src is an ad
       CDN never load, and a MutationObserver removes any ad node
       inserted at any point in the page lifecycle.
* Admin dashboard with pending/approved/rejected filters and one-click approval
  (custom days or lifetime).
* Members tab to grant or revoke ad-free manually.
* Settings tab for PayPal email, prices, slug, review window, disclaimer.
* Daily cron prunes expired memberships.

== Usage ==

1. Install and activate the plugin (rewrites flush automatically).
2. Go to **Ad Removal → Settings** and set your PayPal email, prices,
   disclaimer, and (optionally) change the page slug.
3. Send users to `/subscription/` (link from your theme menu, profile area, or
   wherever you want).
4. Logged-in users pick a plan, send PayPal payment, mark the request.
5. In **Ad Removal → Requests**, approve to grant N days (or 0 for lifetime).
   The user's open subscription page will update on its own within ~30s.

== Frequently Asked Questions ==

= Does it integrate with PayPal IPN? =
No, the flow is intentionally manual. Admin approves each payment after
verifying it in PayPal.

= Does it modify the theme? =
No. Ads are suppressed via `theme_mod_*` filters, plus a small CSS for safety.

= I changed the slug and the page now 404s =
The plugin auto-flushes rewrites when the slug changes, but if your host
caches aggressively visit Settings → Permalinks and click Save Changes once.

== Changelog ==

= 1.0.0 =
* Initial release.

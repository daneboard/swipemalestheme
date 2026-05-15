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
* Suppresses every TikSwipe ad surface for premium users without touching the
  theme: HTML ad slide, VAST preroll, VAST midroll, ExoClick interstitial.
* Defensive CSS hides leftover ExoClick iframes for premium users.
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

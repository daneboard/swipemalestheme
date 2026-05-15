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
* Suppresses every TikSwipe ad surface for premium users without touching the theme:
  - HTML ad slide (`wpst_enable_advertising_switch`)
  - VAST preroll (`wpst_vast_enabled`)
  - VAST midroll (`wpst_vast_midroll_enabled`)
  - ExoClick interstitial (`wpst_interstitial_enabled`)
* Defensive CSS hides leftover ExoClick iframes for premium users.
* Admin dashboard with pending/approved/rejected filters and one-click approval (custom days or lifetime).
* Members tab to grant or revoke ad-free manually.
* Settings tab for PayPal email, prices, currency, and disclaimer text.
* Daily cron cleans up expired memberships.

== Usage ==

1. Install and activate the plugin.
2. Go to **Ad Removal → Settings** and set your PayPal email, prices, and disclaimer.
3. Place the shortcode `[tikswipe_remove_ads]` on a page (e.g. `/remove-ads/`).
4. Logged-in users pick a plan, send PayPal payment, mark the request.
5. In **Ad Removal → Requests**, approve to grant N days (or 0 for lifetime).

== Shortcode ==

`[tikswipe_remove_ads]`

Renders the plan picker + payment-claim form for logged-in users; for premium users it shows their active status. Logged-out users see a login prompt.

== Frequently Asked Questions ==

= Does it integrate with PayPal IPN? =
No, the flow is intentionally manual. Admin approves each payment after verifying it in PayPal.

= Does it modify the theme? =
No. Ads are suppressed via `theme_mod_*` filters, plus a small CSS for safety.

== Changelog ==

= 1.0.0 =
* Initial release.

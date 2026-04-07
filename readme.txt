== TikSwipe ==

== Translations ==

== Changelog ==
= 1.6.1 = 2026-03-02
* Fixed: Fixed an issue with autoplaying videos that caused the audio to play simultaneously from multiple videos.

= 1.6.0 = 2026-01-05
* Added: Theme legacy widgets are now visible in the new WordPress Widgets page

= 1.5.1 = 2024-12-16
* Fixed: Fixed the random display option for posts which was only loading the first few posts randomly
* Fixed: Fixed videos displayed multiple times, ordering them by ID instead of date

= 1.5.0 = 2025-12-12
* Added: Add links to the documentation to help installing WP-Script Core plugin or connecting the theme after installing the theme

= 1.4.1 = 2025-12-04
* Fixed: Fix PHP Deprecated Optional parameter in comment_form_logged_in filter

= 1.4.0 = 2025-12-04
* Added: Add video scrolling with the mouse wheel on desktop
* Added: Add missing video meta tags for SEO

= 1.3.0 = 2025-07-02
* Added: Add keyboard navigation to swipe videos
* Added: Press left or up to go to swipe to the previous video
* Added: Press right or down to swipe to the next video
* Fixed: Prevent logo from shrinking in flex container when the menu is too long
* Fixed: Improve menu layout by preventing items text to be cut off
* Fixed: Iframes and videos tags are now centered in ads, like img tag
* Fixed: Fix issue that prevented to scroll down to fill the form to add content
* Fixed: Fix issue that prevented to scroll down a long menu on mobile devices
* Fixed: Fix menu color issue that prevented to display correctly the links on mobile devices on some pages
* Fixed: Fix css when you are logged in as an admin and the WordPress top bar is displayed

= 1.2.0 = 2025-06-20
* Added: Sync wpst_enable_creators from Customizer option with users_can_register from WordPress settings on theme load
* Added: Sync wpst_enable_creators from Customizer option with users_can_register from WordPress settings when saving one or the other
* Fixed: Fix users_can_register option in WordPress settings that could not be unchecked
* Fixed: Refactor swiper-media feature for better readability and fix some PHP warnings messages
* Fixed: Fix php warnings from WordPress Customizer options
* Fixed: Fix some WordPress coding style issues

= 1.1.0 = 2025-06-16
* Updated: Dropping support for PHP 5.6 to 7.1
* Updated: PHP ^7.2 or PHP 8.x is now required
* Updated: PHP ^7.4 or PHP 8.x is recommended
* Fixed: Fix scrollbar displaying issue
* Fixed: Fix PHP 8 compatibility issues
* Fixed: Replace parse_url with wp_parse_url to enhance theme compatibility across multiple php versions
* Fixed: Fix php warning when calling wp_notify_postauthor function

= 1.0.11 = 2024-07-30
* Fixed: Ads are now displayed correctly

= 1.0.10 = 2024-07-25
* Fixed: Fix overlaped ads
* Fixed: Fix css to center ads correctly
* Fixed: JavaScript ads can now be saved and rendered correctly

= 1.0.9 = 2024-07-24
* Fixed: Fix author page that could display a 404 error page
* Fixed: Fix PHP 8.x Warning Deprecated Optional parameter declared before Required parameter in comment_form_logged_in filter
* Fixed: Fix PHP Warning Undefined variable $primary_menu_id
* Fixed: Fix PHP Warning Undefined array key $author_id
* Fixed: Fix shell_exec condition in add-content template file that could cause a falsy warning message

= 1.0.8 = 2024-05-13
* Fixed: Fix display of paywall badge when WPS Paywall plugin in installed

= 1.0.7 = 2024-05-03
* Added: Add compatibility for new WPS Paywall plugin

= 1.0.6 = 2024-03-25
* Added: Option to display advertising every X slides (from 1 to 10)
* Added: Option to show posts randomly on homepage
* Updated: Latest version of VideoJS 8.10.0
* Fixed: Pages generation during theme activation
* Fixed: Minor bugs

= 1.0.5 = 2024-02-13
* Added: New option to set image quality
* Added: Default avatars for creators when no avatar is uploaded
* Fixed: Logo color issue in the creators page
* Fixed: Video displaying issue in some cases
* Fixed: Logout link displayed on all author pages
* Fixed: Creator's avatar displaying issue on slides
* Fixed: The ffmpeg info message is now displayed only for administrators
* Fixed: Issue with profile's banner image format conversion
* Fixed: Redirection issue during logging out

= 1.0.4 = 2024-01-15
* Added: Video autoplay (with option in customizer)
* Fixed: Paragraph tags code displayed in description

= 1.0.3 = 2023-12-26
* Fixed: Author's page loading issue in some cases

= 1.0.2 = 2023-12-22
* Added: Logout link next to the username on author page
* Updated: Latest version 11.0.5 of Swiper JS
* Fixed: Missing Add content icon in the footer menu in some cases
* Fixed: Login issue in some cases
* Fixed: Minor bugs

= 1.0.1 = 2023-12-20
* Added: Option to choose the status of creator's new post
* Fixed: Minor bugs

= 1.0.0 = 2023-12-20
* Added: Initial release


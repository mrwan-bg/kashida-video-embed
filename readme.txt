=== Kashida Video Embed ===
Contributors: mrwanbg
Tags: video, embed, youtube, vimeo, shortcode
Requires at least: 6.0
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.4.22
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Embed videos from supported URLs with a reusable library, Gutenberg block, shortcodes and five responsive collection layouts.

== Description ==
Kashida Video Embed helps site editors embed videos using regular share links. It includes a video library, shortcode, Gutenberg block, responsive display, aspect ratio settings, and direct video file playback.

Supported sources include YouTube, Vimeo, Facebook, X/Twitter, Dailymotion, TikTok, Twitch, Instagram and WordPress.tv when embedding is available, plus direct MP4, WebM and OGV URLs such as files in the WordPress Media Library.

Some providers restrict third-party embedding, require authentication, or change their embed APIs. Dailymotion requires a valid Player ID for its current iframe endpoint; enter the Player ID from Dailymotion Studio or paste the official embed code. Twitch requires a valid HTTPS site and parent domain. Some short links may need to be replaced with the canonical video URL.

== Features ==

* Save and manage videos in a searchable library.
* Embed by public share URL or supported provider iframe code.
* Use a shortcode or Gutenberg block.
* Create reusable collections with Grid, List, Carousel, Slider and Featured + Playlist layouts.
* Configure responsive columns, visibility, colors, spacing, borders, slider height, autoplay and playlist appearance according to the selected layout.
* Preview collection styling before saving. Actual playback and metadata remain controlled by the source provider.
* Arabic translation files are included.

== Installation ==
1. Upload the plugin ZIP through Plugins > Add New > Upload Plugin.
2. Activate Kashida Video Embed.
3. Open Kashida Video Embed > Add New and paste a video URL or provider embed code.
4. Save, then copy the shortcode from All Videos.

== Shortcode ==
[kashida_video_embed id="123"]

Or use a URL directly:
[kashida_video_embed url="https://youtu.be/VIDEO_ID" ratio="16:9"]

== Privacy ==
The plugin does not send analytics or video data to Kashida. Embedded third-party players may connect to their providers when a visitor loads the page.

== Frequently Asked Questions ==

= Why does Twitch fail on a local site? =
Twitch requires HTTPS and a matching parent domain. Local development domains or nested iframe policies can prevent playback. Test on the actual public site.

= Can every video URL be embedded? =
No. Providers may restrict embedding, require login, or disable external playback. Use the official embed code where available.

= Does the plugin track visitors? =
The plugin does not send analytics or video data to Kashida. Third-party players connect to their providers when loaded.

== Changelog ==

= 1.4.3 =
* New collection designer in the admin: layout tabs, live preview that uses the real frontend styles, and a per-layout Display settings panel (steppers, switches, sliders, color + HEX fields, Reset settings).
* New saved settings: border radius, list thumbnail width, autoplay interval, slider image fit, playlist height and scrolling, active item color, show metadata.
* Frontend refresh of all layouts; existing collections and shortcodes keep working unchanged.

= 1.4.1 =
* Refined Grid, List, Carousel and Slider presentation to better match the supplied visual references.
* Improved per-layout settings visibility in the collection editor.
* Corrected title order for Grid and Carousel, and made the title toggle apply consistently to List details.
* Fixed surface color and overlay opacity CSS variables so saved color controls render reliably.
* Improved spacing, borders, focus states, navigation and responsive list layout.



= 1.4.0 =
* Added responsive desktop, tablet and mobile column counts for Grid and Carousel.
* Added list mode with video-only or a source-details side column.
* Added configurable Slider height, consistent frame sizing, optional titles, arrows and dots.
* Preserved per-layout title visibility and carousel/slider navigation settings.


= 1.3.9 =
* Refined visual styling for all collection layouts.
* Added layout-specific settings for surface color, arrows, dots, autoplay, and list thumbnail preference.
* Added responsive carousel/slider navigation dots and optional autoplay.


= 1.3.8 =
* Fixed featured playlist height to match the active video player and scroll internally when the list is longer.
* Refined playlist contrast, active state, spacing, scrollbar, and responsive behavior.

= 1.3.7 =
* Improved featured video and playlist layout with a playlist matching the player height and internal scrolling for long lists.
* Refined playlist styling, active state, hover state, and scrollbar.

= 1.3.6 =
* Enable Update collection immediately when editing saved collection settings, including layout changes.
* Preserve the permanent shortcode while changes are pending and allow saving without regenerating the shortcode.


= 1.3.5 =
* Added Featured Video + Side Playlist layout with fixed 16:9 featured player area and selectable video list.
* Added responsive side playlist interaction to reusable video collections and gallery shortcodes.



= 1.3.0 =
* Added video collection shortcode and generator.
* Added Grid, List, Carousel and Slider layouts.
* Added per-collection columns, gap, overlay color/opacity, text color, title size and title visibility.
* Added responsive frontend styles and carousel/slider navigation.

= 1.2.1 =

= 1.3.0 =
* Expanded in-dashboard Help guide with setup, features, shortcode attributes, provider limitations, troubleshooting and privacy information.
* Added video library search, title/date sorting and one-click shortcode copy.
* Added a Help Guide shortcut from the Video Library.
* Updated plugin asset versioning.

= 1.1.2 =
* Fix Twitch preview by generating the iframe URL server-side with parent parameters; avoid relying on inline scripts inside AJAX-inserted preview HTML.


= 1.0.8 =
* Improve automatic Twitch parent-host detection.
* Use a fallback Dailymotion player ID when no custom ID is provided.

= 1.0.6 =
* Update TikTok links to the documented player/v1 endpoint.
* Preserve Instagram Reel/post URL types in generated embed URLs.
* Handle Twitch clip URLs and use a 4:3 embed frame to meet Twitch minimum player height requirements.
* Update Dailymotion embedding to the current Player ID-based endpoint and add a per-video Player ID field.
* Pass Dailymotion Player ID through preview and saved shortcode rendering.

= 1.0.5 =
* Show platform branding and platform-specific instructions when a video URL or embed source is selected.
* Include links to official provider help pages for copying share links or embed code.

= 1.0.4 =
* Rename the WordPress admin menu to Kashida Video Embed.
* Move all per-video options above the preview and arrange them in two responsive columns.
* Show a copyable shortcode on the saved video edit screen.
* Add Dailymotion, TikTok, Twitch and Instagram embed URL handling.
* Update preview to use the current source type and selected player settings.

= 1.0.1 =
* Keep the editor on the video edit screen after saving.
* Move player options into a collapsible per-video settings section and remove global settings.
* Add live preview requests and Facebook/X embed handling improvements.


= 1.0.0 =
* Initial release with URL-based embedding, responsive player, video library, Gutenberg block, and settings.

= 1.3.1 =
* Updated the collection shortcode generator to use a clear three-step workflow: select videos, customize display, then generate and copy the shortcode.
* Added Select all and Clear selection actions and a selected-video counter.
* The shortcode output stays disabled until generated, and becomes invalid when settings change.


= 1.4.8 =
* Expanded the plugin settings and layout designer to use the available WordPress admin width.
* Improved on/off switch contrast and keyboard focus visibility.

= 1.4.9 =
* Expanded the plugin admin content width across all Kashida Video Embed admin pages.
* Strengthened toggle switch track and thumb styling so enabled and disabled states are visually distinct.
* Bumped the plugin asset version to invalidate cached admin CSS.

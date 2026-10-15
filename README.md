# Kashida Video Embed

**Current plugin version:** 1.4.22

Embed videos by pasting supported public video URLs—without copying embed code. Manage videos in WordPress and create reusable collections with five layouts: Grid, List, Carousel, Slider, and Featured + Playlist.

## Features

- Supported platforms include YouTube, Vimeo, Facebook, X/Twitter, Dailymotion, TikTok, Twitch, Instagram, WordPress.tv, and WordPress Media Library files.
- Reusable video collections with persistent shortcodes.
- Responsive display controls and layout-specific styling.
- Live layout preview in the collection designer.
- Arabic translation files included.
- Gutenberg block support.
- Lazy loading and playback controls where supported by the source platform.

## Screenshots

- [Plugin cover](screenshots/cover.jpg)
- [Plugin icon](screenshots/plugin-icon.jpg)
- [Video embed showcase](screenshots/kashida-video-embed-showcase.jpg)
- [Layouts showcase](screenshots/kashida-video-layout-showcase.jpg)
- [Features showcase](screenshots/video-embed-features-showcase.jpg)
- [Responsive showcase](screenshots/fully-responsive-video-embed-showcase.jpg)
- [WordPress UI mockup](screenshots/arabic-wordpress-video-embed-ui-mockup.jpg)
- [Plugin overview](screenshots/kashida-video-embed-plugin-overview.jpg)
- [Embed videos with ease](screenshots/embed-videos-with-ease.jpg)
- [Support showcase](screenshots/kashida-plugin-support-showcase.jpg)

## Installation

1. Download the plugin ZIP from the repository's Releases page or the WordPress plugin directory.
2. In WordPress, open **Plugins → Add New Plugin → Upload Plugin**.
3. Choose the ZIP, install it, and activate Kashida Video Embed.

## Example shortcodes

Embed a saved video by ID:

```text
[kashida_video_embed id="123"]
```

Embed directly from a supported URL:

```text
[kashida_video_embed url="https://youtu.be/VIDEO_ID" ratio="16:9"]
```

A video collection shortcode is generated when you save a collection in the plugin's **Video Collections** screen. Use the generated shortcode to display the collection on a page or post.

## Development

Plugin source is kept at the repository root using the standard WordPress plugin layout. Translation sources are in `languages/`, front-end assets in `assets/`, and Gutenberg block files in `block/`.

## License

GPL-2.0-or-later. See [LICENSE](LICENSE).

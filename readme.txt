=== Self-Hosted Google Fonts ===
Contributors: asadkn
Tags: gdpr, google fonts, typography, dsgvo
Requires at least: 4.0
Tested up to: 4.9.6
Requires PHP: 5.4
Stable tag: trunk
License: GPLv2
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Automatically self-host all the Google Fonts on your site. Plug and play.

== Description ==
An easy way to self-host all your Google Fonts for increased Privacy or to meet a law requirement. 
Theme and plugin authors are often unwilling to offer a self-hosted method and it's quite laborious to download and upload each of the required font.

This plugin makes it all easy. It will scan all CSS on your site and automagically download and host on your server the necessary Google Web Fonts.

**How it works:**

* Converts all Google Font enqueues to locally hosted CSS files.
* Scans and converts any inline style tags using @imports for fonts.
* Processes all the local CSS files that weren't properly enqueued (bad authors?).
* While doing so, downloads all the required Google Fonts to your server.

**Features:**

* Automatic self-hosted fonts with no effort.
* Compatible with all themes and plugins.
* Supports IE9+ and all modern browsers.
* Optimized code benchmarked for performance.
* Built-in cache for processing.
* Compatible with cache plugins and Autoptimize.
* API and hooks for theme & plugin authors.
* Uses unicode-range for optimized fonts when using multiple subsets. Google officially does this too, but other solutions for downloading fonts don't support this.

**Dev Notes**

*Cache*: The most common reason for a failure. If you have a cache plugin, clear the caches.

It will not work with JS solutions like WebFont Loader. If you're a developer, you can still use this plugin's API to get the needed CSS and files to convert your WebFont Loader. I will post instructions on support forums if there's interest.


== Installation ==

1. Upload/Install and activate the plugin.
2. Go to *Settings* > Self-Hosted Google Fonts, Enable Processing and Save.
3. Clear all caches from any cache plugin you may have active.

== Changelog ==

= 1.0.1 =
* Added an option to toggle protocol-relative URLs for generated CSS files.
* Fixed: Checkbox options not saving. 
* Fixed: Italics not getting correct WOFF2 files.
* Added normalization to support regular/bold as 400/700.

= 1.0.0 =
* Initial release.
=== Custom Carousel Links for Elementor ===
Contributors: daljevic
Tags: elementor, carousel, image carousel, links, media library
Requires at least: 5.2
Tested up to: 6.9
Requires PHP: 7.2
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Adds per-image custom links to Elementor's Image Carousel widget by extending Media Library attachment fields.

== Description ==

Elementor free does not provide a native way to assign a different custom URL to each image inside the Image Carousel widget.

This plugin adds two fields to each image in the WordPress Media Library:

* Custom link
* Open in new tab?

When those fields are filled, the plugin rewrites the frontend output of the Elementor Image Carousel widget so each image can point to its own saved URL.

Features:

* Adds custom attachment fields in the Media Library
* Supports per-image links in Elementor Image Carousel
* Supports opening each custom link in a new tab
* Keeps Elementor's frontend rendering intact and only adjusts the final slide links

== Installation ==

1. Upload the plugin folder to the `/wp-content/plugins/` directory, or install the ZIP through the WordPress plugins screen.
2. Activate the plugin through the `Plugins` screen in WordPress.
3. Make sure Elementor is installed and activated.
4. Open the Media Library and edit an image.
5. Fill in the `Custom link` field and optionally enable `Open in new tab?`
6. Use that image in the Elementor `Image Carousel` widget.

== Frequently Asked Questions ==

= Does this work with Elementor Free? =

Yes. The plugin is intended for Elementor Free sites that need per-image custom links in the Image Carousel widget.

= Does this modify Elementor core files? =

No. The plugin uses WordPress and Elementor hooks only.

= Does this support the Image Gallery widget too? =

No. This version targets the Elementor Image Carousel widget only.

== Changelog ==

= 1.0.0 =

* Initial public release under the `Custom Carousel Links for Elementor` package
* Added WordPress 6.9 compatibility metadata
* Updated Elementor integration to avoid overriding the full widget render method
* Added standards-compliant packaging files for WordPress.org submission

# Daljevic SlideLinker for Elementor

Adds per-image custom links to Elementor's Image Carousel widget by extending WordPress Media Library attachment fields.

## Overview

Elementor Free does not provide a built-in way to assign a different custom URL to each image inside the Image Carousel widget.

This plugin adds two fields to every image in the WordPress Media Library:

- `Custom link`
- `Open in new tab?`

When those fields are filled, the plugin updates the frontend output of the Elementor Image Carousel widget so each image can use its own saved URL.

## Features

- Adds custom attachment fields in the Media Library
- Supports per-image links in Elementor Image Carousel
- Supports opening each custom link in a new tab
- Preserves Elementor's native carousel rendering and only adjusts the final slide links

## Requirements

- WordPress 5.2 or later
- Tested up to WordPress 6.9
- PHP 7.2 or later
- Elementor installed and activated

## Installation

1. Upload `daljevic-slidelinker-for-elementor.zip` through `Plugins > Add New > Upload Plugin`, or copy the plugin folder into `/wp-content/plugins/`.
2. Activate the plugin in WordPress.
3. Open the Media Library and edit an image.
4. Fill in the `Custom link` field and optionally enable `Open in new tab?`
5. Use that image in Elementor's `Image Carousel` widget.

## Package Contents

- `daljevic-slidelinker-for-elementor/daljevic-slidelinker-for-elementor.php`
- `daljevic-slidelinker-for-elementor/readme.txt`
- `daljevic-slidelinker-for-elementor/license.txt`
- `daljevic-slidelinker-for-elementor/uninstall.php`
- `daljevic-slidelinker-for-elementor/languages/`
- `daljevic-slidelinker-for-elementor.zip`

## Development Notes

- Version: `1.0.0`
- License: `GPL v2 or later`
- Author: `Aleksandar Daljevic`

## Repository

GitHub: [daljevic/Custom-links-in-Elementor-Image-Carousel](https://github.com/daljevic/Custom-links-in-Elementor-Image-Carousel)

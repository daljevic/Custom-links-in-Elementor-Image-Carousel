<?php
/**
 * Uninstall handler for Elementor Image Carousel Custom Links.
 *
 * @package ElementorImageCarouselCustomLinks
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_metadata( 'post', 0, 'elementor_carousel_custom_link', '', true );
delete_metadata( 'post', 0, 'elementor_carousel_custom_link_target', '', true );

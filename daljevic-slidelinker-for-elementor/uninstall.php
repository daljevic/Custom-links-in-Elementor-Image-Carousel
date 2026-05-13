<?php
/**
 * Uninstall handler for Daljevic SlideLinker for Elementor.
 *
 * @package DaljevicSlideLinkerForElementor
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_metadata( 'post', 0, 'dslfe_custom_link', '', true );
delete_metadata( 'post', 0, 'dslfe_custom_link_target', '', true );
delete_metadata( 'post', 0, 'elementor_carousel_custom_link', '', true );
delete_metadata( 'post', 0, 'elementor_carousel_custom_link_target', '', true );

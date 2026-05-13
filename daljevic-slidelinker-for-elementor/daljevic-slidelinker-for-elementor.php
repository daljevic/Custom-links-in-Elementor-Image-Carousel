<?php
/**
 * Plugin Name:       Daljevic SlideLinker for Elementor
 * Description:       Adds per-image custom links to the Elementor Image Carousel widget using Media Library attachment fields.
 * Version:           1.0.0
 * Requires at least: 5.2
 * Requires PHP:      7.2
 * Requires Plugins:  elementor
 * Tested up to:      6.9
 * Author:            Aleksandar Daljevic
 * Author URI:        https://github.com/daljevic
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       daljevic-slidelinker-for-elementor
 * Domain Path:       /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DSLFE_Plugin {

	const META_LINK_KEY        = 'dslfe_custom_link';
	const META_TARGET_KEY      = 'dslfe_custom_link_target';
	const LEGACY_META_LINK_KEY = 'elementor_carousel_custom_link';
	const LEGACY_META_TARGET_KEY = 'elementor_carousel_custom_link_target';
	const FIELD_LINK_KEY       = 'dslfe_custom_link';
	const FIELD_TARGET_KEY     = 'dslfe_custom_link_target';

	private static $instance;

	final public static function get_instance(): DSLFE_Plugin {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	public function init() {
		add_filter( 'attachment_fields_to_edit', array( $this, 'attachment_fields_to_edit' ), 10, 2 );
		add_filter( 'attachment_fields_to_save', array( $this, 'attachment_fields_to_save' ), 10, 2 );
		add_filter( 'elementor/widget/render_content', array( $this, 'widget_content' ), 20, 2 );
	}

	/**
	 * Adds custom link fields to media attachments.
	 *
	 * @param array   $form_fields Attachment form fields.
	 * @param WP_Post $post        Attachment post object.
	 * @return array
	 */
	public function attachment_fields_to_edit( $form_fields, $post ): array {
		$form_fields[ self::FIELD_LINK_KEY ] = array(
			'label' => __( 'Custom link', 'daljevic-slidelinker-for-elementor' ),
			'input' => 'url',
			'value' => $this->get_custom_link( $post->ID ),
			'helps' => __( 'This URL will be used for this image in the Elementor Image Carousel widget.', 'daljevic-slidelinker-for-elementor' ),
		);

		$form_fields[ self::FIELD_TARGET_KEY ] = array(
			'label' => __( 'Open in new tab?', 'daljevic-slidelinker-for-elementor' ),
			'input' => 'html',
			'html'  => sprintf(
				'<input type="checkbox" name="attachments[%1$d][%2$s]" id="attachments[%1$d][%2$s]" %3$s />',
				absint( $post->ID ),
				esc_attr( self::FIELD_TARGET_KEY ),
				checked( $this->get_link_target( $post->ID ), '_blank', false )
			),
			'value' => '_blank' === $this->get_link_target( $post->ID ) ? '1' : '0',
			'helps' => __( 'Open the custom carousel link in a new browser tab.', 'daljevic-slidelinker-for-elementor' ),
		);

		return $form_fields;
	}

	/**
	 * Saves custom attachment fields.
	 *
	 * @param array $post       Attachment post data.
	 * @param array $attachment Submitted attachment fields.
	 * @return array
	 */
	public function attachment_fields_to_save( $post, $attachment ) {
		$dslfe_custom_link = '';

		if ( isset( $attachment[ self::FIELD_LINK_KEY ] ) ) {
			$dslfe_custom_link = esc_url_raw( trim( wp_unslash( $attachment[ self::FIELD_LINK_KEY ] ) ) );
		}

		update_post_meta( $post['ID'], self::META_LINK_KEY, $dslfe_custom_link );
		update_post_meta( $post['ID'], self::META_TARGET_KEY, isset( $attachment[ self::FIELD_TARGET_KEY ] ) ? '1' : '0' );

		// Remove legacy keys once the attachment is resaved.
		delete_post_meta( $post['ID'], self::LEGACY_META_LINK_KEY );
		delete_post_meta( $post['ID'], self::LEGACY_META_TARGET_KEY );

		return $post;
	}

	/**
	 * Rewrites Elementor carousel slide anchors with per-image custom links.
	 *
	 * @param string                 $content Widget HTML output.
	 * @param \Elementor\Widget_Base $widget  Widget instance.
	 * @return string
	 */
	public function widget_content( $content, $widget ) {
		if ( 'image-carousel' !== $widget->get_name() || empty( $content ) ) {
			return $content;
		}

		$settings = $widget->get_settings_for_display();

		if ( empty( $settings['carousel'] ) || ! is_array( $settings['carousel'] ) ) {
			return $content;
		}

		$dslfe_attachments = $this->get_custom_linked_attachments( $settings['carousel'] );

		if ( empty( $dslfe_attachments ) ) {
			return $content;
		}

		return $this->replace_slide_links( $content, $dslfe_attachments );
	}

	/**
	 * Collect attachment IDs and saved custom links from the widget settings.
	 *
	 * @param array $dslfe_carousel_settings Elementor carousel control values.
	 * @return array<int, array<string, string|int>>
	 */
	private function get_custom_linked_attachments( array $dslfe_carousel_settings ): array {
		$dslfe_attachments = array();

		foreach ( $dslfe_carousel_settings as $dslfe_index => $dslfe_attachment ) {
			if ( empty( $dslfe_attachment['id'] ) ) {
				continue;
			}

			$dslfe_attachment_id = absint( $dslfe_attachment['id'] );
			$dslfe_custom_link   = $this->get_custom_link( $dslfe_attachment_id );

			if ( '' === $dslfe_custom_link ) {
				continue;
			}

			$dslfe_attachments[ $dslfe_index ] = array(
				'id'     => $dslfe_attachment_id,
				'url'    => $dslfe_custom_link,
				'target' => $this->get_link_target( $dslfe_attachment_id ),
			);
		}

		return $dslfe_attachments;
	}

	/**
	 * Replace per-slide anchors in the rendered widget HTML.
	 *
	 * @param string $dslfe_content     Widget HTML output.
	 * @param array  $dslfe_attachments Attachments with custom link settings.
	 * @return string
	 */
	private function replace_slide_links( string $dslfe_content, array $dslfe_attachments ): string {
		if ( ! class_exists( 'DOMDocument' ) || ! class_exists( 'DOMXPath' ) ) {
			return $dslfe_content;
		}

		$dslfe_dom = new DOMDocument();

		libxml_use_internal_errors( true );
		$dslfe_loaded = $dslfe_dom->loadHTML(
			'<?xml encoding="utf-8" ?><div id="dslfe-root">' . $dslfe_content . '</div>',
			LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
		);
		libxml_clear_errors();

		if ( ! $dslfe_loaded ) {
			return $dslfe_content;
		}

		$dslfe_xpath  = new DOMXPath( $dslfe_dom );
		$dslfe_slides = $dslfe_xpath->query(
			'//*[contains(concat(" ", normalize-space(@class), " "), " swiper-slide ")][not(contains(concat(" ", normalize-space(@class), " "), " swiper-slide-duplicate "))]'
		);

		if ( ! $dslfe_slides instanceof DOMNodeList || 0 === $dslfe_slides->length ) {
			return $dslfe_content;
		}

		foreach ( $dslfe_attachments as $dslfe_index => $dslfe_attachment ) {
			$dslfe_slide = $dslfe_slides->item( (int) $dslfe_index );

			if ( null === $dslfe_slide || ! $dslfe_slide instanceof DOMElement ) {
				continue;
			}

			$this->apply_link_to_slide( $dslfe_dom, $dslfe_xpath, $dslfe_slide, $dslfe_attachment );
		}

		$dslfe_root = $dslfe_dom->getElementById( 'dslfe-root' );

		if ( ! $dslfe_root instanceof DOMElement ) {
			return $dslfe_content;
		}

		return $this->get_inner_html( $dslfe_root );
	}

	/**
	 * Apply the custom URL to a single carousel slide.
	 *
	 * @param DOMDocument $dslfe_dom        DOM document.
	 * @param DOMXPath    $dslfe_xpath      DOM XPath helper.
	 * @param DOMElement  $dslfe_slide      Slide element.
	 * @param array       $dslfe_attachment Attachment data.
	 * @return void
	 */
	private function apply_link_to_slide( DOMDocument $dslfe_dom, DOMXPath $dslfe_xpath, DOMElement $dslfe_slide, array $dslfe_attachment ): void {
		$dslfe_link = $dslfe_xpath->query( './a[1]', $dslfe_slide )->item( 0 );

		if ( ! $dslfe_link instanceof DOMElement ) {
			$dslfe_link = $dslfe_dom->createElement( 'a' );
			$this->move_slide_children_into_link( $dslfe_slide, $dslfe_link );
			$dslfe_slide->appendChild( $dslfe_link );
		}

		$dslfe_link->setAttribute( 'href', esc_url( $dslfe_attachment['url'] ) );
		$dslfe_link->setAttribute( 'data-elementor-open-lightbox', 'no' );

		if ( '_blank' === $dslfe_attachment['target'] ) {
			$dslfe_link->setAttribute( 'target', '_blank' );
			$dslfe_link->setAttribute( 'rel', 'noopener noreferrer' );
		} else {
			$dslfe_link->removeAttribute( 'target' );
			$dslfe_link->removeAttribute( 'rel' );
		}
	}

	/**
	 * Wrap the existing slide content in a newly created anchor.
	 *
	 * @param DOMElement $dslfe_slide Slide element.
	 * @param DOMElement $dslfe_link  Link element.
	 * @return void
	 */
	private function move_slide_children_into_link( DOMElement $dslfe_slide, DOMElement $dslfe_link ): void {
		while ( $dslfe_slide->firstChild ) {
			$dslfe_link->appendChild( $dslfe_slide->firstChild );
		}
	}

	/**
	 * Get the custom link for an attachment ID.
	 *
	 * @param int $dslfe_attachment_id Attachment ID.
	 * @return string
	 */
	private function get_custom_link( int $dslfe_attachment_id ): string {
		$dslfe_custom_link = get_post_meta( $dslfe_attachment_id, self::META_LINK_KEY, true );

		if ( '' === $dslfe_custom_link ) {
			$dslfe_custom_link = get_post_meta( $dslfe_attachment_id, self::LEGACY_META_LINK_KEY, true );
		}

		return is_string( $dslfe_custom_link ) ? trim( $dslfe_custom_link ) : '';
	}

	/**
	 * Get the target value for the custom attachment link.
	 *
	 * @param int $dslfe_attachment_id Attachment ID.
	 * @return string
	 */
	private function get_link_target( int $dslfe_attachment_id ): string {
		$dslfe_target = get_post_meta( $dslfe_attachment_id, self::META_TARGET_KEY, true );

		if ( '' === $dslfe_target ) {
			$dslfe_target = get_post_meta( $dslfe_attachment_id, self::LEGACY_META_TARGET_KEY, true );
		}

		return '1' === $dslfe_target ? '_blank' : '';
	}

	/**
	 * Return the inner HTML for a DOM element.
	 *
	 * @param DOMElement $dslfe_element DOM element.
	 * @return string
	 */
	private function get_inner_html( DOMElement $dslfe_element ): string {
		$dslfe_html = '';

		foreach ( $dslfe_element->childNodes as $dslfe_child ) {
			$dslfe_html .= $dslfe_element->ownerDocument->saveHTML( $dslfe_child );
		}

		return $dslfe_html;
	}
}

DSLFE_Plugin::get_instance()->init();

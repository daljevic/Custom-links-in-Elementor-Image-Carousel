<?php
/**
 * Plugin Name:       Custom Carousel Links for Elementor
 * Description:       Adds per-image custom links to the Elementor Image Carousel widget using Media Library attachment fields.
 * Version:           1.0.0
 * Requires at least: 5.2
 * Requires PHP:      7.2
 * Tested up to:      6.9
 * Author:            Aleksandar Daljevic
 * Author URI:        https://github.com/daljevic
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       custom-carousel-links-for-elementor
 * Domain Path:       /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CustomLinksEICW {

	private static $instance;

	final public static function get_instance(): CustomLinksEICW {
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
		$form_fields['elementor_carousel_custom_link'] = array(
			'label' => __( 'Custom link', 'custom-carousel-links-for-elementor' ),
			'input' => 'url',
			'value' => get_post_meta( $post->ID, 'elementor_carousel_custom_link', true ),
			'helps' => __( 'This URL will be used for this image in the Elementor Image Carousel widget.', 'custom-carousel-links-for-elementor' ),
		);

		$form_fields['elementor_carousel_custom_link_target'] = array(
			'label' => __( 'Open in new tab?', 'custom-carousel-links-for-elementor' ),
			'input' => 'html',
			'html'  => sprintf(
				'<input type="checkbox" name="attachments[%1$d][elementor_carousel_custom_link_target]" id="attachments[%1$d][elementor_carousel_custom_link_target]" %2$s />',
				absint( $post->ID ),
				checked( get_post_meta( $post->ID, 'elementor_carousel_custom_link_target', true ), '1', false )
			),
			'value' => get_post_meta( $post->ID, 'elementor_carousel_custom_link_target', true ),
			'helps' => __( 'Open the custom carousel link in a new browser tab.', 'custom-carousel-links-for-elementor' ),
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
		$custom_link = '';

		if ( isset( $attachment['elementor_carousel_custom_link'] ) ) {
			$custom_link = esc_url_raw( trim( wp_unslash( $attachment['elementor_carousel_custom_link'] ) ) );
		}

		update_post_meta( $post['ID'], 'elementor_carousel_custom_link', $custom_link );
		update_post_meta( $post['ID'], 'elementor_carousel_custom_link_target', isset( $attachment['elementor_carousel_custom_link_target'] ) ? '1' : '0' );

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

		$attachments = $this->get_custom_linked_attachments( $settings['carousel'] );

		if ( empty( $attachments ) ) {
			return $content;
		}

		return $this->replace_slide_links( $content, $attachments );
	}

	/**
	 * Collect attachment IDs and saved custom links from the widget settings.
	 *
	 * @param array $carousel_settings Elementor carousel control values.
	 * @return array<int, array<string, string|int>>
	 */
	private function get_custom_linked_attachments( array $carousel_settings ): array {
		$attachments = array();

		foreach ( $carousel_settings as $index => $attachment ) {
			if ( empty( $attachment['id'] ) ) {
				continue;
			}

			$attachment_id = absint( $attachment['id'] );
			$custom_link   = $this->get_custom_link( $attachment_id );

			if ( '' === $custom_link ) {
				continue;
			}

			$attachments[ $index ] = array(
				'id'     => $attachment_id,
				'url'    => $custom_link,
				'target' => $this->get_link_target( $attachment_id ),
			);
		}

		return $attachments;
	}

	/**
	 * Replace per-slide anchors in the rendered widget HTML.
	 *
	 * @param string $content     Widget HTML output.
	 * @param array  $attachments Attachments with custom link settings.
	 * @return string
	 */
	private function replace_slide_links( string $content, array $attachments ): string {
		if ( ! class_exists( 'DOMDocument' ) || ! class_exists( 'DOMXPath' ) ) {
			return $content;
		}

		$dom = new DOMDocument();

		libxml_use_internal_errors( true );
		$loaded = $dom->loadHTML(
			'<?xml encoding="utf-8" ?><div id="custom-links-eicw-root">' . $content . '</div>',
			LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
		);
		libxml_clear_errors();

		if ( ! $loaded ) {
			return $content;
		}

		$xpath = new DOMXPath( $dom );
		$slides = $xpath->query(
			'//*[contains(concat(" ", normalize-space(@class), " "), " swiper-slide ")][not(contains(concat(" ", normalize-space(@class), " "), " swiper-slide-duplicate "))]'
		);

		if ( ! $slides instanceof DOMNodeList || 0 === $slides->length ) {
			return $content;
		}

		foreach ( $attachments as $index => $attachment ) {
			$slide = $slides->item( (int) $index );

			if ( null === $slide ) {
				continue;
			}

			if ( ! $slide instanceof DOMElement ) {
				continue;
			}

			$this->apply_link_to_slide( $dom, $xpath, $slide, $attachment );
		}

		$root = $dom->getElementById( 'custom-links-eicw-root' );

		if ( ! $root instanceof DOMElement ) {
			return $content;
		}

		return $this->get_inner_html( $root );
	}

	/**
	 * Apply the custom URL to a single carousel slide.
	 *
	 * @param DOMDocument $dom        DOM document.
	 * @param DOMXPath    $xpath      DOM XPath helper.
	 * @param DOMElement  $slide      Slide element.
	 * @param array       $attachment Attachment data.
	 * @return void
	 */
	private function apply_link_to_slide( DOMDocument $dom, DOMXPath $xpath, DOMElement $slide, array $attachment ): void {
		$link = $xpath->query( './a[1]', $slide )->item( 0 );

		if ( ! $link instanceof DOMElement ) {
			$link = $dom->createElement( 'a' );
			$this->move_slide_children_into_link( $slide, $link );
			$slide->appendChild( $link );
		}

		$link->setAttribute( 'href', esc_url( $attachment['url'] ) );
		$link->setAttribute( 'data-elementor-open-lightbox', 'no' );

		if ( '_blank' === $attachment['target'] ) {
			$link->setAttribute( 'target', '_blank' );
			$link->setAttribute( 'rel', 'noopener noreferrer' );
		} else {
			$link->removeAttribute( 'target' );
			$link->removeAttribute( 'rel' );
		}
	}

	/**
	 * Wrap the existing slide content in a newly created anchor.
	 *
	 * @param DOMElement $slide Slide element.
	 * @param DOMElement $link  Link element.
	 * @return void
	 */
	private function move_slide_children_into_link( DOMElement $slide, DOMElement $link ): void {
		while ( $slide->firstChild ) {
			$link->appendChild( $slide->firstChild );
		}
	}

	/**
	 * Get the custom link for an attachment ID.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return string
	 */
	private function get_custom_link( int $attachment_id ): string {
		$custom_link = get_post_meta( $attachment_id, 'elementor_carousel_custom_link', true );

		return is_string( $custom_link ) ? trim( $custom_link ) : '';
	}

	/**
	 * Get the target value for the custom attachment link.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return string
	 */
	private function get_link_target( int $attachment_id ): string {
		return '1' === get_post_meta( $attachment_id, 'elementor_carousel_custom_link_target', true ) ? '_blank' : '';
	}

	/**
	 * Return the inner HTML for a DOM element.
	 *
	 * @param DOMElement $element DOM element.
	 * @return string
	 */
	private function get_inner_html( DOMElement $element ): string {
		$html = '';

		foreach ( $element->childNodes as $child ) {
			$html .= $element->ownerDocument->saveHTML( $child );
		}

		return $html;
	}
}

CustomLinksEICW::get_instance()->init();

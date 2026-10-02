<?php
/**
 * Accessibility checks on a page's rendered HTML (server-side twin of src/editor/lib/a11y.ts).
 *
 * @package Uncoder\Builder
 */

namespace Uncoder\Builder\Mcp\Tools;

use DOMDocument;
use DOMElement;
use DOMXPath;

defined( 'ABSPATH' ) || exit;

/**
 * Checks what the element tree alone cannot show: names of links and buttons (icon-only ones),
 * vague link text, form fields without labels, images in any widget, frames without titles,
 * duplicate ids, positive tabindex and autoplaying sound. Text contrast is checked by Audit from
 * the settings (and by the editor's accessibility panel from the real colors).
 */
final class Html_A11y {

	private const VAGUE = '/^(click here|here|click|read more|learn more|more|more info|link|this|go|details|continue|see more)[.!…]*$/i';

	/**
	 * @return array<int, array{severity:string, rule:string, id:?string, message:string, fix:string}>
	 */
	public static function check( string $html ): array {
		if ( '' === trim( $html ) ) {
			return array();
		}
		$dom      = new DOMDocument();
		$previous = libxml_use_internal_errors( true );
		$dom->loadHTML( '<?xml encoding="utf-8"?><div id="uncoder-a11y-root">' . $html . '</div>', LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING );
		libxml_clear_errors();
		libxml_use_internal_errors( $previous );
		$xp     = new DOMXPath( $dom );
		$issues = array();
		$seen   = array();
		$add    = static function ( DOMElement $el, string $severity, string $rule, string $message, string $fix ) use ( &$issues, &$seen ): void {
			$id  = self::element_id( $el );
			$key = $id . '|' . $rule . '|' . $message;
			if ( isset( $seen[ $key ] ) ) {
				return;
			}
			$seen[ $key ] = true;
			$issues[]     = compact( 'severity', 'rule', 'id', 'message', 'fix' );
		};

		// Images in any widget (the Image widget itself is checked from its settings).
		foreach ( $xp->query( '//img' ) as $img ) {
			if ( self::hidden( $img ) || 'presentation' === $img->getAttribute( 'role' ) || self::in_class( $img, 'uncoder-image' ) ) {
				continue;
			}
			if ( ! $img->hasAttribute( 'alt' ) ) {
				$add( $img, 'error', 'image-alt', 'Image without an alt attribute.', 'Set alt text on the image (or on the attachment with set_image_alt).' );
			}
		}
		foreach ( $xp->query( '//a[@href]' ) as $a ) {
			if ( self::hidden( $a ) ) {
				continue;
			}
			$name = self::name( $a );
			if ( '' === $name ) {
				$add( $a, 'error', 'link-name', 'Link without text (only an icon or image).', 'Give it an accessible label (e.g. "label" / "Accessible label"), or alt text on the image inside.' );
			} elseif ( preg_match( self::VAGUE, $name ) ) {
				$add( $a, 'warning', 'link-text', sprintf( 'Link text "%s" does not say where it goes.', $name ), 'Use descriptive link text ("View our services").' );
			}
		}
		foreach ( $xp->query( '//button | //*[@role="button"] | //input[@type="submit" or @type="button"]' ) as $b ) {
			if ( ! self::hidden( $b ) && '' === self::name( $b ) ) {
				$add( $b, 'error', 'button-name', 'Button without a label.', 'Give the button text or an accessible label.' );
			}
		}
		foreach ( $xp->query( '//input[not(@type="hidden" or @type="submit" or @type="button" or @type="reset" or @type="image")] | //select | //textarea' ) as $field ) {
			if ( self::hidden( $field ) || '-1' === $field->getAttribute( 'tabindex' ) || self::has_label( $xp, $field ) ) {
				continue;
			}
			$add( $field, 'error', 'form-label', 'Form field without a label.', 'Show the field label (or keep it for screen readers) instead of relying on the placeholder.' );
		}
		foreach ( $xp->query( '//iframe' ) as $frame ) {
			if ( '' === trim( $frame->getAttribute( 'title' ) ) ) {
				$add( $frame, 'warning', 'frame-title', 'Embedded frame without a title.', 'Give the map / video / embed a title.' );
			}
		}
		$ids = array();
		foreach ( $xp->query( '//*[@id]' ) as $el ) {
			$id = $el->getAttribute( 'id' );
			if ( '' === $id || 'uncoder-a11y-root' === $id ) {
				continue;
			}
			if ( isset( $ids[ $id ] ) ) {
				$add( $el, 'warning', 'duplicate-id', sprintf( 'The id "%s" is used more than once.', $id ), 'Give each element a unique CSS ID.' );
			}
			$ids[ $id ] = true;
		}
		foreach ( $xp->query( '//*[@tabindex]' ) as $el ) {
			if ( (int) $el->getAttribute( 'tabindex' ) > 0 ) {
				$add( $el, 'warning', 'tabindex', 'Positive tabindex changes the keyboard order.', 'Remove the tabindex or use 0.' );
			}
		}
		foreach ( $xp->query( '//video[@autoplay]' ) as $video ) {
			if ( ! $video->hasAttribute( 'muted' ) ) {
				$add( $video, 'warning', 'autoplay-sound', 'Video plays automatically with sound.', 'Mute autoplaying videos or let visitors start them.' );
			}
		}
		return $issues;
	}

	/** Accessible name, simplified: aria-label → content (with img alt, svg title) → title. */
	public static function name( DOMElement $el ): string {
		$label = trim( $el->getAttribute( 'aria-label' ) );
		if ( '' !== $label ) {
			return $label;
		}
		$by = trim( $el->getAttribute( 'aria-labelledby' ) );
		if ( '' !== $by ) {
			$text = '';
			foreach ( preg_split( '/\s+/', $by ) as $ref ) {
				$node = $el->ownerDocument->getElementById( $ref );
				$text .= $node ? ' ' . $node->textContent : '';
			}
			if ( '' !== trim( $text ) ) {
				return trim( $text );
			}
		}
		if ( 'input' === $el->tagName ) {
			return trim( $el->getAttribute( 'value' ) );
		}
		$out  = '';
		$walk = static function ( \DOMNode $node ) use ( &$walk, &$out ): void {
			foreach ( $node->childNodes as $child ) {
				if ( XML_TEXT_NODE === $child->nodeType ) {
					$out .= $child->nodeValue;
				} elseif ( $child instanceof DOMElement ) {
					if ( 'true' === $child->getAttribute( 'aria-hidden' ) ) {
						continue;
					}
					if ( 'img' === $child->tagName || 'img' === $child->getAttribute( 'role' ) ) {
						$out .= ' ' . ( $child->hasAttribute( 'alt' ) ? $child->getAttribute( 'alt' ) : $child->getAttribute( 'aria-label' ) );
					} elseif ( 'svg' === $child->tagName ) {
						$title = $child->getElementsByTagName( 'title' )->item( 0 );
						$out  .= ' ' . ( $title ? $title->textContent : $child->getAttribute( 'aria-label' ) );
					} else {
						$walk( $child );
					}
				}
			}
		};
		$walk( $el );
		$out = trim( (string) preg_replace( '/\s+/u', ' ', $out ) );
		return '' !== $out ? $out : trim( $el->getAttribute( 'title' ) );
	}

	private static function has_label( DOMXPath $xp, DOMElement $field ): bool {
		foreach ( array( 'aria-label', 'aria-labelledby', 'title' ) as $attr ) {
			if ( '' !== trim( $field->getAttribute( $attr ) ) ) {
				return true;
			}
		}
		$id = $field->getAttribute( 'id' );
		if ( '' !== $id ) {
			foreach ( $xp->query( '//label[@for="' . str_replace( '"', '', $id ) . '"]' ) as $label ) {
				if ( '' !== trim( $label->textContent ) ) {
					return true;
				}
			}
		}
		for ( $node = $field->parentNode; $node instanceof DOMElement; $node = $node->parentNode ) {
			if ( 'label' === $node->tagName ) {
				return '' !== trim( $node->textContent );
			}
		}
		return false;
	}

	private static function hidden( DOMElement $el ): bool {
		for ( $node = $el; $node instanceof DOMElement; $node = $node->parentNode ) {
			if ( 'true' === $node->getAttribute( 'aria-hidden' ) || $node->hasAttribute( 'hidden' ) || 'template' === $node->tagName ) {
				return true;
			}
		}
		return false;
	}

	private static function in_class( DOMElement $el, string $class ): bool {
		for ( $node = $el; $node instanceof DOMElement; $node = $node->parentNode ) {
			if ( in_array( $class, preg_split( '/\s+/', $node->getAttribute( 'class' ) ), true ) ) {
				return true;
			}
		}
		return false;
	}

	/** The nearest element root: its class list is uncoder-{type} uncoder-{id} … (Renderer::identity()). */
	private static function element_id( DOMElement $el ): ?string {
		for ( $node = $el; $node instanceof DOMElement; $node = $node->parentNode ) {
			$classes = preg_split( '/\s+/', trim( $node->getAttribute( 'class' ) ) );
			if ( 0 === strpos( $classes[0] ?? '', 'uncoder-' ) && preg_match( '/^uncoder-([a-z][a-z0-9]{2,31})$/', $classes[1] ?? '', $m ) ) {
				return $m[1];
			}
			if ( $node->hasAttribute( 'data-id' ) ) {
				return $node->getAttribute( 'data-id' );
			}
		}
		return null;
	}
}

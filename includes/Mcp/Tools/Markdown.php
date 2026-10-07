<?php
/**
 * Minimal Markdown → HTML and HTML → block markup for AI-written posts.
 *
 * @package Uncoder\Builder
 */

namespace Uncoder\Builder\Mcp\Tools;

defined( 'ABSPATH' ) || exit;

/**
 * Covers what AI clients write in posts: headings, paragraphs, emphasis, links, images, lists,
 * blockquotes, code, horizontal rules. Output is passed through wp_kses_post by the caller.
 */
final class Markdown {

	public static function looks_like_markdown( string $text ): bool {
		if ( preg_match( '/<(p|h[1-6]|ul|ol|div|figure|img|blockquote|table)\b/i', $text ) ) {
			return false;
		}
		return (bool) preg_match( '/(^|\n)(#{1,6} |[-*+] |\d+\. |> |```)|\*\*[^*]+\*\*|\[[^\]]+\]\([^)]+\)/', $text );
	}

	public static function to_html( string $md ): string {
		$md    = str_replace( array( "\r\n", "\r" ), "\n", $md );
		$lines = explode( "\n", $md );
		$html  = array();
		$para  = array();
		$list  = null;
		$quote = array();
		$code  = null;

		$flush_para  = static function () use ( &$para, &$html ) {
			if ( $para ) {
				$html[] = '<p>' . self::inline( implode( ' ', $para ) ) . '</p>';
				$para   = array();
			}
		};
		$flush_list  = static function () use ( &$list, &$html ) {
			if ( $list ) {
				$html[] = '<' . $list['tag'] . '>' . implode( '', array_map( static fn( $i ) => '<li>' . self::inline( $i ) . '</li>', $list['items'] ) ) . '</' . $list['tag'] . '>';
				$list   = null;
			}
		};
		$flush_quote = static function () use ( &$quote, &$html ) {
			if ( $quote ) {
				$html[] = '<blockquote><p>' . self::inline( implode( ' ', $quote ) ) . '</p></blockquote>';
				$quote  = array();
			}
		};

		foreach ( $lines as $line ) {
			if ( null !== $code ) {
				if ( preg_match( '/^```\s*$/', $line ) ) {
					$html[] = '<pre><code>' . esc_html( implode( "\n", $code ) ) . '</code></pre>';
					$code   = null;
				} else {
					$code[] = $line;
				}
				continue;
			}
			if ( preg_match( '/^```/', $line ) ) {
				$flush_para();
				$flush_list();
				$flush_quote();
				$code = array();
				continue;
			}
			if ( '' === trim( $line ) ) {
				$flush_para();
				$flush_list();
				$flush_quote();
				continue;
			}
			if ( preg_match( '/^(#{1,6})\s+(.+?)\s*#*$/', $line, $m ) ) {
				$flush_para();
				$flush_list();
				$flush_quote();
				$level  = strlen( $m[1] );
				$html[] = '<h' . $level . '>' . self::inline( $m[2] ) . '</h' . $level . '>';
				continue;
			}
			if ( preg_match( '/^\s*([-*_])(\s*\1){2,}\s*$/', $line ) ) {
				$flush_para();
				$flush_list();
				$flush_quote();
				$html[] = '<hr />';
				continue;
			}
			if ( preg_match( '/^\s*>\s?(.*)$/', $line, $m ) ) {
				$flush_para();
				$flush_list();
				$quote[] = $m[1];
				continue;
			}
			if ( preg_match( '/^\s*([-*+]|\d+[.)])\s+(.+)$/', $line, $m ) ) {
				$flush_para();
				$flush_quote();
				$tag = ctype_digit( rtrim( $m[1], '.)' ) ) ? 'ol' : 'ul';
				if ( $list && $list['tag'] !== $tag ) {
					$flush_list();
				}
				if ( ! $list ) {
					$list = array(
						'tag'   => $tag,
						'items' => array(),
					);
				}
				$list['items'][] = $m[2];
				continue;
			}
			if ( preg_match( '/^!\[([^\]]*)\]\(([^)\s]+)\)\s*$/', trim( $line ), $m ) ) {
				$flush_para();
				$flush_list();
				$flush_quote();
				$html[] = '<figure><img src="' . esc_url( $m[2] ) . '" alt="' . esc_attr( $m[1] ) . '" /></figure>';
				continue;
			}
			if ( $list && preg_match( '/^\s{2,}\S/', $line ) ) {
				$list['items'][ count( $list['items'] ) - 1 ] .= ' ' . trim( $line );
				continue;
			}
			$flush_list();
			$flush_quote();
			$para[] = trim( $line );
		}
		if ( null !== $code ) {
			$html[] = '<pre><code>' . esc_html( implode( "\n", $code ) ) . '</code></pre>';
		}
		$flush_para();
		$flush_list();
		$flush_quote();
		return implode( "\n", $html );
	}

	private static function inline( string $text ): string {
		$codes = array();
		$text  = preg_replace_callback(
			'/`([^`]+)`/',
			static function ( $m ) use ( &$codes ) {
				$codes[] = '<code>' . esc_html( $m[1] ) . '</code>';
				return "\x1A" . ( count( $codes ) - 1 ) . "\x1A";
			},
			$text
		);
		$text = esc_html( (string) $text );
		$text = preg_replace_callback(
			'/!\[([^\]]*)\]\(([^)\s]+)\)/',
			static fn( $m ) => '<img src="' . esc_url( html_entity_decode( $m[2] ) ) . '" alt="' . esc_attr( html_entity_decode( $m[1] ) ) . '" />',
			(string) $text
		);
		$text = preg_replace_callback(
			'/\[([^\]]+)\]\(([^)\s]+)\)/',
			static fn( $m ) => '<a href="' . esc_url( html_entity_decode( $m[2] ) ) . '">' . $m[1] . '</a>',
			(string) $text
		);
		$text = preg_replace( '/\*\*(.+?)\*\*|__(.+?)__/', '<strong>$1$2</strong>', (string) $text );
		$text = preg_replace( '/(?<![*\w])\*(?!\s)(.+?)(?<!\s)\*(?!\*)|(?<![_\w])_(?!\s)(.+?)(?<!\s)_(?![_\w])/', '<em>$1$2</em>', (string) $text );
		$text = preg_replace( '/~~(.+?)~~/', '<del>$1</del>', (string) $text );
		return (string) preg_replace_callback( "/\x1A(\d+)\x1A/", static fn( $m ) => $codes[ (int) $m[1] ], (string) $text );
	}

	/**
	 * Wraps top-level HTML elements in block comments so the block editor shows real blocks.
	 */
	public static function to_blocks( string $html ): string {
		if ( false !== strpos( $html, '<!-- wp:' ) ) {
			return $html;
		}
		if ( ! class_exists( '\DOMDocument' ) ) {
			return $html;
		}
		$doc  = new \DOMDocument();
		$prev = libxml_use_internal_errors( true );
		$doc->loadHTML( '<?xml encoding="utf-8"?><div id="uncoder-root">' . $html . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD );
		libxml_clear_errors();
		libxml_use_internal_errors( $prev );
		$root = $doc->getElementById( 'uncoder-root' );
		if ( ! $root ) {
			return $html;
		}
		$out = array();
		foreach ( iterator_to_array( $root->childNodes ) as $node ) {
			if ( XML_TEXT_NODE === $node->nodeType ) {
				$text = trim( (string) $node->textContent );
				if ( '' !== $text ) {
					$out[] = "<!-- wp:paragraph -->\n<p>" . esc_html( $text ) . "</p>\n<!-- /wp:paragraph -->";
				}
				continue;
			}
			if ( XML_ELEMENT_NODE !== $node->nodeType ) {
				continue;
			}
			$tag  = strtolower( $node->nodeName );
			$markup = (string) $doc->saveHTML( $node );
			if ( 'p' === $tag ) {
				$out[] = "<!-- wp:paragraph -->\n" . $markup . "\n<!-- /wp:paragraph -->";
			} elseif ( preg_match( '/^h([1-6])$/', $tag, $m ) ) {
				$attrs  = '2' === $m[1] ? '' : ' {"level":' . $m[1] . '}';
				$markup = preg_replace( '/^<h' . $m[1] . '(?![^>]*class=)/', '<h' . $m[1] . ' class="wp-block-heading"', $markup );
				$out[]  = '<!-- wp:heading' . $attrs . " -->\n" . $markup . "\n<!-- /wp:heading -->";
			} elseif ( 'ul' === $tag || 'ol' === $tag ) {
				$items = '';
				foreach ( iterator_to_array( $node->childNodes ) as $li ) {
					if ( XML_ELEMENT_NODE === $li->nodeType && 'li' === strtolower( $li->nodeName ) ) {
						$items .= "<!-- wp:list-item -->\n" . $doc->saveHTML( $li ) . "\n<!-- /wp:list-item -->\n";
					}
				}
				$out[] = '<!-- wp:list' . ( 'ol' === $tag ? ' {"ordered":true}' : '' ) . " -->\n<" . $tag . ' class="wp-block-list">' . "\n" . $items . '</' . $tag . ">\n<!-- /wp:list -->";
			} elseif ( 'blockquote' === $tag ) {
				// Quotes hold inner paragraph blocks.
				$markup = preg_replace( '#(<p\b[^>]*>.*?</p>)#s', "<!-- wp:paragraph -->\n$1\n<!-- /wp:paragraph -->", $markup );
				$out[]  = "<!-- wp:quote -->\n" . preg_replace( '/^<blockquote(?![^>]*class=)/', '<blockquote class="wp-block-quote"', (string) $markup ) . "\n<!-- /wp:quote -->";
			} elseif ( 'hr' === $tag ) {
				$out[] = "<!-- wp:separator -->\n<hr class=\"wp-block-separator has-alpha-channel-opacity\"/>\n<!-- /wp:separator -->";
			} elseif ( 'pre' === $tag ) {
				$out[] = "<!-- wp:code -->\n" . preg_replace( '/^<pre(?![^>]*class=)/', '<pre class="wp-block-code"', $markup ) . "\n<!-- /wp:code -->";
			} else {
				$out[] = "<!-- wp:html -->\n" . $markup . "\n<!-- /wp:html -->";
			}
		}
		return implode( "\n\n", $out );
	}
}

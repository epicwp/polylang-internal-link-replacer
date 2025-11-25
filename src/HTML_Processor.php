<?php

namespace EPIC_WP\Polylang_Internal_Link_Replacer;

use voku\helper\HtmlDomParser;
use voku\helper\SimpleHtmlDomInterface;

/**
 * HTML Processor Service.
 *
 * Handles HTML DOM parsing and link manipulation.
 */
class HTML_Processor {
	/**
	 * Get the HTML DOM parser instance for the given HTML string.
	 *
	 * @param  string $html HTML string to parse.
	 * @return HtmlDomParser
	 */
	public function get_dom( string $html ): HtmlDomParser {
		return HtmlDomParser::str_get_html( $html );
	}

	/**
	 * Find all internal links in the HTML content.
	 *
	 * @param  HtmlDomParser $dom DOM parser instance.
	 * @param  string        $home_url Home URL for the site.
	 * @return array<SimpleHtmlDomInterface> Array of link elements.
	 */
	public function find_internal_links( HtmlDomParser $dom, string $home_url ): array {
		// Find all home urls and root urls.
		$selector  = $this->get_selector( $home_url );
		$dom_nodes = $dom->findMulti( $selector );

		// Only return SimpleHtmlDomInterface objects.
		$links = array();
		foreach ( $dom_nodes as $dom_node ) {
			if ( ! ( $dom_node instanceof SimpleHtmlDomInterface ) ) {
				continue;
			}

			// Skip a tags with hreflang or lang attribute a.k.a language switcher links.
			if ( $dom_node->hasAttribute( 'hreflang' ) || $dom_node->hasAttribute( 'lang' ) ) {
				continue;
			}

			$links[] = $dom_node;
		}
		return $links;
	}

	/**
	 * Update a link element with a new URL.
	 *
	 * @param  SimpleHtmlDomInterface $link Link element to update.
	 * @param  string                 $new_url New URL to set.
	 * @return void
	 */
	public function update_link_href( SimpleHtmlDomInterface $link, string $new_url ): void {
		$link->setAttribute( 'href', $new_url );
	}

	/**
	 * Get the href attribute from a link element.
	 *
	 * @param  SimpleHtmlDomInterface $link Link element.
	 * @return string|null The href value or null if not found.
	 */
	public function get_link_href( SimpleHtmlDomInterface $link ): ?string {
		return $link->getAttribute( 'href' );
	}

	/**
	 * Get the base domain from the home URL (e.g. https://www.example.com -> example.com)
	 *
	 * @param  string $home_url Home URL.
	 * @return string The base domain.
	 */
	private function get_base_domain( string $home_url ): string {
		$base_domain = \str_replace( array( 'http://', 'https://' ), '', $home_url );
		$base_domain = \ltrim( $base_domain, 'www.' );
		return $base_domain;
	}

	/**
	 * Get the selector for the links to replace for all variations of the home URL.
	 *
	 * @param  string $home_url Home URL.
	 * @return string The selector.
	 */
	private function get_selector( string $home_url ): string {
		$base_domain = $this->get_base_domain( $home_url );
		return "a[href^='https://{$base_domain}'],a[href^='https://www.{$base_domain}'],a[href^='http://{$base_domain}'],a[href^='http://www.{$base_domain}'],a[href^='/']";
	}
}

<?php
/**
 * Link Replacer Service.
 *
 * @package EPIC_WP\Polylang_Internal_Link_Replacer
 */

namespace EPIC_WP\Polylang_Internal_Link_Replacer;

/**
 * Link Replacer Service.
 *
 * This service is responsible for replacing the URLs in the content with the translated URLs based on the current language.
 */
class Link_Replacer {
    /**
     * HTML processor instance.
     *
     * @var HTML_Processor
     */
    private HTML_Processor $html_processor;

    /**
     * URL parser instance.
     *
     * @var URL_Parser
     */
    private URL_Parser $url_parser;

    /**
     * Content resolver instance.
     *
     * @var Content_Resolver
     */
    private Content_Resolver $content_resolver;

    /**
     * Link translation service instance.
     *
     * @var Link_Translation_Service
     */
    private Link_Translation_Service $translation_service;

    /**
     * Home URL for the site, used to replace absolute URLs in the content.
     *
     * @var string
     */
    private string $home_url;

    /**
     * Non allowed post ids.
     *
     * @var array<int>
     */
    private array $excluded_post_ids;

    /**
     * Constructor.
     */
    public function __construct() {
        $this->html_processor      = new HTML_Processor();
        $this->url_parser          = new URL_Parser();
        $this->content_resolver    = new Content_Resolver();
        $this->translation_service = new Link_Translation_Service();
        $this->home_url            = \get_option( 'home' );
        $this->excluded_post_ids   = array();
    }

    /**
     * Replaces the URLs in the given HTML content with the translated URLs based on the current language.
     *
     * @param  string $html HTML content containing URLs to be replaced.
     * @return string
     */
    public function replace_urls( string $html ): string {
        if ( '' === $html || ! \str_contains( $html, 'href=' ) ) {
            return $html;
        }

        $dom   = $this->html_processor->get_dom( $html );
        $links = $this->html_processor->find_internal_links( $dom, $this->home_url );

        foreach ( $links as $link ) {
            $this->replace_link_url( $link );
        }

        return $dom->html();
    }

    /**
     * Check if the url is not allowed to be translated.
     *
     * @param  string              $url URL to check.
     * @param  array<string,mixed> $query_vars Query variables from the URL.
     * @return bool True if the url is excluded, false otherwise.
     */
    private function is_excluded_url( string $url, array $query_vars ): bool {
        // Skip if the URL has an explicit ?lang= query parameter.
        $parsed = $this->url_parser->parse_url( $url );
        if ( isset( $parsed['query'] ) ) {
            \parse_str( $parsed['query'], $url_query );
            if ( isset( $url_query['lang'] ) ) {
                return true;
            }
        }

        // Make sure pagename query var is set.
        if ( ! isset( $query_vars['pagename'] ) ) {
            return false;
        }

        // Check if the url is a non allowed post, if so, skip it.
        $page = \get_page_by_path( $query_vars['pagename'] );
        if ( ! $page ) {
            return false;
        }

        // Check if the page is excluded from translation (e.g. woocommerce account pages).
        return \in_array( $page->ID, $this->get_excluded_post_ids(), true );
    }

    /**
     * Replace the URL in a single link element.
     *
     * @param  \voku\helper\SimpleHtmlDomInterface $link Link element to process.
     * @return void
     */
    private function replace_link_url( $link ): void {
        $original_url = $this->html_processor->get_link_href( $link );
        if ( ! $original_url ) {
            return;
        }

        // Extract the query vars from the url.
        $query_vars = $this->url_parser->extract_query_vars_from_url( $original_url );
        if ( ! $query_vars ) {
            return;
        }

        // Skip if the url is excluded.
        if ( $this->is_excluded_url( $original_url, $query_vars ) ) {
            return;
        }

        // Strip lang from query vars — it comes from rewrite rules with force_lang >= 1
        // and is not needed for post/term resolution.
        unset( $query_vars['lang'] );

        // Get the translated url.
        $translated_url = $this->translation_service->get_translated_url(
            $query_vars,
            $this->content_resolver,
        );

        // Skip if the translated url is the same as the original url or if the translated url is not found.
        if ( ! $translated_url || $translated_url === $original_url ) {
            return;
        }

        // Preserve the url components and update the link href.
        $final_url = $this->url_parser->preserve_url_components( $translated_url, $original_url );
        $this->html_processor->update_link_href( $link, $final_url );
    }

    /**
     * Get the non allowed post ids.
     *
     * @return array<int> The non allowed post ids.
     */
    private function get_excluded_post_ids(): array {
        if ( ! $this->excluded_post_ids ) {
            // We don't want to translate the woocommerce account page related urls.
            $wc_account_page_id = \get_option( 'woocommerce_myaccount_page_id' );
            if ( $wc_account_page_id ) {
                $this->excluded_post_ids[] = (int) $wc_account_page_id;
            }
        }
        return $this->excluded_post_ids;
    }
}

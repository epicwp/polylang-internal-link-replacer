<?php
/**
 * URL Parser Service.
 *
 * @package EPIC_WP\Polylang_Internal_Link_Replacer
 */

namespace EPIC_WP\Polylang_Internal_Link_Replacer;

/**
 * URL Parser Service.
 *
 * Handles URL parsing and query variable extraction using WordPress rewrite rules.
 */
class URL_Parser {
    /**
     * Home URL for the site, used to identify internal URLs.
     *
     * @var string
     */
    private string $home_url;

    /**
     * Cached rewrite rules.
     *
     * @var array<string,string>
     */
    private array $rewrite_rules = array();

    /**
     * Constructor.
     */
    public function __construct() {
        $this->home_url = \get_option( 'home' );
    }

    /**
     * Check if a URL is an internal URL that should be processed.
     *
     * @param  string $url URL to check.
     * @return bool
     */
    public function is_internal_url( string $url ): bool {
        return \str_starts_with( $url, $this->home_url ) || \str_starts_with( $url, '/' );
    }

    /**
     * Extract query variables from a URL using WordPress rewrite rules.
     *
     * @param  string $url URL to extract query variables from.
     * @return array<string,mixed>|null Query variables or null if no match found.
     */
    public function extract_query_vars_from_url( string $url ): ?array {
        $parsed_url = $this->parse_url( $url );
        if ( ! $parsed_url || ! isset( $parsed_url['path'] ) ) {
            return null;
        }

        $path = \ltrim( $parsed_url['path'], '/' );
        if ( '' === $path ) {
            return null;
        }

        return $this->match_rewrite_rules( $path );
    }

    /**
     * Parse URL safely using WordPress function.
     *
     * @param  string $url URL to parse.
     * @return array{
     *   scheme?: string,
     *   host?: string,
     *   port?:int,
     *   user?: string,
     *   pass?: string,
     *   path?: string,
     *   query?: string,
     *   fragment?: string
     * }
     */
    public function parse_url( string $url ): array {
        return \wp_parse_url( $url ) ?: array();
    }

    /**
     * Preserve query parameters and fragments from the original URL.
     *
     * @param  string $translated_url Translated URL.
     * @param  string $original_url Original URL.
     * @return string URL with preserved components.
     */
    public function preserve_url_components( string $translated_url, string $original_url ): string {
        $original_parsed   = $this->parse_url( $original_url );
        $translated_parsed = $this->parse_url( $translated_url );

        // Preserve original query parameters.
        if ( isset( $original_parsed['query'] ) ) {
            \parse_str( $original_parsed['query'], $original_query );
            \parse_str( $translated_parsed['query'] ?? '', $translated_query );

            // Merge queries, original takes precedence.
            $combined_query = \array_merge( $translated_query, $original_query );
            $translated_url = \add_query_arg( $combined_query, $translated_url );
        }

        // Preserve fragment.
        if ( isset( $original_parsed['fragment'] ) ) {
            $translated_url .= '#' . $original_parsed['fragment'];
        }

        return $translated_url;
    }

    /**
     * Match URL path against WordPress rewrite rules.
     *
     * @param  string $path URL path to match.
     * @return array<string,mixed>|null Query variables or null if no match found.
     */
    private function match_rewrite_rules( string $path ): ?array {
        $rewrite_rules = $this->get_filtered_rewrite_rules();

        foreach ( $rewrite_rules as $match => $query ) {
            $regex = $this->safe_wrap_preg_pattern( $match );

            if ( ! \preg_match( $regex, $path, $matches ) ) {
                continue;
            }

            // Got a match - net zoals WordPress.
            return $this->convert_rewrite_to_query_vars( $query, $matches );
        }

        return null;
    }

    /**
     * Get filtered rewrite rules (only index.php rules).
     *
     * @return array<string,string>
     */
    private function get_filtered_rewrite_rules(): array {
        global $wp_rewrite;

        if ( array() === $this->rewrite_rules ) {
            $this->rewrite_rules = $wp_rewrite->rules;
        }

        $filtered_rules = array();
        foreach ( $this->rewrite_rules as $regex => $rule ) {
            if ( ! \str_starts_with( $rule, 'index.php' ) ) {
                continue;
            }
            $filtered_rules[ $regex ] = $rule;
        }
        return $filtered_rules;
    }

    /**
     * Convert WordPress rewrite rule to query variables.
     * This mimics what WP::parse_request() does internally.
     *
     * @param  string            $rewrite Rewrite rule string.
     * @param  array<int,string> $matches Regex matches from the URL.
     * @return array<string,mixed>
     */
    private function convert_rewrite_to_query_vars( string $rewrite, array $matches ): array {
        $query_string = (string) \preg_replace( '/^index\.php\?/', '', $rewrite );
        $match_count  = \count( $matches );
        for ( $i = 1; $i < $match_count; $i++ ) {
            $query_string = \str_replace( '$matches[' . $i . ']', $matches[ $i ], $query_string );
        }
        return \wp_parse_args( $query_string );
    }

    /**
     * Safely wrap an externally-provided regex pattern body for preg_match().
     *
     * @param string $pattern_body The raw regex (no delimiters, no modifiers).
     * @param string $modifiers    (Optional) Pattern modifiers, e.g. 'i', 'u', etc.
     * @return string              Complete PCRE pattern, ready for preg_match().
     */
    private function safe_wrap_preg_pattern( $pattern_body, $modifiers = '' ) {
        // WordPress adds ^ to the beginning of the pattern.
        if ( ! \str_starts_with( $pattern_body, '^' ) ) {
            $pattern_body = '^' . $pattern_body;
        }

        // A list of possible delimiters to choose from.
        $delimiters = array( '/', '#', '~', '%', '@', '!' );

        // Pick the first delimiter that does NOT appear in the pattern body.
        foreach ( $delimiters as $del ) {
            if ( false === \strpos( $pattern_body, $del ) ) {
                $delimiter = $del;
                break;
            }
        }

        // If all delimiters appear in the pattern, fall back to '/'.
        if ( ! isset( $delimiter ) ) {
            $delimiter = '/';
            // Escape any '/' in the body.
            $pattern_body = \str_replace( '/', '\\/', $pattern_body );
        }

        // Escape only the chosen delimiter in the pattern body.
        $escaped_body = \str_replace( $delimiter, '\\' . $delimiter, $pattern_body );

        // Build and return the full pattern.
        return $delimiter . $escaped_body . $delimiter . $modifiers;
    }
}

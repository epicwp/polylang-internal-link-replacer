<?php
/**
 * Link Translation Service.
 *
 * @package EPIC_WP\Polylang_Internal_Link_Replacer
 */

namespace EPIC_WP\Polylang_Internal_Link_Replacer;

/**
 * Link Translation Service.
 *
 * Handles translation lookups for posts and terms using Polylang.
 */
class Link_Translation_Service {
    /**
     * Current language for URL replacement.
     *
     * @var string
     */
    private string $current_language;

    /**
     * Constructor.
     */
    public function __construct() {
        $this->current_language = (string) \pll_current_language();
    }

    /**
     * Get the translated URL for the given query variables.
     *
     * @param  array<string,mixed> $query_vars Query variables extracted from the original URL.
     * @param  Content_Resolver    $resolver Content resolver service.
     * @return string|null Translated URL or null if no translation found.
     */
    public function get_translated_url( array $query_vars, Content_Resolver $resolver ): ?string {
        // Try post translation first.
        $translated_url = $this->get_translated_post_url( $query_vars, $resolver );
        if ( $translated_url ) {
            return $translated_url;
        }

        // Try term translation.
        return $this->get_translated_term_url( $query_vars, $resolver );
    }

    /**
     * Get translated URL for a post based on query variables.
     *
     * @param  array<string,mixed> $query_vars Query variables.
     * @param  Content_Resolver    $resolver Content resolver service.
     * @return string|null Translated post URL or null if not found.
     */
    public function get_translated_post_url( array $query_vars, Content_Resolver $resolver ): ?string {
        $post_id = $resolver->extract_post_id_from_query_vars( $query_vars );
        if ( ! $post_id ) {
            return null;
        }

        $translated_post_id = \pll_get_post( $post_id, $this->current_language );
        if ( ! $translated_post_id ) {
            return null;
        }

        $permalink = \get_permalink( $translated_post_id );
        return $permalink ?: null;
    }

    /**
     * Get translated URL for a term based on query variables.
     *
     * @param  array<string,mixed> $query_vars Query variables.
     * @param  Content_Resolver    $resolver Content resolver service.
     * @return string|null Translated term URL or null if not found.
     */
    public function get_translated_term_url( array $query_vars, Content_Resolver $resolver ): ?string {
        $term_data = $resolver->extract_term_data_from_query_vars( $query_vars );
        if ( ! $term_data ) {
            return null;
        }

        $translations = \pll_get_term_translations( $term_data['term_id'] );
        if ( ! isset( $translations[ $this->current_language ] ) ) {
            return null;
        }

        $translated_term_id = $translations[ $this->current_language ];
        $term_link          = \get_term_link( $translated_term_id, $term_data['taxonomy'] );

        return \is_wp_error( $term_link ) ? null : $term_link;
    }
}

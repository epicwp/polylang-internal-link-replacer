<?php
/**
 * Content Resolver Service.
 *
 * @package EPIC_WP\Polylang_Internal_Link_Replacer
 */

namespace EPIC_WP\Polylang_Internal_Link_Replacer;

/**
 * Content Resolver Service.
 *
 * Resolves post and term IDs from query variables.
 */
class Content_Resolver {
    /**
     * Extract post ID from query variables.
     *
     * @param  array<string,mixed> $query_vars Query variables.
     * @return int|null Post ID or null if not found.
     */
    public function extract_post_id_from_query_vars( array $query_vars ): ?int {
        $post_id = $this->extract_post_id_from_standard_keys( $query_vars );
        if ( $post_id ) {
            return $post_id;
        }

        return $this->extract_post_id_from_post_type_keys( $query_vars );
    }

    /**
     * Extract term data from query variables.
     *
     * @param  array<string,mixed> $query_vars Query variables.
     * @return array{term_id: int, taxonomy: string}|null Term data or null if not found.
     */
    public function extract_term_data_from_query_vars( array $query_vars ): ?array {
        // Category by ID.
        if ( isset( $query_vars['cat'] ) && \is_numeric( $query_vars['cat'] ) ) {
            return array(
                'taxonomy' => 'category',
                'term_id'  => (int) $query_vars['cat'],
            );
        }

        // Category by name.
        if ( isset( $query_vars['category_name'] ) ) {
            return $this->get_term_data_by_slug( $query_vars['category_name'], 'category' );
        }

        // Tag.
        if ( isset( $query_vars['tag'] ) ) {
            return $this->get_term_data_by_slug( $query_vars['tag'], 'post_tag' );
        }

        // Custom taxonomies.
        return $this->extract_custom_taxonomy_data( $query_vars );
    }

    /**
     * Get post ID by slug.
     *
     * @param  string $slug Post slug.
     * @return int|null Post ID or null if not found.
     */
    public function get_post_id_by_slug( string $slug ): ?int {
        $post = \get_page_by_path( $slug, OBJECT, $this->get_available_post_types() );
        return $post ? $post->ID : null;
    }

    /**
     * Get page ID by path.
     *
     * @param  string $path Page path.
     * @return int|null Page ID or null if not found.
     */
    public function get_page_id_by_path( string $path ): ?int {
        $page = \get_page_by_path( $path );
        return $page ? $page->ID : null;
    }

    /**
     * Get term ID by slug and taxonomy.
     *
     * @param  string $slug Slug of the term.
     * @param  string $taxonomy Taxonomy name.
     * @return int|null Term ID or null if not found.
     */
    public function get_term_id_by_slug( string $slug, string $taxonomy ): ?int {
        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $term_id = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT t.term_id FROM {$wpdb->terms} t
                 JOIN {$wpdb->term_taxonomy} tt ON t.term_id = tt.term_id
                 WHERE t.slug = %s AND tt.taxonomy = %s",
                $slug,
                $taxonomy,
            ),
        );
        return $term_id ? (int) $term_id : null;
    }

    /**
     * Extract post ID from standard query var keys (p, page_id, name, pagename).
     *
     * @param  array<string,mixed> $query_vars Query variables.
     * @return int|null Post ID or null if not found.
     */
    private function extract_post_id_from_standard_keys( array $query_vars ): ?int {
        $keys = array(
            'p',
            'page_id',
            'name',
            'pagename',
        );

        foreach ( $keys as $key ) {
            if ( ! isset( $query_vars[ $key ] ) ) {
                continue;
            }

            return \is_numeric(
                $query_vars[ $key ],
            ) ? (int) $query_vars[ $key ] : $this->get_post_id_by_slug( $query_vars[ $key ] );
        }

        return null;
    }

    /**
     * Extract post ID from custom post type query var keys.
     *
     * @param  array<string,mixed> $query_vars Query variables.
     * @return int|null Post ID or null if not found.
     */
    private function extract_post_id_from_post_type_keys( array $query_vars ): ?int {
        $post_types = $this->get_available_post_types();

        foreach ( $post_types as $post_type ) {
            if ( ! isset( $query_vars[ $post_type ] ) ) {
                continue;
            }

            if ( \in_array( $post_type, array( 'page', 'post' ), true, ) ) {
                continue;
            }

            return $this->get_post_id_by_slug( $query_vars[ $post_type ] );
        }

        return null;
    }

    /**
     * Get the available post types that are translated by Polylang.
     *
     * @return array<string> The available post types.
     */
    private function get_available_post_types(): array {
        $exclude_post_types = array(
            'wp_block',
            'wp_template_part',
            'wp_navigation',
            'shop_order_placehold',
            'shop_order',
        );

        $post_types = \array_filter(
            \get_post_types(),
            static fn( $post_type ) => \function_exists(
                'pll_is_translated_post_type',
            ) && \pll_is_translated_post_type( $post_type ),
        );

        $post_types = \array_filter(
            $post_types,
            static fn( $post_type ) => ! \in_array( $post_type, $exclude_post_types, true ),
        );

        return \array_values( $post_types );
    }

    /**
     * Extract custom taxonomy data from query variables.
     *
     * @param  array<string,mixed> $query_vars Query variables.
     * @return array{term_id: int, taxonomy: string}|null Term data or null if not found.
     */
    private function extract_custom_taxonomy_data( array $query_vars ): ?array {
        foreach ( $query_vars as $key => $value ) {
            if ( $this->is_translated_taxonomy( $key ) ) {
                return $this->get_term_data_by_slug( $value, $key );
            }
        }

        return null;
    }

    /**
     * Check if a query var key is a translated Polylang taxonomy.
     *
     * @param  string $key Query var key to check.
     * @return bool True if the key is a translated taxonomy.
     */
    private function is_translated_taxonomy( string $key ): bool {
        return \taxonomy_exists( $key )
            && \function_exists( 'pll_is_translated_taxonomy' )
            && \pll_is_translated_taxonomy( $key );
    }

    /**
     * Get term data by slug and taxonomy.
     *
     * @param  string $slug Slug of the term.
     * @param  string $taxonomy Taxonomy name.
     * @return array{term_id: int, taxonomy: string}|null Term data or null if not found.
     */
    private function get_term_data_by_slug( string $slug, string $taxonomy ): ?array {
        // Handle hierarchical slugs by trying the full slug first, then the leaf term.
        $term_id = $this->get_term_id_by_slug( $slug, $taxonomy );

        // If not found and slug contains hierarchical path, try just the leaf term.
        if ( ! $term_id && \str_contains( $slug, '/' ) ) {
            $leaf_slug = \basename( $slug );
            $term_id   = $this->get_term_id_by_slug( $leaf_slug, $taxonomy );
        }

        return $term_id ? array(
            'taxonomy' => $taxonomy,
            'term_id'  => $term_id,
        ) : null;
    }
}

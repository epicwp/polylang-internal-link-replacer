<?php
/**
 * Polylang function stubs for PHPStan.
 *
 * @package EPIC_WP\Polylang_Internal_Link_Replacer
 */

/**
 * Get the current language.
 *
 * @param  string $field Optional field to return.
 * @return string|false The requested field for the current language.
 */
function pll_current_language( string $field = 'slug' ) {}

/**
 * Get the post translation in the given language.
 *
 * @param  int    $post_id Post ID.
 * @param  string $slug    Language slug.
 * @return int|false The translated post ID or false if not found.
 */
function pll_get_post( int $post_id, string $slug = '' ) {}

/**
 * Get all translations for a term.
 *
 * @param  int $term_id Term ID.
 * @return array<string,int> Array of language slug => term ID.
 */
function pll_get_term_translations( int $term_id ): array {}

/**
 * Check if a post type is translated.
 *
 * @param  string $post_type Post type name.
 * @return bool True if the post type is translated.
 */
function pll_is_translated_post_type( string $post_type ): bool {}

/**
 * Check if a taxonomy is translated.
 *
 * @param  string $taxonomy Taxonomy name.
 * @return bool True if the taxonomy is translated.
 */
function pll_is_translated_taxonomy( string $taxonomy ): bool {}

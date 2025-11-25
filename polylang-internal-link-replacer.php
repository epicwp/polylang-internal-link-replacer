<?php
/**
 * Plugin Name: Polylang Internal Link Replacer
 * Plugin URI: https://epicwpsolutions.com
 * Description: Automatically replaces internal links with their translated versions on page load.
 * Version: 0.0.0
 * Requires at least: 6.0
 * Requires PHP: 8.1
 * Requires Plugins: polylang
 * Author: EPIC WP
 * Author URI: https://epicwpsolutions.com
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: polylang-internal-link-replacer
 */

namespace EPIC_WP\Polylang_Internal_Link_Replacer;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Autoload.
require_once __DIR__ . '/vendor/autoload.php';

// Bootstrap on init.
add_action(
	'init',
	static function () {
		// Only run on frontend and REST API.
		if ( is_admin() ) {
			return;
		}

		// Check if Polylang is active.
		if ( ! function_exists( 'pll_current_language' ) ) {
			return;
		}

		// Check if current language is set.
		$current_language = pll_current_language();
		if ( empty( $current_language ) ) {
			return;
		}

		// Initialize the link replacer.
		$replacer = new Link_Replacer();

		// Hook into content filters at the latest possible priority.
		add_filter( 'the_content', array( $replacer, 'replace_urls' ), PHP_INT_MAX );
		add_filter( 'wp_nav_menu_items', array( $replacer, 'replace_urls' ), PHP_INT_MAX );
		add_filter( 'elementor/frontend/the_content', array( $replacer, 'replace_urls' ), PHP_INT_MAX );
	},
	20
);

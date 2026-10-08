<?php
/**
 * Plugin Name:       Titip Cirebon Core
 * Plugin URI:        https://titipcirebon.com
 * Description:       Core business logic for Titip Cirebon — product catalog, weekly offers, order management, and dynamic shortcodes.
 * Version:           1.4.0
 * Requires at least: 6.0
 * Requires PHP:      8.0
 * Author:            Titip Cirebon
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       titip-cirebon-core
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'TC_CORE_VERSION', '1.4.0' );
define( 'TC_CORE_PATH', plugin_dir_path( __FILE__ ) );
define( 'TC_CORE_URL', plugin_dir_url( __FILE__ ) );

// Include class files.
require_once TC_CORE_PATH . 'includes/class-tc-product.php';
require_once TC_CORE_PATH . 'includes/class-tc-weekly-offer.php';
require_once TC_CORE_PATH . 'includes/class-tc-shortcode-catalog.php';
require_once TC_CORE_PATH . 'includes/class-tc-order.php';
require_once TC_CORE_PATH . 'includes/class-tc-shortcode-tracking.php';
require_once TC_CORE_PATH . 'includes/class-tc-admin-dashboard.php';
require_once TC_CORE_PATH . 'includes/class-tc-customer-retention.php';

/**
 * Bootstrap all plugin components.
 */
add_action( 'plugins_loaded', function () {
	TC_Product::get_instance();
	TC_Weekly_Offer::get_instance();
	TC_Shortcode_Catalog::get_instance();
	TC_Order::get_instance();
	TC_Shortcode_Tracking::get_instance();
	TC_Admin_Dashboard::get_instance();
	TC_Customer_Retention::get_instance();
} );

/**
 * Activation: register CPT/taxonomy early, seed default terms, flush rewrites.
 */
register_activation_hook( __FILE__, function () {
	// Ensure CPT + taxonomy are registered before seeding terms.
	$product = TC_Product::get_instance();
	$product->register_post_type();
	$product->register_taxonomy();

	// Ensure Weekly Offer CPT is registered for rewrite rules.
	$offer = TC_Weekly_Offer::get_instance();
	$offer->register_post_type();

	// Ensure Order CPT is registered.
	$order = TC_Order::get_instance();
	$order->register_post_type();

	// Seed default product categories.
	$defaults = array( 'Jajanan', 'Makanan', 'Minuman', 'Oleh-oleh', 'Non-Makanan', 'Lainnya' );
	foreach ( $defaults as $term_name ) {
		if ( ! term_exists( $term_name, 'tc_product_cat' ) ) {
			wp_insert_term( $term_name, 'tc_product_cat' );
		}
	}

	flush_rewrite_rules();
} );

/**
 * Deactivation: clean up rewrite rules.
 */
register_deactivation_hook( __FILE__, function () {
    flush_rewrite_rules();
} );

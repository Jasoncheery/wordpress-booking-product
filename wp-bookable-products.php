<?php
/**
 * Plugin Name: WP Bookable Products for WooCommerce
 * Plugin URI: https://github.com/Jasoncheery/wp-bookable-products
 * Description: Full-featured bookable products & booking engine for WooCommerce. Configure resources, availability slots, handle bookings through cart/checkout, with calendar management and notification support.
 * Version: 1.0.0-alpha
 * Author: Coda AI / Jason Yuen
 * Author URI: https://codaai.tech
 * Requires at least: 6.5
 * Requires PHP: 8.1
 * Tested up to: 6.7
 * WC requires at least: 9.0
 * WC tested up to: 9.5
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: wp-bookable-products
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Prevent direct access.
if ( ! class_exists( 'WP_Bookable_Products\Autoloader', false ) ) {
	require_once __DIR__ . '/includes/Autoloader.php';
	new WP_Bookable_Products\Autoloader( __NAMESPACE__, __DIR__ . '/includes/' );
}

define( 'WBP_VERSION', '1.0.0-alpha' );
define( 'WBP_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'WBP_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'WBP_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

require_once WBP_PLUGIN_DIR . 'includes/Core/Plugin.php';

function wbp_bootstrap(): void {
	$plugin = new WP_Bookable_Products\Core\Plugin();
	$plugin->init();
}

add_action( 'plugins_loaded', 'wbp_bootstrap' );

register_activation_hook( __FILE__, static function () {
	WP_Bookable_Products\Storage\Database::activate();
} );

register_deactivation_hook( __FILE__, static function () {
	WP_Bookable_Products\Core\Scheduler::deactivate();
} );

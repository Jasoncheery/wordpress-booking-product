<?php
namespace WP_Bookable_Products\Admin;

/**
 * Registers admin menu items for WooCommerce.
 */
class Menu {

	/**
	 * Initialize admin menu.
	 */
	public static function init(): void {
		add_action( 'admin_menu', [ __CLASS__, 'register_admin_menus' ] );
	}

	/**
	 * Register top-level and submenu items under WooCommerce.
	 */
	public static function register_admin_menus(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		// Bookings submenu under WooCommerce.
		add_submenu_page(
			'woocommerce',
			__( 'Bookings', 'wp-bookable-products' ),
			__( 'Bookings', 'wp-bookable-products' ),
			'manage_woocommerce',
			'wbp_bookings',
			[ __CLASS__, 'render_bookings_page' ]
		);

		// Settings submenu.
		add_submenu_page(
			'woocommerce',
			__( 'Booking Settings', 'wp-bookable-products' ),
			__( 'Booking Settings', 'wp-bookable-products' ),
			'manage_woocommerce',
			'wbp_settings',
			[ __CLASS__, 'render_settings_page' ]
		);
	}

	/**
	 * Render the Bookings list page.
	 */
	public static function render_bookings_page(): void {
		require_once WBP_PLUGIN_DIR . 'templates/admin/bookings-list.php';
	}

	/**
	 * Render the Settings page.
	 */
	public static function render_settings_page(): void {
		require_once WBP_PLUGIN_DIR . 'templates/admin/settings.php';
	}
}

<?php
namespace WP_Bookable_Products\Core;

use WP_Bookable_Products\Admin as WBP_Admin;
use WP_Bookable_Products\Rest as WBP_Rest;
use WP_Bookable_Products\Storage\Database;

/**
 * Main Plugin class — bootstraps all components.
 */
class Plugin {

	public function init(): void {
		// i18n.
		load_plugin_textdomain( 'wp-bookable-products', false, dirname( WBP_PLUGIN_BASENAME ) . '/languages' );

		// Database tables.
		Database::init();

		// Post types + product type registration.
		add_action( 'init', [ $this, 'register_post_types' ] );
		add_filter( 'product_type_selector', [ $this, 'add_product_type_selector' ] );
		add_filter( 'woocommerce_product_class', [ $this, 'load_bookable_product_class' ], 10, 2 );

		// Hooks.
		Scheduler::init();
		Hooks::init();

		// Admin.
		if ( is_admin() ) {
			WBP_Admin\\Menu::init();
			WBP_Admin\\MetaBoxes::init();
			add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_assets' ] );
		}

		// Frontend assets.
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_frontend_assets' ] );

		// REST routes.
		add_action( 'rest_api_init', [ $this, 'register_rest_routes' ] );

		// WooCommerce hooks for cart/checkout integration — loaded via Hooks class.
	}

	public function register_post_types(): void {
		register_post_type( 'wbp_booking', [
			'label'         => __( 'Bookings', 'wp-bookable-products' ),
			'public'        => false,
			'show_ui'       => true,
			'show_in_menu'  => false, // Handled by our custom admin menu.
			'supports'      => [ 'title' ],
			'rewrite'       => false,
			'capabilities'  => [
				'edit_post'    => 'manage_woocommerce',
				'read_post'    => 'manage_woocommerce',
				'delete_post'  => 'manage_woocommerce',
				'edit_posts'   => 'manage_woocommerce',
				'delete_posts' => 'manage_woocommerce',
			],
		] );
	}

	public function add_product_type_selector( array $types ): array {
		$types['bookable'] = __( 'Bookable Product', 'wp-bookable-products' );
		return $types;
	}

	public function load_bookable_product_class( string $class, string $product_type ): string {
		if ( 'bookable' === $product_type ) {
			return 'WP_Bookable_Products\\Integrations\\Product\\BookableProduct';
		}
		return $class;
	}

	public function enqueue_admin_assets( string $hook ): void {
		if ( ! in_array( $hook, [ 'shop_order_page_wbp_bookings', 'shop_product_page_wbp_settings', 'edit-shop_product' ], true ) ) {
			return;
		}
		wp_enqueue_style( 'wbp-admin', WBP_PLUGIN_URL . 'assets/css/admin.css', [], WBP_VERSION );
		wp_enqueue_script( 'wbp-admin', WBP_PLUGIN_URL . 'assets/js/admin.js', [ 'jquery' ], WBP_VERSION, true );
		wp_localize_script( 'wbp-admin', 'wbp_params', [
			'ajax_url'  => admin_url( 'admin-ajax.php' ),
			'nonce'     => wp_create_nonce( 'wbp_admin_nonce' ),
			'i18n'      => [
				'confirm_delete' => __( 'Are you sure you want to delete this booking?', 'wp-bookable-products' ),
			],
		] );
	}

	public function enqueue_frontend_assets(): void {
		wp_enqueue_style( 'wbp-frontend', WBP_PLUGIN_URL . 'assets/css/frontend.css', [], WBP_VERSION );
		wp_enqueue_script( 'wbp-slot-picker', WBP_PLUGIN_URL . 'assets/js/slot-picker.js', [ 'jquery' ], WBP_VERSION, true );
		wp_localize_script( 'wbp-slot-picker', 'wbp_slot_picker', [
			'rest_url' => esc_url_raw( rest_url( 'wbp/v1/' ) ),
			'nonce'    => wp_create_nonce( 'wp_rest' ),
			'i18n'     => [
				'loading'  => __( 'Loading available slots...', 'wp-bookable-products' ),
				'no_slots' => __( 'No slots available for selected date.', 'wp-bookable-products' ),
				'error'    => __( 'Unable to load slots. Please try again.', 'wp-bookable-products' ),
			],
		] );
	}

	public function register_rest_routes(): void {
		WBP_Rest\Controller::register_routes();
	}
}

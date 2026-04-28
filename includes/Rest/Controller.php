<?php
namespace WP_Bookable_Products\Rest;

/**
 * Registers and initializes REST API route namespaces.
 */
class Controller {

	/**
	 * Initialize REST routes.
	 */
	public static function init(): void {
		add_action( 'rest_api_init', [ __CLASS__, 'register_routes' ] );
	}

	/**
	 * Register all custom REST routes.
	 */
	public static function register_routes(): void {
		// Slots availability routes (public, for frontend slot picker).
		$slots = new SlotsController();
		$slots->register_routes();

		// Booking CRUD routes (public create, admin read/write/delete).
		$bookings = new BookingsController();
		$bookings->register_routes();

		// Resource management routes (admin only).
		$resources = new ResourcesController();
		$resources->register_routes();
	}
}

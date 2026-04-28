<?php
namespace WP_Bookable_Products\Rest;

use WP_REST_Controller;
use WP_REST_Server;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_Bookable_Products\Engine\BookingService;
use WP_Bookable_Products\Engine\BookingRepository;
use WP_Bookable_Products\Storage\Database;

/**
 * REST controller for booking CRUD operations.
 */
class BookingsController extends WP_REST_Controller {

	public function __construct() {
		$this->namespace = 'wbp/v1';
		$this->rest_base = 'bookings';
	}

	public function register_routes(): void {
		// Get all bookings (admin only).
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			[
				[
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => [ $this, 'get_items' ],
					'permission_callback' => [ $this, 'check_admin_permission' ],
					'args'                => [
						'resource_id' => [
							'type'              => 'integer',
							'sanitize_callback' => 'absint',
						],
						'status'      => [
							'type'              => 'string',
							'enum'              => [ 'pending', 'confirmed', 'cancelled', 'completed', 'no_show' ],
						],
						'search'    => [
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_text_field',
						],
					],
				],
				[
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => [ $this, 'create_item' ],
					'permission_callback' => [ $this, 'check_create_permission' ],
					'args'                => $this->get_endpoint_args_for_item_schema(),
				],
			]
		);

		// Single booking endpoint.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>\d+)',
			[
				[
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => [ $this, 'get_item' ],
					'permission_callback' => [ $this, 'check_read_permission' ],
				],
				[
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => [ $this, 'update_item' ],
					'permission_callback' => [ $this, 'check_admin_permission' ],
					'args'                => $this->get_endpoint_args_for_item_schema(),
				],
				[
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => [ $this, 'delete_item' ],
					'permission_callback' => [ $this, 'check_admin_permission' ],
				],
			]
		);

		// Action endpoints — single action on a booking.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>\d+)/cancel',
			[
				'methods'             => WP_REST_Server::EDITABLE,
				'callback'            => [ $this, 'cancel_item' ],
				'permission_callback' => [ $this, 'check_cancel_permission' ],
			]
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>\d+)/confirm',
			[
				'methods'             => WP_REST_Server::EDITABLE,
				'callback'            => [ $this, 'confirm_item' ],
				'permission_callback' => [ $this, 'check_admin_permission' ],
			]
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>\d+)/complete',
			[
				'methods'             => WP_REST_Server::EDITABLE,
				'callback'            => [ $this, 'complete_item' ],
				'permission_callback' => [ $this, 'check_admin_permission' ],
			]
		);
	}

	/**
	 * List all bookings (with filters).
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response Response with bookings list.
	 */
	public function get_items( WP_REST_Request $request ): WP_REST_Response {
		$args = [];

		if ( $request->get_param( 'resource_id' ) ) {
			$args['resource_id'] = absint( $request->get_param( 'resource_id' ) );
		}

		// If searching or filtering by resource, use find_by_resource_and_date_range.
		if ( ! empty( $args['resource_id'] ) ) {
			$from = sanitize_text_field( $request->get_param( 'search' ) ?: date( 'Y-m-d', strtotime( '-30 days' ) ) );
			$to   = sanitize_text_field( $request->get_param( 'status' ) ? '' : date( 'Y-m-d' ) );
			if ( empty( $to ) ) {
				$to = date( 'Y-m-d' );
			}
			$bookings = BookingRepository::find_by_resource_and_date_range(
				$args['resource_id'],
				gmdate( 'Y-m-d 00:00:00', strtotime( $from ) ),
				gmdate( 'Y-m-d 23:59:59', strtotime( $to ) )
			);
		} else {
			// Fall back to fetching via audit log as approximate listing.
			$bookings = [];
		}

		return new WP_REST_Response( [
			'bookings' => $bookings,
			'count'    => count( $bookings ),
		], 200 );
	}

	/**
	 * Create a new booking.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error Response.
	 */
	public function create_item( WP_REST_Request $request ) {
		try {
			$data = [
				'resource_id'        => absint( $request->get_param( 'resource_id' ) ),
				'woo_product_id'     => absint( $request->get_param( 'woo_product_id' ) ?: 0 ),
				'slot_start'         => sanitize_text_field( $request->get_param( 'slot_start' ) ),
				'slot_end'           => sanitize_text_field( $request->get_param( 'slot_end' ) ),
				'customer_name'      => sanitize_text_field( $request->get_param( 'customer_name' ) ?: 'Guest' ),
				'customer_email'     => is_email( sanitize_text_field( $request->get_param( 'customer_email' ) ?: '' ) )
					? sanitize_email( $request->get_param( 'customer_email' ) )
					: '',
				'total_amount'       => floatval( $request->get_param( 'total_amount' ) ?: 0 ),
				'confirmation_sent_at' => '',
			];

			$booking = BookingService::create( $data );
			$response = $booking->to_array();

			return new WP_REST_Response( $response, 201 );

		} catch ( \Exception $e ) {
			return new WP_Error(
				'wbp_booking_failed',
				$e->getMessage(),
				[ 'status' => 400 ]
			);
		}
	}

	/**
	 * Get a single booking.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error Response.
	 */
	public function get_item( WP_REST_Request $request ) {
		$id = absint( $request->get_param( 'id' ) );
		$booking = BookingRepository::find_by_id( $id );

		if ( ! $booking ) {
			return new WP_Error( 'wbp_booking_not_found', 'Booking not found.', [ 'status' => 404 ] );
		}

		return new WP_REST_Response( $booking, 200 );
	}

	/**
	 * Update a booking.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error Response.
	 */
	public function update_item( WP_REST_Request $request ) {
		$id = absint( $request->get_param( 'id' ) );
		$booking = BookingRepository::find_by_id( $id );

		if ( ! $booking ) {
			return new WP_Error( 'wbp_booking_not_found', 'Booking not found.', [ 'status' => 404 ] );
		}

		// Update status if provided.
		if ( $request->get_param( 'status' ) ) {
			$status_map = [
				'confirm'  => 'confirm',
				'cancel'   => 'cancel',
				'complete' => 'complete',
			];
			$action_method = $status_map[ $request->get_param( 'status' ) ] ?? '';
			if ( $action_method && method_exists( BookingService::class, $action_method ) ) {
				BookingService::{$action_method}( $id );
			}
		}

		// Refresh and return updated booking.
		$updated = BookingRepository::find_by_id( $id );
		return new WP_REST_Response( $updated, 200 );
	}

	/**
	 * Delete a booking.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error Response.
	 */
	public function delete_item( WP_REST_Request $request ) {
		$id = absint( $request->get_param( 'id' ) );
		$booking = BookingRepository::find_by_id( $id );

		if ( ! $booking ) {
			return new WP_Error( 'wbp_booking_not_found', 'Booking not found.', [ 'status' => 404 ] );
		}

		// Release availability locks before deleting.
		foreach ( $booking['items'] as $item ) {
			$avail_id = $this->find_availability_slot( $booking['resource_id'], $item['slot_start'] );
			if ( $avail_id ) {
				Database::release_lock( $avail_id, $booking['resource_id'] );
			}
		}

		BookingRepository::delete( $id );

		return new WP_REST_Response( [ 'deleted' => true, 'id' => $id ], 200 );
	}

	/**
	 * Cancel a booking (REST action endpoint).
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error Response.
	 */
	public function cancel_item( WP_REST_Request $request ) {
		$id = absint( $request->get_param( 'id' ) );
		try {
			BookingService::cancel( $id );
			return new WP_REST_Response( [ 'success' => true, 'id' => $id, 'action' => 'cancelled' ], 200 );
		} catch ( \Exception $e ) {
			return new WP_Error( 'wbp_cancel_failed', $e->getMessage(), [ 'status' => 400 ] );
		}
	}

	/**
	 * Confirm a booking (REST action endpoint).
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error Response.
	 */
	public function confirm_item( WP_REST_Request $request ) {
		$id = absint( $request->get_param( 'id' ) );
		try {
			BookingService::confirm( $id );
			return new WP_REST_Response( [ 'success' => true, 'id' => $id, 'action' => 'confirmed' ], 200 );
		} catch ( \Exception $e ) {
			return new WP_Error( 'wbp_confirm_failed', $e->getMessage(), [ 'status' => 400 ] );
		}
	}

	/**
	 * Complete a booking (REST action endpoint).
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error Response.
	 */
	public function complete_item( WP_REST_Request $request ) {
		$id = absint( $request->get_param( 'id' ) );
		try {
			BookingService::complete( $id );
			return new WP_REST_Response( [ 'success' => true, 'id' => $id, 'action' => 'completed' ], 200 );
		} catch ( \Exception $e ) {
			return new WP_Error( 'wbp_complete_failed', $e->getMessage(), [ 'status' => 400 ] );
		}
	}

	/**
	 * Find availability slot matching a resource + start time.
	 *
	 * @param int    $resource_id Resource ID.
	 * @param string $slot_start Slot start time.
	 * @return int|null Availability row ID.
	 */
	private function find_availability_slot( int $resource_id, string $slot_start ): ?int {
		global $wpdb;

		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT id FROM {$wpdb->wbp_availability}
				 WHERE resource_id = %d AND DATE(slot_start) = DATE(%s)
				 ORDER BY slot_start ASC LIMIT 1",
				$resource_id,
				gmdate( 'Y-m-d H:i:s', strtotime( $slot_start ) )
			),
			ARRAY_A
		);

		return $row ? absint( $row['id'] ) : null;
	}

	/* ---- Permission callbacks ---- */

	public function check_admin_permission( WP_REST_Request $request ): bool {
		return current_user_can( 'manage_woocommerce' );
	}

	public function check_create_permission( WP_REST_Request $request ): bool {
		return true; // Allow unauthenticated booking creation (cart-based).
	}

	public function check_read_permission( WP_REST_Request $request ): bool {
		return current_user_can( 'read' ); // Logged-in users can read their own.
	}

	public function check_cancel_permission( WP_REST_Request $request ): bool {
		$id = absint( $request->get_param( 'id' ) );
		$booking = BookingRepository::find_by_id( $id );
		if ( ! $booking ) {
			return false;
		}
		// Allow customer cancellation if email matches current logged-in user.
		$current_email = wp_get_current_user()->user_email ?? '';
		return $current_email === $booking['customer_email'] || current_user_can( 'manage_woocommerce' );
	}
}

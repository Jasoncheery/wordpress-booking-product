<?php
namespace WP_Bookable_Products\Rest;

use WP_REST_Controller;
use WP_REST_Server;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_Bookable_Products\Engine\BookingService;
use WP_Bookable_Products\Engine\Availability;
use WP_Bookable_Products\Engine\ProductMeta;

/**
 * REST controller for availability slot queries.
 */
class SlotsController extends WP_REST_Controller {

	public function __construct() {
		$this->namespace = 'wbp/v1';
		$this->rest_base = 'slots';
	}

	public function register_routes(): void {
		// Get slots for a resource on a specific date.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/resource/(?P<resource_id>\d+)/(?P<date>[0-9]{4}-[0-9]{2}-[0-9]{2})',
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_slots_for_resource' ],
				'permission_callback' => [ $this, 'get_items_permissions_check' ],
				'args'                => [
					'resource_id' => [
						'required'          => true,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
					],
					'date' => [
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					],
				],
			]
		);

		// Get slots for a product (looks up resource from meta).
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/product/(?P<product_id>\d+)',
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_slots_for_product' ],
				'permission_callback' => [ $this, 'get_items_permissions_check' ],
				'args'                => [
					'product_id' => [
						'required'          => true,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
					],
					'date'         => [
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					],
				],
			]
		);

		// Get slots for a date range (calendar view).
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/range',
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_slots_for_range' ],
				'permission_callback' => [ $this, 'get_items_permissions_check' ],
				'args'                => [
					'resource_id' => [
						'required'          => true,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
					],
					'start_date' => [
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					],
					'end_date'   => [
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					],
				],
			]
		);
	}

	/**
	 * Get slots for a specific resource on a date.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response Response with slot data.
	 */
	public function get_slots_for_resource( WP_REST_Request $request ): WP_REST_Response {
		$resource_id = absint( $request->get_param( 'resource_id' ) );
		$date        = sanitize_text_field( $request->get_param( 'date' ) );

		$slots = Availability::get_available_slots_for_resource( $resource_id, $date );

		return new WP_REST_Response( [
			'resource_id' => $resource_id,
			'date'        => $date,
			'slots'       => $slots,
			'count'       => count( $slots ),
		], 200 );
	}

	/**
	 * Get slots for a WooCommerce product (looks up its resource).
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response Response with slot data.
	 */
	public function get_slots_for_product( WP_REST_Request $request ): WP_REST_Response {
		$product_id = absint( $request->get_param( 'product_id' ) );
		$date       = sanitize_text_field( $request->get_param( 'date' ) ?: '' );

		$resource_id = ProductMeta::get_resource_id( $product_id );
		if ( ! $resource_id ) {
			return new WP_REST_Response( [ 'error' => 'No resource associated with this product.' ], 400 );
		}

		$slots = Availability::get_available_slots_for_resource( $resource_id, $date );

		return new WP_REST_Response( [
			'product_id'  => $product_id,
			'resource_id' => $resource_id,
			'date'        => $date ?: current_time( 'Y-m-d' ),
			'slots'       => $slots,
			'count'       => count( $slots ),
		], 200 );
	}

	/**
	 * Get slots for a date range (calendar view).
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response Response.
	 */
	public function get_slots_for_range( WP_REST_Request $request ): WP_REST_Response {
		$resource_id    = absint( $request->get_param( 'resource_id' ) );
		$start_date     = sanitize_text_field( $request->get_param( 'start_date' ) );
		$end_date       = sanitize_text_field( $request->get_param( 'end_date' ) );

		$slots = Availability::get_slots_for_date_range( $resource_id, $start_date, $end_date );

		return new WP_REST_Response( [
			'resource_id' => $resource_id,
			'start_date'  => $start_date,
			'end_date'    => $end_date,
			'slots'       => $slots,
		], 200 );
	}

	/**
	 * Permission check — public read access for front-end slot lookup.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return bool True if permitted.
	 */
	public function get_items_permissions_check( WP_REST_Request $request ): bool {
		return true; // Frontend needs to query availability without auth.
	}
}

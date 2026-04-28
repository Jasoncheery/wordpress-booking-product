<?php
namespace WP_Bookable_Products\Rest;

use WP_REST_Controller;
use WP_REST_Server;
use WP_Error;
use WP_Request;
use WP_Bookable_Products\Storage\Database;

/**
 * REST controller for resource CRUD operations.
 */
class ResourcesController extends WP_REST_Controller {

	public function __construct() {
		$this->namespace = 'wbp/v1';
		$this->rest_base = 'resources';
	}

	public function register_routes(): void {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			[
				[
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => [ $this, 'get_items' ],
					'permission_callback' => [ $this, 'check_admin_permission' ],
				],
				[
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => [ $this, 'create_item' ],
					'permission_callback' => [ $this, 'check_admin_permission' ],
					'args'                => $this->get_endpoint_args_for_item_schema(),
				],
			]
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>\d+)',
			[
				[
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => [ $this, 'get_item' ],
					'permission_callback' => [ $this, 'check_admin_permission' ],
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

		// Bulk slot creation endpoint.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>\d+)/slots',
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'create_slots' ],
				'permission_callback' => [ $this, 'check_admin_permission' ],
				'args'                => [
					'start_date'     => [ 'required' => true, 'type' => 'string' ],
					'end_date'       => [ 'required' => true, 'type' => 'string' ],
					'time_start'     => [ 'default' => '09:00', 'type' => 'string' ],
					'time_end'       => [ 'default' => '17:00', 'type' => 'string' ],
					'interval'       => [ 'default' => 60, 'type' => 'integer' ],
					'max_bookings'   => [ 'default' => 1, 'type' => 'integer' ],
				],
			]
		);
	}

	public function get_items( WP_Request $request ): WP_REST_Response {
		$resources = Database::get_all_resources();
		return new WP_REST_Response( [
			'resources' => $resources,
			'count'     => count( $resources ),
		], 200 );
	}

	public function get_item( WP_Request $request ): WP_REST_Response {
		$id = absint( $request->get_param( 'id' ) );
		$all = Database::get_all_resources();

		foreach ( $all as $resource ) {
			if ( $resource['id'] === $id ) {
				return new WP_REST_Response( $resource, 200 );
			}
		}

		return new WP_Error( 'wbp_resource_not_found', 'Resource not found.', [ 'status' => 404 ] );
	}

	public function create_item( WP_Request $request ) {
		try {
			$data = [
				'resource_key'      => sanitize_text_field( $request->get_param( 'resource_key' ) ?: '' ),
				'name'              => sanitize_text_field( $request->get_param( 'name' ) ),
				'description'       => wp_kses_post( $request->get_param( 'description' ) ?: '' ),
				'capacity'          => max( 1, absint( $request->get_param( 'capacity' ) ?: 1 ) ),
				'duration_minutes'  => max( 15, absint( $request->get_param( 'duration_minutes' ) ?: 60 ) ),
				'buffer_minutes'    => max( 0, absint( $request->get_param( 'buffer_minutes' ) ?: 0 ) ),
				'default_price'     => floatval( $request->get_param( 'default_price' ) ?: 0 ),
				'status'            => in_array(
					sanitize_text_field( $request->get_param( 'status' ) ?: 'active' ),
					[ 'active', 'inactive' ],
					true
				) ? sanitize_text_field( $request->get_param( 'status' ) ) : 'active',
			];

			$id = Database::insert_resource( $data );
			$new_resource = Database::get_all_resources();

			foreach ( $new_resource as $r ) {
				if ( $r['id'] === $id ) {
					return new WP_REST_Response( $r, 201 );
				}
			}

			return new WP_REST_Response( [ 'id' => $id, 'message' => 'Resource created.' ], 201 );

		} catch ( \Exception $e ) {
			return new WP_Error( 'wbp_resource_create_failed', $e->getMessage(), [ 'status' => 400 ] );
		}
	}

	public function update_item( WP_Request $request ) {
		$id = absint( $request->get_param( 'id' ) );

		try {
			$data = [];
			$keys = [ 'name', 'description', 'capacity', 'duration_minutes', 'buffer_minutes', 'default_price', 'status' ];
			foreach ( $keys as $key ) {
				if ( $request->has_param( $key ) ) {
					$data[ $key ] = match ( $key ) {
						'capacity'      => max( 1, absint( $request->get_param( $key ) ) ),
						'duration_minutes' => max( 15, absint( $request->get_param( $key ) ) ),
						'buffer_minutes'=> max( 0, absint( $request->get_param( $key ) ) ),
						'default_price' => floatval( $request->get_param( $key ) ),
						'status'        => in_array( sanitize_text_field( $request->get_param( $key ) ), [ 'active', 'inactive' ], true ) ? sanitize_text_field( $request->get_param( $key ) ) : null,
						default         => sanitize_text_field( $request->get_param( $key ) ),
					};
					if ( $data[ $key ] !== null ) {
						continue; // all valid.
					}
					unset( $data[ $key ] );
				}
			}

			if ( ! empty( $data ) ) {
				Database::update_resource( $id, $data );
			}

			return new WP_REST_Response( [ 'updated' => true, 'id' => $id ], 200 );

		} catch ( \Exception $e ) {
			return new WP_Error( 'wbp_resource_update_failed', $e->getMessage(), [ 'status' => 400 ] );
		}
	}

	public function delete_item( WP_Request $request ): WP_REST_Response {
		$id = absint( $request->get_param( 'id' ) );
		$deleted = Database::delete_resource( $id );
		return new WP_REST_Response( [ 'deleted' => true, 'id' => $id ], 200 );
	}

	public function create_slots( WP_Request $request ): WP_REST_Response {
		$resource_id = absint( $request->get_param( 'id' ) );

		try {
			$count = Database::create_availability_slots(
				$resource_id,
				sanitize_text_field( $request->get_param( 'start_date' ) ),
				sanitize_text_field( $request->get_param( 'end_date' ) ),
				sanitize_text_field( $request->get_param( 'time_start' ) ?: '09:00' ),
				sanitize_text_field( $request->get_param( 'time_end' ) ?: '17:00' ),
				max( 15, absint( $request->get_param( 'interval' ) ?: 60 ) ),
				max( 1, absint( $request->get_param( 'max_bookings' ) ?: 1 ) )
			);

			return new WP_REST_Response( [
				'slots_created' => $count,
				'resource_id'   => $resource_id,
			], 200 );

		} catch ( \Exception $e ) {
			return new WP_Error( 'wbp_slots_create_failed', $e->getMessage(), [ 'status' => 400 ] );
		}
	}

	public function check_admin_permission( WP_Request $request ): bool {
		return current_user_can( 'manage_woocommerce' );
	}
}

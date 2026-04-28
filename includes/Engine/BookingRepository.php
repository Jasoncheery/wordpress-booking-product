<?php
namespace WP_Bookable_Products\Engine;

use WP_Bookable_Products\Storage\Database;
use WP_Bookable_Products\Models\BookingModel;

/**
 * Data access layer for booking records.
 */
class BookingRepository {

	/**
	 * Insert a new booking record.
	 *
	 * @param array $data Booking data.
	 * @return int The inserted booking ID.
	 */
	public static function insert( array $data ): int {
		global $wpdb;

		$booking_id = $wpdb->insert(
			$wpdb->wbp_bookings,
			[
				'woo_order_id'       => isset( $data['woo_order_id'] ) ? absint( $data['woo_order_id'] ) : null,
				'woo_product_id'     => isset( $data['woo_product_id'] ) ? absint( $data['woo_product_id'] ) : null,
				'resource_id'        => absint( $data['resource_id'] ),
				'customer_email'     => sanitize_email( $data['customer_email'] ),
				'customer_name'      => sanitize_text_field( $data['customer_name'] ),
				'slot_start'         => gmdate( 'Y-m-d H:i:s', strtotime( $data['slot_start'] ) ),
				'slot_end'           => gmdate( 'Y-m-d H:i:s', strtotime( $data['slot_end'] ) ),
				'total_amount'       => floatval( $data['total_amount'] ),
				'status'             => in_array( $data['status'], [ 'pending', 'confirmed', 'cancelled', 'completed', 'no_show' ], true ) ? $data['status'] : 'pending',
				'confirmation_sent_at' => ! empty( $data['confirmation_sent_at'] ) ? gmdate( 'Y-m-d H:i:s', strtotime( $data['confirmation_sent_at'] ) ) : null,
			],
			[ '%d', '%d', '%d', '%s', '%s', '%s', '%s', '%f', '%s', '%s' ]
		);

		if ( false === $booking_id ) {
			throw new \Exception(
				sprintf(
					'Failed to insert booking: %s',
					$wpdb->last_error ?: 'Unknown database error'
				)
			);
		}

		return (int) $wpdb->insert_id;
	}

	/**
	 * Insert a booking item (line-level slot).
	 *
	 * @param int   $booking_id Parent booking ID.
	 * @param string $slot_start Start time ISO 8601.
	 * @param string $slot_end End time ISO 8601.
	 * @param float  $price Item price.
	 * @return int Inserted item ID.
	 */
	public static function insert_item( int $booking_id, string $slot_start, string $slot_end, float $price ): int {
		global $wpdb;

		$item_id = $wpdb->insert(
			$wpdb->wbp_booking_items,
			[
				'booking_id' => absint( $booking_id ),
				'slot_start' => gmdate( 'Y-m-d H:i:s', strtotime( $slot_start ) ),
				'slot_end'   => gmdate( 'Y-m-d H:i:s', strtotime( $slot_end ) ),
				'price'      => floatval( $price ),
			],
			[ '%d', '%s', '%s', '%f' ]
		);

		if ( false === $item_id ) {
			throw new \Exception(
				sprintf(
					'Failed to insert booking item: %s',
					$wpdb->last_error ?: 'Unknown database error'
				)
			);
		}

		return (int) $wpdb->insert_id;
	}

	/**
	 * Get a single booking by ID.
	 *
	 * @param int $id Booking ID.
	 * @return array|null Booking data or null.
	 */
	public static function find_by_id( int $id ): ?array {
		global $wpdb;

		$row = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$wpdb->wbp_bookings} WHERE id = %d", $id ),
			ARRAY_A
		);

		if ( ! $row ) {
			return null;
		}

		return self::enrich_booking( $row );
	}

	/**
	 * Find bookings by WooCommerce order ID.
	 *
	 * @param int $order_id WooCommerce order ID.
	 * @return array Array of bookings.
	 */
	public static function find_by_order_id( int $order_id ): array {
		global $wpdb;

		$rows = $wpdb->get_results(
			$wpdb->prepare( "SELECT * FROM {$wpdb->wbp_bookings} WHERE woo_order_id = %d", $order_id ),
			ARRAY_A
		);

		if ( empty( $rows ) ) {
			return [];
		}

		$result = [];
		foreach ( $rows as $row ) {
			$result[] = self::enrich_booking( $row );
		}

		return $result;
	}

	/**
	 * Find bookings by resource and date range.
	 *
	 * @param int    $resource_id Resource ID.
	 * @param string $start_from  Start datetime Y-m-d H:i:s.
	 * @param string $end_at      End datetime Y-m-d H:i:s.
	 * @param string $status      Optional status filter.
	 * @return array Array of bookings.
	 */
	public static function find_by_resource_and_date_range( int $resource_id, string $start_from, string $end_at, string $status = '' ): array {
		global $wpdb;

		$sql = "SELECT * FROM {$wpdb->wbp_bookings} WHERE resource_id = %d AND slot_start >= %s AND slot_start <= %s";
		$params = [ $resource_id, $start_from, $end_at ];
		$types = [ '%d', '%s', '%s' ];

		if ( ! empty( $status ) ) {
			$sql .= " AND status = %s";
			$params[] = $status;
			$types[] = '%s';
		}

		$sql .= " ORDER BY slot_start ASC";

		$rows = $wpdb->get_results(
			$wpdb->prepare( $sql, ...$params ),
			ARRAY_A
		);

		$result = [];
		foreach ( $rows as $row ) {
			$result[] = self::enrich_booking( $row );
		}

		return $result;
	}

	/**
	 * Find available slots by checking non-conflicting availability rows.
	 *
	 * @param int    $resource_id Resource ID.
	 * @param string $date        Date Y-m-d.
	 * @return array Available slot IDs.
	 */
	public static function get_available_slot_ids( int $resource_id, string $date ): array {
		global $wpdb;

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, is_booked FROM {$wpdb->wbp_availability}
				 WHERE resource_id = %d AND DATE(slot_start) = %s AND status != 'unavailable'",
				$resource_id,
				$date
			),
			ARRAY_A
		);

		$ids = [];
		foreach ( $rows as $row ) {
			if ( (int) $row['is_booked'] !== 1 ) {
				$ids[] = absint( $row['id'] );
			}
		}

		return $ids;
	}

	/**
	 * Update a booking's status.
	 *
	 * @param int    $id Booking ID.
	 * @param string $new_status New status.
	 * @return bool Success.
	 */
	public static function update_status( int $id, string $new_status ): bool {
		global $wpdb;

		// Validate status enum.
		$valid_statuses = [ 'pending', 'confirmed', 'cancelled', 'completed', 'no_show' ];
		if ( ! in_array( $new_status, $valid_statuses, true ) ) {
			return false;
		}

		$updated = $wpdb->update(
			$wpdb->wbp_bookings,
			[ 'status' => $new_status ],
			[ 'id' => $id ],
			[ '%s' ],
			[ '%d' ]
		);

		if ( false === $updated ) {
			return false;
		}

		Database::audit_log(
			'status_changed',
			'booking',
			$id,
			get_current_user_id(),
			[ 'old_status' => '', 'new_status' => $new_status ]
		);

		return true;
	}

	/**
	 * Delete a booking by ID.
	 *
	 * @param int $id Booking ID.
	 * @return bool Success.
	 */
	public static function delete( int $id ): bool {
		global $wpdb;

		// Delete items first (foreign key cascade will handle it, but be explicit).
		$wpdb->delete( $wpdb->wbp_booking_items, [ 'booking_id' => $id ], [ '%d' ] );

		$deleted = $wpdb->delete( $wpdb->wbp_bookings, [ 'id' => $id ], [ '%d' ] );

		if ( false === $deleted ) {
			return false;
		}

		Database::audit_log(
			'booking_deleted',
			'booking',
			$id,
			get_current_user_id()
		);

		return true;
	}

	/**
	 * Enrich a raw booking row with items.
	 *
	 * @param array $row Raw booking row from DB.
	 * @return array Enriched booking.
	 */
	private static function enrich_booking( array $row ): array {
		global $wpdb;

		$items = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->wbp_booking_items} WHERE booking_id = %d ORDER BY slot_start ASC",
				$row['id']
			),
			ARRAY_A
		);

		$row['items'] = array_map(
			static function ( $item ) {
				return [
					'id'         => absint( $item['id'] ),
					'slot_start' => gmdate( 'c', strtotime( $item['slot_start'] ) ),
					'slot_end'   => gmdate( 'c', strtotime( $item['slot_end'] ) ),
					'price'      => (float) $item['price'],
				];
			},
			$items
		);

		return $row;
	}
}

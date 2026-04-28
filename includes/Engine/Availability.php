<?php
namespace WP_Bookable_Products\Engine;

use WP_Bookable_Products\Storage\Database;

/**
 * Computes available time slots for a product/resource on a given date range.
 */
class Availability {

	/**
	 * Get available slots for a product on a specific date.
	 *
	 * @param int    $product_id WooCommerce product ID.
	 * @param string $date       Date in Y-m-d format.
	 * @return array Array of available slot definitions.
	 */
	public static function get_available_slots_for_product( int $product_id, string $date = '' ): array {
		if ( empty( $date ) ) {
			$date = current_time( 'Y-m-d' );
		}

		$resource_id = self::get_resource_id_for_product( $product_id );
		if ( ! $resource_id ) {
			return [];
		}

		return self::get_available_slots_for_resource( $resource_id, $date );
	}

	/**
	 * Get available slots for a resource on a specific date.
	 *
	 * @param int    $resource_id Resource ID.
	 * @param string $date        Date in Y-m-d format.
	 * @return array Array of available slot definitions.
	 */
	public static function get_available_slots_for_resource( int $resource_id, string $date = '' ): array {
		if ( empty( $date ) ) {
			$date = current_time( 'Y-m-d' );
		}

		global $wpdb;

		$table = $wpdb->wbp_availability;

		// Fetch all availability rows matching this resource and date.
		$days   = [
			'0', // Sunday
			'1', '2', '3', '4', '5', '6', // Mon-Sat
		];
		$day_sql = implode( ',', array_map( 'intval', $days ) );

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table}
				 WHERE resource_id = %d
				 AND DAYOFWEEK(slot_start) IN ($day_sql)
				 AND DATE(slot_start) = %s
				 AND status != 'unavailable'",
				$resource_id,
				$date
			),
			ARRAY_A
		);

		if ( empty( $rows ) ) {
			return [];
		}

		$available = [];
		foreach ( $rows as $row ) {
			// Check if there's room via is_booked flag.
			$is_full = (int) $row['is_booked'] === 1;
			if ( ! $is_full ) {
				$available[] = [
					'id'           => absint( $row['id'] ),
					'slot_start'   => gmdate( 'c', strtotime( $row['slot_start'] ) ),
					'slot_end'     => gmdate( 'c', strtotime( $row['slot_end'] ) ),
					'max_bookings' => absint( $row['max_bookings'] ),
					'current_bookings' => absint( $row['current_bookings'] ),
					'price'        => (float) $row['max_bookings'], // Placeholder, overridden by service.
				];
			}
		}

		return $available;
	}

	/**
	 * Get available slots for a date range (for calendar view).
	 *
	 * @param int    $resource_id Resource ID.
	 * @param string $start_date  Start date Y-m-d.
	 * @param string $end_date    End date Y-m-d.
	 * @return array Associative array keyed by date with slot arrays.
	 */
	public static function get_slots_for_date_range( int $resource_id, string $start_date, string $end_date ): array {
		global $wpdb;

		$table = $wpdb->wbp_availability;

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table}
				 WHERE resource_id = %d
				 AND DATE(slot_start) >= %s
				 AND DATE(slot_start) <= %s
				 AND status != 'unavailable'
				 ORDER BY slot_start ASC",
				$resource_id,
				$start_date,
				$end_date
			),
			ARRAY_A
		);

		$result = [];
		foreach ( $rows as $row ) {
			$date_key = gmdate( 'Y-m-d', strtotime( $row['slot_start'] ) );
			$is_full  = (int) $row['is_booked'] === 1;

			$result[ $date_key ][] = [
				'id'              => absint( $row['id'] ),
				'slot_start'      => gmdate( 'c', strtotime( $row['slot_start'] ) ),
				'slot_end'        => gmdate( 'c', strtotime( $row['slot_end'] ) ),
				'max_bookings'    => absint( $row['max_bookings'] ),
				'current_bookings'=> absint( $row['current_bookings'] ),
				'is_available'    => ! $is_full,
				'booking_count'   => $is_full ? 0 : ( $row['max_bookings'] - $row['current_bookings'] ),
			];
		}

		return $result;
	}

	/**
	 * Get resource ID associated with a WooCommerce product.
	 *
	 * @param int $product_id Product ID.
	 * @return int|null Resource ID or null.
	 */
	private static function get_resource_id_for_product( int $product_id ): ?int {
		$resource_id = get_post_meta( $product_id, '_wbp_resource_id', true );
		return $resource_id ? absint( $resource_id ) : null;
	}

	/**
	 * Manually mark an availability slot as booked (used during booking creation).
	 *
	 * @param int $availability_id The wbp_availability row ID.
	 * @return bool True if successfully locked.
	 */
	public static function reserve_slot( int $availability_id, int $resource_id ): bool {
		return Database::acquire_lock( $availability_id, $resource_id );
	}

	/**
	 * Release a previously reserved slot.
	 *
	 * @param int $availability_id The wbp_availability row ID.
	 * @return bool True if released.
	 */
	public static function release_slot( int $availability_id, int $resource_id ): bool {
		return Database::release_lock( $availability_id, $resource_id );
	}
}

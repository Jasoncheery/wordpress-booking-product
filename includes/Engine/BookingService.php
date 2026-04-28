<?php
namespace WP_Bookable_Products\Engine;

use WP_Bookable_Products\Storage\Database;
use WP_Bookable_Products\Models\BookingModel;

/**
 * Business logic layer for bookings: creation, validation, updates.
 * Handles concurrency via availability table lock acquisition.
 */
class BookingService {

	/**
	 * Create a new booking.
	 *
	 * Uses row-level locking on the availability table to prevent
	 * double-booking when multiple requests target the same slot.
	 *
	 * @param array $data Booking creation data.
	 * @return BookingModel The created booking.
	 * @throws \Exception On validation or conflict failure.
	 */
	public static function create( array $data ): BookingModel {
		// Validate required fields.
		$validation = self::validate_booking_data( $data );
		if ( ! empty( $validation ) ) {
			throw new \Exception( implode( ' ', $validation ) );
		}

		$resource_id  = absint( $data['resource_id'] );
		$product_id   = absint( $data['woo_product_id'] );
		$date         = gmdate( 'Y-m-d', strtotime( $data['slot_start'] ) );
		$customer     = sanitize_text_field( $data['customer_name'] );
		$email        = sanitize_email( $data['customer_email'] );

		// Check for time conflicts.
		$has_conflict = Database::has_conflict( $resource_id, $data['slot_start'], $data['slot_end'] );
		if ( $has_conflict ) {
			throw new \Exception(
				sprintf(
					'Slot is no longer available. Another booking may have been made. Please select a different time.',
					get_current_user_id()
				)
			);
		}

		// Try to reserve an availability slot.
		$available_slot_ids = BookingRepository::get_available_slot_ids( $resource_id, $date );
		if ( empty( $available_slot_ids ) ) {
			throw new \Exception(
				sprintf(
					'No available slots found for %s at resource %d. Please choose another date/time.',
					gmdate( 'Y-m-d H:i', strtotime( $data['slot_start'] ) ),
					$resource_id
				)
			);
		}

		// Attempt to acquire lock on first available slot.
		$locked_availability_id = null;
		foreach ( $available_slot_ids as $avail_id ) {
			if ( Database::acquire_lock( $avail_id, $resource_id ) ) {
				$locked_availability_id = $avail_id;
				break;
			}
		}

		if ( null === $locked_availability_id ) {
			throw new \Exception(
				'This slot was just booked by someone else. Please select a different time.'
			);
		}

		try {
			// Calculate price.
			$price = 0.0;
			if ( $product_id && class_exists( '\\WC_Product' ) ) {
				$product = wc_get_product( $product_id );
				if ( $product && $product->is_type( 'bookable' ) ) {
					$price = (float) $product->get_price();
				}
			}
			if ( empty( $price ) ) {
				$price = (float) Database::get_resource_default_price( $resource_id );
			}

			// Insert booking record.
			$booking_id = BookingRepository::insert(
				array_merge( $data, [
					'total_amount'      => $price,
					'status'            => 'pending',
					'confirmation_sent_at' => '',
				] )
			);

			// Insert booking items.
			BookingRepository::insert_item(
				$booking_id,
				$data['slot_start'],
				$data['slot_end'],
				$price
			);

			// Audit log.
			Database::audit_log( 'booking_created', 'booking', $booking_id, get_current_user_id(), [
				'resource_id'    => $resource_id,
				'product_id'     => $product_id,
				'availability_id' => $locked_availability_id,
			] );

			return new BookingModel(
				array_merge( $data, [
					'id'           => $booking_id,
					'total_amount' => $price,
					'status'       => 'pending',
				] )
			);

		} catch ( \Exception $e ) {
			// Rollback: release the lock if we acquired it but something failed after.
			if ( null !== $locked_availability_id ) {
				Database::release_lock( $locked_availability_id, $resource_id );
			}
			throw $e;
		}
	}

	/**
	 * Cancel a booking.
	 *
	 * Releases the availability slot and updates status.
	 *
	 * @param int $booking_id Booking ID.
	 * @return bool Success.
	 */
	public static function cancel( int $booking_id ): bool {
		global $wpdb;

		$booking = BookingRepository::find_by_id( $booking_id );
		if ( ! $booking ) {
			throw new \Exception( 'Booking not found.', 404 );
		}

		// Already cancelled?
		if ( 'cancelled' === $booking['status'] ) {
			throw new \Exception( 'Booking is already cancelled.' );
		}

		$result = BookingRepository::update_status( $booking_id, 'cancelled' );
		if ( ! $result ) {
			throw new \Exception( 'Failed to update booking status.' );
		}

		// Release availability locks for each item in this booking.
		foreach ( $booking['items'] as $item ) {
			$avail_id = self::find_availability_for_slot( $booking['resource_id'], $item['slot_start'] );
			if ( $avail_id ) {
				Database::release_lock( $avail_id, $booking['resource_id'] );
			}
		}

		Database::audit_log( 'booking_cancelled', 'booking', $booking_id, get_current_user_id() );

		return true;
	}

	/**
	 * Confirm a pending booking.
	 *
	 * @param int $booking_id Booking ID.
	 * @return bool Success.
	 */
	public static function confirm( int $booking_id ): bool {
		$result = BookingRepository::update_status( $booking_id, 'confirmed' );
		if ( ! $result ) {
			throw new \Exception( 'Failed to confirm booking.' );
		}

		Database::audit_log( 'booking_confirmed', 'booking', $booking_id, get_current_user_id() );
		return true;
	}

	/**
	 * Complete a confirmed booking (past its end time).
	 *
	 * @param int $booking_id Booking ID.
	 * @return bool Success.
	 */
	public static function complete( int $booking_id ): bool {
		$result = BookingRepository::update_status( $booking_id, 'completed' );
		if ( ! $result ) {
			throw new \Exception( 'Failed to complete booking.' );
		}

		Database::audit_log( 'booking_completed', 'booking', $booking_id, get_current_user_id() );

		// Release availability slots on completion.
		$booking = BookingRepository::find_by_id( $booking_id );
		if ( $booking ) {
			foreach ( $booking['items'] as $item ) {
				$avail_id = self::find_availability_for_slot( $booking['resource_id'], $item['slot_start'] );
				if ( $avail_id ) {
					Database::release_lock( $avail_id, $booking['resource_id'] );
				}
			}
		}

		return true;
	}

	/**
	 * Mark a booking as no-show (past end time, customer didn't attend).
	 *
	 * @param int $booking_id Booking ID.
	 * @return bool Success.
	 */
	public static function mark_no_show( int $booking_id ): bool {
		$result = BookingRepository::update_status( $booking_id, 'no_show' );
		if ( ! $result ) {
			throw new \Exception( 'Failed to mark booking as no-show.' );
		}

		Database::audit_log( 'booking_no_show', 'booking', $booking_id, get_current_user_id() );

		// Release availability slots.
		$booking = BookingRepository::find_by_id( $booking_id );
		if ( $booking ) {
			foreach ( $booking['items'] as $item ) {
				$avail_id = self::find_availability_for_slot( $booking['resource_id'], $item['slot_start'] );
				if ( $avail_id ) {
					Database::release_lock( $avail_id, $booking['resource_id'] );
				}
			}
		}

		return true;
	}

	/**
	 * Find availability row matching a resource+start time.
	 *
	 * @param int    $resource_id Resource ID.
	 * @param string $slot_start ISO datetime string.
	 * @return int|null Availability ID.
	 */
	private static function find_availability_for_slot( int $resource_id, string $slot_start ): ?int {
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

	/**
	 * Validate booking creation input data.
	 *
	 * @param array $data Input data.
	 * @return array Array of error messages (empty means valid).
	 */
	private static function validate_booking_data( array $data ): array {
		$errors = [];

		if ( empty( $data['resource_id'] ) || ! is_numeric( $data['resource_id'] ) ) {
			$errors[] = 'Valid resource ID is required.';
		}

		if ( empty( $data['slot_start'] ) ) {
			$errors[] = 'Slot start time is required.';
		} elseif ( false === strtotime( $data['slot_start'] ) ) {
			$errors[] = 'Invalid slot start time format.';
		}

		if ( empty( $data['slot_end'] ) ) {
			$errors[] = 'Slot end time is required.';
		} elseif ( false === strtotime( $data['slot_end'] ) ) {
			$errors[] = 'Invalid slot end time format.';
		}

		if ( ! empty( $data['slot_start'] ) && ! empty( $data['slot_end'] ) ) {
			if ( strtotime( $data['slot_end'] ) <= strtotime( $data['slot_start'] ) ) {
				$errors[] = 'Slot end time must be after start time.';
			}
		}

		if ( empty( $data['customer_name'] ) || sanitize_text_field( $data['customer_name'] ) === '' ) {
			$errors[] = 'Customer name is required.';
		}

		if ( empty( $data['customer_email'] ) || ! is_email( $data['customer_email'] ) ) {
			$errors[] = 'Valid customer email is required.';
		}

		return $errors;
	}

	/**
	 * Get upcoming bookings for a resource within a date range.
	 *
	 * @param int    $resource_id Resource ID.
	 * @param string $from        Start Y-m-d.
	 * @param string $to          End Y-m-d.
	 * @return array Bookings array.
	 */
	public static function get_bookings_for_resource( int $resource_id, string $from, string $to ): array {
		$start = gmdate( 'Y-m-d 00:00:00', strtotime( $from ) );
		$end   = gmdate( 'Y-m-d 23:59:59', strtotime( $to ) );

		return BookingRepository::find_by_resource_and_date_range( $resource_id, $start, $end );
	}
}

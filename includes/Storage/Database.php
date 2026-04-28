<?php
namespace WP_Bookable_Products\Storage;

use wpdb;

/**
 * Handles custom database tables and schema management.
 */
class Database {

	private const VERSION = 1;

	public static function init(): void {
		global $wpdb;
		$wpdb->wbp_resources      = $wpdb->prefix . 'wbp_resources';
		$wpdb->wbp_availability    = $wpdb->prefix . 'wbp_availability';
		$wpdb->wbp_bookings        = $wpdb->prefix . 'wbp_bookings';
		$wpdb->wbp_booking_items   = $wpdb->prefix . 'wbp_booking_items';
		$wpdb->wbp_booking_audit   = $wpdb->prefix . 'wbp_booking_audit';
	}

	public static function activate(): void {
		self::init();
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset_collate = $wpdb->get_charset_collate();

		// Resources table — physical/service resources (rooms, staff, equipment).
		$sql_resources = "CREATE TABLE {$wpdb->wbp_resources} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			resource_key VARCHAR(100) NOT NULL,
			name VARCHAR(255) NOT NULL,
			description LONGTEXT DEFAULT '',
			capacity INT UNSIGNED DEFAULT 1,
			duration_minutes INT UNSIGNED DEFAULT 60,
			buffer_minutes INT UNSIGNED DEFAULT 0,
			default_price DECIMAL(10,4) DEFAULT 0.0000,
			status ENUM('active','inactive') DEFAULT 'active',
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			UNIQUE KEY resource_key (resource_key),
			KEY status (status),
			PRIMARY KEY (id)
		) $charset_collate;";

		// Availability table — time slot rules per resource + date.
		$sql_availability = "CREATE TABLE {$wpdb->wbp_availability} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			resource_id BIGINT UNSIGNED NOT NULL,
			slot_start DATETIME NOT NULL,
			slot_end DATETIME NOT NULL,
			max_bookings INT UNSIGNED DEFAULT 1,
			current_bookings INT UNSIGNED DEFAULT 0,
			is_booked TINYINT(1) DEFAULT 0,
			status ENUM('available','unavailable','full') DEFAULT 'available',
			FOREIGN KEY (resource_id) REFERENCES {$wpdb->wbp_resources}(id) ON DELETE CASCADE,
			KEY idx_resource_date (resource_id, slot_start, slot_end),
			KEY idx_status (status),
			PRIMARY KEY (id)
		) $charset_collate;";

		// Bookings master table.
		$sql_bookings = "CREATE TABLE {$wpdb->wbp_bookings} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			woo_order_id BIGINT UNSIGNED DEFAULT NULL,
			woo_product_id BIGINT UNSIGNED DEFAULT NULL,
			resource_id BIGINT UNSIGNED NOT NULL,
			customer_email VARCHAR(255) NOT NULL,
			customer_name VARCHAR(255) NOT NULL,
			slot_start DATETIME NOT NULL,
			slot_end DATETIME NOT NULL,
			total_amount DECIMAL(10,4) NOT NULL DEFAULT 0.0000,
			status ENUM('pending','confirmed','cancelled','completed','no_show') DEFAULT 'pending',
			cancelled_by enum('customer','admin','system') DEFAULT NULL,
			confirmation_sent_at DATETIME DEFAULT NULL,
			reminder_sent_at DATETIME DEFAULT NULL,
			FOREIGN KEY (resource_id) REFERENCES {$wpdb->wbp_resources}(id) ON DELETE CASCADE,
			FOREIGN KEY (woo_order_id) REFERENCES {$wpdb->posts}(ID) ON DELETE SET NULL,
			KEY idx_resource_slot (resource_id, slot_start),
			KEY idx_status (status),
			KEY idx_customer_email (customer_email),
			PRIMARY KEY (id)
		) $charset_collate;";

		// Booking line items (one booking may span multiple slots).
		$sql_booking_items = "CREATE TABLE {$wpdb->wbp_booking_items} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			booking_id BIGINT UNSIGNED NOT NULL,
			slot_start DATETIME NOT NULL,
			slot_end DATETIME NOT NULL,
			price DECIMAL(10,4) NOT NULL DEFAULT 0.0000,
			FOREIGN KEY (booking_id) REFERENCES {$wpdb->wbp_bookings}(id) ON DELETE CASCADE,
			PRIMARY KEY (id)
		) $charset_collate;";

		// Audit log for conflict prevention & debugging.
		$sql_audit = "CREATE TABLE {$wpdb->wbp_booking_audit} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			action VARCHAR(50) NOT NULL,
			entity_type VARCHAR(50) NOT NULL,
			entity_id BIGINT UNSIGNED NOT NULL,
			user_id BIGINT UNSIGNED DEFAULT NULL,
			extra_data JSON DEFAULT NULL,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY idx_entity (entity_type, entity_id),
			KEY idx_created (created_at)
		) $charset_collate;";

		dbDelta( $sql_resources );
		dbDelta( $sql_availability );
		dbDelta( $sql_bookings );
		dbDelta( $sql_booking_items );
		dbDelta( $sql_audit );

		update_option( 'wbp_db_version', self::VERSION );
	}

	/**
	 * Acquire an availability lock row to prevent double-booking.
	 * Uses FOR UPDATE on InnoDB for row-level locking.
	 */
	public static function acquire_lock( int $availability_id, int $resource_id ): bool {
		global $wpdb;
		// Try to update and check affected rows.
		$rows = $wpdb->query(
			$wpdb->prepare(
				"UPDATE {$wpdb->wbp_availability}
				 SET current_bookings = current_bookings + 1, is_booked = 1, status = 'full'
				 WHERE id = %d AND resource_id = %d AND is_booked = 0",
				$availability_id,
				$resource_id
			)
		);
		if ( $rows > 0 ) {
			self::audit_log( 'lock_acquired', 'availability', $availability_id, get_current_user_id(), [
				'availability_id' => $availability_id,
				'resource_id'     => $resource_id,
			] );
			return true;
		}
		self::audit_log( 'lock_failed', 'availability', $availability_id, get_current_user_id(), [
			'already_booked' => true,
		] );
		return false;
	}

	/**
	 * Release a previously acquired lock.
	 */
	public static function release_lock( int $availability_id, int $resource_id ): bool {
		global $wpdb;
		$rows = $wpdb->query(
			$wpdb->prepare(
				"UPDATE {$wpdb->wbp_availability}
				 SET current_bookings = GREATEST(current_bookings - 1, 0),
					 is_booked = 0,
					 status = 'available'
				 WHERE id = %d AND resource_id = %d",
				$availability_id,
				$resource_id
			)
		);
		if ( $rows > 0 ) {
			self::audit_log( 'lock_released', 'availability', $availability_id, get_current_user_id() );
		}
		return $rows > 0;
	}

	/**
	 * Check if a slot conflicts with any confirmed/pending bookings.
	 */
	public static function has_conflict( int $resource_id, string $slot_start, string $slot_end ): bool {
		global $wpdb;
		$count = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->wbp_availability}
				 WHERE resource_id = %d AND status != 'unavailable'
				 AND slot_start < %s AND slot_end > %s",
				$resource_id,
				$slot_end,
				$slot_start
			)
		);
		return (int) $count > 0;
	}

	/**
	 * Write an audit entry.
	 */
	public static function audit_log( string $action, string $entity_type, int $entity_id, ?int $user_id = null, ?array $extra = null ): void {
		global $wpdb;
		$wpdb->insert(
			$wpdb->wbp_booking_audit,
			[
				'action'       => $action,
				'entity_type'  => $entity_type,
				'entity_id'    => $entity_id,
				'user_id'      => $user_id,
				'extra_data'   => wp_json_encode( $extra ),
				'created_at'   => current_time( 'mysql' ),
			],
			[ '%s', '%s', '%d', '%d', '%s', '%s' ]
		);
	}

	public static function get_deprecated_columns(): array {
		return []; // v1 uses all new columns.
	}
}

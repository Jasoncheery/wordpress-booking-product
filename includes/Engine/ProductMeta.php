<?php
namespace WP_Bookable_Products\Engine;

/**
 * Manages product-level meta fields for bookable products.
 */
class ProductMeta {

	// Meta key prefixes.
	private const KEY_RESOURCE_ID   = '_wbp_resource_id';
	private const KEY_DEFAULT_PRICE = '_wbp_default_price';
	private const KEY_DURATION      = '_wbp_duration_minutes';
	private const KEY_BUFFER        = '_wbp_buffer_minutes';
	private const KEY_CAPACITY      = '_wbp_capacity';
	private const KEY_ENABLE_CART   = '_wbp_enable_cart';
	private const KEY_ENABLE_CHECKOUT = '_wbp_enable_checkout';

	/**
	 * Get all bookable product meta keys.
	 *
	 * @return string[]
	 */
	public static function get_meta_keys(): array {
		return [
			self::KEY_RESOURCE_ID,
			self::KEY_DEFAULT_PRICE,
			self::KEY_DURATION,
			self::KEY_BUFFER,
			self::KEY_CAPACITY,
			self::KEY_ENABLE_CART,
			self::KEY_ENABLE_CHECKOUT,
		];
	}

	/**
	 * Set all bookable product meta from an associative array.
	 *
	 * @param int   $product_id WooCommerce product ID.
	 * @param array $data       Meta key-value pairs.
	 */
	public static function set_all( int $product_id, array $data ): void {
		if ( isset( $data['resource_id'] ) ) {
			self::set_resource_id( $product_id, absint( $data['resource_id'] ) );
		}
		if ( isset( $data['default_price'] ) ) {
			self::set_default_price( $product_id, floatval( $data['default_price'] ) );
		}
		if ( isset( $data['duration_minutes'] ) ) {
			self::set_duration( $product_id, max( 15, absint( $data['duration_minutes'] ) ) );
		}
		if ( isset( $data['buffer_minutes'] ) ) {
			self::set_buffer( $product_id, max( 0, absint( $data['buffer_minutes'] ) ) );
		}
		if ( isset( $data['capacity'] ) ) {
			self::set_capacity( $product_id, max( 1, absint( $data['capacity'] ) ) );
		}
		if ( isset( $data['enable_cart_booking'] ) ) {
			self::set_enable_cart( $product_id, (bool) $data['enable_cart_booking'] );
		}
		if ( isset( $data['enable_checkout_booking'] ) ) {
			self::set_enable_checkout( $product_id, (bool) $data['enable_checkout_booking'] );
		}
	}

	/**
	 * Get all bookable product meta as an associative array.
	 *
	 * @param int $product_id WooCommerce product ID.
	 * @return array Associative array of meta values.
	 */
	public static function get_all( int $product_id ): array {
		return [
			'resource_id'           => self::get_resource_id( $product_id ),
			'default_price'         => self::get_default_price( $product_id ),
			'duration_minutes'      => self::get_duration( $product_id ),
			'buffer_minutes'        => self::get_buffer( $product_id ),
			'capacity'              => self::get_capacity( $product_id ),
			'enable_cart_booking'   => self::get_enable_cart( $product_id ),
			'enable_checkout_booking' => self::get_enable_checkout( $product_id ),
		];
	}

	/**
	 * Resource ID meta.
	 */
	public static function set_resource_id( int $product_id, int $resource_id ): void {
		update_post_meta( $product_id, self::KEY_RESOURCE_ID, $resource_id );
	}

	public static function get_resource_id( int $product_id ): int {
		return absint( get_post_meta( $product_id, self::KEY_RESOURCE_ID, true ) );
	}

	/**
	 * Default price meta.
	 */
	public static function set_default_price( int $product_id, float $price ): void {
		update_post_meta( $product_id, self::KEY_DEFAULT_PRICE, number_format( $price, 4, '.', '' ) );
	}

	public static function get_default_price( int $product_id ): float {
		$price = get_post_meta( $product_id, self::KEY_DEFAULT_PRICE, true );
		return $price ? floatval( $price ) : 0.0;
	}

	/**
	 * Duration in minutes.
	 */
	public static function set_duration( int $product_id, int $minutes ): void {
		update_post_meta( $product_id, self::KEY_DURATION, max( 15, absint( $minutes ) ) );
	}

	public static function get_duration( int $product_id ): int {
		return max( 15, absint( get_post_meta( $product_id, self::KEY_DURATION, true ) ) );
	}

	/**
	 * Buffer minutes between slots.
	 */
	public static function set_buffer( int $product_id, int $minutes ): void {
		update_post_meta( $product_id, self::KEY_BUFFER, max( 0, absint( $minutes ) ) );
	}

	public static function get_buffer( int $product_id ): int {
		return max( 0, absint( get_post_meta( $product_id, self::KEY_BUFFER, true ) ) );
	}

	/**
	 * Booking capacity per slot.
	 */
	public static function set_capacity( int $product_id, int $capacity ): void {
		update_post_meta( $product_id, self::KEY_CAPACITY, max( 1, absint( $capacity ) ) );
	}

	public static function get_capacity( int $product_id ): int {
		return max( 1, absint( get_post_meta( $product_id, self::KEY_CAPACITY, true ) ) );
	}

	/**
	 * Enable cart booking toggle.
	 */
	public static function set_enable_cart( int $product_id, bool $enabled ): void {
		update_post_meta( $product_id, self::KEY_ENABLE_CART, $enabled ? 'yes' : 'no' );
	}

	public static function get_enable_cart( int $product_id ): bool {
		return 'yes' === get_post_meta( $product_id, self::KEY_ENABLE_CART, true );
	}

	/**
	 * Enable checkout booking toggle.
	 */
	public static function set_enable_checkout( int $product_id, bool $enabled ): void {
		update_post_meta( $product_id, self::KEY_ENABLE_CHECKOUT, $enabled ? 'yes' : 'no' );
	}

	public static function get_enable_checkout( int $product_id ): bool {
		return 'yes' === get_post_meta( $product_id, self::KEY_ENABLE_CHECKOUT, true );
	}
}

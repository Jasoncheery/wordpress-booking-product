<?php
namespace WP_Bookable_Products\Core;

/**
 * Registers all WooCommerce integration hooks.
 */
class Hooks {

	/**
	 * Initialize WooCommerce hooks.
	 */
	public static function init(): void {
		// Cart item data — store booking slot selection in cart items.
		add_filter( 'woocommerce_add_cart_item_data', [ __CLASS__, 'add_cart_item_booking_data' ], 10, 3 );

		// Validate cart items on add to cart.
		add_filter( 'woocommerce_add_to_cart_validation', [ __CLASS__, 'validate_bookable_product_add_to_cart' ], 10, 3 );

		// Cart item name display.
		add_filter( 'woocommerce_get_item_data', [ __CLASS__, 'display_booking_item_data' ], 10, 2 );

		// Order created from checkout — process bookings.
		add_action( 'woocommerce_checkout_order_processed', [ __CLASS__, 'process_order_bookings' ], 10, 2 );

		// Mark bookings when order status changes.
		add_action( 'woocommerce_order_status_changed', [ __CLASS__, 'handle_order_status_change' ], 10, 3 );

		// Add custom fields to checkout for booking time slot selection.
		add_action( 'woocommerce_after_order_notes', [ __CLASS__, 'render_booking_fields_on_checkout' ] );
	}

	/**
	 * Store booking slot info in cart item metadata.
	 *
	 * @param array   $cart_item_data Existing cart data.
	 * @param int     $product_id     Product ID.
	 * @param int|int $variation_id   Variation ID.
	 * @return array Modified cart data.
	 */
	public static function add_cart_item_booking_data( array $cart_item_data, int $product_id, ?int $variation_id = null ): array {
		if ( empty( $_POST['wbp_slot_start'] ) || empty( $_POST['wbp_slot_end'] ) ) {
			return $cart_item_data;
		}

		$cart_item_data['wbp_booking_info'] = [
			'slot_start' => sanitize_text_field( $_POST['wbp_slot_start'] ),
			'slot_end'   => sanitize_text_field( $_POST['wbp_slot_end'] ),
			'resource_id'=> absint( $_POST['wbp_resource_id'] ?? 0 ),
			'customer_name' => sanitize_text_field( $_POST['wbp_customer_name'] ?? '' ),
			'customer_email' => is_email( $_POST['wbp_customer_email'] ?? '' ) ? sanitize_email( $_POST['wbp_customer_email'] ) : '',
		];

		$cart_item_data['unique_key'] = md5( wp_json_encode( $cart_item_data['wbp_booking_info'] ) . ':' . microtime( true ) );
		return $cart_item_data;
	}

	/**
	 * Validate bookable product before adding to cart.
	 *
	 * @param bool   $passed   Validation result.
	 * @param int    $product_id Product ID.
	 * @param int    $quantity Quantity requested.
	 * @return bool Whether validation passed.
	 */
	public static function validate_bookable_product_add_to_cart( bool $passed, int $product_id, int $quantity ): bool {
		// Only validate if this is a bookable product with slot data.
		if ( empty( $_POST['wbp_slot_start'] ) || empty( $_POST['wbp_slot_end'] ) ) {
			return $passed;
		}

		$product = wc_get_product( $product_id );
		if ( ! $product || ! $product->is_type( 'bookable' ) ) {
			return $passed;
		}

		$resource_id = absint( $_POST['wbp_resource_id'] ?? 0 );
		$start       = sanitize_text_field( $_POST['wbp_slot_start'] );
		$end         = sanitize_text_field( $_POST['wbp_slot_end'] );

		// Check for conflicts before allowing add-to-cart.
		$has_conflict = \WP_Bookable_Products\Storage\Database::has_conflict( $resource_id, $start, $end );
		if ( $has_conflict ) {
			wc_add_notice(
				__( 'Sorry, this time slot is no longer available. Please select another time.', 'wp-bookable-products' ),
				'error'
			);
			return false;
		}

		return $passed;
	}

	/**
	 * Display booking details in cart sidebar.
	 *
	 * @param array      $item_data    Existing item data.
	 * @param WC_Cart_Item $cart_item  Cart item object.
	 * @return array Modified item data.
	 */
	public static function display_booking_item_data( array $item_data, array $cart_item ): array {
		if ( isset( $cart_item['wbp_booking_info'] ) && is_array( $cart_item['wbp_booking_info'] ) ) {
			$info = $cart_item['wbp_booking_info'];
			$item_data[] = [
				'label' => __( 'Booking Time Slot', 'wp-bookable-products' ),
				'value' => gmdate( 'M j, Y g:i A', strtotime( $info['slot_start'] ) ) . ' - ' . gmdate( 'g:i A', strtotime( $info['slot_end'] ) ),
			];
		}
		return $item_data;
	}

	/**
	 * Process bookings after checkout order is placed.
	 *
	 * @param int  $order_id WooCommerce order ID.
	 * @param dict $posted Posted checkout data.
	 */
	public static function process_order_bookings( int $order_id, array $posted ): void {
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}

		foreach ( $order->get_items() as $cart_item ) {
			$product = $cart_item->get_product();
			if ( ! $product || ! $product->is_type( 'bookable' ) ) {
				continue;
			}

			if ( empty( $cart_item['wbp_booking_info'] ) ) {
				continue;
			}

			$booking_info = $cart_item['wbp_booking_info'];

			try {
				$booking_data = [
					'woo_order_id'    => $order_id,
					'woo_product_id'  => $product->get_id(),
					'resource_id'     => absint( $booking_info['resource_id'] ),
					'slot_start'      => sanitize_text_field( $booking_info['slot_start'] ),
					'slot_end'        => sanitize_text_field( $booking_info['slot_end'] ),
					'customer_name'   => sanitize_text_field( $booking_info['customer_name'] ?: $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() ),
					'customer_email'  => sanitize_email( $booking_info['customer_email'] ?: $order->get_billing_email() ),
					'total_amount'    => $cart_item->get_subtotal(),
				];

				\WP_Bookable_Products\Engine\BookingService::create( $booking_data );

			} catch ( \Exception $e ) {
				wc_add_notice(
					sprintf(
						__( 'Booking creation failed: %s', 'wp-bookable-products' ),
						esc_html( $e->getMessage() )
					),
					'error'
				);
			}
		}
	}

	/**
	 * Handle order status transitions.
	 *
	 * @param int    $order_id Order ID.
	 * @param string $old_status Previous order status.
	 * @param string $new_status New order status.
	 */
	public static function handle_order_status_change( int $order_id, string $old_status, string $new_status ): void {
		$order = wc_get_order( $order_id );
		if ( ! $order instanceof \WC_Order ) {
			return;
		}

		global $wpdb;

		switch ( $new_status ) {
			case 'processing':
			case 'completed':
				// Confirm pending bookings linked to this order.
				$bookings = $wpdb->get_results(
					$wpdb->prepare(
						"SELECT id FROM {$wpdb->wbp_bookings} WHERE woo_order_id = %d AND status = 'pending'",
						$order->get_id()
					),
					ARRAY_A
				);
				foreach ( $bookings as $booking ) {
					\WP_Bookable_Products\Engine\BookingService::confirm( absint( $booking['id'] ) );
				}
				break;

			case 'cancelled':
				// Cancel pending/confirmed bookings.
				$bookings = $wpdb->get_results(
					$wpdb->prepare(
						"SELECT id FROM {$wpdb->wbp_bookings} WHERE woo_order_id = %d AND status IN ('pending','confirmed')",
						$order->get_id()
					),
					ARRAY_A
				);
				foreach ( $bookings as $booking ) {
					try {
						\WP_Bookable_Products\Engine\BookingService::cancel( absint( $booking['id'] ) );
					} catch ( \Exception $e ) {
						// Log but don't fail.
					}
				}
				break;

			default:
				break;
		}
	}

	/**
	 * Render booking time slot fields on checkout page.
	 *
	 * @param \WC_Checkout $checkout Checkout instance.
	 */
	public static function render_booking_fields_on_checkout( \WC_Checkout $checkout ): void {
		$has_bookable = false;
		if ( ! WC()->cart ) {
			return;
		}

		foreach ( WC()->cart->get_cart() as $cart_item ) {
			$product = $cart_item['data'];
			if ( $product && $product->is_type( 'bookable' ) ) {
				$has_bookable = true;
				break;
			}
		}

		if ( ! $has_bookable ) {
			return;
		}

		echo '<div id="wbp_checkout_booking_fields"><h3>' . esc_html__( 'Booking Details', 'wp-bookable-products' ) . '</h3>';
		echo '<p><label for="wbp_customer_name">' . esc_html__( 'Your Name', 'wp-bookable-products' ) . ' <span class="required">*</span></label>';
		echo '<input type="text" class="input-text" name="wbp_customer_name" id="wbp_customer_name" value="' . esc_attr( $checkout->get_value( 'billing_first_name' ) . ' ' . $checkout->get_value( 'billing_last_name' ) ) . '" /></p>';
		echo '<p><label for="wbp_customer_email">' . esc_html__( 'Your Email', 'wp-bookable-products' ) . ' <span class="required">*</span></label>';
		echo '<input type="email" class="input-text" name="wbp_customer_email" id="wbp_customer_email" value="' . esc_attr( $checkout->get_value( 'billing_email' ) ) . '" /></p>';
		echo '<p class="form-field" id="wbp_slot_selector_field"></p>';
		echo '</div>';
	}
}

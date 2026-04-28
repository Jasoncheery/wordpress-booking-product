<?php
namespace WP_Bookable_Products\Integrations\Product;

use WC_Product_Data_Store_CPT;

/**
 * Bookable product type extending WooCommerce simple variable-like structure.
 */
class BookableProduct extends \WC_Product {

	public function __construct( $product ) {
		$this->set_props( [ 'product_type' => 'bookable' ] );
		parent::__construct( $product );
	}

	protected function get_product_type(): string {
		return 'bookable';
	}

	public function get_available_slots( string $date = '' ): array {
		return \WP_Bookable_Products\Engine\Availability::get_available_slots_for_product( $this->get_id(), $date );
	}

	public function get_bookable_resource_id(): ?int {
		return (int) $this->get_meta( '_wbp_resource_id', true );
	}

	public function get_booking_price(): float {
		$price = $this->get_meta( '_wbp_default_price', true );
		if ( empty( $price ) ) {
			$price = get_post_meta( $this->get_meta( '_wbp_resource_id', true ), '_default_price', true );
		}
		return (float) ( $price ?: $this->get_price() );
	}

	public function get_duration_minutes(): int {
		return max( 15, (int) $this->get_meta( '_wbp_duration_minutes', true ) );
	}

	public function get_buffer_minutes(): int {
		return (int) $this->get_meta( '_wbp_buffer_minutes', true );
	}

	public function get_capacity(): int {
		return max( 1, (int) $this->get_meta( '_wbp_capacity', true ) );
	}
}

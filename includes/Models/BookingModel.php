<?php
namespace WP_Bookable_Products\Models;

/**
 * Lightweight DTO for booking data.
 */
class BookingModel {

	private int $id;
	private ?int $woo_order_id;
	private ?int $woo_product_id;
	private int $resource_id;
	private string $customer_email;
	private string $customer_name;
	private string $slot_start;
	private string $slot_end;
	private float $total_amount;
	private string $status;
	private ?string $confirmation_sent_at;
	/** @var array[] Slot items */
	private array $items;

	public function __construct( array $data ) {
		$this->id                 = isset( $data['id'] ) ? absint( $data['id'] ) : 0;
		$this->woo_order_id       = isset( $data['woo_order_id'] ) ? absint( $data['woo_order_id'] ) : null;
		$this->woo_product_id     = isset( $data['woo_product_id'] ) ? absint( $data['woo_product_id'] ) : null;
		$this->resource_id        = isset( $data['resource_id'] ) ? absint( $data['resource_id'] ) : 0;
		$this->customer_email     = sanitize_email( $data['customer_email'] ?? '' );
		$this->customer_name      = sanitize_text_field( $data['customer_name'] ?? '' );
		$this->slot_start         = gmdate( 'c', strtotime( $data['slot_start'] ?? '' ) );
		$this->slot_end           = gmdate( 'c', strtotime( $data['slot_end'] ?? '' ) );
		$this->total_amount       = isset( $data['total_amount'] ) ? floatval( $data['total_amount'] ) : 0.0;
		$this->status             = in_array( $data['status'] ?? '', [ 'pending', 'confirmed', 'cancelled', 'completed', 'no_show' ], true ) ? $data['status'] : 'pending';
		$this->confirmation_sent_at = ! empty( $data['confirmation_sent_at'] ) ? gmdate( 'c', strtotime( $data['confirmation_sent_at'] ) ) : null;
		$this->items              = isset( $data['items'] ) ? $data['items'] : [];
	}

	public function get_id(): int {
		return $this->id;
	}

	public function get_woo_order_id(): ?int {
		return $this->woo_order_id;
	}

	public function get_woo_product_id(): ?int {
		return $this->woo_product_id;
	}

	public function get_resource_id(): int {
		return $this->resource_id;
	}

	public function get_customer_email(): string {
		return $this->customer_email;
	}

	public function get_customer_name(): string {
		return $this->customer_name;
	}

	public function get_slot_start(): string {
		return $this->slot_start;
	}

	public function get_slot_end(): string {
		return $this->slot_end;
	}

	public function get_total_amount(): float {
		return $this->total_amount;
	}

	public function get_status(): string {
		return $this->status;
	}

	public function get_confirmation_sent_at(): ?string {
		return $this->confirmation_sent_at;
	}

	/**
	 * Get slot items (line-level breakdown).
	 *
	 * @return array[]
	 */
	public function get_items(): array {
		return $this->items;
	}

	/**
	 * Convert to array representation (for API responses).
	 *
	 * @return array
	 */
	public function to_array(): array {
		return [
			'id'                  => $this->id,
			'woo_order_id'        => $this->woo_order_id,
			'woo_product_id'      => $this->woo_product_id,
			'resource_id'         => $this->resource_id,
			'customer_email'      => $this->customer_email,
			'customer_name'       => $this->customer_name,
			'slot_start'          => $this->slot_start,
			'slot_end'            => $this->slot_end,
			'total_amount'        => $this->total_amount,
			'status'              => $this->status,
			'confirmation_sent_at' => $this->confirmation_sent_at,
			'items'               => $this->items,
		];
	}
}

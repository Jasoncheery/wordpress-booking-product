<?php
namespace WP_Bookable_Products\Admin;

use WP_Bookable_Products\Engine\ProductMeta;
use WP_Bookable_Products\Storage\Database;

/**
 * Adds meta boxes to the WooCommerce product edit screen for bookable products.
 */
class MetaBoxes {

	/**
	 * Initialize hooks.
	 */
	public static function init(): void {
		add_action( 'add_meta_boxes', [ __CLASS__, 'register_meta_boxes' ] );
		add_action( 'save_post_shop_product', [ __CLASS__, 'save_meta_box_data' ], 10, 3 );
	}

	/**
	 * Register meta boxes on the product edit screen.
	 */
	public static function register_meta_boxes(): void {
		if ( ! class_exists( 'WC_Meta_Box_Product_Data' ) ) {
			return;
		}

		add_meta_box(
			'wbp_bookable_settings',
			__( 'Booking Settings', 'wp-bookable-products' ),
			[ __CLASS__, 'render_meta_box' ],
			'shop_product',
			'side',
			'high'
		);
	}

	/**
	 * Render the booking settings meta box.
	 *
	 * @param \WP_Post $post Post object.
	 */
	public static function render_meta_box( \WP_Post $post ): void {
		wp_nonce_field( 'wbp_save_bookable_settings', 'wbp_bookable_nonce' );

		$product = wc_get_product( $post->ID );
		if ( ! $product || ! $product->is_type( 'bookable' ) ) {
			echo '<p>' . esc_html__( 'This meta box is only available for Bookable Product type.', 'wp-bookable-products' ) . '</p>';
			return;
		}

		$meta = ProductMeta::get_all( $post->ID );
		$resources = Database::get_all_resources();

		?>
		<div class="wbp-meta-box">
			<p class="form-field">
				<label for="wbp_resource_id"><?php esc_html_e( 'Resource', 'wp-bookable-products' ); ?></label>
				<select name="wbp_resource_id" id="wbp_resource_id" class="widefat">
					<option value=""><?php esc_html_e( '-- Select Resource --', 'wp-bookable-products' ); ?></option>
					<?php foreach ( $resources as $res ) : ?>
						<option value="<?php echo absint( $res['id'] ); ?>" <?php selected( $meta['resource_id'], $res['id'] ); ?>>
							<?php echo esc_html( $res['name'] ); ?>
						</option>
					<?php endforeach; ?>
				</select>
			</p>

			<p class="form-field">
				<label for="wbp_default_price"><?php esc_html_e( 'Booking Price ($)', 'wp-bookable-products' ); ?></label>
				<input type="number" step="0.01" min="0" name="wbp_default_price" id="wbp_default_price"
					value="<?php echo esc_attr( number_format( $meta['default_price'], 2, '.', '' ) ); ?>"
					class="small-text" />
			</p>

			<p class="form-field">
				<label for="wbp_duration_minutes"><?php esc_html_e( 'Duration (minutes)', 'wp-bookable-products' ); ?></label>
				<input type="number" min="15" step="15" name="wbp_duration_minutes" id="wbp_duration_minutes"
					value="<?php echo esc_attr( $meta['duration_minutes'] ); ?>"
					class="small-text" />
			</p>

			<p class="form-field">
				<label for="wbp_buffer_minutes"><?php esc_html_e( 'Buffer (minutes)', 'wp-bookable-products' ); ?></label>
				<input type="number" min="0" step="5" name="wbp_buffer_minutes" id="wbp_buffer_minutes"
					value="<?php echo esc_attr( $meta['buffer_minutes'] ); ?>"
					class="small-text" />
			</p>

			<p class="form-field">
				<label for="wbp_capacity"><?php esc_html_e( 'Capacity per slot', 'wp-bookable-products' ); ?></label>
				<input type="number" min="1" step="1" name="wbp_capacity" id="wbp_capacity"
					value="<?php echo esc_attr( $meta['capacity'] ); ?>"
					class="small-text" />
			</p>

			<p class="form-field">
				<label><?php esc_html_e( 'Enable Booking At Cart', 'wp-bookable-products' ); ?></label>
				<select name="wbp_enable_cart_booking" id="wbp_enable_cart_booking">
					<option value="yes" <?php selected( $meta['enable_cart_booking'], true ); ?>><?php esc_html_e( 'Yes', 'wp-bookable-products' ); ?></option>
					<option value="no" <?php selected( $meta['enable_cart_booking'], false ); ?>><?php esc_html_e( 'No', 'wp-bookable-products' ); ?></option>
				</select>
			</p>

			<p class="form-field">
				<label><?php esc_html_e( 'Require Booking At Checkout', 'wp-bookable-products' ); ?></label>
				<select name="wbp_enable_checkout_booking" id="wbp_enable_checkout_booking">
					<option value="yes" <?php selected( $meta['enable_checkout_booking'], true ); ?>><?php esc_html_e( 'Yes', 'wp-bookable-products' ); ?></option>
					<option value="no" <?php selected( $meta['enable_checkout_booking'], false ); ?>><?php esc_html_e( 'No', 'wp-bookable-products' ); ?></option>
				</select>
			</p>
		</div>
		<?php
	}

	/**
	 * Save meta box data when product is saved.
	 *
	 * @param int      $post_id Post ID.
	 * @param \WP_Post $post    Post object.
	 * @param bool     $update  Whether this is an update.
	 */
	public static function save_meta_box_data( int $post_id, \WP_Post $post, bool $update ): void {
		// Security checks.
		if ( ! isset( $_POST['wbp_bookable_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( $_POST['wbp_bookable_nonce'] ), 'wbp_save_bookable_settings' ) ) {
			return;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( ! current_user_can( 'edit_product', $post_id ) ) {
			return;
		}

		// Only process bookable products.
		if ( empty( $_POST['post_type'] ) || 'shop_product' !== $_POST['post_type'] ) {
			return;
		}

		$data = [];

		if ( isset( $_POST['wbp_resource_id'] ) ) {
			$data['resource_id'] = absint( $_POST['wbp_resource_id'] );
		}
		if ( isset( $_POST['wbp_default_price'] ) ) {
			$data['default_price'] = floatval( $_POST['wbp_default_price'] );
		}
		if ( isset( $_POST['wbp_duration_minutes'] ) ) {
			$data['duration_minutes'] = absint( $_POST['wbp_duration_minutes'] );
		}
		if ( isset( $_POST['wbp_buffer_minutes'] ) ) {
			$data['buffer_minutes'] = absint( $_POST['wbp_buffer_minutes'] );
		}
		if ( isset( $_POST['wbp_capacity'] ) ) {
			$data['capacity'] = absint( $_POST['wbp_capacity'] );
		}
		if ( isset( $_POST['wbp_enable_cart_booking'] ) ) {
			$data['enable_cart_booking'] = 'yes' === $_POST['wbp_enable_cart_booking'];
		}
		if ( isset( $_POST['wbp_enable_checkout_booking'] ) ) {
			$data['enable_checkout_booking'] = 'yes' === $_POST['wbp_enable_checkout_booking'];
		}

		if ( ! empty( $data ) ) {
			ProductMeta::set_all( $post_id, $data );
		}
	}
}

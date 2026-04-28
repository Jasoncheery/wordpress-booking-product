<?php
/**
 * Bookings list admin page template.
 * Placeholder — actual implementation comes in Phase 3.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$bookings = []; // Filled by Phase 3 endpoint.

?>
<div class="wrap">
	<h1><?php esc_html_e( 'Bookings', 'wp-bookable-products' ); ?></h1>
	<table class="wp-list-table widefat fixed striped">
		<thead>
			<tr>
				<th>ID</th>
				<th><?php esc_html_e( 'Customer', 'wp-bookable-products' ); ?></th>
				<th><?php esc_html_e( 'Resource', 'wp-bookable-products' ); ?></th>
				<th><?php esc_html_e( 'Slot Start', 'wp-bookable-products' ); ?></th>
				<th><?php esc_html_e( 'Slot End', 'wp-bookable-products' ); ?></th>
				<th><?php esc_html_e( 'Amount', 'wp-bookable-products' ); ?></th>
				<th><?php esc_html_e( 'Status', 'wp-bookable-products' ); ?></th>
				<th><?php esc_html_e( 'Actions', 'wp-bookable-products' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php if ( empty( $bookings ) ) : ?>
				<tr>
					<td colspan="8" style="text-align:center"><?php esc_html_e( 'No bookings found.', 'wp-bookable-products' ); ?></td>
				</tr>
			<?php else : ?>
				<?php foreach ( $bookings as $booking ) : ?>
					<tr>
						<td><?php echo esc_html( $booking['id'] ); ?></td>
						<td><?php echo esc_html( $booking['customer_name'] ); ?><br>
							<small><?php echo esc_html( $booking['customer_email'] ); ?></small></td>
						<td><?php echo esc_html( $booking['resource_id'] ); ?></td>
						<td><?php echo esc_html( gmdate( 'M j, Y g:i A', strtotime( $booking['slot_start'] ) ) ); ?></td>
						<td><?php echo esc_html( gmdate( 'M j, Y g:i A', strtotime( $booking['slot_end'] ) ) ); ?></td>
						<td><?php echo esc_html( wc_price( $booking['total_amount'] ) ); ?></td>
						<td><?php echo esc_html( ucfirst( $booking['status'] ) ); ?></td>
						<td>
							<a href="#" data-action="confirm" data-id="<?php echo absint( $booking['id'] ); ?>" class="button button-small"><?php esc_html_e( 'Confirm', 'wp-bookable-products' ); ?></a>
							<a href="#" data-action="cancel" data-id="<?php echo absint( $booking['id'] ); ?>" class="button button-small button-link-delete"><?php esc_html_e( 'Cancel', 'wp-bookable-products' ); ?></a>
						</td>
					</tr>
				<?php endforeach; ?>
			<?php endif; ?>
		</tbody>
	</table>
</div>

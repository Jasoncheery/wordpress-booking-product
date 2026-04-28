<?php
namespace WP_Bookable_Products\Notifications;

use WP_Bookable_Products\Models\BookingModel;

/**
 * Handles email notifications for bookings.
 */
class Mailer {

	private const CONFIRMATION = 'confirmation';
	private const REMINDER     = 'reminder';
	private const CANCELLATION = 'cancellation';

	/**
	 * Send a booking confirmation email.
	 *
	 * @param BookingModel $booking The booking model.
	 * @return bool True if sent.
	 */
	public static function send_confirmation( BookingModel $booking ): bool {
		$to    = $booking->get_customer_email();
		$name  = $booking->get_customer_name();
		$subject = sprintf(
			__( 'Booking Confirmation — %s', 'wp-bookable-products' ),
			get_bloginfo( 'name' )
		);

		$body = self::build_confirmation_body( $booking );

		return self::send( $to, $name, $subject, $body );
	}

	/**
	 * Send a booking reminder email.
	 *
	 * @param BookingModel $booking The booking model.
	 * @return bool True if sent.
	 */
	public static function send_reminder( BookingModel $booking ): bool {
		$to    = $booking->get_customer_email();
		$name  = $booking->get_customer_name();
		$start = gmdate( 'M j, Y g:i A', strtotime( $booking->get_slot_start() ) );
		$subject = sprintf(
			__( 'Reminder: Your Booking Tomorrow', 'wp-bookable-products' ),
			get_bloginfo( 'name' )
		);

		$body = sprintf(
			__( "Hi %s,\n\nThis is a friendly reminder about your upcoming booking:\n\nDate: %s\nResource: #%d\nStatus: Confirmed\n\nWe look forward to seeing you!\n\n— %s", 'wp-bookable-products' ),
			$name,
			$start,
			$booking->get_resource_id(),
			get_bloginfo( 'name' )
		);

		return self::send( $to, $name, $subject, $body );
	}

	/**
	 * Send a booking cancellation notification.
	 *
	 * @param BookingModel $booking The booking model.
	 * @return bool True if sent.
	 */
	public static function send_cancellation( BookingModel $booking ): bool {
		$to    = $booking->get_customer_email();
		$name  = $booking->get_customer_name();
		$start = gmdate( 'M j, Y g:i A', strtotime( $booking->get_slot_start() ) );
		$subject = sprintf(
			__( 'Booking Cancelled — %s', 'wp-bookable-products' ),
			get_bloginfo( 'name' )
		);

		$body = sprintf(
			__( "Hi %s,\n\nYour booking has been cancelled:\n\nDate: %s\nBooking ID: %d\nReason: %s\n\nIf this was an error, please contact us.\n\n— %s", 'wp-bookable-products' ),
			$name,
			$start,
			$booking->get_id(),
			'', // reason set by caller
			get_bloginfo( 'name' )
		);

		return self::send( $to, $name, $subject, $body );
	}

	/**
	 * Build confirmation email body HTML.
	 *
	 * @param BookingModel $booking Booking model.
	 * @return string Email body.
	 */
	private static function build_confirmation_body( BookingModel $booking ): string {
		$start   = gmdate( 'M j, Y \\a\\t g:i A', strtotime( $booking->get_slot_start() ) );
		$end     = gmdate( 'g:i A', strtotime( $booking->get_slot_end() ) );
		$price   = wc_price( $booking->get_total_amount() );

		ob_start();
		?>
<p style="font-family: sans-serif; font-size: 16px; line-height: 1.6;">
<strong><?php esc_html_e( 'Booking Confirmed!', 'wp-bookable-products' ); ?></strong>
</p>
<table style="width: 100%; border-collapse: collapse; margin-top: 16px;">
<tr><td style="padding: 8px 12px; background: #f5f5f5; font-weight: bold;"><?php esc_html_e( 'Booking ID:', 'wp-bookable-products' ); ?></td><td style="padding: 8px 12px;"><?php echo esc_html( $booking->get_id() ); ?></td></tr>
<tr><td style="padding: 8px 12px; background: #f5f5f5; font-weight: bold;"><?php esc_html_e( 'Date & Time:', 'wp-bookable-products' ); ?></td><td style="padding: 8px 12px;"><?php echo esc_html( $start ); ?> - <?php echo esc_html( $end ); ?></td></tr>
<tr><td style="padding: 8px 12px; background: #f5f5f5; font-weight: bold;"><?php esc_html_e( 'Resource:', 'wp-bookable-products' ); ?></td><td style="padding: 8px 12px;"><?php echo esc_html( '#' . $booking->get_resource_id() ); ?></td></tr>
<tr><td style="padding: 8px 12px; background: #f5f5f5; font-weight: bold;"><?php esc_html_e( 'Amount Paid:', 'wp-bookable-products' ); ?></td><td style="padding: 8px 12px;"><?php echo $price; ?></td></tr>
<tr><td style="padding: 8px 12px; background: #f5f5f5; font-weight: bold;"><?php esc_html_e( 'Status:', 'wp-bookable-products' ); ?></td><td style="padding: 8px 12px;"><?php echo esc_html( ucfirst( $booking->get_status() ) ); ?></td></tr>
</table>
<p style="margin-top: 16px;"><?php esc_html_e( 'Thank you for your booking!', 'wp-bookable-products' ); ?></p>
<?php
		return ob_get_clean();
	}

	/**
	 * Send an email using wp_mail.
	 *
	 * @param string $to      Recipient email.
	 * @param string $name    Recipient name.
	 * @param string $subject Email subject.
	 * @param string $body    Email body (HTML supported).
	 * @return bool True if mail sent successfully.
	 */
	private static function send( string $to, string $name, string $subject, string $body ): bool {
		if ( empty( $to ) || ! is_email( $to ) ) {
			return false;
		}

		$headers = [
			'Content-Type: text/html; charset=UTF-8',
			sprintf( 'From: "%s" <%s>', sanitize_text_field( get_option( 'wbp_email_from_name', get_bloginfo( 'name' ) ) ), sanitize_email( get_option( 'wbp_email_from_address', get_bloginfo( 'admin_email' ) ) ) ),
		];

		return (bool) wp_mail( $to, $subject, $body, $headers );
	}
}

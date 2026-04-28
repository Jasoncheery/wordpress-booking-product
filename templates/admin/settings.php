<?php
/**
 * Booking Settings admin page template.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Load current settings from options.
$settings = [
	'reminder_hours_before' => get_option( 'wbp_reminder_hours_before', 24 ),
	'self_cancellation_hours' => get_option( 'wbp_self_cancellation_hours', 12 ),
	'email_from_name'       => get_option( 'wbp_email_from_name', get_bloginfo( 'name' ) ),
	'email_from_address'    => get_option( 'wbp_email_from_address', get_bloginfo( 'admin_email' ) ),
];
$errors = [];

// Handle form submission.
if ( isset( $_POST['wbp_settings_nonce'] ) && wp_verify_nonce( sanitize_text_field( $_POST['wbp_settings_nonce'] ), 'wbp_save_settings' ) && current_user_can( 'manage_woocommerce' ) ) {
	update_option( 'wbp_reminder_hours_before', absint( $_POST['wbp_reminder_hours_before'] ) );
	update_option( 'wbp_self_cancellation_hours', absint( $_POST['wbp_self_cancellation_hours'] ) );
	update_option( 'wbp_email_from_name', sanitize_text_field( $_POST['wbp_email_from_name'] ) );
	update_option( 'wbp_email_from_address', sanitize_email( $_POST['wbp_email_from_address'] ) );
	$errors[] = __( 'Settings saved successfully.', 'wp-bookable-products' );
}

?>
<div class="wrap">
	<h1><?php esc_html_e( 'Booking Settings', 'wp-bookable-products' ); ?></h1>

	<?php if ( ! empty( $errors ) ) : ?>
		<div class="notice notice-success is-dismissible">
			<p><?php echo esc_html( implode( '<br>', $errors ) ); ?></p>
		</div>
	<?php endif; ?>

	<form method="post" action="">
		<?php wp_nonce_field( 'wbp_save_settings', 'wbp_settings_nonce' ); ?>

		<table class="form-table">
			<tr>
				<th scope="row"><label for="wbp_reminder_hours_before"><?php esc_html_e( 'Reminder Hours Before Booking', 'wp-bookable-products' ); ?></label></th>
				<td><input type="number" min="0" name="wbp_reminder_hours_before" id="wbp_reminder_hours_before" value="<?php echo esc_attr( $settings['reminder_hours_before'] ); ?>" class="small-text" /></td>
			</tr>
			<tr>
				<th scope="row"><label for="wbp_self_cancellation_hours"><?php esc_html_e( 'Self-Cancellation Hours Before Start', 'wp-bookable-products' ); ?></label></th>
				<td><input type="number" min="0" name="wbp_self_cancellation_hours" id="wbp_self_cancellation_hours" value="<?php echo esc_attr( $settings['self_cancellation_hours'] ); ?>" class="small-text" /></td>
			</tr>
			<tr>
				<th scope="row"><label for="wbp_email_from_name"><?php esc_html_e( 'Email From Name', 'wp-bookable-products' ); ?></label></th>
				<td><input type="text" name="wbp_email_from_name" id="wbp_email_from_name" value="<?php echo esc_attr( $settings['email_from_name'] ); ?>" /></td>
			</tr>
			<tr>
				<th scope="row"><label for="wbp_email_from_address"><?php esc_html_e( 'Email From Address', 'wp-bookable-products' ); ?></label></th>
				<td><input type="email" name="wbp_email_from_address" id="wbp_email_from_address" value="<?php echo esc_attr( $settings['email_from_address'] ); ?>" /></td>
			</tr>
		</table>

		<p class="submit">
			<input type="submit" class="button button-primary" value="<?php esc_attr_e( 'Save Settings', 'wp-bookable-products' ); ?>" />
		</p>
	</form>
</div>

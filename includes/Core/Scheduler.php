<?php
namespace WP_Bookable_Products\Core;

/**
 * Handles WordPress cron scheduling for reminders, overruns, etc.
 */
class Scheduler {

	private const CRON_EVENT_PREFIX = 'wbp_';
	private const SCHEDULED_EVENTS  = [
		'wbp_send_reminders' => [
			'hook'   => 'wbp_send_reminders',
			'cron'   => 'twicedaily',
			'desc'   => 'Send booking reminder emails.',
		],
		'wbp_expire_pending' => [
			'hook'   => 'wbp_expire_pending',
			'cron'   => 'hourly',
			'desc'   => 'Expire pending bookings past their grace period.',
		],
	];

	/**
	 * Initialize scheduled actions and cron events.
	 */
	public static function init(): void {
		foreach ( self::SCHEDULED_EVENTS as $schedule ) {
			if ( ! wp_next_scheduled( $schedule['hook'] ) ) {
				wp_schedule_event( time(), $schedule['cron'], $schedule['hook'] );
			}
		}

		add_action( 'wbp_send_reminders', [ __CLASS__, 'send_reminders' ] );
		add_action( 'wbp_expire_pending', [ __CLASS__, 'expire_pending_bookings' ] );
	}

	/**
	 * Deactivate — remove all scheduled events.
	 */
	public static function deactivate(): void {
		foreach ( self::SCHEDULED_EVENTS as $schedule ) {
			$timestamp = wp_next_scheduled( $schedule['hook'] );
			if ( false !== $timestamp ) {
				wp_unschedule_event( $timestamp, $schedule['hook'] );
			}
		}
	}

	/**
	 * Scheduled action: send booking reminders.
	 */
	public static function send_reminders(): void {
		// Hooked via Notification\Mailer class in Phase 6.
	}

	/**
	 * Scheduled action: expire pending bookings past grace period.
	 */
	public static function expire_pending_bookings(): void {
		// Will hook into BookingService::cancel() with system reason.
	}

	/**
	 * Schedule a one-time event.
	 *
	 * @param string $hook     Action hook.
	 * @param int    $timestamp Unix timestamp.
	 * @param array  $args     Arguments to pass.
	 * @return bool Success.
	 */
	public static function schedule_once( string $hook, int $timestamp, array $args = [] ): bool {
		return wp_schedule_single_event( $timestamp, $hook, $args );
	}

	/**
	 * Cancel a scheduled event.
	 *
	 * @param string $hook Hook name.
	 * @param array  $args Arguments.
	 */
	public static function cancel_event( string $hook, array $args = [] ): void {
		$timestamp = wp_next_scheduled( $hook, $args );
		if ( false !== $timestamp ) {
			wp_unschedule_event( $timestamp, $hook, $args );
		}
	}
}

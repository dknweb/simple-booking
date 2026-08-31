<?php
/**
 * Appointment business statuses.
 *
 * @package SimpleBooking
 */

namespace SimpleBooking;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Provides the canonical appointment status values.
 */
final class Appointment_Statuses {

	public const PENDING   = 'pending';
	public const CONFIRMED = 'confirmed';
	public const COMPLETED = 'completed';
	public const CANCELLED = 'cancelled';

	/**
	 * Return status labels.
	 *
	 * @return array<string, string>
	 */
	public static function get_labels(): array {
		return array(
			self::PENDING   => __( 'Pending', 'simple-booking' ),
			self::CONFIRMED => __( 'Confirmed', 'simple-booking' ),
			self::COMPLETED => __( 'Completed', 'simple-booking' ),
			self::CANCELLED => __( 'Cancelled', 'simple-booking' ),
		);
	}

	/**
	 * Normalize an unknown status to Pending.
	 *
	 * @param string $status Status value.
	 * @return string
	 */
	public static function normalize( string $status ): string {
		$labels = self::get_labels();
		return isset( $labels[ $status ] ) ? $status : self::PENDING;
	}

	/**
	 * Check whether a status blocks provider availability.
	 *
	 * @param string $status Status value.
	 * @return bool
	 */
	public static function blocks_availability( string $status ): bool {
		return self::CANCELLED !== self::normalize( $status );
	}
}

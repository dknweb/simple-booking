<?php
/**
 * Configurable presentation labels.
 *
 * @package SimpleBooking
 */

namespace SimpleBooking;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Provides generic labels that themes and integrations can customize.
 */
final class Labels {

	/**
	 * Return all presentation labels.
	 *
	 * @return array<string, string>
	 */
	public static function get_all(): array {
		$defaults = array(
			'appointment'         => __( 'Appointment', 'simple-booking' ),
			'appointments'        => __( 'Appointments', 'simple-booking' ),
			'service'             => __( 'Service', 'simple-booking' ),
			'services'            => __( 'Services', 'simple-booking' ),
			'provider'            => __( 'Provider', 'simple-booking' ),
			'providers'           => __( 'Providers', 'simple-booking' ),
			'customer'            => __( 'Customer', 'simple-booking' ),
			'business'            => __( 'Business', 'simple-booking' ),
			'book_appointment'    => __( 'Book an appointment', 'simple-booking' ),
			'customer_details'    => __( 'Customer details', 'simple-booking' ),
			'request_appointment' => __( 'Request appointment', 'simple-booking' ),
		);

		/**
		 * Filters public and admin presentation labels.
		 *
		 * Internal post types, metadata, hooks, and scheduling logic are unaffected.
		 *
		 * @param array<string, string> $defaults Generic labels.
		 */
		$labels = apply_filters( 'simple_booking_labels', $defaults );
		$labels = is_array( $labels ) ? array_intersect_key( $labels, $defaults ) : array();

		foreach ( $defaults as $key => $default ) {
			$value            = isset( $labels[ $key ] ) ? sanitize_text_field( (string) $labels[ $key ] ) : '';
			$defaults[ $key ] = '' !== $value ? $value : $default;
		}

		return $defaults;
	}

	/**
	 * Return one presentation label.
	 *
	 * @param string $key Label key.
	 * @return string
	 */
	public static function get( string $key ): string {
		$labels = self::get_all();
		return $labels[ $key ] ?? '';
	}
}

<?php
/**
 * Plugin activation handler.
 *
 * @package SimpleBooking
 */

namespace SimpleBooking;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Runs activation tasks.
 */
final class Activator {

	/**
	 * Register post types before refreshing rewrite rules.
	 *
	 * @return void
	 */
	public static function activate(): void {
		( new Post_Types() )->register();
		self::add_appointment_capabilities();
		add_option( Settings::OPTION_NAME, Settings::get_defaults(), '', 'no' );
		flush_rewrite_rules();
	}

	/**
	 * Restrict private customer records to administrators by default.
	 *
	 * @return void
	 */
	private static function add_appointment_capabilities(): void {
		$role = get_role( 'administrator' );

		if ( ! $role ) {
			return;
		}

		foreach ( self::get_appointment_capabilities() as $capability ) {
			$role->add_cap( $capability );
		}
	}

	/**
	 * Return the primitive capabilities used by appointments.
	 *
	 * @return string[]
	 */
	private static function get_appointment_capabilities(): array {
		return array(
			'edit_simple_appointment',
			'read_simple_appointment',
			'delete_simple_appointment',
			'edit_simple_appointments',
			'edit_others_simple_appointments',
			'publish_simple_appointments',
			'read_private_simple_appointments',
			'delete_simple_appointments',
			'delete_private_simple_appointments',
			'delete_published_simple_appointments',
			'delete_others_simple_appointments',
			'edit_private_simple_appointments',
			'edit_published_simple_appointments',
			'create_simple_appointments',
		);
	}
}

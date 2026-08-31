<?php
/**
 * Simple Booking uninstall routine.
 *
 * Data is retained unless SIMPLE_BOOKING_REMOVE_DATA is explicitly set to true.
 *
 * @package SimpleBooking
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

$simple_booking_administrator = get_role( 'administrator' );

if ( $simple_booking_administrator ) {
	$simple_booking_capabilities = array(
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

	foreach ( $simple_booking_capabilities as $simple_booking_capability ) {
		$simple_booking_administrator->remove_cap( $simple_booking_capability );
	}
}

if ( ! defined( 'SIMPLE_BOOKING_REMOVE_DATA' ) || true !== SIMPLE_BOOKING_REMOVE_DATA ) {
	return;
}

$simple_booking_post_types = array(
	'simple_appointment',
	'simple_service',
	'simple_provider',
);

foreach ( $simple_booking_post_types as $simple_booking_post_type ) {
	$simple_booking_post_ids = get_posts(
		array(
			'post_type'      => $simple_booking_post_type,
			'post_status'    => 'any',
			'posts_per_page' => -1,
			'fields'         => 'ids',
		)
	);

	foreach ( $simple_booking_post_ids as $simple_booking_post_id ) {
		wp_delete_post( $simple_booking_post_id, true );
	}
}

delete_option( 'simple_booking_settings' );

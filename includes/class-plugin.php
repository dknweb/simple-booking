<?php
/**
 * Main plugin coordinator.
 *
 * @package SimpleBooking
 */

namespace SimpleBooking;

use SimpleBooking\Admin\Admin;
use SimpleBooking\Frontend\Frontend;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers the plugin's WordPress hooks.
 */
final class Plugin {

	/**
	 * Start the plugin.
	 *
	 * @return void
	 */
	public function run(): void {
		$post_types   = new Post_Types();
		$availability = new Availability();
		$assets       = new Assets();
		$booking      = new Booking( $availability );
		$email        = new Email();
		$frontend     = new Frontend( $assets, $availability );

		add_action( 'init', array( $post_types, 'register' ) );
		add_action( 'init', array( $this, 'load_textdomain' ) );
		$assets->register_hooks();
		$availability->register_hooks();
		$email->register_hooks();
		$booking->register_hooks();
		$frontend->register_hooks();

		if ( is_admin() ) {
			( new Admin( $availability ) )->register_hooks();
			( new Settings() )->register_hooks();
		}
	}

	/**
	 * Load plugin translations.
	 *
	 * @return void
	 */
	public function load_textdomain(): void {
		load_plugin_textdomain(
			'simple-booking',
			false,
			dirname( plugin_basename( SIMPLE_BOOKING_FILE ) ) . '/languages'
		);
	}
}

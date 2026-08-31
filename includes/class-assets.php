<?php
/**
 * Plugin asset management.
 *
 * @package SimpleBooking
 */

namespace SimpleBooking;

use WP_Post;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers and conditionally loads frontend assets.
 */
final class Assets {

	/**
	 * Register WordPress hooks.
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		add_action( 'wp_enqueue_scripts', array( $this, 'register_assets' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_for_current_post' ), 20 );
	}

	/**
	 * Register frontend asset handles.
	 *
	 * @return void
	 */
	public function register_assets(): void {
		wp_register_style(
			'simple-booking',
			SIMPLE_BOOKING_URL . 'assets/css/simple-booking.css',
			array(),
			SIMPLE_BOOKING_VERSION
		);

		wp_register_script(
			'simple-booking',
			SIMPLE_BOOKING_URL . 'assets/js/simple-booking.js',
			array(),
			SIMPLE_BOOKING_VERSION,
			true
		);
	}

	/**
	 * Load assets on ordinary posts that contain the shortcode.
	 *
	 * @return void
	 */
	public function enqueue_for_current_post(): void {
		global $post;

		if ( $post instanceof WP_Post && has_shortcode( $post->post_content, 'simple_booking' ) ) {
			$this->enqueue_frontend();
		}
	}

	/**
	 * Enqueue the booking interface assets.
	 *
	 * This is also called while rendering the shortcode so shortcodes inserted
	 * outside normal post content still receive their assets.
	 *
	 * @return void
	 */
	public function enqueue_frontend(): void {
		if ( ! wp_style_is( 'simple-booking', 'registered' ) || ! wp_script_is( 'simple-booking', 'registered' ) ) {
			$this->register_assets();
		}

		wp_localize_script(
			'simple-booking',
			'simpleBookingConfig',
			array(
				'ajaxUrl'          => admin_url( 'admin-ajax.php' ),
				'action'           => Availability::AJAX_ACTION,
				'nonce'            => wp_create_nonce( Availability::NONCE_ACTION ),
				'chooseTime'       => __( 'Choose an available time', 'simple-booking' ),
				'loadingTimes'     => __( 'Loading available times…', 'simple-booking' ),
				'noTimes'          => __( 'No appointment times are available on this date.', 'simple-booking' ),
				'loadError'        => __( 'Available times could not be loaded. Please try again.', 'simple-booking' ),
				'selectFirst'      => __( 'Choose a service, provider, and date first.', 'simple-booking' ),
			)
		);

		wp_enqueue_style( 'simple-booking' );
		wp_enqueue_script( 'simple-booking' );
	}
}

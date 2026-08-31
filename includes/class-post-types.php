<?php
/**
 * Custom post type registration.
 *
 * @package SimpleBooking
 */

namespace SimpleBooking;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers Simple Booking content types.
 */
final class Post_Types {

	public const APPOINTMENT = 'simple_appointment';
	public const SERVICE     = 'simple_service';
	public const PROVIDER    = 'simple_provider';

	/**
	 * Register all plugin post types.
	 *
	 * @return void
	 */
	public function register(): void {
		$this->register_appointment();
		$this->register_service();
		$this->register_provider();
	}

	/**
	 * Register private appointments.
	 *
	 * @return void
	 */
	private function register_appointment(): void {
		$labels              = $this->get_labels( Labels::get( 'appointment' ), Labels::get( 'appointments' ) );
		$labels['menu_name'] = __( 'Simple Booking', 'simple-booking' );

		register_post_type(
			self::APPOINTMENT,
			array(
				'labels'              => $labels,
				'description'         => __( 'Private appointment records.', 'simple-booking' ),
				'public'              => false,
				'publicly_queryable'  => false,
				'exclude_from_search' => true,
				'show_ui'             => true,
				'show_in_menu'        => true,
				'show_in_admin_bar'   => false,
				'show_in_rest'        => false,
				'menu_icon'           => 'dashicons-calendar-alt',
				'menu_position'       => 26,
				'supports'            => array( 'title' ),
				'has_archive'         => false,
				'rewrite'             => false,
				'query_var'           => false,
				'capability_type'     => array( 'simple_appointment', 'simple_appointments' ),
				'map_meta_cap'        => true,
			)
		);
	}

	/**
	 * Register services managed by business staff.
	 *
	 * @return void
	 */
	private function register_service(): void {
		register_post_type(
			self::SERVICE,
			array(
				'labels'              => $this->get_labels( Labels::get( 'service' ), Labels::get( 'services' ) ),
				'description'         => __( 'Services available for appointments.', 'simple-booking' ),
				'public'              => false,
				'publicly_queryable'  => false,
				'exclude_from_search' => true,
				'show_ui'             => true,
				'show_in_menu'        => 'edit.php?post_type=' . self::APPOINTMENT,
				'show_in_admin_bar'   => false,
				'show_in_rest'        => false,
				'supports'            => array( 'title', 'editor' ),
				'has_archive'         => false,
				'rewrite'             => false,
				'query_var'           => false,
				'map_meta_cap'        => true,
			)
		);
	}

	/**
	 * Register providers and other appointment providers.
	 *
	 * @return void
	 */
	private function register_provider(): void {
		register_post_type(
			self::PROVIDER,
			array(
				'labels'              => $this->get_labels( Labels::get( 'provider' ), Labels::get( 'providers' ) ),
				'description'         => __( 'Providers and other appointment providers.', 'simple-booking' ),
				'public'              => false,
				'publicly_queryable'  => false,
				'exclude_from_search' => true,
				'show_ui'             => true,
				'show_in_menu'        => 'edit.php?post_type=' . self::APPOINTMENT,
				'show_in_admin_bar'   => false,
				'show_in_rest'        => false,
				'supports'            => array( 'title', 'editor', 'thumbnail' ),
				'has_archive'         => false,
				'rewrite'             => false,
				'query_var'           => false,
				'map_meta_cap'        => true,
			)
		);
	}

	/**
	 * Build standard post type labels.
	 *
	 * @param string $singular Singular label.
	 * @param string $plural   Plural label.
	 * @return array<string, string>
	 */
	private function get_labels( string $singular, string $plural ): array {
		return array(
			'name'                  => $plural,
			'singular_name'         => $singular,
			'menu_name'             => $plural,
			'name_admin_bar'        => $singular,
			'add_new'               => __( 'Add New', 'simple-booking' ),
			'add_new_item'          => sprintf( __( 'Add New %s', 'simple-booking' ), $singular ),
			'new_item'              => sprintf( __( 'New %s', 'simple-booking' ), $singular ),
			'edit_item'             => sprintf( __( 'Edit %s', 'simple-booking' ), $singular ),
			'view_item'             => sprintf( __( 'View %s', 'simple-booking' ), $singular ),
			'all_items'             => sprintf( __( 'All %s', 'simple-booking' ), $plural ),
			'search_items'          => sprintf( __( 'Search %s', 'simple-booking' ), $plural ),
			'not_found'             => sprintf( __( 'No %s found.', 'simple-booking' ), strtolower( $plural ) ),
			'not_found_in_trash'    => sprintf( __( 'No %s found in Trash.', 'simple-booking' ), strtolower( $plural ) ),
			'featured_image'        => __( 'Profile image', 'simple-booking' ),
			'set_featured_image'    => __( 'Set profile image', 'simple-booking' ),
			'remove_featured_image' => __( 'Remove profile image', 'simple-booking' ),
			'use_featured_image'    => __( 'Use as profile image', 'simple-booking' ),
		);
	}
}

<?php
/**
 * Frontend booking interface.
 *
 * @package SimpleBooking
 */

namespace SimpleBooking\Frontend;

use SimpleBooking\Assets;
use SimpleBooking\Availability;
use SimpleBooking\Booking;
use SimpleBooking\Labels;
use SimpleBooking\Post_Types;
use WP_Post;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers and renders the booking shortcode.
 */
final class Frontend {
	private int $instance = 0;

	/**
	 * Frontend constructor.
	 *
	 * @param Assets       $assets       Asset manager.
	 * @param Availability $availability Availability engine.
	 */
	public function __construct( private Assets $assets, private Availability $availability ) {}

	/**
	 * Register frontend hooks.
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		add_action( 'init', array( $this, 'register_shortcode' ) );
	}

	/**
	 * Register the booking shortcode.
	 *
	 * @return void
	 */
	public function register_shortcode(): void {
		add_shortcode( 'simple_booking', array( $this, 'render_shortcode' ) );
	}

	/**
	 * Render the booking form.
	 *
	 * @return string
	 */
	public function render_shortcode(): string {
		$this->assets->enqueue_frontend();
		++$this->instance;

		$providers         = $this->get_active_posts( Post_Types::PROVIDER );
		$services          = $this->filter_bookable_services( $this->get_active_posts( Post_Types::SERVICE ), $providers );
		$provider_services = $this->get_provider_services( $services );
		$providers         = array_values(
			array_filter(
				$providers,
				static fn( WP_Post $provider ): bool => ! empty( $provider_services[ $provider->ID ] )
			)
		);
		$messages          = $this->get_messages();
		$invalid_fields    = $this->get_invalid_fields();
		$minimum_date      = $this->availability->get_minimum_date();
		$maximum_date      = $this->availability->get_maximum_date();
		$return_url        = $this->get_return_url();
		$consent_required  = (bool) apply_filters( 'simple_booking_consent_required', true );
		$privacy_url         = get_privacy_policy_url();
		$presentation_labels = Labels::get_all();
		$id_suffix         = 1 === $this->instance ? '' : '-' . $this->instance;
		$form_id           = 'simple-booking-form' . $id_suffix;
		$field_ids         = array();

		foreach ( array( 'errors', 'service', 'provider', 'date', 'time', 'time-status', 'first-name', 'last-name', 'email', 'phone', 'notes', 'consent' ) as $field_key ) {
			$field_ids[ $field_key ] = 'simple-booking-' . $field_key . $id_suffix;
		}

		ob_start();
		require SIMPLE_BOOKING_PATH . 'public/views/booking-form.php';
		return (string) ob_get_clean();
	}

	/**
	 * Get active, published service or provider posts.
	 *
	 * Missing active metadata is treated as active for Phase 1 compatibility.
	 *
	 * @param string $post_type Post type.
	 * @return WP_Post[]
	 */
	private function get_active_posts( string $post_type ): array {
		return get_posts(
			array(
				'post_type'      => $post_type,
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'orderby'        => 'title',
				'order'          => 'ASC',
				'meta_query'     => array(
					'relation' => 'OR',
					array(
						'key'     => '_simple_booking_active',
						'value'   => '1',
						'compare' => '=',
					),
					array(
						'key'     => '_simple_booking_active',
						'compare' => 'NOT EXISTS',
					),
				),
			)
		);
	}

	/**
	 * Map each provider to services that assign them.
	 *
	 * @param WP_Post[] $services Active services.
	 * @return array<int, int[]>
	 */
	private function get_provider_services( array $services ): array {
		$map = array();

		foreach ( $services as $service ) {
			$provider_ids = array_map( 'absint', (array) get_post_meta( $service->ID, '_simple_booking_provider_ids', true ) );

			foreach ( $provider_ids as $provider_id ) {
				$map[ $provider_id ]   ??= array();
				$map[ $provider_id ][] = $service->ID;
			}
		}

		return $map;
	}

	/**
	 * Remove services that have no active, published assigned provider.
	 *
	 * @param WP_Post[] $services  Active services.
	 * @param WP_Post[] $providers Active providers.
	 * @return WP_Post[]
	 */
	private function filter_bookable_services( array $services, array $providers ): array {
		$active_provider_ids = array_map( 'absint', wp_list_pluck( $providers, 'ID' ) );

		return array_values(
			array_filter(
				$services,
				static function ( WP_Post $service ) use ( $active_provider_ids ): bool {
					$assigned_ids = array_map( 'absint', (array) get_post_meta( $service->ID, '_simple_booking_provider_ids', true ) );
					return (bool) array_intersect( $assigned_ids, $active_provider_ids );
				}
			)
		);
	}

	/**
	 * Read safe result codes from the URL and map them to messages.
	 *
	 * @return array<int, array{type:string, text:string, field:string}>
	 */
	private function get_messages(): array {
		if ( isset( $_GET['simple_booking_result'] ) && 'success' === sanitize_key( wp_unslash( $_GET['simple_booking_result'] ) ) ) {
			return array(
				array(
					'type'  => 'success',
					'text'  => __( 'Your appointment request was submitted and is pending confirmation.', 'simple-booking' ),
					'field' => '',
				),
			);
		}

		$codes = isset( $_GET['simple_booking_errors'] ) ? explode( ',', sanitize_text_field( wp_unslash( $_GET['simple_booking_errors'] ) ) ) : array();
		$map   = $this->get_error_message_map();
		$items = array();

		foreach ( array_unique( array_map( 'sanitize_key', $codes ) ) as $code ) {
			if ( isset( $map[ $code ] ) ) {
				$items[] = array(
					'type'  => 'error',
					'text'  => $map[ $code ]['text'],
					'field' => $map[ $code ]['field'],
				);
			}
		}

		return $items;
	}

	/**
	 * Return field IDs with current validation errors.
	 *
	 * @return string[]
	 */
	private function get_invalid_fields(): array {
		$codes = isset( $_GET['simple_booking_errors'] ) ? explode( ',', sanitize_text_field( wp_unslash( $_GET['simple_booking_errors'] ) ) ) : array();
		$map   = $this->get_error_message_map();
		$ids   = array();

		foreach ( array_map( 'sanitize_key', $codes ) as $code ) {
			if ( ! empty( $map[ $code ]['field'] ) ) {
				$ids[] = $map[ $code ]['field'];
			}
		}

		return array_values( array_unique( $ids ) );
	}

	/**
	 * Return validation messages and their related field IDs.
	 *
	 * @return array<string, array{text:string, field:string}>
	 */
	private function get_error_message_map(): array {
		return array(
			'invalid_request'  => array( 'text' => __( 'Your form session expired. Please try again.', 'simple-booking' ), 'field' => '' ),
			'required_customer' => array( 'text' => __( 'Enter your first name, last name, and phone number.', 'simple-booking' ), 'field' => 'simple-booking-first-name' ),
			'first_name'       => array( 'text' => __( 'Enter your first name.', 'simple-booking' ), 'field' => 'simple-booking-first-name' ),
			'last_name'        => array( 'text' => __( 'Enter your last name.', 'simple-booking' ), 'field' => 'simple-booking-last-name' ),
			'customer_phone'    => array( 'text' => __( 'Enter your phone number.', 'simple-booking' ), 'field' => 'simple-booking-phone' ),
			'field_length'     => array( 'text' => __( 'One or more fields are longer than allowed.', 'simple-booking' ), 'field' => 'simple-booking-first-name' ),
			'email'            => array( 'text' => __( 'Enter a valid email address.', 'simple-booking' ), 'field' => 'simple-booking-email' ),
			'service'          => array( 'text' => __( 'Choose an available service.', 'simple-booking' ), 'field' => 'simple-booking-service' ),
			'provider'         => array( 'text' => __( 'Choose an available provider.', 'simple-booking' ), 'field' => 'simple-booking-provider' ),
			'assignment'       => array( 'text' => __( 'The selected provider is not assigned to that service.', 'simple-booking' ), 'field' => 'simple-booking-provider' ),
			'date'             => array( 'text' => __( 'Choose a valid appointment date.', 'simple-booking' ), 'field' => 'simple-booking-date' ),
			'time'             => array( 'text' => __( 'Choose a valid appointment time.', 'simple-booking' ), 'field' => 'simple-booking-time' ),
			'past'             => array( 'text' => __( 'Choose an appointment time in the future.', 'simple-booking' ), 'field' => 'simple-booking-date' ),
			'minimum_notice'   => array( 'text' => __( 'The selected time does not meet the business’s minimum booking notice.', 'simple-booking' ), 'field' => 'simple-booking-time' ),
			'advance_limit'    => array( 'text' => __( 'Choose a date within the business’s advance-booking limit.', 'simple-booking' ), 'field' => 'simple-booking-date' ),
			'closed'           => array( 'text' => __( 'The business is closed or the provider is not working on the selected date.', 'simple-booking' ), 'field' => 'simple-booking-date' ),
			'unavailable'      => array( 'text' => __( 'That appointment time is no longer available. Choose another time.', 'simple-booking' ), 'field' => 'simple-booking-time' ),
			'booking_busy'     => array( 'text' => __( 'The booking schedule is busy. Please try again.', 'simple-booking' ), 'field' => 'simple-booking-time' ),
			'consent'          => array( 'text' => __( 'Consent is required to process the appointment request.', 'simple-booking' ), 'field' => 'simple-booking-consent' ),
			'create_failed'    => array( 'text' => __( 'The appointment could not be created. Please try again.', 'simple-booking' ), 'field' => '' ),
		);
	}

	/**
	 * Build a clean return URL for the current page.
	 *
	 * @return string
	 */
	private function get_return_url(): string {
		$url = get_permalink();

		if ( ! is_string( $url ) || '' === $url ) {
			$url = home_url( '/' );
		}

		return remove_query_arg( array( 'simple_booking_result', 'simple_booking_errors' ), $url );
	}
}

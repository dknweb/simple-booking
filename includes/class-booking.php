<?php
/**
 * Frontend booking submission handling.
 *
 * @package SimpleBooking
 */

namespace SimpleBooking;

use DateTimeImmutable;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Validates public submissions and creates private appointments.
 */
final class Booking {

	private const NONCE_ACTION = 'simple_booking_submit_appointment';
	private const NONCE_FIELD  = 'simple_booking_nonce';

	/** @var array<string, int|string> */
	private array $created_booking_data = array();

	/**
	 * Booking constructor.
	 *
	 * @param Availability $availability Availability engine.
	 */
	public function __construct( private Availability $availability ) {}

	/**
	 * Register public and authenticated submission handlers.
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		add_action( 'admin_post_nopriv_simple_booking_submit', array( $this, 'handle_submission' ) );
		add_action( 'admin_post_simple_booking_submit', array( $this, 'handle_submission' ) );
	}

	/**
	 * Process a booking form submission.
	 *
	 * @return void
	 */
	public function handle_submission(): void {
		$return_url = $this->get_return_url();
		$method     = isset( $_SERVER['REQUEST_METHOD'] ) ? sanitize_key( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) : '';

		if ( 'post' !== $method ) {
			$this->redirect_with_errors( $return_url, array( 'invalid_request' ) );
		}

		$nonce = isset( $_POST[ self::NONCE_FIELD ] ) ? sanitize_text_field( wp_unslash( $_POST[ self::NONCE_FIELD ] ) ) : '';

		if ( ! wp_verify_nonce( $nonce, self::NONCE_ACTION ) ) {
			$this->redirect_with_errors( $return_url, array( 'invalid_request' ) );
		}

		$data   = $this->sanitize_submission();
		$errors = $this->validate_submission( $data );

		if ( $errors ) {
			$this->redirect_with_errors( $return_url, $errors );
		}

		$lock_name = $this->availability->acquire_lock( (int) $data['provider_id'], (string) $data['date'] );

		if ( '' === $lock_name ) {
			$this->redirect_with_errors( $return_url, array( 'booking_busy' ) );
		}

		$schedule_error = '';
		$appointment_id = new WP_Error( 'simple_booking_not_created' );

		try {
			$schedule_error = $this->availability->validate_slot(
				(int) $data['service_id'],
				(int) $data['provider_id'],
				(string) $data['date'],
				(string) $data['time']
			);

			if ( '' === $schedule_error ) {
				$appointment_id = $this->create_appointment( $data );
			}
		} finally {
			$this->availability->release_lock( $lock_name );
		}

		if ( '' !== $schedule_error ) {
			$this->redirect_with_errors( $return_url, array( $schedule_error ) );
		}

		if ( is_wp_error( $appointment_id ) ) {
			$this->redirect_with_errors( $return_url, array( 'create_failed' ) );
		}

		/**
		 * Fires after an appointment is created successfully and its schedule lock is released.
		 *
		 * @param int                       $appointment_id New appointment ID.
		 * @param array<string, int|string> $booking_data  Sanitized booking data.
		 */
		do_action( 'simple_booking_after_create_appointment', $appointment_id, $this->created_booking_data );

		$this->redirect( add_query_arg( 'simple_booking_result', 'success', $return_url ) );
	}

	/**
	 * Return the nonce action used by the form.
	 *
	 * @return string
	 */
	public static function get_nonce_action(): string {
		return self::NONCE_ACTION;
	}

	/**
	 * Return the nonce field used by the form.
	 *
	 * @return string
	 */
	public static function get_nonce_field(): string {
		return self::NONCE_FIELD;
	}

	/**
	 * Sanitize untrusted form values.
	 *
	 * @return array<string, int|string>
	 */
	private function sanitize_submission(): array {
		return array(
			'first_name'  => isset( $_POST['simple_booking_first_name'] ) ? sanitize_text_field( wp_unslash( $_POST['simple_booking_first_name'] ) ) : '',
			'last_name'   => isset( $_POST['simple_booking_last_name'] ) ? sanitize_text_field( wp_unslash( $_POST['simple_booking_last_name'] ) ) : '',
			'email'       => isset( $_POST['simple_booking_email'] ) ? sanitize_email( wp_unslash( $_POST['simple_booking_email'] ) ) : '',
			'phone'       => isset( $_POST['simple_booking_phone'] ) ? sanitize_text_field( wp_unslash( $_POST['simple_booking_phone'] ) ) : '',
			'notes'       => isset( $_POST['simple_booking_notes'] ) ? sanitize_textarea_field( wp_unslash( $_POST['simple_booking_notes'] ) ) : '',
			'service_id'  => isset( $_POST['simple_booking_service_id'] ) ? absint( wp_unslash( $_POST['simple_booking_service_id'] ) ) : 0,
			'provider_id' => isset( $_POST['simple_booking_provider_id'] ) ? absint( wp_unslash( $_POST['simple_booking_provider_id'] ) ) : 0,
			'date'        => isset( $_POST['simple_booking_date'] ) ? sanitize_text_field( wp_unslash( $_POST['simple_booking_date'] ) ) : '',
			'time'        => isset( $_POST['simple_booking_time'] ) ? sanitize_text_field( wp_unslash( $_POST['simple_booking_time'] ) ) : '',
			'consent'     => isset( $_POST['simple_booking_consent'] ) ? 1 : 0,
		);
	}

	/**
	 * Validate customer, relationship, and schedule fields.
	 *
	 * @param array<string, int|string> $data Sanitized booking values.
	 * @return string[]
	 */
	private function validate_submission( array $data ): array {
		$errors = array();

		if ( '' === $data['first_name'] ) {
			$errors[] = 'first_name';
		}

		if ( '' === $data['last_name'] ) {
			$errors[] = 'last_name';
		}

		if ( '' === $data['phone'] ) {
			$errors[] = 'customer_phone';
		}

		if (
			strlen( (string) $data['first_name'] ) > 100
			|| strlen( (string) $data['last_name'] ) > 100
			|| strlen( (string) $data['email'] ) > 254
			|| strlen( (string) $data['phone'] ) > 50
			|| strlen( (string) $data['notes'] ) > 2000
		) {
			$errors[] = 'field_length';
		}

		if ( ! is_email( (string) $data['email'] ) ) {
			$errors[] = 'email';
		}

		if ( ! $this->is_active_post( (int) $data['service_id'], Post_Types::SERVICE ) ) {
			$errors[] = 'service';
		}

		if ( ! $this->is_active_post( (int) $data['provider_id'], Post_Types::PROVIDER ) ) {
			$errors[] = 'provider';
		}

		if ( ! in_array( 'service', $errors, true ) && ! in_array( 'provider', $errors, true ) ) {
			if ( ! $this->availability->is_provider_assigned( (int) $data['service_id'], (int) $data['provider_id'] ) ) {
				$errors[] = 'assignment';
			}
		}

		$appointment = $this->create_datetime( (string) $data['date'], (string) $data['time'] );

		if ( ! $appointment ) {
			if ( ! $this->is_valid_date( (string) $data['date'] ) ) {
				$errors[] = 'date';
			}

			if ( ! $this->is_valid_time( (string) $data['time'] ) ) {
				$errors[] = 'time';
			}
		} elseif ( ! array_intersect( array( 'service', 'provider', 'assignment' ), $errors ) ) {
			$schedule_error = $this->availability->validate_slot(
				(int) $data['service_id'],
				(int) $data['provider_id'],
				(string) $data['date'],
				(string) $data['time']
			);

			if ( '' !== $schedule_error ) {
				$errors[] = $schedule_error;
			}
		}

		$consent_required = (bool) apply_filters( 'simple_booking_consent_required', true );

		if ( $consent_required && 1 !== $data['consent'] ) {
			$errors[] = 'consent';
		}

		return array_values( array_unique( $errors ) );
	}

	/**
	 * Create a private Pending appointment.
	 *
	 * @param array<string, int|string> $data Validated booking values.
	 * @return int|WP_Error
	 */
	private function create_appointment( array $data ): int|WP_Error {
		$appointment = $this->create_datetime( (string) $data['date'], (string) $data['time'] );

		if ( ! $appointment ) {
			return new WP_Error( 'simple_booking_invalid_datetime' );
		}

		$end_time = $this->availability->calculate_end_time( (int) $data['service_id'], $appointment );
		$buffer   = min( 1440, absint( get_post_meta( (int) $data['service_id'], '_simple_booking_buffer_time', true ) ) );
		$name     = trim( (string) $data['first_name'] . ' ' . (string) $data['last_name'] );

		$booking_data = array(
			'customer_first_name' => (string) $data['first_name'],
			'customer_last_name'  => (string) $data['last_name'],
			'customer_email'      => (string) $data['email'],
			'customer_phone'      => (string) $data['phone'],
			'customer_notes'      => (string) $data['notes'],
			'service_id'         => (int) $data['service_id'],
			'provider_id'        => (int) $data['provider_id'],
			'appointment_date'   => (string) $data['date'],
			'start_time'         => (string) $data['time'],
			'end_time'           => $end_time,
			'buffer_time'        => $buffer,
			'status'             => Appointment_Statuses::PENDING,
			'consent'            => (int) $data['consent'],
		);

		/**
		 * Fires immediately before an appointment is created.
		 *
		 * @param array<string, int|string> $booking_data Sanitized booking data.
		 */
		do_action( 'simple_booking_before_create_appointment', $booking_data );

		$appointment_id = wp_insert_post(
			array(
				'post_type'   => Post_Types::APPOINTMENT,
				'post_status' => 'private',
				'post_title'  => sprintf(
					/* translators: 1: customer name, 2: appointment date, 3: appointment time. */
					__( 'Appointment: %1$s — %2$s %3$s', 'simple-booking' ),
					$name,
					(string) $data['date'],
					(string) $data['time']
				),
				'meta_input'  => array(
					'_simple_booking_customer_first_name' => $booking_data['customer_first_name'],
					'_simple_booking_customer_last_name'  => $booking_data['customer_last_name'],
					'_simple_booking_customer_email'      => $booking_data['customer_email'],
					'_simple_booking_customer_phone'      => $booking_data['customer_phone'],
					'_simple_booking_customer_notes'      => $booking_data['customer_notes'],
					'_simple_booking_internal_notes'     => '',
					'_simple_booking_service_id'         => $booking_data['service_id'],
					'_simple_booking_provider_id'        => $booking_data['provider_id'],
					'_simple_booking_appointment_date'   => $booking_data['appointment_date'],
					'_simple_booking_start_time'         => $booking_data['start_time'],
					'_simple_booking_end_time'           => $booking_data['end_time'],
					'_simple_booking_buffer_time'         => $booking_data['buffer_time'],
					'_simple_booking_status'             => $booking_data['status'],
					'_simple_booking_consent'            => $booking_data['consent'],
				),
			),
			true
		);

		if ( is_wp_error( $appointment_id ) ) {
			return $appointment_id;
		}

		$this->created_booking_data = $booking_data;

		return $appointment_id;
	}

	/**
	 * Check that a related post exists, is published, and is active.
	 *
	 * @param int    $post_id   Related post ID.
	 * @param string $post_type Expected post type.
	 * @return bool
	 */
	private function is_active_post( int $post_id, string $post_type ): bool {
		if ( $post_id < 1 || $post_type !== get_post_type( $post_id ) || 'publish' !== get_post_status( $post_id ) ) {
			return false;
		}

		return '0' !== get_post_meta( $post_id, '_simple_booking_active', true );
	}

	/**
	 * Build an appointment date in the WordPress site timezone.
	 *
	 * @param string $date ISO date.
	 * @param string $time 24-hour time.
	 * @return DateTimeImmutable|null
	 */
	private function create_datetime( string $date, string $time ): ?DateTimeImmutable {
		if ( ! $this->is_valid_date( $date ) || ! $this->is_valid_time( $time ) ) {
			return null;
		}

		$appointment = DateTimeImmutable::createFromFormat( '!Y-m-d H:i', $date . ' ' . $time, wp_timezone() );

		if ( false === $appointment || $appointment->format( 'Y-m-d H:i' ) !== $date . ' ' . $time ) {
			return null;
		}

		return $appointment;
	}

	/**
	 * Validate an ISO calendar date.
	 *
	 * @param string $date Date string.
	 * @return bool
	 */
	private function is_valid_date( string $date ): bool {
		$parsed = DateTimeImmutable::createFromFormat( '!Y-m-d', $date, wp_timezone() );
		return false !== $parsed && $parsed->format( 'Y-m-d' ) === $date;
	}

	/**
	 * Validate a 24-hour time.
	 *
	 * @param string $time Time string.
	 * @return bool
	 */
	private function is_valid_time( string $time ): bool {
		return 1 === preg_match( '/^(?:[01]\d|2[0-3]):[0-5]\d$/', $time );
	}

	/**
	 * Return a same-site URL supplied by the form.
	 *
	 * @return string
	 */
	private function get_return_url(): string {
		$submitted = isset( $_POST['simple_booking_return_url'] ) ? esc_url_raw( wp_unslash( $_POST['simple_booking_return_url'] ) ) : '';
		$fallback  = home_url( '/' );

		return wp_validate_redirect( $submitted, $fallback );
	}

	/**
	 * Redirect to the form with validation error codes.
	 *
	 * @param string   $return_url Return page.
	 * @param string[] $errors     Error codes.
	 * @return void
	 */
	private function redirect_with_errors( string $return_url, array $errors ): void {
		$url = add_query_arg(
			'simple_booking_errors',
			implode( ',', array_map( 'sanitize_key', array_unique( $errors ) ) ),
			$return_url
		);

		$this->redirect( $url );
	}

	/**
	 * Perform a safe post/redirect/get response.
	 *
	 * @param string $url Destination URL.
	 * @return void
	 */
	private function redirect( string $url ): void {
		wp_safe_redirect( $url . '#simple-booking-form', 303 );
		exit;
	}
}

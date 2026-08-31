<?php
/**
 * Dynamic appointment availability.
 *
 * @package SimpleBooking
 */

namespace SimpleBooking;

use DateInterval;
use DateTimeImmutable;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Generates time slots and validates schedule rules.
 */
final class Availability {

	public const AJAX_ACTION = 'simple_booking_availability';
	public const NONCE_ACTION = 'simple_booking_get_availability';

	/**
	 * Register public availability endpoints.
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		add_action( 'wp_ajax_' . self::AJAX_ACTION, array( $this, 'handle_ajax' ) );
		add_action( 'wp_ajax_nopriv_' . self::AJAX_ACTION, array( $this, 'handle_ajax' ) );
	}

	/**
	 * Return available times as structured JSON.
	 *
	 * @return void
	 */
	public function handle_ajax(): void {
		check_ajax_referer( self::NONCE_ACTION, 'nonce' );
		$method = isset( $_SERVER['REQUEST_METHOD'] ) ? sanitize_key( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) : '';

		if ( 'post' !== $method ) {
			wp_send_json_error( array( 'message' => __( 'Invalid availability request.', 'simple-booking' ) ), 405 );
		}

		$service_id  = isset( $_POST['service_id'] ) ? absint( wp_unslash( $_POST['service_id'] ) ) : 0;
		$provider_id = isset( $_POST['provider_id'] ) ? absint( wp_unslash( $_POST['provider_id'] ) ) : 0;
		$date        = isset( $_POST['date'] ) ? sanitize_text_field( wp_unslash( $_POST['date'] ) ) : '';

		if ( ! $this->is_active_post( $service_id, Post_Types::SERVICE ) || ! $this->is_active_post( $provider_id, Post_Types::PROVIDER ) || ! $this->is_provider_assigned( $service_id, $provider_id ) || ! $this->is_valid_date( $date ) ) {
			wp_send_json_error( array( 'message' => __( 'Choose a valid service, provider, and date.', 'simple-booking' ) ), 400 );
		}

		$slots = array_map(
			fn( string $time ): array => array(
				'value' => $time,
				'label' => $this->format_time( $date, $time ),
			),
			$this->get_available_slots( $service_id, $provider_id, $date )
		);

		wp_send_json_success(
			array(
				'slots'   => $slots,
				'message' => $slots ? '' : __( 'No appointment times are available on this date.', 'simple-booking' ),
			)
		);
	}

	/**
	 * Generate available times for a service, provider, and date.
	 *
	 * @param int    $service_id  Service ID.
	 * @param int    $provider_id Provider ID.
	 * @param string $date        ISO date.
	 * @param int    $exclude_appointment_id Appointment ID to ignore while rescheduling.
	 * @return string[]
	 */
	public function get_available_slots( int $service_id, int $provider_id, string $date, int $exclude_appointment_id = 0 ): array {
		$bounds = $this->get_day_bounds( $date, $provider_id );

		if ( ! $bounds || ! $this->is_within_booking_window( $date ) ) {
			return array();
		}

		$settings       = Settings::get_settings();
		$interval       = max( 5, absint( $settings['slot_interval'] ) );
		$duration       = $this->get_service_duration( $service_id );
		$buffer         = $this->get_service_buffer( $service_id );
		$notice_cutoff  = current_datetime()->add( new DateInterval( 'PT' . absint( $settings['minimum_notice'] ) . 'M' ) );
		$existing       = $this->get_existing_intervals( $provider_id, $date, $exclude_appointment_id );
		$break          = $this->get_break_bounds( $date, $provider_id );
		$slots          = array();
		$candidate      = $bounds['open'];

		while ( $candidate < $bounds['close'] ) {
			$appointment_end = $candidate->add( new DateInterval( 'PT' . $duration . 'M' ) );
			$blocked_end     = $appointment_end->add( new DateInterval( 'PT' . $buffer . 'M' ) );

			if (
				$candidate >= $notice_cutoff
				&& $blocked_end <= $bounds['close']
				&& ! $this->overlaps_interval( $candidate, $blocked_end, $break )
				&& ! $this->overlaps_existing( $candidate, $blocked_end, $existing )
			) {
				$slots[] = $candidate->format( 'H:i' );
			}

			$candidate = $candidate->add( new DateInterval( 'PT' . $interval . 'M' ) );
		}

		/**
		 * Filters dynamically generated appointment times.
		 *
		 * @param string[] $slots       Available times in H:i format.
		 * @param int      $provider_id Provider ID.
		 * @param int      $service_id  Service ID.
		 * @param string   $date        ISO date.
		 */
		$slots = apply_filters( 'simple_booking_available_slots', $slots, $provider_id, $service_id, $date );

		return array_values(
			array_filter(
				array_unique( array_map( 'strval', is_array( $slots ) ? $slots : array() ) ),
				array( $this, 'is_valid_time' )
			)
		);
	}

	/**
	 * Return a schedule error code, or an empty string when a slot is valid.
	 *
	 * @param int    $service_id  Service ID.
	 * @param int    $provider_id Provider ID.
	 * @param string $date        ISO date.
	 * @param string $time        24-hour time.
	 * @param int    $exclude_appointment_id Appointment ID to ignore while rescheduling.
	 * @return string
	 */
	public function validate_slot( int $service_id, int $provider_id, string $date, string $time, int $exclude_appointment_id = 0 ): string {
		$appointment = $this->create_datetime( $date, $time );

		if ( ! $appointment ) {
			return 'time';
		}

		$settings      = Settings::get_settings();
		$notice_cutoff = current_datetime()->add( new DateInterval( 'PT' . absint( $settings['minimum_notice'] ) . 'M' ) );

		if ( $appointment <= current_datetime() ) {
			return 'past';
		}

		if ( $appointment < $notice_cutoff ) {
			return 'minimum_notice';
		}

		if ( ! $this->is_within_booking_window( $date ) ) {
			return 'advance_limit';
		}

		if ( ! $this->get_day_bounds( $date, $provider_id ) ) {
			return 'closed';
		}

		return in_array( $time, $this->get_available_slots( $service_id, $provider_id, $date, $exclude_appointment_id ), true ) ? '' : 'unavailable';
	}

	/**
	 * Return the first selectable date.
	 *
	 * @return string
	 */
	public function get_minimum_date(): string {
		return current_datetime()->format( 'Y-m-d' );
	}

	/**
	 * Return the last selectable date.
	 *
	 * @return string
	 */
	public function get_maximum_date(): string {
		$days = max( 1, absint( Settings::get_settings()['maximum_advance_days'] ) );
		return current_datetime()->add( new DateInterval( 'P' . $days . 'D' ) )->format( 'Y-m-d' );
	}

	/**
	 * Calculate the appointment end time from the service duration.
	 *
	 * @param int               $service_id Service ID.
	 * @param DateTimeImmutable $start      Appointment start.
	 * @return string
	 */
	public function calculate_end_time( int $service_id, DateTimeImmutable $start ): string {
		return $start->add( new DateInterval( 'PT' . $this->get_service_duration( $service_id ) . 'M' ) )->format( 'H:i' );
	}

	/**
	 * Build a date and time in the WordPress site timezone.
	 *
	 * @param string $date ISO date.
	 * @param string $time 24-hour time.
	 * @return DateTimeImmutable|null
	 */
	public function create_datetime( string $date, string $time ): ?DateTimeImmutable {
		if ( ! $this->is_valid_date( $date ) || ! $this->is_valid_time( $time ) ) {
			return null;
		}

		$value = DateTimeImmutable::createFromFormat( '!Y-m-d H:i', $date . ' ' . $time, wp_timezone() );
		return false !== $value && $value->format( 'Y-m-d H:i' ) === $date . ' ' . $time ? $value : null;
	}

	/**
	 * Acquire a provider/date database lock for atomic conflict checking.
	 *
	 * @param int    $provider_id Provider ID.
	 * @param string $date        ISO date.
	 * @return string
	 */
	public function acquire_lock( int $provider_id, string $date ): string {
		global $wpdb;

		$lock_name = substr( 'simple_booking_' . get_current_blog_id() . '_' . $provider_id . '_' . $date, 0, 64 );
		$acquired  = $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s, 5)', $lock_name ) );

		return '1' === (string) $acquired ? $lock_name : '';
	}

	/**
	 * Release a database lock.
	 *
	 * @param string $lock_name Lock identifier.
	 * @return void
	 */
	public function release_lock( string $lock_name ): void {
		global $wpdb;

		if ( '' !== $lock_name ) {
			$wpdb->get_var( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $lock_name ) );
		}
	}

	/**
	 * Check provider assignment.
	 *
	 * @param int $service_id  Service ID.
	 * @param int $provider_id Provider ID.
	 * @return bool
	 */
	public function is_provider_assigned( int $service_id, int $provider_id ): bool {
		$provider_ids = array_map( 'absint', (array) get_post_meta( $service_id, '_simple_booking_provider_ids', true ) );
		return in_array( $provider_id, $provider_ids, true );
	}

	/**
	 * Return provider or business-hour bounds for a date.
	 *
	 * @param string $date        ISO date.
	 * @param int    $provider_id Provider ID.
	 * @return array{open:DateTimeImmutable,close:DateTimeImmutable}|null
	 */
	private function get_day_bounds( string $date, int $provider_id ): ?array {
		$day = $this->create_datetime( $date, '00:00' );

		if ( ! $day ) {
			return null;
		}

		$settings = Settings::get_settings();

		if ( in_array( $date, (array) ( $settings['closed_dates'] ?? array() ), true ) ) {
			return null;
		}

		$day_key = strtolower( $day->format( 'l' ) );
		$hours   = $this->get_provider_day_schedule( $provider_id, $day_key );

		if ( null === $hours ) {
			$hours = $settings['business_hours'][ $day_key ] ?? array();
		}

		if ( '1' !== ( $hours['enabled'] ?? '0' ) ) {
			return null;
		}

		$open  = $this->create_datetime( $date, (string) ( $hours['open'] ?? '' ) );
		$close = $this->create_datetime( $date, (string) ( $hours['close'] ?? '' ) );

		return $open && $close && $open < $close ? array( 'open' => $open, 'close' => $close ) : null;
	}

	/**
	 * Return a provider's custom schedule for a weekday, or null when inheriting.
	 *
	 * @param int    $provider_id Provider ID.
	 * @param string $day_key     Lowercase weekday key.
	 * @return array<string, string>|null
	 */
	private function get_provider_day_schedule( int $provider_id, string $day_key ): ?array {
		if ( 'custom' !== get_post_meta( $provider_id, '_simple_booking_schedule_mode', true ) ) {
			return null;
		}

		$schedule = get_post_meta( $provider_id, '_simple_booking_schedule', true );
		return is_array( $schedule ) && isset( $schedule[ $day_key ] ) && is_array( $schedule[ $day_key ] ) ? $schedule[ $day_key ] : array();
	}

	/**
	 * Return the optional provider break on a date.
	 *
	 * @param string $date        ISO date.
	 * @param int    $provider_id Provider ID.
	 * @return array{start:DateTimeImmutable,end:DateTimeImmutable}|null
	 */
	private function get_break_bounds( string $date, int $provider_id ): ?array {
		$day = $this->create_datetime( $date, '00:00' );

		if ( ! $day ) {
			return null;
		}

		$hours = $this->get_provider_day_schedule( $provider_id, strtolower( $day->format( 'l' ) ) );

		if ( null === $hours ) {
			return null;
		}

		$start = $this->create_datetime( $date, (string) ( $hours['break_start'] ?? '' ) );
		$end   = $this->create_datetime( $date, (string) ( $hours['break_end'] ?? '' ) );

		return $start && $end && $start < $end ? array( 'start' => $start, 'end' => $end ) : null;
	}

	/**
	 * Check a candidate against one optional blocked interval.
	 *
	 * @param DateTimeImmutable                                        $start    Candidate start.
	 * @param DateTimeImmutable                                        $end      Candidate end including buffer.
	 * @param array{start:DateTimeImmutable,end:DateTimeImmutable}|null $interval Blocked interval.
	 * @return bool
	 */
	private function overlaps_interval( DateTimeImmutable $start, DateTimeImmutable $end, ?array $interval ): bool {
		return null !== $interval && $start < $interval['end'] && $end > $interval['start'];
	}

	/**
	 * Return existing provider intervals that block availability.
	 *
	 * @param int    $provider_id Provider ID.
	 * @param string $date        ISO date.
	 * @param int    $exclude_appointment_id Appointment ID to ignore.
	 * @return array<int, array{start:DateTimeImmutable,end:DateTimeImmutable}>
	 */
	private function get_existing_intervals( int $provider_id, string $date, int $exclude_appointment_id = 0 ): array {
		$appointments = get_posts(
			array(
				'post_type'        => Post_Types::APPOINTMENT,
				'post_status'      => array( 'private', 'publish' ),
				'posts_per_page'   => -1,
				'fields'           => 'ids',
				'no_found_rows'    => true,
				'suppress_filters' => true,
				'meta_query'       => array(
					array(
						'key'     => '_simple_booking_provider_id',
						'value'   => $provider_id,
						'compare' => '=',
						'type'    => 'NUMERIC',
					),
					array(
						'key'     => '_simple_booking_appointment_date',
						'value'   => $date,
						'compare' => '=',
					),
				),
			)
		);

		$intervals = array();

		foreach ( $appointments as $appointment_id ) {
			if ( $exclude_appointment_id === (int) $appointment_id || ! Appointment_Statuses::blocks_availability( (string) get_post_meta( $appointment_id, '_simple_booking_status', true ) ) ) {
				continue;
			}

			$service_id = absint( get_post_meta( $appointment_id, '_simple_booking_service_id', true ) );
			$start      = $this->create_datetime( $date, (string) get_post_meta( $appointment_id, '_simple_booking_start_time', true ) );
			$end        = $this->create_datetime( $date, (string) get_post_meta( $appointment_id, '_simple_booking_end_time', true ) );

			if ( ! $start ) {
				continue;
			}

			if ( ! $end || $end <= $start ) {
				$end = $start->add( new DateInterval( 'PT' . $this->get_service_duration( $service_id ) . 'M' ) );
			}

			$stored_buffer = get_post_meta( $appointment_id, '_simple_booking_buffer_time', true );
			$buffer        = '' === $stored_buffer ? $this->get_service_buffer( $service_id ) : min( 1440, absint( $stored_buffer ) );
			$intervals[]    = array(
				'start' => $start,
				'end'   => $end->add( new DateInterval( 'PT' . $buffer . 'M' ) ),
			);
		}

		return $intervals;
	}

	/**
	 * Check a candidate against existing blocked intervals.
	 *
	 * @param DateTimeImmutable                                            $start     Candidate start.
	 * @param DateTimeImmutable                                            $end       Candidate blocked end.
	 * @param array<int, array{start:DateTimeImmutable,end:DateTimeImmutable}> $existing Existing intervals.
	 * @return bool
	 */
	private function overlaps_existing( DateTimeImmutable $start, DateTimeImmutable $end, array $existing ): bool {
		foreach ( $existing as $interval ) {
			if ( $start < $interval['end'] && $end > $interval['start'] ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Check a date against the maximum advance limit.
	 *
	 * @param string $date ISO date.
	 * @return bool
	 */
	private function is_within_booking_window( string $date ): bool {
		return $this->is_valid_date( $date ) && $date >= $this->get_minimum_date() && $date <= $this->get_maximum_date();
	}

	/**
	 * Return a service duration with safe limits.
	 *
	 * @param int $service_id Service ID.
	 * @return int
	 */
	private function get_service_duration( int $service_id ): int {
		$duration = absint( get_post_meta( $service_id, '_simple_booking_duration', true ) );
		return min( 1440, max( 5, $duration ?: 30 ) );
	}

	/**
	 * Return a service buffer with safe limits.
	 *
	 * @param int $service_id Service ID.
	 * @return int
	 */
	private function get_service_buffer( int $service_id ): int {
		return min( 1440, absint( get_post_meta( $service_id, '_simple_booking_buffer_time', true ) ) );
	}

	/**
	 * Check that a related post is published and active.
	 *
	 * @param int    $post_id   Post ID.
	 * @param string $post_type Expected type.
	 * @return bool
	 */
	public function is_active_post( int $post_id, string $post_type ): bool {
		return $post_id > 0 && $post_type === get_post_type( $post_id ) && 'publish' === get_post_status( $post_id ) && '0' !== get_post_meta( $post_id, '_simple_booking_active', true );
	}

	/**
	 * Validate an ISO date.
	 *
	 * @param string $date Date value.
	 * @return bool
	 */
	private function is_valid_date( string $date ): bool {
		$value = DateTimeImmutable::createFromFormat( '!Y-m-d', $date, wp_timezone() );
		return false !== $value && $value->format( 'Y-m-d' ) === $date;
	}

	/**
	 * Validate a 24-hour time.
	 *
	 * @param string $time Time value.
	 * @return bool
	 */
	private function is_valid_time( string $time ): bool {
		return 1 === preg_match( '/^(?:[01]\d|2[0-3]):[0-5]\d$/', $time );
	}

	/**
	 * Format a time using the WordPress display preference.
	 *
	 * @param string $date ISO date.
	 * @param string $time 24-hour time.
	 * @return string
	 */
	private function format_time( string $date, string $time ): string {
		$value = $this->create_datetime( $date, $time );
		return $value ? wp_date( (string) get_option( 'time_format' ), $value->getTimestamp(), wp_timezone() ) : $time;
	}
}

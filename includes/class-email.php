<?php
/**
 * Appointment email notifications.
 *
 * @package SimpleBooking
 */

namespace SimpleBooking;

use DateTimeImmutable;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Sends customer and business appointment emails.
 */
final class Email {

	/**
	 * Register notification hooks.
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		add_action( 'simple_booking_after_create_appointment', array( $this, 'send_new_booking_emails' ), 10, 2 );
		add_action( 'simple_booking_appointment_status_changed', array( $this, 'send_status_email' ), 10, 3 );
	}

	/**
	 * Send customer and business notifications for a new request.
	 *
	 * @param int                       $appointment_id Appointment ID.
	 * @param array<string, int|string> $booking_data  Sanitized booking data.
	 * @return void
	 */
	public function send_new_booking_emails( int $appointment_id, array $booking_data ): void {
		unset( $booking_data );
		$settings = Settings::get_settings();

		if ( '1' === $settings['customer_email_enabled'] && '1' !== get_post_meta( $appointment_id, '_simple_booking_customer_email_sent', true ) ) {
			$sent = $this->send_customer_email( $appointment_id, 'new' );

			if ( $sent ) {
				update_post_meta( $appointment_id, '_simple_booking_customer_email_sent', '1' );
			}
		}

		if ( '1' === $settings['business_email_enabled'] && '1' !== get_post_meta( $appointment_id, '_simple_booking_business_email_sent', true ) ) {
			$sent = $this->send_business_email( $appointment_id );

			if ( $sent ) {
				update_post_meta( $appointment_id, '_simple_booking_business_email_sent', '1' );
			}
		}
	}

	/**
	 * Notify the customer when an appointment is cancelled.
	 *
	 * @param int    $appointment_id Appointment ID.
	 * @param string $new_status     New business status.
	 * @param string $old_status     Previous business status.
	 * @return void
	 */
	public function send_status_email( int $appointment_id, string $new_status, string $old_status ): void {
		if ( $new_status === $old_status || Appointment_Statuses::CANCELLED !== $new_status ) {
			return;
		}

		$settings = Settings::get_settings();

		if ( '1' === $settings['customer_email_enabled'] ) {
			$this->send_customer_email( $appointment_id, 'cancelled' );
		}
	}

	/**
	 * Send an appointment email to the customer.
	 *
	 * @param int    $appointment_id Appointment ID.
	 * @param string $context        New or cancelled context.
	 * @return bool
	 */
	private function send_customer_email( int $appointment_id, string $context ): bool {
		$recipient = sanitize_email( (string) get_post_meta( $appointment_id, '_simple_booking_customer_email', true ) );

		if ( ! is_email( $recipient ) ) {
			return false;
		}

		$settings     = Settings::get_settings();
		$is_cancelled = 'cancelled' === $context;
		$subject_key  = $is_cancelled ? 'cancellation_subject' : 'customer_subject';
		$message_key  = $is_cancelled ? 'cancellation_message' : 'customer_message';
		$subject      = $this->replace_tokens( (string) $settings[ $subject_key ], $appointment_id );
		$body         = $this->build_customer_body( $appointment_id, (string) $settings[ $message_key ] );

		if ( $is_cancelled ) {
			$subject = (string) apply_filters( 'simple_booking_cancellation_email_subject', $subject, $appointment_id );
			$body    = (string) apply_filters( 'simple_booking_cancellation_email_body', $body, $appointment_id );
		} else {
			$subject = (string) apply_filters( 'simple_booking_confirmation_email_subject', $subject, $appointment_id );
			$body    = (string) apply_filters( 'simple_booking_confirmation_email_body', $body, $appointment_id );
		}

		$recipient = (string) apply_filters( 'simple_booking_customer_email_recipient', $recipient, $appointment_id, $context );

		if ( ! is_email( $recipient ) ) {
			return false;
		}

		return wp_mail( $recipient, sanitize_text_field( $subject ), $body, $this->get_headers() );
	}

	/**
	 * Send the business a new-booking notification.
	 *
	 * @param int $appointment_id Appointment ID.
	 * @return bool
	 */
	private function send_business_email( int $appointment_id ): bool {
		$settings  = Settings::get_settings();
		$recipient = sanitize_email( (string) $settings['notification_email'] );

		if ( ! is_email( $recipient ) ) {
			return false;
		}

		$subject   = $this->replace_tokens( (string) $settings['business_subject'], $appointment_id );
		$body      = $this->build_business_body( $appointment_id );
		$subject   = (string) apply_filters( 'simple_booking_business_email_subject', $subject, $appointment_id );
		$body      = (string) apply_filters( 'simple_booking_business_email_body', $body, $appointment_id );
		$recipient = (string) apply_filters( 'simple_booking_business_email_recipient', $recipient, $appointment_id );

		if ( ! is_email( $recipient ) ) {
			return false;
		}

		return wp_mail( $recipient, sanitize_text_field( $subject ), $body, $this->get_headers() );
	}

	/**
	 * Build a plain-text customer email.
	 *
	 * @param int    $appointment_id Appointment ID.
	 * @param string $intro          Configurable message.
	 * @return string
	 */
	private function build_customer_body( int $appointment_id, string $intro ): string {
		$settings = Settings::get_settings();
		$lines    = array(
			$this->replace_tokens( $intro, $appointment_id ),
			'',
			...$this->get_appointment_lines( $appointment_id, false ),
		);

		$contact = array_filter(
			array(
				(string) $settings['business_name'],
				(string) $settings['business_email'],
				(string) $settings['business_phone'],
			)
		);

		if ( $contact ) {
			$lines[] = '';
			$lines[] = __( 'Business contact', 'simple-booking' );
			$lines   = array_merge( $lines, $contact );
		}

		return implode( "\n", $lines );
	}

	/**
	 * Build a plain-text business email.
	 *
	 * @param int $appointment_id Appointment ID.
	 * @return string
	 */
	private function build_business_body( int $appointment_id ): string {
		$lines   = array( __( 'A new appointment request was submitted.', 'simple-booking' ), '' );
		$lines   = array_merge( $lines, $this->get_appointment_lines( $appointment_id, true ) );
		$lines[] = '';
		$lines[] = sprintf(
			/* translators: %s: appointment admin URL. */
			__( 'Manage appointment: %s', 'simple-booking' ),
			admin_url( 'post.php?post=' . $appointment_id . '&action=edit' )
		);

		return implode( "\n", $lines );
	}

	/**
	 * Return formatted appointment details.
	 *
	 * @param int  $appointment_id Appointment ID.
	 * @param bool $include_contact Include private customer contact details.
	 * @return string[]
	 */
	private function get_appointment_lines( int $appointment_id, bool $include_contact ): array {
		$first_name  = (string) get_post_meta( $appointment_id, '_simple_booking_customer_first_name', true );
		$last_name   = (string) get_post_meta( $appointment_id, '_simple_booking_customer_last_name', true );
		$service_id  = absint( get_post_meta( $appointment_id, '_simple_booking_service_id', true ) );
		$provider_id = absint( get_post_meta( $appointment_id, '_simple_booking_provider_id', true ) );
		$status      = Appointment_Statuses::normalize( (string) get_post_meta( $appointment_id, '_simple_booking_status', true ) );
		$labels      = Appointment_Statuses::get_labels();
		$date        = (string) get_post_meta( $appointment_id, '_simple_booking_appointment_date', true );
		$time        = (string) get_post_meta( $appointment_id, '_simple_booking_start_time', true );
		$datetime    = DateTimeImmutable::createFromFormat( '!Y-m-d H:i', $date . ' ' . $time, wp_timezone() );
		$date_label  = $datetime ? wp_date( (string) get_option( 'date_format' ), $datetime->getTimestamp(), wp_timezone() ) : $date;
		$time_label  = $datetime ? wp_date( (string) get_option( 'time_format' ), $datetime->getTimestamp(), wp_timezone() ) : $time;

		$lines = array(
			sprintf( __( 'Customer: %s', 'simple-booking' ), trim( $first_name . ' ' . $last_name ) ),
			sprintf( __( 'Service: %s', 'simple-booking' ), get_the_title( $service_id ) ?: '—' ),
			sprintf( __( 'Provider: %s', 'simple-booking' ), get_the_title( $provider_id ) ?: '—' ),
			sprintf( __( 'Date: %s', 'simple-booking' ), $date_label ),
			sprintf( __( 'Time: %s', 'simple-booking' ), $time_label ),
			sprintf( __( 'Status: %s', 'simple-booking' ), $labels[ $status ] ),
		);

		if ( $include_contact ) {
			$lines[] = sprintf( __( 'Email: %s', 'simple-booking' ), (string) get_post_meta( $appointment_id, '_simple_booking_customer_email', true ) );
			$lines[] = sprintf( __( 'Phone: %s', 'simple-booking' ), (string) get_post_meta( $appointment_id, '_simple_booking_customer_phone', true ) );
			$notes   = (string) get_post_meta( $appointment_id, '_simple_booking_customer_notes', true );

			if ( '' !== $notes ) {
				$lines[] = sprintf( __( 'Customer notes: %s', 'simple-booking' ), $notes );
			}
		}

		return $lines;
	}

	/**
	 * Replace supported message tokens.
	 *
	 * @param string $text           Template text.
	 * @param int    $appointment_id Appointment ID.
	 * @return string
	 */
	private function replace_tokens( string $text, int $appointment_id ): string {
		$settings    = Settings::get_settings();
		$first_name  = (string) get_post_meta( $appointment_id, '_simple_booking_customer_first_name', true );
		$last_name   = (string) get_post_meta( $appointment_id, '_simple_booking_customer_last_name', true );
		$service_id  = absint( get_post_meta( $appointment_id, '_simple_booking_service_id', true ) );
		$provider_id = absint( get_post_meta( $appointment_id, '_simple_booking_provider_id', true ) );

		return strtr(
			$text,
			array(
				'{customer_name}'  => trim( $first_name . ' ' . $last_name ),
				'{service}'       => get_the_title( $service_id ) ?: '',
				'{provider}'      => get_the_title( $provider_id ) ?: '',
				'{date}'          => (string) get_post_meta( $appointment_id, '_simple_booking_appointment_date', true ),
				'{time}'          => (string) get_post_meta( $appointment_id, '_simple_booking_start_time', true ),
				'{business_name}'   => (string) $settings['business_name'],
			)
		);
	}

	/**
	 * Return plain-text mail headers.
	 *
	 * @return string[]
	 */
	private function get_headers(): array {
		$settings = Settings::get_settings();
		$email    = sanitize_email( (string) $settings['business_email'] );
		$headers  = array( 'Content-Type: text/plain; charset=UTF-8' );

		if ( is_email( $email ) ) {
			$name      = sanitize_text_field( (string) $settings['business_name'] );
			$headers[] = $name ? 'Reply-To: ' . $name . ' <' . $email . '>' : 'Reply-To: ' . $email;
		}

		return $headers;
	}
}

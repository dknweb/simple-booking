<?php
/**
 * Booking schedule settings.
 *
 * @package SimpleBooking
 */

namespace SimpleBooking;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Stores and renders business-wide booking rules.
 */
final class Settings {

	public const OPTION_NAME = 'simple_booking_settings';

	/**
	 * Register settings hooks.
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		add_action( 'admin_menu', array( $this, 'add_settings_page' ) );
		add_action( 'admin_init', array( $this, 'register_setting' ) );
	}

	/**
	 * Add the settings page beneath Simple Booking.
	 *
	 * @return void
	 */
	public function add_settings_page(): void {
		add_submenu_page(
			'edit.php?post_type=' . Post_Types::APPOINTMENT,
			__( 'Simple Booking Settings', 'simple-booking' ),
			__( 'Settings', 'simple-booking' ),
			'manage_options',
			'simple-booking-settings',
			array( $this, 'render_page' )
		);
	}

	/**
	 * Register the schedule option.
	 *
	 * @return void
	 */
	public function register_setting(): void {
		register_setting(
			'simple_booking_settings_group',
			self::OPTION_NAME,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize' ),
				'default'           => self::get_defaults(),
			)
		);
	}

	/**
	 * Sanitize schedule settings.
	 *
	 * @param mixed $input Untrusted settings input.
	 * @return array<string, mixed>
	 */
	public function sanitize( mixed $input ): array {
		$input    = is_array( $input ) ? $input : array();
		$defaults = self::get_defaults();
		$output   = array(
			'business_name'           => sanitize_text_field( (string) ( $input['business_name'] ?? '' ) ),
			'business_email'          => self::sanitize_email( $input['business_email'] ?? '' ),
			'business_phone'          => sanitize_text_field( (string) ( $input['business_phone'] ?? '' ) ),
			'slot_interval'         => min( 240, max( 5, absint( $input['slot_interval'] ?? $defaults['slot_interval'] ) ) ),
			'minimum_notice'        => min( 10080, absint( $input['minimum_notice'] ?? $defaults['minimum_notice'] ) ),
			'maximum_advance_days'  => min( 730, max( 1, absint( $input['maximum_advance_days'] ?? $defaults['maximum_advance_days'] ) ) ),
			'customer_email_enabled' => ! empty( $input['customer_email_enabled'] ) ? '1' : '0',
			'business_email_enabled'  => ! empty( $input['business_email_enabled'] ) ? '1' : '0',
			'notification_email'    => self::sanitize_email( $input['notification_email'] ?? '' ),
			'customer_subject'       => sanitize_text_field( (string) ( $input['customer_subject'] ?? $defaults['customer_subject'] ) ),
			'customer_message'       => sanitize_textarea_field( (string) ( $input['customer_message'] ?? $defaults['customer_message'] ) ),
			'business_subject'        => sanitize_text_field( (string) ( $input['business_subject'] ?? $defaults['business_subject'] ) ),
			'cancellation_subject'  => sanitize_text_field( (string) ( $input['cancellation_subject'] ?? $defaults['cancellation_subject'] ) ),
			'cancellation_message'  => sanitize_textarea_field( (string) ( $input['cancellation_message'] ?? $defaults['cancellation_message'] ) ),
			'closed_dates'          => self::sanitize_closed_dates( $input['closed_dates'] ?? '' ),
			'business_hours'        => array(),
		);

		$submitted_hours = isset( $input['business_hours'] ) && is_array( $input['business_hours'] ) ? $input['business_hours'] : array();

		foreach ( self::get_weekdays() as $day_key => $day_label ) {
			unset( $day_label );
			$day     = isset( $submitted_hours[ $day_key ] ) && is_array( $submitted_hours[ $day_key ] ) ? $submitted_hours[ $day_key ] : array();
			$open    = self::sanitize_time( $day['open'] ?? '' );
			$close   = self::sanitize_time( $day['close'] ?? '' );
			$enabled = ! empty( $day['enabled'] ) && $open && $close && $open < $close;

			$output['business_hours'][ $day_key ] = array(
				'enabled' => $enabled ? '1' : '0',
				'open'    => $open ?: $defaults['business_hours'][ $day_key ]['open'],
				'close'   => $close ?: $defaults['business_hours'][ $day_key ]['close'],
			);
		}

		return $output;
	}

	/**
	 * Render the settings page.
	 *
	 * @return void
	 */
	public function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$settings = self::get_settings();
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Simple Booking Settings', 'simple-booking' ); ?></h1>
			<p><?php esc_html_e( 'Configure business-wide scheduling rules. All appointment times use the WordPress site timezone.', 'simple-booking' ); ?></p>

			<form action="options.php" method="post">
				<?php settings_fields( 'simple_booking_settings_group' ); ?>

				<h2><?php esc_html_e( 'Business details', 'simple-booking' ); ?></h2>
				<table class="form-table" role="presentation">
					<tbody>
						<tr>
							<th scope="row"><label for="simple-booking-business-name"><?php esc_html_e( 'Business name', 'simple-booking' ); ?></label></th>
							<td><input id="simple-booking-business-name" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[business_name]" type="text" class="regular-text" value="<?php echo esc_attr( $settings['business_name'] ); ?>"></td>
						</tr>
						<tr>
							<th scope="row"><label for="simple-booking-business-email"><?php esc_html_e( 'Business email', 'simple-booking' ); ?></label></th>
							<td><input id="simple-booking-business-email" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[business_email]" type="email" class="regular-text" value="<?php echo esc_attr( $settings['business_email'] ); ?>"></td>
						</tr>
						<tr>
							<th scope="row"><label for="simple-booking-business-phone"><?php esc_html_e( 'Business phone', 'simple-booking' ); ?></label></th>
							<td><input id="simple-booking-business-phone" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[business_phone]" type="text" class="regular-text" value="<?php echo esc_attr( $settings['business_phone'] ); ?>"></td>
						</tr>
					</tbody>
				</table>

				<h2><?php esc_html_e( 'Booking rules', 'simple-booking' ); ?></h2>
				<table class="form-table" role="presentation">
					<tbody>
						<tr>
							<th scope="row"><label for="simple-booking-slot-interval"><?php esc_html_e( 'Slot interval', 'simple-booking' ); ?></label></th>
							<td><input id="simple-booking-slot-interval" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[slot_interval]" type="number" min="5" max="240" step="5" value="<?php echo esc_attr( $settings['slot_interval'] ); ?>"> <?php esc_html_e( 'minutes', 'simple-booking' ); ?></td>
						</tr>
						<tr>
							<th scope="row"><label for="simple-booking-minimum-notice"><?php esc_html_e( 'Minimum booking notice', 'simple-booking' ); ?></label></th>
							<td><input id="simple-booking-minimum-notice" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[minimum_notice]" type="number" min="0" max="10080" step="5" value="<?php echo esc_attr( $settings['minimum_notice'] ); ?>"> <?php esc_html_e( 'minutes', 'simple-booking' ); ?></td>
						</tr>
						<tr>
							<th scope="row"><label for="simple-booking-maximum-advance"><?php esc_html_e( 'Maximum advance booking', 'simple-booking' ); ?></label></th>
							<td><input id="simple-booking-maximum-advance" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[maximum_advance_days]" type="number" min="1" max="730" value="<?php echo esc_attr( $settings['maximum_advance_days'] ); ?>"> <?php esc_html_e( 'days', 'simple-booking' ); ?></td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Timezone', 'simple-booking' ); ?></th>
							<td><code><?php echo esc_html( wp_timezone_string() ); ?></code><p class="description"><?php esc_html_e( 'Change this under WordPress Settings → General.', 'simple-booking' ); ?></p></td>
						</tr>
					</tbody>
				</table>

				<h2><?php esc_html_e( 'Business hours', 'simple-booking' ); ?></h2>
				<p><?php esc_html_e( 'Disabled weekdays are treated as closed.', 'simple-booking' ); ?></p>
				<table class="widefat striped" style="max-width:760px">
					<thead><tr><th><?php esc_html_e( 'Day', 'simple-booking' ); ?></th><th><?php esc_html_e( 'Open', 'simple-booking' ); ?></th><th><?php esc_html_e( 'Opening time', 'simple-booking' ); ?></th><th><?php esc_html_e( 'Closing time', 'simple-booking' ); ?></th></tr></thead>
					<tbody>
						<?php foreach ( self::get_weekdays() as $day_key => $day_label ) : ?>
							<?php $hours = $settings['business_hours'][ $day_key ]; ?>
							<tr>
								<th scope="row"><?php echo esc_html( $day_label ); ?></th>
								<td><label><input name="<?php echo esc_attr( self::OPTION_NAME ); ?>[business_hours][<?php echo esc_attr( $day_key ); ?>][enabled]" type="checkbox" value="1" <?php checked( '1', $hours['enabled'] ); ?>> <span class="screen-reader-text"><?php echo esc_html( sprintf( __( 'Open on %s', 'simple-booking' ), $day_label ) ); ?></span></label></td>
								<td><input name="<?php echo esc_attr( self::OPTION_NAME ); ?>[business_hours][<?php echo esc_attr( $day_key ); ?>][open]" type="time" value="<?php echo esc_attr( $hours['open'] ); ?>" aria-label="<?php echo esc_attr( sprintf( __( '%s opening time', 'simple-booking' ), $day_label ) ); ?>"></td>
								<td><input name="<?php echo esc_attr( self::OPTION_NAME ); ?>[business_hours][<?php echo esc_attr( $day_key ); ?>][close]" type="time" value="<?php echo esc_attr( $hours['close'] ); ?>" aria-label="<?php echo esc_attr( sprintf( __( '%s closing time', 'simple-booking' ), $day_label ) ); ?>"></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>

				<h2><?php esc_html_e( 'Closed dates', 'simple-booking' ); ?></h2>
				<p><?php esc_html_e( 'Enter one date per line in YYYY-MM-DD format. Closed dates override business and provider working hours.', 'simple-booking' ); ?></p>
				<label for="simple-booking-closed-dates"><strong><?php esc_html_e( 'Dates', 'simple-booking' ); ?></strong></label><br>
				<textarea id="simple-booking-closed-dates" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[closed_dates]" class="regular-text code" rows="7" placeholder="2026-12-25&#10;2027-01-01"><?php echo esc_textarea( implode( "\n", $settings['closed_dates'] ) ); ?></textarea>

				<h2><?php esc_html_e( 'Email notifications', 'simple-booking' ); ?></h2>
				<table class="form-table" role="presentation">
					<tbody>
						<tr>
							<th scope="row"><?php esc_html_e( 'Customer email', 'simple-booking' ); ?></th>
							<td><label><input name="<?php echo esc_attr( self::OPTION_NAME ); ?>[customer_email_enabled]" type="checkbox" value="1" <?php checked( '1', $settings['customer_email_enabled'] ); ?>> <?php esc_html_e( 'Send the customer a booking email and cancellation updates', 'simple-booking' ); ?></label></td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Business email', 'simple-booking' ); ?></th>
							<td><label><input name="<?php echo esc_attr( self::OPTION_NAME ); ?>[business_email_enabled]" type="checkbox" value="1" <?php checked( '1', $settings['business_email_enabled'] ); ?>> <?php esc_html_e( 'Notify the business when a booking is submitted', 'simple-booking' ); ?></label></td>
						</tr>
						<tr>
							<th scope="row"><label for="simple-booking-notification-email"><?php esc_html_e( 'Business notification email', 'simple-booking' ); ?></label></th>
							<td><input id="simple-booking-notification-email" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[notification_email]" type="email" class="regular-text" value="<?php echo esc_attr( $settings['notification_email'] ); ?>"></td>
						</tr>
						<tr>
							<th scope="row"><label for="simple-booking-customer-subject"><?php esc_html_e( 'Customer email subject', 'simple-booking' ); ?></label></th>
							<td><input id="simple-booking-customer-subject" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[customer_subject]" type="text" class="large-text" value="<?php echo esc_attr( $settings['customer_subject'] ); ?>"></td>
						</tr>
						<tr>
							<th scope="row"><label for="simple-booking-customer-message"><?php esc_html_e( 'Customer email message', 'simple-booking' ); ?></label></th>
							<td><textarea id="simple-booking-customer-message" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[customer_message]" class="large-text" rows="4"><?php echo esc_textarea( $settings['customer_message'] ); ?></textarea></td>
						</tr>
						<tr>
							<th scope="row"><label for="simple-booking-business-subject"><?php esc_html_e( 'Business email subject', 'simple-booking' ); ?></label></th>
							<td><input id="simple-booking-business-subject" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[business_subject]" type="text" class="large-text" value="<?php echo esc_attr( $settings['business_subject'] ); ?>"></td>
						</tr>
						<tr>
							<th scope="row"><label for="simple-booking-cancellation-subject"><?php esc_html_e( 'Cancellation subject', 'simple-booking' ); ?></label></th>
							<td><input id="simple-booking-cancellation-subject" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[cancellation_subject]" type="text" class="large-text" value="<?php echo esc_attr( $settings['cancellation_subject'] ); ?>"></td>
						</tr>
						<tr>
							<th scope="row"><label for="simple-booking-cancellation-message"><?php esc_html_e( 'Cancellation message', 'simple-booking' ); ?></label></th>
							<td>
								<textarea id="simple-booking-cancellation-message" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[cancellation_message]" class="large-text" rows="4"><?php echo esc_textarea( $settings['cancellation_message'] ); ?></textarea>
								<p class="description"><?php esc_html_e( 'Available tokens: {customer_name}, {service}, {provider}, {date}, {time}, {business_name}.', 'simple-booking' ); ?></p>
							</td>
						</tr>
					</tbody>
				</table>

				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Return normalized settings.
	 *
	 * @return array<string, mixed>
	 */
	public static function get_settings(): array {
		$saved = get_option( self::OPTION_NAME, array() );
		$saved = is_array( $saved ) ? $saved : array();
		$settings = array_replace_recursive( self::get_defaults(), $saved );
		$settings['closed_dates'] = isset( $settings['closed_dates'] ) && is_array( $settings['closed_dates'] ) ? $settings['closed_dates'] : array();

		return $settings;
	}

	/**
	 * Return default booking rules.
	 *
	 * @return array<string, mixed>
	 */
	public static function get_defaults(): array {
		$hours = array();

		foreach ( array_keys( self::get_weekdays() ) as $day_key ) {
			$hours[ $day_key ] = array(
				'enabled' => in_array( $day_key, array( 'monday', 'tuesday', 'wednesday', 'thursday', 'friday' ), true ) ? '1' : '0',
				'open'    => '09:00',
				'close'   => '17:00',
			);
		}

		return array(
			'business_name'           => (string) get_bloginfo( 'name' ),
			'business_email'          => (string) get_option( 'admin_email' ),
			'business_phone'          => '',
			'slot_interval'         => 30,
			'minimum_notice'        => 120,
			'maximum_advance_days'  => 90,
			'customer_email_enabled' => '1',
			'business_email_enabled'  => '1',
			'notification_email'    => (string) get_option( 'admin_email' ),
			'customer_subject'       => __( 'Appointment request received — {business_name}', 'simple-booking' ),
			'customer_message'       => __( 'Hello {customer_name}, we received your appointment request. The business will contact you when it is confirmed.', 'simple-booking' ),
			'business_subject'        => __( 'New appointment request from {customer_name}', 'simple-booking' ),
			'cancellation_subject'  => __( 'Appointment cancelled — {business_name}', 'simple-booking' ),
			'cancellation_message'  => __( 'Hello {customer_name}, your appointment has been cancelled. Please contact the business if you would like to arrange another time.', 'simple-booking' ),
			'closed_dates'          => array(),
			'business_hours'       => $hours,
		);
	}

	/**
	 * Return weekday keys and labels.
	 *
	 * @return array<string, string>
	 */
	public static function get_weekdays(): array {
		return array(
			'monday'    => __( 'Monday', 'simple-booking' ),
			'tuesday'   => __( 'Tuesday', 'simple-booking' ),
			'wednesday' => __( 'Wednesday', 'simple-booking' ),
			'thursday'  => __( 'Thursday', 'simple-booking' ),
			'friday'    => __( 'Friday', 'simple-booking' ),
			'saturday'  => __( 'Saturday', 'simple-booking' ),
			'sunday'    => __( 'Sunday', 'simple-booking' ),
		);
	}

	/**
	 * Sanitize a 24-hour time.
	 *
	 * @param mixed $time Untrusted time.
	 * @return string
	 */
	private static function sanitize_time( mixed $time ): string {
		$time = sanitize_text_field( (string) $time );
		return preg_match( '/^(?:[01]\d|2[0-3]):[0-5]\d$/', $time ) ? $time : '';
	}

	/**
	 * Sanitize a newline- or comma-separated collection of ISO dates.
	 *
	 * @param mixed $dates Untrusted date collection.
	 * @return string[]
	 */
	private static function sanitize_closed_dates( mixed $dates ): array {
		$dates = is_array( $dates ) ? $dates : preg_split( '/[\r\n,]+/', (string) $dates );
		$clean = array();

		foreach ( is_array( $dates ) ? $dates : array() as $date ) {
			$date   = sanitize_text_field( (string) $date );
			$parsed = \DateTimeImmutable::createFromFormat( '!Y-m-d', $date, wp_timezone() );

			if ( false !== $parsed && $parsed->format( 'Y-m-d' ) === $date ) {
				$clean[] = $date;
			}
		}

		$clean = array_values( array_unique( $clean ) );
		sort( $clean );
		return $clean;
	}

	/**
	 * Sanitize an optional email address.
	 *
	 * @param mixed $email Untrusted email.
	 * @return string
	 */
	private static function sanitize_email( mixed $email ): string {
		$email = sanitize_email( (string) $email );
		return is_email( $email ) ? $email : '';
	}
}

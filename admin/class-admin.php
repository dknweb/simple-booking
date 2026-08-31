<?php
/**
 * WordPress admin screens.
 *
 * @package SimpleBooking
 */

namespace SimpleBooking\Admin;

use SimpleBooking\Appointment_Statuses;
use SimpleBooking\Availability;
use SimpleBooking\Post_Types;
use SimpleBooking\Settings;
use WP_Post;
use WP_Query;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Provides appointment, service, and provider management workflows.
 */
final class Admin {

	private const APPOINTMENT_NONCE_ACTION = 'simple_booking_save_appointment';
	private const SERVICE_NONCE_ACTION     = 'simple_booking_save_service';
	private const PROVIDER_NONCE_ACTION    = 'simple_booking_save_provider';

	private string $appointment_error = '';

	/**
	 * Admin constructor.
	 *
	 * @param Availability $availability Availability engine.
	 */
	public function __construct( private Availability $availability ) {}

	/**
	 * Register admin hooks.
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		add_action( 'add_meta_boxes', array( $this, 'register_meta_boxes' ) );
		add_action( 'save_post_' . Post_Types::APPOINTMENT, array( $this, 'save_appointment' ) );
		add_action( 'save_post_' . Post_Types::SERVICE, array( $this, 'save_service' ) );
		add_action( 'save_post_' . Post_Types::PROVIDER, array( $this, 'save_provider' ) );

		add_filter( 'manage_' . Post_Types::APPOINTMENT . '_posts_columns', array( $this, 'appointment_columns' ) );
		add_action( 'manage_' . Post_Types::APPOINTMENT . '_posts_custom_column', array( $this, 'render_appointment_column' ), 10, 2 );
		add_action( 'restrict_manage_posts', array( $this, 'render_appointment_filters' ), 10, 2 );
		add_action( 'pre_get_posts', array( $this, 'filter_appointments' ) );
		add_filter( 'redirect_post_location', array( $this, 'add_appointment_error_to_redirect' ) );
		add_action( 'admin_notices', array( $this, 'render_appointment_notice' ) );
		add_filter( 'manage_' . Post_Types::SERVICE . '_posts_columns', array( $this, 'service_columns' ) );
		add_action( 'manage_' . Post_Types::SERVICE . '_posts_custom_column', array( $this, 'render_service_column' ), 10, 2 );
		add_filter( 'manage_' . Post_Types::PROVIDER . '_posts_columns', array( $this, 'provider_columns' ) );
		add_action( 'manage_' . Post_Types::PROVIDER . '_posts_custom_column', array( $this, 'render_provider_column' ), 10, 2 );
		add_filter( 'enter_title_here', array( $this, 'title_placeholder' ), 10, 2 );
	}

	/**
	 * Clarify the title field on each management screen.
	 *
	 * @param string  $placeholder Existing placeholder.
	 * @param WP_Post $post        Current post.
	 * @return string
	 */
	public function title_placeholder( string $placeholder, WP_Post $post ): string {
		switch ( $post->post_type ) {
			case Post_Types::APPOINTMENT:
				return __( 'Appointment reference', 'simple-booking' );
			case Post_Types::SERVICE:
				return __( 'Service name', 'simple-booking' );
			case Post_Types::PROVIDER:
				return __( 'Provider name', 'simple-booking' );
			default:
				return $placeholder;
		}
	}

	/**
	 * Register CPT meta boxes.
	 *
	 * @return void
	 */
	public function register_meta_boxes(): void {
		add_meta_box(
			'simple-booking-appointment-details',
			__( 'Appointment Details', 'simple-booking' ),
			array( $this, 'render_appointment_meta_box' ),
			Post_Types::APPOINTMENT,
			'normal',
			'high'
		);

		add_meta_box(
			'simple-booking-service-settings',
			__( 'Service Settings', 'simple-booking' ),
			array( $this, 'render_service_meta_box' ),
			Post_Types::SERVICE,
			'normal',
			'high'
		);

		add_meta_box(
			'simple-booking-provider-settings',
			__( 'Provider Settings', 'simple-booking' ),
			array( $this, 'render_provider_meta_box' ),
			Post_Types::PROVIDER,
			'normal',
			'high'
		);
	}

	/**
	 * Render appointment fields.
	 *
	 * @param WP_Post $post Appointment post.
	 * @return void
	 */
	public function render_appointment_meta_box( WP_Post $post ): void {
		wp_nonce_field( self::APPOINTMENT_NONCE_ACTION, 'simple_booking_appointment_nonce' );

		$service_id  = absint( get_post_meta( $post->ID, '_simple_booking_service_id', true ) );
		$provider_id = absint( get_post_meta( $post->ID, '_simple_booking_provider_id', true ) );
		$status      = Appointment_Statuses::normalize( (string) get_post_meta( $post->ID, '_simple_booking_status', true ) );
		$statuses    = Appointment_Statuses::get_labels();
		?>
		<table class="form-table" role="presentation">
			<tbody>
				<?php $this->render_text_row( 'customer_first_name', __( 'First name', 'simple-booking' ), $post ); ?>
				<?php $this->render_text_row( 'customer_last_name', __( 'Last name', 'simple-booking' ), $post ); ?>
				<?php $this->render_text_row( 'customer_email', __( 'Email', 'simple-booking' ), $post, 'email' ); ?>
				<?php $this->render_text_row( 'customer_phone', __( 'Phone', 'simple-booking' ), $post, 'tel' ); ?>
				<tr>
					<th scope="row"><label for="simple-booking-service-id"><?php esc_html_e( 'Service', 'simple-booking' ); ?></label></th>
					<td><?php $this->render_post_select( 'service_id', Post_Types::SERVICE, $service_id, __( 'Select a service', 'simple-booking' ) ); ?></td>
				</tr>
				<tr>
					<th scope="row"><label for="simple-booking-provider-id"><?php esc_html_e( 'Provider', 'simple-booking' ); ?></label></th>
					<td><?php $this->render_post_select( 'provider_id', Post_Types::PROVIDER, $provider_id, __( 'Select a provider', 'simple-booking' ) ); ?></td>
				</tr>
				<?php $this->render_text_row( 'appointment_date', __( 'Appointment date', 'simple-booking' ), $post, 'date' ); ?>
				<?php $this->render_text_row( 'start_time', __( 'Start time', 'simple-booking' ), $post, 'time' ); ?>
				<?php $this->render_readonly_row( 'end_time', __( 'End time', 'simple-booking' ), $post ); ?>
				<tr>
					<th scope="row"><label for="simple-booking-status"><?php esc_html_e( 'Status', 'simple-booking' ); ?></label></th>
					<td>
						<select id="simple-booking-status" name="simple_booking_status">
							<?php foreach ( $statuses as $value => $label ) : ?>
								<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $status, $value ); ?>><?php echo esc_html( $label ); ?></option>
							<?php endforeach; ?>
						</select>
						<p class="description"><?php esc_html_e( 'Changing the status to Cancelled releases the time slot and emails the customer when customer emails are enabled.', 'simple-booking' ); ?></p>
					</td>
				</tr>
				<?php $this->render_textarea_row( 'customer_notes', __( 'Customer notes', 'simple-booking' ), $post ); ?>
				<?php $this->render_textarea_row( 'internal_notes', __( 'Internal business notes', 'simple-booking' ), $post ); ?>
			</tbody>
		</table>
		<?php
	}

	/**
	 * Render service settings.
	 *
	 * @param WP_Post $post Service post.
	 * @return void
	 */
	public function render_service_meta_box( WP_Post $post ): void {
		wp_nonce_field( self::SERVICE_NONCE_ACTION, 'simple_booking_service_nonce' );

		$duration     = absint( get_post_meta( $post->ID, '_simple_booking_duration', true ) );
		$buffer       = absint( get_post_meta( $post->ID, '_simple_booking_buffer_time', true ) );
		$price        = (string) get_post_meta( $post->ID, '_simple_booking_price_display', true );
		$active       = get_post_meta( $post->ID, '_simple_booking_active', true );
		$provider_ids = array_map( 'absint', (array) get_post_meta( $post->ID, '_simple_booking_provider_ids', true ) );

		if ( '' === $active ) {
			$active = '1';
		}
		?>
		<table class="form-table" role="presentation">
			<tbody>
				<tr>
					<th scope="row"><label for="simple-booking-duration"><?php esc_html_e( 'Duration', 'simple-booking' ); ?></label></th>
					<td><input id="simple-booking-duration" name="simple_booking_duration" type="number" min="5" max="1440" step="5" value="<?php echo esc_attr( $duration ?: 30 ); ?>"> <?php esc_html_e( 'minutes', 'simple-booking' ); ?></td>
				</tr>
				<tr>
					<th scope="row"><label for="simple-booking-buffer-time"><?php esc_html_e( 'Buffer time', 'simple-booking' ); ?></label></th>
					<td><input id="simple-booking-buffer-time" name="simple_booking_buffer_time" type="number" min="0" max="1440" step="5" value="<?php echo esc_attr( $buffer ); ?>"> <?php esc_html_e( 'minutes', 'simple-booking' ); ?></td>
				</tr>
				<tr>
					<th scope="row"><label for="simple-booking-price-display"><?php esc_html_e( 'Price display', 'simple-booking' ); ?></label></th>
					<td><input id="simple-booking-price-display" name="simple_booking_price_display" type="text" class="regular-text" value="<?php echo esc_attr( $price ); ?>"><p class="description"><?php esc_html_e( 'Optional display text, for example “From $80”.', 'simple-booking' ); ?></p></td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Assigned providers', 'simple-booking' ); ?></th>
					<td><?php $this->render_provider_checkboxes( $provider_ids ); ?></td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Availability', 'simple-booking' ); ?></th>
					<td><label><input name="simple_booking_active" type="checkbox" value="1" <?php checked( '1', $active ); ?>> <?php esc_html_e( 'Service is active', 'simple-booking' ); ?></label></td>
				</tr>
			</tbody>
		</table>
		<?php
	}

	/**
	 * Render provider settings.
	 *
	 * @param WP_Post $post Provider post.
	 * @return void
	 */
	public function render_provider_meta_box( WP_Post $post ): void {
		wp_nonce_field( self::PROVIDER_NONCE_ACTION, 'simple_booking_provider_nonce' );
		$active        = get_post_meta( $post->ID, '_simple_booking_active', true );
		$schedule_mode = (string) get_post_meta( $post->ID, '_simple_booking_schedule_mode', true );
		$schedule      = get_post_meta( $post->ID, '_simple_booking_schedule', true );
		$business_hours  = Settings::get_settings()['business_hours'];
		$schedule      = is_array( $schedule ) ? $schedule : array();

		if ( '' === $active ) {
			$active = '1';
		}
		?>
		<p><label><input name="simple_booking_active" type="checkbox" value="1" <?php checked( '1', $active ); ?>> <?php esc_html_e( 'Provider is active', 'simple-booking' ); ?></label></p>
		<p class="description"><?php esc_html_e( 'Assign this provider from each service’s settings.', 'simple-booking' ); ?></p>

		<h3><?php esc_html_e( 'Working schedule', 'simple-booking' ); ?></h3>
		<p>
			<label for="simple-booking-schedule-mode"><?php esc_html_e( 'Schedule source', 'simple-booking' ); ?></label>
			<select id="simple-booking-schedule-mode" name="simple_booking_schedule_mode">
				<option value="inherit" <?php selected( 'custom' !== $schedule_mode ); ?>><?php esc_html_e( 'Inherit business hours', 'simple-booking' ); ?></option>
				<option value="custom" <?php selected( 'custom', $schedule_mode ); ?>><?php esc_html_e( 'Use a custom provider schedule', 'simple-booking' ); ?></option>
			</select>
		</p>
		<p class="description"><?php esc_html_e( 'A complete custom schedule overrides business weekday hours. Business closed dates always apply.', 'simple-booking' ); ?></p>

		<table class="widefat striped">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Day', 'simple-booking' ); ?></th>
					<th><?php esc_html_e( 'Working', 'simple-booking' ); ?></th>
					<th><?php esc_html_e( 'Start', 'simple-booking' ); ?></th>
					<th><?php esc_html_e( 'End', 'simple-booking' ); ?></th>
					<th><?php esc_html_e( 'Break start', 'simple-booking' ); ?></th>
					<th><?php esc_html_e( 'Break end', 'simple-booking' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( Settings::get_weekdays() as $day_key => $day_label ) : ?>
					<?php
					$day = isset( $schedule[ $day_key ] ) && is_array( $schedule[ $day_key ] )
						? $schedule[ $day_key ]
						: array(
							'enabled'     => $business_hours[ $day_key ]['enabled'],
							'open'        => $business_hours[ $day_key ]['open'],
							'close'       => $business_hours[ $day_key ]['close'],
							'break_start' => '',
							'break_end'   => '',
						);
					?>
					<tr>
						<th scope="row"><?php echo esc_html( $day_label ); ?></th>
						<td><label><input name="simple_booking_schedule[<?php echo esc_attr( $day_key ); ?>][enabled]" type="checkbox" value="1" <?php checked( '1', $day['enabled'] ?? '0' ); ?>> <span class="screen-reader-text"><?php echo esc_html( sprintf( __( 'Working on %s', 'simple-booking' ), $day_label ) ); ?></span></label></td>
						<td><input name="simple_booking_schedule[<?php echo esc_attr( $day_key ); ?>][open]" type="time" value="<?php echo esc_attr( $day['open'] ?? '09:00' ); ?>" aria-label="<?php echo esc_attr( sprintf( __( '%s start time', 'simple-booking' ), $day_label ) ); ?>"></td>
						<td><input name="simple_booking_schedule[<?php echo esc_attr( $day_key ); ?>][close]" type="time" value="<?php echo esc_attr( $day['close'] ?? '17:00' ); ?>" aria-label="<?php echo esc_attr( sprintf( __( '%s end time', 'simple-booking' ), $day_label ) ); ?>"></td>
						<td><input name="simple_booking_schedule[<?php echo esc_attr( $day_key ); ?>][break_start]" type="time" value="<?php echo esc_attr( $day['break_start'] ?? '' ); ?>" aria-label="<?php echo esc_attr( sprintf( __( '%s break start', 'simple-booking' ), $day_label ) ); ?>"></td>
						<td><input name="simple_booking_schedule[<?php echo esc_attr( $day_key ); ?>][break_end]" type="time" value="<?php echo esc_attr( $day['break_end'] ?? '' ); ?>" aria-label="<?php echo esc_attr( sprintf( __( '%s break end', 'simple-booking' ), $day_label ) ); ?>"></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<?php
	}

	/**
	 * Save appointment fields.
	 *
	 * @param int $post_id Appointment ID.
	 * @return void
	 */
	public function save_appointment( int $post_id ): void {
		if ( ! $this->can_save( $post_id, 'simple_booking_appointment_nonce', self::APPOINTMENT_NONCE_ACTION ) ) {
			return;
		}

		$data = $this->get_submitted_appointment_data();
		$old  = array(
			'service_id'  => absint( get_post_meta( $post_id, '_simple_booking_service_id', true ) ),
			'provider_id' => absint( get_post_meta( $post_id, '_simple_booking_provider_id', true ) ),
			'date'        => (string) get_post_meta( $post_id, '_simple_booking_appointment_date', true ),
			'time'        => (string) get_post_meta( $post_id, '_simple_booking_start_time', true ),
			'status'      => Appointment_Statuses::normalize( (string) get_post_meta( $post_id, '_simple_booking_status', true ) ),
		);

		$schedule_changed = (int) $data['service_id'] !== $old['service_id']
			|| (int) $data['provider_id'] !== $old['provider_id']
			|| (string) $data['date'] !== $old['date']
			|| (string) $data['time'] !== $old['time'];
		$starts_blocking = ! Appointment_Statuses::blocks_availability( $old['status'] )
			&& Appointment_Statuses::blocks_availability( (string) $data['status'] );
		$requires_validation = Appointment_Statuses::blocks_availability( (string) $data['status'] )
			&& ( $schedule_changed || $starts_blocking );

		if ( ( $schedule_changed || $starts_blocking ) && ! $this->validate_appointment_relationship( (int) $data['service_id'], (int) $data['provider_id'] ) ) {
			$this->appointment_error = 'relationship';
			return;
		}

		if ( $schedule_changed && ! $this->availability->create_datetime( (string) $data['date'], (string) $data['time'] ) ) {
			$this->appointment_error = 'time';
			return;
		}

		$lock_name = '';

		if ( $requires_validation ) {
			$lock_name = $this->availability->acquire_lock( (int) $data['provider_id'], (string) $data['date'] );

			if ( '' === $lock_name ) {
				$this->appointment_error = 'booking_busy';
				return;
			}

			$schedule_error = $this->availability->validate_slot(
				(int) $data['service_id'],
				(int) $data['provider_id'],
				(string) $data['date'],
				(string) $data['time'],
				$post_id
			);

			if ( '' !== $schedule_error ) {
				$this->availability->release_lock( $lock_name );
				$this->appointment_error = sanitize_key( $schedule_error );
				return;
			}
		}

		$this->save_text( $post_id, 'customer_first_name' );
		$this->save_text( $post_id, 'customer_last_name' );
		$this->save_text( $post_id, 'customer_phone' );
		$this->save_textarea( $post_id, 'customer_notes' );
		$this->save_textarea( $post_id, 'internal_notes' );

		$email = isset( $_POST['simple_booking_customer_email'] ) ? sanitize_email( wp_unslash( $_POST['simple_booking_customer_email'] ) ) : '';
		update_post_meta( $post_id, '_simple_booking_customer_email', is_email( $email ) ? $email : '' );

		update_post_meta( $post_id, '_simple_booking_service_id', (int) $data['service_id'] );
		update_post_meta( $post_id, '_simple_booking_provider_id', (int) $data['provider_id'] );
		update_post_meta( $post_id, '_simple_booking_appointment_date', (string) $data['date'] );
		update_post_meta( $post_id, '_simple_booking_start_time', (string) $data['time'] );
		update_post_meta( $post_id, '_simple_booking_status', (string) $data['status'] );

		if ( $schedule_changed ) {
			$start = $this->availability->create_datetime( (string) $data['date'], (string) $data['time'] );

			if ( $start ) {
				$buffer = min( 1440, absint( get_post_meta( (int) $data['service_id'], '_simple_booking_buffer_time', true ) ) );
				update_post_meta( $post_id, '_simple_booking_end_time', $this->availability->calculate_end_time( (int) $data['service_id'], $start ) );
				update_post_meta( $post_id, '_simple_booking_buffer_time', $buffer );
			}
		}

		$this->availability->release_lock( $lock_name );

		if ( (string) $data['status'] !== $old['status'] ) {
			/**
			 * Fires after an appointment business status changes.
			 *
			 * @param int    $post_id    Appointment ID.
			 * @param string $new_status New status.
			 * @param string $old_status Previous status.
			 */
			do_action( 'simple_booking_appointment_status_changed', $post_id, (string) $data['status'], $old['status'] );
		}
	}

	/**
	 * Save service fields.
	 *
	 * @param int $post_id Service ID.
	 * @return void
	 */
	public function save_service( int $post_id ): void {
		if ( ! $this->can_save( $post_id, 'simple_booking_service_nonce', self::SERVICE_NONCE_ACTION ) ) {
			return;
		}

		$duration = isset( $_POST['simple_booking_duration'] ) ? absint( wp_unslash( $_POST['simple_booking_duration'] ) ) : 30;
		$buffer   = isset( $_POST['simple_booking_buffer_time'] ) ? absint( wp_unslash( $_POST['simple_booking_buffer_time'] ) ) : 0;
		$price    = isset( $_POST['simple_booking_price_display'] ) ? sanitize_text_field( wp_unslash( $_POST['simple_booking_price_display'] ) ) : '';

		update_post_meta( $post_id, '_simple_booking_duration', min( 1440, max( 5, $duration ) ) );
		update_post_meta( $post_id, '_simple_booking_buffer_time', min( 1440, $buffer ) );
		update_post_meta( $post_id, '_simple_booking_price_display', $price );
		update_post_meta( $post_id, '_simple_booking_active', isset( $_POST['simple_booking_active'] ) ? '1' : '0' );

		$provider_ids = isset( $_POST['simple_booking_provider_ids'] ) ? (array) wp_unslash( $_POST['simple_booking_provider_ids'] ) : array();
		$provider_ids = array_values( array_filter( array_map( 'absint', $provider_ids ) ) );
		$provider_ids = array_values(
			array_filter(
				$provider_ids,
				static fn( int $provider_id ): bool => Post_Types::PROVIDER === get_post_type( $provider_id )
			)
		);
		update_post_meta( $post_id, '_simple_booking_provider_ids', $provider_ids );
	}

	/**
	 * Save provider fields.
	 *
	 * @param int $post_id Provider ID.
	 * @return void
	 */
	public function save_provider( int $post_id ): void {
		if ( ! $this->can_save( $post_id, 'simple_booking_provider_nonce', self::PROVIDER_NONCE_ACTION ) ) {
			return;
		}

		update_post_meta( $post_id, '_simple_booking_active', isset( $_POST['simple_booking_active'] ) ? '1' : '0' );

		$mode      = isset( $_POST['simple_booking_schedule_mode'] ) ? sanitize_key( wp_unslash( $_POST['simple_booking_schedule_mode'] ) ) : 'inherit';
		$submitted = isset( $_POST['simple_booking_schedule'] ) && is_array( $_POST['simple_booking_schedule'] ) ? wp_unslash( $_POST['simple_booking_schedule'] ) : array();
		$schedule  = array();

		foreach ( Settings::get_weekdays() as $day_key => $day_label ) {
			unset( $day_label );
			$day         = isset( $submitted[ $day_key ] ) && is_array( $submitted[ $day_key ] ) ? $submitted[ $day_key ] : array();
			$open        = $this->sanitize_time( $day['open'] ?? '' );
			$close       = $this->sanitize_time( $day['close'] ?? '' );
			$break_start = $this->sanitize_time( $day['break_start'] ?? '' );
			$break_end   = $this->sanitize_time( $day['break_end'] ?? '' );

			if ( ! $break_start || ! $break_end || $break_start >= $break_end || $break_start < $open || $break_end > $close ) {
				$break_start = '';
				$break_end   = '';
			}

			$schedule[ $day_key ] = array(
				'enabled'     => ! empty( $day['enabled'] ) && $open && $close && $open < $close ? '1' : '0',
				'open'        => $open ?: '09:00',
				'close'       => $close ?: '17:00',
				'break_start' => $break_start,
				'break_end'   => $break_end,
			);
		}

		update_post_meta( $post_id, '_simple_booking_schedule_mode', 'custom' === $mode ? 'custom' : 'inherit' );
		update_post_meta( $post_id, '_simple_booking_schedule', $schedule );
	}

	/**
	 * Set appointment list columns.
	 *
	 * @param array<string, string> $columns Existing columns.
	 * @return array<string, string>
	 */
	public function appointment_columns( array $columns ): array {
		return array(
			'cb'               => $columns['cb'],
			'title'            => __( 'Appointment', 'simple-booking' ),
			'customer'          => __( 'Customer', 'simple-booking' ),
			'service'          => __( 'Service', 'simple-booking' ),
			'provider'         => __( 'Provider', 'simple-booking' ),
			'appointment_date' => __( 'Appointment Date', 'simple-booking' ),
			'appointment_time' => __( 'Appointment Time', 'simple-booking' ),
			'booking_status'   => __( 'Status', 'simple-booking' ),
			'date'             => __( 'Date Booked', 'simple-booking' ),
		);
	}

	/**
	 * Render appointment list values.
	 *
	 * @param string $column  Column key.
	 * @param int    $post_id Appointment ID.
	 * @return void
	 */
	public function render_appointment_column( string $column, int $post_id ): void {
		switch ( $column ) {
			case 'customer':
				$name = trim( get_post_meta( $post_id, '_simple_booking_customer_first_name', true ) . ' ' . get_post_meta( $post_id, '_simple_booking_customer_last_name', true ) );
				echo esc_html( $name ?: '—' );
				break;
			case 'service':
				$this->render_related_title( $post_id, '_simple_booking_service_id' );
				break;
			case 'provider':
				$this->render_related_title( $post_id, '_simple_booking_provider_id' );
				break;
			case 'appointment_date':
				$date = (string) get_post_meta( $post_id, '_simple_booking_appointment_date', true );
				echo esc_html( $date ?: '—' );
				break;
			case 'appointment_time':
				$time = (string) get_post_meta( $post_id, '_simple_booking_start_time', true );
				echo esc_html( $time ?: '—' );
				break;
			case 'booking_status':
				$status   = Appointment_Statuses::normalize( (string) get_post_meta( $post_id, '_simple_booking_status', true ) );
				$statuses = Appointment_Statuses::get_labels();
				echo esc_html( $statuses[ $status ] );
				break;
		}
	}

	/**
	 * Render appointment list filters.
	 *
	 * @param string $post_type Current post type.
	 * @param string $which     Filter bar position.
	 * @return void
	 */
	public function render_appointment_filters( string $post_type, string $which ): void {
		if ( Post_Types::APPOINTMENT !== $post_type || 'top' !== $which ) {
			return;
		}

		$status      = isset( $_GET['simple_booking_status_filter'] ) ? sanitize_key( wp_unslash( $_GET['simple_booking_status_filter'] ) ) : '';
		$provider_id = isset( $_GET['simple_booking_provider_filter'] ) ? absint( wp_unslash( $_GET['simple_booking_provider_filter'] ) ) : 0;
		$service_id  = isset( $_GET['simple_booking_service_filter'] ) ? absint( wp_unslash( $_GET['simple_booking_service_filter'] ) ) : 0;
		$date        = isset( $_GET['simple_booking_date_filter'] ) ? sanitize_text_field( wp_unslash( $_GET['simple_booking_date_filter'] ) ) : '';
		?>
		<label class="screen-reader-text" for="simple-booking-status-filter"><?php esc_html_e( 'Filter by appointment status', 'simple-booking' ); ?></label>
		<select id="simple-booking-status-filter" name="simple_booking_status_filter">
			<option value=""><?php esc_html_e( 'All statuses', 'simple-booking' ); ?></option>
			<?php foreach ( Appointment_Statuses::get_labels() as $value => $label ) : ?>
				<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $status, $value ); ?>><?php echo esc_html( $label ); ?></option>
			<?php endforeach; ?>
		</select>
		<?php $this->render_filter_post_select( 'simple_booking_provider_filter', Post_Types::PROVIDER, $provider_id, __( 'All providers', 'simple-booking' ) ); ?>
		<?php $this->render_filter_post_select( 'simple_booking_service_filter', Post_Types::SERVICE, $service_id, __( 'All services', 'simple-booking' ) ); ?>
		<label class="screen-reader-text" for="simple-booking-date-filter"><?php esc_html_e( 'Filter by appointment date', 'simple-booking' ); ?></label>
		<input id="simple-booking-date-filter" name="simple_booking_date_filter" type="date" value="<?php echo esc_attr( $this->is_valid_date( $date ) ? $date : '' ); ?>">
		<?php
	}

	/**
	 * Apply appointment list filters.
	 *
	 * @param WP_Query $query Current query.
	 * @return void
	 */
	public function filter_appointments( WP_Query $query ): void {
		if ( ! is_admin() || ! $query->is_main_query() || Post_Types::APPOINTMENT !== $query->get( 'post_type' ) ) {
			return;
		}

		$meta_query = $query->get( 'meta_query' );
		$meta_query = is_array( $meta_query ) ? $meta_query : array();
		$status     = isset( $_GET['simple_booking_status_filter'] ) ? sanitize_key( wp_unslash( $_GET['simple_booking_status_filter'] ) ) : '';

		$statuses = Appointment_Statuses::get_labels();

		if ( isset( $statuses[ $status ] ) ) {
			if ( Appointment_Statuses::PENDING === $status ) {
				$meta_query[] = array(
					'relation' => 'OR',
					array(
						'key'     => '_simple_booking_status',
						'value'   => Appointment_Statuses::PENDING,
						'compare' => '=',
					),
					array(
						'key'     => '_simple_booking_status',
						'compare' => 'NOT EXISTS',
					),
				);
			} else {
				$meta_query[] = array(
					'key'     => '_simple_booking_status',
					'value'   => $status,
					'compare' => '=',
				);
			}
		}

		$this->add_id_filter_to_meta_query( $meta_query, 'simple_booking_provider_filter', '_simple_booking_provider_id' );
		$this->add_id_filter_to_meta_query( $meta_query, 'simple_booking_service_filter', '_simple_booking_service_id' );

		$date = isset( $_GET['simple_booking_date_filter'] ) ? sanitize_text_field( wp_unslash( $_GET['simple_booking_date_filter'] ) ) : '';

		if ( $this->is_valid_date( $date ) ) {
			$meta_query[] = array(
				'key'     => '_simple_booking_appointment_date',
				'value'   => $date,
				'compare' => '=',
			);
		}

		if ( $meta_query ) {
			$query->set( 'meta_query', $meta_query );
		}
	}

	/**
	 * Append a rescheduling error to the post-save redirect.
	 *
	 * @param string $location Redirect URL.
	 * @return string
	 */
	public function add_appointment_error_to_redirect( string $location ): string {
		$location = remove_query_arg( 'simple_booking_admin_error', $location );

		if ( ! $this->appointment_error ) {
			return $location;
		}

		$location = remove_query_arg( 'message', $location );
		return add_query_arg( 'simple_booking_admin_error', $this->appointment_error, $location );
	}

	/**
	 * Display an appointment scheduling error.
	 *
	 * @return void
	 */
	public function render_appointment_notice(): void {
		$screen = get_current_screen();

		if ( ! $screen || Post_Types::APPOINTMENT !== $screen->post_type ) {
			return;
		}

		$code = isset( $_GET['simple_booking_admin_error'] ) ? sanitize_key( wp_unslash( $_GET['simple_booking_admin_error'] ) ) : '';
		$messages = array(
			'relationship'   => __( 'The appointment was not updated. Choose an active provider assigned to the selected service.', 'simple-booking' ),
			'time'           => __( 'The appointment was not updated. Enter a valid date and start time.', 'simple-booking' ),
			'past'           => __( 'The appointment was not updated because the selected time is in the past.', 'simple-booking' ),
			'minimum_notice' => __( 'The appointment was not updated because the selected time does not meet the minimum notice.', 'simple-booking' ),
			'advance_limit'  => __( 'The appointment was not updated because the selected date exceeds the advance-booking limit.', 'simple-booking' ),
			'closed'         => __( 'The appointment was not updated because the business is closed or the provider is not working on that date.', 'simple-booking' ),
			'unavailable'    => __( 'The appointment was not updated because that time is unavailable.', 'simple-booking' ),
			'booking_busy'   => __( 'The appointment was not updated because the schedule is busy. Please try again.', 'simple-booking' ),
		);

		if ( ! isset( $messages[ $code ] ) ) {
			return;
		}
		?>
		<div class="notice notice-error"><p><?php echo esc_html( $messages[ $code ] ); ?></p></div>
		<?php
	}

	/**
	 * Add service list columns.
	 *
	 * @param array<string, string> $columns Existing columns.
	 * @return array<string, string>
	 */
	public function service_columns( array $columns ): array {
		$columns['duration'] = __( 'Duration', 'simple-booking' );
		$columns['active']   = __( 'Active', 'simple-booking' );
		return $columns;
	}

	/**
	 * Render service list values.
	 *
	 * @param string $column  Column key.
	 * @param int    $post_id Service ID.
	 * @return void
	 */
	public function render_service_column( string $column, int $post_id ): void {
		if ( 'duration' === $column ) {
			printf( esc_html__( '%d minutes', 'simple-booking' ), absint( get_post_meta( $post_id, '_simple_booking_duration', true ) ) );
		}

		if ( 'active' === $column ) {
			echo '1' === get_post_meta( $post_id, '_simple_booking_active', true ) ? esc_html__( 'Yes', 'simple-booking' ) : esc_html__( 'No', 'simple-booking' );
		}
	}

	/**
	 * Add provider list columns.
	 *
	 * @param array<string, string> $columns Existing columns.
	 * @return array<string, string>
	 */
	public function provider_columns( array $columns ): array {
		$columns['schedule'] = __( 'Schedule', 'simple-booking' );
		$columns['active']   = __( 'Active', 'simple-booking' );
		return $columns;
	}

	/**
	 * Render provider list values.
	 *
	 * @param string $column  Column key.
	 * @param int    $post_id Provider ID.
	 * @return void
	 */
	public function render_provider_column( string $column, int $post_id ): void {
		if ( 'schedule' === $column ) {
			echo 'custom' === get_post_meta( $post_id, '_simple_booking_schedule_mode', true ) ? esc_html__( 'Custom', 'simple-booking' ) : esc_html__( 'Business hours', 'simple-booking' );
		}

		if ( 'active' === $column ) {
			echo '1' === get_post_meta( $post_id, '_simple_booking_active', true ) ? esc_html__( 'Yes', 'simple-booking' ) : esc_html__( 'No', 'simple-booking' );
		}
	}

	/**
	 * Render a text field table row.
	 *
	 * @param string  $key   Meta key suffix.
	 * @param string  $label Field label.
	 * @param WP_Post $post  Current post.
	 * @param string  $type  Input type.
	 * @return void
	 */
	private function render_text_row( string $key, string $label, WP_Post $post, string $type = 'text' ): void {
		$value = (string) get_post_meta( $post->ID, '_simple_booking_' . $key, true );
		$id    = 'simple-booking-' . str_replace( '_', '-', $key );
		?>
		<tr>
			<th scope="row"><label for="<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $label ); ?></label></th>
			<td><input id="<?php echo esc_attr( $id ); ?>" name="simple_booking_<?php echo esc_attr( $key ); ?>" type="<?php echo esc_attr( $type ); ?>" class="regular-text" value="<?php echo esc_attr( $value ); ?>"></td>
		</tr>
		<?php
	}

	/**
	 * Render a generated appointment value.
	 *
	 * @param string  $key   Meta key suffix.
	 * @param string  $label Field label.
	 * @param WP_Post $post  Current post.
	 * @return void
	 */
	private function render_readonly_row( string $key, string $label, WP_Post $post ): void {
		$value = (string) get_post_meta( $post->ID, '_simple_booking_' . $key, true );
		?>
		<tr>
			<th scope="row"><?php echo esc_html( $label ); ?></th>
			<td><code><?php echo esc_html( $value ?: '—' ); ?></code><p class="description"><?php esc_html_e( 'Calculated automatically from the selected service duration.', 'simple-booking' ); ?></p></td>
		</tr>
		<?php
	}

	/**
	 * Render a textarea table row.
	 *
	 * @param string  $key   Meta key suffix.
	 * @param string  $label Field label.
	 * @param WP_Post $post  Current post.
	 * @return void
	 */
	private function render_textarea_row( string $key, string $label, WP_Post $post ): void {
		$value = (string) get_post_meta( $post->ID, '_simple_booking_' . $key, true );
		$id    = 'simple-booking-' . str_replace( '_', '-', $key );
		?>
		<tr>
			<th scope="row"><label for="<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $label ); ?></label></th>
			<td><textarea id="<?php echo esc_attr( $id ); ?>" name="simple_booking_<?php echo esc_attr( $key ); ?>" class="large-text" rows="4"><?php echo esc_textarea( $value ); ?></textarea></td>
		</tr>
		<?php
	}

	/**
	 * Render a dropdown of a related post type.
	 *
	 * @param string $key         Field key.
	 * @param string $post_type   Related post type.
	 * @param int    $selected_id Selected post ID.
	 * @param string $placeholder Empty option label.
	 * @return void
	 */
	private function render_post_select( string $key, string $post_type, int $selected_id, string $placeholder ): void {
		$posts = get_posts(
			array(
				'post_type'      => $post_type,
				'post_status'    => array( 'publish', 'draft', 'private' ),
				'posts_per_page' => -1,
				'orderby'        => 'title',
				'order'          => 'ASC',
			)
		);
		?>
		<select id="simple-booking-<?php echo esc_attr( str_replace( '_', '-', $key ) ); ?>" name="simple_booking_<?php echo esc_attr( $key ); ?>">
			<option value="0"><?php echo esc_html( $placeholder ); ?></option>
			<?php foreach ( $posts as $related_post ) : ?>
				<option value="<?php echo esc_attr( $related_post->ID ); ?>" <?php selected( $selected_id, $related_post->ID ); ?>><?php echo esc_html( $related_post->post_title ); ?></option>
			<?php endforeach; ?>
		</select>
		<?php
	}

	/**
	 * Render a service or provider list filter.
	 *
	 * @param string $name        Field name.
	 * @param string $post_type   Related post type.
	 * @param int    $selected_id Selected ID.
	 * @param string $placeholder Empty option label.
	 * @return void
	 */
	private function render_filter_post_select( string $name, string $post_type, int $selected_id, string $placeholder ): void {
		$posts = get_posts(
			array(
				'post_type'      => $post_type,
				'post_status'    => array( 'publish', 'draft', 'private' ),
				'posts_per_page' => -1,
				'orderby'        => 'title',
				'order'          => 'ASC',
			)
		);
		?>
		<select name="<?php echo esc_attr( $name ); ?>" aria-label="<?php echo esc_attr( $placeholder ); ?>">
			<option value="0"><?php echo esc_html( $placeholder ); ?></option>
			<?php foreach ( $posts as $related_post ) : ?>
				<option value="<?php echo esc_attr( $related_post->ID ); ?>" <?php selected( $selected_id, $related_post->ID ); ?>><?php echo esc_html( $related_post->post_title ); ?></option>
			<?php endforeach; ?>
		</select>
		<?php
	}

	/**
	 * Render provider assignment checkboxes.
	 *
	 * @param int[] $selected_ids Selected providers.
	 * @return void
	 */
	private function render_provider_checkboxes( array $selected_ids ): void {
		$providers = get_posts(
			array(
				'post_type'      => Post_Types::PROVIDER,
				'post_status'    => array( 'publish', 'draft', 'private' ),
				'posts_per_page' => -1,
				'orderby'        => 'title',
				'order'          => 'ASC',
			)
		);

		if ( ! $providers ) {
			echo esc_html__( 'Create a provider before assigning one to this service.', 'simple-booking' );
			return;
		}

		foreach ( $providers as $provider ) {
			?>
			<label style="display:block;margin-bottom:6px;">
				<input type="checkbox" name="simple_booking_provider_ids[]" value="<?php echo esc_attr( $provider->ID ); ?>" <?php checked( in_array( $provider->ID, $selected_ids, true ) ); ?>>
				<?php echo esc_html( $provider->post_title ); ?>
			</label>
			<?php
		}
	}

	/**
	 * Read and sanitize appointment management fields.
	 *
	 * @return array{service_id:int,provider_id:int,date:string,time:string,status:string}
	 */
	private function get_submitted_appointment_data(): array {
		$status = isset( $_POST['simple_booking_status'] ) ? sanitize_key( wp_unslash( $_POST['simple_booking_status'] ) ) : Appointment_Statuses::PENDING;

		return array(
			'service_id'  => isset( $_POST['simple_booking_service_id'] ) ? absint( wp_unslash( $_POST['simple_booking_service_id'] ) ) : 0,
			'provider_id' => isset( $_POST['simple_booking_provider_id'] ) ? absint( wp_unslash( $_POST['simple_booking_provider_id'] ) ) : 0,
			'date'        => isset( $_POST['simple_booking_appointment_date'] ) ? sanitize_text_field( wp_unslash( $_POST['simple_booking_appointment_date'] ) ) : '',
			'time'        => isset( $_POST['simple_booking_start_time'] ) ? sanitize_text_field( wp_unslash( $_POST['simple_booking_start_time'] ) ) : '',
			'status'      => Appointment_Statuses::normalize( $status ),
		);
	}

	/**
	 * Validate a service/provider relationship for scheduling.
	 *
	 * @param int $service_id  Service ID.
	 * @param int $provider_id Provider ID.
	 * @return bool
	 */
	private function validate_appointment_relationship( int $service_id, int $provider_id ): bool {
		return $this->availability->is_active_post( $service_id, Post_Types::SERVICE )
			&& $this->availability->is_active_post( $provider_id, Post_Types::PROVIDER )
			&& $this->availability->is_provider_assigned( $service_id, $provider_id );
	}

	/**
	 * Add a numeric relationship filter to an appointment meta query.
	 *
	 * @param array<int|string, mixed> $meta_query Meta query clauses.
	 * @param string                   $request_key Request parameter.
	 * @param string                   $meta_key    Appointment meta key.
	 * @return void
	 */
	private function add_id_filter_to_meta_query( array &$meta_query, string $request_key, string $meta_key ): void {
		$value = isset( $_GET[ $request_key ] ) ? absint( wp_unslash( $_GET[ $request_key ] ) ) : 0;

		if ( $value > 0 ) {
			$meta_query[] = array(
				'key'     => $meta_key,
				'value'   => $value,
				'compare' => '=',
				'type'    => 'NUMERIC',
			);
		}
	}

	/**
	 * Check whether a meta box submission may be saved.
	 *
	 * @param int    $post_id      Post ID.
	 * @param string $nonce_field  Nonce field name.
	 * @param string $nonce_action Nonce action.
	 * @return bool
	 */
	private function can_save( int $post_id, string $nonce_field, string $nonce_action ): bool {
		if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) {
			return false;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return false;
		}

		if ( ! isset( $_POST[ $nonce_field ] ) ) {
			return false;
		}

		$nonce = sanitize_text_field( wp_unslash( $_POST[ $nonce_field ] ) );
		return wp_verify_nonce( $nonce, $nonce_action ) > 0;
	}

	/**
	 * Save a sanitized text field.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $key     Field key.
	 * @return void
	 */
	private function save_text( int $post_id, string $key ): void {
		$field = 'simple_booking_' . $key;
		$value = isset( $_POST[ $field ] ) ? sanitize_text_field( wp_unslash( $_POST[ $field ] ) ) : '';
		update_post_meta( $post_id, '_simple_booking_' . $key, $value );
	}

	/**
	 * Save a sanitized textarea field.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $key     Field key.
	 * @return void
	 */
	private function save_textarea( int $post_id, string $key ): void {
		$field = 'simple_booking_' . $key;
		$value = isset( $_POST[ $field ] ) ? sanitize_textarea_field( wp_unslash( $_POST[ $field ] ) ) : '';
		update_post_meta( $post_id, '_simple_booking_' . $key, $value );
	}

	/**
	 * Validate an ISO calendar date.
	 *
	 * @param string $date Date string.
	 * @return bool
	 */
	private function is_valid_date( string $date ): bool {
		$parsed = \DateTimeImmutable::createFromFormat( '!Y-m-d', $date, wp_timezone() );
		return false !== $parsed && $parsed->format( 'Y-m-d' ) === $date;
	}

	/**
	 * Sanitize a 24-hour time value.
	 *
	 * @param mixed $time Untrusted time.
	 * @return string
	 */
	private function sanitize_time( mixed $time ): string {
		$time = sanitize_text_field( (string) $time );
		return preg_match( '/^(?:[01]\d|2[0-3]):[0-5]\d$/', $time ) ? $time : '';
	}

	/**
	 * Output a related post title.
	 *
	 * @param int    $post_id  Appointment ID.
	 * @param string $meta_key Relationship meta key.
	 * @return void
	 */
	private function render_related_title( int $post_id, string $meta_key ): void {
		$related_id = absint( get_post_meta( $post_id, $meta_key, true ) );
		echo esc_html( $related_id ? get_the_title( $related_id ) : '—' );
	}

}

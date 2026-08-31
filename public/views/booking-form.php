<?php
/**
 * Booking form view.
 *
 * @package SimpleBooking
 *
 * @var WP_Post[]                                               $services
 * @var WP_Post[]                                               $providers
 * @var array<int, int[]>                                       $provider_services
 * @var array<int, array{type:string,text:string,field:string}> $messages
 * @var string[]                                                $invalid_fields
 * @var string                                                  $minimum_date
 * @var string                                                  $maximum_date
 * @var string                                                  $return_url
 * @var bool                                                    $consent_required
 * @var string                                                  $privacy_url
 * @var string                                                  $form_id
 * @var array<string, string>                                   $field_ids
 * @var array<string, string>                                   $presentation_labels
 */

use SimpleBooking\Booking;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$has_errors = (bool) array_filter( $messages, static fn( array $message ): bool => 'error' === $message['type'] );
$is_invalid = static fn( string $canonical_id ): bool => in_array( $canonical_id, $invalid_fields, true );
$described_by = static function ( string $canonical_id ) use ( $is_invalid, $field_ids ): string {
	return $is_invalid( $canonical_id ) ? ' aria-invalid="true" aria-describedby="' . esc_attr( $field_ids['errors'] ) . '"' : '';
};
?>
<section
	id="<?php echo esc_attr( $form_id ); ?>"
	class="simple-booking"
	data-simple-booking
	data-provider-prompt="<?php esc_attr_e( 'Choose a service first to see its providers.', 'simple-booking' ); ?>"
	data-no-providers="<?php esc_attr_e( 'No providers are currently assigned to this service.', 'simple-booking' ); ?>"
>
	<div class="simple-booking__header">
		<h2 class="simple-booking__title"><?php echo esc_html( $presentation_labels['book_appointment'] ); ?></h2>
		<p class="simple-booking__intro"><?php esc_html_e( 'Choose a time, add your details, and review the request before submitting.', 'simple-booking' ); ?></p>
	</div>

	<?php if ( $messages ) : ?>
		<div
			<?php if ( $has_errors ) : ?>id="<?php echo esc_attr( $field_ids['errors'] ); ?>"<?php endif; ?>
			class="simple-booking__message simple-booking__message--<?php echo esc_attr( $has_errors ? 'error' : 'success' ); ?>"
			role="<?php echo esc_attr( $has_errors ? 'alert' : 'status' ); ?>"
			aria-live="polite"
			data-simple-booking-messages
			data-status="<?php echo esc_attr( $has_errors ? 'error' : 'success' ); ?>"
			<?php if ( $has_errors ) : ?>tabindex="-1"<?php endif; ?>
		>
			<?php if ( $has_errors ) : ?>
				<p><strong><?php esc_html_e( 'Please correct the following:', 'simple-booking' ); ?></strong></p>
				<ul>
					<?php foreach ( $messages as $message ) : ?>
						<?php $message_key = str_replace( 'simple-booking-', '', $message['field'] ); ?>
						<li><?php if ( $message['field'] && isset( $field_ids[ $message_key ] ) ) : ?><a href="#<?php echo esc_attr( $field_ids[ $message_key ] ); ?>"><?php echo esc_html( $message['text'] ); ?></a><?php else : ?><?php echo esc_html( $message['text'] ); ?><?php endif; ?></li>
					<?php endforeach; ?>
				</ul>
			<?php else : ?>
				<p><?php echo esc_html( $messages[0]['text'] ); ?></p>
			<?php endif; ?>
		</div>
	<?php endif; ?>

	<?php if ( ! $services || ! $providers ) : ?>
		<p class="simple-booking__message simple-booking__message--notice" role="status"><?php esc_html_e( 'Online booking is not available yet. Please contact the business directly.', 'simple-booking' ); ?></p>
	<?php else : ?>
		<form class="simple-booking__form" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post" data-simple-booking-form>
			<input type="hidden" name="action" value="simple_booking_submit">
			<input type="hidden" name="simple_booking_return_url" value="<?php echo esc_url( $return_url ); ?>">
			<?php wp_nonce_field( Booking::get_nonce_action(), Booking::get_nonce_field() ); ?>

			<ol class="simple-booking__steps" aria-label="<?php esc_attr_e( 'Booking progress', 'simple-booking' ); ?>" data-simple-booking-progress hidden>
				<li data-simple-booking-progress-step="1" aria-current="step"><?php esc_html_e( 'Appointment', 'simple-booking' ); ?></li>
				<li data-simple-booking-progress-step="2"><?php esc_html_e( 'Your details', 'simple-booking' ); ?></li>
				<li data-simple-booking-progress-step="3"><?php esc_html_e( 'Review', 'simple-booking' ); ?></li>
			</ol>

			<fieldset class="simple-booking__fieldset" data-simple-booking-step="1">
				<legend class="simple-booking__legend" tabindex="-1" data-simple-booking-step-heading><?php esc_html_e( '1. Appointment preferences', 'simple-booking' ); ?></legend>
				<div class="simple-booking__grid">
					<div class="simple-booking__field simple-booking__field--full">
						<label class="simple-booking__label" for="<?php echo esc_attr( $field_ids['service'] ); ?>"><?php echo esc_html( $presentation_labels['service'] ); ?> <span aria-hidden="true">*</span></label>
						<select class="simple-booking__control" id="<?php echo esc_attr( $field_ids['service'] ); ?>" name="simple_booking_service_id" required data-simple-booking-service<?php echo $described_by( 'simple-booking-service' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
							<option value=""><?php esc_html_e( 'Choose a service', 'simple-booking' ); ?></option>
							<?php foreach ( $services as $service ) : ?>
								<?php $price = (string) get_post_meta( $service->ID, '_simple_booking_price_display', true ); ?>
								<option value="<?php echo esc_attr( $service->ID ); ?>"><?php echo esc_html( $service->post_title . ( $price ? ' — ' . $price : '' ) ); ?></option>
							<?php endforeach; ?>
						</select>
					</div>
					<div class="simple-booking__field simple-booking__field--full">
						<label class="simple-booking__label" for="<?php echo esc_attr( $field_ids['provider'] ); ?>"><?php echo esc_html( $presentation_labels['provider'] ); ?> <span aria-hidden="true">*</span></label>
						<select class="simple-booking__control" id="<?php echo esc_attr( $field_ids['provider'] ); ?>" name="simple_booking_provider_id" required data-simple-booking-provider<?php echo $described_by( 'simple-booking-provider' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
							<option value=""><?php esc_html_e( 'Choose a provider', 'simple-booking' ); ?></option>
							<?php foreach ( $providers as $provider ) : ?>
								<option value="<?php echo esc_attr( $provider->ID ); ?>" data-services="<?php echo esc_attr( implode( ',', $provider_services[ $provider->ID ] ?? array() ) ); ?>"><?php echo esc_html( $provider->post_title ); ?></option>
							<?php endforeach; ?>
						</select>
						<p class="simple-booking__hint" data-simple-booking-provider-hint><?php esc_html_e( 'Choose a provider assigned to the selected service.', 'simple-booking' ); ?></p>
					</div>
					<div class="simple-booking__field">
						<label class="simple-booking__label" for="<?php echo esc_attr( $field_ids['date'] ); ?>"><?php esc_html_e( 'Preferred date', 'simple-booking' ); ?> <span aria-hidden="true">*</span></label>
						<input class="simple-booking__control" id="<?php echo esc_attr( $field_ids['date'] ); ?>" name="simple_booking_date" type="date" min="<?php echo esc_attr( $minimum_date ); ?>" max="<?php echo esc_attr( $maximum_date ); ?>" required data-simple-booking-date<?php echo $described_by( 'simple-booking-date' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
					</div>
					<div class="simple-booking__field">
						<label class="simple-booking__label" for="<?php echo esc_attr( $field_ids['time'] ); ?>"><?php esc_html_e( 'Available time', 'simple-booking' ); ?> <span aria-hidden="true">*</span></label>
						<select class="simple-booking__control" id="<?php echo esc_attr( $field_ids['time'] ); ?>" name="simple_booking_time" required disabled data-simple-booking-time aria-describedby="<?php echo esc_attr( $field_ids['time-status'] . ( $is_invalid( 'simple-booking-time' ) ? ' ' . $field_ids['errors'] : '' ) ); ?>"<?php if ( $is_invalid( 'simple-booking-time' ) ) : ?> aria-invalid="true"<?php endif; ?>><option value=""><?php esc_html_e( 'Choose a service, provider, and date first', 'simple-booking' ); ?></option></select>
						<p id="<?php echo esc_attr( $field_ids['time-status'] ); ?>" class="simple-booking__hint" role="status" aria-live="polite" data-simple-booking-time-status></p>
					</div>
				</div>
				<div class="simple-booking__actions" data-simple-booking-navigation hidden><button class="simple-booking__button simple-booking__button--primary" type="button" data-simple-booking-next><?php esc_html_e( 'Continue to your details', 'simple-booking' ); ?></button></div>
			</fieldset>

			<fieldset class="simple-booking__fieldset" data-simple-booking-step="2">
				<legend class="simple-booking__legend" tabindex="-1" data-simple-booking-step-heading><?php echo esc_html( sprintf( '2. %s', $presentation_labels['customer_details'] ) ); ?></legend>
				<div class="simple-booking__grid">
					<div class="simple-booking__field">
						<label class="simple-booking__label" for="<?php echo esc_attr( $field_ids['first-name'] ); ?>"><?php esc_html_e( 'First name', 'simple-booking' ); ?> <span aria-hidden="true">*</span></label>
						<input class="simple-booking__control" id="<?php echo esc_attr( $field_ids['first-name'] ); ?>" name="simple_booking_first_name" type="text" autocomplete="given-name" maxlength="100" required<?php echo $described_by( 'simple-booking-first-name' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
					</div>
					<div class="simple-booking__field">
						<label class="simple-booking__label" for="<?php echo esc_attr( $field_ids['last-name'] ); ?>"><?php esc_html_e( 'Last name', 'simple-booking' ); ?> <span aria-hidden="true">*</span></label>
						<input class="simple-booking__control" id="<?php echo esc_attr( $field_ids['last-name'] ); ?>" name="simple_booking_last_name" type="text" autocomplete="family-name" maxlength="100" required<?php echo $described_by( 'simple-booking-last-name' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
					</div>
					<div class="simple-booking__field">
						<label class="simple-booking__label" for="<?php echo esc_attr( $field_ids['email'] ); ?>"><?php esc_html_e( 'Email', 'simple-booking' ); ?> <span aria-hidden="true">*</span></label>
						<input class="simple-booking__control" id="<?php echo esc_attr( $field_ids['email'] ); ?>" name="simple_booking_email" type="email" autocomplete="email" maxlength="254" required<?php echo $described_by( 'simple-booking-email' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
					</div>
					<div class="simple-booking__field">
						<label class="simple-booking__label" for="<?php echo esc_attr( $field_ids['phone'] ); ?>"><?php esc_html_e( 'Phone', 'simple-booking' ); ?> <span aria-hidden="true">*</span></label>
						<input class="simple-booking__control" id="<?php echo esc_attr( $field_ids['phone'] ); ?>" name="simple_booking_phone" type="tel" autocomplete="tel" maxlength="50" required<?php echo $described_by( 'simple-booking-phone' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
					</div>
					<div class="simple-booking__field simple-booking__field--full">
						<label class="simple-booking__label" for="<?php echo esc_attr( $field_ids['notes'] ); ?>"><?php esc_html_e( 'Notes', 'simple-booking' ); ?> <span class="simple-booking__optional"><?php esc_html_e( '(optional)', 'simple-booking' ); ?></span></label>
						<textarea class="simple-booking__control" id="<?php echo esc_attr( $field_ids['notes'] ); ?>" name="simple_booking_notes" rows="4" maxlength="2000"></textarea>
					</div>
				</div>
				<?php if ( $consent_required ) : ?>
					<div class="simple-booking__consent">
						<input id="<?php echo esc_attr( $field_ids['consent'] ); ?>" name="simple_booking_consent" type="checkbox" value="1" required<?php echo $described_by( 'simple-booking-consent' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
						<label for="<?php echo esc_attr( $field_ids['consent'] ); ?>"><?php esc_html_e( 'I consent to the business using this information to manage my appointment request.', 'simple-booking' ); ?> <?php if ( $privacy_url ) : ?><a href="<?php echo esc_url( $privacy_url ); ?>"><?php esc_html_e( 'Privacy policy', 'simple-booking' ); ?></a><?php endif; ?></label>
					</div>
				<?php endif; ?>
				<div class="simple-booking__actions" data-simple-booking-navigation hidden><button class="simple-booking__button" type="button" data-simple-booking-back><?php esc_html_e( 'Back', 'simple-booking' ); ?></button><button class="simple-booking__button simple-booking__button--primary" type="button" data-simple-booking-next><?php esc_html_e( 'Review appointment', 'simple-booking' ); ?></button></div>
			</fieldset>

			<fieldset class="simple-booking__fieldset" data-simple-booking-step="3">
				<legend class="simple-booking__legend" tabindex="-1" data-simple-booking-step-heading><?php esc_html_e( '3. Review your request', 'simple-booking' ); ?></legend>
				<p><?php esc_html_e( 'Check these details before submitting. The appointment will be pending until the business confirms it.', 'simple-booking' ); ?></p>
				<dl class="simple-booking__summary">
					<div><dt><?php echo esc_html( $presentation_labels['service'] ); ?></dt><dd data-simple-booking-review="service">—</dd></div>
					<div><dt><?php echo esc_html( $presentation_labels['provider'] ); ?></dt><dd data-simple-booking-review="provider">—</dd></div>
					<div><dt><?php esc_html_e( 'Date', 'simple-booking' ); ?></dt><dd data-simple-booking-review="date">—</dd></div>
					<div><dt><?php esc_html_e( 'Time', 'simple-booking' ); ?></dt><dd data-simple-booking-review="time">—</dd></div>
					<div><dt><?php echo esc_html( $presentation_labels['customer'] ); ?></dt><dd data-simple-booking-review="customer">—</dd></div>
					<div><dt><?php esc_html_e( 'Email', 'simple-booking' ); ?></dt><dd data-simple-booking-review="email">—</dd></div>
					<div><dt><?php esc_html_e( 'Phone', 'simple-booking' ); ?></dt><dd data-simple-booking-review="phone">—</dd></div>
					<div><dt><?php esc_html_e( 'Notes', 'simple-booking' ); ?></dt><dd data-simple-booking-review="notes">—</dd></div>
				</dl>
				<p class="simple-booking__required"><span aria-hidden="true">*</span> <?php esc_html_e( 'Required fields', 'simple-booking' ); ?></p>
				<noscript><p class="simple-booking__message simple-booking__message--notice"><?php esc_html_e( 'JavaScript is required to load current appointment availability.', 'simple-booking' ); ?></p></noscript>
				<div class="simple-booking__actions"><button class="simple-booking__button" type="button" data-simple-booking-back hidden><?php esc_html_e( 'Back', 'simple-booking' ); ?></button><button class="simple-booking__button simple-booking__button--primary" type="submit"><?php echo esc_html( $presentation_labels['request_appointment'] ); ?></button></div>
			</fieldset>
		</form>
	<?php endif; ?>
</section>

# Simple Booking

Simple Booking is a reusable WordPress plugin for managing appointment requests, services, providers, dynamic scheduling, notifications, and business workflows. Version `1.0.0` completes Phase 5 of the first production release.

## Requirements

- WordPress 6.0 or newer
- PHP 8.0 or newer

## Installation

1. Upload the `simple-booking` folder to `wp-content/plugins/`.
2. Activate **Simple Booking** from **Plugins** in WordPress admin.
3. Open **Simple Booking** in the admin menu.
4. Configure **Simple Booking → Settings**.
5. Create providers and services before accepting appointments.

## Current features

- Modular, namespaced plugin bootstrap
- Safe activation and deactivation routines
- Private `simple_appointment` post type
- Admin-managed `simple_service` post type
- Admin-managed `simple_provider` post type
- Appointment customer and scheduling fields
- Service duration, buffer, price display, provider assignment, and active state
- Provider description, profile image, and active state
- Useful appointment, service, and provider admin columns
- Appointment filters for status, provider, service, and appointment date
- Pending, Confirmed, Completed, and Cancelled business statuses
- Availability-validated admin rescheduling with automatic end-time recalculation
- Cancellation releases availability immediately
- Administrator-only appointment capabilities by default
- Nonce, capability, validation, sanitization, and output escaping safeguards
- Non-destructive deactivation and uninstall behavior
- `[simple_booking]` frontend shortcode
- Active service and assigned-provider selection
- Preferred appointment date and time fields using the WordPress site timezone
- Configurable weekday business hours
- Business-wide closed dates
- Provider-specific weekday schedules with optional daily breaks and business-hours fallback
- Configurable slot interval, minimum notice, and maximum advance-booking period
- Dynamic available-time loading through a nonce-protected WordPress AJAX endpoint
- Service duration and buffer-aware slot generation
- Existing Pending, Confirmed, and Completed appointments block overlapping slots
- Cancelled appointments do not block availability
- Atomic provider/date locking and final server-side revalidation to prevent simultaneous double bookings
- Configurable business contact details and notification preferences
- Plain-text customer booking emails and business notification emails through `wp_mail()`
- Configurable email subjects, customer message, and cancellation message with template tokens
- Customer cancellation email when staff change an appointment to Cancelled
- Accessible three-step booking flow with appointment, customer information, and review screens
- Keyboard navigation, visible focus, field-linked errors, live availability feedback, and unique form IDs
- Vanilla JavaScript provider filtering with a no-JavaScript fallback
- Server-side validation for required fields, relationships, activity, dates, and times
- Private appointment creation with a Pending business status
- Post/redirect/get submission flow that prevents browser-refresh duplicates
- Frontend assets loaded only where the shortcode is present whenever practical

## Folder structure

```text
simple-booking/
├── admin/
│   └── class-admin.php
├── assets/
│   ├── css/simple-booking.css
│   ├── images/
│   ├── js/simple-booking.js
│   └── scss/simple-booking.scss
├── includes/
│   ├── class-activator.php
│   ├── class-appointment-statuses.php
│   ├── class-availability.php
│   ├── class-assets.php
│   ├── class-booking.php
│   ├── class-deactivator.php
│   ├── class-email.php
│   ├── class-labels.php
│   ├── class-plugin.php
│   ├── class-post-types.php
│   └── class-settings.php
├── public/
│   ├── views/booking-form.php
│   └── class-public.php
├── languages/
├── simple-booking.php
├── package.json
├── README.md
└── uninstall.php
```

## Admin usage

### Providers

Add each provider under **Simple Booking → Providers**. The title is the provider name, the editor stores a short description, and the featured image is the optional profile image. Providers inherit business hours by default. Enable a custom provider schedule to configure working hours and one optional break for each weekday.

### Services

Add services under **Simple Booking → Services**. Configure duration, buffer time, optional price display text, active state, and assigned providers.

### Appointments

Appointments are private and have no public single page, archive, REST endpoint, or search visibility. Staff can review customer details, change status, add internal notes, cancel, and reschedule an appointment. The appointment list can be filtered by status, provider, service, or appointment date.

The post publication state remains a WordPress administrative state. The appointment's business state is stored separately as Pending, Confirmed, Completed, or Cancelled. Cancelled appointments do not block availability.

When rescheduling, choose a service, assigned provider, date, and start time, then update the appointment. The plugin validates the same scheduling rules used by the public form, excludes the appointment being edited from its conflict check, locks the new provider/date during the update, and recalculates the end time and buffer snapshot. If validation fails, the saved appointment schedule is preserved and an admin error explains why.

### Scheduling settings

Open **Simple Booking → Settings** to configure the slot interval, minimum notice, maximum advance-booking period, opening/closing time for each weekday, and business-wide closed dates. Enter closed dates one per line in `YYYY-MM-DD` format. Scheduling uses the timezone configured under **WordPress Settings → General**.

Business closed dates override all schedules. A provider's custom schedule overrides business weekday hours; providers set to inherit continue using the business schedule.

### Email settings

Under **Simple Booking → Settings**, configure the business name, contact email, phone, business notification recipient, email toggles, subjects, customer message, and cancellation message.

Message fields support these tokens:

```text
{customer_name}
{service}
{provider}
{date}
{time}
{business_name}
```

Customer emails include the customer name, service, provider, appointment date/time, status, and business contact details. Business emails include customer contact details and a direct WordPress admin management link. Email delivery depends on the site's WordPress mail configuration.

## Booking shortcode

Add the booking form to a page with:

```text
[simple_booking]
```

The form shows published services and providers that are active. A provider must be assigned to the selected service in the service settings. Customers choose an appointment, enter their details, and review the request before submission. Submitted appointments are stored privately with a Pending status and do not require a customer WordPress account.

Availability is calculated from closed dates, business or provider hours, provider breaks, slot interval, minimum notice, maximum booking range, service duration and buffer, and the provider's existing appointments. The same rules are checked again under a provider/date lock immediately before creation. JavaScript is required to load live time choices and the review-step enhancement; all authoritative validation remains on the server.

## Styling customization

All frontend selectors are scoped beneath `.simple-booking`. Themes can override the included custom properties, for example:

```css
.simple-booking {
  --simple-booking-primary: #155e75;
  --simple-booking-primary-hover: #164e63;
  --simple-booking-focus: #1d4ed8;
}
```

## Terminology customization

Generic labels can be replaced for a site's presentation without changing internal identifiers or booking logic. For example:

```php
add_filter(
	'simple_booking_labels',
	static function ( array $labels ): array {
		$labels['provider']  = 'Technician';
		$labels['providers'] = 'Technicians';
		$labels['customer']  = 'Customer';

		return $labels;
	}
);
```

The filter supports `appointment`, `appointments`, `service`, `services`, `provider`, `providers`, `customer`, `business`, `book_appointment`, `customer_details`, and `request_appointment`. Translation files can customize all other interface text.

## Dental Booking migration

This package uses a clean Simple Booking identifier set and does not automatically migrate data from the development-only Dental Booking plugin. Existing Dental Booking installations must not be replaced without a database backup and an explicit one-time migration.

The main persistent mappings are:

| Dental Booking | Simple Booking |
| --- | --- |
| `dental_appointment` | `simple_appointment` |
| `dental_service` | `simple_service` |
| `dental_provider` | `simple_provider` |
| `dental_booking_settings` | `simple_booking_settings` |
| `_dental_booking_*` metadata | `_simple_booking_*` metadata |

Hook names, form fields, AJAX actions, nonces, capabilities, asset handles, and the shortcode also moved from the `dental_booking` or `dental-booking` prefixes to `simple_booking` or `simple-booking`. A production migration should update each stored identifier in one controlled maintenance operation and verify relationships before the old plugin is removed.

## Development setup

Install the optional Sass development dependency and rebuild CSS with:

```bash
npm install
npm run build:css
```

PHP files can be checked with:

```bash
find simple-booking -name '*.php' -print0 | xargs -0 -n1 php -l
```

## Hooks

The plugin provides these extension points:

```php
do_action( 'simple_booking_before_create_appointment', $booking_data );
do_action( 'simple_booking_after_create_appointment', $appointment_id, $booking_data );
apply_filters( 'simple_booking_consent_required', true );
apply_filters( 'simple_booking_labels', $labels );
apply_filters( 'simple_booking_available_slots', $slots, $provider_id, $service_id, $date );
do_action( 'simple_booking_appointment_status_changed', $appointment_id, $new_status, $old_status );
apply_filters( 'simple_booking_confirmation_email_subject', $subject, $appointment_id );
apply_filters( 'simple_booking_confirmation_email_body', $body, $appointment_id );
apply_filters( 'simple_booking_customer_email_recipient', $recipient, $appointment_id, $context );
apply_filters( 'simple_booking_business_email_subject', $subject, $appointment_id );
apply_filters( 'simple_booking_business_email_body', $body, $appointment_id );
apply_filters( 'simple_booking_business_email_recipient', $recipient, $appointment_id );
apply_filters( 'simple_booking_cancellation_email_subject', $subject, $appointment_id );
apply_filters( 'simple_booking_cancellation_email_body', $body, $appointment_id );
```

The two actions receive sanitized booking data. Important values are validated again by the plugin before the appointment is created.

## Data removal

Deactivating the plugin never deletes appointments, services, providers, or settings.

Uninstalling also retains data by default. To explicitly remove all Simple Booking data during uninstall, define this before uninstalling:

```php
define( 'SIMPLE_BOOKING_REMOVE_DATA', true );
```

This deletion is permanent and includes all appointment records.

## Known limitations

The first production version intentionally does not include:

- Email delivery logging or retry queues
- Payments, insurance processing, medical records, SMS, or calendar synchronization

## Roadmap

- **Phase 2:** shortcode, service/provider selection, customer form, and appointment creation — complete
- **Phase 3:** business hours, dynamic availability, durations, and server-side conflict prevention — complete
- **Phase 4:** emails, advanced statuses, filters, cancellation, and rescheduling — complete
- **Phase 5:** improved UX, closed dates, provider schedules, accessibility, performance, and security review — complete

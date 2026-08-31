<?php
/**
 * Plugin Name:       Simple Booking
 * Description:       Reusable appointment requests with services and providers.
 * Version:           1.0.0
 * Requires at least: 6.0
 * Requires PHP:      8.0
 * Author:            DKNWeb
 * Text Domain:       simple-booking
 * Domain Path:       /languages
 *
 * @package SimpleBooking
 */

namespace SimpleBooking;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'SIMPLE_BOOKING_VERSION', '1.0.0' );
define( 'SIMPLE_BOOKING_FILE', __FILE__ );
define( 'SIMPLE_BOOKING_PATH', plugin_dir_path( __FILE__ ) );
define( 'SIMPLE_BOOKING_URL', plugin_dir_url( __FILE__ ) );

require_once SIMPLE_BOOKING_PATH . 'includes/class-labels.php';
require_once SIMPLE_BOOKING_PATH . 'includes/class-post-types.php';
require_once SIMPLE_BOOKING_PATH . 'includes/class-activator.php';
require_once SIMPLE_BOOKING_PATH . 'includes/class-deactivator.php';
require_once SIMPLE_BOOKING_PATH . 'includes/class-settings.php';
require_once SIMPLE_BOOKING_PATH . 'includes/class-appointment-statuses.php';
require_once SIMPLE_BOOKING_PATH . 'includes/class-availability.php';
require_once SIMPLE_BOOKING_PATH . 'includes/class-assets.php';
require_once SIMPLE_BOOKING_PATH . 'includes/class-booking.php';
require_once SIMPLE_BOOKING_PATH . 'includes/class-email.php';
require_once SIMPLE_BOOKING_PATH . 'admin/class-admin.php';
require_once SIMPLE_BOOKING_PATH . 'public/class-public.php';
require_once SIMPLE_BOOKING_PATH . 'includes/class-plugin.php';

register_activation_hook( SIMPLE_BOOKING_FILE, array( Activator::class, 'activate' ) );
register_deactivation_hook( SIMPLE_BOOKING_FILE, array( Deactivator::class, 'deactivate' ) );

( new Plugin() )->run();

<?php
/**
 * Plugin Name:       Simple Popup Manager
 * Plugin URI:        https://github.com/dknweb/simple-booking
 * Description:       A lightweight, reusable plugin for creating and scheduling simple website popups targeted to specific WordPress pages.
 * Version:           1.0.0
 * Requires PHP:      8.0
 * Requires at least: 6.0
 * Author:            Dan Biscaro
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       simple-popup-manager
 * Domain Path:       /languages
 *
 * @package SimplePopupManager
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

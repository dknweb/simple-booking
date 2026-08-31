<?php
/**
 * Plugin deactivation handler.
 *
 * @package SimpleBooking
 */

namespace SimpleBooking;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Runs deactivation tasks.
 */
final class Deactivator {

	/**
	 * Refresh rewrite rules without deleting plugin data.
	 *
	 * @return void
	 */
	public static function deactivate(): void {
		flush_rewrite_rules();
	}
}

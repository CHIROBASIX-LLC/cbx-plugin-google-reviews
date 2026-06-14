<?php
/**
 * Plugin Name: CHIROBASIX Google Reviews Widget
 * Plugin URI:  https://chirobasix.com
 * Description: Displays Google Reviews as a floating widget with slide-out panel. Self-hosted Elfsight replacement.
 * Version:     1.5.0
 * Author:      CHIROBASIX
 * Author URI:  https://chirobasix.com
 * License:     GPL-2.0+
 * Text Domain: cbx-google-reviews
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'CBXR_VERSION', '1.5.0' );
define( 'CBXR_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'CBXR_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

require_once CBXR_PLUGIN_DIR . 'includes/class-cbxr-api.php';
require_once CBXR_PLUGIN_DIR . 'includes/class-cbxr-admin.php';
require_once CBXR_PLUGIN_DIR . 'includes/class-cbxr-widget.php';
require_once CBXR_PLUGIN_DIR . 'includes/class-cbxr-updater.php';

/**
 * Initialize the plugin.
 */
function cbxr_init() {
	CBXR_Admin::instance();
	CBXR_Widget::instance();

	// GitHub auto-updater: checks for new releases and enables one-click updates.
	new CBXR_Updater( __FILE__, 'CHIROBASIX-LLC', 'cbx-google-reviews' );
}
add_action( 'plugins_loaded', 'cbxr_init' );

/**
 * On activation, schedule the cron job for review refresh.
 */
function cbxr_activate() {
	if ( ! wp_next_scheduled( 'cbxr_refresh_reviews_cron' ) ) {
		wp_schedule_event( time(), 'twicedaily', 'cbxr_refresh_reviews_cron' );
	}
}
register_activation_hook( __FILE__, 'cbxr_activate' );

/**
 * On deactivation, clear the cron job.
 */
function cbxr_deactivate() {
	wp_clear_scheduled_hook( 'cbxr_refresh_reviews_cron' );
}
register_deactivation_hook( __FILE__, 'cbxr_deactivate' );

/**
 * Cron callback: refresh reviews from Google.
 */
function cbxr_cron_refresh() {
	$api = new CBXR_API();
	$api->refresh_reviews();
}
add_action( 'cbxr_refresh_reviews_cron', 'cbxr_cron_refresh' );

/**
 * Auto-update this plugin whenever WordPress processes updates.
 * No manual "click Update" needed — new GitHub releases install automatically.
 */
add_filter( 'auto_update_plugin', function ( $update, $item ) {
	if ( isset( $item->slug ) && 'cbx-google-reviews' === $item->slug ) {
		return true;
	}
	return $update;
}, 10, 2 );

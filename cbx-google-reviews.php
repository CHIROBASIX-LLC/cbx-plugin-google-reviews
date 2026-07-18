<?php
/**
 * Plugin Name: CHIROBASIX Google Reviews Widget
 * Plugin URI:  https://chirobasix.com
 * Description: Displays Google Reviews as a floating widget with slide-out panel. Self-hosted Elfsight replacement.
 * Version:     1.5.6
 * Author:      CHIROBASIX
 * Author URI:  https://chirobasix.com
 * License:     GPL-2.0+
 * Text Domain: cbx-google-reviews
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'CBXR_VERSION', '1.5.6' );
define( 'CBXR_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'CBXR_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

/**
 * WP Rocket compatibility. The widget is positioned/hidden via inline style attributes + JS (see
 * class-cbxr-widget.php), so it never dumps below the footer even when WP Rocket strips its CSS.
 * These filters additionally keep it fully STYLED and FUNCTIONAL under "Optimize CSS Delivery"
 * (Remove Unused CSS) and "Delay JavaScript":
 *   1. Keep the widget stylesheet out of Remove Unused CSS so it loads normally → panel stays styled.
 *      (RUCSS otherwise deems the hidden slide-panel CSS "unused" and strips it.)
 *   2. Safelist the widget selectors as a fallback for setups that process rather than exclude it.
 *   3. Keep the widget script out of Delay JS so the open/close handlers always attach.
 */
add_filter( 'rocket_rucss_external_exclusions', function ( $exclusions ) {
	$exclusions   = (array) $exclusions;
	$exclusions[] = 'cbx-google-reviews/assets/css/cbxr-widget.css';
	return $exclusions;
} );
add_filter( 'rocket_rucss_safelist', function ( $safelist ) {
	$safelist = (array) $safelist;
	foreach ( array( '#cbxr-widget', '.cbxr-badge', '.cbxr-panel', '.cbxr-overlay', '.cbxr-open' ) as $sel ) {
		if ( ! in_array( $sel, $safelist, true ) ) {
			$safelist[] = $sel;
		}
	}
	return $safelist;
} );
add_filter( 'rocket_delay_js_exclusions', function ( $exclusions ) {
	$exclusions   = (array) $exclusions;
	$exclusions[] = 'cbx-google-reviews/assets/js/cbxr-widget.js';
	return $exclusions;
} );

/**
 * Read an API key, preferring a wp-config.php constant over the stored option.
 *
 * For the most secure setup (keeps the key out of the database entirely), define:
 *   define( 'CBXR_GOOGLE_API_KEY', 'AIza...' );
 *   define( 'CBXR_OUTSCRAPER_KEY', '...' );
 */
function cbxr_get_google_key() {
	if ( defined( 'CBXR_GOOGLE_API_KEY' ) && CBXR_GOOGLE_API_KEY ) {
		return CBXR_GOOGLE_API_KEY;
	}
	return (string) get_option( 'cbxr_api_key', '' );
}
function cbxr_get_outscraper_key() {
	if ( defined( 'CBXR_OUTSCRAPER_KEY' ) && CBXR_OUTSCRAPER_KEY ) {
		return CBXR_OUTSCRAPER_KEY;
	}
	return (string) get_option( 'cbxr_outscraper_key', '' );
}
function cbxr_key_from_constant( $which ) {
	return ( 'outscraper' === $which )
		? ( defined( 'CBXR_OUTSCRAPER_KEY' ) && (bool) CBXR_OUTSCRAPER_KEY )
		: ( defined( 'CBXR_GOOGLE_API_KEY' ) && (bool) CBXR_GOOGLE_API_KEY );
}

/**
 * Sanitize callbacks: a wp-config constant always wins (never stored). Otherwise
 * keep the existing saved key when the field is submitted blank — the settings
 * form never renders the saved key back into the input.
 */
function cbxr_sanitize_google_key( $value ) {
	if ( cbxr_key_from_constant( 'google' ) ) {
		return '';
	}
	$value = sanitize_text_field( $value );
	return ( '' !== $value ) ? $value : (string) get_option( 'cbxr_api_key', '' );
}
function cbxr_sanitize_outscraper_key( $value ) {
	if ( cbxr_key_from_constant( 'outscraper' ) ) {
		return '';
	}
	$value = sanitize_text_field( $value );
	return ( '' !== $value ) ? $value : (string) get_option( 'cbxr_outscraper_key', '' );
}

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
	new CBXR_Updater( __FILE__, 'CHIROBASIX-LLC', 'cbx-plugin-google-reviews' );
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

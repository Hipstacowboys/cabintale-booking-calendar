<?php
/**
 * Plugin Name:       Cabintale Booking Calendar
 * Plugin URI:        https://cabintale.com/
 * Description:       Show live availability and take bookings for your cabin, cottage or rental with a Cabintale widget — as a block or a shortcode.
 * Version:           0.7.0
 * Requires at least: 6.3
 * Requires PHP:      7.4
 * Author:            Cabintale
 * Author URI:        https://cabintale.com/
 * License:           GPLv2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       cabintale-booking-calendar
 * Domain Path:       /languages
 *
 * Cabintale Booking Calendar is free software: you can redistribute it and/or
 * modify it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 2 of the License, or (at your
 * option) any later version.
 *
 * @package Cabintale\BookingCalendar
 */

namespace Cabintale\BookingCalendar;

defined( 'ABSPATH' ) || exit;

const VERSION  = '0.7.0';
const SLUG     = 'cabintale-booking-calendar';
const TEXT_DOM = 'cabintale-booking-calendar';

/** Handle for the embed script served by Cabintale. */
const SCRIPT_HANDLE = 'cabintale-embed';

/** Handle for the block editor script. */
const EDITOR_HANDLE = 'cabintale-bc-editor';

define( __NAMESPACE__ . '\PLUGIN_FILE', __FILE__ );
define( __NAMESPACE__ . '\PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( __NAMESPACE__ . '\PLUGIN_URL', plugin_dir_url( __FILE__ ) );

require_once PLUGIN_DIR . 'includes/class-renderer.php';
require_once PLUGIN_DIR . 'includes/class-block.php';
require_once PLUGIN_DIR . 'includes/class-shortcode.php';
require_once PLUGIN_DIR . 'includes/class-settings.php';
require_once PLUGIN_DIR . 'includes/class-connect.php';

/**
 * Where the Cabintale app lives.
 *
 * The embed script and the widget iframes are both served from here. Override
 * with the CABINTALE_APP_URL constant (wp-config.php) when pointing a site at
 * staging or a local Cabintale install, or with the filter for programmatic
 * cases. Deliberately not a settings field: a low-privilege editor must not be
 * able to repoint the script tag at a domain of their choosing.
 */
function app_url(): string {
	$url = defined( 'CABINTALE_APP_URL' ) ? CABINTALE_APP_URL : 'https://admin.cabintale.com';

	/**
	 * Filters the Cabintale app base URL.
	 *
	 * @param string $url Base URL, no trailing slash.
	 */
	$url = (string) apply_filters( 'cabintale_app_url', $url );

	return untrailingslashit( $url );
}

/**
 * Where the Cabintale documentation lives.
 *
 * A different host from app_url(): the app is admin.cabintale.com, the docs are
 * on the marketing site. Kept in one place so a move needs one edit.
 *
 * @param string $path Path under /docs, with no leading slash.
 */
function docs_url( string $path = '' ): string {
	$base = defined( 'CABINTALE_DOCS_URL' ) ? CABINTALE_DOCS_URL : 'https://www.cabintale.com/docs';
	$base = untrailingslashit( (string) apply_filters( 'cabintale_docs_url', $base ) );

	return '' === $path ? $base : $base . '/' . ltrim( $path, '/' );
}

/**
 * Register the embed script. Nothing is enqueued here — the renderer enqueues
 * it only on requests that actually output a widget, so a page without one
 * ships no extra script tag.
 */
function register_assets(): void {
	wp_register_script(
		SCRIPT_HANDLE,
		app_url() . '/widget-embed.js',
		array(),
		// No version query string: the file is served by Cabintale with its own
		// cache headers, and a plugin version here would bust them on every
		// plugin update for no reason.
		null, // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion -- deliberate, see above.
		array(
			'in_footer' => true,
			'strategy'  => 'defer',
		)
	);
}
add_action( 'init', __NAMESPACE__ . '\register_assets' );

/**
 * Load translations. Kept on init (not plugins_loaded) to match how WordPress
 * loads block and shortcode strings.
 */
function load_textdomain(): void {
	load_plugin_textdomain( TEXT_DOM, false, dirname( plugin_basename( PLUGIN_FILE ) ) . '/languages' );
}
add_action( 'init', __NAMESPACE__ . '\load_textdomain' );

Block::init();
Shortcode::init();
Settings::init();
Connect::init();

/**
 * On activation, remember that setup has not been done yet so the settings
 * screen can be offered once. Uses an option rather than a redirect: redirecting
 * from an activation hook breaks bulk activation and WP-CLI.
 */
function activate(): void {
	if ( false === get_option( Settings::OPTION_TOKEN, false ) ) {
		update_option( Settings::OPTION_NEEDS_SETUP, 1 );
	}
}
register_activation_hook( __FILE__, __NAMESPACE__ . '\activate' );

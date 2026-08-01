<?php
/**
 * Runs when the plugin is deleted (not merely deactivated).
 *
 * Removes everything the plugin stored. Widgets themselves live in Cabintale and
 * are untouched — deleting a WordPress plugin must not delete anyone's booking
 * setup.
 *
 * @package Cabintale\BookingCalendar
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

delete_option( 'cabintale_default_widget_token' );
delete_option( 'cabintale_needs_setup' );

// Multisite: options are per site, so clear each one.
if ( is_multisite() ) {
	$sites = get_sites( array( 'fields' => 'ids' ) );

	foreach ( $sites as $site_id ) {
		switch_to_blog( (int) $site_id );
		delete_option( 'cabintale_default_widget_token' );
		delete_option( 'cabintale_needs_setup' );
		restore_current_blog();
	}
}

<?php
/**
 * Runs when the plugin is deleted (not merely deactivated).
 *
 * Removes everything the plugin stored, including the API token for the
 * connected Cabintale account. Widgets themselves live in Cabintale and are
 * untouched — deleting a WordPress plugin must not delete anyone's booking
 * setup.
 *
 * Note this does not tell Cabintale to revoke the token: WordPress gives an
 * uninstall routine no reliable moment to make a network call, and a token we
 * have deleted is unusable from here anyway. The connection stays listed in
 * Cabintale settings until the owner revokes it there, which is the safer
 * default — a visible connection can be removed, an invisible one cannot.
 *
 * @package Cabintale\BookingCalendar
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

/**
 * Everything the plugin writes, in one list so nothing is missed when a new
 * option is added.
 */
function cabintale_bc_delete_site_data() {
	$options = array(
		'cabintale_default_widget_token',
		'cabintale_default_widget_kind',
		'cabintale_needs_setup',
		'cabintale_api_token',
		'cabintale_account_name',
	);

	foreach ( $options as $option ) {
		delete_option( $option );
	}

	// Per-user PKCE state and the cached widget list are transients; their keys
	// include an id or a hash, so they are cleared by prefix.
	global $wpdb;

	$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- uninstall cleanup, no caching layer applies.
		"DELETE FROM {$wpdb->options}
		 WHERE option_name LIKE '_transient_cabintale_%'
		    OR option_name LIKE '_transient_timeout_cabintale_%'"
	);
}

cabintale_bc_delete_site_data();

// Multisite: options are per site, so clear each one.
if ( is_multisite() ) {
	$sites = get_sites( array( 'fields' => 'ids' ) );

	foreach ( $sites as $site_id ) {
		switch_to_blog( (int) $site_id );
		cabintale_bc_delete_site_data();
		restore_current_blog();
	}
}

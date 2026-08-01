<?php
/**
 * Block registration.
 *
 * @package Cabintale\BookingCalendar
 */

namespace Cabintale\BookingCalendar;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the cabintale/widget block.
 *
 * The block is dynamic (rendered in PHP at request time) for two reasons. It
 * keeps one renderer shared with the shortcode, and it means the markup is never
 * stored in post content — so wp_kses cannot strip <cabintale-root> for authors
 * without unfiltered_html, which is exactly the failure that makes copy-paste
 * embed snippets unreliable on WordPress in the first place.
 */
class Block {

	public static function init(): void {
		add_action( 'init', array( __CLASS__, 'register' ) );
	}

	public static function register(): void {
		// Registered by hand rather than relying on block.json's `file:` syntax:
		// that expects a build step to emit an .asset.php dependency list, and
		// this plugin ships plain readable JavaScript with no build.
		wp_register_script(
			EDITOR_HANDLE,
			PLUGIN_URL . 'assets/js/editor.js',
			array( 'wp-blocks', 'wp-element', 'wp-block-editor', 'wp-components', 'wp-i18n' ),
			VERSION,
			true
		);

		wp_set_script_translations( EDITOR_HANDLE, TEXT_DOM, PLUGIN_DIR . 'languages' );

		register_block_type( PLUGIN_DIR . 'blocks/widget' );
	}
}

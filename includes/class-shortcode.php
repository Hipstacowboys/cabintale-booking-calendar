<?php
/**
 * Shortcode entry point.
 *
 * @package Cabintale\BookingCalendar
 */

namespace Cabintale\BookingCalendar;

defined( 'ABSPATH' ) || exit;

/**
 * [cabintale_widget] — the same widget as the block, for the places blocks do
 * not reach: Elementor, Bricks, classic editor, theme templates calling
 * do_shortcode(), and text widgets.
 */
class Shortcode {

	const TAG = 'cabintale_widget';

	public static function init(): void {
		add_action( 'init', array( __CLASS__, 'register' ) );
	}

	public static function register(): void {
		add_shortcode( self::TAG, array( __CLASS__, 'render' ) );
	}

	/**
	 * @param array<string, mixed>|string $atts Shortcode attributes.
	 * @return string
	 */
	public static function render( $atts ): string {
		$atts = shortcode_atts(
			array(
				'token'             => '',
				// `type` reads more naturally than `kind` in a shortcode, and it
				// matches the wording in the help text and readme.
				'type'              => Renderer::KIND_PLACE,
				'border'            => '1',
				'availability_only' => '0',
			),
			is_array( $atts ) ? $atts : array(),
			self::TAG
		);

		return Renderer::render(
			array(
				'token'             => $atts['token'],
				'kind'              => $atts['type'],
				'border'            => $atts['border'],
				'availability_only' => $atts['availability_only'],
			)
		);
	}
}

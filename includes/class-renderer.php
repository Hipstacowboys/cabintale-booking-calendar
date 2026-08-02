<?php
/**
 * The one place widget markup is produced.
 *
 * @package Cabintale\BookingCalendar
 */

namespace Cabintale\BookingCalendar;

defined( 'ABSPATH' ) || exit;

/**
 * Turns normalized attributes into the <cabintale-root> element that
 * Cabintale's embed script looks for.
 *
 * Both the block's render.php and the shortcode call this — there is exactly one
 * copy of the markup contract in the plugin. If Cabintale ever changes the
 * attribute names (see docs/embed-patterns.md in the admin.cabintale repo), this
 * is the only file that needs to know.
 */
class Renderer {

	const KIND_PLACE    = 'place';
	const KIND_SERVICE  = 'service';
	const KIND_CHECKOUT = 'checkout';

	/**
	 * Widget kinds mapped to the data attribute the embed script switches on.
	 *
	 * @var array<string, string>
	 */
	const KIND_ATTRIBUTES = array(
		self::KIND_PLACE    => 'data-token',
		self::KIND_SERVICE  => 'data-service',
		self::KIND_CHECKOUT => 'data-checkout',
	);

	/**
	 * Coerce raw block attributes or shortcode attributes into a known shape.
	 *
	 * @param array<string, mixed> $raw Untrusted input.
	 * @return array{token: string, kind: string, border: bool, availability_only: bool}
	 */
	public static function normalize( array $raw ): array {
		$token = isset( $raw['token'] ) ? trim( (string) $raw['token'] ) : '';
		$kind  = isset( $raw['kind'] ) ? strtolower( (string) $raw['kind'] ) : self::KIND_PLACE;

		// No widget chosen here, so fall back to the site default — and take its
		// kind with it. A widget and its kind always travel together: pairing the
		// default place widget with someone's leftover "service" would ask
		// Cabintale for a service widget that does not exist, and the visitor
		// gets an empty frame.
		if ( '' === $token ) {
			$token = (string) get_option( Settings::OPTION_TOKEN, '' );
			$kind  = (string) get_option( Settings::OPTION_KIND, self::KIND_PLACE );
		}

		if ( ! isset( self::KIND_ATTRIBUTES[ $kind ] ) ) {
			$kind = self::KIND_PLACE;
		}

		return array(
			'token'             => self::is_token( $token ) ? $token : '',
			'kind'              => $kind,
			'border'            => self::to_bool( $raw['border'] ?? true ),
			'availability_only' => self::to_bool( $raw['availabilityOnly'] ?? $raw['availability_only'] ?? false ),
		);
	}

	/**
	 * Render a widget.
	 *
	 * @param array<string, mixed> $raw               Raw attributes.
	 * @param string               $wrapper_attributes Pre-built wrapper attributes (block context) or ''.
	 * @return string Markup, or '' when there is nothing safe to render.
	 */
	public static function render( array $raw, string $wrapper_attributes = '' ): string {
		$atts = self::normalize( $raw );

		if ( '' === $atts['token'] ) {
			return self::missing_token_notice();
		}

		wp_enqueue_script( SCRIPT_HANDLE );

		$attributes = array(
			self::KIND_ATTRIBUTES[ $atts['kind'] ] => $atts['token'],
		);

		// Only emit the flags when they differ from the widget's own defaults, so
		// the markup stays as close as possible to what Cabintale's own embed
		// snippet produces.
		if ( ! $atts['border'] ) {
			$attributes['data-border'] = 'false';
		}

		if ( $atts['availability_only'] && self::KIND_PLACE === $atts['kind'] ) {
			$attributes['data-availability-only'] = '1';
		}

		$rendered = '';
		foreach ( $attributes as $name => $value ) {
			$rendered .= sprintf( ' %s="%s"', esc_attr( $name ), esc_attr( $value ) );
		}

		$wrapper = '' !== $wrapper_attributes ? ' ' . $wrapper_attributes : ' class="cabintale-widget"';

		return sprintf(
			'<div%1$s><cabintale-root%2$s></cabintale-root></div>',
			$wrapper,
			$rendered
		);
	}

	/**
	 * What to show when no widget has been chosen.
	 *
	 * Visitors get nothing — a half-configured block must not leak plugin
	 * scaffolding onto a live page. Users who could fix it get told.
	 */
	private static function missing_token_notice(): string {
		if ( ! current_user_can( 'edit_posts' ) ) {
			return '';
		}

		return sprintf(
			'<div class="cabintale-widget cabintale-widget--unconfigured notice notice-warning"><p>%s</p></div>',
			esc_html__( 'Cabintale: choose a widget for this block, or set a default widget in Settings → Cabintale.', 'cabintale-booking-calendar' )
		);
	}

	/**
	 * Cabintale widget tokens are UUIDs. Anything else never reaches an
	 * attribute — the token is interpolated into markup, so it gets checked
	 * against a shape rather than merely escaped.
	 *
	 * @param string $value Candidate token.
	 */
	public static function is_token( string $value ): bool {
		return 1 === preg_match( '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $value );
	}

	/**
	 * Shortcodes hand us strings ("0", "false", "no"); the block hands us real
	 * booleans. Accept both.
	 *
	 * @param mixed $value Raw value.
	 */
	private static function to_bool( $value ): bool {
		if ( is_bool( $value ) ) {
			return $value;
		}

		return ! in_array( strtolower( trim( (string) $value ) ), array( '', '0', 'false', 'no', 'off' ), true );
	}
}

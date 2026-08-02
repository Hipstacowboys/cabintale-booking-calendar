<?php
/**
 * Connecting this site to a Cabintale account.
 *
 * @package Cabintale\BookingCalendar
 */

namespace Cabintale\BookingCalendar;

defined( 'ABSPATH' ) || exit;

/**
 * The PKCE handshake, the stored API token, and the widget list it buys.
 *
 * The token is read-only and scoped to listing widgets — it cannot see
 * bookings, guests or payments, and cannot change anything in Cabintale. That
 * matters because it lives in wp_options on a site nobody at Cabintale
 * controls. It is never sent to the browser: the exchange and every subsequent
 * call happen from PHP.
 */
class Connect {

	const OPTION_TOKEN   = 'cabintale_api_token';
	const OPTION_ACCOUNT = 'cabintale_account_name';
	const TRANSIENT_PKCE = 'cabintale_pkce_';
	const TRANSIENT_LIST = 'cabintale_widgets_';

	/** How long a started-but-unfinished connection stays resumable. */
	const PKCE_TTL = HOUR_IN_SECONDS;

	/** How long the widget list is reused before asking Cabintale again. */
	const LIST_TTL = 10 * MINUTE_IN_SECONDS;

	public static function init(): void {
		add_action( 'admin_init', array( __CLASS__, 'handle_actions' ) );
		add_action( 'rest_api_init', array( __CLASS__, 'register_rest' ) );
	}

	public static function is_connected(): bool {
		return '' !== self::token();
	}

	public static function account_name(): string {
		return (string) get_option( self::OPTION_ACCOUNT, '' );
	}

	private static function token(): string {
		return (string) get_option( self::OPTION_TOKEN, '' );
	}

	// ── Starting the handshake ───────────────────────────────────────────────

	/**
	 * Build the URL that sends the site owner to Cabintale to approve, and park
	 * the PKCE verifier where only this WordPress user can pick it up again.
	 */
	public static function start_url(): string {
		$verifier = self::random( 64 );
		$state    = self::random( 32 );

		set_transient(
			self::TRANSIENT_PKCE . get_current_user_id(),
			array(
				'verifier' => $verifier,
				'state'    => $state,
			),
			self::PKCE_TTL
		);

		return add_query_arg(
			array(
				'site_url'              => rawurlencode( home_url() ),
				'site_name'             => rawurlencode( get_bloginfo( 'name' ) ),
				'redirect_uri'          => rawurlencode( self::redirect_uri() ),
				'state'                 => $state,
				'code_challenge'        => self::challenge( $verifier ),
				'code_challenge_method' => 'S256',
			),
			app_url() . '/connect/wordpress'
		);
	}

	/**
	 * Where Cabintale sends the owner back to. Must be on the same host as
	 * home_url() or Cabintale rejects the request before showing the approval
	 * screen — deliberately, so the domain on that screen is the domain that
	 * receives the code.
	 */
	private static function redirect_uri(): string {
		return admin_url( 'options-general.php?page=' . Settings::PAGE_SLUG );
	}

	// ── Admin actions ────────────────────────────────────────────────────────

	public static function handle_actions(): void {
		if ( ! is_admin() || ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// Cabintale redirects back here with ?code=&state=.
		if ( isset( $_GET['code'], $_GET['state'] ) && self::on_settings_page() ) {
			self::complete( sanitize_text_field( wp_unslash( $_GET['code'] ) ), sanitize_text_field( wp_unslash( $_GET['state'] ) ) );
			return;
		}

		// Or with ?error= when the owner pressed Cancel.
		if ( isset( $_GET['error'] ) && self::on_settings_page() ) {
			self::redirect_with( 'cancelled' );
			return;
		}

		if ( isset( $_GET['cabintale_action'] ) ) {
			$action = sanitize_key( wp_unslash( $_GET['cabintale_action'] ) );
			check_admin_referer( 'cabintale_' . $action );

			if ( 'connect' === $action ) {
				// wp_redirect, not wp_safe_redirect: this deliberately leaves the
				// site for the Cabintale domain, which is the whole point.
				wp_redirect( self::start_url() ); // phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect
				exit;
			}

			if ( 'disconnect' === $action ) {
				self::disconnect();
				self::redirect_with( 'disconnected' );
			}

			if ( 'create_page' === $action ) {
				self::create_booking_page();
			}
		}
	}

	private static function on_settings_page(): bool {
		return isset( $_GET['page'] ) && Settings::PAGE_SLUG === sanitize_key( wp_unslash( $_GET['page'] ) );
	}

	/**
	 * Exchange the one-time code for a token — server to server, so the token
	 * never touches the browser.
	 */
	private static function complete( string $code, string $state ): void {
		$pending = get_transient( self::TRANSIENT_PKCE . get_current_user_id() );

		if ( ! is_array( $pending ) ) {
			self::redirect_with( 'expired' );
		}

		if ( ! hash_equals( (string) $pending['state'], $state ) ) {
			self::redirect_with( 'state_mismatch' );
		}

		delete_transient( self::TRANSIENT_PKCE . get_current_user_id() );

		$response = wp_remote_post(
			app_url() . '/api/v1/connect/token',
			array(
				'timeout' => 15,
				'headers' => array( 'Accept' => 'application/json' ),
				'body'    => array(
					'code'          => $code,
					'code_verifier' => $pending['verifier'],
					'redirect_uri'  => self::redirect_uri(),
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			self::redirect_with( 'network' );
		}

		$body = json_decode( (string) wp_remote_retrieve_body( $response ), true );

		if ( 200 !== (int) wp_remote_retrieve_response_code( $response ) || empty( $body['token'] ) ) {
			self::redirect_with( 'rejected' );
		}

		// autoload off: the token is only needed on the settings screen and in
		// REST calls, not on every front-end request.
		update_option( self::OPTION_TOKEN, (string) $body['token'], false );
		update_option( self::OPTION_ACCOUNT, sanitize_text_field( (string) ( $body['account'] ?? '' ) ), false );
		delete_option( Settings::OPTION_NEEDS_SETUP );
		self::forget_widgets();

		self::redirect_with( 'connected' );
	}

	private static function disconnect(): void {
		$token = self::token();

		if ( '' !== $token ) {
			// Best effort — tell Cabintale to drop the token too. If the request
			// fails we still forget it locally; the owner can revoke from
			// Cabintale settings, and a token we no longer hold is unusable here.
			wp_remote_post(
				app_url() . '/api/v1/connect/revoke',
				array(
					'timeout' => 10,
					'headers' => array(
						'Authorization' => 'Bearer ' . $token,
						'Accept'        => 'application/json',
					),
				)
			);
		}

		delete_option( self::OPTION_TOKEN );
		delete_option( self::OPTION_ACCOUNT );
		self::forget_widgets();
	}

	// ── Widget list ──────────────────────────────────────────────────────────

	/**
	 * Widgets on the connected account, newest cache first.
	 *
	 * @return array<int, array{kind: string, token: string, name: string, group: string}>
	 */
	public static function widgets( bool $force = false ): array {
		$key = self::TRANSIENT_LIST . md5( self::token() );

		if ( ! $force ) {
			$cached = get_transient( $key );

			if ( is_array( $cached ) ) {
				return $cached;
			}
		}

		if ( ! self::is_connected() ) {
			return array();
		}

		$response = wp_remote_get(
			app_url() . '/api/v1/widgets',
			array(
				'timeout' => 15,
				'headers' => array(
					'Authorization' => 'Bearer ' . self::token(),
					'Accept'        => 'application/json',
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return array();
		}

		$code = (int) wp_remote_retrieve_response_code( $response );

		// 401 means the owner revoked us in Cabintale. Forget the token rather
		// than retrying forever, so the UI can offer to reconnect.
		if ( 401 === $code ) {
			delete_option( self::OPTION_TOKEN );
			delete_option( self::OPTION_ACCOUNT );

			return array();
		}

		if ( 200 !== $code ) {
			return array();
		}

		$body    = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		$widgets = array();

		foreach ( (array) ( $body['widgets'] ?? array() ) as $widget ) {
			if ( empty( $widget['token'] ) || ! Renderer::is_token( (string) $widget['token'] ) ) {
				continue;
			}

			$widgets[] = array(
				'kind'  => isset( Renderer::KIND_ATTRIBUTES[ $widget['kind'] ?? '' ] ) ? (string) $widget['kind'] : Renderer::KIND_PLACE,
				'token' => (string) $widget['token'],
				'name'  => sanitize_text_field( (string) ( $widget['name'] ?? '' ) ),
				'group' => sanitize_text_field( (string) ( $widget['group'] ?? '' ) ),
			);
		}

		set_transient( $key, $widgets, self::LIST_TTL );

		return $widgets;
	}

	public static function forget_widgets(): void {
		delete_transient( self::TRANSIENT_LIST . md5( self::token() ) );
	}

	// ── REST, for the block editor ───────────────────────────────────────────

	/**
	 * The editor needs the widget list, but must never see the API token — so it
	 * asks WordPress, and WordPress asks Cabintale.
	 */
	public static function register_rest(): void {
		register_rest_route(
			'cabintale/v1',
			'/widgets',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'rest_widgets' ),
				'permission_callback' => function () {
					return current_user_can( 'edit_posts' );
				},
			)
		);
	}

	public static function rest_widgets( \WP_REST_Request $request ) {
		return rest_ensure_response(
			array(
				'connected' => self::is_connected(),
				'account'   => self::account_name(),
				'widgets'   => self::widgets( (bool) $request->get_param( 'refresh' ) ),
			)
		);
	}

	// ── Create a ready-made booking page ─────────────────────────────────────

	/**
	 * The one-click path from "connected" to "there is a booking page on my
	 * site". Non-technical owners should not have to know what a block is to get
	 * their first widget live.
	 */
	private static function create_booking_page(): void {
		$widgets = self::widgets();
		$first   = $widgets[0] ?? null;

		if ( ! $first ) {
			self::redirect_with( 'no_widgets' );
		}

		$page_id = wp_insert_post(
			array(
				'post_type'    => 'page',
				'post_status'  => 'publish',
				'post_title'   => __( 'Book now', 'cabintale-booking-calendar' ),
				'post_content' => sprintf(
					'<!-- wp:cabintale/widget {"token":"%s","kind":"%s"} /-->',
					esc_attr( $first['token'] ),
					esc_attr( $first['kind'] )
				),
			),
			true
		);

		if ( is_wp_error( $page_id ) ) {
			self::redirect_with( 'page_failed' );
		}

		wp_safe_redirect( get_edit_post_link( $page_id, 'raw' ) );
		exit;
	}

	// ── Helpers ──────────────────────────────────────────────────────────────

	/**
	 * Send the owner back to the settings screen with a status to display, and
	 * without the code/state still sitting in their browser history.
	 */
	private static function redirect_with( string $status ): void {
		wp_safe_redirect(
			add_query_arg(
				array(
					'page'              => Settings::PAGE_SLUG,
					'cabintale_status'  => $status,
				),
				admin_url( 'options-general.php' )
			)
		);
		exit;
	}

	private static function random( int $length ): string {
		return substr( str_replace( array( '+', '/', '=' ), '', base64_encode( random_bytes( $length ) ) ), 0, $length );
	}

	/** base64url( sha256( verifier ) ), per PKCE S256. */
	private static function challenge( string $verifier ): string {
		return rtrim( strtr( base64_encode( hash( 'sha256', $verifier, true ) ), '+/', '-_' ), '=' );
	}
}

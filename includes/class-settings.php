<?php
/**
 * Settings screen.
 *
 * @package Cabintale\BookingCalendar
 */

namespace Cabintale\BookingCalendar;

defined( 'ABSPATH' ) || exit;

/**
 * A single setting — the default widget — under Settings → Cabintale.
 *
 * Not a top-level menu: one option does not earn one, and the directory
 * guidelines ask plugins not to crowd the admin.
 */
class Settings {

	const OPTION_TOKEN       = 'cabintale_default_widget_token';
	const OPTION_KIND        = 'cabintale_default_widget_kind';
	const OPTION_NEEDS_SETUP = 'cabintale_needs_setup';
	const PAGE_SLUG          = 'cabintale-booking-calendar';

	public static function init(): void {
		add_action( 'admin_menu', array( __CLASS__, 'add_page' ) );
		add_action( 'admin_init', array( __CLASS__, 'register_setting' ) );
		add_action( 'admin_notices', array( __CLASS__, 'setup_notice' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( PLUGIN_FILE ), array( __CLASS__, 'action_links' ) );
	}

	public static function add_page(): void {
		add_options_page(
			__( 'Cabintale', 'cabintale-booking-calendar' ),
			__( 'Cabintale', 'cabintale-booking-calendar' ),
			'manage_options',
			self::PAGE_SLUG,
			array( __CLASS__, 'render_page' )
		);
	}

	public static function register_setting(): void {
		register_setting(
			self::PAGE_SLUG,
			self::OPTION_TOKEN,
			array(
				'type'              => 'string',
				'default'           => '',
				'sanitize_callback' => array( __CLASS__, 'sanitize_token' ),
				// Never expose the setting through the REST API: it is not secret,
				// but nothing needs it there and an unauthenticated read is one
				// fewer thing to reason about.
				'show_in_rest'      => false,
			)
		);

		register_setting(
			self::PAGE_SLUG,
			self::OPTION_KIND,
			array(
				'type'              => 'string',
				'default'           => Renderer::KIND_PLACE,
				'sanitize_callback' => array( __CLASS__, 'sanitize_kind' ),
				'show_in_rest'      => false,
			)
		);
	}

	/**
	 * @param mixed $value Submitted value.
	 */
	public static function sanitize_kind( $value ): string {
		$value = strtolower( trim( (string) $value ) );

		return isset( Renderer::KIND_ATTRIBUTES[ $value ] ) ? $value : Renderer::KIND_PLACE;
	}

	/**
	 * Store only something shaped like a widget token, and keep the previous
	 * value when the input is malformed rather than silently blanking a working
	 * default.
	 *
	 * @param mixed $value Submitted value.
	 */
	public static function sanitize_token( $value ): string {
		$value = trim( (string) $value );

		if ( '' === $value ) {
			return '';
		}

		if ( ! Renderer::is_token( $value ) ) {
			add_settings_error(
				self::OPTION_TOKEN,
				'cabintale_invalid_token',
				__( 'That does not look like a Cabintale widget ID. Copy it from the embed code in your Cabintale account.', 'cabintale-booking-calendar' ),
				'error'
			);

			return (string) get_option( self::OPTION_TOKEN, '' );
		}

		delete_option( self::OPTION_NEEDS_SETUP );

		return $value;
	}

	public static function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		?>
		<div class="wrap">
			<h1><?php echo esc_html__( 'Cabintale', 'cabintale-booking-calendar' ); ?></h1>

			<?php self::render_status(); ?>

			<div class="card" style="max-width:46em">
				<h2 class="title"><?php echo esc_html__( 'How this works', 'cabintale-booking-calendar' ); ?></h2>
				<p>
					<?php echo esc_html__( 'Your places, prices, availability and calendar sync live in your Cabintale account. This plugin has one job: putting them on your WordPress pages.', 'cabintale-booking-calendar' ); ?>
				</p>
				<p>
					<?php echo esc_html__( 'Block a date or change a price in Cabintale and your pages show it straight away — there is nothing to update or re-publish here.', 'cabintale-booking-calendar' ); ?>
				</p>
			</div>

			<?php self::render_connection(); ?>
			<?php self::render_usage(); ?>
			<?php self::render_where_to_change(); ?>
			<?php self::render_advanced(); ?>
		</div>
		<?php
	}

	/**
	 * Step two: the widget is connected, now get it onto a page.
	 */
	private static function render_usage(): void {
		echo '<h2>' . esc_html__( 'Put a widget on a page', 'cabintale-booking-calendar' ) . '</h2>';

		echo '<p><strong>' . esc_html__( 'Block editor', 'cabintale-booking-calendar' ) . '</strong></p>';

		echo '<ol>';
		printf( '<li>%s</li>', esc_html__( 'Edit the page where you want bookings.', 'cabintale-booking-calendar' ) );
		printf( '<li>%s</li>', esc_html__( 'Add the “Cabintale booking widget” block — type /cabintale to find it quickly.', 'cabintale-booking-calendar' ) );
		printf( '<li>%s</li>', esc_html__( 'Pick your widget from the dropdown in the block settings, then update the page.', 'cabintale-booking-calendar' ) );
		echo '</ol>';

		echo '<p class="description">' . esc_html__( 'While editing you will see a grey card instead of the calendar. That is normal — the real widget appears on the published page.', 'cabintale-booking-calendar' ) . '</p>';

		echo '<p><strong>' . esc_html__( 'Elementor, Bricks, classic editor', 'cabintale-booking-calendar' ) . '</strong></p>';

		echo '<p>' . esc_html__( 'Paste this shortcode into any text field. It shows your default widget:', 'cabintale-booking-calendar' ) . '</p>';
		echo '<p><code>[cabintale_widget]</code></p>';
	}

	/**
	 * The question this screen kept failing to answer: where do I go to change
	 * the thing I want to change?
	 */
	private static function render_where_to_change(): void {
		$rows = array(
			array(
				__( 'Prices, availability, blocked dates, seasons', 'cabintale-booking-calendar' ),
				__( 'Cabintale', 'cabintale-booking-calendar' ),
				app_url() . '/places',
			),
			array(
				__( 'Sync with Airbnb, Booking.com, Vrbo (iCal)', 'cabintale-booking-calendar' ),
				__( 'Cabintale', 'cabintale-booking-calendar' ),
				app_url() . '/places',
			),
			array(
				__( 'Services and time slots', 'cabintale-booking-calendar' ),
				__( 'Cabintale', 'cabintale-booking-calendar' ),
				app_url() . '/services',
			),
			array(
				__( 'Bookings that come in', 'cabintale-booking-calendar' ),
				__( 'Cabintale', 'cabintale-booking-calendar' ),
				app_url() . '/dashboard',
			),
			array(
				__( 'Which widget appears on which page', 'cabintale-booking-calendar' ),
				__( 'Here, in WordPress', 'cabintale-booking-calendar' ),
				'',
			),
			array(
				__( 'Border, and hiding the booking form', 'cabintale-booking-calendar' ),
				__( 'Block settings on the page', 'cabintale-booking-calendar' ),
				'',
			),
		);

		echo '<h2>' . esc_html__( 'Where to change what', 'cabintale-booking-calendar' ) . '</h2>';
		echo '<table class="widefat striped" style="max-width:46em"><tbody>';

		foreach ( $rows as $row ) {
			echo '<tr><td>' . esc_html( $row[0] ) . '</td><td>';

			if ( '' !== $row[2] ) {
				printf(
					'<a href="%s" target="_blank" rel="noopener noreferrer">%s</a>',
					esc_url( $row[2] ),
					esc_html( $row[1] )
				);
			} else {
				echo esc_html( $row[1] );
			}

			echo '</td></tr>';
		}

		echo '</tbody></table>';
	}

	/**
	 * The escape hatch for people who will not connect an account, plus the
	 * shortcode reference. Collapsed, because neither is the normal path.
	 */
	private static function render_advanced(): void {
		?>
		<h2><?php echo esc_html__( 'Advanced', 'cabintale-booking-calendar' ); ?></h2>

		<details<?php echo Connect::is_connected() ? '' : ' open'; ?>>
			<summary><?php echo esc_html__( 'Use a widget ID instead of connecting', 'cabintale-booking-calendar' ); ?></summary>

			<p>
				<?php echo esc_html__( 'If you would rather not connect your account, paste a widget ID here. It becomes the widget used by the shortcode and by blocks where nothing is chosen. In Cabintale, open a widget and copy the ID from its embed code.', 'cabintale-booking-calendar' ); ?>
			</p>

			<form method="post" action="options.php">
				<?php settings_fields( self::PAGE_SLUG ); ?>

				<table class="form-table" role="presentation">
					<tr>
						<th scope="row">
							<label for="cabintale_default_widget_token">
								<?php echo esc_html__( 'Widget ID', 'cabintale-booking-calendar' ); ?>
							</label>
						</th>
						<td>
							<input
								type="text"
								class="regular-text code"
								id="cabintale_default_widget_token"
								name="<?php echo esc_attr( self::OPTION_TOKEN ); ?>"
								value="<?php echo esc_attr( (string) get_option( self::OPTION_TOKEN, '' ) ); ?>"
								placeholder="00000000-0000-0000-0000-000000000000"
							/>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="cabintale_default_widget_kind">
								<?php echo esc_html__( 'What it is', 'cabintale-booking-calendar' ); ?>
							</label>
						</th>
						<td>
							<?php $kind = (string) get_option( self::OPTION_KIND, Renderer::KIND_PLACE ); ?>
							<select id="cabintale_default_widget_kind" name="<?php echo esc_attr( self::OPTION_KIND ); ?>">
								<option value="place" <?php selected( $kind, 'place' ); ?>><?php echo esc_html__( 'Place — availability calendar', 'cabintale-booking-calendar' ); ?></option>
								<option value="service" <?php selected( $kind, 'service' ); ?>><?php echo esc_html__( 'Service — time slots', 'cabintale-booking-calendar' ); ?></option>
								<option value="checkout" <?php selected( $kind, 'checkout' ); ?>><?php echo esc_html__( 'Product — checkout button', 'cabintale-booking-calendar' ); ?></option>
							</select>
							<p class="description">
								<?php echo esc_html__( 'Must match the widget the ID belongs to, otherwise the widget will not load. Connecting your account removes this guesswork.', 'cabintale-booking-calendar' ); ?>
							</p>
						</td>
					</tr>
				</table>

				<?php submit_button(); ?>
			</form>
		</details>

		<details>
			<summary><?php echo esc_html__( 'Shortcode options', 'cabintale-booking-calendar' ); ?></summary>

			<table class="widefat striped" style="max-width:46em">
				<tbody>
					<tr><td><code>token</code></td><td><?php echo esc_html__( 'A specific widget ID. Defaults to the one above.', 'cabintale-booking-calendar' ); ?></td></tr>
					<tr><td><code>type</code></td><td><?php echo esc_html__( 'place, service or checkout. Must match the widget.', 'cabintale-booking-calendar' ); ?></td></tr>
					<tr><td><code>border</code></td><td><?php echo esc_html__( '1 or 0.', 'cabintale-booking-calendar' ); ?></td></tr>
					<tr><td><code>availability_only</code></td><td><?php echo esc_html__( '1 or 0. Places only — shows the calendar without the booking form.', 'cabintale-booking-calendar' ); ?></td></tr>
				</tbody>
			</table>

			<p><code>[cabintale_widget type="place" border="0" availability_only="1"]</code></p>
		</details>
		<?php
	}

	/**
	 * The connect / connected panel — the primary path on this screen.
	 */
	private static function render_connection(): void {
		if ( Connect::is_connected() ) {
			$widgets = Connect::widgets();
			$account = Connect::account_name();

			echo '<h2>' . esc_html__( 'Your Cabintale account', 'cabintale-booking-calendar' ) . '</h2>';

			printf(
				'<p><strong>%s</strong>%s</p>',
				esc_html( '' !== $account ? $account : __( 'Connected', 'cabintale-booking-calendar' ) ),
				esc_html(
					sprintf(
						/* translators: %d: number of widgets available on the connected account. */
						_n( ' — %d widget available', ' — %d widgets available', count( $widgets ), 'cabintale-booking-calendar' ),
						count( $widgets )
					)
				)
			);

			echo '<p>';

			if ( $widgets ) {
				printf(
					'<a href="%s" class="button button-primary">%s</a> ',
					esc_url( self::action_url( 'create_page' ) ),
					esc_html__( 'Create my booking page', 'cabintale-booking-calendar' )
				);
			}

			printf(
				'<a href="%s" class="button">%s</a>',
				esc_url( self::action_url( 'disconnect' ) ),
				esc_html__( 'Disconnect', 'cabintale-booking-calendar' )
			);

			echo '</p>';

			if ( $widgets ) {
				echo '<table class="widefat striped" style="max-width:46em"><thead><tr>';
				echo '<th>' . esc_html__( 'Widget', 'cabintale-booking-calendar' ) . '</th>';
				echo '<th>' . esc_html__( 'Shows', 'cabintale-booking-calendar' ) . '</th>';
				echo '</tr></thead><tbody>';

				foreach ( $widgets as $widget ) {
					$label = trim( ( $widget['group'] ? $widget['group'] . ' — ' : '' ) . $widget['name'] );

					printf(
						'<tr><td>%s</td><td>%s</td></tr>',
						esc_html( '' !== $label ? $label : __( 'Untitled widget', 'cabintale-booking-calendar' ) ),
						esc_html( self::kind_label( $widget['kind'] ) )
					);
				}

				echo '</tbody></table>';
			}

			return;
		}

		echo '<h2>' . esc_html__( 'Connect your Cabintale account', 'cabintale-booking-calendar' ) . '</h2>';

		echo '<p>' . esc_html__( 'Connecting lets you pick your widgets by name when adding them to a page. If you do not have an account yet, you can create one for free during the next step — it takes a couple of minutes and sets up your first availability calendar.', 'cabintale-booking-calendar' ) . '</p>';

		printf(
			'<p><a href="%s" class="button button-primary">%s</a></p>',
			esc_url( self::action_url( 'connect' ) ),
			esc_html__( 'Connect to Cabintale', 'cabintale-booking-calendar' )
		);

		echo '<p class="description">' . esc_html__( 'You will be asked to approve the connection in Cabintale. It can only read the names of your places and widgets — never your bookings, guests or payments.', 'cabintale-booking-calendar' ) . '</p>';
	}

	/**
	 * Outcome of whatever the owner just did. Kept out of admin_notices so it
	 * appears in the flow of this screen rather than above the page title.
	 */
	private static function render_status(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display-only, no state change.
		$status = isset( $_GET['cabintale_status'] ) ? sanitize_key( wp_unslash( $_GET['cabintale_status'] ) ) : '';

		if ( '' === $status ) {
			return;
		}

		$messages = array(
			'connected'      => array( 'success', __( 'Connected. Your widgets are now available in the Cabintale block.', 'cabintale-booking-calendar' ) ),
			'disconnected'   => array( 'success', __( 'Disconnected. Widgets already on your pages keep working.', 'cabintale-booking-calendar' ) ),
			'cancelled'      => array( 'info', __( 'Connection cancelled. Nothing was changed.', 'cabintale-booking-calendar' ) ),
			'expired'        => array( 'warning', __( 'That connection attempt timed out. Please start again.', 'cabintale-booking-calendar' ) ),
			'state_mismatch' => array( 'error', __( 'The response did not match the request that started it, so it was ignored. Please start again.', 'cabintale-booking-calendar' ) ),
			'network'        => array( 'error', __( 'Could not reach Cabintale. Check your connection and try again.', 'cabintale-booking-calendar' ) ),
			'rejected'       => array( 'error', __( 'Cabintale did not accept the connection. Please start again.', 'cabintale-booking-calendar' ) ),
			'no_widgets'     => array( 'warning', __( 'There are no widgets on your Cabintale account yet. Create one in Cabintale, then try again.', 'cabintale-booking-calendar' ) ),
			'page_failed'    => array( 'error', __( 'The page could not be created. You can add the Cabintale block to a page yourself instead.', 'cabintale-booking-calendar' ) ),
		);

		if ( ! isset( $messages[ $status ] ) ) {
			return;
		}

		printf(
			'<div class="notice notice-%1$s"><p>%2$s</p></div>',
			esc_attr( $messages[ $status ][0] ),
			esc_html( $messages[ $status ][1] )
		);
	}

	/**
	 * What a widget shows, in the owner's words rather than ours.
	 */
	private static function kind_label( string $kind ): string {
		if ( Renderer::KIND_SERVICE === $kind ) {
			return __( 'A service — time slots', 'cabintale-booking-calendar' );
		}

		if ( Renderer::KIND_CHECKOUT === $kind ) {
			return __( 'A product — checkout button', 'cabintale-booking-calendar' );
		}

		return __( 'A place — availability calendar', 'cabintale-booking-calendar' );
	}

	private static function action_url( string $action ): string {
		return wp_nonce_url(
			add_query_arg(
				array(
					'page'             => self::PAGE_SLUG,
					'cabintale_action' => $action,
				),
				admin_url( 'options-general.php' )
			),
			'cabintale_' . $action
		);
	}

	/**
	 * One dismissible pointer to the settings screen, shown until a default
	 * widget exists. Self-dismisses on save, so it cannot become a permanent ad.
	 */
	public static function setup_notice(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		if ( ! get_option( self::OPTION_NEEDS_SETUP ) ) {
			return;
		}

		// Either route out of setup counts: a connected account or a pasted ID.
		if ( get_option( self::OPTION_TOKEN ) || Connect::is_connected() ) {
			delete_option( self::OPTION_NEEDS_SETUP );

			return;
		}

		printf(
			'<div class="notice notice-info is-dismissible"><p>%1$s <a href="%2$s">%3$s</a></p></div>',
			esc_html__( 'Cabintale is active. Add your widget to finish setting it up.', 'cabintale-booking-calendar' ),
			esc_url( admin_url( 'options-general.php?page=' . self::PAGE_SLUG ) ),
			esc_html__( 'Open Cabintale settings', 'cabintale-booking-calendar' )
		);
	}

	/**
	 * @param array<int, string> $links Existing action links.
	 * @return array<int, string>
	 */
	public static function action_links( array $links ): array {
		$settings = sprintf(
			'<a href="%s">%s</a>',
			esc_url( admin_url( 'options-general.php?page=' . self::PAGE_SLUG ) ),
			esc_html__( 'Settings', 'cabintale-booking-calendar' )
		);

		array_unshift( $links, $settings );

		return $links;
	}
}

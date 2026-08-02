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
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
		add_action( 'admin_init', array( __CLASS__, 'register_setting' ) );
		add_action( 'admin_notices', array( __CLASS__, 'setup_notice' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( PLUGIN_FILE ), array( __CLASS__, 'action_links' ) );
	}

	/**
	 * Only on our own screen — a plugin has no business loading assets across
	 * the whole admin.
	 *
	 * @param string $hook Current admin page hook.
	 */
	public static function enqueue_assets( string $hook ): void {
		if ( 'settings_page_' . self::PAGE_SLUG !== $hook ) {
			return;
		}

		wp_enqueue_style(
			'cabintale-bc-settings',
			PLUGIN_URL . 'assets/css/settings.css',
			array(),
			VERSION
		);

		wp_enqueue_script(
			'cabintale-bc-settings',
			PLUGIN_URL . 'assets/js/settings.js',
			array( 'wp-a11y' ),
			VERSION,
			true
		);
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
		<div class="wrap cbt-settings">
			<div class="cbt-header">
				<span class="cbt-header__mark">
					<img src="<?php echo esc_url( PLUGIN_URL . 'assets/img/cabintale-mark.svg' ); ?>" alt="" aria-hidden="true" width="26" height="26" />
				</span>
				<span class="cbt-header__text">
					<h1 class="cbt-header__title"><?php echo esc_html__( 'Cabintale', 'cabintale-booking-calendar' ); ?></h1>
					<p class="cbt-header__sub"><?php echo esc_html__( 'Booking calendar and availability for your pages', 'cabintale-booking-calendar' ); ?></p>
				</span>
			</div>

			<?php
			self::render_status();
			self::render_connection();

			// Placing a widget and knowing where to change things are only
			// actionable once there is an account behind them. Showing the
			// instructions first is what made this screen read as a wall of text:
			// correct information, offered at a moment it cannot be used.
			if ( Connect::is_connected() ) {
				self::render_widgets();
				self::render_usage();
				self::render_where_to_change();
			}

			self::render_advanced();
			?>
		</div>
		<?php
	}

	/**
	 * Getting a widget onto a page. Two disclosures rather than two stacked
	 * walls: most people need one of these, never both.
	 */
	private static function render_usage(): void {
		?>
		<h2><?php echo esc_html__( 'Put a widget on a page', 'cabintale-booking-calendar' ); ?></h2>

		<details open>
			<summary><?php echo esc_html__( 'Any editor — Elementor, Bricks, Divi, classic, templates', 'cabintale-booking-calendar' ); ?></summary>
			<ol>
				<li><?php echo esc_html__( 'Copy the shortcode next to your widget above.', 'cabintale-booking-calendar' ); ?></li>
				<li><?php echo esc_html__( 'Edit the page you already have, where you want the calendar to appear.', 'cabintale-booking-calendar' ); ?></li>
				<li><?php echo esc_html__( 'Add a shortcode or text element, paste it in, and save.', 'cabintale-booking-calendar' ); ?></li>
			</ol>
			<p class="description">
				<?php echo esc_html__( 'This keeps your own page design. The widget sits inside it like any other element.', 'cabintale-booking-calendar' ); ?>
			</p>
		</details>

		<details>
			<summary><?php echo esc_html__( 'Block editor', 'cabintale-booking-calendar' ); ?></summary>
			<ol>
				<li><?php echo esc_html__( 'Edit the page where you want bookings.', 'cabintale-booking-calendar' ); ?></li>
				<li><?php echo esc_html__( 'Add the “Cabintale booking widget” block — type /cabintale to find it quickly.', 'cabintale-booking-calendar' ); ?></li>
				<li><?php echo esc_html__( 'Pick your widget from the dropdown in the block settings, then update the page.', 'cabintale-booking-calendar' ); ?></li>
			</ol>
			<p class="description">
				<?php echo esc_html__( 'While editing you will see a grey card instead of the calendar. That is normal — the widget loads on the published page.', 'cabintale-booking-calendar' ); ?>
			</p>
		</details>

		<?php
	}

	/**
	 * The question this screen kept failing to answer: where do I go to change
	 * the thing I want to change?
	 *
	 * Every plausible hunt target gets a row, because the failure this prevents
	 * is someone searching WordPress for an availability setting that only
	 * exists in Cabintale. Destinations are named the way Cabintale's own
	 * navigation names them, so the click lands on a familiar word.
	 */
	private static function render_where_to_change(): void {
		// Only guides that were verified to exist are linked — a help link that
		// 404s is worse than no help link.
		$rows = array(
			array( __( 'Prices, availability, blocked dates, seasonal pricing', 'cabintale-booking-calendar' ), __( 'Cabintale → Places', 'cabintale-booking-calendar' ), app_url() . '/places', docs_url( 'get-set-up/seasonal-pricing' ) ),
			array( __( 'Calendar sync with Airbnb, Booking.com and other platforms (iCal)', 'cabintale-booking-calendar' ), __( 'Cabintale → Places', 'cabintale-booking-calendar' ), app_url() . '/places', docs_url( 'connect-channels/ical-export' ) ),
			array( __( 'Services and time slots', 'cabintale-booking-calendar' ), __( 'Cabintale → Services', 'cabintale-booking-calendar' ), app_url() . '/services', '' ),
			array( __( 'Widget language, look and booking form', 'cabintale-booking-calendar' ), __( 'Cabintale → the widget', 'cabintale-booking-calendar' ), app_url() . '/places', docs_url( 'customize-site/embeddable-widget' ) ),
			array( __( 'Property name, timezone and currency', 'cabintale-booking-calendar' ), __( 'Cabintale → Places', 'cabintale-booking-calendar' ), app_url() . '/places', docs_url( 'get-set-up/property-basics' ) ),
			array( __( 'Bookings as they come in', 'cabintale-booking-calendar' ), __( 'Cabintale → Dashboard', 'cabintale-booking-calendar' ), app_url() . '/dashboard', '' ),
			array( __( 'Which widget appears on which page', 'cabintale-booking-calendar' ), __( 'Here, in WordPress', 'cabintale-booking-calendar' ), '', '' ),
			array( __( 'Border, and hiding the booking form', 'cabintale-booking-calendar' ), __( 'Block settings on the page', 'cabintale-booking-calendar' ), '', '' ),
		);

		echo '<h2>' . esc_html__( 'Where to change what', 'cabintale-booking-calendar' ) . '</h2>';
		echo '<div style="overflow-x:auto"><table class="widefat striped"><thead><tr>';
		echo '<th>' . esc_html__( 'What', 'cabintale-booking-calendar' ) . '</th>';
		echo '<th>' . esc_html__( 'Where', 'cabintale-booking-calendar' ) . '</th>';
		echo '<th>' . esc_html__( 'Guide', 'cabintale-booking-calendar' ) . '</th>';
		echo '</tr></thead><tbody>';

		foreach ( $rows as $row ) {
			echo '<tr><td>' . esc_html( $row[0] ) . '</td><td>';

			if ( '' !== $row[2] ) {
				printf(
					'<span class="dashicons dashicons-external" aria-hidden="true"></span><a href="%s" target="_blank" rel="noopener noreferrer">%s</a>',
					esc_url( $row[2] ),
					esc_html( $row[1] )
				);
			} else {
				echo esc_html( $row[1] );
			}

			echo '</td><td>';

			if ( '' !== $row[3] ) {
				printf(
					'<a href="%s" target="_blank" rel="noopener noreferrer">%s</a>',
					esc_url( $row[3] ),
					esc_html__( 'Read', 'cabintale-booking-calendar' )
				);
			} else {
				echo '&mdash;';
			}

			echo '</td></tr>';
		}

		echo '</tbody></table></div>';

		self::render_help();
	}

	/**
	 * A short way out to the documentation, for the questions this screen does
	 * not answer.
	 */
	private static function render_help(): void {
		$links = array(
			array( __( 'Getting started in 30 minutes', 'cabintale-booking-calendar' ), docs_url( 'getting-started/welcome-and-first-steps' ) ),
			array( __( 'The booking widget explained', 'cabintale-booking-calendar' ), docs_url( 'customize-site/embeddable-widget' ) ),
			array( __( 'All Cabintale documentation', 'cabintale-booking-calendar' ), docs_url() ),
		);

		echo '<h2>' . esc_html__( 'Help', 'cabintale-booking-calendar' ) . '</h2>';
		echo '<ul class="cbt-help">';

		foreach ( $links as $link ) {
			printf(
				'<li><a href="%s" target="_blank" rel="noopener noreferrer">%s</a></li>',
				esc_url( $link[1] ),
				esc_html( $link[0] )
			);
		}

		echo '</ul>';
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
								<option value="place" <?php selected( $kind, 'place' ); ?>><?php echo esc_html__( 'Property — availability calendar', 'cabintale-booking-calendar' ); ?></option>
								<option value="service" <?php selected( $kind, 'service' ); ?>><?php echo esc_html__( 'Service — time slots', 'cabintale-booking-calendar' ); ?></option>
								<option value="checkout" <?php selected( $kind, 'checkout' ); ?>><?php echo esc_html__( 'Product — checkout button', 'cabintale-booking-calendar' ); ?></option>
							</select>
							<p class="description">
								<?php echo esc_html__( 'Must match the widget the ID belongs to, or the widget will not load. Connecting your account removes this guesswork.', 'cabintale-booking-calendar' ); ?>
							</p>
						</td>
					</tr>
				</table>

				<?php submit_button(); ?>
			</form>
		</details>

		<details>
			<summary><?php echo esc_html__( 'Shortcode options', 'cabintale-booking-calendar' ); ?></summary>

			<table class="widefat striped">
				<tbody>
					<tr><td><code>token</code></td><td><?php echo esc_html__( 'A specific widget ID. Defaults to the one above.', 'cabintale-booking-calendar' ); ?></td></tr>
					<tr><td><code>type</code></td><td><?php echo esc_html__( 'property, service or checkout. Must match the widget.', 'cabintale-booking-calendar' ); ?></td></tr>
					<tr><td><code>border</code></td><td><?php echo esc_html__( '1 or 0.', 'cabintale-booking-calendar' ); ?></td></tr>
					<tr><td><code>availability_only</code></td><td><?php echo esc_html__( '1 or 0. Properties only — shows the calendar without the booking form.', 'cabintale-booking-calendar' ); ?></td></tr>
				</tbody>
			</table>

			<p><code>[cabintale_widget type="property" border="0" availability_only="1"]</code></p>
		</details>
		<?php
	}

	/**
	 * The connect / connected panel — the primary path on this screen.
	 *
	 * Disconnected, this is the only thing that matters, so it carries the
	 * explanation too rather than leaving a separate "how this works" block to
	 * compete with it. Connected, it becomes a settled one-liner: the account is
	 * sorted, the widgets below are the interesting part.
	 */
	private static function render_connection(): void {
		if ( Connect::is_connected() ) {
			$account = Connect::account_name();
			$widgets = Connect::widgets();

			echo '<div class="cbt-connected">';

			printf(
				'<span class="dashicons dashicons-yes-alt" aria-hidden="true"></span><strong>%s</strong><span class="cbt-connected__meta">%s</span>',
				esc_html( '' !== $account ? $account : __( 'Connected', 'cabintale-booking-calendar' ) ),
				esc_html(
					sprintf(
						/* translators: %d: number of widgets available on the connected account. */
						_n( ' — %d widget', ' — %d widgets', count( $widgets ), 'cabintale-booking-calendar' ),
						count( $widgets )
					)
				)
			);

			echo '<span class="cbt-connected__actions">';

			printf(
				'<a href="%1$s" class="button button-primary cbt-button-brand" target="_blank" rel="noopener noreferrer">%2$s<span class="dashicons dashicons-external" aria-hidden="true"></span></a>',
				esc_url( app_url() . '/dashboard' ),
				esc_html__( 'Go to my Cabintale account', 'cabintale-booking-calendar' )
			);

			printf(
				'<a href="%1$s" class="button" data-cabintale-busy="%2$s">%3$s</a>',
				esc_url( self::action_url( 'disconnect' ) ),
				esc_attr__( 'Disconnecting…', 'cabintale-booking-calendar' ),
				esc_html__( 'Disconnect', 'cabintale-booking-calendar' )
			);

			echo '</span></div>';

			return;
		}

		?>
		<div class="card">
			<h2 class="title" style="display:flex;align-items:center;gap:8px;flex-wrap:wrap">
				<span class="dashicons dashicons-calendar-alt" aria-hidden="true"></span>
				<?php echo esc_html__( 'Connect your Cabintale account', 'cabintale-booking-calendar' ); ?>
			</h2>

			<p>
				<?php echo esc_html__( 'Your properties, prices, availability and calendar sync live in Cabintale. This plugin puts them on your WordPress pages — change a price there and your pages show it straight away, with nothing to re-publish here.', 'cabintale-booking-calendar' ); ?>
			</p>

			<p>
				<?php
				printf(
					'<a href="%1$s" class="button button-primary" data-cabintale-busy="%2$s">%3$s</a>',
					esc_url( self::action_url( 'connect' ) ),
					esc_attr__( 'Opening Cabintale…', 'cabintale-booking-calendar' ),
					esc_html__( 'Connect to Cabintale', 'cabintale-booking-calendar' )
				);
				?>
				<span class="spinner" style="float:none;margin:0 0 0 6px;vertical-align:middle"></span>
			</p>

			<p class="description">
				<?php echo esc_html__( 'No account yet? You can create one for free in the next step.', 'cabintale-booking-calendar' ); ?>
			</p>

			<p class="description">
				<?php echo esc_html__( 'Cabintale will ask you to approve the connection. It can only read the names of your properties and widgets — never your bookings, guests or payments.', 'cabintale-booking-calendar' ); ?>
			</p>
		</div>
		<?php
	}

	/**
	 * The widgets on the connected account, each previewable on demand.
	 */
	private static function render_widgets(): void {
		$widgets = Connect::widgets();

		echo '<div class="card">';
		echo '<h2 class="title">' . esc_html__( 'Your widgets', 'cabintale-booking-calendar' ) . '</h2>';

		if ( ! $widgets ) {
			echo '<p>' . esc_html__( 'No widgets yet. Create one in Cabintale, then come back here to add it to a page.', 'cabintale-booking-calendar' ) . '</p>';
			printf(
				'<p><a href="%s" target="_blank" rel="noopener noreferrer">%s</a></p>',
				esc_url( app_url() . '/places' ),
				esc_html__( 'Open Cabintale', 'cabintale-booking-calendar' )
			);
			echo '</div>';

			return;
		}

		echo '<p class="description">' . esc_html__( 'How a widget looks, what language it speaks and which fields it asks for are set in Cabintale, per widget.', 'cabintale-booking-calendar' ) . '</p>';

		echo '<div class="cbt-widgets-table" style="overflow-x:auto">';
		echo '<table class="widefat striped"><thead><tr>';
		echo '<th class="cbt-col-name">' . esc_html__( 'Widget', 'cabintale-booking-calendar' ) . '</th>';
		echo '<th>' . esc_html__( 'Shortcode', 'cabintale-booking-calendar' ) . '</th>';
		echo '<th class="cbt-actions">' . esc_html__( 'Actions', 'cabintale-booking-calendar' ) . '</th>';
		echo '</tr></thead><tbody>';

		$index = 0;

		foreach ( $widgets as $widget ) {
			$index++;
			$panel_id = 'cabintale-preview-' . $index;
			$name     = trim( (string) $widget['name'] );
			$parent   = trim( (string) $widget['group'] );

			if ( '' === $name ) {
				$name = '' !== $parent ? $parent : __( 'Untitled widget', 'cabintale-booking-calendar' );
			}

			// Widgets are often named after the property they belong to
			// ("Mountain Cabin – Widget"), so printing both produced
			// "Mountain Cabin — Mountain Cabin – Widget". Only show the parent
			// when it adds something the name does not already say.
			$show_parent = '' !== $parent && false === stripos( $name, $parent );
			$label       = $show_parent ? $parent . ' — ' . $name : $name;

			echo '<tr>';

			printf(
				'<td><span class="cbt-widget-name">%s</span><span class="cbt-widget-parent">%s</span></td>',
				esc_html( $name ),
				esc_html( $show_parent ? $parent . ' · ' . self::kind_label( $widget['kind'] ) : self::kind_label( $widget['kind'] ) )
			);

			// The shortcode is the one instruction that works everywhere —
			// Elementor, Bricks, Divi, the classic editor, a theme template —
			// so it is offered per widget rather than buried in Advanced.
			$shortcode = self::shortcode_for( $widget );

			printf(
				'<td><span class="cbt-shortcode-cell"><code class="cbt-shortcode">%1$s</code><button type="button" class="button" data-cabintale-copy="%2$s" data-cabintale-copied="%3$s">%4$s</button></span></td>',
				esc_html( $shortcode ),
				esc_attr( $shortcode ),
				esc_attr__( 'Copied', 'cabintale-booking-calendar' ),
				esc_html__( 'Copy', 'cabintale-booking-calendar' )
			);

			// Styling, language and behaviour are widget settings, so they are
			// changed in Cabintale. The link carries only the widget token; the
			// editor URL is resolved there from the owner's session (see
			// WidgetEditRedirectController for why the place token stays away
			// from WordPress).
			printf(
				'<td class="cbt-actions">'
					. '<button type="button" class="button" aria-expanded="false" aria-controls="%1$s" data-cabintale-preview="%2$s"><span class="dashicons dashicons-visibility" aria-hidden="true"></span>%3$s</button>'
					. '<a href="%4$s" class="button button-primary" target="_blank" rel="noopener noreferrer">%5$s<span class="dashicons dashicons-external" aria-hidden="true"></span></a>'
				. '</td>',
				esc_attr( $panel_id ),
				/* translators: %s: widget name. */
				esc_attr( sprintf( __( 'Loading preview of %s', 'cabintale-booking-calendar' ), $label ) ),
				esc_html__( 'Preview', 'cabintale-booking-calendar' ),
				esc_url( app_url() . '/connect/widget/' . rawurlencode( $widget['token'] ) . '/edit' ),
				esc_html__( 'Style &amp; language', 'cabintale-booking-calendar' )
			);


			echo '</tr>';

			// The preview panel is a sibling row so it can span the table, and it
			// carries no src until opened — see assets/js/settings.js for why the
			// first request is deliberately user-initiated.
			printf(
				'<tr id="%1$s" hidden class="cbt-preview"><td colspan="3">%2$s<iframe data-src="%3$s" title="%4$s" style="height:%5$dpx" scrolling="no"></iframe><p class="description">%6$s <a href="%3$s" target="_blank" rel="noopener noreferrer">%7$s</a></p></td></tr>',
				esc_attr( $panel_id ),
				'<p data-cabintale-loading class="description">' . esc_html__( 'Loading preview…', 'cabintale-booking-calendar' ) . '</p>',
				esc_url( self::preview_url( $widget ) ),
				/* translators: %s: widget name. */
				esc_attr( sprintf( __( 'Preview of %s', 'cabintale-booking-calendar' ), $label ) ),
				(int) self::preview_height( $widget['kind'] ),
				esc_html__( 'This is live — visitors see the same widget on your page.', 'cabintale-booking-calendar' ),
				esc_html__( 'Open full size', 'cabintale-booking-calendar' )
			);
		}

		echo '</tbody></table></div>';

		// Demoted from the primary action it used to be. It creates a plain page
		// on the theme's default template, which is the wrong starting point for
		// anyone using Elementor, Bricks, Divi or a designed template — and that
		// is most of this audience. Useful as a shortcut, not as the headline.
		printf(
			'<p><a href="%1$s" class="button" data-cabintale-busy="%2$s">%3$s</a></p>',
			esc_url( self::action_url( 'create_page' ) ),
			esc_attr__( 'Creating the page…', 'cabintale-booking-calendar' ),
			esc_html__( 'Create a starter page', 'cabintale-booking-calendar' )
		);

		echo '<p class="description">' . esc_html__( 'Makes a new page called “Book now” with your first widget on it, using your theme’s default layout. If you already have a page designed, paste the shortcode into it instead.', 'cabintale-booking-calendar' ) . '</p>';

		echo '</div>';
	}

	/**
	 * The shortcode for a specific widget. Type is only included when it is not
	 * the default, so the common case stays short enough to read at a glance.
	 *
	 * @param array{kind: string, token: string} $widget Widget row.
	 */
	private static function shortcode_for( array $widget ): string {
		$attributes = 'token="' . $widget['token'] . '"';

		if ( Renderer::KIND_PLACE !== $widget['kind'] ) {
			$attributes .= ' type="' . $widget['kind'] . '"';
		}

		return '[cabintale_widget ' . $attributes . ']';
	}

	/**
	 * @param array{kind: string, token: string} $widget Widget row.
	 */
	private static function preview_url( array $widget ): string {
		$path = Renderer::KIND_SERVICE === $widget['kind'] ? '/service-widget/' : ( Renderer::KIND_CHECKOUT === $widget['kind'] ? '/checkout/' : '/widget/' );

		return app_url() . $path . rawurlencode( $widget['token'] );
	}

	/** Matches the heights Cabintale's own embed code uses for each kind. */
	private static function preview_height( string $kind ): int {
		return Renderer::KIND_PLACE === $kind ? 450 : 550;
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
			'cancelled'      => array( 'info', __( 'Connection cancelled. Nothing changed.', 'cabintale-booking-calendar' ) ),
			'expired'        => array( 'warning', __( 'That connection attempt timed out. Try connecting again.', 'cabintale-booking-calendar' ) ),
			'state_mismatch' => array( 'error', __( 'That connection could not be verified. Try connecting again.', 'cabintale-booking-calendar' ) ),
			'network'        => array( 'error', __( 'Could not reach Cabintale. Check your internet connection and try again.', 'cabintale-booking-calendar' ) ),
			'rejected'       => array( 'error', __( 'Cabintale did not accept the connection. Try connecting again.', 'cabintale-booking-calendar' ) ),
			'no_widgets'     => array( 'warning', __( 'Connected, but there are no widgets on your Cabintale account yet. Create one in Cabintale, then refresh this page.', 'cabintale-booking-calendar' ) ),
			'page_failed'    => array( 'error', __( 'The booking page could not be created. Add the Cabintale block to a page yourself instead.', 'cabintale-booking-calendar' ) ),
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

		return __( 'A property — availability calendar', 'cabintale-booking-calendar' );
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

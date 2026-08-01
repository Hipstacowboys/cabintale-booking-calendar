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

			<p>
				<?php echo esc_html__( 'Set a default widget so the Cabintale block is ready to use without pasting an ID every time. You can still override it on any individual block.', 'cabintale-booking-calendar' ); ?>
			</p>

			<form method="post" action="options.php">
				<?php settings_fields( self::PAGE_SLUG ); ?>

				<table class="form-table" role="presentation">
					<tr>
						<th scope="row">
							<label for="cabintale_default_widget_token">
								<?php echo esc_html__( 'Default widget ID', 'cabintale-booking-calendar' ); ?>
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
							<p class="description">
								<?php echo esc_html__( 'In Cabintale, open your widget and copy the ID from its embed code.', 'cabintale-booking-calendar' ); ?>
								<a href="https://cabintale.com/" target="_blank" rel="noopener noreferrer">
									<?php echo esc_html__( 'Create a free Cabintale account', 'cabintale-booking-calendar' ); ?>
								</a>
							</p>
						</td>
					</tr>
				</table>

				<?php submit_button(); ?>
			</form>

			<h2><?php echo esc_html__( 'Adding a widget to a page', 'cabintale-booking-calendar' ); ?></h2>

			<p>
				<?php echo esc_html__( 'In the block editor, add the “Cabintale booking widget” block. In Elementor, Bricks, the classic editor or a theme template, use the shortcode:', 'cabintale-booking-calendar' ); ?>
			</p>

			<p><code>[cabintale_widget]</code></p>

			<p>
				<?php echo esc_html__( 'Optional attributes: token (a specific widget), type (place, service or checkout), border (1 or 0), availability_only (1 or 0).', 'cabintale-booking-calendar' ); ?>
			</p>

			<p><code>[cabintale_widget type="place" border="0" availability_only="1"]</code></p>
		</div>
		<?php
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

		if ( get_option( self::OPTION_TOKEN ) ) {
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

<?php
/**
 * Handle troubleshooting options.
 *
 * @package Health Check
 */

namespace SiteHealth\Troubleshooting;

// Make sure the file is not directly accessible.
if ( ! defined( 'ABSPATH' ) ) {
	die( 'We\'re sorry, but you can not directly access this file.' );
}

/**
 * Class Troubleshoot
 */
class Troubleshoot {

	/**
	 * Notices to show at the head of the admin screen.
	 *
	 * @access public
	 *
	 * @var array<int, array<string, string>>
	 */
	public $admin_notices = array();

	/**
	 * The single instance of the class.
	 *
	 * @var null|Troubleshoot
	 */
	private static $instance = null;

	public function __construct() {
		\add_action( 'admin_notices', array( $this, 'admin_notices' ) );

		\add_filter( 'plugin_action_links', array( $this, 'troubleshoot_plugin_action' ), 20, 4 );

		\add_action( 'init', array( $this, 'start_troubleshoot_mode' ) );
		\add_action( 'load-plugins.php', array( $this, 'start_troubleshoot_single_plugin_mode' ) );
	}

	public function __clone() {}
	public function __wakeup() {}

	public static function get_instance() : self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Initiate troubleshooting mode.
	 *
	 * Catch when the troubleshooting form has been submitted, and appropriately set required options and cookies.
	 *
	 * @uses current_user_can()
	 * @uses self::initiate_troubleshooting_mode()
	 *
	 * @return void
	 */
	public function start_troubleshoot_mode() {
		if ( ! isset( $_POST['health-check-troubleshoot-mode'] ) || ! \current_user_can( 'view_site_health_checks' ) ) {
			return;
		}

		// Don't enable troubleshooting if nonces are missing or do not match.
		if (
			! isset( $_POST['_wpnonce'] )
			|| ! \wp_verify_nonce( \sanitize_text_field( \wp_unslash( $_POST['_wpnonce'] ) ), 'health-check-enable-troubleshooting' )
		) {
			return;
		}

		self::initiate_troubleshooting_mode();
	}

	/**
	 * Initiate troubleshooting mode for a specific plugin.
	 *
	 * Catch when the troubleshooting link on an individual plugin has been clicked, and appropriately sets the
	 * required options and cookies.
	 *
	 * @uses current_user_can()
	 * @uses ob_start()
	 * @uses self::mu_plugin_exists()
	 * @uses self::get_filesystem_credentials()
	 * @uses self::setup_must_use_plugin()
	 * @uses self::maybe_update_must_use_plugin()
	 * @uses ob_get_clean()
	 * @uses self::initiate_troubleshooting_mode()
	 * @uses wp_redirect()
	 * @uses admin_url()
	 *
	 * @return void
	 */
	public function start_troubleshoot_single_plugin_mode() {
		if ( ! isset( $_GET['health-check-troubleshoot-plugin'] ) || ! \current_user_can( 'view_site_health_checks' ) ) {
			return;
		}

		// Don't enable troubleshooting for an individual plugin if the nonce is missing or invalid.
		if (
			! isset( $_GET['_wpnonce'] )
			|| ! \wp_verify_nonce( \sanitize_text_field( \wp_unslash( $_GET['_wpnonce'] ) ), 'health-check-troubleshoot-plugin-' . \sanitize_text_field( \wp_unslash( $_GET['health-check-troubleshoot-plugin'] ) ) )
		) {
			return;
		}

		ob_start();

		$needs_credentials = false;

		if ( ! self::mu_plugin_exists() ) {
			if ( ! self::get_filesystem_credentials() ) {
				$needs_credentials = true;
			} else {
				$check_output = self::setup_must_use_plugin( false );
				if ( false === $check_output ) {
					$needs_credentials = true;
				}
			}
		} else {
			if ( ! self::maybe_update_must_use_plugin() ) {
				$needs_credentials = true;
			}
		}

		$result = ob_get_clean();

		if ( $needs_credentials ) {
			$this->admin_notices[] = array(
				'message' => (string) $result,
				'type'    => 'warning',
			);
			return;
		}

		$sanitized_plugin_slug = (string) \sanitize_text_field( \wp_unslash( $_GET['health-check-troubleshoot-plugin'] ) );

		self::initiate_troubleshooting_mode(
			array(
				$sanitized_plugin_slug => $sanitized_plugin_slug,
			)
		);

		\wp_redirect( \admin_url( 'plugins.php' ) );
	}

	/**
	 * Add a troubleshooting action link to plugins.
	 *
	 * @param array<string, string> $actions
	 * @param string $plugin_file
	 * @param array<string, bool|string|string[]> $plugin_data
	 * @param string $context
	 *
	 * @return array<string, string>
	 */
	public function troubleshoot_plugin_action( $actions, $plugin_file, $plugin_data, $context ) {
		// Ensure data types are as expected.
		$actions = Types::ensure( $actions, 'array' );
		$plugin_file = Types::ensure( $plugin_file, 'string' );
		$plugin_data = Types::ensure( $plugin_data, 'array' );
		$context = Types::ensure( $context, 'string' );

		// Don't add anything if this is a Must-Use plugin, we can't touch those.
		if ( 'mustuse' === $context ) {
			return $actions;
		}

		// Only add troubleshooting actions to active plugins.
		if ( ! \is_plugin_active( $plugin_file ) ) {
			return $actions;
		}

		// Set a slug if the plugin lives in the plugins directory root.
		if ( ! stristr( $plugin_file, '/' ) ) {
			$plugin_slug = $plugin_file;
		} else { // Set the slug for plugin inside a folder.
			$plugin_slug = explode( '/', $plugin_file );
			$plugin_slug = $plugin_slug[0];
		}

		$actions['troubleshoot'] = sprintf(
			'<a href="%s" id="start-troubleshooting-%s">%s</a>',
			\esc_url(
				\add_query_arg(
					array(
						'health-check-troubleshoot-plugin' => $plugin_slug,
						'_wpnonce'                         => \wp_create_nonce( 'health-check-troubleshoot-plugin-' . $plugin_slug ),
					),
					\admin_url( 'plugins.php' )
				)
			),
			\esc_attr( $plugin_slug ),
			\esc_html__( 'Troubleshoot', 'troubleshooting' )
		);

		return $actions;
	}

	/**
	 * Initiate the troubleshooting mode by setting meta data and cookies.
	 *
	 * @uses is_array()
	 * @uses md5()
	 * @uses rand()
	 * @uses update_option()
	 * @uses setcookie()
	 *
	 * @param array<int|string, string> $allowed_plugins An array of plugins that may be active right away.
	 *
	 * @return void
	 */
	static function initiate_troubleshooting_mode( array $allowed_plugins = array() ) : void {
		if ( ! is_array( $allowed_plugins ) ) {
			$allowed_plugins = (array) $allowed_plugins;
		}

		$loopback_hash = md5( (string) \wp_rand() );
		$client_ip     = \filter_input( INPUT_SERVER, 'REMOTE_ADDR', FILTER_VALIDATE_IP );

		\update_option( 'health-check-allowed-plugins', $allowed_plugins );

		\update_option( 'health-check-disable-plugin-hash', $loopback_hash . md5( $client_ip ) );

		setcookie( 'wp-health-check-disable-plugins', $loopback_hash, 0, \COOKIEPATH, \COOKIE_DOMAIN );
	}

	/**
	 * Check if our Must-Use plugin exists.
	 *
	 * @uses file_exists()
	 *
	 * @return bool
	 */
	static function mu_plugin_exists() {
		return file_exists( \WPMU_PLUGIN_DIR . '/troubleshooting-mode.php' );
	}

	/**
	 * Introduce our Must-Use plugin.
	 *
	 * Move the Must-Use plugin out to the correct directory, and prompt for credentials if required.
	 *
	 * @global $wp_filesystem
	 *
	 * @uses is_dir()
	 * @uses WP_Filesystem::mkdir()
	 * @uses self::display_notice()
	 * @uses esc_html__()
	 * @uses WP_Filesystem::copy()
	 * @uses trailingslashit()
	 * @uses self::session_started()
	 *
	 * @param bool $redirect Whether the user should be redirected after setting up the Must-Use plugin or not.
	 *
	 * @return bool
	 */
	static function setup_must_use_plugin( bool $redirect = true ) : bool {
		global $wp_filesystem;

		// Make sure the `mu-plugins` directory exists.
		if ( ! is_dir( \WPMU_PLUGIN_DIR ) ) {
			if ( ! $wp_filesystem->mkdir( \WPMU_PLUGIN_DIR ) ) {
				self::display_notice( \esc_html__( 'We were unable to create the mu-plugins directory.', 'troubleshooting' ), 'error' );
				return false;
			}
		}

		// Attempt to symlink the must-use plugin first, as the preferred method.
		if ( ! \symlink( \trailingslashit( SITEHEALTH_TROUBLESHOOTING_PLUGIN_DIRECTORY ) . 'mu-plugins/troubleshooting-mode.php', \trailingslashit( \WPMU_PLUGIN_DIR ) . 'troubleshooting-mode.php' ) ) {
			// If the symlink fails, try to copy the file instead.
			if ( ! $wp_filesystem->copy( \trailingslashit( SITEHEALTH_TROUBLESHOOTING_PLUGIN_DIRECTORY ) . 'mu-plugins/troubleshooting-mode.php', \trailingslashit( \WPMU_PLUGIN_DIR ) . 'troubleshooting-mode.php' ) ) {
				self::display_notice( \esc_html__( 'We were unable to copy the plugin file required to enable the Troubleshooting Mode.', 'troubleshooting' ), 'error' );
				return false;
			}
		}

		if ( $redirect ) {
			self::session_started();
		}

		return true;
	}

	/**
	 * Check if our Must-Use plugin needs updating, and do so if necessary.
	 *
	 * @global $wp_filesystem
	 *
	 * @uses self::mu_plugin_exists()
	 * @uses self::get_filesystem_credentials()
	 * @uses get_plugin_data()
	 * @uses trailingslashit()
	 * @uses version_compare()
	 * @uses WP_Filesystem::copy()
	 * @uses esc_html__()
	 *
	 * @return bool
	 */
	static function maybe_update_must_use_plugin() {
		if ( ! self::mu_plugin_exists() ) {
			return false;
		}
		if ( ! self::get_filesystem_credentials() ) {
			return false;
		}

		$current = \get_plugin_data( \trailingslashit( SITEHEALTH_TROUBLESHOOTING_PLUGIN_DIRECTORY ) . 'mu-plugins/troubleshooting-mode.php' );
		$active  = \get_plugin_data( \trailingslashit( \WPMU_PLUGIN_DIR ) . 'troubleshooting-mode.php' );

		$current_version = $current['Version'];
		$active_version  = $active['Version'];

		if ( version_compare( $current_version, $active_version, '>' ) ) {
			global $wp_filesystem;

			if ( ! $wp_filesystem->copy( \trailingslashit( SITEHEALTH_TROUBLESHOOTING_PLUGIN_DIRECTORY ) . 'mu-plugins/troubleshooting-mode.php', \trailingslashit( \WPMU_PLUGIN_DIR ) . 'troubleshooting-mode.php', true ) ) {
				self::display_notice( \esc_html__( 'We were unable to replace the plugin file required to enable the Troubleshooting Mode.', 'troubleshooting' ), 'error' );
				return false;
			}
		}

		return true;
	}

	/**
	 * Output a notice if our Troubleshooting Mode has been initiated.
	 *
	 * @uses self::display_notice()
	 * @uses sprintf()
	 * @uses esc_html__()
	 * @uses esc_url()
	 * @uses admin_url()
	 *
	 * @return void
	 */
	static function session_started() {
		self::display_notice(
			sprintf(
				'%s<br>%s',
				\esc_html__( 'You have successfully enabled Troubleshooting Mode, all plugins will appear inactive until you disable Troubleshooting Mode, or log out and back in again.', 'troubleshooting' ),
				sprintf(
					'<a href="%1$s">%2$s</a><script type="text/javascript">window.location = "%1$s";</script>',
					\esc_url( \admin_url( '/' ) ),
					\esc_html__( 'Return to the Dashboard', 'troubleshooting' )
				)
			)
		);
	}

	/**
	 * Display styled admin notices.
	 *
	 * @uses printf()
	 *
	 * @param string $message A sanitized string containing our notice message.
	 * @param string $status  A string representing the status type.
	 *
	 * @return void
	 */
	static function display_notice( $message, $status = 'success' ) {
		printf(
			'<div class="notice notice-%s inline"><p>%s</p></div>',
			\esc_attr( $status ),
			$message // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Content may contain markup and other expected elements, and is not based on user generated input.
		);
	}

	/**
	 * Display admin notices if we have any queued.
	 *
	 * @return void
	 */
	public function admin_notices() : void {
		foreach ( $this->admin_notices as $admin_notice ) {
			printf(
				'<div class="notice notice-%s"><p>%s</p></div>',
				\esc_attr( $admin_notice['type'] ),
				$admin_notice['message'] // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Content may contain markup and other expected elements, and is not based on user generated input.
			);
		}
	}

	/**
	 * Conditionally show a form for providing filesystem credentials when introducing our troubleshooting mode plugin.
	 *
	 * @uses wp_nonce_url()
	 * @uses add_query_arg()
	 * @uses admin_url()
	 * @uses request_filesystem_credentials()
	 * @uses WP_Filesystem
	 *
	 * @param array<string, string> $args Any WP_Filesystem arguments you wish to pass.
	 *
	 * @return bool
	 */
	static function get_filesystem_credentials( $args = array() ) : bool {
		$args = array_merge(
			array(
				'page' => 'health-check',
				'tab'  => 'troubleshoot',
			),
			$args
		);

		$url   = \wp_nonce_url( \add_query_arg( $args, \admin_url() ) );
		$creds = \request_filesystem_credentials( $url, '', false, \WP_CONTENT_DIR, array( 'health-check-troubleshoot-mode', 'action', '_wpnonce' ) );
		if ( false === $creds ) {
			return false;
		}

		// @phpstan-ignore-next-line -- PHPStan doesn't like that we _can_ pass `true` to `WP_Filesystem`, but in some special scenarios, we do, or else a fatal error is thrown.
		if ( ! \WP_Filesystem( $creds ) ) {
			\request_filesystem_credentials( $url, '', true, \WPMU_PLUGIN_DIR, array( 'health-check-troubleshoot-mode', 'action', '_wpnonce' ) );
			return false;
		}

		return true;
	}

	/**
	 * Display the form for enabling troubleshooting mode.
	 *
	 * @uses printf()
	 * @uses esc_html__()
	 * @uses self::mu_plugin_exists()
	 * @uses self::maybe_update_must_use_plugin()
	 * @uses self::session_started()
	 * @uses self::get_filesystem_credentials()
	 * @uses self::setup_must_use_plugin()
	 * @uses MustUse::is_troubleshooting()
	 * @uses esc_url()
	 * @uses add_query_arg()
	 * @uses esc_html_e()
	 *
	 * @return void
	 */
	static function show_enable_troubleshoot_form() {
		if (
			isset( $_POST['health-check-troubleshoot-mode'] )
			&& isset( $_POST['_wpnonce'] )
			&& \wp_verify_nonce( \sanitize_text_field( \wp_unslash( $_POST['_wpnonce'] ) ), 'health-check-enable-troubleshooting' )
		) {
			if ( self::mu_plugin_exists() ) {
				if ( ! self::maybe_update_must_use_plugin() ) {
					return;
				}
				self::session_started();
			} else {
				if ( ! self::get_filesystem_credentials() ) {
					return;
				} else {
					self::setup_must_use_plugin();
				}
			}
		}

		?>
		<div>

			<?php
			$troubleshooting = null;

			if ( class_exists( 'SiteHealth\Troubleshooting\MustUse' ) ) {
				$troubleshooting = new MustUse();
			}

			if ( null !== $troubleshooting && is_callable( array( $troubleshooting, 'is_troubleshooting' ) ) && $troubleshooting->is_troubleshooting() ) :
				?>
				<p style="text-align: center;">
					<a class="button button-primary" href="<?php echo \esc_url( \add_query_arg( array( 'health-check-disable-troubleshooting' => true ) ) ); ?>">
						<?php \esc_html_e( 'Disable Troubleshooting Mode', 'troubleshooting' ); ?>
					</a>
				</p>

			<?php else : ?>

				<form action="" method="post" class="form" style="text-align: center;">
					<?php \wp_nonce_field( 'health-check-enable-troubleshooting' ); ?>
					<input type="hidden" name="health-check-troubleshoot-mode" value="true">
					<p>
						<button type="submit" class="button button-primary">
							<?php \esc_html_e( 'Enable Troubleshooting Mode', 'troubleshooting' ); ?>
						</button>
					</p>
				</form>

			<?php endif; ?>

		</div>

		<?php
	}
}

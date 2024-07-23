<?php
/**
 * Tests to determine if the WordPress loopbacks are able to run unhindered.
 *
 * @package Health Check
 */

namespace SiteHealth\Troubleshooting;

// Make sure the file is not directly accessible.
if ( ! defined( 'ABSPATH' ) ) {
	die( 'We\'re sorry, but you can not directly access this file.' );
}

/**
 * Class Health_Check_Loopback
 */
class Loopback {
	/**
	 * Run a loopback test on our site.
	 *
	 * @uses wp_unslash()
	 * @uses base64_encode()
	 * @uses admin_url()
	 * @uses add_query_arg()
	 * @uses is_array()
	 * @uses implode()
	 * @uses wp_remote_get()
	 * @uses compact()
	 * @uses is_wp_error()
	 * @uses wp_remote_retrieve_response_code()
	 * @uses sprintf()
	 *
	 * @param null|string       $disable_plugin_hash Optional. A hash to send with our request to disable any plugins.
	 * @param null|string|array<int, string> $allowed_plugins     Optional. A string or array of approved plugin slugs that can run even when we globally ignore plugins.
	 *
	 * @return array<string, string>
	 */
	static function can_perform_loopback( $disable_plugin_hash = null, $allowed_plugins = null ) {
		$cookies = \wp_unslash( $_COOKIE );
		$timeout = 10;
		$headers = array(
			'Cache-Control' => 'no-cache',
		);

		// Include Basic auth in loopback requests.
		if ( isset( $_SERVER['PHP_AUTH_USER'] ) && isset( $_SERVER['PHP_AUTH_PW'] ) ) {
			$headers['Authorization'] = 'Basic ' . base64_encode( \wp_unslash( $_SERVER['PHP_AUTH_USER'] ) . ':' . \wp_unslash( $_SERVER['PHP_AUTH_PW'] ) );
		}

		$url = \admin_url();

		if ( ! empty( $disable_plugin_hash ) ) {
			$url = \add_query_arg(
				array(
					'health-check-disable-plugin-hash' => $disable_plugin_hash,
				),
				$url
			);
		}
		if ( ! empty( $allowed_plugins ) ) {
			if ( ! is_array( $allowed_plugins ) ) {
				$allowed_plugins = (array) $allowed_plugins;
			}

			$url = \add_query_arg(
				array(
					'health-check-allowed-plugins' => implode( ',', $allowed_plugins ),
				),
				$url
			);
		}

		$r = \wp_remote_get( $url, compact( 'cookies', 'headers', 'timeout' ) );

		if ( \is_wp_error( $r ) ) {
			return array(
				'status'  => 'critical',
				'message' => sprintf(
					'%s<br>%s',
					\esc_html__( 'The loopback request to your site failed, this means features relying on them are not currently working as expected.', 'troubleshooting' ),
					sprintf(
						/* translators: %1$d: The HTTP response code. %2$s: The error message returned. */
						\esc_html__( 'Error encountered: (%1$d) %2$s', 'troubleshooting' ),
						\wp_remote_retrieve_response_code( $r ),
						$r->get_error_message()
					)
				),
			);
		}

		if ( 200 !== \wp_remote_retrieve_response_code( $r ) ) {
			return array(
				'status'  => 'recommended',
				'message' => sprintf(
					/* translators: %d: The HTTP response code returned. */
					\esc_html__( 'The loopback request returned an unexpected http status code, %d, it was not possible to determine if this will prevent features from working as expected.', 'troubleshooting' ),
					\wp_remote_retrieve_response_code( $r )
				),
			);
		}

		return array(
			'status'  => 'good',
			'message' => \__( 'The loopback request to your site completed successfully.', 'troubleshooting' ),
		);
	}

	/**
	 * Perform the loopback check, but ensure no plugins are enabled when we do so.
	 *
	 * @uses ob_start()
	 * @uses Health_Check_Troubleshoot::mu_plugin_exists()
	 * @uses Health_Check::get_filesystem_credentials()
	 * @uses Health_Check_Troubleshoot::setup_must_use_plugin()
	 * @uses Health_Check_Troubleshoot::maybe_update_must_use_plugin()
	 * @uses ob_get_clean()
	 * @uses wp_send_json_error()
	 * @uses md5()
	 * @uses rand()
	 * @uses update_option()
	 * @uses Health_Check_Loopback::can_perform_loopback()
	 * @uses sprintf()
	 * @uses esc_attr()
	 * @uses esc_html__()
	 * @uses esc_html()
	 * @uses wp_send_json_success()
	 *
	 * @return void
	 */
	static function loopback_no_plugins() {
		\check_ajax_referer( 'health-check-loopback-no-plugins' );

		if ( ! \current_user_can( 'view_site_health_checks' ) ) {
			\wp_send_json_error();
		}

		ob_start();

		$needs_creds = false;

		if ( ! Troubleshoot::mu_plugin_exists() ) {
			if ( ! Troubleshoot::get_filesystem_credentials() ) {
				$needs_creds = true;
			} else {
				$check_output = Troubleshoot::setup_must_use_plugin();
				if ( false === $check_output ) {
					$needs_creds = true;
				}
			}
		} else {
			if ( ! Troubleshoot::maybe_update_must_use_plugin() ) {
				$needs_creds = true;
			}
		}

		$result = ob_get_clean();

		if ( $needs_creds ) {
			\wp_send_json_error( $result );
		}

		$loopback_hash = md5( (string) \wp_rand() );
		\update_option( 'health-check-disable-plugin-hash', $loopback_hash );
		\update_option( 'health-check-default-theme', 'yes' );

		$no_plugin_test = self::can_perform_loopback( $loopback_hash );

		$message = sprintf(
			'<br><span class="%s"></span> %s: %s',
			\esc_attr( $no_plugin_test['status'] ),
			\esc_html__( 'Result from testing without any plugins active and a default theme', 'troubleshooting' ),
			$no_plugin_test['message']
		);

		if ( 'error' !== $no_plugin_test['status'] ) {
			$plugins = \wp_get_active_and_valid_plugins();
			$theme   = \wp_get_theme();

			$message .= '<table id="loopback-individual-plugins-list">';

			foreach ( $plugins as $single_plugin ) {
				$plugin = \get_plugin_data( $single_plugin );

				$message .= sprintf(
					'<tr data-test-plugin="%s" class="not-tested"><td>%s</td><td class="individual-loopback-test-status">%s</td></tr>',
					\esc_attr( \plugin_basename( $single_plugin ) ),
					\esc_html( $plugin['Name'] ),
					\esc_html__( 'Waiting...', 'troubleshooting' )
				);
			}

			$message .= sprintf(
				'<tr id="test-single-no-theme"><td>%s</td><td class="individual-loopback-test-status">%s</td></tr>',
				sprintf(
					// translators: %s: The active theme name.
					\esc_html__( 'Active theme: %s', 'troubleshooting' ),
					$theme['name']
				),
				\esc_html__( 'Waiting...', 'troubleshootingk' )
			);

			$message .= '</table>';

			$message .= '<br><button type="button" id="loopback-individual-plugins" class="button button-primary">Test individual plugins</button>';
		}

		$response = array(
			'message' => $message,
		);

		\delete_option( 'health-check-default-theme' );

		\wp_send_json_success( $response );
	}

	/**
	 * Test individual plugins for loopback compatibility issues.
	 *
	 * This function will perform the loopback check, without any plugins, then conditionally enables one plugin at a time.
	 *
	 * @uses ob_start()
	 * @uses Health_Check_Troubleshoot::mu_plugin_exists()
	 * @uses Health_Check::get_filesystem_credentials()
	 * @uses Health_Check_Troubleshoot::setup_must_use_plugin()
	 * @uses ob_get_clean()
	 * @uses wp_send_json_error()
	 * @uses delete_option()
	 * @uses get_option()
	 * @uses md5()
	 * @uses rand()
	 * @uses update_option()
	 * @uses explode()
	 * @uses Health_Check_Loopback::can_perform_loopback()
	 * @uses sprintf()
	 * @uses esc_attr()
	 * @uses esc_html__()
	 * @uses esc_html()
	 * @uses wp_send_json_success()
	 *
	 * @return void
	 */
	static function loopback_test_individual_plugins() : void {
		\check_ajax_referer( 'health-check-loopback-individual-plugins' );

		if ( ! \current_user_can( 'view_site_health_checks' ) ) {
			\wp_send_json_error();
		}

		ob_start();

		$needs_creds = false;

		if ( ! Troubleshoot::mu_plugin_exists() ) {
			if ( ! Troubleshoot::get_filesystem_credentials() ) {
				$needs_creds = true;
			} else {
				Troubleshoot::setup_must_use_plugin();
			}
		}

		$result = ob_get_clean();

		if ( $needs_creds ) {
			\wp_send_json_error( $result );
		}

		\delete_option( 'health-check-disable-plugin-hash' );

		$loopback_hash = md5( (string) \wp_rand() );
		\update_option( 'health-check-disable-plugin-hash', $loopback_hash );

		$plugin_slug = explode( '/', $_POST['plugin'] );
		$plugin_slug = $plugin_slug[0];

		$single_test = self::can_perform_loopback( $loopback_hash, $plugin_slug );

		$message = sprintf(
			'<span class="%s"></span> %s',
			\esc_attr( $single_test['status'] ),
			$single_test['message']
		);

		$response = array(
			'message' => $message,
		);

		\wp_send_json_success( $response );
	}

	static function loopback_test_default_theme() : void {
		\check_ajax_referer( 'health-check-loopback-default-theme' );

		if ( ! \current_user_can( 'view_site_health_checks' ) ) {
			\wp_send_json_error();
		}

		ob_start();

		$needs_creds = false;

		if ( ! Troubleshoot::mu_plugin_exists() ) {
			if ( ! Troubleshoot::get_filesystem_credentials() ) {
				$needs_creds = true;
			} else {
				Troubleshoot::setup_must_use_plugin();
			}
		}

		$result = ob_get_clean();

		if ( $needs_creds ) {
			\wp_send_json_error( $result );
		}

		\delete_option( 'health-check-disable-plugin-hash' );

		$loopback_hash = md5( (string) \wp_rand() );
		\update_option( 'health-check-disable-plugin-hash', $loopback_hash );

		$message = '';

		// Test without a theme active.
		\update_option( 'health-check-default-theme', 'yes' );

		$theme_test = self::can_perform_loopback( $loopback_hash, '' );

		$message .= sprintf(
			'<span class="%s"></span> %s',
			\esc_attr( $theme_test['status'] ),
			$theme_test['message']
		);

		\delete_option( 'health-check-default-theme' );

		$response = array(
			'message' => $message,
		);

		\wp_send_json_success( $response );
	}
}

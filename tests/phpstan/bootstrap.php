<?php
/**
 * Bootstrap file for declarations needed by PHPStan.
 *
 * Any declarations here should match what their values would be within WordPress core,
 * or what the plugin would dictate them to be when ran on the wp-env local environment.
 */

if ( ! defined( 'ABSPATH' ) ) { define( 'ABSPATH', '/var/www/html' ); }
if ( ! defined( 'WP_CONTENT_DIR' ) ) { define( 'WP_CONTENT_DIR', ABSPATH . '/wp-content' ); }
if ( ! defined( 'WP_PLUGIN_DIR' ) ) { define( 'WP_PLUGIN_DIR', ABSPATH . '/wp-content/plugins' ); }
if ( ! defined( 'COOKIE_DOMAIN' ) ) { define( 'COOKIE_DOMAIN', 'localhost' ); }
if ( ! defined( 'COOKIEPATH' ) ) { define( 'COOKIEPATH', '/' ); }

if ( ! defined( 'TROUBLESHOOTING_PLUGIN_DIRECTORY' ) ) { define( 'TROUBLESHOOTING_PLUGIN_DIRECTORY', WP_PLUGIN_DIR . '/troubleshooting' ); }
if ( ! defined( 'SITEHEALTH_TROUBLESHOOTING_PLUGIN_DIRECTORY' ) ) { define( 'SITEHEALTH_TROUBLESHOOTING_PLUGIN_DIRECTORY', WP_PLUGIN_DIR . '/troubleshooting' ); }

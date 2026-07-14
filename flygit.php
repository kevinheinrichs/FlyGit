<?php
/**
 * Plugin Name: FlyGit
 * Plugin URI: https://github.com/kevinheinrichs/FlyGit/
 * Description: Pull-basierte Git-Deployments für WordPress. Installiert und aktualisiert Plugins & Themes automatisch aus GitHub-Repositories — einzeln oder flottenweit über ein zentrales Manifest.
 * Version: 2.1.0
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Author: Kevin Heinrichs
 * Author URI: https://www.kevinheinrichs.com/
 * License: GPL-2.0-or-later
 * Text Domain: flygit
 *
 * @package FlyGit
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'FLYGIT_VERSION', '2.1.0' );
define( 'FLYGIT_PLUGIN_FILE', __FILE__ );
define( 'FLYGIT_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'FLYGIT_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'FLYGIT_BASENAME', plugin_basename( __FILE__ ) );

require_once FLYGIT_PLUGIN_DIR . 'includes/class-flygit-options.php';
require_once FLYGIT_PLUGIN_DIR . 'includes/class-flygit-crypto.php';
require_once FLYGIT_PLUGIN_DIR . 'includes/class-flygit-logger.php';
require_once FLYGIT_PLUGIN_DIR . 'includes/class-flygit-github.php';
require_once FLYGIT_PLUGIN_DIR . 'includes/class-flygit-registry.php';
require_once FLYGIT_PLUGIN_DIR . 'includes/class-flygit-installer.php';
require_once FLYGIT_PLUGIN_DIR . 'includes/class-flygit-updater.php';
require_once FLYGIT_PLUGIN_DIR . 'includes/class-flygit-manifest.php';
require_once FLYGIT_PLUGIN_DIR . 'includes/class-flygit-webhook.php';
require_once FLYGIT_PLUGIN_DIR . 'includes/class-flygit-admin.php';

/**
 * Service container (lazy singletons).
 *
 * @param string $service Service key.
 * @return object|null
 */
function flygit_service( $service ) {
	static $instances = array();

	if ( isset( $instances[ $service ] ) ) {
		return $instances[ $service ];
	}

	switch ( $service ) {
		case 'registry':
			$instances[ $service ] = new FlyGit_Registry();
			break;
		case 'installer':
			$instances[ $service ] = new FlyGit_Installer( flygit_service( 'registry' ) );
			break;
		case 'manifest':
			$instances[ $service ] = new FlyGit_Manifest( flygit_service( 'registry' ), flygit_service( 'installer' ) );
			break;
		case 'updater':
			$instances[ $service ] = new FlyGit_Updater( flygit_service( 'registry' ), flygit_service( 'installer' ), flygit_service( 'manifest' ) );
			break;
		case 'webhook':
			$instances[ $service ] = new FlyGit_Webhook( flygit_service( 'registry' ), flygit_service( 'updater' ) );
			break;
		case 'admin':
			$instances[ $service ] = new FlyGit_Admin( flygit_service( 'registry' ), flygit_service( 'installer' ), flygit_service( 'updater' ), flygit_service( 'manifest' ) );
			break;
		default:
			return null;
	}

	return $instances[ $service ];
}

/**
 * Wire up runtime hooks.
 */
function flygit_bootstrap() {
	$updater = flygit_service( 'updater' );

	// cron.
	add_filter( 'cron_schedules', array( $updater, 'register_cron_interval' ) );
	add_action( 'flygit_check_updates', array( $updater, 'run_scheduled_checks' ) );
	add_action( 'flygit_run_single_check', array( $updater, 'run_single_check' ), 10, 2 );
	add_action( 'flygit_run_manifest_sync', array( $updater, 'run_manifest_sync' ) );

	// Native update UI integration.
	add_filter( 'pre_set_site_transient_update_plugins', array( $updater, 'inject_plugin_updates' ) );
	add_filter( 'pre_set_site_transient_update_themes', array( $updater, 'inject_theme_updates' ) );
	add_filter( 'upgrader_pre_download', array( $updater, 'maybe_provide_package' ), 10, 3 );
	add_action( 'upgrader_process_complete', array( $updater, 'after_native_upgrade' ), 10, 2 );

	// REST webhook.
	add_action( 'rest_api_init', array( flygit_service( 'webhook' ), 'register_routes' ) );

	// Admin.
	if ( is_admin() ) {
		$admin = flygit_service( 'admin' );
		add_action( 'admin_menu', array( $admin, 'register_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $admin, 'enqueue_assets' ) );
		add_action( 'admin_notices', array( $admin, 'render_notices' ) );

		add_action( 'admin_post_flygit_install', array( $admin, 'handle_install' ) );
		add_action( 'admin_post_flygit_check_now', array( $admin, 'handle_check_now' ) );
		add_action( 'admin_post_flygit_update_now', array( $admin, 'handle_update_now' ) );
		add_action( 'admin_post_flygit_toggle_auto_update', array( $admin, 'handle_toggle_auto_update' ) );
		add_action( 'admin_post_flygit_detach', array( $admin, 'handle_detach' ) );
		add_action( 'admin_post_flygit_delete', array( $admin, 'handle_delete' ) );
		add_action( 'admin_post_flygit_save_settings', array( $admin, 'handle_save_settings' ) );
		add_action( 'admin_post_flygit_save_manifest', array( $admin, 'handle_save_manifest' ) );
		add_action( 'admin_post_flygit_manifest_sync', array( $admin, 'handle_manifest_sync' ) );
		add_action( 'admin_post_flygit_clear_log', array( $admin, 'handle_clear_log' ) );
		add_action( 'admin_post_flygit_regenerate_secret', array( $admin, 'handle_regenerate_secret' ) );
	}
}
add_action( 'plugins_loaded', 'flygit_bootstrap' );

/**
 * Activation: seed defaults and schedule the update check.
 */
function flygit_activate() {
	FlyGit_Options::ensure_defaults();

	// During the activation request plugins_loaded already fired, so the
	// custom interval filter is not registered yet — add it here first.
	add_filter( 'cron_schedules', array( flygit_service( 'updater' ), 'register_cron_interval' ) );

	if ( ! wp_next_scheduled( 'flygit_check_updates' ) ) {
		wp_schedule_event( time() + 120, FlyGit_Updater::CRON_INTERVAL_KEY, 'flygit_check_updates' );
	}

	FlyGit_Logger::log( 'info', 'FlyGit ' . FLYGIT_VERSION . ' aktiviert.' );
}
register_activation_hook( __FILE__, 'flygit_activate' );

/**
 * Deactivation: clear all scheduled events.
 */
function flygit_deactivate() {
	wp_clear_scheduled_hook( 'flygit_check_updates' );
	wp_clear_scheduled_hook( 'flygit_run_manifest_sync' );

	FlyGit_Logger::log( 'info', 'FlyGit deaktiviert, Cron-Events entfernt.' );
}
register_deactivation_hook( __FILE__, 'flygit_deactivate' );

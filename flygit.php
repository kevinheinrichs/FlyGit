<?php
/**
 * Plugin Name: FlyGit
 * Plugin URI: https://github.com/kevinheinrichs/FlyGit/
 * Description: Install themes and plugins from public and private Git repositories directly within WordPress.
 * Version: 1.0.0
 * Author: Kevin Heinrichs
 * Author URI: https://www.kevinheinrichs.com/
 * Text Domain: flygit
 * Domain Path: /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

define( 'FLYGIT_VERSION', '1.0.0' );
define( 'FLYGIT_PLUGIN_FILE', __FILE__ );
define( 'FLYGIT_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'FLYGIT_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

require_once ABSPATH . 'wp-admin/includes/plugin.php';
require_once ABSPATH . 'wp-admin/includes/theme.php';
require_once ABSPATH . 'wp-admin/includes/file.php';

require_once FLYGIT_PLUGIN_DIR . 'includes/class-flygit-installer.php';
require_once FLYGIT_PLUGIN_DIR . 'includes/class-flygit-admin.php';
require_once FLYGIT_PLUGIN_DIR . 'includes/class-flygit-webhook-handler.php';

/**
 * Initialize the FlyGit plugin.
 */
function flygit_init() {
    $installer = new FlyGit_Installer();
    $admin     = new FlyGit_Admin( $installer );
    $webhook   = new FlyGit_Webhook_Handler( $installer );

    $GLOBALS['flygit_installer'] = $installer;
    $GLOBALS['flygit_admin']     = $admin;
    $GLOBALS['flygit_webhook']   = $webhook;

    add_action( 'admin_menu', array( $admin, 'register_menu' ) );
    add_action( 'admin_post_flygit_install', array( $admin, 'handle_install_request' ) );
    add_action( 'admin_post_flygit_save_webhook_settings', array( $admin, 'handle_webhook_settings' ) );
    add_action( 'admin_post_flygit_uninstall', array( $admin, 'handle_uninstall_request' ) );
    add_action( 'admin_enqueue_scripts', array( $admin, 'enqueue_assets' ) );

    add_action( 'rest_api_init', array( $webhook, 'register_routes' ) );
}
add_action( 'plugins_loaded', 'flygit_init' );

/**
 * Load plugin textdomain for translations.
 */
function flygit_load_textdomain() {
    load_plugin_textdomain( 'flygit', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
}
add_action( 'init', 'flygit_load_textdomain' );

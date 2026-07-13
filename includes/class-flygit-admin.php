<?php
/**
 * Admin controller: menu, assets, form handlers.
 *
 * @package FlyGit
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renders the settings screen (tabbed SPA-feel, vanilla CSS/JS) and
 * handles all admin-post actions. Assets load ONLY on our screen.
 */
class FlyGit_Admin {

	/**
	 * Registry.
	 *
	 * @var FlyGit_Registry
	 */
	protected $registry;

	/**
	 * Installer.
	 *
	 * @var FlyGit_Installer
	 */
	protected $installer;

	/**
	 * Updater.
	 *
	 * @var FlyGit_Updater
	 */
	protected $updater;

	/**
	 * Manifest.
	 *
	 * @var FlyGit_Manifest
	 */
	protected $manifest;

	/**
	 * Screen hook suffix.
	 *
	 * @var string
	 */
	protected $hook = '';

	/**
	 * Constructor.
	 *
	 * @param FlyGit_Registry  $registry  Registry.
	 * @param FlyGit_Installer $installer Installer.
	 * @param FlyGit_Updater   $updater   Updater.
	 * @param FlyGit_Manifest  $manifest  Manifest.
	 */
	public function __construct( FlyGit_Registry $registry, FlyGit_Installer $installer, FlyGit_Updater $updater, FlyGit_Manifest $manifest ) {
		$this->registry  = $registry;
		$this->installer = $installer;
		$this->updater   = $updater;
		$this->manifest  = $manifest;
	}

	/**
	 * Register the admin menu.
	 */
	public function register_menu() {
		$pending = count( $this->registry->pending_updates() );
		$badge   = $pending > 0 ? ' <span class="awaiting-mod count-' . $pending . '"><span class="pending-count">' . $pending . '</span></span>' : '';

		$this->hook = add_menu_page(
			__( 'FlyGit', 'flygit' ),
			__( 'FlyGit', 'flygit' ) . $badge,
			'manage_options',
			'flygit',
			array( $this, 'render_page' ),
			'dashicons-cloud-upload',
			66
		);
	}

	/**
	 * Enqueue assets on our screen only.
	 *
	 * @param string $hook Current screen hook.
	 */
	public function enqueue_assets( $hook ) {
		if ( $hook !== $this->hook ) {
			return;
		}

		wp_enqueue_style( 'flygit-admin', FLYGIT_PLUGIN_URL . 'assets/css/admin.css', array(), FLYGIT_VERSION );
		wp_enqueue_script( 'flygit-admin', FLYGIT_PLUGIN_URL . 'assets/js/admin.js', array(), FLYGIT_VERSION, true );
	}

	/**
	 * Render the page shell.
	 */
	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$data = array(
			'installations' => $this->enriched_installations(),
			'settings'      => FlyGit_Options::all(),
			'log'           => FlyGit_Logger::entries( 50 ),
			'webhook_url'   => rest_url( 'flygit/v1/sync' ),
			'has_token'     => '' !== FlyGit_Options::github_token(),
			'next_check'    => wp_next_scheduled( 'flygit_check_updates' ),
			'active_tab'    => isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'dashboard', // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		);

		require FLYGIT_PLUGIN_DIR . 'includes/views/admin-page.php';
	}

	/**
	 * Installations enriched with live filesystem state.
	 *
	 * @return array[]
	 */
	protected function enriched_installations() {
		$items = array();

		foreach ( $this->registry->all() as $item ) {
			$header = $this->installer->installed_header( $item );

			$item['display_name']   = $header['name'];
			$item['local_version']  = $header['version'];
			$item['update_pending'] = ! empty( $item['remote_sha'] ) && $item['remote_sha'] !== $item['installed_sha'];

			if ( 'plugin' === $item['type'] ) {
				$file            = $this->installer->plugin_file( $item['slug'] );
				$item['active']  = '' !== $file && is_plugin_active( $file );
				$item['on_disk'] = '' !== $file;
			} else {
				$theme           = wp_get_theme( $item['slug'] );
				$item['on_disk'] = $theme->exists();
				$item['active']  = $item['on_disk'] && wp_get_theme()->get_stylesheet() === $item['slug'];
			}

			$items[] = $item;
		}

		return $items;
	}

	/* ---------------------------------------------------------------------
	 * Notices (flash messages via transient).
	 * ------------------------------------------------------------------- */

	/**
	 * Store a flash notice for the current user.
	 *
	 * @param string $type    success|error|warning|info.
	 * @param string $message Message.
	 */
	protected function flash( $type, $message ) {
		set_transient( 'flygit_notice_' . get_current_user_id(), array( 'type' => $type, 'message' => $message ), 60 );
	}

	/**
	 * Output stored notices on our screen.
	 */
	public function render_notices() {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || false === strpos( (string) $screen->id, 'flygit' ) ) {
			return;
		}

		$notice = get_transient( 'flygit_notice_' . get_current_user_id() );
		if ( ! is_array( $notice ) ) {
			return;
		}

		delete_transient( 'flygit_notice_' . get_current_user_id() );

		printf(
			'<div class="notice notice-%1$s is-dismissible"><p>%2$s</p></div>',
			esc_attr( in_array( $notice['type'], array( 'success', 'error', 'warning', 'info' ), true ) ? $notice['type'] : 'info' ),
			esc_html( $notice['message'] )
		);
	}

	/**
	 * Redirect back to the plugin page.
	 *
	 * @param string $tab Target tab.
	 */
	protected function back( $tab = 'dashboard' ) {
		wp_safe_redirect( add_query_arg( array( 'page' => 'flygit', 'tab' => $tab ), admin_url( 'admin.php' ) ) );
		exit;
	}

	/**
	 * Common guard for all POST handlers.
	 *
	 * @param string $nonce_action Nonce action.
	 */
	protected function guard( $nonce_action ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Keine Berechtigung.', 'flygit' ) );
		}

		check_admin_referer( $nonce_action );
	}

	/* ---------------------------------------------------------------------
	 * POST handlers.
	 * ------------------------------------------------------------------- */

	/**
	 * Install a new repository.
	 */
	public function handle_install() {
		$this->guard( 'flygit_install' );

		$repo_ref = isset( $_POST['repository'] ) ? sanitize_text_field( wp_unslash( $_POST['repository'] ) ) : '';
		$type     = isset( $_POST['type'] ) && 'theme' === $_POST['type'] ? 'theme' : 'plugin';
		$branch   = isset( $_POST['branch'] ) && '' !== trim( (string) wp_unslash( $_POST['branch'] ) ) ? sanitize_text_field( wp_unslash( $_POST['branch'] ) ) : 'main';
		$token    = isset( $_POST['token'] ) ? trim( (string) wp_unslash( $_POST['token'] ) ) : '';
		$slug     = isset( $_POST['slug'] ) ? sanitize_key( wp_unslash( $_POST['slug'] ) ) : '';

		$parsed = FlyGit_GitHub::parse_repo( $repo_ref );
		if ( is_wp_error( $parsed ) ) {
			$this->flash( 'error', $parsed->get_error_message() );
			$this->back( 'add' );
		}

		$effective_token = '' !== $token ? $token : FlyGit_Options::github_token();

		// Resolve current head first (validates repo + branch + token).
		$head = FlyGit_GitHub::latest_commit( $parsed['owner'], $parsed['repo'], $branch, $effective_token );
		if ( is_wp_error( $head ) ) {
			$this->flash( 'error', $head->get_error_message() );
			$this->back( 'add' );
		}

		$result = $this->installer->deploy(
			array(
				'type'   => $type,
				'owner'  => $parsed['owner'],
				'repo'   => $parsed['repo'],
				'branch' => $branch,
				'ref'    => $head['sha'],
				'token'  => $effective_token,
				'slug'   => $slug,
			)
		);

		if ( is_wp_error( $result ) ) {
			$this->flash( 'error', $result->get_error_message() );
			$this->back( 'add' );
		}

		$this->registry->upsert(
			array(
				'type'           => $type,
				'slug'           => $result['slug'],
				'owner'          => $parsed['owner'],
				'repo'           => $parsed['repo'],
				'branch'         => $branch,
				'token'          => '' !== $token ? FlyGit_Crypto::encrypt( $token ) : '',
				'managed_by'     => 'manual',
				'installed_sha'  => $head['sha'],
				'remote_sha'     => $head['sha'],
				'remote_date'    => $head['date'],
				'remote_message' => $head['message'],
				'etag'           => $head['etag'],
				'last_checked'   => time(),
				'last_updated'   => time(),
				'last_error'     => '',
			)
		);

		FlyGit_Logger::log( 'success', sprintf( '"%s" installiert (%s @ %s).', $result['slug'], $parsed['owner'] . '/' . $parsed['repo'], substr( $head['sha'], 0, 7 ) ) );

		$this->flash( 'success', sprintf( __( '"%1$s" wurde installiert (Version %2$s).', 'flygit' ), $result['name'], $result['version'] ? $result['version'] : substr( $head['sha'], 0, 7 ) ) );
		$this->back();
	}

	/**
	 * Manual "check now" for one installation.
	 */
	public function handle_check_now() {
		$this->guard( 'flygit_check_now' );

		$id   = isset( $_POST['installation_id'] ) ? sanitize_text_field( wp_unslash( $_POST['installation_id'] ) ) : '';
		$item = $this->registry->find( $id );

		if ( ! $item ) {
			$this->flash( 'error', __( 'Installation nicht gefunden.', 'flygit' ) );
			$this->back();
		}

		$result = $this->updater->check_installation( $item, false );

		if ( is_wp_error( $result ) ) {
			$this->flash( 'error', $result->get_error_message() );
			$this->back();
		}

		$fresh = $this->registry->find( $id );

		if ( ! empty( $fresh['remote_sha'] ) && $fresh['remote_sha'] !== $fresh['installed_sha'] ) {
			$this->flash( 'info', sprintf( __( 'Update verfügbar: %s', 'flygit' ), $fresh['remote_message'] ) );
		} else {
			$this->flash( 'success', __( 'Alles aktuell.', 'flygit' ) );
		}

		$this->back();
	}

	/**
	 * Manual update trigger.
	 */
	public function handle_update_now() {
		$this->guard( 'flygit_update_now' );

		$id   = isset( $_POST['installation_id'] ) ? sanitize_text_field( wp_unslash( $_POST['installation_id'] ) ) : '';
		$item = $this->registry->find( $id );

		if ( ! $item ) {
			$this->flash( 'error', __( 'Installation nicht gefunden.', 'flygit' ) );
			$this->back();
		}

		// Ensure we know the latest head.
		$this->updater->check_installation( $item, false );
		$item = $this->registry->find( $id );

		if ( empty( $item['remote_sha'] ) || $item['remote_sha'] === $item['installed_sha'] ) {
			$this->flash( 'success', __( 'Bereits auf dem neuesten Stand.', 'flygit' ) );
			$this->back();
		}

		$result = $this->updater->apply_update( $item );

		if ( is_wp_error( $result ) ) {
			$this->flash( 'error', $result->get_error_message() );
		} else {
			$this->flash( 'success', sprintf( __( '"%s" wurde aktualisiert.', 'flygit' ), $item['slug'] ) );
		}

		$this->back();
	}

	/**
	 * Toggle per-installation auto updates.
	 */
	public function handle_toggle_auto_update() {
		$this->guard( 'flygit_toggle_auto_update' );

		$id   = isset( $_POST['installation_id'] ) ? sanitize_text_field( wp_unslash( $_POST['installation_id'] ) ) : '';
		$item = $this->registry->find( $id );

		if ( $item ) {
			$new = empty( $item['auto_update'] );
			$this->registry->patch( $id, array( 'auto_update' => $new ) );
			$this->flash( 'success', $new ? __( 'Auto-Update aktiviert.', 'flygit' ) : __( 'Auto-Update deaktiviert.', 'flygit' ) );
		}

		$this->back();
	}

	/**
	 * Detach: stop managing, keep files.
	 */
	public function handle_detach() {
		$this->guard( 'flygit_detach' );

		$id   = isset( $_POST['installation_id'] ) ? sanitize_text_field( wp_unslash( $_POST['installation_id'] ) ) : '';
		$item = $this->registry->find( $id );

		if ( $item ) {
			$this->registry->remove( $id );
			FlyGit_Logger::log( 'info', sprintf( '"%s" aus FlyGit-Verwaltung entfernt (Dateien bleiben).', $item['slug'] ) );
			$this->flash( 'success', __( 'Aus der Verwaltung entfernt. Dateien bleiben erhalten.', 'flygit' ) );
		}

		$this->back();
	}

	/**
	 * Delete: remove files AND registry entry.
	 */
	public function handle_delete() {
		$this->guard( 'flygit_delete' );

		$id   = isset( $_POST['installation_id'] ) ? sanitize_text_field( wp_unslash( $_POST['installation_id'] ) ) : '';
		$item = $this->registry->find( $id );

		if ( ! $item ) {
			$this->flash( 'error', __( 'Installation nicht gefunden.', 'flygit' ) );
			$this->back();
		}

		$result = $this->installer->delete_files( $item );

		if ( is_wp_error( $result ) ) {
			$this->flash( 'error', $result->get_error_message() );
			$this->back();
		}

		$this->registry->remove( $id );
		FlyGit_Logger::log( 'info', sprintf( '"%s" gelöscht.', $item['slug'] ) );
		$this->flash( 'success', sprintf( __( '"%s" wurde gelöscht.', 'flygit' ), $item['slug'] ) );
		$this->back();
	}

	/**
	 * Save global settings.
	 */
	public function handle_save_settings() {
		$this->guard( 'flygit_save_settings' );

		$interval = isset( $_POST['check_interval'] ) ? sanitize_key( wp_unslash( $_POST['check_interval'] ) ) : 'twicedaily';
		if ( ! in_array( $interval, array( 'flygit_15min', 'hourly', 'twicedaily', 'daily' ), true ) ) {
			$interval = 'twicedaily';
		}

		$old_interval = FlyGit_Options::get( 'check_interval' );

		FlyGit_Options::update(
			array(
				'check_interval'   => $interval,
				'auto_update'      => ! empty( $_POST['auto_update'] ),
				'webhook_enabled'  => ! empty( $_POST['webhook_enabled'] ),
				'keep_log_entries' => isset( $_POST['keep_log_entries'] ) ? max( 10, min( 500, (int) $_POST['keep_log_entries'] ) ) : 100,
			)
		);

		// Token only when the field was actually filled (avoid clearing on masked value).
		if ( isset( $_POST['github_token'] ) ) {
			$token = trim( (string) wp_unslash( $_POST['github_token'] ) );
			if ( '' !== $token && '••••••••' !== $token ) {
				FlyGit_Options::set_github_token( $token );
			} elseif ( ! empty( $_POST['github_token_clear'] ) ) {
				FlyGit_Options::set_github_token( '' );
			}
		}

		if ( $old_interval !== $interval ) {
			$this->updater->reschedule();
		}

		$this->flash( 'success', __( 'Einstellungen gespeichert.', 'flygit' ) );
		$this->back( 'settings' );
	}

	/**
	 * Save manifest settings.
	 */
	public function handle_save_manifest() {
		$this->guard( 'flygit_save_manifest' );

		$repo = isset( $_POST['manifest_repo'] ) ? sanitize_text_field( wp_unslash( $_POST['manifest_repo'] ) ) : '';

		if ( '' !== $repo ) {
			$parsed = FlyGit_GitHub::parse_repo( $repo );
			if ( is_wp_error( $parsed ) ) {
				$this->flash( 'error', $parsed->get_error_message() );
				$this->back( 'manifest' );
			}
			$repo = $parsed['owner'] . '/' . $parsed['repo'];
		}

		FlyGit_Options::update(
			array(
				'manifest_enabled'   => ! empty( $_POST['manifest_enabled'] ),
				'manifest_repo'      => $repo,
				'manifest_branch'    => isset( $_POST['manifest_branch'] ) && '' !== trim( (string) wp_unslash( $_POST['manifest_branch'] ) ) ? sanitize_text_field( wp_unslash( $_POST['manifest_branch'] ) ) : 'main',
				'manifest_path'      => isset( $_POST['manifest_path'] ) && '' !== trim( (string) wp_unslash( $_POST['manifest_path'] ) ) ? sanitize_text_field( wp_unslash( $_POST['manifest_path'] ) ) : 'fleet-manifest.json',
				'manifest_autoapply' => ! empty( $_POST['manifest_autoapply'] ),
			)
		);

		// Reset ETag so the next sync re-reads the file.
		update_option( 'flygit_manifest_etag', '', false );

		$this->flash( 'success', __( 'Manifest-Einstellungen gespeichert.', 'flygit' ) );
		$this->back( 'manifest' );
	}

	/**
	 * Trigger a manifest sync now.
	 */
	public function handle_manifest_sync() {
		$this->guard( 'flygit_manifest_sync' );

		update_option( 'flygit_manifest_etag', '', false );

		$result = $this->manifest->sync( (bool) FlyGit_Options::get( 'manifest_autoapply', true ) );

		if ( is_wp_error( $result ) ) {
			$this->flash( 'error', $result->get_error_message() );
		} else {
			$this->flash( 'success', __( 'Manifest synchronisiert: ', 'flygit' ) . $result['summary'] );
		}

		$this->back( 'manifest' );
	}

	/**
	 * Clear the activity log.
	 */
	public function handle_clear_log() {
		$this->guard( 'flygit_clear_log' );

		FlyGit_Logger::clear();
		$this->flash( 'success', __( 'Log geleert.', 'flygit' ) );
		$this->back( 'log' );
	}

	/**
	 * Regenerate the webhook secret.
	 */
	public function handle_regenerate_secret() {
		$this->guard( 'flygit_regenerate_secret' );

		FlyGit_Options::update( array( 'webhook_secret' => wp_generate_password( 40, false, false ) ) );
		FlyGit_Logger::log( 'info', 'Webhook-Secret neu generiert.' );
		$this->flash( 'success', __( 'Neues Webhook-Secret generiert. Bestehende Webhooks müssen aktualisiert werden.', 'flygit' ) );
		$this->back( 'settings' );
	}
}

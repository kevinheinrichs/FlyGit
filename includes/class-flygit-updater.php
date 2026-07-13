<?php
/**
 * Scheduled update checks + native WP update UI integration.
 *
 * @package FlyGit
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The heart of the pull model:
 *
 * - WP-cron fires `flygit_check_updates` on the configured interval.
 * - Each installation asks GitHub for the branch head — with ETag, so
 *   unchanged repos answer 304 (no body, no rate-limit cost).
 * - When a new commit is found: auto_update ? deploy : expose in the
 *   native WordPress update UI (Dashboard → Updates), where it can be
 *   applied like any wordpress.org update.
 */
class FlyGit_Updater {

	const CRON_INTERVAL_KEY = 'flygit_interval';

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
	 * Manifest.
	 *
	 * @var FlyGit_Manifest
	 */
	protected $manifest;

	/**
	 * Constructor.
	 *
	 * @param FlyGit_Registry  $registry  Registry.
	 * @param FlyGit_Installer $installer Installer.
	 * @param FlyGit_Manifest  $manifest  Manifest service.
	 */
	public function __construct( FlyGit_Registry $registry, FlyGit_Installer $installer, FlyGit_Manifest $manifest ) {
		$this->registry  = $registry;
		$this->installer = $installer;
		$this->manifest  = $manifest;
	}

	/**
	 * Register the configurable cron interval.
	 *
	 * @param array $schedules Cron schedules.
	 * @return array
	 */
	public function register_cron_interval( $schedules ) {
		$schedules[ self::CRON_INTERVAL_KEY ] = array(
			'interval' => $this->interval_seconds(),
			'display'  => __( 'FlyGit Prüf-Intervall', 'flygit' ),
		);

		return $schedules;
	}

	/**
	 * Interval in seconds based on settings.
	 *
	 * @return int
	 */
	public function interval_seconds() {
		switch ( FlyGit_Options::get( 'check_interval', 'twicedaily' ) ) {
			case 'flygit_15min':
				return 15 * MINUTE_IN_SECONDS;
			case 'hourly':
				return HOUR_IN_SECONDS;
			case 'daily':
				return DAY_IN_SECONDS;
			case 'twicedaily':
			default:
				return 12 * HOUR_IN_SECONDS;
		}
	}

	/**
	 * Re-schedule the recurring event (after interval change).
	 */
	public function reschedule() {
		wp_clear_scheduled_hook( 'flygit_check_updates' );
		wp_schedule_event( time() + 60, self::CRON_INTERVAL_KEY, 'flygit_check_updates' );
	}

	/**
	 * Cron: check everything.
	 *
	 * Staggered: only ONE GitHub request per installation, manifest first.
	 */
	public function run_scheduled_checks() {
		if ( FlyGit_Options::get( 'manifest_enabled', false ) ) {
			$this->manifest->sync( (bool) FlyGit_Options::get( 'manifest_autoapply', true ) );
		}

		foreach ( $this->registry->all() as $item ) {
			$this->check_installation( $item, true );
		}
	}

	/**
	 * Cron: single delayed check (used by webhook debounce).
	 *
	 * @param string $installation_id Installation id.
	 * @param bool   $apply           Deploy when update found.
	 */
	public function run_single_check( $installation_id, $apply = true ) {
		$item = $this->registry->find( $installation_id );
		if ( $item ) {
			$this->check_installation( $item, (bool) $apply );
		}
	}

	/**
	 * Cron: manifest sync entry point (webhook debounce).
	 */
	public function run_manifest_sync() {
		$this->manifest->sync( (bool) FlyGit_Options::get( 'manifest_autoapply', true ) );
	}

	/**
	 * Check one installation against GitHub; optionally deploy.
	 *
	 * @param array $item        Registry item.
	 * @param bool  $allow_apply Allow auto-deploy when enabled.
	 * @return true|WP_Error
	 */
	public function check_installation( array $item, $allow_apply = false ) {
		$token  = $this->registry->effective_token( $item );
		$result = FlyGit_GitHub::latest_commit( $item['owner'], $item['repo'], $item['branch'], $token, $item['etag'] );

		if ( is_wp_error( $result ) ) {
			$this->registry->patch(
				$item['id'],
				array(
					'last_checked' => time(),
					'last_error'   => $result->get_error_message(),
				)
			);

			FlyGit_Logger::log(
				'error',
				sprintf( 'Check fehlgeschlagen für %s: %s', $item['slug'], $result->get_error_message() ),
				array( 'slug' => $item['slug'] )
			);

			return $result;
		}

		$patch = array(
			'last_checked' => time(),
			'last_error'   => '',
			'etag'         => $result['etag'],
		);

		if ( $result['modified'] && '' !== $result['sha'] ) {
			$patch['remote_sha']     = $result['sha'];
			$patch['remote_date']    = $result['date'];
			$patch['remote_message'] = $result['message'];
		}

		$this->registry->patch( $item['id'], $patch );

		// Merge fresh state.
		$item = array_merge( $item, $patch );

		$needs_update = ! empty( $item['remote_sha'] ) && $item['remote_sha'] !== $item['installed_sha'];

		if ( $needs_update && $allow_apply && ! empty( $item['auto_update'] ) ) {
			return $this->apply_update( $item );
		}

		return true;
	}

	/**
	 * Deploy the latest known remote commit for an installation.
	 *
	 * @param array $item Registry item.
	 * @return true|WP_Error
	 */
	public function apply_update( array $item ) {
		$token = $this->registry->effective_token( $item );
		$sha   = ! empty( $item['remote_sha'] ) ? $item['remote_sha'] : $item['branch'];

		$was_active = $this->is_active( $item );

		$result = $this->installer->deploy(
			array(
				'type'   => $item['type'],
				'owner'  => $item['owner'],
				'repo'   => $item['repo'],
				'branch' => $item['branch'],
				'ref'    => $sha,
				'token'  => $token,
				'slug'   => $item['slug'],
			)
		);

		if ( is_wp_error( $result ) ) {
			$this->registry->patch(
				$item['id'],
				array( 'last_error' => $result->get_error_message() )
			);

			FlyGit_Logger::log(
				'error',
				sprintf( 'Update fehlgeschlagen für %s: %s', $item['slug'], $result->get_error_message() ),
				array( 'slug' => $item['slug'] )
			);

			return $result;
		}

		// Re-activate plugin if it was active before the swap.
		if ( $was_active && 'plugin' === $item['type'] ) {
			$file = $this->installer->plugin_file( $item['slug'] );
			if ( '' !== $file && ! is_plugin_active( $file ) ) {
				activate_plugin( $file );
			}
		}

		$this->registry->patch(
			$item['id'],
			array(
				'installed_sha' => $item['remote_sha'],
				'last_updated'  => time(),
				'last_error'    => '',
			)
		);

		FlyGit_Logger::log(
			'success',
			sprintf(
				'%s "%s" aktualisiert auf %s (%s)',
				'plugin' === $item['type'] ? 'Plugin' : 'Theme',
				$item['slug'],
				substr( $item['remote_sha'], 0, 7 ),
				$item['remote_message']
			),
			array( 'slug' => $item['slug'] )
		);

		return true;
	}

	/**
	 * Whether an installation is currently active.
	 *
	 * @param array $item Registry item.
	 * @return bool
	 */
	protected function is_active( array $item ) {
		if ( 'theme' === $item['type'] ) {
			$theme = wp_get_theme();
			return $theme && $theme->get_stylesheet() === $item['slug'];
		}

		if ( ! function_exists( 'is_plugin_active' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$file = $this->installer->plugin_file( $item['slug'] );

		return '' !== $file && is_plugin_active( $file );
	}

	/**
	 * Expose pending plugin updates in the native update UI.
	 *
	 * @param object $transient update_plugins transient.
	 * @return object
	 */
	public function inject_plugin_updates( $transient ) {
		if ( ! is_object( $transient ) ) {
			return $transient;
		}

		foreach ( $this->registry->pending_updates() as $item ) {
			if ( 'plugin' !== $item['type'] ) {
				continue;
			}

			$file = $this->installer->plugin_file( $item['slug'] );
			if ( '' === $file ) {
				continue;
			}

			$header = $this->installer->installed_header( $item );

			$update = (object) array(
				'id'          => 'flygit/' . $item['id'],
				'slug'        => $item['slug'],
				'plugin'      => $file,
				'new_version' => $this->pretty_version( $header['version'], $item['remote_sha'] ),
				'url'         => sprintf( 'https://github.com/%s/%s', $item['owner'], $item['repo'] ),
				'package'     => 'flygit://' . $item['id'],
			);

			if ( ! isset( $transient->response ) || ! is_array( $transient->response ) ) {
				$transient->response = array();
			}

			$transient->response[ $file ] = $update;
		}

		return $transient;
	}

	/**
	 * Expose pending theme updates in the native update UI.
	 *
	 * @param object $transient update_themes transient.
	 * @return object
	 */
	public function inject_theme_updates( $transient ) {
		if ( ! is_object( $transient ) ) {
			return $transient;
		}

		foreach ( $this->registry->pending_updates() as $item ) {
			if ( 'theme' !== $item['type'] ) {
				continue;
			}

			$header = $this->installer->installed_header( $item );

			if ( ! isset( $transient->response ) || ! is_array( $transient->response ) ) {
				$transient->response = array();
			}

			$transient->response[ $item['slug'] ] = array(
				'theme'       => $item['slug'],
				'new_version' => $this->pretty_version( $header['version'], $item['remote_sha'] ),
				'url'         => sprintf( 'https://github.com/%s/%s', $item['owner'], $item['repo'] ),
				'package'     => 'flygit://' . $item['id'],
			);
		}

		return $transient;
	}

	/**
	 * Intercept our pseudo-package URLs when WP runs an upgrade.
	 *
	 * Instead of letting WP download `flygit://...`, we run our own
	 * atomic deploy and short-circuit the upgrader.
	 *
	 * @param mixed       $reply    Download reply.
	 * @param string      $package  Package URL.
	 * @param WP_Upgrader $upgrader Upgrader instance.
	 * @return mixed
	 */
	public function maybe_provide_package( $reply, $package, $upgrader ) {
		if ( ! is_string( $package ) || 0 !== strpos( $package, 'flygit://' ) ) {
			return $reply;
		}

		$id   = substr( $package, strlen( 'flygit://' ) );
		$item = $this->registry->find( $id );

		if ( ! $item ) {
			return new WP_Error( 'flygit_unknown_package', __( 'FlyGit-Installation nicht gefunden.', 'flygit' ) );
		}

		$result = $this->apply_update( $item );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		// Deploy done — hand WP a tiny no-op zip so the upgrader completes gracefully.
		return $this->noop_zip( $item['slug'] );
	}

	/**
	 * After a native upgrade ran, refresh caches for affected items.
	 *
	 * @param WP_Upgrader $upgrader Upgrader.
	 * @param array       $extra    Extra info.
	 */
	public function after_native_upgrade( $upgrader, $extra ) {
		unset( $upgrader, $extra );
		delete_site_transient( 'update_plugins' );
		delete_site_transient( 'update_themes' );
	}

	/**
	 * Build a version label like "1.2.0 (a1b2c3d)".
	 *
	 * @param string $version Installed header version.
	 * @param string $sha     Remote sha.
	 * @return string
	 */
	protected function pretty_version( $version, $sha ) {
		$short = substr( (string) $sha, 0, 7 );
		return '' !== $version ? $version . '+' . $short : $short;
	}

	/**
	 * Create a minimal valid zip that extracts to the existing (already
	 * deployed) directory — keeps WP_Upgrader happy after our swap.
	 *
	 * @param string $slug Directory slug.
	 * @return string|WP_Error Path to zip.
	 */
	protected function noop_zip( $slug ) {
		if ( ! class_exists( 'ZipArchive' ) ) {
			// Without ZipArchive we cannot fake a package; abort the native
			// flow with a success-ish error that explains itself.
			return new WP_Error(
				'flygit_deploy_done',
				__( 'FlyGit hat das Update bereits atomar installiert. Diese Meldung kann ignoriert werden.', 'flygit' )
			);
		}

		$file = wp_tempnam( 'flygit-noop' );
		$zip  = new ZipArchive();
		$zip->open( $file, ZipArchive::OVERWRITE );
		$zip->addFromString( $slug . '/.flygit-deployed', (string) time() );
		$zip->close();

		return $file;
	}
}

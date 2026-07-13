<?php
/**
 * Fleet manifest: one central JSON file defines the desired state
 * for every shop in the fleet.
 *
 * @package FlyGit
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Manifest format (fleet-manifest.json in its own repo):
 *
 * {
 *   "version": 1,
 *   "plugins": [
 *     { "repo": "kevinheinrichs/fly-geo",    "branch": "main" },
 *     { "repo": "kevinheinrichs/fly-cache",  "branch": "main", "slug": "flycache" }
 *   ],
 *   "themes": [
 *     { "repo": "kevinheinrichs/fly-theme",  "branch": "main" }
 *   ],
 *   "sites": {
 *     "beauty-bazaar.de": {
 *       "exclude": [ "kevinheinrichs/fly-geo" ],
 *       "plugins": [ { "repo": "kevinheinrichs/bb-only-plugin", "branch": "main" } ]
 *     }
 *   }
 * }
 *
 * Top-level plugins/themes apply to ALL sites. The optional `sites`
 * message adds per-host additions (`plugins`/`themes`) or removals
 * (`exclude` by repo). Hosts are matched against this site's host name.
 *
 * The manifest file itself is fetched with an ETag conditional request,
 * so polling is free until the file actually changes.
 */
class FlyGit_Manifest {

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
	 * Constructor.
	 *
	 * @param FlyGit_Registry  $registry  Registry.
	 * @param FlyGit_Installer $installer Installer.
	 */
	public function __construct( FlyGit_Registry $registry, FlyGit_Installer $installer ) {
		$this->registry  = $registry;
		$this->installer = $installer;
	}

	/**
	 * Sync desired state from the central manifest.
	 *
	 * @param bool $apply Deploy changes immediately (otherwise only register).
	 * @return array|WP_Error Summary array.
	 */
	public function sync( $apply = true ) {
		if ( ! FlyGit_Options::get( 'manifest_enabled', false ) ) {
			return new WP_Error( 'flygit_manifest_disabled', __( 'Manifest-Modus ist deaktiviert.', 'flygit' ) );
		}

		$repo_ref = FlyGit_Options::get( 'manifest_repo', '' );
		$parsed   = FlyGit_GitHub::parse_repo( $repo_ref );

		if ( is_wp_error( $parsed ) ) {
			return $parsed;
		}

		$branch = FlyGit_Options::get( 'manifest_branch', 'main' );
		$path   = FlyGit_Options::get( 'manifest_path', 'fleet-manifest.json' );
		$token  = FlyGit_Options::github_token();
		$etag   = get_option( 'flygit_manifest_etag', '' );

		$result = FlyGit_GitHub::file_contents( $parsed['owner'], $parsed['repo'], $path, $branch, $token, $etag );

		if ( is_wp_error( $result ) ) {
			FlyGit_Logger::log( 'error', 'Manifest-Abruf fehlgeschlagen: ' . $result->get_error_message() );
			return $result;
		}

		if ( ! $result['modified'] ) {
			// Nothing changed since last sync — free exit.
			return array(
				'changed' => false,
				'summary' => __( 'Manifest unverändert (ETag-Treffer).', 'flygit' ),
			);
		}

		update_option( 'flygit_manifest_etag', $result['etag'], false );

		$manifest = json_decode( $result['content'], true );
		if ( ! is_array( $manifest ) ) {
			FlyGit_Logger::log( 'error', 'Manifest ist kein gültiges JSON.' );
			return new WP_Error( 'flygit_manifest_json', __( 'Manifest ist kein gültiges JSON.', 'flygit' ) );
		}

		$desired = $this->resolve_desired_state( $manifest );

		$added   = 0;
		$removed = 0;

		// Upsert desired installations.
		foreach ( $desired as $entry ) {
			$existing = $this->registry->find_by_slug( $entry['type'], $entry['slug'] );

			if ( ! $existing ) {
				$this->registry->upsert(
					array(
						'type'        => $entry['type'],
						'slug'        => $entry['slug'],
						'owner'       => $entry['owner'],
						'repo'        => $entry['repo'],
						'branch'      => $entry['branch'],
						'managed_by'  => 'manifest',
						'auto_update' => true,
					)
				);
				$added++;
			} elseif ( 'manifest' === $existing['managed_by'] ) {
				// Keep branch in sync with manifest.
				if ( $existing['branch'] !== $entry['branch'] || $existing['owner'] !== $entry['owner'] || $existing['repo'] !== $entry['repo'] ) {
					$this->registry->patch(
						$existing['id'],
						array(
							'owner'  => $entry['owner'],
							'repo'   => $entry['repo'],
							'branch' => $entry['branch'],
							'etag'   => '',
						)
					);
				}
			}
		}

		// Remove manifest-managed items that vanished from the manifest.
		$desired_keys = array();
		foreach ( $desired as $entry ) {
			$desired_keys[ $entry['type'] . ':' . $entry['slug'] ] = true;
		}

		foreach ( $this->registry->all() as $item ) {
			if ( 'manifest' !== $item['managed_by'] ) {
				continue;
			}

			if ( ! isset( $desired_keys[ $item['type'] . ':' . $item['slug'] ] ) ) {
				$delete = $this->installer->delete_files( $item );

				if ( is_wp_error( $delete ) ) {
					FlyGit_Logger::log( 'warning', sprintf( 'Manifest-Entfernung übersprungen (%s): %s', $item['slug'], $delete->get_error_message() ) );
					continue;
				}

				$this->registry->remove( $item['id'] );
				$removed++;

				FlyGit_Logger::log( 'info', sprintf( '"%s" entfernt (nicht mehr im Manifest).', $item['slug'] ) );
			}
		}

		FlyGit_Logger::log(
			'success',
			sprintf( 'Manifest synchronisiert: %d neu, %d entfernt, %d verwaltet.', $added, $removed, count( $desired ) )
		);

		// Deploy anything that is registered but not yet installed.
		if ( $apply ) {
			$updater = flygit_service( 'updater' );

			foreach ( $this->registry->all() as $item ) {
				if ( 'manifest' !== $item['managed_by'] ) {
					continue;
				}

				$header = $this->installer->installed_header( $item );

				if ( '' === $item['installed_sha'] && '' === $header['version'] ) {
					// Not on disk yet → first install.
					$updater->check_installation( $item, true );
				}
			}
		}

		return array(
			'changed' => true,
			'summary' => sprintf( __( '%1$d neu registriert, %2$d entfernt.', 'flygit' ), $added, $removed ),
		);
	}

	/**
	 * Flatten the manifest into this site's desired installations.
	 *
	 * @param array $manifest Parsed manifest.
	 * @return array[] Each: { type, slug, owner, repo, branch }.
	 */
	protected function resolve_desired_state( array $manifest ) {
		$host    = wp_parse_url( home_url(), PHP_URL_HOST );
		$desired = array();
		$exclude = array();

		$site_config = array();
		if ( isset( $manifest['sites'] ) && is_array( $manifest['sites'] ) ) {
			foreach ( $manifest['sites'] as $site_host => $config ) {
				if ( strtolower( trim( $site_host ) ) === strtolower( (string) $host ) && is_array( $config ) ) {
					$site_config = $config;
					break;
				}
			}
		}

		if ( isset( $site_config['exclude'] ) && is_array( $site_config['exclude'] ) ) {
			foreach ( $site_config['exclude'] as $repo_ref ) {
				$exclude[ strtolower( trim( (string) $repo_ref ) ) ] = true;
			}
		}

		$collect = function ( $entries, $type ) use ( &$desired, $exclude ) {
			if ( ! is_array( $entries ) ) {
				return;
			}

			foreach ( $entries as $entry ) {
				if ( ! is_array( $entry ) || empty( $entry['repo'] ) ) {
					continue;
				}

				if ( isset( $exclude[ strtolower( trim( $entry['repo'] ) ) ] ) ) {
					continue;
				}

				$parsed = FlyGit_GitHub::parse_repo( $entry['repo'] );
				if ( is_wp_error( $parsed ) ) {
					FlyGit_Logger::log( 'warning', 'Manifest-Eintrag übersprungen: ' . $entry['repo'] );
					continue;
				}

				$slug = ! empty( $entry['slug'] ) ? sanitize_key( $entry['slug'] ) : sanitize_key( $parsed['repo'] );

				$desired[ $type . ':' . $slug ] = array(
					'type'   => $type,
					'slug'   => $slug,
					'owner'  => $parsed['owner'],
					'repo'   => $parsed['repo'],
					'branch' => ! empty( $entry['branch'] ) ? sanitize_text_field( $entry['branch'] ) : 'main',
				);
			}
		};

		// Global lists.
		$collect( isset( $manifest['plugins'] ) ? $manifest['plugins'] : array(), 'plugin' );
		$collect( isset( $manifest['themes'] ) ? $manifest['themes'] : array(), 'theme' );

		// Site-specific additions.
		$collect( isset( $site_config['plugins'] ) ? $site_config['plugins'] : array(), 'plugin' );
		$collect( isset( $site_config['themes'] ) ? $site_config['themes'] : array(), 'theme' );

		return array_values( $desired );
	}
}

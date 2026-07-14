<?php
/**
 * Atomic installer: download → stage → verify → swap → rollback.
 *
 * @package FlyGit
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Deploys a repository into wp-content/plugins or /themes.
 *
 * Deployment is ATOMIC: the new version is extracted and verified in a
 * staging directory first. Only then is the live directory renamed away
 * and the staged one renamed in (two rename() calls — near-instant).
 * If anything fails, the previous version is restored. A broken download
 * can never take down a live site.
 */
class FlyGit_Installer {

	/**
	 * Registry.
	 *
	 * @var FlyGit_Registry
	 */
	protected $registry;

	/**
	 * Constructor.
	 *
	 * @param FlyGit_Registry $registry Registry instance.
	 */
	public function __construct( FlyGit_Registry $registry ) {
		$this->registry = $registry;
	}

	/**
	 * Install or update an installation from GitHub.
	 *
	 * @param array $args {
	 *     @type string $type    plugin|theme.
	 *     @type string $owner   GitHub owner.
	 *     @type string $repo    GitHub repo.
	 *     @type string $branch  Branch.
	 *     @type string $ref     Optional explicit ref (sha/tag); default branch head.
	 *     @type string $token   Plain token for this operation.
	 *     @type string $slug    Optional slug override; default repo name.
	 * }
	 * @return array|WP_Error { slug, sha, name, version } on success.
	 */
	public function deploy( array $args ) {
		$type   = ( isset( $args['type'] ) && 'theme' === $args['type'] ) ? 'theme' : 'plugin';
		$owner  = isset( $args['owner'] ) ? $args['owner'] : '';
		$repo   = isset( $args['repo'] ) ? $args['repo'] : '';
		$branch = ! empty( $args['branch'] ) ? $args['branch'] : 'main';
		$ref    = ! empty( $args['ref'] ) ? $args['ref'] : $branch;
		$token  = isset( $args['token'] ) ? $args['token'] : '';
		$slug   = ! empty( $args['slug'] ) ? sanitize_key( $args['slug'] ) : sanitize_key( $repo );

		if ( '' === $owner || '' === $repo || '' === $slug ) {
			return new WP_Error( 'flygit_deploy_args', __( 'Unvollständige Deployment-Parameter.', 'flygit' ) );
		}

		// 1) Download zipball (streamed to disk).
		$zip_file = FlyGit_GitHub::download_zipball( $owner, $repo, $ref, $token );
		if ( is_wp_error( $zip_file ) ) {
			return $zip_file;
		}

		// 2) Extract into private staging area.
		$staging = $this->staging_dir( $slug );
		if ( is_wp_error( $staging ) ) {
			@unlink( $zip_file ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			return $staging;
		}

		$extract = $this->extract( $zip_file, $staging );
		@unlink( $zip_file ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged

		if ( is_wp_error( $extract ) ) {
			$this->rrmdir( $staging );
			return $extract;
		}

		// GitHub zipballs wrap contents in {owner}-{repo}-{sha}/.
		$source = $this->inner_dir( $staging );
		if ( null === $source ) {
			$this->rrmdir( $staging );
			return new WP_Error( 'flygit_empty_package', __( 'Das Archiv enthält keine installierbaren Dateien.', 'flygit' ) );
		}

		// 3) Verify the package BEFORE touching the live directory.
		$header = $this->verify_package( $type, $source );
		if ( is_wp_error( $header ) ) {
			$this->rrmdir( $staging );
			return $header;
		}

		// 4) Atomic swap with rollback.
		$root   = ( 'plugin' === $type ) ? WP_PLUGIN_DIR : get_theme_root();
		$target = trailingslashit( $root ) . $slug;
		$backup = trailingslashit( dirname( $staging ) ) . $slug . '-backup-' . time();

		$had_previous = is_dir( $target );

		if ( $had_previous && ! @rename( $target, $backup ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			$this->rrmdir( $staging );
			return new WP_Error( 'flygit_swap_failed', __( 'Bestehendes Verzeichnis konnte nicht ausgelagert werden (Dateirechte prüfen).', 'flygit' ) );
		}

		$moved = @rename( $source, $target ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged

		if ( ! $moved ) {
			// rename() across filesystems can fail — fall back to copy.
			if ( ! function_exists( 'copy_dir' ) ) {
				require_once ABSPATH . 'wp-admin/includes/file.php';
			}

			global $wp_filesystem;
			if ( ! $wp_filesystem ) {
				WP_Filesystem();
			}

			wp_mkdir_p( $target );
			$copy  = $wp_filesystem ? copy_dir( $source, $target ) : new WP_Error( 'flygit_fs', 'no filesystem' );
			$moved = ! is_wp_error( $copy );
		}

		if ( ! $moved ) {
			// ROLLBACK.
			if ( $had_previous ) {
				@rename( $backup, $target ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			}
			$this->rrmdir( $staging );

			return new WP_Error( 'flygit_swap_failed', __( 'Neues Verzeichnis konnte nicht platziert werden — vorherige Version wurde wiederhergestellt.', 'flygit' ) );
		}

		// 5) Success: clean up backup + staging leftovers.
		if ( $had_previous ) {
			$this->rrmdir( $backup );
		}
		$this->rrmdir( $staging );

		if ( 'plugin' === $type ) {
			wp_clean_plugins_cache( true );
		} else {
			wp_clean_themes_cache( true );
		}

		/**
		 * Fires after a successful deployment (files are swapped in place).
		 *
		 * Listeners can reset OPcache, purge page caches, rewarm URLs etc.
		 * FlyCache hooks this to automate the OPcache-reset + purge + rewarm
		 * dance that otherwise has to happen manually after every deploy.
		 *
		 * @param array $deploy_info { type, slug, name, version, owner, repo, branch }
		 */
		do_action(
			'flygit_after_deploy',
			array(
				'type'    => $type,
				'slug'    => $slug,
				'name'    => $header['name'],
				'version' => $header['version'],
				'owner'   => $owner,
				'repo'    => $repo,
				'branch'  => $branch,
			)
		);

		return array(
			'slug'    => $slug,
			'name'    => $header['name'],
			'version' => $header['version'],
		);
	}

	/**
	 * Remove a managed installation from disk.
	 *
	 * @param array $item Registry item.
	 * @return true|WP_Error
	 */
	public function delete_files( array $item ) {
		$type = $item['type'];
		$slug = $item['slug'];

		if ( 'theme' === $type ) {
			$theme = wp_get_theme();
			if ( $theme && $theme->get_stylesheet() === $slug ) {
				return new WP_Error( 'flygit_active_theme', __( 'Das aktive Theme kann nicht gelöscht werden.', 'flygit' ) );
			}
			$path = trailingslashit( get_theme_root() ) . $slug;
		} else {
			$file = $this->plugin_file( $slug );
			if ( $file && is_plugin_active( $file ) ) {
				deactivate_plugins( array( $file ) );
			}
			$path = trailingslashit( WP_PLUGIN_DIR ) . $slug;
		}

		if ( is_dir( $path ) ) {
			$this->rrmdir( $path );

			if ( is_dir( $path ) ) {
				return new WP_Error( 'flygit_delete_failed', __( 'Verzeichnis konnte nicht gelöscht werden.', 'flygit' ) );
			}
		}

		if ( 'plugin' === $type ) {
			wp_clean_plugins_cache( true );
		} else {
			wp_clean_themes_cache( true );
		}

		return true;
	}

	/**
	 * Resolve the main plugin file for a slug.
	 *
	 * @param string $slug Plugin directory slug.
	 * @return string Plugin basename or ''.
	 */
	public function plugin_file( $slug ) {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		foreach ( get_plugins() as $file => $data ) {
			if ( 0 === strpos( $file, trailingslashit( $slug ) ) ) {
				return $file;
			}
		}

		return '';
	}

	/**
	 * Read installed version/name for an item from disk.
	 *
	 * @param array $item Registry item.
	 * @return array { name, version }
	 */
	public function installed_header( array $item ) {
		if ( 'theme' === $item['type'] ) {
			$theme = wp_get_theme( $item['slug'] );
			if ( $theme->exists() ) {
				return array(
					'name'    => $theme->get( 'Name' ),
					'version' => $theme->get( 'Version' ),
				);
			}
		} else {
			$file = $this->plugin_file( $item['slug'] );
			if ( '' !== $file && file_exists( trailingslashit( WP_PLUGIN_DIR ) . $file ) ) {
				$data = get_plugin_data( trailingslashit( WP_PLUGIN_DIR ) . $file, false, false );
				return array(
					'name'    => $data['Name'],
					'version' => $data['Version'],
				);
			}
		}

		return array(
			'name'    => $item['slug'],
			'version' => '',
		);
	}

	/**
	 * Validate that a staged package is a real plugin/theme.
	 *
	 * @param string $type   plugin|theme.
	 * @param string $source Staged source directory.
	 * @return array|WP_Error { name, version }
	 */
	protected function verify_package( $type, $source ) {
		if ( 'theme' === $type ) {
			$style = trailingslashit( $source ) . 'style.css';
			if ( ! file_exists( $style ) ) {
				return new WP_Error( 'flygit_invalid_theme', __( 'Paket abgelehnt: style.css fehlt im Repository-Root.', 'flygit' ) );
			}

			$data = get_file_data(
				$style,
				array(
					'name'    => 'Theme Name',
					'version' => 'Version',
				)
			);

			if ( empty( $data['name'] ) ) {
				return new WP_Error( 'flygit_invalid_theme', __( 'Paket abgelehnt: style.css enthält keinen "Theme Name"-Header.', 'flygit' ) );
			}

			return array(
				'name'    => $data['name'],
				'version' => $data['version'],
			);
		}

		// Plugin: find any top-level .php with a Plugin Name header.
		$files = glob( trailingslashit( $source ) . '*.php' );
		if ( is_array( $files ) ) {
			foreach ( $files as $file ) {
				$data = get_file_data(
					$file,
					array(
						'name'    => 'Plugin Name',
						'version' => 'Version',
					)
				);

				if ( ! empty( $data['name'] ) ) {
					return array(
						'name'    => $data['name'],
						'version' => $data['version'],
					);
				}
			}
		}

		return new WP_Error( 'flygit_invalid_plugin', __( 'Paket abgelehnt: keine PHP-Datei mit "Plugin Name"-Header im Repository-Root gefunden.', 'flygit' ) );
	}

	/**
	 * Create a fresh staging directory inside uploads (private, .htaccess-guarded).
	 *
	 * @param string $slug Slug for readability.
	 * @return string|WP_Error
	 */
	protected function staging_dir( $slug ) {
		$uploads = wp_upload_dir( null, false );
		$base    = trailingslashit( $uploads['basedir'] ) . 'flygit-staging';

		if ( ! wp_mkdir_p( $base ) ) {
			return new WP_Error( 'flygit_staging_failed', __( 'Staging-Verzeichnis konnte nicht angelegt werden.', 'flygit' ) );
		}

		// Deny direct web access.
		if ( ! file_exists( $base . '/.htaccess' ) ) {
			@file_put_contents( $base . '/.htaccess', "Deny from all\n" ); // phpcs:ignore
		}
		if ( ! file_exists( $base . '/index.php' ) ) {
			@file_put_contents( $base . '/index.php', "<?php // Silence.\n" ); // phpcs:ignore
		}

		$dir = trailingslashit( $base ) . $slug . '-' . wp_generate_password( 8, false, false );

		if ( ! wp_mkdir_p( $dir ) ) {
			return new WP_Error( 'flygit_staging_failed', __( 'Staging-Verzeichnis konnte nicht angelegt werden.', 'flygit' ) );
		}

		return $dir;
	}

	/**
	 * Extract a zip archive.
	 *
	 * @param string $zip_file Zip path.
	 * @param string $dest     Destination directory.
	 * @return true|WP_Error
	 */
	protected function extract( $zip_file, $dest ) {
		if ( class_exists( 'ZipArchive' ) ) {
			$zip = new ZipArchive();
			if ( true === $zip->open( $zip_file ) ) {
				$ok = $zip->extractTo( $dest );
				$zip->close();

				if ( $ok ) {
					return true;
				}
			}
		}

		if ( ! function_exists( 'unzip_file' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}

		global $wp_filesystem;
		if ( ! $wp_filesystem ) {
			WP_Filesystem();
		}

		$result = unzip_file( $zip_file, $dest );

		return is_wp_error( $result )
			? new WP_Error( 'flygit_unzip_failed', $result->get_error_message() )
			: true;
	}

	/**
	 * Find the single wrapper directory of a GitHub zipball.
	 *
	 * @param string $dir Extraction directory.
	 * @return string|null
	 */
	protected function inner_dir( $dir ) {
		$entries = glob( trailingslashit( $dir ) . '*' );
		if ( empty( $entries ) ) {
			return null;
		}

		foreach ( $entries as $entry ) {
			if ( is_dir( $entry ) ) {
				return $entry;
			}
		}

		return $dir;
	}

	/**
	 * Recursively delete a directory (native, fast, no WP_Filesystem needed).
	 *
	 * @param string $dir Directory path.
	 */
	protected function rrmdir( $dir ) {
		if ( ! is_dir( $dir ) ) {
			return;
		}

		$iterator = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator( $dir, FilesystemIterator::SKIP_DOTS ),
			RecursiveIteratorIterator::CHILD_FIRST
		);

		foreach ( $iterator as $file ) {
			if ( $file->isDir() && ! $file->isLink() ) {
				@rmdir( $file->getPathname() ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			} else {
				@unlink( $file->getPathname() ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			}
		}

		@rmdir( $dir ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
	}
}

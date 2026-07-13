<?php
/**
 * Installation registry — tracked plugins/themes with commit state.
 *
 * @package FlyGit
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Stores all FlyGit-managed installations in one non-autoloaded option.
 *
 * Item shape:
 * {
 *   id:             uuid
 *   type:           plugin|theme
 *   slug:           directory slug
 *   owner:          github owner
 *   repo:           github repo
 *   branch:         branch name
 *   token:          per-repo token, encrypted ('' = use global token)
 *   auto_update:    bool
 *   managed_by:     manual|manifest
 *   installed_sha:  commit sha currently deployed
 *   remote_sha:     latest known remote sha
 *   remote_date:    commit date of remote sha
 *   remote_message: first line of remote commit message
 *   etag:           etag for conditional commit checks
 *   last_checked:   unix ts of last remote check
 *   last_updated:   unix ts of last successful deploy
 *   last_error:     last error message ('' = none)
 * }
 */
class FlyGit_Registry {

	const OPTION_KEY = 'flygit_installations';

	/**
	 * Request-level cache.
	 *
	 * @var array|null
	 */
	protected $cache = null;

	/**
	 * Get all installations.
	 *
	 * @return array[]
	 */
	public function all() {
		if ( null === $this->cache ) {
			$stored      = get_option( self::OPTION_KEY, array() );
			$this->cache = is_array( $stored ) ? array_values( $stored ) : array();
		}

		return $this->cache;
	}

	/**
	 * Persist the full set.
	 *
	 * @param array $items Installations.
	 */
	protected function save( array $items ) {
		$this->cache = array_values( $items );
		update_option( self::OPTION_KEY, $this->cache, false );
	}

	/**
	 * Find one installation by id.
	 *
	 * @param string $id Installation id.
	 * @return array|null
	 */
	public function find( $id ) {
		foreach ( $this->all() as $item ) {
			if ( isset( $item['id'] ) && $item['id'] === $id ) {
				return $item;
			}
		}

		return null;
	}

	/**
	 * Find by type + slug.
	 *
	 * @param string $type plugin|theme.
	 * @param string $slug Directory slug.
	 * @return array|null
	 */
	public function find_by_slug( $type, $slug ) {
		foreach ( $this->all() as $item ) {
			if ( isset( $item['type'], $item['slug'] ) && $item['type'] === $type && $item['slug'] === $slug ) {
				return $item;
			}
		}

		return null;
	}

	/**
	 * Insert or update an installation keyed by type+slug.
	 *
	 * @param array $data Installation data (must contain type + slug).
	 * @return array Stored item.
	 */
	public function upsert( array $data ) {
		$items    = $this->all();
		$existing = null;

		foreach ( $items as $index => $item ) {
			if ( $item['type'] === $data['type'] && $item['slug'] === $data['slug'] ) {
				$existing = $index;
				break;
			}
		}

		if ( null !== $existing ) {
			$items[ $existing ] = array_merge( $items[ $existing ], $data );
			$stored             = $items[ $existing ];
		} else {
			$defaults = array(
				'id'             => wp_generate_uuid4(),
				'token'          => '',
				'auto_update'    => (bool) FlyGit_Options::get( 'auto_update', true ),
				'managed_by'     => 'manual',
				'installed_sha'  => '',
				'remote_sha'     => '',
				'remote_date'    => '',
				'remote_message' => '',
				'etag'           => '',
				'last_checked'   => 0,
				'last_updated'   => 0,
				'last_error'     => '',
			);
			$stored   = array_merge( $defaults, $data );
			$items[]  = $stored;
		}

		$this->save( $items );

		return $stored;
	}

	/**
	 * Patch an installation by id.
	 *
	 * @param string $id   Installation id.
	 * @param array  $data Fields to merge.
	 * @return bool
	 */
	public function patch( $id, array $data ) {
		$items = $this->all();

		foreach ( $items as $index => $item ) {
			if ( isset( $item['id'] ) && $item['id'] === $id ) {
				$items[ $index ] = array_merge( $item, $data );
				$this->save( $items );
				return true;
			}
		}

		return false;
	}

	/**
	 * Remove an installation record.
	 *
	 * @param string $id Installation id.
	 * @return bool
	 */
	public function remove( $id ) {
		$items = $this->all();
		$kept  = array();
		$found = false;

		foreach ( $items as $item ) {
			if ( isset( $item['id'] ) && $item['id'] === $id ) {
				$found = true;
				continue;
			}
			$kept[] = $item;
		}

		if ( $found ) {
			$this->save( $kept );
		}

		return $found;
	}

	/**
	 * Resolve the effective token for an installation.
	 *
	 * @param array $item Installation.
	 * @return string Plain token.
	 */
	public function effective_token( array $item ) {
		if ( ! empty( $item['token'] ) ) {
			$token = FlyGit_Crypto::decrypt( $item['token'] );
			if ( '' !== $token ) {
				return $token;
			}
		}

		return FlyGit_Options::github_token();
	}

	/**
	 * Installations that have a newer remote commit than installed.
	 *
	 * @return array[]
	 */
	public function pending_updates() {
		$pending = array();

		foreach ( $this->all() as $item ) {
			if ( ! empty( $item['remote_sha'] ) && $item['remote_sha'] !== $item['installed_sha'] ) {
				$pending[] = $item;
			}
		}

		return $pending;
	}
}

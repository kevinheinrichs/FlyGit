<?php
/**
 * Central settings access with a single autoloaded option.
 *
 * @package FlyGit
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * All plugin-wide settings live in ONE option row (`flygit_settings`)
 * to keep the options table lean. The installation registry and log
 * are stored separately with autoload disabled.
 */
class FlyGit_Options {

	const OPTION_KEY = 'flygit_settings';

	/**
	 * Cached settings for the current request.
	 *
	 * @var array|null
	 */
	protected static $cache = null;

	/**
	 * Default settings.
	 *
	 * @return array
	 */
	public static function defaults() {
		return array(
			'check_interval'     => 'twicedaily', // hourly | twicedaily | daily | flygit_15min.
			'github_token'       => '',           // encrypted at rest.
			'manifest_enabled'   => false,
			'manifest_repo'      => '',           // owner/repo.
			'manifest_branch'    => 'main',
			'manifest_path'      => 'fleet-manifest.json',
			'manifest_autoapply' => true,
			'webhook_enabled'    => false,
			'webhook_secret'     => '',
			'auto_update'        => true,          // global default for new installations.
			'keep_log_entries'   => 100,
		);
	}

	/**
	 * Get all settings (merged with defaults).
	 *
	 * @return array
	 */
	public static function all() {
		if ( null === self::$cache ) {
			$stored = get_option( self::OPTION_KEY, array() );
			if ( ! is_array( $stored ) ) {
				$stored = array();
			}
			self::$cache = wp_parse_args( $stored, self::defaults() );
		}

		return self::$cache;
	}

	/**
	 * Get a single setting.
	 *
	 * @param string $key     Setting key.
	 * @param mixed  $fallback Fallback when unset.
	 * @return mixed
	 */
	public static function get( $key, $fallback = null ) {
		$all = self::all();
		return array_key_exists( $key, $all ) ? $all[ $key ] : $fallback;
	}

	/**
	 * Update settings (partial merge).
	 *
	 * @param array $values Key/value pairs to update.
	 */
	public static function update( array $values ) {
		$all = array_merge( self::all(), $values );
		update_option( self::OPTION_KEY, $all, true );
		self::$cache = $all;
	}

	/**
	 * Ensure the option row exists with defaults on activation.
	 */
	public static function ensure_defaults() {
		if ( false === get_option( self::OPTION_KEY, false ) ) {
			add_option( self::OPTION_KEY, self::defaults(), '', true );
		}

		$settings = self::all();
		if ( empty( $settings['webhook_secret'] ) ) {
			self::update( array( 'webhook_secret' => wp_generate_password( 40, false, false ) ) );
		}
	}

	/**
	 * Get the decrypted global GitHub token.
	 *
	 * @return string
	 */
	public static function github_token() {
		$stored = self::get( 'github_token', '' );
		if ( '' === $stored ) {
			return '';
		}

		return FlyGit_Crypto::decrypt( $stored );
	}

	/**
	 * Store the GitHub token encrypted.
	 *
	 * @param string $token Plain token ('' clears it).
	 */
	public static function set_github_token( $token ) {
		$token = trim( (string) $token );
		self::update( array( 'github_token' => '' === $token ? '' : FlyGit_Crypto::encrypt( $token ) ) );
	}
}

<?php
/**
 * Ring-buffer activity log (single non-autoloaded option).
 *
 * @package FlyGit
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Lightweight logger: newest first, capped length, no autoload,
 * so it never weighs on frontend requests.
 */
class FlyGit_Logger {

	const OPTION_KEY = 'flygit_log';

	/**
	 * Append a log entry.
	 *
	 * @param string $level   info | success | warning | error.
	 * @param string $message Log message.
	 * @param array  $context Optional context (slug, repo, ...).
	 */
	public static function log( $level, $message, array $context = array() ) {
		$entries = get_option( self::OPTION_KEY, array() );
		if ( ! is_array( $entries ) ) {
			$entries = array();
		}

		array_unshift(
			$entries,
			array(
				'time'    => time(),
				'level'   => in_array( $level, array( 'info', 'success', 'warning', 'error' ), true ) ? $level : 'info',
				'message' => wp_strip_all_tags( (string) $message ),
				'context' => $context,
			)
		);

		$max     = (int) FlyGit_Options::get( 'keep_log_entries', 100 );
		$max     = max( 10, min( 500, $max ) );
		$entries = array_slice( $entries, 0, $max );

		update_option( self::OPTION_KEY, $entries, false );
	}

	/**
	 * Get log entries (newest first).
	 *
	 * @param int $limit Max entries.
	 * @return array
	 */
	public static function entries( $limit = 50 ) {
		$entries = get_option( self::OPTION_KEY, array() );
		if ( ! is_array( $entries ) ) {
			return array();
		}

		return array_slice( $entries, 0, max( 1, (int) $limit ) );
	}

	/**
	 * Clear the log.
	 */
	public static function clear() {
		update_option( self::OPTION_KEY, array(), false );
	}
}

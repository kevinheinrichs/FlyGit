<?php
/**
 * Token encryption at rest (AES-256-GCM via WordPress salts).
 *
 * @package FlyGit
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Encrypts secrets before they hit the database. Uses AUTH_KEY/AUTH_SALT
 * as key material, so tokens are useless when only the DB leaks.
 * Falls back to base64 tagging when OpenSSL is unavailable (rare).
 */
class FlyGit_Crypto {

	const PREFIX_ENC   = '$fg1$'; // AES-256-GCM.
	const PREFIX_PLAIN = '$fg0$'; // base64 fallback.

	/**
	 * Derive a stable 32-byte key from WP salts.
	 *
	 * @return string
	 */
	protected static function key() {
		$material = ( defined( 'AUTH_KEY' ) ? AUTH_KEY : '' ) . ( defined( 'AUTH_SALT' ) ? AUTH_SALT : '' );
		if ( '' === $material ) {
			$material = wp_salt( 'auth' );
		}

		return hash( 'sha256', 'flygit-v2|' . $material, true );
	}

	/**
	 * Encrypt a value.
	 *
	 * @param string $plain Plain text.
	 * @return string Storable cipher string.
	 */
	public static function encrypt( $plain ) {
		$plain = (string) $plain;
		if ( '' === $plain ) {
			return '';
		}

		if ( function_exists( 'openssl_encrypt' ) ) {
			$iv     = random_bytes( 12 );
			$tag    = '';
			$cipher = openssl_encrypt( $plain, 'aes-256-gcm', self::key(), OPENSSL_RAW_DATA, $iv, $tag );

			if ( false !== $cipher ) {
				return self::PREFIX_ENC . base64_encode( $iv . $tag . $cipher ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
			}
		}

		return self::PREFIX_PLAIN . base64_encode( $plain ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
	}

	/**
	 * Decrypt a stored value. Tolerates legacy plaintext values.
	 *
	 * @param string $stored Stored string.
	 * @return string
	 */
	public static function decrypt( $stored ) {
		$stored = (string) $stored;
		if ( '' === $stored ) {
			return '';
		}

		if ( 0 === strpos( $stored, self::PREFIX_ENC ) ) {
			$raw = base64_decode( substr( $stored, strlen( self::PREFIX_ENC ) ), true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
			if ( false === $raw || strlen( $raw ) < 29 ) {
				return '';
			}

			$iv     = substr( $raw, 0, 12 );
			$tag    = substr( $raw, 12, 16 );
			$cipher = substr( $raw, 28 );

			if ( ! function_exists( 'openssl_decrypt' ) ) {
				return '';
			}

			$plain = openssl_decrypt( $cipher, 'aes-256-gcm', self::key(), OPENSSL_RAW_DATA, $iv, $tag );

			return false === $plain ? '' : $plain;
		}

		if ( 0 === strpos( $stored, self::PREFIX_PLAIN ) ) {
			$plain = base64_decode( substr( $stored, strlen( self::PREFIX_PLAIN ) ), true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
			return false === $plain ? '' : $plain;
		}

		// Legacy plaintext (v1 migration).
		return $stored;
	}
}

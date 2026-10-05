<?php
/**
 * Encryption for stored secrets (SMTP passwords).
 *
 * @package Glixform
 */

namespace Glixform\Mail;

defined( 'ABSPATH' ) || exit;

/**
 * AES-256-GCM with a key derived from the site's secret keys in wp-config.php,
 * so a database dump alone does not reveal the password. If the secret keys
 * change, stored secrets can no longer be decrypted and must be re-entered.
 */
class Crypto {

	const PREFIX = 'gfenc1:';

	/**
	 * Encryption key.
	 *
	 * @return string 32 raw bytes.
	 */
	private static function key() {
		$material = defined( 'GLIXFORM_ENCRYPTION_KEY' ) ? (string) GLIXFORM_ENCRYPTION_KEY : wp_salt( 'auth' ) . wp_salt( 'secure_auth' );
		return hash( 'sha256', 'glixform|' . $material, true );
	}

	/**
	 * Whether strong encryption is available.
	 *
	 * @return bool
	 */
	public static function available() {
		return function_exists( 'openssl_encrypt' ) && in_array( 'aes-256-gcm', openssl_get_cipher_methods(), true );
	}

	/**
	 * Encrypt a value.
	 *
	 * @param string $plain Plain text.
	 * @return string Encrypted text, or '' for an empty value.
	 */
	public static function encrypt( $plain ) {
		$plain = (string) $plain;
		if ( '' === $plain ) {
			return '';
		}
		if ( ! self::available() ) {
			// Last resort: obscure rather than store in clear text.
			return 'gfb64:' . base64_encode( $plain ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
		}
		$iv     = random_bytes( 12 );
		$tag    = '';
		$cipher = openssl_encrypt( $plain, 'aes-256-gcm', self::key(), OPENSSL_RAW_DATA, $iv, $tag );
		return self::PREFIX . base64_encode( $iv . $tag . $cipher ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
	}

	/**
	 * Decrypt a value.
	 *
	 * @param string $stored Stored text.
	 * @return string Plain text, or '' if it cannot be decrypted.
	 */
	public static function decrypt( $stored ) {
		$stored = (string) $stored;
		if ( 0 === strpos( $stored, 'gfb64:' ) ) {
			return (string) base64_decode( substr( $stored, 6 ), true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
		}
		if ( 0 !== strpos( $stored, self::PREFIX ) || ! self::available() ) {
			return '';
		}
		$raw = base64_decode( substr( $stored, strlen( self::PREFIX ) ), true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
		if ( false === $raw || strlen( $raw ) < 29 ) {
			return '';
		}
		$plain = openssl_decrypt( substr( $raw, 28 ), 'aes-256-gcm', self::key(), OPENSSL_RAW_DATA, substr( $raw, 0, 12 ), substr( $raw, 12, 16 ) );
		return false === $plain ? '' : $plain;
	}
}

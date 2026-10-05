<?php
/**
 * Email delivery settings.
 *
 * @package Glixform
 */

namespace Glixform\Mail;

defined( 'ABSPATH' ) || exit;

/**
 * Stored in the "glixform_email" option.
 *
 * The SMTP password is encrypted with Crypto and never sent back to the browser.
 * It can also be set in wp-config.php with define( 'GLIXFORM_SMTP_PASSWORD', '...' ).
 */
class SmtpSettings {

	const OPTION = 'glixform_email';

	/**
	 * Defaults.
	 *
	 * @return array
	 */
	public static function defaults() {
		return array(
			'provider'         => 'default',
			'host'             => '',
			'encryption'       => 'tls',
			'port'             => 587,
			'auth'             => true,
			'username'         => '',
			'password'         => '',
			'from_email'       => '',
			'from_name'        => '',
			'force_from_email' => false,
			'force_from_name'  => false,
			'log_enabled'      => true,
			'log_days'         => 30,
		);
	}

	/**
	 * Current settings.
	 *
	 * @return array
	 */
	public static function get() {
		$saved = get_option( self::OPTION, array() );
		return array_merge( self::defaults(), is_array( $saved ) ? $saved : array() );
	}

	/**
	 * Whether SMTP is chosen (any provider other than "default").
	 *
	 * @param array|null $settings Settings.
	 * @return bool
	 */
	public static function uses_smtp( $settings = null ) {
		$settings = $settings ?? self::get();
		return 'default' !== $settings['provider'] && '' !== $settings['host'];
	}

	/**
	 * Decrypted SMTP password (wp-config.php constant wins).
	 *
	 * @param array|null $settings Settings.
	 * @return string
	 */
	public static function password( $settings = null ) {
		if ( defined( 'GLIXFORM_SMTP_PASSWORD' ) ) {
			return (string) GLIXFORM_SMTP_PASSWORD;
		}
		$settings = $settings ?? self::get();
		return Crypto::decrypt( (string) $settings['password'] );
	}

	/**
	 * Whether a password is stored or defined.
	 *
	 * @return bool
	 */
	public static function has_password() {
		return defined( 'GLIXFORM_SMTP_PASSWORD' ) || '' !== (string) self::get()['password'];
	}

	/**
	 * Sanitize submitted settings.
	 *
	 * The form posts the new password as "new_password"; an empty value keeps the
	 * saved one. "password" itself is only accepted when already encrypted (WordPress
	 * may run this callback twice on the first save).
	 *
	 * @param mixed $input Raw input.
	 * @return array
	 */
	public static function sanitize( $input ) {
		$input    = is_array( $input ) ? $input : array();
		$current  = self::get();
		$defaults = self::defaults();

		$provider = sanitize_key( (string) ( $input['provider'] ?? $current['provider'] ) );
		if ( 'default' !== $provider && ! Providers::get( $provider ) ) {
			$provider = 'default';
		}

		$encryption = (string) ( $input['encryption'] ?? $current['encryption'] );
		if ( ! in_array( $encryption, array( 'none', 'ssl', 'tls' ), true ) ) {
			$encryption = 'tls';
		}

		$port = absint( $input['port'] ?? $current['port'] );
		if ( $port < 1 || $port > 65535 ) {
			$port = Providers::default_port( $encryption );
		}

		// Host: keep only valid hostname characters.
		$host = strtolower( trim( (string) ( $input['host'] ?? $current['host'] ) ) );
		$host = (string) preg_replace( '/[^a-z0-9.\-:\[\]]/', '', $host );
		if ( '' === $host && 'default' !== $provider ) {
			$preset = Providers::get( $provider );
			$host   = (string) ( $preset['host'] ?? '' );
		}

		$password = (string) $current['password'];
		if ( isset( $input['password'] ) && 0 === strpos( (string) $input['password'], Crypto::PREFIX ) ) {
			$password = (string) $input['password'];
		}
		if ( ! empty( $input['remove_password'] ) ) {
			$password = '';
		}
		if ( isset( $input['new_password'] ) && '' !== (string) $input['new_password'] ) {
			// Passwords may contain any character; only strip line breaks.
			$new = str_replace( array( "\r", "\n" ), '', (string) $input['new_password'] );
			// Google shows App Passwords in groups ("abcd efgh ijkl mnop"); Gmail wants them without spaces.
			if ( 'gmail' === $provider && preg_match( '/^[a-z]{4}( [a-z]{4}){3}$/i', trim( $new ) ) ) {
				$new = str_replace( ' ', '', trim( $new ) );
			}
			$password = Crypto::encrypt( $new );
		}

		$from_email = sanitize_email( (string) ( $input['from_email'] ?? '' ) );

		return array(
			'provider'         => $provider,
			'host'             => $host,
			'encryption'       => $encryption,
			'port'             => $port,
			'auth'             => ! empty( $input['auth'] ),
			'username'         => trim( sanitize_text_field( (string) ( $input['username'] ?? '' ) ) ),
			'password'         => $password,
			'from_email'       => is_email( $from_email ) ? $from_email : '',
			'from_name'        => sanitize_text_field( (string) ( $input['from_name'] ?? '' ) ),
			'force_from_email' => ! empty( $input['force_from_email'] ),
			'force_from_name'  => ! empty( $input['force_from_name'] ),
			'log_enabled'      => ! empty( $input['log_enabled'] ),
			'log_days'         => min( 365, max( 1, absint( $input['log_days'] ?? $defaults['log_days'] ) ) ),
		);
	}
}

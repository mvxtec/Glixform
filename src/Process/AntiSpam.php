<?php
/**
 * Lightweight spam protection: honeypot and signed timestamp token.
 *
 * @package Glixform
 */

namespace Glixform\Process;

use Glixform\Plugin;

defined( 'ABSPATH' ) || exit;

/**
 * Nonces are not used on public forms because page caches would serve stale
 * ones. Instead each form carries a signed render timestamp: bots that post
 * without loading the form, or submit faster than a human can, are rejected.
 */
class AntiSpam {

	const HONEYPOT_NAME = 'glixform_hp';

	/**
	 * Create a token for a form.
	 *
	 * @param int      $form_id Form ID.
	 * @param int|null $time    Timestamp, defaults to now.
	 * @return string
	 */
	public static function token( $form_id, $time = null ) {
		$time = null === $time ? time() : (int) $time;
		return $time . ':' . self::sign( $form_id, $time );
	}

	/**
	 * Check a token.
	 *
	 * @param int    $form_id Form ID.
	 * @param string $token   Submitted token.
	 * @return bool
	 */
	public static function verify_token( $form_id, $token ) {
		$parts = explode( ':', (string) $token, 2 );
		if ( 2 !== count( $parts ) || ! ctype_digit( $parts[0] ) ) {
			return false;
		}
		$time = (int) $parts[0];
		if ( ! hash_equals( self::sign( $form_id, $time ), $parts[1] ) ) {
			return false;
		}
		$min_seconds = (int) Plugin::setting( 'min_submit_seconds', 2 );
		return ( time() - $time ) >= $min_seconds && $time <= time() + 60;
	}

	/**
	 * Whether the honeypot was filled in.
	 *
	 * @param array $data Submitted glixform[] data.
	 * @return bool
	 */
	public static function honeypot_triggered( array $data ) {
		return isset( $data[ self::HONEYPOT_NAME ] ) && '' !== trim( (string) $data[ self::HONEYPOT_NAME ] );
	}

	/**
	 * HMAC for a form and time.
	 *
	 * @param int $form_id Form ID.
	 * @param int $time    Timestamp.
	 * @return string
	 */
	private static function sign( $form_id, $time ) {
		return hash_hmac( 'sha256', 'glixform|' . absint( $form_id ) . '|' . (int) $time, wp_salt( 'nonce' ) );
	}
}

<?php
/**
 * Akismet spam check for submissions.
 *
 * @package Glixform
 */

namespace Glixform\Process;

defined( 'ABSPATH' ) || exit;

/**
 * Uses the Akismet plugin when it is active and connected.
 */
class Akismet {

	/**
	 * Whether Akismet is active with an API key.
	 *
	 * @return bool
	 */
	public static function is_available() {
		return class_exists( '\Akismet' ) && is_callable( array( '\Akismet', 'get_api_key' ) ) && (bool) \Akismet::get_api_key();
	}

	/**
	 * Ask Akismet whether a submission is spam.
	 *
	 * @param array  $form     Form.
	 * @param array  $snapshot Submitted fields.
	 * @param string $page_url Page URL.
	 * @return bool True for spam.
	 */
	public static function is_spam( array $form, array $snapshot, $page_url = '' ) {
		if ( ! self::is_available() ) {
			return false;
		}

		$author  = '';
		$email   = '';
		$content = array();
		foreach ( $snapshot as $item ) {
			$text = (string) ( $item['formatted'] ?? '' );
			if ( '' === $email && 'email' === $item['type'] ) {
				$email = $text;
			} elseif ( '' === $author && 'name' === $item['type'] ) {
				$author = $text;
			} elseif ( in_array( $item['type'], array( 'text', 'textarea' ), true ) ) {
				$content[] = $text;
			}
		}

		$request = array(
			'blog'                 => home_url( '/' ),
			'user_ip'              => isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '',
			'user_agent'           => isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '',
			'referrer'             => isset( $_SERVER['HTTP_REFERER'] ) ? esc_url_raw( wp_unslash( $_SERVER['HTTP_REFERER'] ) ) : '',
			'permalink'            => $page_url,
			'comment_type'         => 'contact-form',
			'comment_author'       => $author,
			'comment_author_email' => $email,
			'comment_content'      => implode( "\n\n", array_filter( $content ) ),
			'blog_lang'            => get_locale(),
			'blog_charset'         => get_option( 'blog_charset' ),
		);

		/**
		 * Filters the data sent to Akismet.
		 *
		 * @param array $request Request data.
		 * @param array $form    Form.
		 */
		$request  = apply_filters( 'glixform_akismet_request', $request, $form );
		$response = \Akismet::http_post( build_query( $request ), 'comment-check' );

		return is_array( $response ) && isset( $response[1] ) && 'true' === trim( (string) $response[1] );
	}
}

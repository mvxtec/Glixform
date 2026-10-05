<?php
/**
 * CAPTCHA providers: reCAPTCHA v2/v3, hCaptcha and Cloudflare Turnstile.
 *
 * @package Glixform
 */

namespace Glixform\Process;

use Glixform\Plugin;

defined( 'ABSPATH' ) || exit;

/**
 * One provider is configured site-wide (Glixform → Settings); each form opts in.
 */
class Captcha {

	const PROVIDERS = array(
		'recaptcha_v2' => array(
			'script'       => 'https://www.google.com/recaptcha/api.js',
			'verify'       => 'https://www.google.com/recaptcha/api/siteverify',
			'response_key' => 'g-recaptcha-response',
			'class'        => 'g-recaptcha',
		),
		'recaptcha_v3' => array(
			'script'       => 'https://www.google.com/recaptcha/api.js?render=',
			'verify'       => 'https://www.google.com/recaptcha/api/siteverify',
			'response_key' => 'g-recaptcha-response',
			'class'        => '',
		),
		'hcaptcha'     => array(
			'script'       => 'https://js.hcaptcha.com/1/api.js',
			'verify'       => 'https://api.hcaptcha.com/siteverify',
			'response_key' => 'h-captcha-response',
			'class'        => 'h-captcha',
		),
		'turnstile'    => array(
			'script'       => 'https://challenges.cloudflare.com/turnstile/v0/api.js',
			'verify'       => 'https://challenges.cloudflare.com/turnstile/v0/siteverify',
			'response_key' => 'cf-turnstile-response',
			'class'        => 'cf-turnstile',
		),
	);

	/**
	 * Provider labels for the settings screen.
	 *
	 * @return array
	 */
	public static function labels() {
		return array(
			''             => __( 'None', 'glixform' ),
			'turnstile'    => __( 'Cloudflare Turnstile', 'glixform' ),
			'hcaptcha'     => __( 'hCaptcha', 'glixform' ),
			'recaptcha_v2' => __( 'Google reCAPTCHA v2 (checkbox)', 'glixform' ),
			'recaptcha_v3' => __( 'Google reCAPTCHA v3 (invisible)', 'glixform' ),
		);
	}

	/**
	 * Configured provider slug, or '' when not set up.
	 *
	 * @return string
	 */
	public static function provider() {
		$provider = (string) Plugin::setting( 'captcha_provider', '' );
		if ( ! isset( self::PROVIDERS[ $provider ] ) || '' === (string) Plugin::setting( 'captcha_site_key', '' ) || '' === (string) Plugin::setting( 'captcha_secret_key', '' ) ) {
			return '';
		}
		return $provider;
	}

	/**
	 * Whether a CAPTCHA provider is fully configured.
	 *
	 * @return bool
	 */
	public static function is_configured() {
		return '' !== self::provider();
	}

	/**
	 * Request key holding the provider's token.
	 *
	 * @return string
	 */
	public static function response_key() {
		$provider = self::provider();
		return $provider ? self::PROVIDERS[ $provider ]['response_key'] : '';
	}

	/**
	 * Widget markup and script.
	 *
	 * @return string
	 */
	public static function render() {
		$provider = self::provider();
		if ( ! $provider ) {
			return '';
		}
		$config   = self::PROVIDERS[ $provider ];
		$site_key = (string) Plugin::setting( 'captcha_site_key', '' );

		$src = 'recaptcha_v3' === $provider ? $config['script'] . rawurlencode( $site_key ) : $config['script'];
		wp_enqueue_script( 'glixform-captcha', $src, array(), null, true ); // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion -- Third-party script.

		if ( 'recaptcha_v3' === $provider ) {
			// The token is fetched on submit by frontend.js.
			return sprintf(
				'<input type="hidden" name="g-recaptcha-response" value="" class="glixform-recaptcha-v3" data-sitekey="%s">',
				esc_attr( $site_key )
			);
		}

		return sprintf(
			'<div class="glixform-captcha"><div class="%s" data-sitekey="%s"></div></div>',
			esc_attr( $config['class'] ),
			esc_attr( $site_key )
		);
	}

	/**
	 * Verify a token with the provider.
	 *
	 * @param string $token Token from the request.
	 * @param string $ip    Visitor IP.
	 * @return bool
	 */
	public static function verify( $token, $ip = '' ) {
		$provider = self::provider();
		if ( ! $provider ) {
			return true;
		}
		if ( '' === (string) $token ) {
			return false;
		}

		$response = wp_remote_post(
			self::PROVIDERS[ $provider ]['verify'],
			array(
				'timeout' => 10,
				'body'    => array_filter(
					array(
						'secret'   => (string) Plugin::setting( 'captcha_secret_key', '' ),
						'response' => (string) $token,
						'remoteip' => (string) $ip,
					)
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return false;
		}

		$body = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $body ) || empty( $body['success'] ) ) {
			return false;
		}

		if ( 'recaptcha_v3' === $provider ) {
			$threshold = (float) Plugin::setting( 'recaptcha_v3_threshold', 0.5 );
			return isset( $body['score'] ) && (float) $body['score'] >= $threshold;
		}

		return true;
	}
}

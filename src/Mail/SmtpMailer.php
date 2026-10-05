<?php
/**
 * Sends WordPress email through the configured SMTP server.
 *
 * @package Glixform
 */

namespace Glixform\Mail;

defined( 'ABSPATH' ) || exit;

/**
 * WordPress sends all email with PHPMailer and lets plugins configure it on
 * "phpmailer_init". This applies to every email the site sends, not only
 * Glixform notifications. Nothing is changed while another SMTP plugin is active.
 */
class SmtpMailer {

	/**
	 * Collects SMTP server replies during a test email (null when not collecting).
	 *
	 * @var string[]|null
	 */
	public static $transcript = null;

	/**
	 * Hook into WordPress.
	 */
	public function register_hooks() {
		add_action( 'plugins_loaded', array( $this, 'maybe_hook' ), 20 );
	}

	/**
	 * Add the mail hooks unless another email plugin is in charge.
	 */
	public function maybe_hook() {
		if ( '' !== Conflicts::active_plugin() ) {
			return;
		}
		add_action( 'phpmailer_init', array( $this, 'configure' ), 999 );
		add_filter( 'wp_mail_from', array( $this, 'from_email' ), 999 );
		add_filter( 'wp_mail_from_name', array( $this, 'from_name' ), 999 );
	}

	/**
	 * Point PHPMailer at the SMTP server.
	 *
	 * @param \PHPMailer\PHPMailer\PHPMailer $phpmailer Mailer.
	 */
	public function configure( $phpmailer ) {
		$settings = SmtpSettings::get();
		if ( ! SmtpSettings::uses_smtp( $settings ) ) {
			return;
		}

		// phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- PHPMailer API.
		$phpmailer->isSMTP();
		$phpmailer->Host        = $settings['host'];
		$phpmailer->Port        = (int) $settings['port'];
		$phpmailer->SMTPSecure  = 'none' === $settings['encryption'] ? '' : $settings['encryption'];
		$phpmailer->SMTPAutoTLS = 'none' !== $settings['encryption'];
		$phpmailer->SMTPAuth    = (bool) $settings['auth'];
		$phpmailer->Timeout     = 20;

		if ( $settings['auth'] ) {
			$phpmailer->Username = $settings['username'];
			$phpmailer->Password = SmtpSettings::password( $settings );
		}

		// Envelope sender matches the From address so SPF/DMARC checks line up.
		if ( $settings['from_email'] && $settings['force_from_email'] ) {
			$phpmailer->Sender = $settings['from_email'];
		}

		if ( is_array( self::$transcript ) ) {
			$phpmailer->SMTPDebug   = 2;
			$phpmailer->Debugoutput = static function ( $line ) {
				// Keep only server replies: client lines can contain encoded credentials.
				if ( 0 === strpos( (string) $line, 'SERVER -> CLIENT:' ) ) {
					self::$transcript[] = trim( substr( (string) $line, 17 ) );
				}
			};
		}
		// phpcs:enable
	}

	/**
	 * From address: always when forced, otherwise only instead of WordPress's default.
	 *
	 * @param string $email Current From address.
	 * @return string
	 */
	public function from_email( $email ) {
		$settings = SmtpSettings::get();
		if ( '' === $settings['from_email'] ) {
			return $email;
		}
		if ( $settings['force_from_email'] || 0 === strpos( (string) $email, 'wordpress@' ) ) {
			return $settings['from_email'];
		}
		return $email;
	}

	/**
	 * From name: always when forced, otherwise only instead of "WordPress".
	 *
	 * @param string $name Current From name.
	 * @return string
	 */
	public function from_name( $name ) {
		$settings = SmtpSettings::get();
		if ( '' === $settings['from_name'] ) {
			return $name;
		}
		if ( $settings['force_from_name'] || 'WordPress' === $name ) {
			return $settings['from_name'];
		}
		return $name;
	}
}

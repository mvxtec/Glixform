<?php
/**
 * Detects other plugins that already handle email delivery.
 *
 * @package Glixform
 */

namespace Glixform\Mail;

defined( 'ABSPATH' ) || exit;

/**
 * When another SMTP plugin is active, Glixform leaves email delivery to it.
 */
class Conflicts {

	/**
	 * Name of the active SMTP plugin, or '' when there is none.
	 *
	 * @return string
	 */
	public static function active_plugin() {
		$checks = array(
			'WP Mail SMTP' => defined( 'WPMS_PLUGIN_VER' ) || function_exists( 'wp_mail_smtp' ),
			'FluentSMTP'   => defined( 'FLUENTMAIL' ) || defined( 'FLUENTMAIL_PLUGIN_VERSION' ),
			'Post SMTP'    => defined( 'POST_SMTP_VER' ) || class_exists( 'PostmanWpMail' ),
			'Easy WP SMTP' => defined( 'EasyWPSMTP_PLUGIN_VERSION' ) || class_exists( 'EasyWPSMTP' ) || class_exists( '\EasyWPSMTP\Core' ),
			'SMTP Mailer'  => class_exists( 'SMTP_MAILER' ),
			'Site Mailer'  => defined( 'SITE_MAILER_VERSION' ),
		);

		/**
		 * Filters the detected email plugins (name => active).
		 *
		 * @param array $checks Checks.
		 */
		$checks = apply_filters( 'glixform_smtp_conflicts', $checks );

		foreach ( $checks as $name => $active ) {
			if ( $active ) {
				return (string) $name;
			}
		}
		return '';
	}
}

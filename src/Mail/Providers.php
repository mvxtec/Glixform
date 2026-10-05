<?php
/**
 * Ready-made settings for popular email services.
 *
 * @package Glixform
 */

namespace Glixform\Mail;

defined( 'ABSPATH' ) || exit;

/**
 * Presets fill in the server details; the site owner only adds their login.
 */
class Providers {

	/**
	 * All presets.
	 *
	 * @return array slug => [ name, host, encryption, port, auth, username_hint, help, docs ].
	 */
	public static function all() {
		$presets = array(
			'gmail'    => array(
				'name'          => __( 'Gmail / Google Workspace', 'glixform' ),
				'host'          => 'smtp.gmail.com',
				'encryption'    => 'tls',
				'port'          => 587,
				'username_hint' => __( 'Your full Gmail address', 'glixform' ),
				'password_hint' => __( 'A Google App Password (16 characters), not your normal password', 'glixform' ),
				'help'          => __( 'Turn on 2-Step Verification in your Google Account, then search for "App passwords" and create one. The From Email must be this Gmail address.', 'glixform' ),
				'docs'          => 'https://support.google.com/accounts/answer/185833',
			),
			'outlook'  => array(
				'name'          => __( 'Outlook / Microsoft 365', 'glixform' ),
				'host'          => 'smtp.office365.com',
				'encryption'    => 'tls',
				'port'          => 587,
				'username_hint' => __( 'Your full Microsoft 365 email address', 'glixform' ),
				'password_hint' => __( 'Your mailbox password or an app password', 'glixform' ),
				'help'          => __( 'Your Microsoft 365 admin must allow "Authenticated SMTP" for this mailbox. Personal Outlook.com accounts may not support it.', 'glixform' ),
				'docs'          => 'https://learn.microsoft.com/exchange/clients-and-mobile-in-exchange-online/authenticated-client-smtp-submission',
			),
			'brevo'    => array(
				'name'          => __( 'Brevo (Sendinblue)', 'glixform' ),
				'host'          => 'smtp-relay.brevo.com',
				'encryption'    => 'tls',
				'port'          => 587,
				'username_hint' => __( 'Your Brevo SMTP login (shown in SMTP & API settings)', 'glixform' ),
				'password_hint' => __( 'Your Brevo SMTP key', 'glixform' ),
				'help'          => __( 'In Brevo, open SMTP & API → SMTP, then copy the login and generate an SMTP key. Verify your sender address in Brevo first.', 'glixform' ),
				'docs'          => 'https://help.brevo.com/hc/en-us/articles/7924908994450',
			),
			'sendgrid' => array(
				'name'          => __( 'SendGrid', 'glixform' ),
				'host'          => 'smtp.sendgrid.net',
				'encryption'    => 'tls',
				'port'          => 587,
				'username'      => 'apikey',
				'username_hint' => __( 'Always the word "apikey"', 'glixform' ),
				'password_hint' => __( 'Your SendGrid API key', 'glixform' ),
				'help'          => __( 'Create an API key with "Mail Send" access in SendGrid, and verify your sender identity.', 'glixform' ),
				'docs'          => 'https://www.twilio.com/docs/sendgrid/for-developers/sending-email/integrating-with-the-smtp-api',
			),
			'mailgun'  => array(
				'name'          => __( 'Mailgun', 'glixform' ),
				'host'          => 'smtp.mailgun.org',
				'encryption'    => 'tls',
				'port'          => 587,
				'username_hint' => __( 'Your Mailgun SMTP login, e.g. postmaster@mg.yourdomain.com', 'glixform' ),
				'password_hint' => __( 'The SMTP password for that login', 'glixform' ),
				'help'          => __( 'Use smtp.eu.mailgun.org if your Mailgun domain is in the EU region.', 'glixform' ),
				'docs'          => 'https://documentation.mailgun.com/docs/mailgun/user-manual/sending-messages/send-smtp',
			),
			'zoho'     => array(
				'name'          => __( 'Zoho Mail', 'glixform' ),
				'host'          => 'smtp.zoho.com',
				'encryption'    => 'ssl',
				'port'          => 465,
				'username_hint' => __( 'Your full Zoho email address', 'glixform' ),
				'password_hint' => __( 'Your Zoho password, or an app-specific password if 2FA is on', 'glixform' ),
				'help'          => __( 'Use smtp.zoho.eu or smtp.zoho.in if your account is in those data centres.', 'glixform' ),
				'docs'          => 'https://www.zoho.com/mail/help/zoho-smtp.html',
			),
			'smtp2go'  => array(
				'name'          => __( 'SMTP2GO', 'glixform' ),
				'host'          => 'mail.smtp2go.com',
				'encryption'    => 'tls',
				'port'          => 587,
				'username_hint' => __( 'The SMTP username you created in SMTP2GO', 'glixform' ),
				'password_hint' => __( 'That SMTP user’s password', 'glixform' ),
				'help'          => __( 'If port 587 is blocked by your host, try 2525.', 'glixform' ),
				'docs'          => 'https://support.smtp2go.com/hc/en-gb/articles/223087967',
			),
			'ses'      => array(
				'name'          => __( 'Amazon SES', 'glixform' ),
				'host'          => 'email-smtp.us-east-1.amazonaws.com',
				'encryption'    => 'tls',
				'port'          => 587,
				'username_hint' => __( 'Your SES SMTP username (not your AWS access key)', 'glixform' ),
				'password_hint' => __( 'Your SES SMTP password', 'glixform' ),
				'help'          => __( 'Change the region in the host to match your SES region, e.g. email-smtp.eu-west-1.amazonaws.com.', 'glixform' ),
				'docs'          => 'https://docs.aws.amazon.com/ses/latest/dg/smtp-credentials.html',
			),
			'other'    => array(
				'name'          => __( 'Other SMTP', 'glixform' ),
				'host'          => '',
				'encryption'    => 'tls',
				'port'          => 587,
				'username_hint' => __( 'Usually your full email address', 'glixform' ),
				'password_hint' => __( 'Your email password or an app password', 'glixform' ),
				'help'          => __( 'Get these details from your web host or email provider.', 'glixform' ),
				'docs'          => '',
			),
		);

		/**
		 * Filters the SMTP provider presets.
		 *
		 * @param array $presets Presets.
		 */
		return apply_filters( 'glixform_smtp_providers', $presets );
	}

	/**
	 * One preset.
	 *
	 * @param string $slug Slug.
	 * @return array|null
	 */
	public static function get( $slug ) {
		$all = self::all();
		return $all[ $slug ] ?? null;
	}

	/**
	 * Default port for an encryption type.
	 *
	 * @param string $encryption none|ssl|tls.
	 * @return int
	 */
	public static function default_port( $encryption ) {
		return array(
			'ssl'  => 465,
			'tls'  => 587,
			'none' => 25,
		)[ $encryption ] ?? 587;
	}
}

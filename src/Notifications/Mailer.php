<?php
/**
 * Sends notification emails.
 *
 * @package Glixform
 */

namespace Glixform\Notifications;

defined( 'ABSPATH' ) || exit;

/**
 * Builds and sends the admin notification for an entry.
 */
class Mailer {

	/**
	 * Smart tag parser.
	 *
	 * @var SmartTags
	 */
	private $smart_tags;

	/**
	 * Constructor.
	 *
	 * @param SmartTags $smart_tags Parser.
	 */
	public function __construct( SmartTags $smart_tags ) {
		$this->smart_tags = $smart_tags;
	}

	/**
	 * Send the form's notification.
	 *
	 * @param array $form    Form (from FormRepository::get()).
	 * @param array $context Smart tag context.
	 * @return bool Whether wp_mail() reported success. False when disabled.
	 */
	public function send( array $form, array $context ) {
		$settings = $form['data']['settings']['notification'];
		if ( empty( $settings['enabled'] ) ) {
			return false;
		}

		$to = $this->email_list( $this->smart_tags->process( $settings['to'], $context, 'text' ) );
		if ( ! $to ) {
			return false;
		}

		$subject   = $this->single_line( $this->smart_tags->process( $settings['subject'], $context, 'text' ) );
		$from_name = $this->single_line( $this->smart_tags->process( $settings['from_name'], $context, 'text' ) );
		$reply_to  = $this->email_list( $this->smart_tags->process( $settings['reply_to'], $context, 'text' ) );
		// wpautop() would wrap the {all_fields} table in a <p>, which is invalid HTML.
		$body = str_replace( '<p>{all_fields}</p>', '{all_fields}', wpautop( $settings['message'] ) );
		$body = $this->smart_tags->process( $body, $context, 'html' );

		$headers = array( 'Content-Type: text/html; charset=UTF-8' );
		if ( $reply_to ) {
			$headers[] = 'Reply-To: ' . $reply_to[0];
		}

		$from_filter = null;
		if ( '' !== $from_name ) {
			// Only the display name is customisable; the address stays the site default to avoid spoofing and spam flags.
			$from_filter = static function () use ( $from_name ) {
				return $from_name;
			};
			add_filter( 'wp_mail_from_name', $from_filter );
		}

		/**
		 * Filters the notification email before sending.
		 *
		 * @param array $email   to, subject, message, headers.
		 * @param array $form    Form.
		 * @param array $context Smart tag context.
		 */
		$email = apply_filters(
			'glixform_notification_email',
			array(
				'to'      => $to,
				'subject' => $subject,
				'message' => $this->wrap( $body, $subject ),
				'headers' => $headers,
			),
			$form,
			$context
		);

		$sent = wp_mail( $email['to'], $email['subject'], $email['message'], $email['headers'] );

		if ( $from_filter ) {
			remove_filter( 'wp_mail_from_name', $from_filter );
		}

		return (bool) $sent;
	}

	/**
	 * Split a comma-separated list and keep only valid addresses.
	 *
	 * @param string $addresses Comma-separated addresses.
	 * @return string[]
	 */
	public function email_list( $addresses ) {
		$valid = array();
		foreach ( explode( ',', (string) $addresses ) as $address ) {
			$address = sanitize_email( $this->single_line( $address ) );
			if ( $address && is_email( $address ) ) {
				$valid[] = $address;
			}
		}
		return array_values( array_unique( $valid ) );
	}

	/**
	 * Strip line breaks to prevent header injection.
	 *
	 * @param string $value Value.
	 * @return string
	 */
	public function single_line( $value ) {
		return trim( (string) preg_replace( '/\s*(?:\r|\n|%0a|%0d)+\s*/i', ' ', (string) $value ) );
	}

	/**
	 * Minimal HTML email layout.
	 *
	 * @param string $body    HTML body.
	 * @param string $subject Subject.
	 * @return string
	 */
	private function wrap( $body, $subject ) {
		return '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>' . esc_html( $subject ) . '</title></head>'
			. '<body style="margin:0;padding:24px;background:#f4f4f5;font-family:-apple-system,BlinkMacSystemFont,Segoe UI,Roboto,Helvetica,Arial,sans-serif;font-size:15px;color:#1f2937;">'
			. '<div style="max-width:600px;margin:0 auto;background:#ffffff;border-radius:8px;padding:24px;">' . $body . '</div>'
			. '</body></html>';
	}
}

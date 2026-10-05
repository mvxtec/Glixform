<?php
/**
 * Sends notification emails.
 *
 * @package Glixform
 */

namespace Glixform\Notifications;

use Glixform\Forms\ConditionalLogic;

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
	 * Send every enabled notification whose conditions match.
	 *
	 * @param array $form    Form (from FormRepository::get()).
	 * @param array $context Smart tag context, plus logic_values (field_id => value).
	 * @return int Number of emails wp_mail() accepted.
	 */
	public function send( array $form, array $context ) {
		$sent = 0;
		foreach ( (array) $form['data']['settings']['notifications'] as $notification ) {
			if ( empty( $notification['enabled'] ) ) {
				continue;
			}
			if ( ! ConditionalLogic::evaluate( (array) $notification['conditional'], (array) ( $context['logic_values'] ?? array() ) ) ) {
				continue;
			}
			if ( $this->send_one( $form, $notification, $context ) ) {
				++$sent;
			}
		}
		return $sent;
	}

	/**
	 * Send one notification.
	 *
	 * @param array $form         Form.
	 * @param array $notification Notification settings.
	 * @param array $context      Smart tag context.
	 * @return bool
	 */
	public function send_one( array $form, array $notification, array $context ) {
		$to = $this->email_list( $this->smart_tags->process( $notification['to'], $context, 'text' ) );
		if ( ! $to ) {
			return false;
		}

		$subject   = $this->single_line( $this->smart_tags->process( $notification['subject'], $context, 'text' ) );
		$from_name = $this->single_line( $this->smart_tags->process( $notification['from_name'], $context, 'text' ) );
		$reply_to  = $this->email_list( $this->smart_tags->process( $notification['reply_to'], $context, 'text' ) );

		// wpautop() would wrap the {all_fields} table in a <p>, which is invalid HTML.
		$body = str_replace( '<p>{all_fields}</p>', '{all_fields}', wpautop( $notification['message'] ) );
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
		 * Filters a notification email before sending.
		 *
		 * @param array $email        to, subject, message, headers.
		 * @param array $form         Form.
		 * @param array $context      Smart tag context.
		 * @param array $notification Notification settings.
		 */
		$email = apply_filters(
			'glixform_notification_email',
			array(
				'to'      => $to,
				'subject' => $subject,
				'message' => $this->wrap( $body, $subject, $form ),
				'headers' => $headers,
			),
			$form,
			$context,
			$notification
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
	 * Clean, responsive HTML email layout.
	 *
	 * @param string $body    HTML body.
	 * @param string $subject Subject.
	 * @param array  $form    Form.
	 * @return string
	 */
	private function wrap( $body, $subject, array $form = array() ) {
		$site = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
		return '<!DOCTYPE html><html><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>' . esc_html( $subject ) . '</title></head>'
			. '<body style="margin:0;padding:0;background:#f3f4f8;font-family:-apple-system,BlinkMacSystemFont,\'Segoe UI\',Roboto,Helvetica,Arial,sans-serif;color:#111827;">'
			. '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f3f4f8;padding:32px 12px;"><tr><td align="center">'
			. '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:600px;">'
			. '<tr><td style="padding:0 4px 16px;font-size:13px;color:#6b7280;">' . esc_html( $site ) . '</td></tr>'
			. '<tr><td style="background:#ffffff;border-radius:14px;padding:8px 28px 24px;box-shadow:0 1px 3px rgba(16,24,40,.08);border-top:4px solid #6d4aff;">'
			. '<h1 style="font-size:18px;line-height:1.4;margin:20px 0 4px;color:#111827;">' . esc_html( $subject ) . '</h1>'
			. ( empty( $form['title'] ) ? '' : '<div style="font-size:13px;color:#6b7280;margin-bottom:8px;">' . esc_html( $form['title'] ) . '</div>' )
			. '<div style="font-size:15px;line-height:1.6;">' . $body . '</div>'
			. '</td></tr>'
			. '<tr><td style="padding:16px 4px;font-size:12px;color:#9ca3af;text-align:center;">' . esc_html__( 'Sent by Glixform', 'glixform' ) . '</td></tr>'
			. '</table></td></tr></table></body></html>';
	}
}

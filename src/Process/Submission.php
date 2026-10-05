<?php
/**
 * Processes a form submission.
 *
 * @package Glixform
 */

namespace Glixform\Process;

use Glixform\Database\EntryRepository;
use Glixform\Fields\FieldRegistry;
use Glixform\Forms\FormRepository;
use Glixform\Notifications\Mailer;
use Glixform\Notifications\SmartTags;
use Glixform\Plugin;

defined( 'ABSPATH' ) || exit;

/**
 * Pipeline: load form → spam checks → sanitize → validate → save entry →
 * send notification → build confirmation. Each step has a hook.
 */
class Submission {

	/**
	 * Forms.
	 *
	 * @var FormRepository
	 */
	private $forms;

	/**
	 * Field types.
	 *
	 * @var FieldRegistry
	 */
	private $fields;

	/**
	 * Entries.
	 *
	 * @var EntryRepository
	 */
	private $entries;

	/**
	 * Mailer.
	 *
	 * @var Mailer
	 */
	private $mailer;

	/**
	 * Smart tags.
	 *
	 * @var SmartTags
	 */
	private $smart_tags;

	/**
	 * Constructor.
	 *
	 * @param FormRepository  $forms      Forms.
	 * @param FieldRegistry   $fields     Field types.
	 * @param EntryRepository $entries    Entries.
	 * @param Mailer          $mailer     Mailer.
	 * @param SmartTags       $smart_tags Smart tags.
	 */
	public function __construct( FormRepository $forms, FieldRegistry $fields, EntryRepository $entries, Mailer $mailer, SmartTags $smart_tags ) {
		$this->forms      = $forms;
		$this->fields     = $fields;
		$this->entries    = $entries;
		$this->mailer     = $mailer;
		$this->smart_tags = $smart_tags;
	}

	/**
	 * Process submitted data.
	 *
	 * @param array $data Unslashed $_POST['glixform'].
	 * @return array {
	 *     @type bool   $success      Whether the submission was accepted.
	 *     @type int    $form_id      Form ID.
	 *     @type string $message      General error message.
	 *     @type array  $errors       field_id => error message.
	 *     @type array  $values       field_id => sanitized value (for re-display).
	 *     @type array  $confirmation type, message (HTML), url.
	 *     @type int    $entry_id     Saved entry ID (0 if not stored).
	 * }
	 */
	public function process( array $data ) {
		$form_id = absint( $data['form_id'] ?? 0 );
		$result  = array(
			'success'      => false,
			'form_id'      => $form_id,
			'message'      => '',
			'errors'       => array(),
			'values'       => array(),
			'confirmation' => array(),
			'entry_id'     => 0,
		);

		$form = $this->forms->get( $form_id, true );
		if ( ! $form ) {
			$result['message'] = __( 'This form is no longer available.', 'glixform' );
			return $result;
		}

		/**
		 * Fires before a submission is processed.
		 *
		 * @param array $form Form.
		 * @param array $data Raw submitted data.
		 */
		do_action( 'glixform_process_before', $form, $data );

		// Honeypot: pretend everything went fine so bots learn nothing.
		if ( AntiSpam::honeypot_triggered( $data ) ) {
			$result['success']      = true;
			$result['confirmation'] = $this->confirmation( $form, array() );
			return $result;
		}

		if ( ! AntiSpam::verify_token( $form_id, (string) ( $data['token'] ?? '' ) ) ) {
			$result['message'] = __( 'The form could not be submitted. Please wait a moment and try again.', 'glixform' );
			return $result;
		}

		$submitted = isset( $data['fields'] ) && is_array( $data['fields'] ) ? $data['fields'] : array();
		$snapshot  = array();

		foreach ( $form['data']['fields'] as $field ) {
			$type = $this->fields->get( $field['type'] );
			if ( ! $type ) {
				continue;
			}

			$raw   = $submitted[ $field['id'] ] ?? ( $type->is_multiple() ? array() : '' );
			$value = $type->sanitize_value( $field, $raw );
			$error = $type->validate( $field, $value );

			/**
			 * Filters a field's validation error. Return a non-empty string to reject the value.
			 *
			 * @param string       $error Error message ('' when valid).
			 * @param array        $field Field config.
			 * @param string|array $value Sanitized value.
			 * @param array        $form  Form.
			 */
			$error = (string) apply_filters( 'glixform_validate_field', $error, $field, $value, $form );

			$result['values'][ $field['id'] ] = $value;
			if ( '' !== $error ) {
				$result['errors'][ $field['id'] ] = $error;
			}

			$snapshot[] = array(
				'id'    => (int) $field['id'],
				'type'  => $field['type'],
				'label' => $field['label'],
				'value' => $value,
			);
		}

		/**
		 * Filters all validation errors for a submission.
		 *
		 * @param array $errors   field_id => message.
		 * @param array $snapshot Submitted fields.
		 * @param array $form     Form.
		 */
		$result['errors'] = (array) apply_filters( 'glixform_validation_errors', $result['errors'], $snapshot, $form );

		if ( $result['errors'] ) {
			$result['message'] = __( 'Please correct the errors below and submit again.', 'glixform' );
			return $result;
		}

		$page_url = esc_url_raw( (string) ( $data['page_url'] ?? '' ) );
		$entry_id = 0;

		if ( ! empty( $form['data']['settings']['store_entries'] ) ) {
			$store_ip = (bool) Plugin::setting( 'store_ip', true );
			$entry_id = $this->entries->insert(
				$form_id,
				$snapshot,
				array(
					'user_id'    => get_current_user_id(),
					'ip_address' => $store_ip ? $this->client_ip() : '',
					'user_agent' => $store_ip ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ?? '' ) ) : '', // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
					'page_url'   => $page_url,
				)
			);

			if ( $entry_id ) {
				/**
				 * Fires after an entry is saved.
				 *
				 * @param int   $entry_id Entry ID.
				 * @param array $snapshot Submitted fields.
				 * @param array $form     Form.
				 */
				do_action( 'glixform_entry_saved', $entry_id, $snapshot, $form );
			}
		}

		$context = array(
			'form'     => $form,
			'fields'   => $snapshot,
			'entry_id' => $entry_id,
			'page_url' => $page_url,
		);

		$this->mailer->send( $form, $context );

		/**
		 * Fires after a submission is fully processed. Integrations hook here.
		 *
		 * @param array $snapshot Submitted fields.
		 * @param array $form     Form.
		 * @param int   $entry_id Entry ID (0 when entries are not stored).
		 */
		do_action( 'glixform_process_complete', $snapshot, $form, $entry_id );

		$result['success']      = true;
		$result['entry_id']     = $entry_id;
		$result['confirmation'] = $this->confirmation( $form, $context );

		return $result;
	}

	/**
	 * Build the confirmation response.
	 *
	 * @param array $form    Form.
	 * @param array $context Smart tag context.
	 * @return array type, message, url.
	 */
	private function confirmation( array $form, array $context ) {
		$settings = $form['data']['settings']['confirmation'];
		$url      = '';

		if ( 'redirect' === $settings['type'] && $settings['url'] ) {
			$url = $settings['url'];
		} elseif ( 'page' === $settings['type'] && $settings['page_id'] ) {
			$url = (string) get_permalink( $settings['page_id'] );
		}

		if ( $url ) {
			return array(
				'type'    => 'redirect',
				'url'     => $url,
				'message' => '',
			);
		}

		$context = $context ? $context : array( 'form' => $form );

		return array(
			'type'    => 'message',
			'url'     => '',
			'message' => wp_kses_post( wpautop( $this->smart_tags->process( $settings['message'], $context, 'html' ) ) ),
		);
	}

	/**
	 * Visitor IP. Only REMOTE_ADDR is trusted; sites behind a proxy can use the filter.
	 *
	 * @return string
	 */
	private function client_ip() {
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		$ip = filter_var( $ip, FILTER_VALIDATE_IP ) ? $ip : '';

		/**
		 * Filters the stored visitor IP address.
		 *
		 * @param string $ip IP address.
		 */
		return (string) apply_filters( 'glixform_client_ip', $ip );
	}
}

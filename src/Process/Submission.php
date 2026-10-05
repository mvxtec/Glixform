<?php
/**
 * Processes a form submission.
 *
 * @package Glixform
 */

namespace Glixform\Process;

use Glixform\Database\EntryRepository;
use Glixform\Fields\FieldRegistry;
use Glixform\Forms\ConditionalLogic;
use Glixform\Forms\FormRepository;
use Glixform\Notifications\Mailer;
use Glixform\Notifications\SmartTags;
use Glixform\Plugin;

defined( 'ABSPATH' ) || exit;

/**
 * Pipeline: load form → honeypot → token → CAPTCHA → sanitize → conditional
 * logic → validate visible fields → Akismet → finalize (move uploads) →
 * save entry → notifications → confirmation. Each step has a hook.
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
	 * @param array $data  Unslashed $_POST['glixform'] plus "captcha_response".
	 * @param array $files field_id => list of upload records (see SubmissionController::files()).
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
	public function process( array $data, array $files = array() ) {
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

		$settings = $form['data']['settings'];

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
			$result['confirmation'] = $this->confirmation( $form, array( 'form' => $form ), array() );
			return $result;
		}

		if ( ! AntiSpam::verify_token( $form_id, (string) ( $data['token'] ?? '' ) ) ) {
			$result['message'] = __( 'The form could not be submitted. Please wait a moment and try again.', 'glixform' );
			return $result;
		}

		if ( ! empty( $settings['captcha'] ) && Captcha::is_configured() && ! Captcha::verify( (string) ( $data['captcha_response'] ?? '' ), $this->client_ip() ) ) {
			$result['message'] = __( 'Please complete the CAPTCHA check and try again.', 'glixform' );
			return $result;
		}

		$submitted = isset( $data['fields'] ) && is_array( $data['fields'] ) ? $data['fields'] : array();

		// 1. Sanitize every input field.
		$values = array();
		$logic  = array();
		$inputs = array();
		foreach ( $form['data']['fields'] as $field ) {
			$type = $this->fields->get( $field['type'] );
			if ( ! $type || ! $type->is_input() ) {
				continue;
			}
			$id = (int) $field['id'];
			if ( 'file' === $field['type'] ) {
				$raw = $files[ $id ] ?? array();
			} else {
				$raw = $submitted[ $id ] ?? ( $type->is_multiple() ? array() : '' );
			}
			$values[ $id ] = $type->sanitize_value( $field, $raw );
			$logic[ $id ]  = $type->logic_value( $field, $values[ $id ] );
			$inputs[ $id ] = $field;
		}

		// 2. Conditional logic: hidden fields are skipped entirely.
		$visible = ConditionalLogic::visibility( array_values( $inputs ), $logic );

		// 3. Validate visible fields.
		foreach ( $inputs as $id => $field ) {
			if ( empty( $visible[ $id ] ) ) {
				continue;
			}
			$type  = $this->fields->get( $field['type'] );
			$error = $type->validate( $field, $values[ $id ] );

			/**
			 * Filters a field's validation error. Return a non-empty string to reject the value.
			 *
			 * @param string       $error Error message ('' when valid).
			 * @param array        $field Field config.
			 * @param string|array $value Sanitized value.
			 * @param array        $form  Form.
			 */
			$error = (string) apply_filters( 'glixform_validate_field', $error, $field, $values[ $id ], $form );

			if ( 'file' !== $field['type'] ) {
				$result['values'][ $id ] = $values[ $id ];
			}
			if ( '' !== $error ) {
				$result['errors'][ $id ] = $error;
			}
		}

		$snapshot = $this->snapshot( $inputs, $visible, $values );

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
		$is_spam  = ! empty( $settings['akismet'] ) && Akismet::is_spam( $form, $snapshot, $page_url );

		// 4. Finalize values (move uploaded files). Spam keeps file names only.
		foreach ( $inputs as $id => $field ) {
			if ( empty( $visible[ $id ] ) || 'file' !== $field['type'] ) {
				continue;
			}
			if ( $is_spam ) {
				$values[ $id ] = array();
				continue;
			}
			$final = $this->fields->get( $field['type'] )->finalize_value( $field, $values[ $id ], $form_id );
			if ( is_wp_error( $final ) ) {
				$result['errors'][ $id ] = $final->get_error_message();
				$result['message']       = __( 'Please correct the errors below and submit again.', 'glixform' );
				return $result;
			}
			$values[ $id ] = $final;
		}

		$snapshot = $this->snapshot( $inputs, $visible, $values );
		$store_ip = (bool) Plugin::setting( 'store_ip', true );
		$entry_id = 0;

		if ( ! empty( $settings['store_entries'] ) || $is_spam ) {
			$entry_id = $this->entries->insert(
				$form_id,
				$snapshot,
				array(
					'status'     => $is_spam ? 'spam' : 'unread',
					'user_id'    => get_current_user_id(),
					'ip_address' => $store_ip ? $this->client_ip() : '',
					'user_agent' => $store_ip && isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '',
					'page_url'   => $page_url,
				)
			);

			if ( $entry_id && ! $is_spam ) {
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
			'form'         => $form,
			'fields'       => $snapshot,
			'entry_id'     => $entry_id,
			'page_url'     => $page_url,
			'page_title'   => sanitize_text_field( (string) ( $data['page_title'] ?? '' ) ),
			'user_ip'      => $this->client_ip(),
			'logic_values' => $logic,
		);

		if ( ! $is_spam ) {
			$this->mailer->send( $form, $context );

			/**
			 * Fires after a submission is fully processed. Integrations hook here.
			 *
			 * @param array $snapshot Submitted fields.
			 * @param array $form     Form.
			 * @param int   $entry_id Entry ID (0 when entries are not stored).
			 */
			do_action( 'glixform_process_complete', $snapshot, $form, $entry_id );
		}

		$result['success']      = true;
		$result['entry_id']     = $is_spam ? 0 : $entry_id;
		$result['confirmation'] = $this->confirmation( $form, $context, $logic );

		return $result;
	}

	/**
	 * Entry snapshot of visible input fields.
	 *
	 * @param array $inputs  field_id => config.
	 * @param array $visible field_id => bool.
	 * @param array $values  field_id => value.
	 * @return array
	 */
	private function snapshot( array $inputs, array $visible, array $values ) {
		$snapshot = array();
		foreach ( $inputs as $id => $field ) {
			if ( empty( $visible[ $id ] ) ) {
				continue;
			}
			$type       = $this->fields->get( $field['type'] );
			$snapshot[] = array(
				'id'        => (int) $id,
				'type'      => $field['type'],
				'label'     => $field['label'],
				'value'     => $values[ $id ],
				'formatted' => $type->format_value( $field, $values[ $id ] ),
			);
		}
		return $snapshot;
	}

	/**
	 * Pick the confirmation: the first one whose conditions match, otherwise
	 * the first one without conditions.
	 *
	 * @param array $form    Form.
	 * @param array $context Smart tag context.
	 * @param array $logic   field_id => logic value.
	 * @return array type, message, url.
	 */
	private function confirmation( array $form, array $context, array $logic ) {
		$chosen   = null;
		$fallback = null;
		foreach ( (array) $form['data']['settings']['confirmations'] as $confirmation ) {
			if ( empty( $confirmation['conditional']['enabled'] ) ) {
				$fallback = $fallback ?? $confirmation;
				continue;
			}
			if ( ConditionalLogic::evaluate( $confirmation['conditional'], $logic ) ) {
				$chosen = $confirmation;
				break;
			}
		}
		$confirmation = $chosen ?? $fallback ?? FormRepository::default_confirmation( 1 );

		$url = '';
		if ( 'redirect' === $confirmation['type'] && $confirmation['url'] ) {
			$url = $confirmation['url'];
		} elseif ( 'page' === $confirmation['type'] && $confirmation['page_id'] ) {
			$url = (string) get_permalink( $confirmation['page_id'] );
		}

		if ( $url ) {
			return array(
				'type'    => 'redirect',
				'url'     => $url,
				'message' => '',
			);
		}

		return array(
			'type'    => 'message',
			'url'     => '',
			'message' => wp_kses_post( wpautop( $this->smart_tags->process( $confirmation['message'], $context, 'html' ) ) ),
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

<?php
/**
 * Renders forms on the front end.
 *
 * @package Glixform
 */

namespace Glixform\Frontend;

use Glixform\Fields\FieldRegistry;
use Glixform\Process\AntiSpam;

defined( 'ABSPATH' ) || exit;

/**
 * Turns a form definition into HTML.
 */
class Renderer {

	/**
	 * Field types.
	 *
	 * @var FieldRegistry
	 */
	private $fields;

	/**
	 * Results of a failed non-AJAX submission, keyed by form ID.
	 *
	 * @var array
	 */
	private $state = array();

	/**
	 * Constructor.
	 *
	 * @param FieldRegistry $fields Field types.
	 */
	public function __construct( FieldRegistry $fields ) {
		$this->fields = $fields;
	}

	/**
	 * Remember a failed submission so the form re-renders with errors and values.
	 *
	 * @param int   $form_id Form ID.
	 * @param array $result  Result from Submission::process().
	 */
	public function set_state( $form_id, array $result ) {
		$this->state[ (int) $form_id ] = $result;
	}

	/**
	 * Render a form.
	 *
	 * @param array $form Form from FormRepository::get().
	 * @param array $args title (bool): show the form title.
	 * @return string
	 */
	public function render( array $form, array $args = array() ) {
		Assets::enqueue();

		$form_id = (int) $form['id'];
		$html_id = 'glixform-' . $form_id;

		$confirmation = $this->pending_confirmation( $form_id );
		if ( null !== $confirmation ) {
			return sprintf(
				'<div class="glixform-container" id="%s"><div class="glixform-confirmation" role="status" tabindex="-1">%s</div></div>',
				esc_attr( $html_id ),
				wp_kses_post( $confirmation )
			);
		}

		$state    = $this->state[ $form_id ] ?? array();
		$values   = $state['values'] ?? array();
		$errors   = $state['errors'] ?? array();
		$message  = $state['message'] ?? '';
		$settings = $form['data']['settings'];

		$html  = sprintf( '<div class="glixform-container" id="%s">', esc_attr( $html_id ) );
		$html .= sprintf(
			'<form class="glixform" method="post" action="%s" data-form-id="%d">',
			esc_url( $this->current_url() ),
			$form_id
		);

		if ( ! empty( $args['title'] ) ) {
			$html .= sprintf( '<h3 class="glixform-title">%s</h3>', esc_html( $form['title'] ) );
		}

		$html .= sprintf(
			'<div class="glixform-notice glixform-notice-error" role="alert"%s>%s</div>',
			$message ? '' : ' hidden',
			esc_html( $message )
		);

		$html .= '<div class="glixform-fields">';
		foreach ( $form['data']['fields'] as $field ) {
			$type = $this->fields->get( $field['type'] );
			if ( ! $type ) {
				continue;
			}
			$value = array_key_exists( $field['id'], $values ) ? $values[ $field['id'] ] : null;
			$html .= $type->render( $field, $form_id, $value, $errors[ $field['id'] ] ?? '' );
		}
		$html .= '</div>';

		// Honeypot: hidden from people (off-screen, not focusable), tempting to bots.
		$html .= sprintf(
			'<div class="glixform-hp" aria-hidden="true"><label for="%1$s-hp">%2$s</label><input type="text" id="%1$s-hp" name="glixform[%3$s]" value="" tabindex="-1" autocomplete="off"></div>',
			esc_attr( $html_id ),
			esc_html__( 'Leave this field empty', 'glixform' ),
			esc_attr( AntiSpam::HONEYPOT_NAME )
		);

		$html .= sprintf( '<input type="hidden" name="glixform[form_id]" value="%d">', $form_id );
		$html .= sprintf( '<input type="hidden" name="glixform[token]" value="%s">', esc_attr( AntiSpam::token( $form_id ) ) );
		$html .= sprintf( '<input type="hidden" name="glixform[page_url]" value="%s">', esc_url( $this->current_url() ) );
		$html .= '<input type="hidden" name="action" value="glixform_submit">';

		$html .= sprintf(
			'<div class="glixform-submit"><button type="submit" class="glixform-button">%s</button></div>',
			esc_html( $settings['submit_text'] )
		);

		$html .= '</form></div>';

		/**
		 * Filters the rendered form HTML.
		 *
		 * @param string $html Form HTML.
		 * @param array  $form Form.
		 */
		return (string) apply_filters( 'glixform_form_html', $html, $form );
	}

	/**
	 * Confirmation message stored by a non-AJAX submission (Post/Redirect/Get).
	 *
	 * @param int $form_id Form ID.
	 * @return string|null
	 */
	private function pending_confirmation( $form_id ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only lookup of a random key.
		$key = isset( $_GET['glixform_confirm'] ) ? sanitize_key( wp_unslash( $_GET['glixform_confirm'] ) ) : '';
		if ( '' === $key ) {
			return null;
		}
		$stored = get_transient( 'glixform_confirm_' . $key );
		if ( ! is_array( $stored ) || (int) $stored['form_id'] !== (int) $form_id ) {
			return null;
		}
		return (string) $stored['message'];
	}

	/**
	 * Current URL without Glixform's own query args.
	 *
	 * @return string
	 */
	private function current_url() {
		$uri = isset( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '/';
		return remove_query_arg( 'glixform_confirm', home_url( $uri ) );
	}
}

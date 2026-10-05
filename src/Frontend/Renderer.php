<?php
/**
 * Renders forms on the front end.
 *
 * @package Glixform
 */

namespace Glixform\Frontend;

use Glixform\Fields\FieldRegistry;
use Glixform\Notifications\SmartTags;
use Glixform\Process\AntiSpam;
use Glixform\Process\Captcha;

defined( 'ABSPATH' ) || exit;

/**
 * Turns a form definition into HTML. Page breaks split the fields into steps;
 * without JavaScript every step shows and the form still works.
 */
class Renderer {

	/**
	 * Field types.
	 *
	 * @var FieldRegistry
	 */
	private $fields;

	/**
	 * Smart tags (for default values).
	 *
	 * @var SmartTags
	 */
	private $smart_tags;

	/**
	 * Results of a failed non-AJAX submission, keyed by form ID.
	 *
	 * @var array
	 */
	private $state = array();

	/**
	 * Constructor.
	 *
	 * @param FieldRegistry  $fields     Field types.
	 * @param SmartTags|null $smart_tags Smart tags.
	 */
	public function __construct( FieldRegistry $fields, ?SmartTags $smart_tags = null ) {
		$this->fields     = $fields;
		$this->smart_tags = $smart_tags ? $smart_tags : new SmartTags();
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
	 * @param array $args title (bool): show the title; preview (bool): builder preview, submissions disabled.
	 * @return string
	 */
	public function render( array $form, array $args = array() ) {
		$preview = ! empty( $args['preview'] );
		if ( ! $preview ) {
			Assets::enqueue();
		}

		$form_id = (int) $form['id'];
		$html_id = 'glixform-' . $form_id;

		$confirmation = $preview ? null : $this->pending_confirmation( $form_id );
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
		$pages    = $this->pages( $form['data']['fields'] );
		$count    = count( $pages );
		$has_file = (bool) array_filter(
			$form['data']['fields'],
			static function ( $field ) {
				return 'file' === $field['type'];
			}
		);

		$classes = array( 'glixform' );
		if ( $count > 1 ) {
			$classes[] = 'glixform-multipage';
		}

		$html  = sprintf( '<div class="glixform-container" id="%s">', esc_attr( $html_id ) );
		$html .= sprintf(
			'<form class="%s" method="post" action="%s" data-form-id="%d"%s%s>',
			esc_attr( implode( ' ', $classes ) ),
			esc_url( $this->current_url() ),
			$form_id,
			$has_file ? ' enctype="multipart/form-data"' : '',
			$preview ? ' data-preview="1"' : ''
		);

		if ( ! empty( $args['title'] ) ) {
			$html .= sprintf( '<h3 class="glixform-title">%s</h3>', esc_html( $form['title'] ) );
		}

		$html .= sprintf(
			'<div class="glixform-notice glixform-notice-error" role="alert"%s>%s</div>',
			$message ? '' : ' hidden',
			esc_html( $message )
		);

		if ( $count > 1 && 'none' !== $settings['progress'] ) {
			$html .= $this->progress( $pages, $settings['progress'] );
		}

		foreach ( $pages as $index => $page ) {
			$html .= sprintf(
				'<div class="glixform-page" data-page="%d" data-title="%s">',
				$index,
				esc_attr( $page['title'] )
			);
			$html .= '<div class="glixform-fields">';
			foreach ( $page['fields'] as $field ) {
				$type = $this->fields->get( $field['type'] );
				if ( ! $type ) {
					continue;
				}
				$field = $this->prepare_field( $field );
				$value = array_key_exists( $field['id'], $values ) ? $values[ $field['id'] ] : null;
				$html .= $type->render( $field, $form_id, $value, $errors[ $field['id'] ] ?? '' );
			}
			$html .= '</div>';

			if ( $count > 1 ) {
				$html .= '<div class="glixform-page-nav" hidden>';
				if ( $index > 0 ) {
					$html .= sprintf( '<button type="button" class="glixform-button glixform-button-secondary glixform-prev">%s</button>', esc_html( $page['prev_text'] ) );
				}
				if ( $index < $count - 1 ) {
					$html .= sprintf( '<button type="button" class="glixform-button glixform-next">%s</button>', esc_html( $pages[ $index + 1 ]['next_text_before'] ) );
				}
				$html .= '</div>';
			}
			$html .= '</div>';
		}

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
		$html .= sprintf( '<input type="hidden" name="glixform[page_title]" value="%s">', esc_attr( wp_strip_all_tags( (string) get_the_title() ) ) );
		$html .= '<input type="hidden" name="action" value="glixform_submit">';

		$html .= '<div class="glixform-submit">';
		if ( ! empty( $settings['captcha'] ) && Captcha::is_configured() ) {
			$html .= $preview ? '<div class="glixform-captcha-placeholder">' . esc_html__( 'CAPTCHA appears here', 'glixform' ) . '</div>' : Captcha::render();
		}
		if ( $count > 1 ) {
			$html .= sprintf( '<button type="button" class="glixform-button glixform-button-secondary glixform-prev glixform-prev-last" hidden>%s</button>', esc_html( $pages[ $count - 1 ]['prev_text'] ) );
		}
		$html .= sprintf(
			'<button type="submit" class="glixform-button glixform-button-primary" data-processing="%s"><span class="glixform-button-label">%s</span></button>',
			esc_attr( $settings['processing_text'] ),
			esc_html( $settings['submit_text'] )
		);
		$html .= '</div>';

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
	 * Split fields into pages at page breaks.
	 *
	 * @param array $fields Field configs.
	 * @return array[] title, fields, prev_text, next_text_before (label of the button leading to this page).
	 */
	public function pages( array $fields ) {
		$pages   = array();
		$current = array(
			'title'            => '',
			'fields'           => array(),
			'prev_text'        => '',
			'next_text_before' => '',
		);
		foreach ( $fields as $field ) {
			if ( 'pagebreak' === $field['type'] ) {
				$pages[] = $current;
				$current = array(
					'title'            => (string) $field['label'],
					'fields'           => array(),
					'prev_text'        => (string) ( $field['prev_text'] ?? __( 'Previous', 'glixform' ) ),
					'next_text_before' => (string) ( $field['next_text'] ?? __( 'Next', 'glixform' ) ),
				);
				continue;
			}
			$current['fields'][] = $field;
		}
		$pages[] = $current;

		// Drop empty pages (e.g. a page break at the very start or end).
		$pages = array_values(
			array_filter(
				$pages,
				static function ( $page ) {
					return (bool) $page['fields'];
				}
			)
		);
		return $pages ? $pages : array(
			array(
				'title'            => '',
				'fields'           => array(),
				'prev_text'        => '',
				'next_text_before' => '',
			),
		);
	}

	/**
	 * Progress indicator; frontend.js updates it. Hidden without JavaScript.
	 *
	 * @param array  $pages Pages.
	 * @param string $style "bar" or "steps".
	 * @return string
	 */
	private function progress( array $pages, $style ) {
		$count = count( $pages );
		$html  = sprintf( '<div class="glixform-progress glixform-progress-%s" data-count="%d" hidden>', esc_attr( $style ), $count );

		if ( 'steps' === $style ) {
			$html .= '<ol class="glixform-steps">';
			foreach ( $pages as $index => $page ) {
				$html .= sprintf(
					'<li class="glixform-step" data-step="%d"><span class="glixform-step-number">%d</span><span class="glixform-step-title">%s</span></li>',
					$index,
					$index + 1,
					/* translators: %d: step number. */
					esc_html( '' !== $page['title'] ? $page['title'] : sprintf( __( 'Step %d', 'glixform' ), $index + 1 ) )
				);
			}
			return $html . '</ol></div>';
		}

		$html .= sprintf(
			'<div class="glixform-progress-text"><span class="glixform-progress-label" data-template="%s"></span><span class="glixform-progress-title"></span></div>',
			/* translators: 1: current step, 2: number of steps. Keep the placeholders. */
			esc_attr__( 'Step {current} of {total}', 'glixform' )
		);
		return $html . '<div class="glixform-progress-track" role="progressbar" aria-valuemin="1" aria-valuemax="' . (int) $count . '"><span class="glixform-progress-fill"></span></div></div>';
	}

	/**
	 * Resolve smart tags in default values.
	 *
	 * @param array $field Field config.
	 * @return array
	 */
	private function prepare_field( array $field ) {
		if ( isset( $field['default_value'] ) && is_string( $field['default_value'] ) && false !== strpos( $field['default_value'], '{' ) ) {
			$field['default_value'] = $this->smart_tags->process(
				$field['default_value'],
				array(
					'page_url'   => $this->current_url(),
					'page_title' => wp_strip_all_tags( (string) get_the_title() ),
				),
				'text'
			);
		}
		return $field;
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

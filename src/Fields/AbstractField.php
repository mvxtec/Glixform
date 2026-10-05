<?php
/**
 * Base class for all field types.
 *
 * @package Glixform
 */

namespace Glixform\Fields;

defined( 'ABSPATH' ) || exit;

/**
 * A field type knows how to describe its builder options, render itself,
 * and validate, sanitize and format submitted values.
 *
 * A field "config" is the array stored in the form JSON, for example:
 * [ 'id' => 3, 'type' => 'email', 'label' => 'Email', 'required' => true ].
 */
abstract class AbstractField {

	/**
	 * Machine name, e.g. "text".
	 *
	 * @return string
	 */
	abstract public function type();

	/**
	 * Human-readable name shown in the builder.
	 *
	 * @return string
	 */
	abstract public function name();

	/**
	 * Render the input element(s) only; the wrapper, label and errors come from render().
	 *
	 * @param array        $field Field config.
	 * @param string|array $value Current value.
	 * @param array        $attrs Shared attributes: id, name, aria-describedby.
	 * @return string
	 */
	abstract protected function render_input( array $field, $value, array $attrs );

	/**
	 * Dashicon slug for the builder button.
	 *
	 * @return string
	 */
	public function icon() {
		return 'dashicons-editor-textcolor';
	}

	/**
	 * Builder options this field supports, in display order.
	 * Known keys: label, description, required, placeholder, default_value,
	 * choices, max_length, min, max, step.
	 *
	 * @return string[]
	 */
	public function options() {
		return array( 'label', 'description', 'required', 'placeholder', 'default_value' );
	}

	/**
	 * Default config for a newly added field.
	 *
	 * @return array
	 */
	public function defaults() {
		return array(
			'label'         => $this->name(),
			'description'   => '',
			'required'      => false,
			'placeholder'   => '',
			'default_value' => '',
		);
	}

	/**
	 * Whether the field offers a fixed list of choices.
	 *
	 * @return bool
	 */
	public function has_choices() {
		return in_array( 'choices', $this->options(), true );
	}

	/**
	 * Whether the submitted value is an array (e.g. checkboxes).
	 *
	 * @return bool
	 */
	public function is_multiple() {
		return false;
	}

	/**
	 * Clean a config coming from the builder. Only keys listed in options() survive.
	 *
	 * @param array $config Raw config.
	 * @return array
	 */
	public function sanitize_config( array $config ) {
		$clean = array(
			'id'   => absint( $config['id'] ?? 0 ),
			'type' => $this->type(),
		);

		foreach ( $this->options() as $option ) {
			$raw = $config[ $option ] ?? ( $this->defaults()[ $option ] ?? '' );

			switch ( $option ) {
				case 'label':
				case 'placeholder':
					$clean[ $option ] = sanitize_text_field( (string) $raw );
					break;
				case 'description':
					$clean[ $option ] = wp_kses_post( (string) $raw );
					break;
				case 'default_value':
					$clean[ $option ] = sanitize_textarea_field( (string) $raw );
					break;
				case 'required':
					$clean[ $option ] = (bool) $raw;
					break;
				case 'max_length':
					$clean[ $option ] = absint( $raw );
					break;
				case 'min':
				case 'max':
				case 'step':
					$clean[ $option ] = is_numeric( $raw ) ? (string) ( 0 + $raw ) : '';
					break;
				case 'choices':
					$clean[ $option ] = $this->sanitize_choices( $raw );
					break;
			}
		}

		if ( isset( $clean['label'] ) && '' === $clean['label'] ) {
			$clean['label'] = $this->name();
		}

		return $clean;
	}

	/**
	 * Clean a list of choices: [ [ 'label' => 'Red' ], ... ].
	 *
	 * @param mixed $choices Raw choices.
	 * @return array
	 */
	protected function sanitize_choices( $choices ) {
		$clean = array();
		foreach ( (array) $choices as $choice ) {
			$label = sanitize_text_field( (string) ( is_array( $choice ) ? ( $choice['label'] ?? '' ) : $choice ) );
			if ( '' !== $label ) {
				$clean[] = array(
					'label'   => $label,
					'default' => is_array( $choice ) && ! empty( $choice['default'] ),
				);
			}
		}
		return $clean;
	}

	/**
	 * Full field HTML: wrapper, label, input, description and error.
	 *
	 * @param array             $field   Field config.
	 * @param int               $form_id Form ID.
	 * @param string|array|null $value   Submitted value, or null to use the default.
	 * @param string            $error   Validation error message.
	 * @return string
	 */
	public function render( array $field, $form_id, $value = null, $error = '' ) {
		$html_id = sprintf( 'glixform-%d-field-%d', $form_id, $field['id'] );
		$desc_id = $html_id . '-description';
		$err_id  = $html_id . '-error';

		if ( null === $value ) {
			$value = $this->default_value( $field );
		}

		$described = array();
		if ( ! empty( $field['description'] ) ) {
			$described[] = $desc_id;
		}
		if ( $error ) {
			$described[] = $err_id;
		}

		$attrs = array(
			'id'               => $html_id,
			'name'             => sprintf( 'glixform[fields][%d]', $field['id'] ),
			'aria-describedby' => implode( ' ', $described ),
			'error'            => (bool) $error,
		);

		$classes = array( 'glixform-field', 'glixform-field-' . $this->type() );
		if ( ! empty( $field['required'] ) ) {
			$classes[] = 'glixform-field-required';
		}
		if ( $error ) {
			$classes[] = 'glixform-has-error';
		}

		$html  = sprintf( '<div class="%s" data-field-id="%d">', esc_attr( implode( ' ', $classes ) ), (int) $field['id'] );
		$html .= $this->render_label( $field, $html_id );
		$html .= $this->render_input( $field, $value, $attrs );

		if ( ! empty( $field['description'] ) ) {
			$html .= sprintf( '<div class="glixform-description" id="%s">%s</div>', esc_attr( $desc_id ), wp_kses_post( $field['description'] ) );
		}

		$html .= sprintf(
			'<div class="glixform-error" id="%s" role="alert"%s>%s</div>',
			esc_attr( $err_id ),
			$error ? '' : ' hidden',
			esc_html( $error )
		);

		return $html . '</div>';
	}

	/**
	 * Label markup. Group fields (radio/checkbox) override this with a legend.
	 *
	 * @param array  $field   Field config.
	 * @param string $html_id Input ID.
	 * @return string
	 */
	protected function render_label( array $field, $html_id ) {
		return sprintf(
			'<label class="glixform-label" for="%s">%s%s</label>',
			esc_attr( $html_id ),
			esc_html( $field['label'] ),
			$this->required_marker( $field )
		);
	}

	/**
	 * Asterisk for required fields.
	 *
	 * @param array $field Field config.
	 * @return string
	 */
	protected function required_marker( array $field ) {
		if ( empty( $field['required'] ) ) {
			return '';
		}
		return ' <span class="glixform-required" aria-hidden="true">*</span>';
	}

	/**
	 * Shared HTML attributes for a single input element.
	 *
	 * @param array $field Field config.
	 * @param array $attrs Attributes from render().
	 * @return string
	 */
	protected function common_attributes( array $field, array $attrs ) {
		$out = sprintf( ' id="%s" name="%s"', esc_attr( $attrs['id'] ), esc_attr( $attrs['name'] ) );
		if ( $attrs['aria-describedby'] ) {
			$out .= sprintf( ' aria-describedby="%s"', esc_attr( $attrs['aria-describedby'] ) );
		}
		if ( ! empty( $field['required'] ) ) {
			$out .= ' required aria-required="true"';
		}
		if ( ! empty( $attrs['error'] ) ) {
			$out .= ' aria-invalid="true"';
		}
		if ( ! empty( $field['placeholder'] ) ) {
			$out .= sprintf( ' placeholder="%s"', esc_attr( $field['placeholder'] ) );
		}
		return $out;
	}

	/**
	 * Initial value before anything is submitted.
	 *
	 * @param array $field Field config.
	 * @return string|array
	 */
	public function default_value( array $field ) {
		return (string) ( $field['default_value'] ?? '' );
	}

	/**
	 * Normalize raw request input into the field's value shape.
	 *
	 * @param array $field Field config.
	 * @param mixed $raw   Raw value from the request (already unslashed).
	 * @return string|array
	 */
	public function sanitize_value( array $field, $raw ) {
		if ( is_array( $raw ) ) {
			return '';
		}
		return sanitize_text_field( (string) $raw );
	}

	/**
	 * Validate a sanitized value.
	 *
	 * @param array        $field Field config.
	 * @param string|array $value Sanitized value.
	 * @return string Error message, or empty string when valid.
	 */
	public function validate( array $field, $value ) {
		if ( ! empty( $field['required'] ) && $this->is_empty( $value ) ) {
			return __( 'This field is required.', 'glixform' );
		}
		if ( ! empty( $field['max_length'] ) && ! is_array( $value ) && mb_strlen( $value ) > (int) $field['max_length'] ) {
			/* translators: %d: maximum number of characters. */
			return sprintf( __( 'Please enter no more than %d characters.', 'glixform' ), (int) $field['max_length'] );
		}
		return '';
	}

	/**
	 * Whether a value counts as empty.
	 *
	 * @param string|array $value Value.
	 * @return bool
	 */
	public function is_empty( $value ) {
		return is_array( $value ) ? array() === array_filter( $value, 'strlen' ) : '' === trim( (string) $value );
	}

	/**
	 * Plain-text representation used in emails, the entries list and CSV export.
	 *
	 * @param array        $field Field config.
	 * @param string|array $value Stored value.
	 * @return string
	 */
	public function format_value( array $field, $value ) {
		return is_array( $value ) ? implode( "\n", $value ) : (string) $value;
	}

	/**
	 * Data passed to the builder JavaScript.
	 *
	 * @return array
	 */
	public function to_builder_array() {
		return array(
			'type'     => $this->type(),
			'name'     => $this->name(),
			'icon'     => $this->icon(),
			'options'  => $this->options(),
			'defaults' => $this->defaults(),
		);
	}
}

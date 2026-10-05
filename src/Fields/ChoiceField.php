<?php
/**
 * Base for fields with a fixed list of choices.
 *
 * @package Glixform
 */

namespace Glixform\Fields;

defined( 'ABSPATH' ) || exit;

/**
 * Shared choice handling: only configured choices are accepted.
 */
abstract class ChoiceField extends AbstractField {

	/**
	 * {@inheritDoc}
	 */
	public function options() {
		return array( 'label', 'description', 'required', 'choices' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function defaults() {
		return array(
			'label'       => $this->name(),
			'description' => '',
			'required'    => false,
			'choices'     => array(
				array(
					'label'   => __( 'First Choice', 'glixform' ),
					'default' => false,
				),
				array(
					'label'   => __( 'Second Choice', 'glixform' ),
					'default' => false,
				),
				array(
					'label'   => __( 'Third Choice', 'glixform' ),
					'default' => false,
				),
			),
		);
	}

	/**
	 * Labels of all configured choices.
	 *
	 * @param array $field Field config.
	 * @return string[]
	 */
	protected function choice_labels( array $field ) {
		return array_map(
			static function ( $choice ) {
				return (string) $choice['label'];
			},
			(array) ( $field['choices'] ?? array() )
		);
	}

	/**
	 * Choices marked as selected by default.
	 *
	 * @param array $field Field config.
	 * @return string|array
	 */
	public function default_value( array $field ) {
		$defaults = array();
		foreach ( (array) ( $field['choices'] ?? array() ) as $choice ) {
			if ( ! empty( $choice['default'] ) ) {
				$defaults[] = (string) $choice['label'];
			}
		}
		if ( $this->is_multiple() ) {
			return $defaults;
		}
		return $defaults ? $defaults[0] : '';
	}

	/**
	 * Overrides the parent implementation.
	 *
	 * @param array $field Field config.
	 * @param mixed $raw   Raw value from the request.
	 * @return string|array
	 */
	public function sanitize_value( array $field, $raw ) {
		if ( $this->is_multiple() ) {
			return array_values( array_map( 'sanitize_text_field', array_filter( (array) $raw, 'is_string' ) ) );
		}
		return is_array( $raw ) ? '' : sanitize_text_field( (string) $raw );
	}

	/**
	 * Overrides the parent implementation.
	 *
	 * @param array        $field Field config.
	 * @param string|array $value Value.
	 * @return string
	 */
	public function validate( array $field, $value ) {
		$error = parent::validate( $field, $value );
		if ( '' !== $error ) {
			return $error;
		}
		$allowed = $this->choice_labels( $field );
		foreach ( (array) $value as $item ) {
			if ( '' !== $item && ! in_array( $item, $allowed, true ) ) {
				return __( 'Please select a valid option.', 'glixform' );
			}
		}
		return '';
	}

	/**
	 * Fieldset legend for radio/checkbox groups.
	 *
	 * @param array $field Field config.
	 * @return string
	 */
	protected function render_legend( array $field ) {
		return sprintf(
			'<legend class="glixform-label">%s%s</legend>',
			esc_html( $field['label'] ),
			$this->required_marker( $field )
		);
	}

	/**
	 * Render a radio or checkbox group.
	 *
	 * @param array        $field Field config.
	 * @param string|array $value Value.
	 * @param array        $attrs Attributes.
	 * @param string       $type  "radio" or "checkbox".
	 * @return string
	 */
	protected function render_group( array $field, $value, array $attrs, $type ) {
		$selected = (array) $value;
		$name     = $attrs['name'] . ( 'checkbox' === $type ? '[]' : '' );

		$html = sprintf( '<fieldset class="glixform-choices" id="%s"', esc_attr( $attrs['id'] ) );
		if ( $attrs['aria-describedby'] ) {
			$html .= sprintf( ' aria-describedby="%s"', esc_attr( $attrs['aria-describedby'] ) );
		}
		if ( ! empty( $field['required'] ) && 'radio' === $type ) {
			$html .= ' aria-required="true"';
		}
		$html .= '>' . $this->render_legend( $field ) . '<ul>';

		foreach ( $this->choice_labels( $field ) as $index => $label ) {
			$choice_id = $attrs['id'] . '-' . $index;
			$html     .= sprintf(
				'<li><input type="%1$s" id="%2$s" name="%3$s" value="%4$s"%5$s%6$s> <label for="%2$s">%7$s</label></li>',
				esc_attr( $type ),
				esc_attr( $choice_id ),
				esc_attr( $name ),
				esc_attr( $label ),
				in_array( $label, $selected, true ) ? ' checked' : '',
				( 'radio' === $type && ! empty( $field['required'] ) ) ? ' required' : '',
				esc_html( $label )
			);
		}

		return $html . '</ul></fieldset>';
	}
}

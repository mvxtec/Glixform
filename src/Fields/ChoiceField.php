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
	 * Supported options.
	 *
	 * @return string[]
	 */
	public function options() {
		return array( 'label', 'description', 'required', 'choices', 'css_class' );
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
	 * Sanitize the selection.
	 *
	 * @param array $field Field config.
	 * @param mixed $raw   Raw value.
	 * @return string|array
	 */
	public function sanitize_value( array $field, $raw ) {
		if ( $this->is_multiple() ) {
			return array_values( array_map( 'sanitize_text_field', array_filter( (array) $raw, 'is_string' ) ) );
		}
		return is_array( $raw ) ? '' : sanitize_text_field( (string) $raw );
	}

	/**
	 * Reject anything that is not a configured choice.
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
	 * Lists stay lists for "is"/"is not" checks.
	 *
	 * @param array        $field Field config.
	 * @param string|array $value Value.
	 * @return string|array
	 */
	public function logic_value( array $field, $value ) {
		return $this->is_multiple() ? array_values( (array) $value ) : (string) $value;
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
	 * @param array        $labels Optional labels to render instead of choice labels.
	 * @return string
	 */
	protected function render_group( array $field, $value, array $attrs, $type, array $labels = array() ) {
		$selected = array_map( 'strval', (array) $value );
		$name     = $attrs['name'] . ( 'checkbox' === $type ? '[]' : '' );
		$labels   = $labels ? $labels : $this->choice_labels( $field );

		$html = sprintf( '<fieldset class="glixform-choices glixform-choices-%s" id="%s"', esc_attr( $type ), esc_attr( $attrs['id'] ) );
		if ( ! empty( $attrs['aria-describedby'] ) ) {
			$html .= sprintf( ' aria-describedby="%s"', esc_attr( $attrs['aria-describedby'] ) );
		}
		if ( 'radio' === $type && $this->html_required( $field ) ) {
			$html .= ' aria-required="true"';
		}
		$html .= '>' . $this->render_legend( $field ) . '<ul>';

		foreach ( $labels as $index => $label ) {
			$choice_id = $attrs['id'] . '-' . $index;
			$html     .= sprintf(
				'<li><input type="%1$s" id="%2$s" name="%3$s" value="%4$s"%5$s%6$s><label for="%2$s">%7$s</label></li>',
				esc_attr( $type ),
				esc_attr( $choice_id ),
				esc_attr( $name ),
				esc_attr( $label ),
				in_array( (string) $label, $selected, true ) ? ' checked' : '',
				( 'radio' === $type && $this->html_required( $field ) ) ? ' required' : '',
				esc_html( $label )
			);
		}

		return $html . '</ul></fieldset>';
	}
}

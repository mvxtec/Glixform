<?php
/**
 * Single line text field.
 *
 * @package Glixform
 */

namespace Glixform\Fields;

defined( 'ABSPATH' ) || exit;

/**
 * <input type="text">. Also the base for email, URL, phone and number inputs.
 */
class Text extends AbstractField {

	/**
	 * Machine name.
	 *
	 * @return string
	 */
	public function type() {
		return 'text';
	}

	/**
	 * Display name.
	 *
	 * @return string
	 */
	public function name() {
		return __( 'Single Line Text', 'glixform' );
	}

	/**
	 * Supported options.
	 *
	 * @return string[]
	 */
	public function options() {
		return array( 'label', 'description', 'required', 'placeholder', 'default_value', 'max_length', 'css_class' );
	}

	/**
	 * Input type attribute.
	 *
	 * @return string
	 */
	protected function input_type() {
		return 'text';
	}

	/**
	 * Extra attributes for subclasses (autocomplete, inputmode, min/max...).
	 *
	 * @param array $field Field config.
	 * @return string
	 */
	protected function extra_attributes( array $field ) {
		return '';
	}

	/**
	 * Render the input.
	 *
	 * @param array        $field Field config.
	 * @param string|array $value Value.
	 * @param array        $attrs Shared attributes.
	 * @return string
	 */
	protected function render_input( array $field, $value, array $attrs ) {
		$extra = '';
		if ( ! empty( $field['max_length'] ) ) {
			$extra .= sprintf( ' maxlength="%d"', (int) $field['max_length'] );
		}
		return sprintf(
			'<input type="%s" class="glixform-input"%s%s value="%s">',
			esc_attr( $this->input_type() ),
			$this->common_attributes( $field, $attrs ),
			$extra . $this->extra_attributes( $field ),
			esc_attr( is_array( $value ) ? '' : (string) $value )
		);
	}
}

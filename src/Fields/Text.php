<?php
/**
 * Single line text field.
 *
 * @package Glixform
 */

namespace Glixform\Fields;

defined( 'ABSPATH' ) || exit;

/**
 * <input type="text">.
 */
class Text extends AbstractField {

	/**
	 * {@inheritDoc}
	 */
	public function type() {
		return 'text';
	}

	/**
	 * {@inheritDoc}
	 */
	public function name() {
		return __( 'Single Line Text', 'glixform' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function options() {
		return array( 'label', 'description', 'required', 'placeholder', 'default_value', 'max_length' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function defaults() {
		return parent::defaults() + array( 'max_length' => 0 );
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
	 * Overrides the parent implementation.
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
			esc_attr( (string) $value )
		);
	}

	/**
	 * Additional attributes for subclasses.
	 *
	 * @param array $field Field config.
	 * @return string
	 */
	protected function extra_attributes( array $field ) {
		return '';
	}
}

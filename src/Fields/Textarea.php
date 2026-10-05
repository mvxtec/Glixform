<?php
/**
 * Paragraph text field.
 *
 * @package Glixform
 */

namespace Glixform\Fields;

defined( 'ABSPATH' ) || exit;

/**
 * <textarea>.
 */
class Textarea extends AbstractField {

	/**
	 * {@inheritDoc}
	 */
	public function type() {
		return 'textarea';
	}

	/**
	 * {@inheritDoc}
	 */
	public function name() {
		return __( 'Paragraph Text', 'glixform' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function icon() {
		return 'dashicons-editor-paragraph';
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
	 * Overrides the parent implementation.
	 *
	 * @param array        $field Field config.
	 * @param string|array $value Value.
	 * @param array        $attrs Shared attributes.
	 * @return string
	 */
	protected function render_input( array $field, $value, array $attrs ) {
		$extra = empty( $field['max_length'] ) ? '' : sprintf( ' maxlength="%d"', (int) $field['max_length'] );
		return sprintf(
			'<textarea class="glixform-input" rows="5"%s%s>%s</textarea>',
			$this->common_attributes( $field, $attrs ),
			$extra,
			esc_textarea( (string) $value )
		);
	}

	/**
	 * Keep line breaks.
	 *
	 * @param array $field Field config.
	 * @param mixed $raw   Raw value.
	 * @return string
	 */
	public function sanitize_value( array $field, $raw ) {
		return is_array( $raw ) ? '' : sanitize_textarea_field( (string) $raw );
	}
}

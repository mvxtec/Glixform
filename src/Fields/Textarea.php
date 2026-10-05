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
	 * Machine name.
	 *
	 * @return string
	 */
	public function type() {
		return 'textarea';
	}

	/**
	 * Display name.
	 *
	 * @return string
	 */
	public function name() {
		return __( 'Paragraph Text', 'glixform' );
	}

	/**
	 * Builder icon.
	 *
	 * @return string
	 */
	public function icon() {
		return 'dashicons-editor-paragraph';
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
	 * Default value is multi-line here.
	 *
	 * @return array
	 */
	protected function custom_option_definitions() {
		return array(
			'default_value' => array(
				'type'    => 'textarea',
				'label'   => __( 'Default value', 'glixform' ),
				'default' => '',
				'group'   => 'advanced',
			),
		);
	}

	/**
	 * Render the textarea.
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
			esc_textarea( is_array( $value ) ? '' : (string) $value )
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

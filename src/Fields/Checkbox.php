<?php
/**
 * Checkboxes field.
 *
 * @package Glixform
 */

namespace Glixform\Fields;

defined( 'ABSPATH' ) || exit;

/**
 * Checkbox group; the value is an array of selected labels.
 */
class Checkbox extends ChoiceField {

	/**
	 * {@inheritDoc}
	 */
	public function type() {
		return 'checkbox';
	}

	/**
	 * {@inheritDoc}
	 */
	public function name() {
		return __( 'Checkboxes', 'glixform' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function icon() {
		return 'dashicons-yes-alt';
	}

	/**
	 * {@inheritDoc}
	 */
	public function is_multiple() {
		return true;
	}

	/**
	 * The fieldset legend replaces the label.
	 *
	 * @param array  $field   Field config.
	 * @param string $html_id Input ID.
	 * @return string
	 */
	protected function render_label( array $field, $html_id ) {
		return '';
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
		return $this->render_group( $field, $value, $attrs, 'checkbox' );
	}

	/**
	 * Overrides the parent implementation.
	 *
	 * @param array        $field Field config.
	 * @param string|array $value Value.
	 * @return string
	 */
	public function format_value( array $field, $value ) {
		return implode( ', ', (array) $value );
	}
}

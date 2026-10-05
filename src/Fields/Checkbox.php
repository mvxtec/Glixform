<?php
/**
 * Checkboxes field.
 *
 * @package Glixform
 */

namespace Glixform\Fields;

defined( 'ABSPATH' ) || exit;

/**
 * Checkbox group; the value is a list of selected labels.
 */
class Checkbox extends ChoiceField {

	/**
	 * Machine name.
	 *
	 * @return string
	 */
	public function type() {
		return 'checkbox';
	}

	/**
	 * Display name.
	 *
	 * @return string
	 */
	public function name() {
		return __( 'Checkboxes', 'glixform' );
	}

	/**
	 * Builder icon.
	 *
	 * @return string
	 */
	public function icon() {
		return 'dashicons-yes-alt';
	}

	/**
	 * Selected values are a list.
	 *
	 * @return bool
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
	 * Render the group.
	 *
	 * @param array        $field Field config.
	 * @param string|array $value Value.
	 * @param array        $attrs Shared attributes.
	 * @return string
	 */
	protected function render_input( array $field, $value, array $attrs ) {
		return $this->render_group( $field, $value, $attrs, 'checkbox' );
	}
}

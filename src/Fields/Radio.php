<?php
/**
 * Multiple Choice field.
 *
 * @package Glixform
 */

namespace Glixform\Fields;

defined( 'ABSPATH' ) || exit;

/**
 * Radio button group.
 */
class Radio extends ChoiceField {

	/**
	 * Machine name.
	 *
	 * @return string
	 */
	public function type() {
		return 'radio';
	}

	/**
	 * Display name.
	 *
	 * @return string
	 */
	public function name() {
		return __( 'Multiple Choice', 'glixform' );
	}

	/**
	 * Builder icon.
	 *
	 * @return string
	 */
	public function icon() {
		return 'dashicons-marker';
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
		return $this->render_group( $field, $value, $attrs, 'radio' );
	}
}

<?php
/**
 * Multiple choice (radio) field.
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
	 * {@inheritDoc}
	 */
	public function type() {
		return 'radio';
	}

	/**
	 * {@inheritDoc}
	 */
	public function name() {
		return __( 'Multiple Choice', 'glixform' );
	}

	/**
	 * {@inheritDoc}
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
	 * Overrides the parent implementation.
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

<?php
/**
 * Hidden field.
 *
 * @package Glixform
 */

namespace Glixform\Fields;

defined( 'ABSPATH' ) || exit;

/**
 * <input type="hidden"> carrying a fixed default value.
 */
class Hidden extends AbstractField {

	/**
	 * {@inheritDoc}
	 */
	public function type() {
		return 'hidden';
	}

	/**
	 * {@inheritDoc}
	 */
	public function name() {
		return __( 'Hidden', 'glixform' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function icon() {
		return 'dashicons-hidden';
	}

	/**
	 * {@inheritDoc}
	 */
	public function options() {
		return array( 'label', 'default_value' );
	}

	/**
	 * No visible wrapper or label.
	 *
	 * @param array             $field   Field config.
	 * @param int               $form_id Form ID.
	 * @param string|array|null $value   Value.
	 * @param string            $error   Ignored.
	 * @return string
	 */
	public function render( array $field, $form_id, $value = null, $error = '' ) {
		return sprintf(
			'<input type="hidden" name="%s" value="%s">',
			esc_attr( sprintf( 'glixform[fields][%d]', $field['id'] ) ),
			esc_attr( $this->default_value( $field ) )
		);
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
		return '';
	}
}

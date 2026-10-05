<?php
/**
 * Hidden field.
 *
 * @package Glixform
 */

namespace Glixform\Fields;

defined( 'ABSPATH' ) || exit;

/**
 * <input type="hidden"> carrying a fixed default value (smart tags allowed).
 */
class Hidden extends AbstractField {

	/**
	 * Machine name.
	 *
	 * @return string
	 */
	public function type() {
		return 'hidden';
	}

	/**
	 * Display name.
	 *
	 * @return string
	 */
	public function name() {
		return __( 'Hidden', 'glixform' );
	}

	/**
	 * Builder icon.
	 *
	 * @return string
	 */
	public function icon() {
		return 'dashicons-hidden';
	}

	/**
	 * Palette group.
	 *
	 * @return string
	 */
	public function category() {
		return 'fancy';
	}

	/**
	 * Hidden fields cannot be shown by logic.
	 *
	 * @return bool
	 */
	public function supports_logic() {
		return false;
	}

	/**
	 * Supported options.
	 *
	 * @return string[]
	 */
	public function options() {
		return array( 'label', 'default_value' );
	}

	/**
	 * Default value is a basic option here.
	 *
	 * @return array
	 */
	protected function custom_option_definitions() {
		return array(
			'default_value' => array(
				'type'    => 'text',
				'label'   => __( 'Value', 'glixform' ),
				'help'    => __( 'Smart tags work here, e.g. {query_var key="utm_source"}.', 'glixform' ),
				'default' => '',
			),
		);
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
	 * Unused; see render().
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

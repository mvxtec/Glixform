<?php
/**
 * Section divider.
 *
 * @package Glixform
 */

namespace Glixform\Fields;

defined( 'ABSPATH' ) || exit;

/**
 * A heading with optional text, to group fields.
 */
class Divider extends AbstractField {

	/**
	 * Machine name.
	 *
	 * @return string
	 */
	public function type() {
		return 'divider';
	}

	/**
	 * Display name.
	 *
	 * @return string
	 */
	public function name() {
		return __( 'Section Divider', 'glixform' );
	}

	/**
	 * Builder icon.
	 *
	 * @return string
	 */
	public function icon() {
		return 'dashicons-minus';
	}

	/**
	 * Palette group.
	 *
	 * @return string
	 */
	public function category() {
		return 'layout';
	}

	/**
	 * No value.
	 *
	 * @return bool
	 */
	public function is_input() {
		return false;
	}

	/**
	 * Supported options.
	 *
	 * @return string[]
	 */
	public function options() {
		return array( 'label', 'description', 'css_class' );
	}

	/**
	 * Heading, text and rule.
	 *
	 * @param array             $field   Field config.
	 * @param int               $form_id Form ID.
	 * @param string|array|null $value   Unused.
	 * @param string            $error   Unused.
	 * @return string
	 */
	public function render( array $field, $form_id, $value = null, $error = '' ) {
		$html = $this->open_wrapper( $field ) . sprintf( '<h3 class="glixform-divider-title">%s</h3>', esc_html( $field['label'] ) );
		if ( ! empty( $field['description'] ) ) {
			$html .= sprintf( '<div class="glixform-description">%s</div>', wp_kses_post( $field['description'] ) );
		}
		return $html . '</div>';
	}

	/**
	 * Unused.
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

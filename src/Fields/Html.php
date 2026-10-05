<?php
/**
 * Custom HTML / content block.
 *
 * @package Glixform
 */

namespace Glixform\Fields;

defined( 'ABSPATH' ) || exit;

/**
 * Free content between fields. Filtered with wp_kses_post (no scripts).
 */
class Html extends AbstractField {

	/**
	 * Machine name.
	 *
	 * @return string
	 */
	public function type() {
		return 'html';
	}

	/**
	 * Display name.
	 *
	 * @return string
	 */
	public function name() {
		return __( 'HTML / Content', 'glixform' );
	}

	/**
	 * Builder icon.
	 *
	 * @return string
	 */
	public function icon() {
		return 'dashicons-editor-code';
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
		return array( 'label', 'content', 'css_class' );
	}

	/**
	 * Content option; the label is only shown in the builder.
	 *
	 * @return array
	 */
	protected function custom_option_definitions() {
		return array(
			'label'   => array(
				'type'    => 'text',
				'label'   => __( 'Name in builder', 'glixform' ),
				'help'    => __( 'Not shown on the form.', 'glixform' ),
				'default' => __( 'HTML / Content', 'glixform' ),
			),
			'content' => array(
				'type'    => 'html',
				'label'   => __( 'Content', 'glixform' ),
				'help'    => __( 'HTML is allowed; scripts are removed.', 'glixform' ),
				'default' => '<p>' . __( 'Your text here.', 'glixform' ) . '</p>',
			),
		);
	}

	/**
	 * Output the content.
	 *
	 * @param array             $field   Field config.
	 * @param int               $form_id Form ID.
	 * @param string|array|null $value   Unused.
	 * @param string            $error   Unused.
	 * @return string
	 */
	public function render( array $field, $form_id, $value = null, $error = '' ) {
		return $this->open_wrapper( $field ) . wp_kses_post( (string) ( $field['content'] ?? '' ) ) . '</div>';
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

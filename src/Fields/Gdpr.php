<?php
/**
 * GDPR consent checkbox.
 *
 * @package Glixform
 */

namespace Glixform\Fields;

defined( 'ABSPATH' ) || exit;

/**
 * A single, always-required consent checkbox. The stored value is the exact
 * consent text the visitor agreed to.
 */
class Gdpr extends AbstractField {

	/**
	 * Machine name.
	 *
	 * @return string
	 */
	public function type() {
		return 'gdpr';
	}

	/**
	 * Display name.
	 *
	 * @return string
	 */
	public function name() {
		return __( 'GDPR Consent', 'glixform' );
	}

	/**
	 * Builder icon.
	 *
	 * @return string
	 */
	public function icon() {
		return 'dashicons-shield';
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
	 * Supported options.
	 *
	 * @return string[]
	 */
	public function options() {
		return array( 'label', 'consent_text', 'description', 'css_class' );
	}

	/**
	 * Consent text option.
	 *
	 * @return array
	 */
	protected function custom_option_definitions() {
		return array(
			'label'        => array(
				'type'    => 'text',
				'label'   => __( 'Label', 'glixform' ),
				'default' => __( 'Privacy', 'glixform' ),
			),
			'consent_text' => array(
				'type'    => 'textarea',
				'label'   => __( 'Consent text', 'glixform' ),
				'default' => __( 'I agree that this website stores my submitted information so they can respond to my inquiry.', 'glixform' ),
			),
		);
	}

	/**
	 * Always required.
	 *
	 * @param array $config Raw config.
	 * @return array
	 */
	public function sanitize_config( array $config ) {
		return array( 'required' => true ) + parent::sanitize_config( $config );
	}

	/**
	 * Legend and checkbox.
	 *
	 * @param array  $field   Field config.
	 * @param string $html_id Input ID.
	 * @return string
	 */
	protected function render_label( array $field, $html_id ) {
		return '';
	}

	/**
	 * Render the checkbox.
	 *
	 * @param array        $field Field config.
	 * @param string|array $value Value.
	 * @param array        $attrs Shared attributes.
	 * @return string
	 */
	protected function render_input( array $field, $value, array $attrs ) {
		$field['required'] = true;
		return sprintf(
			'<fieldset class="glixform-choices glixform-choices-checkbox"><legend class="glixform-label">%1$s%2$s</legend><ul><li><input type="checkbox" value="1"%3$s%4$s><label for="%5$s">%6$s</label></li></ul></fieldset>',
			esc_html( $field['label'] ),
			$this->required_marker( $field ),
			$this->common_attributes( $field, $attrs ),
			'' !== $value && array() !== $value ? ' checked' : '',
			esc_attr( $attrs['id'] ),
			esc_html( $field['consent_text'] ?? '' )
		);
	}

	/**
	 * Unchecked by default.
	 *
	 * @param array $field Field config.
	 * @return string
	 */
	public function default_value( array $field ) {
		return '';
	}

	/**
	 * Store the consent text when checked.
	 *
	 * @param array $field Field config.
	 * @param mixed $raw   Raw value.
	 * @return string
	 */
	public function sanitize_value( array $field, $raw ) {
		return '1' === $raw ? (string) ( $field['consent_text'] ?? __( 'Agreed', 'glixform' ) ) : '';
	}

	/**
	 * Must be checked.
	 *
	 * @param array        $field Field config.
	 * @param string|array $value Value.
	 * @return string
	 */
	public function validate( array $field, $value ) {
		return '' === $value ? __( 'Please agree to continue.', 'glixform' ) : '';
	}
}

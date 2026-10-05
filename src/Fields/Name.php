<?php
/**
 * Name field.
 *
 * @package Glixform
 */

namespace Glixform\Fields;

defined( 'ABSPATH' ) || exit;

/**
 * Either one input ("simple") or first and last name inputs.
 */
class Name extends CompositeField {

	/**
	 * Machine name.
	 *
	 * @return string
	 */
	public function type() {
		return 'name';
	}

	/**
	 * Display name.
	 *
	 * @return string
	 */
	public function name() {
		return __( 'Name', 'glixform' );
	}

	/**
	 * Builder icon.
	 *
	 * @return string
	 */
	public function icon() {
		return 'dashicons-admin-users';
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
		return array( 'label', 'description', 'required', 'format', 'css_class' );
	}

	/**
	 * Format option.
	 *
	 * @return array
	 */
	protected function custom_option_definitions() {
		return array(
			'format' => array(
				'type'    => 'select',
				'label'   => __( 'Format', 'glixform' ),
				'default' => 'first-last',
				'choices' => array(
					'first-last' => __( 'First and last name', 'glixform' ),
					'simple'     => __( 'Single input', 'glixform' ),
				),
			),
		);
	}

	/**
	 * Whether first/last inputs are used.
	 *
	 * @param array $field Field config.
	 * @return bool
	 */
	protected function uses_parts( array $field ) {
		return 'simple' !== ( $field['format'] ?? 'first-last' );
	}

	/**
	 * First and last name parts.
	 *
	 * @param array $field Field config.
	 * @return array
	 */
	protected function parts( array $field ) {
		return array(
			'first' => array(
				'label'        => __( 'First', 'glixform' ),
				'autocomplete' => 'given-name',
				'required'     => true,
			),
			'last'  => array(
				'label'        => __( 'Last', 'glixform' ),
				'autocomplete' => 'family-name',
				'required'     => true,
			),
		);
	}

	/**
	 * "First Last".
	 *
	 * @param array $field Field config.
	 * @param array $value Parts.
	 * @return string
	 */
	protected function join_parts( array $field, array $value ) {
		return trim( ( $value['first'] ?? '' ) . ' ' . ( $value['last'] ?? '' ) );
	}

	/**
	 * Single input in "simple" mode.
	 *
	 * @param array        $field Field config.
	 * @param string|array $value Value.
	 * @param array        $attrs Shared attributes.
	 * @return string
	 */
	protected function render_input( array $field, $value, array $attrs ) {
		if ( $this->uses_parts( $field ) ) {
			return parent::render_input( $field, $value, $attrs );
		}
		return sprintf(
			'<input type="text" class="glixform-input" autocomplete="name"%s value="%s">',
			$this->common_attributes( $field, $attrs ),
			esc_attr( is_array( $value ) ? '' : (string) $value )
		);
	}

	/**
	 * String in "simple" mode, parts otherwise.
	 *
	 * @param array $field Field config.
	 * @param mixed $raw   Raw value.
	 * @return string|array
	 */
	public function sanitize_value( array $field, $raw ) {
		if ( $this->uses_parts( $field ) ) {
			return parent::sanitize_value( $field, $raw );
		}
		return is_array( $raw ) ? '' : sanitize_text_field( (string) $raw );
	}

	/**
	 * Validate either mode.
	 *
	 * @param array        $field Field config.
	 * @param string|array $value Value.
	 * @return string
	 */
	public function validate( array $field, $value ) {
		if ( $this->uses_parts( $field ) ) {
			return parent::validate( $field, $value );
		}
		return AbstractField::validate( $field, $value );
	}

	/**
	 * Empty default in parts mode.
	 *
	 * @param array $field Field config.
	 * @return string|array
	 */
	public function default_value( array $field ) {
		return $this->uses_parts( $field ) ? array() : '';
	}
}

<?php
/**
 * Number field.
 *
 * @package Glixform
 */

namespace Glixform\Fields;

defined( 'ABSPATH' ) || exit;

/**
 * <input type="number"> with optional min, max and step.
 */
class Number extends Text {

	/**
	 * Machine name.
	 *
	 * @return string
	 */
	public function type() {
		return 'number';
	}

	/**
	 * Display name.
	 *
	 * @return string
	 */
	public function name() {
		return __( 'Number', 'glixform' );
	}

	/**
	 * Builder icon.
	 *
	 * @return string
	 */
	public function icon() {
		return 'dashicons-calculator';
	}

	/**
	 * Supported options.
	 *
	 * @return string[]
	 */
	public function options() {
		return array( 'label', 'description', 'required', 'min', 'max', 'placeholder', 'default_value', 'step', 'css_class' );
	}

	/**
	 * Input type attribute.
	 *
	 * @return string
	 */
	protected function input_type() {
		return 'number';
	}

	/**
	 * Min, max and step attributes.
	 *
	 * @param array $field Field config.
	 * @return string
	 */
	protected function extra_attributes( array $field ) {
		$out = '';
		foreach ( array( 'min', 'max', 'step' ) as $attr ) {
			if ( isset( $field[ $attr ] ) && '' !== $field[ $attr ] ) {
				$out .= sprintf( ' %s="%s"', $attr, esc_attr( $field[ $attr ] ) );
			}
		}
		if ( ! isset( $field['step'] ) || '' === $field['step'] ) {
			$out .= ' step="any"';
		}
		return $out . ' inputmode="decimal"';
	}

	/**
	 * Validate the number and its range.
	 *
	 * @param array        $field Field config.
	 * @param string|array $value Value.
	 * @return string
	 */
	public function validate( array $field, $value ) {
		$error = parent::validate( $field, $value );
		if ( '' !== $error || '' === $value ) {
			return $error;
		}
		if ( ! is_numeric( $value ) ) {
			return __( 'Please enter a valid number.', 'glixform' );
		}
		if ( isset( $field['min'] ) && '' !== $field['min'] && (float) $value < (float) $field['min'] ) {
			/* translators: %s: minimum value. */
			return sprintf( __( 'Please enter a value of at least %s.', 'glixform' ), $field['min'] );
		}
		if ( isset( $field['max'] ) && '' !== $field['max'] && (float) $value > (float) $field['max'] ) {
			/* translators: %s: maximum value. */
			return sprintf( __( 'Please enter a value no greater than %s.', 'glixform' ), $field['max'] );
		}
		return '';
	}
}

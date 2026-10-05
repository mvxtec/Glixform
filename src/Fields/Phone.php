<?php
/**
 * Phone field.
 *
 * @package Glixform
 */

namespace Glixform\Fields;

defined( 'ABSPATH' ) || exit;

/**
 * <input type="tel"> accepting digits, spaces and + ( ) - . characters.
 */
class Phone extends Text {

	/**
	 * Machine name.
	 *
	 * @return string
	 */
	public function type() {
		return 'phone';
	}

	/**
	 * Display name.
	 *
	 * @return string
	 */
	public function name() {
		return __( 'Phone', 'glixform' );
	}

	/**
	 * Builder icon.
	 *
	 * @return string
	 */
	public function icon() {
		return 'dashicons-phone';
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
		return array( 'label', 'description', 'required', 'placeholder', 'default_value', 'css_class' );
	}

	/**
	 * Input type attribute.
	 *
	 * @return string
	 */
	protected function input_type() {
		return 'tel';
	}

	/**
	 * Extra attributes.
	 *
	 * @param array $field Field config.
	 * @return string
	 */
	protected function extra_attributes( array $field ) {
		return ' autocomplete="tel" inputmode="tel"';
	}

	/**
	 * Validate the number: allowed characters and 7–15 digits (E.164 maximum).
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
		$digits = strlen( (string) preg_replace( '/\D/', '', $value ) );
		if ( ! preg_match( '/^\+?[0-9\s().\-]+$/', $value ) || $digits < 7 || $digits > 15 ) {
			return __( 'Please enter a valid phone number.', 'glixform' );
		}
		return '';
	}
}

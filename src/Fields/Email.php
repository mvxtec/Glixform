<?php
/**
 * Email field.
 *
 * @package Glixform
 */

namespace Glixform\Fields;

defined( 'ABSPATH' ) || exit;

/**
 * <input type="email">.
 */
class Email extends Text {

	/**
	 * Machine name.
	 *
	 * @return string
	 */
	public function type() {
		return 'email';
	}

	/**
	 * Display name.
	 *
	 * @return string
	 */
	public function name() {
		return __( 'Email', 'glixform' );
	}

	/**
	 * Builder icon.
	 *
	 * @return string
	 */
	public function icon() {
		return 'dashicons-email';
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
		return 'email';
	}

	/**
	 * Extra attributes.
	 *
	 * @param array $field Field config.
	 * @return string
	 */
	protected function extra_attributes( array $field ) {
		return ' autocomplete="email"';
	}

	/**
	 * Trim the address.
	 *
	 * @param array $field Field config.
	 * @param mixed $raw   Raw value.
	 * @return string
	 */
	public function sanitize_value( array $field, $raw ) {
		return is_array( $raw ) ? '' : trim( sanitize_text_field( (string) $raw ) );
	}

	/**
	 * Validate the address.
	 *
	 * @param array        $field Field config.
	 * @param string|array $value Value.
	 * @return string
	 */
	public function validate( array $field, $value ) {
		$error = parent::validate( $field, $value );
		if ( '' === $error && '' !== $value && ! is_email( $value ) ) {
			$error = __( 'Please enter a valid email address.', 'glixform' );
		}
		return $error;
	}
}

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
	 * {@inheritDoc}
	 */
	public function type() {
		return 'email';
	}

	/**
	 * {@inheritDoc}
	 */
	public function name() {
		return __( 'Email', 'glixform' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function icon() {
		return 'dashicons-email';
	}

	/**
	 * {@inheritDoc}
	 */
	public function options() {
		return array( 'label', 'description', 'required', 'placeholder', 'default_value' );
	}

	/**
	 * {@inheritDoc}
	 */
	protected function input_type() {
		return 'email';
	}

	/**
	 * Overrides the parent implementation.
	 *
	 * @param array $field Field config.
	 * @return string
	 */
	protected function extra_attributes( array $field ) {
		return ' autocomplete="email"';
	}

	/**
	 * Overrides the parent implementation.
	 *
	 * @param array $field Field config.
	 * @param mixed $raw   Raw value from the request.
	 * @return string|array
	 */
	public function sanitize_value( array $field, $raw ) {
		return is_array( $raw ) ? '' : trim( sanitize_text_field( (string) $raw ) );
	}

	/**
	 * Overrides the parent implementation.
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

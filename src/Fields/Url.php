<?php
/**
 * Website / URL field.
 *
 * @package Glixform
 */

namespace Glixform\Fields;

defined( 'ABSPATH' ) || exit;

/**
 * <input type="url"> accepting http and https addresses.
 */
class Url extends Text {

	/**
	 * Machine name.
	 *
	 * @return string
	 */
	public function type() {
		return 'url';
	}

	/**
	 * Display name.
	 *
	 * @return string
	 */
	public function name() {
		return __( 'Website / URL', 'glixform' );
	}

	/**
	 * Builder icon.
	 *
	 * @return string
	 */
	public function icon() {
		return 'dashicons-admin-links';
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
		return 'url';
	}

	/**
	 * Extra attributes.
	 *
	 * @param array $field Field config.
	 * @return string
	 */
	protected function extra_attributes( array $field ) {
		return ' autocomplete="url" inputmode="url"';
	}

	/**
	 * Add https:// when the visitor left out the scheme.
	 *
	 * @param array $field Field config.
	 * @param mixed $raw   Raw value.
	 * @return string
	 */
	public function sanitize_value( array $field, $raw ) {
		$value = is_array( $raw ) ? '' : trim( sanitize_text_field( (string) $raw ) );
		if ( '' !== $value && ! preg_match( '#^[a-z][a-z0-9+.\-]*://#i', $value ) ) {
			$value = 'https://' . $value;
		}
		return $value;
	}

	/**
	 * Only http(s) URLs with a host.
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
		$scheme = strtolower( (string) wp_parse_url( $value, PHP_URL_SCHEME ) );
		$host   = (string) wp_parse_url( $value, PHP_URL_HOST );
		if ( ! filter_var( $value, FILTER_VALIDATE_URL ) || ! in_array( $scheme, array( 'http', 'https' ), true ) || false === strpos( $host, '.' ) ) {
			return __( 'Please enter a valid website address.', 'glixform' );
		}
		return '';
	}
}

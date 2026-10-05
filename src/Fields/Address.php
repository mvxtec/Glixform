<?php
/**
 * Address field.
 *
 * @package Glixform
 */

namespace Glixform\Fields;

defined( 'ABSPATH' ) || exit;

/**
 * Street, city, region, postal code and country.
 */
class Address extends CompositeField {

	/**
	 * Machine name.
	 *
	 * @return string
	 */
	public function type() {
		return 'address';
	}

	/**
	 * Display name.
	 *
	 * @return string
	 */
	public function name() {
		return __( 'Address', 'glixform' );
	}

	/**
	 * Builder icon.
	 *
	 * @return string
	 */
	public function icon() {
		return 'dashicons-location';
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
		return array( 'label', 'description', 'required', 'hide_line2', 'hide_country', 'css_class' );
	}

	/**
	 * Toggles for optional parts.
	 *
	 * @return array
	 */
	protected function custom_option_definitions() {
		return array(
			'hide_line2'   => array(
				'type'    => 'toggle',
				'label'   => __( 'Hide address line 2', 'glixform' ),
				'default' => false,
				'group'   => 'advanced',
			),
			'hide_country' => array(
				'type'    => 'toggle',
				'label'   => __( 'Hide country', 'glixform' ),
				'default' => false,
				'group'   => 'advanced',
			),
		);
	}

	/**
	 * Address parts.
	 *
	 * @param array $field Field config.
	 * @return array
	 */
	protected function parts( array $field ) {
		$parts = array(
			'line1'   => array(
				'label'        => __( 'Address line 1', 'glixform' ),
				'autocomplete' => 'address-line1',
				'required'     => true,
				'wide'         => true,
			),
			'line2'   => array(
				'label'        => __( 'Address line 2', 'glixform' ),
				'autocomplete' => 'address-line2',
				'required'     => false,
				'wide'         => true,
			),
			'city'    => array(
				'label'        => __( 'City', 'glixform' ),
				'autocomplete' => 'address-level2',
				'required'     => true,
			),
			'state'   => array(
				'label'        => __( 'State / Province / Region', 'glixform' ),
				'autocomplete' => 'address-level1',
				'required'     => false,
			),
			'postal'  => array(
				'label'        => __( 'ZIP / Postal code', 'glixform' ),
				'autocomplete' => 'postal-code',
				'required'     => true,
			),
			'country' => array(
				'label'        => __( 'Country', 'glixform' ),
				'autocomplete' => 'country-name',
				'required'     => true,
			),
		);
		if ( ! empty( $field['hide_line2'] ) ) {
			unset( $parts['line2'] );
		}
		if ( ! empty( $field['hide_country'] ) ) {
			unset( $parts['country'] );
		}
		return $parts;
	}

	/**
	 * Multi-line postal format.
	 *
	 * @param array $field Field config.
	 * @param array $value Parts.
	 * @return string
	 */
	protected function join_parts( array $field, array $value ) {
		$city_line = trim( implode( ' ', array_filter( array( trim( ( $value['city'] ?? '' ) . ( ! empty( $value['state'] ) ? ',' : '' ) ), $value['state'] ?? '', $value['postal'] ?? '' ), 'strlen' ) ) );
		$lines     = array( $value['line1'] ?? '', $value['line2'] ?? '', $city_line, $value['country'] ?? '' );
		return implode( "\n", array_filter( array_map( 'trim', $lines ), 'strlen' ) );
	}
}

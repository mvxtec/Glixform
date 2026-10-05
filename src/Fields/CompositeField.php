<?php
/**
 * Base for fields made of several inputs (name, address).
 *
 * @package Glixform
 */

namespace Glixform\Fields;

defined( 'ABSPATH' ) || exit;

/**
 * Renders a fieldset with one labelled input per part; the value is an
 * associative array of parts.
 */
abstract class CompositeField extends AbstractField {

	/**
	 * Parts of the field.
	 *
	 * @param array $field Field config.
	 * @return array key => [ label, autocomplete, required (when the field is required), wide (bool) ].
	 */
	abstract protected function parts( array $field );

	/**
	 * Join parts into display text.
	 *
	 * @param array $field Field config.
	 * @param array $value Parts.
	 * @return string
	 */
	abstract protected function join_parts( array $field, array $value );

	/**
	 * Whether this config uses parts at all (a "simple" name is a single input).
	 *
	 * @param array $field Field config.
	 * @return bool
	 */
	protected function uses_parts( array $field ) {
		return true;
	}

	/**
	 * The legend replaces the label when parts are used.
	 *
	 * @param array  $field   Field config.
	 * @param string $html_id Input ID.
	 * @return string
	 */
	protected function render_label( array $field, $html_id ) {
		return $this->uses_parts( $field ) ? '' : parent::render_label( $field, $html_id );
	}

	/**
	 * Render all parts.
	 *
	 * @param array        $field Field config.
	 * @param string|array $value Value.
	 * @param array        $attrs Shared attributes.
	 * @return string
	 */
	protected function render_input( array $field, $value, array $attrs ) {
		$value = is_array( $value ) ? $value : array();

		$html = sprintf( '<fieldset class="glixform-parts" id="%s"', esc_attr( $attrs['id'] ) );
		if ( ! empty( $attrs['aria-describedby'] ) ) {
			$html .= sprintf( ' aria-describedby="%s"', esc_attr( $attrs['aria-describedby'] ) );
		}
		$html .= sprintf(
			'><legend class="glixform-label">%s%s</legend><div class="glixform-parts-grid">',
			esc_html( $field['label'] ),
			$this->required_marker( $field )
		);

		foreach ( $this->parts( $field ) as $key => $part ) {
			$part_id  = $attrs['id'] . '-' . $key;
			$required = $this->html_required( $field ) && ! empty( $part['required'] );
			$html    .= sprintf(
				'<div class="glixform-part%1$s"><input type="text" class="glixform-input" id="%2$s" name="%3$s[%4$s]" value="%5$s" autocomplete="%6$s"%7$s%8$s%10$s><label class="glixform-sublabel" for="%2$s">%9$s</label></div>',
				empty( $part['wide'] ) ? '' : ' glixform-part-wide',
				esc_attr( $part_id ),
				esc_attr( $attrs['name'] ),
				esc_attr( $key ),
				esc_attr( isset( $value[ $key ] ) && is_scalar( $value[ $key ] ) ? (string) $value[ $key ] : '' ),
				esc_attr( $part['autocomplete'] ),
				$required ? ' required aria-required="true"' : '',
				! empty( $attrs['error'] ) ? ' aria-invalid="true"' : '',
				esc_html( $part['label'] ),
				! empty( $field['required'] ) && ! empty( $part['required'] ) ? ' data-part-required' : ''
			);
		}

		return $html . '</div></fieldset>';
	}

	/**
	 * Keep known parts only.
	 *
	 * @param array $field Field config.
	 * @param mixed $raw   Raw value.
	 * @return array
	 */
	public function sanitize_value( array $field, $raw ) {
		$raw   = is_array( $raw ) ? $raw : array();
		$clean = array();
		foreach ( array_keys( $this->parts( $field ) ) as $key ) {
			$clean[ $key ] = isset( $raw[ $key ] ) && is_scalar( $raw[ $key ] ) ? sanitize_text_field( (string) $raw[ $key ] ) : '';
		}
		return $clean;
	}

	/**
	 * Required parts must be filled in when the field is required.
	 *
	 * @param array        $field Field config.
	 * @param string|array $value Value.
	 * @return string
	 */
	public function validate( array $field, $value ) {
		if ( empty( $field['required'] ) ) {
			return '';
		}
		foreach ( $this->parts( $field ) as $key => $part ) {
			if ( ! empty( $part['required'] ) && '' === trim( (string) ( $value[ $key ] ?? '' ) ) ) {
				return __( 'Please fill in all required parts of this field.', 'glixform' );
			}
		}
		return '';
	}

	/**
	 * Display text.
	 *
	 * @param array        $field Field config.
	 * @param string|array $value Value.
	 * @return string
	 */
	public function format_value( array $field, $value ) {
		return is_array( $value ) ? trim( $this->join_parts( $field, $value ) ) : (string) $value;
	}

	/**
	 * Parts joined by spaces (frontend.js builds the same string).
	 *
	 * @param array        $field Field config.
	 * @param string|array $value Value.
	 * @return string
	 */
	public function logic_value( array $field, $value ) {
		if ( ! is_array( $value ) ) {
			return (string) $value;
		}
		return implode( ' ', array_filter( array_map( 'trim', array_map( 'strval', array_filter( $value, 'is_scalar' ) ) ), 'strlen' ) );
	}
}

<?php
/**
 * Dropdown field.
 *
 * @package Glixform
 */

namespace Glixform\Fields;

defined( 'ABSPATH' ) || exit;

/**
 * <select>.
 */
class Select extends ChoiceField {

	/**
	 * {@inheritDoc}
	 */
	public function type() {
		return 'select';
	}

	/**
	 * {@inheritDoc}
	 */
	public function name() {
		return __( 'Dropdown', 'glixform' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function icon() {
		return 'dashicons-arrow-down-alt2';
	}

	/**
	 * {@inheritDoc}
	 */
	public function options() {
		return array( 'label', 'description', 'required', 'placeholder', 'choices' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function defaults() {
		return parent::defaults() + array( 'placeholder' => '' );
	}

	/**
	 * Overrides the parent implementation.
	 *
	 * @param array        $field Field config.
	 * @param string|array $value Value.
	 * @param array        $attrs Shared attributes.
	 * @return string
	 */
	protected function render_input( array $field, $value, array $attrs ) {
		$placeholder = $field['placeholder'] ?? '';
		$field_attrs = $field;
		unset( $field_attrs['placeholder'] ); // Not a valid <select> attribute.

		$html = sprintf( '<select class="glixform-input"%s>', $this->common_attributes( $field_attrs, $attrs ) );

		// An empty first option lets "required" work and avoids silently picking choice one.
		$html .= sprintf( '<option value="">%s</option>', esc_html( '' !== $placeholder ? $placeholder : __( '— Select —', 'glixform' ) ) );

		foreach ( $this->choice_labels( $field ) as $label ) {
			$html .= sprintf(
				'<option value="%1$s"%2$s>%3$s</option>',
				esc_attr( $label ),
				selected( (string) $value, $label, false ),
				esc_html( $label )
			);
		}

		return $html . '</select>';
	}
}

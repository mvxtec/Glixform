<?php
/**
 * Date / time field.
 *
 * @package Glixform
 */

namespace Glixform\Fields;

defined( 'ABSPATH' ) || exit;

/**
 * Native date, time or date-and-time picker. Values are stored in ISO format
 * (2026-10-05, 14:30, 2026-10-05T14:30) and displayed in the site's format.
 */
class DateTime extends Text {

	const FORMATS = array(
		'date'     => 'Y-m-d',
		'time'     => 'H:i',
		'datetime' => 'Y-m-d\TH:i',
	);

	/**
	 * Machine name.
	 *
	 * @return string
	 */
	public function type() {
		return 'datetime';
	}

	/**
	 * Display name.
	 *
	 * @return string
	 */
	public function name() {
		return __( 'Date / Time', 'glixform' );
	}

	/**
	 * Builder icon.
	 *
	 * @return string
	 */
	public function icon() {
		return 'dashicons-calendar-alt';
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
		return array( 'label', 'description', 'required', 'format', 'min', 'max', 'default_value', 'css_class' );
	}

	/**
	 * Format, earliest and latest options.
	 *
	 * @return array
	 */
	protected function custom_option_definitions() {
		return array(
			'format' => array(
				'type'    => 'select',
				'label'   => __( 'Type', 'glixform' ),
				'default' => 'date',
				'choices' => array(
					'date'     => __( 'Date', 'glixform' ),
					'time'     => __( 'Time', 'glixform' ),
					'datetime' => __( 'Date and time', 'glixform' ),
				),
			),
			'min'    => array(
				'type'    => 'text',
				'label'   => __( 'Earliest', 'glixform' ),
				'help'    => __( 'Same format as the value, e.g. 2026-01-31 or 09:00. Leave empty for no limit.', 'glixform' ),
				'default' => '',
				'group'   => 'advanced',
			),
			'max'    => array(
				'type'    => 'text',
				'label'   => __( 'Latest', 'glixform' ),
				'default' => '',
				'group'   => 'advanced',
			),
		);
	}

	/**
	 * PHP format for the field's type.
	 *
	 * @param array $field Field config.
	 * @return string
	 */
	private function php_format( array $field ) {
		return self::FORMATS[ $field['format'] ?? 'date' ] ?? self::FORMATS['date'];
	}

	/**
	 * Input type attribute (unused; see render_input()).
	 *
	 * @return string
	 */
	protected function input_type() {
		return 'date';
	}

	/**
	 * Render the picker.
	 *
	 * @param array        $field Field config.
	 * @param string|array $value Value.
	 * @param array        $attrs Shared attributes.
	 * @return string
	 */
	protected function render_input( array $field, $value, array $attrs ) {
		$types = array(
			'date'     => 'date',
			'time'     => 'time',
			'datetime' => 'datetime-local',
		);
		$type  = $types[ $field['format'] ?? 'date' ] ?? 'date';
		$extra = '';
		foreach ( array( 'min', 'max' ) as $attr ) {
			if ( ! empty( $field[ $attr ] ) && $this->parse( $field, $field[ $attr ] ) ) {
				$extra .= sprintf( ' %s="%s"', $attr, esc_attr( $field[ $attr ] ) );
			}
		}
		return sprintf(
			'<input type="%s" class="glixform-input"%s%s value="%s">',
			esc_attr( $type ),
			$this->common_attributes( $field, $attrs ),
			$extra,
			esc_attr( is_array( $value ) ? '' : (string) $value )
		);
	}

	/**
	 * Parse a value strictly in the field's format.
	 *
	 * @param array  $field Field config.
	 * @param string $value Value.
	 * @return \DateTimeImmutable|null
	 */
	private function parse( array $field, $value ) {
		$value = (string) $value;
		// Browsers may send seconds for time inputs; drop them.
		if ( preg_match( '/^(.*\d{2}:\d{2}):\d{2}(\.\d+)?$/', $value, $m ) ) {
			$value = $m[1];
		}
		$date = \DateTimeImmutable::createFromFormat( '!' . $this->php_format( $field ), $value, new \DateTimeZone( 'UTC' ) );
		return ( $date && $date->format( $this->php_format( $field ) ) === $value ) ? $date : null;
	}

	/**
	 * Normalize to the ISO format.
	 *
	 * @param array $field Field config.
	 * @param mixed $raw   Raw value.
	 * @return string
	 */
	public function sanitize_value( array $field, $raw ) {
		$value = is_array( $raw ) ? '' : trim( sanitize_text_field( (string) $raw ) );
		$date  = '' === $value ? null : $this->parse( $field, $value );
		return $date ? $date->format( $this->php_format( $field ) ) : $value;
	}

	/**
	 * Valid format and within earliest/latest.
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
		$date = $this->parse( $field, $value );
		if ( ! $date ) {
			return __( 'Please enter a valid date or time.', 'glixform' );
		}
		$min = empty( $field['min'] ) ? null : $this->parse( $field, $field['min'] );
		$max = empty( $field['max'] ) ? null : $this->parse( $field, $field['max'] );
		if ( ( $min && $date < $min ) || ( $max && $date > $max ) ) {
			return __( 'Please choose a value within the allowed range.', 'glixform' );
		}
		return '';
	}

	/**
	 * Display in the site's date/time format.
	 *
	 * @param array        $field Field config.
	 * @param string|array $value Value.
	 * @return string
	 */
	public function format_value( array $field, $value ) {
		$date = is_string( $value ) && '' !== $value ? $this->parse( $field, $value ) : null;
		if ( ! $date ) {
			return is_string( $value ) ? $value : '';
		}
		$formats = array(
			'date'     => get_option( 'date_format' ),
			'time'     => get_option( 'time_format' ),
			'datetime' => get_option( 'date_format' ) . ' ' . get_option( 'time_format' ),
		);
		return date_i18n( $formats[ $field['format'] ?? 'date' ] ?? $formats['date'], $date->getTimestamp() );
	}

	/**
	 * Compare ISO values in logic, so "greater than" works chronologically.
	 *
	 * @param array        $field Field config.
	 * @param string|array $value Value.
	 * @return string
	 */
	public function logic_value( array $field, $value ) {
		return is_string( $value ) ? $value : '';
	}
}

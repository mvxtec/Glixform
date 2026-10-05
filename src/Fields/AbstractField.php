<?php
/**
 * Base class for all field types.
 *
 * @package Glixform
 */

namespace Glixform\Fields;

defined( 'ABSPATH' ) || exit;

/**
 * A field type describes its builder options, renders itself, and validates,
 * sanitizes and formats submitted values.
 *
 * A field "config" is the array stored in the form JSON, for example:
 * [ 'id' => 3, 'type' => 'email', 'label' => 'Email', 'required' => true ].
 *
 * Builder options are declared once in option_definitions(); the builder UI,
 * defaults and server-side sanitizing are all generated from that schema.
 */
abstract class AbstractField {

	/**
	 * Machine name, e.g. "text".
	 *
	 * @return string
	 */
	abstract public function type();

	/**
	 * Human-readable name shown in the builder.
	 *
	 * @return string
	 */
	abstract public function name();

	/**
	 * Render the input element(s) only; the wrapper, label and errors come from render().
	 *
	 * @param array        $field Field config.
	 * @param string|array $value Current value.
	 * @param array        $attrs Shared attributes: id, name, aria-describedby, error.
	 * @return string
	 */
	abstract protected function render_input( array $field, $value, array $attrs );

	/**
	 * Dashicon slug for the builder.
	 *
	 * @return string
	 */
	public function icon() {
		return 'dashicons-editor-textcolor';
	}

	/**
	 * Palette group: "standard", "fancy" or "layout".
	 *
	 * @return string
	 */
	public function category() {
		return 'standard';
	}

	/**
	 * Whether the field collects a value. Layout fields (page break, divider, HTML) do not.
	 *
	 * @return bool
	 */
	public function is_input() {
		return true;
	}

	/**
	 * Whether the field can be shown or hidden with conditional logic.
	 *
	 * @return bool
	 */
	public function supports_logic() {
		return true;
	}

	/**
	 * Builder options this field supports, in display order.
	 *
	 * @return string[]
	 */
	public function options() {
		return array( 'label', 'description', 'required', 'placeholder', 'default_value', 'css_class' );
	}

	/**
	 * Schema of every option a field may use. Subclasses add their own via
	 * custom_option_definitions().
	 *
	 * Types: text, textarea, html, toggle, number, select, choices.
	 *
	 * @return array key => definition.
	 */
	public function option_definitions() {
		$base = array(
			'label'         => array(
				'type'    => 'text',
				'label'   => __( 'Label', 'glixform' ),
				'default' => $this->name(),
			),
			'description'   => array(
				'type'    => 'html',
				'label'   => __( 'Description', 'glixform' ),
				'help'    => __( 'Shown below the field.', 'glixform' ),
				'default' => '',
			),
			'required'      => array(
				'type'    => 'toggle',
				'label'   => __( 'Required', 'glixform' ),
				'default' => false,
			),
			'placeholder'   => array(
				'type'    => 'text',
				'label'   => __( 'Placeholder', 'glixform' ),
				'default' => '',
				'group'   => 'advanced',
			),
			'default_value' => array(
				'type'    => 'text',
				'label'   => __( 'Default value', 'glixform' ),
				'help'    => __( 'Smart tags work here, e.g. {query_var key="ref"} or {user_email}.', 'glixform' ),
				'default' => '',
				'group'   => 'advanced',
			),
			'max_length'    => array(
				'type'    => 'number',
				'label'   => __( 'Character limit', 'glixform' ),
				'help'    => __( '0 means no limit.', 'glixform' ),
				'default' => 0,
				'min'     => 0,
				'step'    => 1,
				'group'   => 'advanced',
			),
			'min'           => array(
				'type'    => 'number',
				'label'   => __( 'Minimum', 'glixform' ),
				'default' => '',
			),
			'max'           => array(
				'type'    => 'number',
				'label'   => __( 'Maximum', 'glixform' ),
				'default' => '',
			),
			'step'          => array(
				'type'    => 'number',
				'label'   => __( 'Step', 'glixform' ),
				'default' => '',
				'group'   => 'advanced',
			),
			'choices'       => array(
				'type'    => 'choices',
				'label'   => __( 'Choices', 'glixform' ),
				'default' => array(
					array(
						'label'   => __( 'First Choice', 'glixform' ),
						'default' => false,
					),
					array(
						'label'   => __( 'Second Choice', 'glixform' ),
						'default' => false,
					),
					array(
						'label'   => __( 'Third Choice', 'glixform' ),
						'default' => false,
					),
				),
			),
			'css_class'     => array(
				'type'    => 'text',
				'label'   => __( 'CSS classes', 'glixform' ),
				'help'    => __( 'Separate several classes with spaces.', 'glixform' ),
				'default' => '',
				'group'   => 'advanced',
			),
		);

		$definitions = array_merge( $base, $this->custom_option_definitions() );

		$out = array();
		foreach ( $this->options() as $key ) {
			if ( isset( $definitions[ $key ] ) ) {
				$out[ $key ] = $definitions[ $key ] + array(
					'group' => 'basic',
					'help'  => '',
				);
			}
		}
		return $out;
	}

	/**
	 * Field-specific option definitions, merged over the base ones.
	 *
	 * @return array
	 */
	protected function custom_option_definitions() {
		return array();
	}

	/**
	 * Default config for a newly added field.
	 *
	 * @return array
	 */
	public function defaults() {
		$defaults = array();
		foreach ( $this->option_definitions() as $key => $definition ) {
			$defaults[ $key ] = $definition['default'];
		}
		return $defaults;
	}

	/**
	 * Whether the field offers a fixed list of choices.
	 *
	 * @return bool
	 */
	public function has_choices() {
		return in_array( 'choices', $this->options(), true );
	}

	/**
	 * Whether the submitted value is a list (e.g. checkboxes).
	 *
	 * @return bool
	 */
	public function is_multiple() {
		return false;
	}

	/**
	 * Clean a config coming from the builder. Only declared options survive.
	 * Conditional logic is sanitized separately (it needs the whole form).
	 *
	 * @param array $config Raw config.
	 * @return array
	 */
	public function sanitize_config( array $config ) {
		$clean = array(
			'id'   => absint( $config['id'] ?? 0 ),
			'type' => $this->type(),
		);

		foreach ( $this->option_definitions() as $key => $definition ) {
			$raw = array_key_exists( $key, $config ) ? $config[ $key ] : $definition['default'];

			switch ( $definition['type'] ) {
				case 'textarea':
					$clean[ $key ] = sanitize_textarea_field( is_scalar( $raw ) ? (string) $raw : '' );
					break;
				case 'html':
					$clean[ $key ] = wp_kses_post( is_scalar( $raw ) ? (string) $raw : '' );
					break;
				case 'toggle':
					$clean[ $key ] = (bool) $raw;
					break;
				case 'number':
					$clean[ $key ] = $this->sanitize_number_option( $raw, $definition );
					break;
				case 'select':
					$choices       = array_keys( $definition['choices'] ?? array() );
					$clean[ $key ] = in_array( (string) $raw, array_map( 'strval', $choices ), true ) ? (string) $raw : (string) $definition['default'];
					break;
				case 'choices':
					$clean[ $key ] = $this->sanitize_choices( $raw );
					break;
				default:
					$clean[ $key ] = sanitize_text_field( is_scalar( $raw ) ? (string) $raw : '' );
			}
		}

		if ( isset( $clean['label'] ) && '' === $clean['label'] ) {
			$clean['label'] = $this->name();
		}
		if ( isset( $clean['css_class'] ) ) {
			$clean['css_class'] = implode( ' ', array_filter( array_map( 'sanitize_html_class', explode( ' ', $clean['css_class'] ) ) ) );
		}

		return $clean;
	}

	/**
	 * Clean a numeric option. Empty stays empty unless the default is a number.
	 *
	 * @param mixed $raw        Raw value.
	 * @param array $definition Option definition.
	 * @return int|float|string
	 */
	protected function sanitize_number_option( $raw, array $definition ) {
		if ( ! is_numeric( $raw ) ) {
			return $definition['default'];
		}
		$number = 0 + $raw;
		if ( isset( $definition['min'] ) && $number < $definition['min'] ) {
			$number = $definition['min'];
		}
		if ( isset( $definition['max'] ) && $number > $definition['max'] ) {
			$number = $definition['max'];
		}
		if ( is_int( $definition['default'] ) ) {
			return (int) $number;
		}
		return (string) $number;
	}

	/**
	 * Clean a list of choices: [ [ 'label' => 'Red', 'default' => false ], ... ].
	 *
	 * @param mixed $choices Raw choices.
	 * @return array
	 */
	protected function sanitize_choices( $choices ) {
		$clean = array();
		foreach ( (array) $choices as $choice ) {
			$label = is_array( $choice ) ? ( $choice['label'] ?? '' ) : $choice;
			$label = sanitize_text_field( is_scalar( $label ) ? (string) $label : '' );
			if ( '' !== $label ) {
				$clean[] = array(
					'label'   => $label,
					'default' => is_array( $choice ) && ! empty( $choice['default'] ),
				);
			}
		}
		return $clean;
	}

	/**
	 * Full field HTML: wrapper, label, input, description and error.
	 *
	 * @param array             $field   Field config.
	 * @param int               $form_id Form ID.
	 * @param string|array|null $value   Submitted value, or null to use the default.
	 * @param string            $error   Validation error message.
	 * @return string
	 */
	public function render( array $field, $form_id, $value = null, $error = '' ) {
		$html_id = $this->html_id( $field, $form_id );
		$desc_id = $html_id . '-description';
		$err_id  = $html_id . '-error';

		if ( null === $value ) {
			$value = $this->default_value( $field );
		}

		$described = array();
		if ( ! empty( $field['description'] ) ) {
			$described[] = $desc_id;
		}
		if ( $error ) {
			$described[] = $err_id;
		}

		$attrs = array(
			'id'               => $html_id,
			'name'             => sprintf( 'glixform[fields][%d]', $field['id'] ),
			'aria-describedby' => implode( ' ', $described ),
			'error'            => (bool) $error,
		);

		$html  = $this->open_wrapper( $field, $error );
		$html .= $this->render_label( $field, $html_id );
		$html .= $this->render_input( $field, $value, $attrs );

		if ( ! empty( $field['description'] ) ) {
			$html .= sprintf( '<div class="glixform-description" id="%s">%s</div>', esc_attr( $desc_id ), wp_kses_post( $field['description'] ) );
		}

		$html .= sprintf(
			'<div class="glixform-error" id="%s" role="alert"%s>%s</div>',
			esc_attr( $err_id ),
			$error ? '' : ' hidden',
			esc_html( $error )
		);

		return $html . '</div>';
	}

	/**
	 * HTML ID of the field's main control.
	 *
	 * @param array $field   Field config.
	 * @param int   $form_id Form ID.
	 * @return string
	 */
	protected function html_id( array $field, $form_id ) {
		return sprintf( 'glixform-%d-field-%d', $form_id, $field['id'] );
	}

	/**
	 * Opening wrapper tag with classes and conditional-logic data.
	 *
	 * @param array  $field Field config.
	 * @param string $error Error message.
	 * @return string
	 */
	protected function open_wrapper( array $field, $error = '' ) {
		$classes = array( 'glixform-field', 'glixform-field-' . $this->type() );
		if ( ! empty( $field['required'] ) ) {
			$classes[] = 'glixform-field-required';
		}
		if ( $error ) {
			$classes[] = 'glixform-has-error';
		}
		if ( ! empty( $field['css_class'] ) ) {
			$classes[] = $field['css_class'];
		}

		$logic = '';
		if ( ! empty( $field['conditional']['enabled'] ) ) {
			$classes[] = 'glixform-conditional';
			$logic     = sprintf( ' data-conditional="%s"', esc_attr( wp_json_encode( $field['conditional'] ) ) );
		}

		return sprintf(
			'<div class="%s" data-field-id="%d" data-field-type="%s"%s>',
			esc_attr( implode( ' ', $classes ) ),
			(int) $field['id'],
			esc_attr( $this->type() ),
			$logic
		);
	}

	/**
	 * Label markup. Group fields override this with a legend.
	 *
	 * @param array  $field   Field config.
	 * @param string $html_id Input ID.
	 * @return string
	 */
	protected function render_label( array $field, $html_id ) {
		return sprintf(
			'<label class="glixform-label" for="%s">%s%s</label>',
			esc_attr( $html_id ),
			esc_html( $field['label'] ),
			$this->required_marker( $field )
		);
	}

	/**
	 * Asterisk for required fields.
	 *
	 * @param array $field Field config.
	 * @return string
	 */
	protected function required_marker( array $field ) {
		if ( empty( $field['required'] ) ) {
			return '';
		}
		return ' <span class="glixform-required" aria-hidden="true">*</span>';
	}

	/**
	 * Shared HTML attributes for a single input element.
	 *
	 * @param array $field Field config.
	 * @param array $attrs Attributes from render().
	 * @return string
	 */
	protected function common_attributes( array $field, array $attrs ) {
		$out = sprintf( ' id="%s" name="%s"', esc_attr( $attrs['id'] ), esc_attr( $attrs['name'] ) );
		if ( ! empty( $attrs['aria-describedby'] ) ) {
			$out .= sprintf( ' aria-describedby="%s"', esc_attr( $attrs['aria-describedby'] ) );
		}
		if ( $this->html_required( $field ) ) {
			$out .= ' required aria-required="true"';
		}
		if ( ! empty( $attrs['error'] ) ) {
			$out .= ' aria-invalid="true"';
		}
		if ( ! empty( $field['placeholder'] ) ) {
			$out .= sprintf( ' placeholder="%s"', esc_attr( $field['placeholder'] ) );
		}
		return $out;
	}

	/**
	 * Whether to add the browser's "required" attribute. Fields with conditional
	 * logic skip it: without JavaScript a hidden-by-logic field would otherwise
	 * block submission. The server and frontend.js still enforce it.
	 *
	 * @param array $field Field config.
	 * @return bool
	 */
	protected function html_required( array $field ) {
		return ! empty( $field['required'] ) && empty( $field['conditional']['enabled'] );
	}

	/**
	 * Initial value before anything is submitted.
	 *
	 * @param array $field Field config.
	 * @return string|array
	 */
	public function default_value( array $field ) {
		return (string) ( $field['default_value'] ?? '' );
	}

	/**
	 * Normalize raw request input into the field's value shape.
	 *
	 * @param array $field Field config.
	 * @param mixed $raw   Raw value from the request (already unslashed).
	 * @return string|array
	 */
	public function sanitize_value( array $field, $raw ) {
		if ( is_array( $raw ) ) {
			return '';
		}
		return sanitize_text_field( (string) $raw );
	}

	/**
	 * Validate a sanitized value.
	 *
	 * @param array        $field Field config.
	 * @param string|array $value Sanitized value.
	 * @return string Error message, or empty string when valid.
	 */
	public function validate( array $field, $value ) {
		if ( ! empty( $field['required'] ) && $this->is_empty( $value ) ) {
			return __( 'This field is required.', 'glixform' );
		}
		if ( ! empty( $field['max_length'] ) && is_string( $value ) && mb_strlen( $value ) > (int) $field['max_length'] ) {
			/* translators: %d: maximum number of characters. */
			return sprintf( __( 'Please enter no more than %d characters.', 'glixform' ), (int) $field['max_length'] );
		}
		return '';
	}

	/**
	 * Last step after the whole form is valid, e.g. moving uploaded files.
	 *
	 * @param array        $field   Field config.
	 * @param string|array $value   Sanitized, valid value.
	 * @param int          $form_id Form ID.
	 * @return string|array|\WP_Error Final value to store.
	 */
	public function finalize_value( array $field, $value, $form_id ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- Used by subclasses.
		return $value;
	}

	/**
	 * Whether a value counts as empty.
	 *
	 * @param string|array $value Value.
	 * @return bool
	 */
	public function is_empty( $value ) {
		if ( is_array( $value ) ) {
			foreach ( $value as $item ) {
				if ( ! $this->is_empty( $item ) ) {
					return false;
				}
			}
			return true;
		}
		return '' === trim( (string) $value );
	}

	/**
	 * Plain-text representation used in emails, the entries list and CSV export.
	 *
	 * @param array        $field Field config.
	 * @param string|array $value Stored value.
	 * @return string
	 */
	public function format_value( array $field, $value ) {
		if ( ! is_array( $value ) ) {
			return (string) $value;
		}
		return implode( ', ', array_filter( array_map( 'strval', array_filter( $value, 'is_scalar' ) ), 'strlen' ) );
	}

	/**
	 * Value used to evaluate conditional logic rules.
	 *
	 * @param array        $field Field config.
	 * @param string|array $value Sanitized value.
	 * @return string|array A list for multi-select fields, otherwise a string.
	 */
	public function logic_value( array $field, $value ) {
		return $this->format_value( $field, $value );
	}

	/**
	 * HTML for an entry value on the admin entry screen.
	 *
	 * @param array $item Entry snapshot item: id, type, label, value, formatted.
	 * @param array $context entry_id, form_id.
	 * @return string
	 */
	public function entry_html( array $item, array $context = array() ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- Used by subclasses.
		$text = $item['formatted'] ?? self::flatten( $item['value'] ?? '' );
		return '' === $text ? '' : nl2br( esc_html( $text ) );
	}

	/**
	 * Flatten any stored value into a string.
	 *
	 * @param mixed $value Value.
	 * @return string
	 */
	public static function flatten( $value ) {
		if ( ! is_array( $value ) ) {
			return (string) $value;
		}
		$parts = array();
		foreach ( $value as $item ) {
			$part = is_array( $item ) ? ( $item['name'] ?? self::flatten( $item ) ) : (string) $item;
			if ( '' !== $part ) {
				$parts[] = $part;
			}
		}
		return implode( ', ', $parts );
	}

	/**
	 * Data passed to the builder JavaScript.
	 *
	 * @return array
	 */
	public function to_builder_array() {
		return array(
			'type'          => $this->type(),
			'name'          => $this->name(),
			'icon'          => $this->icon(),
			'category'      => $this->category(),
			'isInput'       => $this->is_input(),
			'supportsLogic' => $this->supports_logic(),
			'isMultiple'    => $this->is_multiple(),
			'hasChoices'    => $this->has_choices(),
			'options'       => $this->option_definitions(),
			'defaults'      => $this->defaults(),
		);
	}
}

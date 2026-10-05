<?php
/**
 * Star rating.
 *
 * @package Glixform
 */

namespace Glixform\Fields;

defined( 'ABSPATH' ) || exit;

/**
 * Radio buttons styled as stars; the value is a number from 1 to the scale.
 */
class Rating extends ChoiceField {

	/**
	 * Machine name.
	 *
	 * @return string
	 */
	public function type() {
		return 'rating';
	}

	/**
	 * Display name.
	 *
	 * @return string
	 */
	public function name() {
		return __( 'Rating', 'glixform' );
	}

	/**
	 * Builder icon.
	 *
	 * @return string
	 */
	public function icon() {
		return 'dashicons-star-filled';
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
		return array( 'label', 'description', 'required', 'scale', 'css_class' );
	}

	/**
	 * Scale option.
	 *
	 * @return array
	 */
	protected function custom_option_definitions() {
		return array(
			'scale' => array(
				'type'    => 'number',
				'label'   => __( 'Number of stars', 'glixform' ),
				'default' => 5,
				'min'     => 3,
				'max'     => 10,
				'step'    => 1,
			),
		);
	}

	/**
	 * Configured scale.
	 *
	 * @param array $field Field config.
	 * @return int
	 */
	private function scale( array $field ) {
		return min( 10, max( 3, (int) ( $field['scale'] ?? 5 ) ) );
	}

	/**
	 * "1" to scale.
	 *
	 * @param array $field Field config.
	 * @return string[]
	 */
	protected function choice_labels( array $field ) {
		return array_map( 'strval', range( 1, $this->scale( $field ) ) );
	}

	/**
	 * Nothing selected by default.
	 *
	 * @param array $field Field config.
	 * @return string
	 */
	public function default_value( array $field ) {
		return '';
	}

	/**
	 * The fieldset legend replaces the label.
	 *
	 * @param array  $field   Field config.
	 * @param string $html_id Input ID.
	 * @return string
	 */
	protected function render_label( array $field, $html_id ) {
		return '';
	}

	/**
	 * Stars: visually icons, accessible as "3 out of 5" radio buttons.
	 *
	 * @param array        $field Field config.
	 * @param string|array $value Value.
	 * @param array        $attrs Shared attributes.
	 * @return string
	 */
	protected function render_input( array $field, $value, array $attrs ) {
		$scale = $this->scale( $field );
		$html  = sprintf( '<fieldset class="glixform-rating" id="%s"', esc_attr( $attrs['id'] ) );
		if ( ! empty( $attrs['aria-describedby'] ) ) {
			$html .= sprintf( ' aria-describedby="%s"', esc_attr( $attrs['aria-describedby'] ) );
		}
		$html .= '>' . $this->render_legend( $field ) . '<div class="glixform-stars">';
		for ( $i = 1; $i <= $scale; $i++ ) {
			$id    = $attrs['id'] . '-' . $i;
			$html .= sprintf(
				'<input type="radio" class="glixform-star-input" id="%1$s" name="%2$s" value="%3$d"%4$s%5$s><label class="glixform-star" for="%1$s"><span class="screen-reader-text">%6$s</span><svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M12 2.5l2.9 6.2 6.8.7-5.1 4.6 1.5 6.7L12 17.3l-6.1 3.4 1.5-6.7L2.3 9.4l6.8-.7z"/></svg></label>',
				esc_attr( $id ),
				esc_attr( $attrs['name'] ),
				$i,
				(string) $i === (string) $value ? ' checked' : '',
				( 1 === $i && $this->html_required( $field ) ) ? ' required' : '',
				/* translators: 1: rating, 2: maximum rating. */
				esc_html( sprintf( __( '%1$d out of %2$d', 'glixform' ), $i, $scale ) )
			);
		}
		return $html . '</div></fieldset>';
	}

	/**
	 * "4 / 5".
	 *
	 * @param array        $field Field config.
	 * @param string|array $value Value.
	 * @return string
	 */
	public function format_value( array $field, $value ) {
		return is_string( $value ) && '' !== $value ? $value . ' / ' . $this->scale( $field ) : '';
	}

	/**
	 * Numeric value for logic.
	 *
	 * @param array        $field Field config.
	 * @param string|array $value Value.
	 * @return string
	 */
	public function logic_value( array $field, $value ) {
		return is_string( $value ) ? $value : '';
	}
}

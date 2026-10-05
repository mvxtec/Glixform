<?php
/**
 * Page break: splits a form into steps.
 *
 * @package Glixform
 */

namespace Glixform\Fields;

defined( 'ABSPATH' ) || exit;

/**
 * Marks the start of a new page. The renderer groups fields into pages;
 * without JavaScript all pages simply show one after another.
 */
class PageBreak extends AbstractField {

	/**
	 * Machine name.
	 *
	 * @return string
	 */
	public function type() {
		return 'pagebreak';
	}

	/**
	 * Display name.
	 *
	 * @return string
	 */
	public function name() {
		return __( 'Page Break', 'glixform' );
	}

	/**
	 * Builder icon.
	 *
	 * @return string
	 */
	public function icon() {
		return 'dashicons-admin-page';
	}

	/**
	 * Palette group.
	 *
	 * @return string
	 */
	public function category() {
		return 'layout';
	}

	/**
	 * No value.
	 *
	 * @return bool
	 */
	public function is_input() {
		return false;
	}

	/**
	 * Pages are always shown.
	 *
	 * @return bool
	 */
	public function supports_logic() {
		return false;
	}

	/**
	 * Supported options.
	 *
	 * @return string[]
	 */
	public function options() {
		return array( 'label', 'next_text', 'prev_text' );
	}

	/**
	 * Button labels.
	 *
	 * @return array
	 */
	protected function custom_option_definitions() {
		return array(
			'label'     => array(
				'type'    => 'text',
				'label'   => __( 'Next page title', 'glixform' ),
				'help'    => __( 'Shown in the progress bar.', 'glixform' ),
				'default' => '',
			),
			'next_text' => array(
				'type'    => 'text',
				'label'   => __( '"Next" button text', 'glixform' ),
				'default' => __( 'Next', 'glixform' ),
			),
			'prev_text' => array(
				'type'    => 'text',
				'label'   => __( '"Previous" button text', 'glixform' ),
				'default' => __( 'Previous', 'glixform' ),
			),
		);
	}

	/**
	 * Allow an empty title.
	 *
	 * @param array $config Raw config.
	 * @return array
	 */
	public function sanitize_config( array $config ) {
		$label          = isset( $config['label'] ) && is_scalar( $config['label'] ) ? sanitize_text_field( (string) $config['label'] ) : '';
		$clean          = parent::sanitize_config( $config );
		$clean['label'] = $label;
		foreach ( array( 'next_text', 'prev_text' ) as $key ) {
			if ( '' === $clean[ $key ] ) {
				$clean[ $key ] = $this->defaults()[ $key ];
			}
		}
		return $clean;
	}

	/**
	 * Rendered by the Renderer as page boundaries.
	 *
	 * @param array             $field   Field config.
	 * @param int               $form_id Form ID.
	 * @param string|array|null $value   Unused.
	 * @param string            $error   Unused.
	 * @return string
	 */
	public function render( array $field, $form_id, $value = null, $error = '' ) {
		return '';
	}

	/**
	 * Unused.
	 *
	 * @param array        $field Field config.
	 * @param string|array $value Value.
	 * @param array        $attrs Shared attributes.
	 * @return string
	 */
	protected function render_input( array $field, $value, array $attrs ) {
		return '';
	}
}

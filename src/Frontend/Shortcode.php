<?php
/**
 * [glixform id="123"] shortcode.
 *
 * @package Glixform
 */

namespace Glixform\Frontend;

use Glixform\Forms\FormRepository;

defined( 'ABSPATH' ) || exit;

/**
 * Embeds a form in content.
 */
class Shortcode {

	/**
	 * Forms.
	 *
	 * @var FormRepository
	 */
	private $forms;

	/**
	 * Renderer.
	 *
	 * @var Renderer
	 */
	private $renderer;

	/**
	 * Constructor.
	 *
	 * @param FormRepository $forms    Forms.
	 * @param Renderer       $renderer Renderer.
	 */
	public function __construct( FormRepository $forms, Renderer $renderer ) {
		$this->forms    = $forms;
		$this->renderer = $renderer;
	}

	/**
	 * Hook into WordPress.
	 */
	public function register_hooks() {
		add_shortcode( 'glixform', array( $this, 'render' ) );
	}

	/**
	 * Shortcode callback.
	 *
	 * @param array|string $atts Attributes: id, title ("true"/"false").
	 * @return string
	 */
	public function render( $atts ) {
		$atts = shortcode_atts(
			array(
				'id'    => 0,
				'title' => 'false',
			),
			$atts,
			'glixform'
		);

		$form = $this->forms->get( absint( $atts['id'] ), true );
		if ( ! $form ) {
			return current_user_can( 'edit_posts' )
				? '<p class="glixform-missing">' . esc_html__( 'Glixform: form not found.', 'glixform' ) . '</p>'
				: '';
		}

		return $this->renderer->render( $form, array( 'title' => filter_var( $atts['title'], FILTER_VALIDATE_BOOLEAN ) ) );
	}
}

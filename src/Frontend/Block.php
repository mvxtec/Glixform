<?php
/**
 * Gutenberg block.
 *
 * @package Glixform
 */

namespace Glixform\Frontend;

use Glixform\Forms\FormRepository;

defined( 'ABSPATH' ) || exit;

/**
 * "glixform/form" block: pick a form in the editor, rendered on the server.
 */
class Block {

	/**
	 * Forms.
	 *
	 * @var FormRepository
	 */
	private $forms;

	/**
	 * Constructor.
	 *
	 * @param FormRepository $forms Forms.
	 */
	public function __construct( FormRepository $forms ) {
		$this->forms = $forms;
	}

	/**
	 * Hook into WordPress.
	 */
	public function register_hooks() {
		add_action( 'init', array( $this, 'register' ) );
		add_action( 'enqueue_block_editor_assets', array( $this, 'editor_data' ) );
	}

	/**
	 * Register the block and its editor script.
	 */
	public function register() {
		wp_register_script(
			'glixform-block',
			GLIXFORM_URL . 'assets/js/block.js',
			array( 'wp-blocks', 'wp-element', 'wp-components', 'wp-block-editor', 'wp-server-side-render', 'wp-i18n' ),
			GLIXFORM_VERSION,
			true
		);

		register_block_type(
			'glixform/form',
			array(
				'api_version'     => 3,
				'title'           => __( 'Glixform', 'glixform' ),
				'description'     => __( 'Display a Glixform form.', 'glixform' ),
				'category'        => 'widgets',
				'icon'            => 'feedback',
				'keywords'        => array( 'form', 'contact' ),
				'editor_script'   => 'glixform-block',
				'editor_style'    => 'glixform',
				'attributes'      => array(
					'formId'    => array(
						'type'    => 'integer',
						'default' => 0,
					),
					'showTitle' => array(
						'type'    => 'boolean',
						'default' => false,
					),
				),
				'supports'        => array( 'html' => false ),
				'render_callback' => array( $this, 'render' ),
			)
		);
	}

	/**
	 * Pass the list of forms to the editor script.
	 */
	public function editor_data() {
		$forms = array();
		foreach ( $this->forms->all() as $post ) {
			$forms[] = array(
				'id'    => (int) $post->ID,
				'title' => $post->post_title,
			);
		}
		wp_add_inline_script(
			'glixform-block',
			'window.glixformBlock = ' . wp_json_encode(
				array(
					'forms'      => $forms,
					'newFormUrl' => admin_url( 'admin.php?page=glixform-builder' ),
				)
			) . ';',
			'before'
		);
	}

	/**
	 * Server-side render.
	 *
	 * @param array $attributes Block attributes.
	 * @return string
	 */
	public function render( $attributes ) {
		$form_id = absint( $attributes['formId'] ?? 0 );
		if ( ! $form_id ) {
			return '';
		}
		return do_shortcode(
			sprintf(
				'[glixform id="%d" title="%s"]',
				$form_id,
				empty( $attributes['showTitle'] ) ? 'false' : 'true'
			)
		);
	}
}

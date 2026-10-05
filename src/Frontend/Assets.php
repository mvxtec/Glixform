<?php
/**
 * Front-end scripts and styles.
 *
 * @package Glixform
 */

namespace Glixform\Frontend;

defined( 'ABSPATH' ) || exit;

/**
 * Assets are registered everywhere but only enqueued when a form is rendered.
 */
class Assets {

	/**
	 * Hook into WordPress.
	 */
	public function register_hooks() {
		add_action( 'init', array( $this, 'register' ) );
	}

	/**
	 * Register handles.
	 */
	public function register() {
		wp_register_style( 'glixform', GLIXFORM_URL . 'assets/css/frontend.css', array(), GLIXFORM_VERSION );
		wp_register_script( 'glixform', GLIXFORM_URL . 'assets/js/frontend.js', array(), GLIXFORM_VERSION, true );
		wp_localize_script(
			'glixform',
			'glixformSettings',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'i18n'    => array(
					'required'   => __( 'This field is required.', 'glixform' ),
					'email'      => __( 'Please enter a valid email address.', 'glixform' ),
					'number'     => __( 'Please enter a valid number.', 'glixform' ),
					'fixErrors'  => __( 'Please correct the errors below and submit again.', 'glixform' ),
					'networkErr' => __( 'Something went wrong. Please try again.', 'glixform' ),
					'sending'    => __( 'Sending…', 'glixform' ),
				),
			)
		);
	}

	/**
	 * Enqueue for the current page.
	 */
	public static function enqueue() {
		wp_enqueue_style( 'glixform' );
		wp_enqueue_script( 'glixform' );
	}
}

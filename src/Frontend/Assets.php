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
		wp_localize_script( 'glixform', 'glixformSettings', self::script_settings() );
	}

	/**
	 * Settings and strings for frontend.js.
	 *
	 * @return array
	 */
	public static function script_settings() {
		return array(
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'i18n'    => array(
				'required'     => __( 'This field is required.', 'glixform' ),
				'email'        => __( 'Please enter a valid email address.', 'glixform' ),
				'phone'        => __( 'Please enter a valid phone number.', 'glixform' ),
				'url'          => __( 'Please enter a valid website address.', 'glixform' ),
				'invalid'      => __( 'Please enter a valid value.', 'glixform' ),
				'parts'        => __( 'Please fill in all required parts of this field.', 'glixform' ),
				'consent'      => __( 'Please agree to continue.', 'glixform' ),
				'fileRequired' => __( 'Please choose a file.', 'glixform' ),
				/* translators: %d: maximum number of files. */
				'tooManyFiles' => __( 'You can upload up to %d files.', 'glixform' ),
				/* translators: %s: list of allowed file extensions. */
				'fileType'     => __( 'This file type is not allowed. Allowed types: %s.', 'glixform' ),
				/* translators: %s: maximum file size. */
				'fileSize'     => __( 'Files must be smaller than %s.', 'glixform' ),
				'fixErrors'    => __( 'Please correct the errors below and submit again.', 'glixform' ),
				'networkErr'   => __( 'Something went wrong. Please try again.', 'glixform' ),
				'sending'      => __( 'Sending…', 'glixform' ),
				'previewNote'  => __( 'Looks good! This is a preview, so nothing was sent.', 'glixform' ),
			),
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

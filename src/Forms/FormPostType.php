<?php
/**
 * Custom post type that stores forms.
 *
 * @package Glixform
 */

namespace Glixform\Forms;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the "glixform_form" post type. The form definition is JSON in post_content.
 */
class FormPostType {

	const POST_TYPE = 'glixform_form';

	/**
	 * Hook into WordPress.
	 */
	public function register_hooks() {
		add_action( 'init', array( $this, 'register' ) );
	}

	/**
	 * Register the post type. It is private: forms are managed on Glixform's own screens.
	 */
	public function register() {
		register_post_type(
			self::POST_TYPE,
			array(
				'labels'              => array(
					'name'          => __( 'Forms', 'glixform' ),
					'singular_name' => __( 'Form', 'glixform' ),
				),
				'public'              => false,
				'show_ui'             => false,
				'show_in_rest'        => false,
				'exclude_from_search' => true,
				'publicly_queryable'  => false,
				'rewrite'             => false,
				'query_var'           => false,
				'supports'            => array( 'title', 'revisions' ),
				'capability_type'     => 'post',
				'map_meta_cap'        => true,
			)
		);
	}
}

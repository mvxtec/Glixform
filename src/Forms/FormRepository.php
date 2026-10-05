<?php
/**
 * Reads and writes forms.
 *
 * @package Glixform
 */

namespace Glixform\Forms;

use Glixform\Fields\FieldRegistry;

defined( 'ABSPATH' ) || exit;

/**
 * Form data shape (stored as JSON in post_content):
 *
 * [
 *   'fields'        => [ field config, ... ],
 *   'settings'      => [ see default_settings() ],
 *   'next_field_id' => int,
 * ]
 */
class FormRepository {

	/**
	 * Field registry.
	 *
	 * @var FieldRegistry
	 */
	private $fields;

	/**
	 * Constructor.
	 *
	 * @param FieldRegistry $fields Field registry.
	 */
	public function __construct( FieldRegistry $fields ) {
		$this->fields = $fields;
	}

	/**
	 * Default per-form settings.
	 *
	 * @return array
	 */
	public static function default_settings() {
		return array(
			'submit_text'   => __( 'Submit', 'glixform' ),
			'store_entries' => true,
			'confirmation'  => array(
				'type'    => 'message',
				'message' => __( 'Thanks for contacting us! We will be in touch with you shortly.', 'glixform' ),
				'url'     => '',
				'page_id' => 0,
			),
			'notification'  => array(
				'enabled'   => true,
				'to'        => '{admin_email}',
				'subject'   => __( 'New entry: {form_name}', 'glixform' ),
				'from_name' => '{site_name}',
				'reply_to'  => '',
				'message'   => '{all_fields}',
			),
		);
	}

	/**
	 * Get a form with its decoded data.
	 *
	 * @param int  $form_id      Form post ID.
	 * @param bool $require_live Only return published forms.
	 * @return array|null [ 'id', 'title', 'status', 'data' ]
	 */
	public function get( $form_id, $require_live = false ) {
		$post = get_post( absint( $form_id ) );
		if ( ! $post || FormPostType::POST_TYPE !== $post->post_type ) {
			return null;
		}
		if ( $require_live && 'publish' !== $post->post_status ) {
			return null;
		}
		return array(
			'id'     => (int) $post->ID,
			'title'  => $post->post_title,
			'status' => $post->post_status,
			'date'   => $post->post_date,
			'data'   => $this->decode( $post->post_content ),
		);
	}

	/**
	 * All forms, newest first.
	 *
	 * @param array $args Extra get_posts() args.
	 * @return \WP_Post[]
	 */
	public function all( array $args = array() ) {
		return get_posts(
			array_merge(
				array(
					'post_type'      => FormPostType::POST_TYPE,
					'post_status'    => 'publish',
					'posts_per_page' => -1,
					'orderby'        => 'date',
					'order'          => 'DESC',
				),
				$args
			)
		);
	}

	/**
	 * Create or update a form. The data is sanitized here, so callers may pass raw builder input.
	 *
	 * @param int    $form_id 0 to create.
	 * @param string $title   Form title.
	 * @param array  $data    Raw form data.
	 * @return int|\WP_Error Form ID.
	 */
	public function save( $form_id, $title, array $data ) {
		$data  = $this->sanitize( $data );
		$title = sanitize_text_field( $title );

		$postarr = array(
			'post_type'    => FormPostType::POST_TYPE,
			'post_status'  => 'publish',
			'post_title'   => '' !== $title ? $title : __( 'Untitled Form', 'glixform' ),
			// wp_insert_post() unslashes its input, so slash the JSON to keep backslashes intact.
			'post_content' => wp_slash( wp_json_encode( $data ) ),
		);

		if ( $form_id ) {
			$postarr['ID'] = absint( $form_id );
			return wp_update_post( $postarr, true );
		}

		return wp_insert_post( $postarr, true );
	}

	/**
	 * Duplicate a form.
	 *
	 * @param int $form_id Source form.
	 * @return int|\WP_Error New form ID.
	 */
	public function duplicate( $form_id ) {
		$form = $this->get( $form_id );
		if ( ! $form ) {
			return new \WP_Error( 'glixform_not_found', __( 'Form not found.', 'glixform' ) );
		}
		/* translators: %s: original form title. */
		return $this->save( 0, sprintf( __( '%s (copy)', 'glixform' ), $form['title'] ), $form['data'] );
	}

	/**
	 * Permanently delete a form (entries are deleted separately).
	 *
	 * @param int $form_id Form ID.
	 * @return bool
	 */
	public function delete( $form_id ) {
		$form = $this->get( $form_id );
		return $form ? (bool) wp_delete_post( $form['id'], true ) : false;
	}

	/**
	 * Decode stored JSON, filling in defaults.
	 *
	 * @param string $json Stored content.
	 * @return array
	 */
	public function decode( $json ) {
		$data = json_decode( (string) $json, true );
		if ( ! is_array( $data ) ) {
			$data = array();
		}
		$data['fields']        = isset( $data['fields'] ) && is_array( $data['fields'] ) ? array_values( $data['fields'] ) : array();
		$data['settings']      = $this->merge_settings( $data['settings'] ?? array() );
		$data['next_field_id'] = max( 1, (int) ( $data['next_field_id'] ?? 1 ) );
		return $data;
	}

	/**
	 * Deep-merge saved settings onto defaults.
	 *
	 * @param mixed $settings Saved settings.
	 * @return array
	 */
	private function merge_settings( $settings ) {
		$defaults = self::default_settings();
		$settings = is_array( $settings ) ? $settings : array();
		$merged   = array_merge( $defaults, $settings );
		foreach ( array( 'confirmation', 'notification' ) as $group ) {
			$merged[ $group ] = array_merge( $defaults[ $group ], is_array( $settings[ $group ] ?? null ) ? $settings[ $group ] : array() );
		}
		return $merged;
	}

	/**
	 * Sanitize a complete form definition. Unknown field types are dropped and
	 * field IDs are made unique.
	 *
	 * @param array $data Raw data.
	 * @return array
	 */
	public function sanitize( array $data ) {
		$fields  = array();
		$used    = array();
		$next_id = max( 1, absint( $data['next_field_id'] ?? 1 ) );

		foreach ( (array) ( $data['fields'] ?? array() ) as $config ) {
			if ( ! is_array( $config ) ) {
				continue;
			}
			$type = $this->fields->get( (string) ( $config['type'] ?? '' ) );
			if ( ! $type ) {
				continue;
			}
			$clean = $type->sanitize_config( $config );
			if ( ! $clean['id'] || isset( $used[ $clean['id'] ] ) ) {
				$clean['id'] = $next_id;
			}
			$used[ $clean['id'] ] = true;
			$next_id              = max( $next_id, $clean['id'] + 1 );
			$fields[]             = $clean;
		}

		$raw  = $this->merge_settings( $data['settings'] ?? array() );
		$conf = $raw['confirmation'];
		$noti = $raw['notification'];

		$settings = array(
			'submit_text'   => sanitize_text_field( (string) $raw['submit_text'] ),
			'store_entries' => (bool) $raw['store_entries'],
			'confirmation'  => array(
				'type'    => in_array( $conf['type'], array( 'message', 'redirect', 'page' ), true ) ? $conf['type'] : 'message',
				'message' => wp_kses_post( (string) $conf['message'] ),
				'url'     => esc_url_raw( (string) $conf['url'] ),
				'page_id' => absint( $conf['page_id'] ),
			),
			'notification'  => array(
				'enabled'   => (bool) $noti['enabled'],
				'to'        => sanitize_text_field( (string) $noti['to'] ),
				'subject'   => sanitize_text_field( (string) $noti['subject'] ),
				'from_name' => sanitize_text_field( (string) $noti['from_name'] ),
				'reply_to'  => sanitize_text_field( (string) $noti['reply_to'] ),
				'message'   => wp_kses_post( (string) $noti['message'] ),
			),
		);

		if ( '' === $settings['submit_text'] ) {
			$settings['submit_text'] = __( 'Submit', 'glixform' );
		}

		return array(
			'fields'        => $fields,
			'settings'      => $settings,
			'next_field_id' => $next_id,
		);
	}

	/**
	 * Starter template for a new form: a simple contact form.
	 *
	 * @return array
	 */
	public function contact_template() {
		return $this->sanitize(
			array(
				'fields'        => array(
					array(
						'id'       => 1,
						'type'     => 'text',
						'label'    => __( 'Name', 'glixform' ),
						'required' => true,
					),
					array(
						'id'       => 2,
						'type'     => 'email',
						'label'    => __( 'Email', 'glixform' ),
						'required' => true,
					),
					array(
						'id'       => 3,
						'type'     => 'textarea',
						'label'    => __( 'Message', 'glixform' ),
						'required' => true,
					),
				),
				'next_field_id' => 4,
			)
		);
	}
}

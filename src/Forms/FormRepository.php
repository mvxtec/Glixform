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
 *   'fields'        => [ field config (+ 'conditional'), ... ],
 *   'settings'      => [ see default_settings() ],
 *   'next_field_id' => int,
 * ]
 *
 * Forms saved by Glixform 0.1 (one "notification" and one "confirmation")
 * are upgraded on read by decode().
 */
class FormRepository {

	const MAX_NOTIFICATIONS = 20;

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
			'submit_text'     => __( 'Submit', 'glixform' ),
			'processing_text' => __( 'Sending…', 'glixform' ),
			'store_entries'   => true,
			'captcha'         => false,
			'akismet'         => false,
			'progress'        => 'bar',
			'notifications'   => array( self::default_notification( 1 ) ),
			'confirmations'   => array( self::default_confirmation( 1 ) ),
		);
	}

	/**
	 * A new notification.
	 *
	 * @param int $id Notification ID.
	 * @return array
	 */
	public static function default_notification( $id ) {
		return array(
			'id'          => (int) $id,
			'name'        => __( 'Admin notification', 'glixform' ),
			'enabled'     => true,
			'to'          => '{admin_email}',
			'subject'     => __( 'New entry: {form_name}', 'glixform' ),
			'from_name'   => '{site_name}',
			'reply_to'    => '',
			'message'     => '{all_fields}',
			'conditional' => ConditionalLogic::defaults(),
		);
	}

	/**
	 * A new confirmation.
	 *
	 * @param int $id Confirmation ID.
	 * @return array
	 */
	public static function default_confirmation( $id ) {
		return array(
			'id'          => (int) $id,
			'name'        => __( 'Default confirmation', 'glixform' ),
			'type'        => 'message',
			'message'     => __( 'Thanks for contacting us! We will be in touch with you shortly.', 'glixform' ),
			'url'         => '',
			'page_id'     => 0,
			'conditional' => ConditionalLogic::defaults(),
		);
	}

	/**
	 * Get a form with its decoded data.
	 *
	 * @param int  $form_id      Form post ID.
	 * @param bool $require_live Only return published forms.
	 * @return array|null [ 'id', 'title', 'status', 'date', 'data' ]
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
			'id'       => (int) $post->ID,
			'title'    => $post->post_title,
			'status'   => $post->post_status,
			'date'     => $post->post_date,
			'modified' => $post->post_modified,
			'data'     => $this->decode( $post->post_content ),
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
	 * Decode stored JSON, upgrading old formats and filling in defaults.
	 *
	 * @param string $json Stored content.
	 * @return array
	 */
	public function decode( $json ) {
		$data = json_decode( (string) $json, true );
		if ( ! is_array( $data ) ) {
			$data = array();
		}

		$fields = isset( $data['fields'] ) && is_array( $data['fields'] ) ? array_values( $data['fields'] ) : array();
		foreach ( $fields as &$field ) {
			if ( is_array( $field ) ) {
				$field['conditional'] = isset( $field['conditional'] ) && is_array( $field['conditional'] )
					? array_merge( ConditionalLogic::defaults(), $field['conditional'] )
					: ConditionalLogic::defaults();
			}
		}
		unset( $field );

		$data['fields']        = $fields;
		$data['settings']      = $this->merge_settings( $data['settings'] ?? array() );
		$data['next_field_id'] = max( 1, (int) ( $data['next_field_id'] ?? 1 ) );
		return $data;
	}

	/**
	 * Merge saved settings onto defaults; upgrade the 0.1 single notification/confirmation.
	 *
	 * @param mixed $settings Saved settings.
	 * @return array
	 */
	private function merge_settings( $settings ) {
		$settings = is_array( $settings ) ? $settings : array();
		$defaults = self::default_settings();

		if ( ! isset( $settings['notifications'] ) && isset( $settings['notification'] ) && is_array( $settings['notification'] ) ) {
			$settings['notifications'] = array( array( 'id' => 1 ) + $settings['notification'] );
		}
		if ( ! isset( $settings['confirmations'] ) && isset( $settings['confirmation'] ) && is_array( $settings['confirmation'] ) ) {
			$settings['confirmations'] = array( array( 'id' => 1 ) + $settings['confirmation'] );
		}
		unset( $settings['notification'], $settings['confirmation'] );

		$merged = array_merge( $defaults, $settings );

		$merged['notifications'] = $this->merge_list( $merged['notifications'], array( __CLASS__, 'default_notification' ), false );
		$merged['confirmations'] = $this->merge_list( $merged['confirmations'], array( __CLASS__, 'default_confirmation' ), true );

		return $merged;
	}

	/**
	 * Fill each list item with defaults.
	 *
	 * @param mixed    $items         Items.
	 * @param callable $make_default  Default factory taking an ID.
	 * @param bool     $require_one   Keep at least one item.
	 * @return array
	 */
	private function merge_list( $items, callable $make_default, $require_one ) {
		$out = array();
		foreach ( is_array( $items ) ? $items : array() as $index => $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}
			$base                  = call_user_func( $make_default, absint( $item['id'] ?? ( $index + 1 ) ) );
			$merged                = array_merge( $base, $item );
			$merged['id']          = $base['id'] ? $base['id'] : $index + 1;
			$merged['conditional'] = is_array( $merged['conditional'] ) ? array_merge( ConditionalLogic::defaults(), $merged['conditional'] ) : ConditionalLogic::defaults();
			$out[]                 = $merged;
		}
		if ( $require_one && ! $out ) {
			$out[] = call_user_func( $make_default, 1 );
		}
		return $out;
	}

	/**
	 * Sanitize a complete form definition. Unknown field types are dropped,
	 * field IDs are made unique and conditions may only reference real fields.
	 *
	 * @param array $data Raw data.
	 * @return array
	 */
	public function sanitize( array $data ) {
		$fields  = array();
		$used    = array();
		$raw_map = array();
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
			$used[ $clean['id'] ]    = true;
			$next_id                 = max( $next_id, $clean['id'] + 1 );
			$raw_map[ $clean['id'] ] = $config['conditional'] ?? array();
			$fields[]                = $clean;
		}

		// Conditions may reference input fields only.
		$input_ids = array();
		foreach ( $fields as $field ) {
			$type = $this->fields->get( $field['type'] );
			if ( $type && $type->is_input() ) {
				$input_ids[] = (int) $field['id'];
			}
		}

		foreach ( $fields as &$field ) {
			$type                 = $this->fields->get( $field['type'] );
			$field['conditional'] = $type && $type->supports_logic()
				? ConditionalLogic::sanitize( $raw_map[ $field['id'] ], $input_ids, $field['id'] )
				: ConditionalLogic::defaults();
		}
		unset( $field );

		$raw = $this->merge_settings( $data['settings'] ?? array() );

		$settings = array(
			'submit_text'     => sanitize_text_field( (string) $raw['submit_text'] ),
			'processing_text' => sanitize_text_field( (string) $raw['processing_text'] ),
			'store_entries'   => (bool) $raw['store_entries'],
			'captcha'         => (bool) $raw['captcha'],
			'akismet'         => (bool) $raw['akismet'],
			'progress'        => in_array( $raw['progress'], array( 'bar', 'steps', 'none' ), true ) ? $raw['progress'] : 'bar',
			'notifications'   => array(),
			'confirmations'   => array(),
		);

		foreach ( array( 'submit_text', 'processing_text' ) as $key ) {
			if ( '' === $settings[ $key ] ) {
				$settings[ $key ] = self::default_settings()[ $key ];
			}
		}

		$ids = array();
		foreach ( array_slice( $raw['notifications'], 0, self::MAX_NOTIFICATIONS ) as $item ) {
			$id                          = $this->unique_id( (int) $item['id'], $ids );
			$settings['notifications'][] = array(
				'id'          => $id,
				'name'        => sanitize_text_field( (string) $item['name'] ),
				'enabled'     => (bool) $item['enabled'],
				'to'          => sanitize_text_field( (string) $item['to'] ),
				'subject'     => sanitize_text_field( (string) $item['subject'] ),
				'from_name'   => sanitize_text_field( (string) $item['from_name'] ),
				'reply_to'    => sanitize_text_field( (string) $item['reply_to'] ),
				'message'     => wp_kses_post( (string) $item['message'] ),
				'conditional' => ConditionalLogic::sanitize( $item['conditional'], $input_ids ),
			);
		}

		$ids = array();
		foreach ( array_slice( $raw['confirmations'], 0, self::MAX_NOTIFICATIONS ) as $item ) {
			$id                          = $this->unique_id( (int) $item['id'], $ids );
			$settings['confirmations'][] = array(
				'id'          => $id,
				'name'        => sanitize_text_field( (string) $item['name'] ),
				'type'        => in_array( $item['type'], array( 'message', 'redirect', 'page' ), true ) ? $item['type'] : 'message',
				'message'     => wp_kses_post( (string) $item['message'] ),
				'url'         => esc_url_raw( (string) $item['url'], array( 'http', 'https' ) ),
				'page_id'     => absint( $item['page_id'] ),
				'conditional' => ConditionalLogic::sanitize( $item['conditional'], $input_ids ),
			);
		}

		return array(
			'fields'        => $fields,
			'settings'      => $settings,
			'next_field_id' => $next_id,
		);
	}

	/**
	 * Keep list item IDs unique and positive.
	 *
	 * @param int   $id   Proposed ID.
	 * @param array $used Used IDs (updated).
	 * @return int
	 */
	private function unique_id( $id, array &$used ) {
		if ( $id < 1 || isset( $used[ $id ] ) ) {
			$id = $used ? max( array_keys( $used ) ) + 1 : 1;
		}
		$used[ $id ] = true;
		return $id;
	}
}

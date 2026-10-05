<?php
/**
 * REST API: /wp-json/glixform/v1/...
 *
 * @package Glixform
 */

namespace Glixform\Rest;

use Glixform\Frontend\Preview;
use Glixform\Plugin;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

/**
 * Routes (all require the Glixform capability; cookie + nonce or application passwords):
 *
 * GET    /forms                 List forms.
 * POST   /forms                 Create a form { title, data }.
 * GET    /forms/{id}            Get a form.
 * POST   /forms/{id}            Update a form { title, data }.
 * DELETE /forms/{id}            Delete a form and its entries.
 * GET    /forms/{id}/entries    List entries (page, per_page, search, status).
 * GET    /entries/{id}          Get an entry.
 * DELETE /entries/{id}          Delete an entry.
 * GET    /templates             List form templates.
 * POST   /preview               Render unsaved form data { title, data } to HTML.
 */
class RestController {

	const NAMESPACE = 'glixform/v1';

	/**
	 * Plugin.
	 *
	 * @var Plugin
	 */
	private $plugin;

	/**
	 * Constructor.
	 *
	 * @param Plugin $plugin Plugin.
	 */
	public function __construct( Plugin $plugin ) {
		$this->plugin = $plugin;
	}

	/**
	 * Hook into WordPress.
	 */
	public function register_hooks() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Permission check for every route.
	 *
	 * @return bool
	 */
	public function can_manage() {
		return current_user_can( Plugin::capability() );
	}

	/**
	 * Register routes.
	 */
	public function register_routes() {
		$id_arg    = array(
			'id' => array(
				'type'              => 'integer',
				'required'          => true,
				'sanitize_callback' => 'absint',
			),
		);
		$form_args = array(
			'title' => array(
				'type'     => 'string',
				'required' => false,
			),
			'data'  => array(
				'type'     => 'object',
				'required' => false,
			),
		);
		$perm      = array( $this, 'can_manage' );

		register_rest_route(
			self::NAMESPACE,
			'/forms',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'list_forms' ),
					'permission_callback' => $perm,
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_form' ),
					'permission_callback' => $perm,
					'args'                => $form_args,
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/forms/(?P<id>\d+)',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_form' ),
					'permission_callback' => $perm,
					'args'                => $id_arg,
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_form' ),
					'permission_callback' => $perm,
					'args'                => $id_arg + $form_args,
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'delete_form' ),
					'permission_callback' => $perm,
					'args'                => $id_arg,
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/forms/(?P<id>\d+)/entries',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'list_entries' ),
				'permission_callback' => $perm,
				'args'                => $id_arg + array(
					'page'     => array(
						'type'    => 'integer',
						'default' => 1,
						'minimum' => 1,
					),
					'per_page' => array(
						'type'    => 'integer',
						'default' => 20,
						'minimum' => 1,
						'maximum' => 100,
					),
					'search'   => array(
						'type'    => 'string',
						'default' => '',
					),
					'status'   => array(
						'type'    => 'string',
						'default' => '',
						'enum'    => array( '', 'read', 'unread', 'spam' ),
					),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/entries/(?P<id>\d+)',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_entry' ),
					'permission_callback' => $perm,
					'args'                => $id_arg,
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'delete_entry' ),
					'permission_callback' => $perm,
					'args'                => $id_arg,
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/templates',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'list_templates' ),
				'permission_callback' => $perm,
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/preview',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'preview' ),
				'permission_callback' => $perm,
				'args'                => $form_args,
			)
		);
	}

	/**
	 * Form as returned by the API.
	 *
	 * @param array $form Form.
	 * @return array
	 */
	private function format_form( array $form ) {
		return array(
			'id'         => $form['id'],
			'title'      => $form['title'],
			'date'       => $form['date'],
			'modified'   => $form['modified'] ?? $form['date'],
			'shortcode'  => sprintf( '[glixform id="%d"]', $form['id'] ),
			'previewUrl' => Preview::url( $form['id'] ),
			'data'       => $form['data'],
		);
	}

	/**
	 * GET /forms.
	 *
	 * @return WP_REST_Response
	 */
	public function list_forms() {
		$counts = $this->plugin->entries->counts_by_form();
		$out    = array();
		foreach ( $this->plugin->forms->all() as $post ) {
			$out[] = array(
				'id'      => (int) $post->ID,
				'title'   => $post->post_title,
				'date'    => $post->post_date,
				'entries' => $counts[ $post->ID ] ?? 0,
			);
		}
		return rest_ensure_response( $out );
	}

	/**
	 * GET /forms/{id}.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_form( WP_REST_Request $request ) {
		$form = $this->plugin->forms->get( $request['id'] );
		return $form ? rest_ensure_response( $this->format_form( $form ) ) : $this->not_found();
	}

	/**
	 * POST /forms.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function create_form( WP_REST_Request $request ) {
		$id = $this->plugin->forms->save( 0, (string) $request['title'], (array) $request['data'] );
		if ( is_wp_error( $id ) ) {
			return $id;
		}
		$response = rest_ensure_response( $this->format_form( $this->plugin->forms->get( $id ) ) );
		$response->set_status( 201 );
		return $response;
	}

	/**
	 * POST /forms/{id}.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function update_form( WP_REST_Request $request ) {
		$form = $this->plugin->forms->get( $request['id'] );
		if ( ! $form ) {
			return $this->not_found();
		}
		$title = null === $request['title'] ? $form['title'] : (string) $request['title'];
		$data  = null === $request['data'] ? $form['data'] : (array) $request['data'];
		$id    = $this->plugin->forms->save( $form['id'], $title, $data );
		if ( is_wp_error( $id ) ) {
			return $id;
		}
		return rest_ensure_response( $this->format_form( $this->plugin->forms->get( $id ) ) );
	}

	/**
	 * DELETE /forms/{id}.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function delete_form( WP_REST_Request $request ) {
		if ( ! $this->plugin->forms->delete( $request['id'] ) ) {
			return $this->not_found();
		}
		$this->plugin->entries->delete_by_form( $request['id'] );
		return rest_ensure_response( array( 'deleted' => true ) );
	}

	/**
	 * GET /forms/{id}/entries.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function list_entries( WP_REST_Request $request ) {
		if ( ! $this->plugin->forms->get( $request['id'] ) ) {
			return $this->not_found();
		}
		$args    = array(
			'form_id' => $request['id'],
			'search'  => sanitize_text_field( (string) $request['search'] ),
			'status'  => (string) $request['status'],
		);
		$total   = $this->plugin->entries->count( $args );
		$entries = $this->plugin->entries->query(
			$args + array(
				'page'     => (int) $request['page'],
				'per_page' => (int) $request['per_page'],
			)
		);

		$response = rest_ensure_response( array_map( array( $this, 'format_entry' ), $entries ) );
		$response->header( 'X-WP-Total', (string) $total );
		$response->header( 'X-WP-TotalPages', (string) (int) ceil( $total / max( 1, (int) $request['per_page'] ) ) );
		return $response;
	}

	/**
	 * GET /entries/{id}.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_entry( WP_REST_Request $request ) {
		$entry = $this->plugin->entries->get( $request['id'] );
		return $entry ? rest_ensure_response( $this->format_entry( $entry ) ) : $this->not_found();
	}

	/**
	 * DELETE /entries/{id}.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function delete_entry( WP_REST_Request $request ) {
		return $this->plugin->entries->delete( array( $request['id'] ) )
			? rest_ensure_response( array( 'deleted' => true ) )
			: $this->not_found();
	}

	/**
	 * Entry as returned by the API (file paths are not exposed).
	 *
	 * @param array $entry Entry.
	 * @return array
	 */
	public function format_entry( array $entry ) {
		$fields = array();
		foreach ( $entry['fields'] as $item ) {
			$fields[] = array(
				'id'    => (int) $item['id'],
				'type'  => $item['type'],
				'label' => $item['label'],
				'value' => \Glixform\Notifications\SmartTags::item_text( $item ),
			);
		}
		return array(
			'id'         => $entry['id'],
			'form_id'    => $entry['form_id'],
			'status'     => $entry['status'],
			'fields'     => $fields,
			'user_id'    => $entry['user_id'],
			'ip_address' => $entry['ip_address'],
			'page_url'   => $entry['page_url'],
			'created_at' => mysql_to_rfc3339( $entry['created_at'] ),
		);
	}

	/**
	 * GET /templates.
	 *
	 * @return WP_REST_Response
	 */
	public function list_templates() {
		return rest_ensure_response( $this->plugin->templates->all() );
	}

	/**
	 * POST /preview: render unsaved form data exactly like the front end.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function preview( WP_REST_Request $request ) {
		$form = array(
			'id'    => 0,
			'title' => sanitize_text_field( (string) $request['title'] ),
			'data'  => $this->plugin->forms->sanitize( (array) $request['data'] ),
		);
		return rest_ensure_response(
			array(
				'html'  => $this->plugin->renderer->render( $form, array( 'preview' => true ) ),
				'pages' => count( $this->plugin->renderer->pages( $form['data']['fields'] ) ),
			)
		);
	}

	/**
	 * 404 error.
	 *
	 * @return WP_Error
	 */
	private function not_found() {
		return new WP_Error( 'glixform_not_found', __( 'Not found.', 'glixform' ), array( 'status' => 404 ) );
	}
}

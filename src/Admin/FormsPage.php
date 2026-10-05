<?php
/**
 * "All Forms" screen.
 *
 * @package Glixform
 */

namespace Glixform\Admin;

use Glixform\Plugin;

defined( 'ABSPATH' ) || exit;

/**
 * Lists forms and handles duplicate/delete.
 */
class FormsPage {

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
		add_action( 'admin_post_glixform_form_action', array( $this, 'handle_action' ) );
	}

	/**
	 * Signed URL for a form action.
	 *
	 * @param string $action  "duplicate" or "delete".
	 * @param int    $form_id Form ID.
	 * @return string
	 */
	public static function action_url( $action, $form_id ) {
		return wp_nonce_url(
			add_query_arg(
				array(
					'action'      => 'glixform_form_action',
					'form_action' => $action,
					'form_id'     => absint( $form_id ),
				),
				admin_url( 'admin-post.php' )
			),
			'glixform_form_' . $action . '_' . absint( $form_id )
		);
	}

	/**
	 * Duplicate or delete a form.
	 */
	public function handle_action() {
		Admin::check_permission();

		$action  = isset( $_GET['form_action'] ) ? sanitize_key( wp_unslash( $_GET['form_action'] ) ) : '';
		$form_id = isset( $_GET['form_id'] ) ? absint( $_GET['form_id'] ) : 0;

		check_admin_referer( 'glixform_form_' . $action . '_' . $form_id );

		$notice = '';
		if ( 'duplicate' === $action ) {
			$result = $this->plugin->forms->duplicate( $form_id );
			$notice = is_wp_error( $result ) ? '' : 'duplicated';
		} elseif ( 'delete' === $action ) {
			if ( $this->plugin->forms->delete( $form_id ) ) {
				$this->plugin->entries->delete_by_form( $form_id );
				$notice = 'deleted';
			}
		}

		wp_safe_redirect( add_query_arg( 'glixform_notice', $notice, admin_url( 'admin.php?page=glixform' ) ) );
		exit;
	}

	/**
	 * Render the screen.
	 */
	public function render() {
		Admin::check_permission();

		$forms = $this->plugin->forms->all();
		echo '<div class="wrap glixform-admin">';
		Admin::header(
			__( 'Forms', 'glixform' ),
			array(
				array(
					'url'     => admin_url( 'admin.php?page=glixform-builder' ),
					'label'   => __( 'Add new form', 'glixform' ),
					'icon'    => 'dashicons-plus-alt2',
					'primary' => true,
				),
			)
		);
		Admin::notice(
			array(
				'duplicated' => __( 'Form duplicated.', 'glixform' ),
				'deleted'    => __( 'Form and its entries deleted.', 'glixform' ),
				'imported'   => __( 'Forms imported.', 'glixform' ),
			)
		);

		if ( ! $forms ) {
			Admin::empty_state(
				'dashicons-feedback',
				__( 'Build your first form', 'glixform' ),
				__( 'Pick a template or start from scratch. It takes about a minute.', 'glixform' ),
				admin_url( 'admin.php?page=glixform-builder' ),
				__( 'Create a form', 'glixform' )
			);
		} else {
			$table = new FormsListTable( $forms, $this->plugin->entries->counts_by_form() );
			$table->prepare_items();
			echo '<div class="glixform-card glixform-table-card">';
			$table->display();
			echo '</div>';
		}
		echo '</div>';
		Admin::confirm_script();
	}
}

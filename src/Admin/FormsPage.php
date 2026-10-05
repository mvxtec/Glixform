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

		$table = new FormsListTable( $this->plugin->forms->all(), $this->plugin->entries->counts_by_form() );
		$table->prepare_items();
		?>
		<div class="wrap glixform-wrap">
			<h1 class="wp-heading-inline"><?php esc_html_e( 'Forms', 'glixform' ); ?></h1>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=glixform-builder' ) ); ?>" class="page-title-action"><?php esc_html_e( 'Add New Form', 'glixform' ); ?></a>
			<hr class="wp-header-end">
			<?php
			Admin::notice(
				array(
					'duplicated' => __( 'Form duplicated.', 'glixform' ),
					'deleted'    => __( 'Form and its entries deleted.', 'glixform' ),
				)
			);
			$table->display();
			?>
		</div>
		<script>
		document.addEventListener( 'click', function ( e ) {
			var link = e.target.closest( '.glixform-confirm' );
			if ( link && ! window.confirm( link.getAttribute( 'data-confirm' ) ) ) {
				e.preventDefault();
			}
		} );
		</script>
		<?php
	}
}

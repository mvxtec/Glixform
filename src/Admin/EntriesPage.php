<?php
/**
 * Entries screens: list, single entry, bulk actions and export.
 *
 * @package Glixform
 */

namespace Glixform\Admin;

use Glixform\Notifications\SmartTags;
use Glixform\Plugin;

defined( 'ABSPATH' ) || exit;

/**
 * Entries admin.
 */
class EntriesPage {

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
		add_action( 'admin_init', array( $this, 'handle_actions' ) );
		add_action( 'admin_post_glixform_export_entries', array( $this, 'export' ) );
	}

	/**
	 * URL of a single entry.
	 *
	 * @param int $form_id  Form ID.
	 * @param int $entry_id Entry ID.
	 * @return string
	 */
	public static function entry_url( $form_id, $entry_id ) {
		return admin_url( sprintf( 'admin.php?page=glixform-entries&form_id=%d&entry_id=%d', $form_id, $entry_id ) );
	}

	/**
	 * Signed URL to delete one entry.
	 *
	 * @param int $form_id  Form ID.
	 * @param int $entry_id Entry ID.
	 * @return string
	 */
	public static function delete_url( $form_id, $entry_id ) {
		return wp_nonce_url(
			admin_url( sprintf( 'admin.php?page=glixform-entries&form_id=%d&glixform_action=delete_entry&entry_id=%d', $form_id, $entry_id ) ),
			'glixform_delete_entry_' . $entry_id
		);
	}

	/**
	 * Handle single delete and bulk actions before any output.
	 */
	public function handle_actions() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Nonces are checked below per action.
		if ( ! isset( $_GET['page'] ) || 'glixform-entries' !== $_GET['page'] ) {
			return;
		}

		$form_id = isset( $_GET['form_id'] ) ? absint( $_GET['form_id'] ) : 0;
		$back    = admin_url( 'admin.php?page=glixform-entries&form_id=' . $form_id );

		if ( isset( $_GET['glixform_action'] ) && 'delete_entry' === $_GET['glixform_action'] ) {
			Admin::check_permission();
			$entry_id = isset( $_GET['entry_id'] ) ? absint( $_GET['entry_id'] ) : 0;
			check_admin_referer( 'glixform_delete_entry_' . $entry_id );
			$this->plugin->entries->delete( array( $entry_id ) );
			wp_safe_redirect( add_query_arg( 'glixform_notice', 'deleted', $back ) );
			exit;
		}

		$action = isset( $_GET['action'] ) && '-1' !== $_GET['action'] ? sanitize_key( wp_unslash( $_GET['action'] ) ) : '';
		if ( '' === $action && isset( $_GET['action2'] ) && '-1' !== $_GET['action2'] ) {
			$action = sanitize_key( wp_unslash( $_GET['action2'] ) );
		}
		$ids = isset( $_GET['entry_ids'] ) ? array_map( 'absint', (array) wp_unslash( $_GET['entry_ids'] ) ) : array();
		// phpcs:enable

		if ( ! $action || ! $ids ) {
			return;
		}

		Admin::check_permission();
		check_admin_referer( 'bulk-entries' );

		// Only touch entries that belong to the form being viewed.
		$ids = array_values(
			array_filter(
				$ids,
				function ( $id ) use ( $form_id ) {
					$entry = $this->plugin->entries->get( $id );
					return $entry && $entry['form_id'] === $form_id;
				}
			)
		);

		$notice = '';
		if ( 'delete' === $action ) {
			$this->plugin->entries->delete( $ids );
			$notice = 'deleted';
		} elseif ( 'mark_read' === $action || 'mark_unread' === $action ) {
			$this->plugin->entries->set_status( $ids, 'mark_read' === $action ? 'read' : 'unread' );
			$notice = 'updated';
		}

		wp_safe_redirect( add_query_arg( 'glixform_notice', $notice, $back ) );
		exit;
	}

	/**
	 * CSV export handler.
	 */
	public function export() {
		Admin::check_permission();
		check_admin_referer( 'glixform_export_entries' );

		$form = $this->plugin->forms->get( isset( $_GET['form_id'] ) ? absint( $_GET['form_id'] ) : 0 );
		if ( ! $form ) {
			wp_die( esc_html__( 'Form not found.', 'glixform' ), 404 );
		}

		( new CsvExporter( $this->plugin->entries ) )->download( $form );
	}

	/**
	 * Render the screen.
	 */
	public function render() {
		Admin::check_permission();

		$forms = $this->plugin->forms->all();
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		$form_id  = isset( $_GET['form_id'] ) ? absint( $_GET['form_id'] ) : 0;
		$entry_id = isset( $_GET['entry_id'] ) ? absint( $_GET['entry_id'] ) : 0;
		// phpcs:enable

		if ( ! $form_id && $forms ) {
			$form_id = (int) $forms[0]->ID;
		}
		$form = $form_id ? $this->plugin->forms->get( $form_id ) : null;

		echo '<div class="wrap glixform-wrap">';

		if ( ! $form ) {
			printf( '<h1>%s</h1>', esc_html__( 'Entries', 'glixform' ) );
			printf(
				'<p>%s <a href="%s">%s</a></p>',
				esc_html__( 'You have not created any forms yet.', 'glixform' ),
				esc_url( admin_url( 'admin.php?page=glixform-builder' ) ),
				esc_html__( 'Create your first form', 'glixform' )
			);
			echo '</div>';
			return;
		}

		if ( $entry_id ) {
			$this->render_entry( $form, $entry_id );
		} else {
			$this->render_list( $form, $forms );
		}

		echo '</div>';
		?>
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

	/**
	 * Entries list.
	 *
	 * @param array      $form  Form.
	 * @param \WP_Post[] $forms All forms.
	 */
	private function render_list( array $form, array $forms ) {
		$table = new EntriesListTable( $this->plugin->entries, $form );
		$table->prepare_items();

		$export = wp_nonce_url( admin_url( 'admin-post.php?action=glixform_export_entries&form_id=' . $form['id'] ), 'glixform_export_entries' );
		?>
		<h1 class="wp-heading-inline">
			<?php
			/* translators: %s: form name. */
			printf( esc_html__( 'Entries: %s', 'glixform' ), esc_html( $form['title'] ) );
			?>
		</h1>
		<a href="<?php echo esc_url( $export ); ?>" class="page-title-action"><?php esc_html_e( 'Export CSV', 'glixform' ); ?></a>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=glixform-builder&form_id=' . $form['id'] ) ); ?>" class="page-title-action"><?php esc_html_e( 'Edit Form', 'glixform' ); ?></a>
		<hr class="wp-header-end">

		<?php
		Admin::notice(
			array(
				'deleted' => __( 'Entries deleted.', 'glixform' ),
				'updated' => __( 'Entries updated.', 'glixform' ),
			)
		);
		?>

		<form method="get" class="glixform-form-switcher">
			<input type="hidden" name="page" value="glixform-entries">
			<label for="glixform-form-switch"><?php esc_html_e( 'Form:', 'glixform' ); ?></label>
			<select id="glixform-form-switch" name="form_id" onchange="this.form.submit()">
				<?php foreach ( $forms as $post ) : ?>
					<option value="<?php echo (int) $post->ID; ?>" <?php selected( $post->ID, $form['id'] ); ?>><?php echo esc_html( $post->post_title ); ?></option>
				<?php endforeach; ?>
			</select>
			<noscript><button type="submit" class="button"><?php esc_html_e( 'Switch', 'glixform' ); ?></button></noscript>
		</form>

		<?php $table->views(); ?>

		<form method="get">
			<input type="hidden" name="page" value="glixform-entries">
			<input type="hidden" name="form_id" value="<?php echo (int) $form['id']; ?>">
			<?php
			$table->search_box( __( 'Search Entries', 'glixform' ), 'glixform-entries' );
			$table->display();
			?>
		</form>
		<?php
	}

	/**
	 * Single entry view. Opening it marks the entry as read.
	 *
	 * @param array $form     Form.
	 * @param int   $entry_id Entry ID.
	 */
	private function render_entry( array $form, $entry_id ) {
		$entry = $this->plugin->entries->get( $entry_id );
		if ( ! $entry || $entry['form_id'] !== $form['id'] ) {
			printf( '<h1>%s</h1><p>%s</p>', esc_html__( 'Entry', 'glixform' ), esc_html__( 'Entry not found.', 'glixform' ) );
			return;
		}

		if ( 'unread' === $entry['status'] ) {
			$this->plugin->entries->set_status( array( $entry_id ), 'read' );
		}

		$back = admin_url( 'admin.php?page=glixform-entries&form_id=' . $form['id'] );
		$user = $entry['user_id'] ? get_userdata( $entry['user_id'] ) : null;
		?>
		<h1 class="wp-heading-inline">
			<?php
			/* translators: %d: entry ID. */
			printf( esc_html__( 'Entry #%d', 'glixform' ), (int) $entry['id'] );
			?>
		</h1>
		<a href="<?php echo esc_url( $back ); ?>" class="page-title-action"><?php esc_html_e( 'Back to entries', 'glixform' ); ?></a>
		<hr class="wp-header-end">

		<div class="glixform-entry">
			<div class="glixform-card glixform-entry-fields">
				<h2><?php echo esc_html( $form['title'] ); ?></h2>
				<table class="widefat striped">
					<tbody>
					<?php foreach ( $entry['fields'] as $field ) : ?>
						<tr>
							<th scope="row"><?php echo esc_html( $field['label'] ); ?></th>
							<td>
								<?php
								$value = SmartTags::value_to_string( $field['value'] );
								echo '' === $value ? '<em>' . esc_html__( 'Empty', 'glixform' ) . '</em>' : nl2br( esc_html( $value ) );
								?>
							</td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			</div>

			<div class="glixform-card glixform-entry-meta">
				<h2><?php esc_html_e( 'Details', 'glixform' ); ?></h2>
				<dl>
					<dt><?php esc_html_e( 'Submitted', 'glixform' ); ?></dt>
					<dd><?php echo esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $entry['created_at'] . ' UTC' ) ) ); ?></dd>
					<dt><?php esc_html_e( 'User', 'glixform' ); ?></dt>
					<dd><?php echo $user ? esc_html( $user->display_name ) : esc_html__( 'Guest', 'glixform' ); ?></dd>
					<?php if ( $entry['ip_address'] ) : ?>
						<dt><?php esc_html_e( 'IP address', 'glixform' ); ?></dt>
						<dd><?php echo esc_html( $entry['ip_address'] ); ?></dd>
					<?php endif; ?>
					<?php if ( $entry['page_url'] ) : ?>
						<dt><?php esc_html_e( 'Page', 'glixform' ); ?></dt>
						<dd><a href="<?php echo esc_url( $entry['page_url'] ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $entry['page_url'] ); ?></a></dd>
					<?php endif; ?>
					<?php if ( $entry['user_agent'] ) : ?>
						<dt><?php esc_html_e( 'Browser', 'glixform' ); ?></dt>
						<dd class="glixform-user-agent"><?php echo esc_html( $entry['user_agent'] ); ?></dd>
					<?php endif; ?>
				</dl>
				<p>
					<a href="<?php echo esc_url( self::delete_url( $form['id'], $entry['id'] ) ); ?>" class="button button-link-delete glixform-confirm" data-confirm="<?php esc_attr_e( 'Delete this entry?', 'glixform' ); ?>"><?php esc_html_e( 'Delete entry', 'glixform' ); ?></a>
				</p>
			</div>
		</div>
		<?php
	}
}

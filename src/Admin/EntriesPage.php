<?php
/**
 * Entries screens: list, single entry, bulk actions, export and file downloads.
 *
 * @package Glixform
 */

namespace Glixform\Admin;

use Glixform\Notifications\SmartTags;
use Glixform\Plugin;
use Glixform\Support\Uploads;

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
		add_action( 'admin_post_glixform_download', array( $this, 'download' ) );
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
	 * Signed URL for a single-entry action.
	 *
	 * @param string $action   delete_entry | spam_entry | unspam_entry.
	 * @param int    $form_id  Form ID.
	 * @param int    $entry_id Entry ID.
	 * @return string
	 */
	public static function action_url( $action, $form_id, $entry_id ) {
		return wp_nonce_url(
			admin_url( sprintf( 'admin.php?page=glixform-entries&form_id=%d&glixform_action=%s&entry_id=%d', $form_id, $action, $entry_id ) ),
			'glixform_' . $action . '_' . $entry_id
		);
	}

	/**
	 * Signed URL to delete one entry.
	 *
	 * @param int $form_id  Form ID.
	 * @param int $entry_id Entry ID.
	 * @return string
	 */
	public static function delete_url( $form_id, $entry_id ) {
		return self::action_url( 'delete_entry', $form_id, $entry_id );
	}

	/**
	 * Handle single-entry and bulk actions before any output.
	 */
	public function handle_actions() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Nonces are checked below per action.
		if ( ! isset( $_GET['page'] ) || 'glixform-entries' !== $_GET['page'] ) {
			return;
		}

		$form_id = isset( $_GET['form_id'] ) ? absint( $_GET['form_id'] ) : 0;
		$back    = admin_url( 'admin.php?page=glixform-entries&form_id=' . $form_id );

		$single = isset( $_GET['glixform_action'] ) ? sanitize_key( wp_unslash( $_GET['glixform_action'] ) ) : '';
		if ( in_array( $single, array( 'delete_entry', 'spam_entry', 'unspam_entry' ), true ) ) {
			Admin::check_permission();
			$entry_id = isset( $_GET['entry_id'] ) ? absint( $_GET['entry_id'] ) : 0;
			check_admin_referer( 'glixform_' . $single . '_' . $entry_id );
			$entry = $this->plugin->entries->get( $entry_id );
			if ( $entry && $entry['form_id'] === $form_id ) {
				if ( 'delete_entry' === $single ) {
					$this->plugin->entries->delete( array( $entry_id ) );
				} else {
					$this->plugin->entries->set_status( array( $entry_id ), 'spam_entry' === $single ? 'spam' : 'read' );
				}
			}
			$notice = array(
				'delete_entry' => 'deleted',
				'spam_entry'   => 'spammed',
				'unspam_entry' => 'unspammed',
			)[ $single ];
			wp_safe_redirect( add_query_arg( 'glixform_notice', $notice, $back ) );
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

		$statuses = array(
			'mark_read'   => 'read',
			'mark_unread' => 'unread',
			'mark_spam'   => 'spam',
			'not_spam'    => 'unread',
		);

		$notice = '';
		if ( 'delete' === $action ) {
			$this->plugin->entries->delete( $ids );
			$notice = 'deleted';
		} elseif ( isset( $statuses[ $action ] ) ) {
			$this->plugin->entries->set_status( $ids, $statuses[ $action ] );
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
	 * Stream an uploaded file to an administrator.
	 */
	public function download() {
		Admin::check_permission();
		$entry_id = isset( $_GET['entry_id'] ) ? absint( $_GET['entry_id'] ) : 0;
		check_admin_referer( 'glixform_download_' . $entry_id );

		$field_id = isset( $_GET['field_id'] ) ? absint( $_GET['field_id'] ) : 0;
		$index    = isset( $_GET['index'] ) ? absint( $_GET['index'] ) : 0;
		$entry    = $this->plugin->entries->get( $entry_id );
		$path     = false;
		$name     = '';

		foreach ( $entry ? $entry['fields'] : array() as $item ) {
			if ( (int) $item['id'] === $field_id && 'file' === $item['type'] && isset( $item['value'][ $index ]['file'] ) ) {
				$path = Uploads::path( $item['value'][ $index ]['file'] );
				$name = (string) $item['value'][ $index ]['name'];
			}
		}

		if ( ! $path ) {
			wp_die( esc_html__( 'File not found.', 'glixform' ), 404 );
		}

		$type = wp_check_filetype( $path );
		nocache_headers();
		header( 'Content-Type: ' . ( $type['type'] ? $type['type'] : 'application/octet-stream' ) );
		header( 'Content-Disposition: attachment; filename="' . str_replace( array( '"', "\r", "\n" ), '', sanitize_file_name( $name ) ) . '"' );
		header( 'Content-Length: ' . filesize( $path ) );
		header( 'X-Content-Type-Options: nosniff' );
		readfile( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile
		exit;
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

		echo '<div class="wrap glixform-admin">';

		if ( ! $form ) {
			Admin::header( __( 'Entries', 'glixform' ) );
			Admin::empty_state(
				'dashicons-list-view',
				__( 'No entries yet', 'glixform' ),
				__( 'Create a form and add it to a page. Every submission will show up here.', 'glixform' ),
				admin_url( 'admin.php?page=glixform-builder' ),
				__( 'Create your first form', 'glixform' )
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
		Admin::confirm_script();
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

		ob_start();
		?>
		<form method="get" class="glixform-form-switcher">
			<input type="hidden" name="page" value="glixform-entries">
			<label class="screen-reader-text" for="glixform-form-switch"><?php esc_html_e( 'Form', 'glixform' ); ?></label>
			<select id="glixform-form-switch" name="form_id" onchange="this.form.submit()">
				<?php foreach ( $forms as $post ) : ?>
					<option value="<?php echo (int) $post->ID; ?>" <?php selected( $post->ID, $form['id'] ); ?>><?php echo esc_html( $post->post_title ); ?></option>
				<?php endforeach; ?>
			</select>
			<noscript><button type="submit" class="button"><?php esc_html_e( 'Switch', 'glixform' ); ?></button></noscript>
		</form>
		<?php
		$switcher = ob_get_clean();

		Admin::header(
			__( 'Entries', 'glixform' ),
			array(
				array(
					'url'   => $export,
					'label' => __( 'Export CSV', 'glixform' ),
					'icon'  => 'dashicons-download',
				),
				array(
					'url'   => admin_url( 'admin.php?page=glixform-builder&form_id=' . $form['id'] ),
					'label' => __( 'Edit form', 'glixform' ),
					'icon'  => 'dashicons-edit',
				),
			),
			$switcher
		);

		Admin::notice(
			array(
				'deleted'   => __( 'Entries deleted.', 'glixform' ),
				'updated'   => __( 'Entries updated.', 'glixform' ),
				'spammed'   => __( 'Entry moved to spam.', 'glixform' ),
				'unspammed' => __( 'Entry restored from spam.', 'glixform' ),
			)
		);
		?>
		<div class="glixform-card glixform-table-card">
			<?php $table->views(); ?>
			<form method="get">
				<input type="hidden" name="page" value="glixform-entries">
				<input type="hidden" name="form_id" value="<?php echo (int) $form['id']; ?>">
				<?php
				// phpcs:ignore WordPress.Security.NonceVerification.Recommended
				if ( ! empty( $_GET['status'] ) ) {
					// phpcs:ignore WordPress.Security.NonceVerification.Recommended
					printf( '<input type="hidden" name="status" value="%s">', esc_attr( sanitize_key( wp_unslash( $_GET['status'] ) ) ) );
				}
				$table->search_box( __( 'Search entries', 'glixform' ), 'glixform-entries' );
				$table->display();
				?>
			</form>
		</div>
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
			Admin::header( __( 'Entry', 'glixform' ) );
			echo '<p>' . esc_html__( 'Entry not found.', 'glixform' ) . '</p>';
			return;
		}

		if ( 'unread' === $entry['status'] ) {
			$this->plugin->entries->set_status( array( $entry_id ), 'read' );
		}

		$back = admin_url( 'admin.php?page=glixform-entries&form_id=' . $form['id'] );
		$user = $entry['user_id'] ? get_userdata( $entry['user_id'] ) : null;

		Admin::header(
			/* translators: %d: entry ID. */
			sprintf( __( 'Entry #%d', 'glixform' ), (int) $entry['id'] ),
			array(
				array(
					'url'   => $back,
					'label' => __( 'Back to entries', 'glixform' ),
					'icon'  => 'dashicons-arrow-left-alt',
				),
			),
			'<span class="glixform-header-sub">' . esc_html( $form['title'] ) . '</span>'
		);
		?>
		<div class="glixform-entry">
			<div class="glixform-card glixform-entry-fields">
				<?php if ( 'spam' === $entry['status'] ) : ?>
					<div class="glixform-inline-notice"><?php esc_html_e( 'This entry was marked as spam. No notifications were sent for it.', 'glixform' ); ?></div>
				<?php endif; ?>
				<dl>
					<?php foreach ( $entry['fields'] as $item ) : ?>
						<?php
						$type = $this->plugin->fields->get( (string) $item['type'] );
						$html = $type ? $type->entry_html(
							$item,
							array(
								'entry_id' => $entry['id'],
								'form_id'  => $form['id'],
							)
						) : nl2br( esc_html( SmartTags::item_text( $item ) ) );
						?>
						<div class="glixform-entry-row">
							<dt><?php echo esc_html( $item['label'] ); ?></dt>
							<dd><?php echo '' === $html ? '<span class="glixform-empty-value">' . esc_html__( 'Empty', 'glixform' ) . '</span>' : $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Field types escape their own output. ?></dd>
						</div>
					<?php endforeach; ?>
				</dl>
			</div>

			<aside class="glixform-card glixform-entry-meta">
				<h2><?php esc_html_e( 'Details', 'glixform' ); ?></h2>
				<dl>
					<dt><?php esc_html_e( 'Submitted', 'glixform' ); ?></dt>
					<dd><?php echo esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $entry['created_at'] . ' UTC' ) ) ); ?></dd>
					<dt><?php esc_html_e( 'Status', 'glixform' ); ?></dt>
					<dd><span class="glixform-pill glixform-pill-<?php echo esc_attr( $entry['status'] ); ?>"><?php echo esc_html( 'spam' === $entry['status'] ? __( 'Spam', 'glixform' ) : __( 'Read', 'glixform' ) ); ?></span></dd>
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
				<div class="glixform-entry-actions">
					<?php if ( 'spam' === $entry['status'] ) : ?>
						<a class="button" href="<?php echo esc_url( self::action_url( 'unspam_entry', $form['id'], $entry['id'] ) ); ?>"><?php esc_html_e( 'Not spam', 'glixform' ); ?></a>
					<?php else : ?>
						<a class="button" href="<?php echo esc_url( self::action_url( 'spam_entry', $form['id'], $entry['id'] ) ); ?>"><?php esc_html_e( 'Mark as spam', 'glixform' ); ?></a>
					<?php endif; ?>
					<a href="<?php echo esc_url( self::delete_url( $form['id'], $entry['id'] ) ); ?>" class="button glixform-button-danger glixform-confirm" data-confirm="<?php esc_attr_e( 'Delete this entry and its files?', 'glixform' ); ?>"><?php esc_html_e( 'Delete', 'glixform' ); ?></a>
				</div>
			</aside>
		</div>
		<?php
	}
}

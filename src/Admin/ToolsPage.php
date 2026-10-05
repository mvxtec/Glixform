<?php
/**
 * Tools: import and export forms as JSON.
 *
 * @package Glixform
 */

namespace Glixform\Admin;

use Glixform\Plugin;

defined( 'ABSPATH' ) || exit;

/**
 * Export selected forms to a JSON file, or import forms from one. Imported
 * data goes through FormRepository::sanitize() like anything from the builder.
 */
class ToolsPage {

	const MAX_IMPORT_BYTES = 5242880;

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
		add_action( 'admin_post_glixform_export_forms', array( $this, 'export' ) );
		add_action( 'admin_post_glixform_import_forms', array( $this, 'import' ) );
	}

	/**
	 * Build the export document.
	 *
	 * @param int[] $ids Form IDs.
	 * @return array
	 */
	public function export_data( array $ids ) {
		$forms = array();
		foreach ( $ids as $id ) {
			$form = $this->plugin->forms->get( $id );
			if ( $form ) {
				$forms[] = array(
					'title' => $form['title'],
					'data'  => $form['data'],
				);
			}
		}
		return array(
			'glixform' => GLIXFORM_VERSION,
			'exported' => gmdate( 'c' ),
			'forms'    => $forms,
		);
	}

	/**
	 * Create forms from an export document.
	 *
	 * @param mixed $document Decoded JSON.
	 * @return int|\WP_Error Number of forms imported.
	 */
	public function import_data( $document ) {
		if ( ! is_array( $document ) || ! isset( $document['forms'] ) || ! is_array( $document['forms'] ) ) {
			return new \WP_Error( 'glixform_import_invalid', __( 'This is not a Glixform export file.', 'glixform' ) );
		}
		$count = 0;
		foreach ( array_slice( $document['forms'], 0, 100 ) as $form ) {
			if ( ! is_array( $form ) || ! isset( $form['data'] ) || ! is_array( $form['data'] ) ) {
				continue;
			}
			$result = $this->plugin->forms->save( 0, (string) ( $form['title'] ?? '' ), $form['data'] );
			if ( ! is_wp_error( $result ) ) {
				++$count;
			}
		}
		return $count;
	}

	/**
	 * Download handler.
	 */
	public function export() {
		Admin::check_permission();
		check_admin_referer( 'glixform_export_forms' );

		$ids = isset( $_POST['form_ids'] ) ? array_map( 'absint', (array) wp_unslash( $_POST['form_ids'] ) ) : array();
		if ( ! $ids ) {
			wp_safe_redirect( add_query_arg( 'glixform_notice', 'none_selected', admin_url( 'admin.php?page=glixform-tools' ) ) );
			exit;
		}

		nocache_headers();
		header( 'Content-Type: application/json; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="glixform-forms-' . gmdate( 'Y-m-d' ) . '.json"' );
		echo wp_json_encode( $this->export_data( $ids ), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		exit;
	}

	/**
	 * Upload handler.
	 */
	public function import() {
		Admin::check_permission();
		check_admin_referer( 'glixform_import_forms' );

		$back = admin_url( 'admin.php?page=glixform-tools' );
		$file = isset( $_FILES['import_file'] ) && is_array( $_FILES['import_file'] ) ? $_FILES['import_file'] : null; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Validated below.

		if ( ! $file || UPLOAD_ERR_OK !== (int) $file['error'] || ! is_uploaded_file( (string) $file['tmp_name'] ) || (int) $file['size'] > self::MAX_IMPORT_BYTES ) {
			wp_safe_redirect( add_query_arg( 'glixform_notice', 'import_failed', $back ) );
			exit;
		}

		$document = json_decode( (string) file_get_contents( $file['tmp_name'] ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$result   = $this->import_data( $document );

		if ( is_wp_error( $result ) || 0 === $result ) {
			wp_safe_redirect( add_query_arg( 'glixform_notice', 'import_failed', $back ) );
			exit;
		}

		wp_safe_redirect(
			add_query_arg(
				array(
					'glixform_notice' => 'imported',
					'count'           => $result,
				),
				admin_url( 'admin.php?page=glixform' )
			)
		);
		exit;
	}

	/**
	 * Render the screen.
	 */
	public function render() {
		Admin::check_permission();
		$forms = $this->plugin->forms->all();
		?>
		<div class="wrap glixform-admin">
			<?php
			Admin::header( __( 'Tools', 'glixform' ) );
			Admin::notice(
				array(
					'none_selected' => __( 'Select at least one form to export.', 'glixform' ),
					'import_failed' => __( 'The file could not be imported. Choose a .json file exported from Glixform (5 MB max).', 'glixform' ),
				)
			);
			?>
			<div class="glixform-tools-grid">
				<section class="glixform-card glixform-settings-card">
					<header>
						<span class="dashicons dashicons-download" aria-hidden="true"></span>
						<div>
							<h2><?php esc_html_e( 'Export forms', 'glixform' ); ?></h2>
							<p><?php esc_html_e( 'Download forms as a JSON file to back them up or move them to another site. Entries are not included.', 'glixform' ); ?></p>
						</div>
					</header>
					<?php if ( $forms ) : ?>
						<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
							<input type="hidden" name="action" value="glixform_export_forms">
							<?php wp_nonce_field( 'glixform_export_forms' ); ?>
							<fieldset class="glixform-checklist">
								<legend class="screen-reader-text"><?php esc_html_e( 'Forms to export', 'glixform' ); ?></legend>
								<label class="glixform-check-all"><input type="checkbox" onclick="this.closest('form').querySelectorAll('input[name=&quot;form_ids[]&quot;]').forEach(function(c){c.checked=this.checked;}.bind(this))"> <strong><?php esc_html_e( 'Select all', 'glixform' ); ?></strong></label>
								<?php foreach ( $forms as $post ) : ?>
									<label><input type="checkbox" name="form_ids[]" value="<?php echo (int) $post->ID; ?>"> <?php echo esc_html( $post->post_title ); ?></label>
								<?php endforeach; ?>
							</fieldset>
							<?php submit_button( __( 'Download export file', 'glixform' ), 'primary', 'submit', false ); ?>
						</form>
					<?php else : ?>
						<p><?php esc_html_e( 'There are no forms to export yet.', 'glixform' ); ?></p>
					<?php endif; ?>
				</section>

				<section class="glixform-card glixform-settings-card">
					<header>
						<span class="dashicons dashicons-upload" aria-hidden="true"></span>
						<div>
							<h2><?php esc_html_e( 'Import forms', 'glixform' ); ?></h2>
							<p><?php esc_html_e( 'Upload a Glixform export file. Each form in it is added as a new form; existing forms are not changed.', 'glixform' ); ?></p>
						</div>
					</header>
					<form method="post" enctype="multipart/form-data" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
						<input type="hidden" name="action" value="glixform_import_forms">
						<?php wp_nonce_field( 'glixform_import_forms' ); ?>
						<p><input type="file" name="import_file" accept=".json,application/json" required></p>
						<?php submit_button( __( 'Import', 'glixform' ), 'secondary', 'submit', false ); ?>
					</form>
				</section>
			</div>
		</div>
		<?php
	}
}

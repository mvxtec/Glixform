<?php
/**
 * Form builder screen.
 *
 * @package Glixform
 */

namespace Glixform\Admin;

use Glixform\Frontend\Preview;
use Glixform\Plugin;

defined( 'ABSPATH' ) || exit;

/**
 * Field list is edited in JavaScript and posted as JSON; settings are plain form inputs.
 */
class BuilderPage {

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
		add_action( 'admin_post_glixform_save_form', array( $this, 'save' ) );
	}

	/**
	 * Form being edited, or a new contact-form template.
	 *
	 * @return array
	 */
	private function current_form() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only.
		$form_id = isset( $_GET['form_id'] ) ? absint( $_GET['form_id'] ) : 0;
		$form    = $form_id ? $this->plugin->forms->get( $form_id ) : null;

		if ( $form ) {
			return $form;
		}

		return array(
			'id'    => 0,
			'title' => '',
			'data'  => $this->plugin->forms->contact_template(),
		);
	}

	/**
	 * Enqueue builder assets.
	 */
	public function enqueue() {
		$form  = $this->current_form();
		$types = array();
		foreach ( $this->plugin->fields->all() as $type ) {
			$types[] = $type->to_builder_array();
		}

		wp_enqueue_script( 'glixform-builder', GLIXFORM_URL . 'assets/js/builder.js', array(), GLIXFORM_VERSION, true );
		wp_localize_script(
			'glixform-builder',
			'glixformBuilder',
			array(
				'types'  => $types,
				'fields' => $form['data']['fields'],
				'nextId' => $form['data']['next_field_id'],
				'i18n'   => array(
					'label'         => __( 'Label', 'glixform' ),
					'description'   => __( 'Description', 'glixform' ),
					'required'      => __( 'Required', 'glixform' ),
					'placeholder'   => __( 'Placeholder', 'glixform' ),
					'default_value' => __( 'Default value', 'glixform' ),
					'max_length'    => __( 'Maximum characters (0 = no limit)', 'glixform' ),
					'min'           => __( 'Minimum', 'glixform' ),
					'max'           => __( 'Maximum', 'glixform' ),
					'step'          => __( 'Step', 'glixform' ),
					'choices'       => __( 'Choices', 'glixform' ),
					'addChoice'     => __( 'Add choice', 'glixform' ),
					'removeChoice'  => __( 'Remove choice', 'glixform' ),
					'defaultChoice' => __( 'Selected by default', 'glixform' ),
					'moveUp'        => __( 'Move up', 'glixform' ),
					'moveDown'      => __( 'Move down', 'glixform' ),
					'duplicate'     => __( 'Duplicate', 'glixform' ),
					'delete'        => __( 'Delete', 'glixform' ),
					'edit'          => __( 'Edit field', 'glixform' ),
					'confirmDelete' => __( 'Delete this field?', 'glixform' ),
					'noFields'      => __( 'Your form has no fields yet. Add one from the panel on the left.', 'glixform' ),
					'fieldId'       => __( 'Field ID', 'glixform' ),
					'newChoice'     => __( 'New choice', 'glixform' ),
					'unsaved'       => __( 'You have unsaved changes.', 'glixform' ),
					'needChoice'    => __( 'Add at least one choice.', 'glixform' ),
					'dragHandle'    => __( 'Drag to reorder', 'glixform' ),
				),
			)
		);
	}

	/**
	 * Save handler.
	 */
	public function save() {
		Admin::check_permission();
		check_admin_referer( 'glixform_save_form' );

		$form_id = isset( $_POST['form_id'] ) ? absint( $_POST['form_id'] ) : 0;
		$title   = isset( $_POST['form_title'] ) ? sanitize_text_field( wp_unslash( $_POST['form_title'] ) ) : '';

		// Fields arrive as JSON from the builder script; FormRepository::sanitize() cleans every value.
		$fields = isset( $_POST['form_fields'] ) ? json_decode( wp_unslash( $_POST['form_fields'] ), true ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$raw    = isset( $_POST['settings'] ) && is_array( $_POST['settings'] ) ? wp_unslash( $_POST['settings'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

		// Unchecked checkboxes are absent from the request.
		$raw['store_entries']           = ! empty( $raw['store_entries'] );
		$raw['notification']            = isset( $raw['notification'] ) && is_array( $raw['notification'] ) ? $raw['notification'] : array();
		$raw['notification']['enabled'] = ! empty( $raw['notification']['enabled'] );

		$data = array(
			'fields'        => is_array( $fields ) ? $fields : array(),
			'next_field_id' => isset( $_POST['next_field_id'] ) ? absint( $_POST['next_field_id'] ) : 1,
			'settings'      => $raw,
		);

		if ( $form_id && ! $this->plugin->forms->get( $form_id ) ) {
			wp_die( esc_html__( 'Form not found.', 'glixform' ), 404 );
		}

		$result = $this->plugin->forms->save( $form_id, $title, $data );
		if ( is_wp_error( $result ) ) {
			wp_die( esc_html( $result->get_error_message() ) );
		}

		$tab = isset( $_POST['active_tab'] ) && 'settings' === $_POST['active_tab'] ? 'settings' : 'fields';

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'            => 'glixform-builder',
					'form_id'         => $result,
					'tab'             => $tab,
					'glixform_notice' => 'saved',
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Render the builder.
	 */
	public function render() {
		Admin::check_permission();

		$form     = $this->current_form();
		$settings = $form['data']['settings'];
		$conf     = $settings['confirmation'];
		$noti     = $settings['notification'];
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Display only.
		$tab = isset( $_GET['tab'] ) && 'settings' === $_GET['tab'] ? 'settings' : 'fields';
		?>
		<div class="wrap glixform-wrap glixform-builder-wrap">
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" id="glixform-builder-form">
				<input type="hidden" name="action" value="glixform_save_form">
				<input type="hidden" name="form_id" value="<?php echo (int) $form['id']; ?>">
				<input type="hidden" name="form_fields" id="glixform-form-fields" value="">
				<input type="hidden" name="next_field_id" id="glixform-next-field-id" value="<?php echo (int) $form['data']['next_field_id']; ?>">
				<input type="hidden" name="active_tab" id="glixform-active-tab" value="<?php echo esc_attr( $tab ); ?>">
				<?php wp_nonce_field( 'glixform_save_form' ); ?>

				<div class="glixform-topbar">
					<label class="screen-reader-text" for="glixform-title"><?php esc_html_e( 'Form name', 'glixform' ); ?></label>
					<input type="text" id="glixform-title" name="form_title" class="glixform-title-input" value="<?php echo esc_attr( $form['title'] ); ?>" placeholder="<?php esc_attr_e( 'Form name', 'glixform' ); ?>" required>
					<div class="glixform-topbar-actions">
						<?php if ( $form['id'] ) : ?>
							<code class="glixform-shortcode" title="<?php esc_attr_e( 'Paste this shortcode into any page or post.', 'glixform' ); ?>">[glixform id="<?php echo (int) $form['id']; ?>"]</code>
							<a class="button" href="<?php echo esc_url( Preview::url( $form['id'] ) ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Preview', 'glixform' ); ?></a>
							<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=glixform-entries&form_id=' . $form['id'] ) ); ?>"><?php esc_html_e( 'Entries', 'glixform' ); ?></a>
						<?php endif; ?>
						<button type="submit" class="button button-primary"><?php esc_html_e( 'Save Form', 'glixform' ); ?></button>
					</div>
				</div>

				<?php Admin::notice( array( 'saved' => __( 'Form saved.', 'glixform' ) ) ); ?>

				<nav class="nav-tab-wrapper glixform-tabs" role="tablist">
					<button type="button" role="tab" class="nav-tab<?php echo 'fields' === $tab ? ' nav-tab-active' : ''; ?>" data-tab="fields" aria-selected="<?php echo 'fields' === $tab ? 'true' : 'false'; ?>" aria-controls="glixform-tab-fields"><?php esc_html_e( 'Fields', 'glixform' ); ?></button>
					<button type="button" role="tab" class="nav-tab<?php echo 'settings' === $tab ? ' nav-tab-active' : ''; ?>" data-tab="settings" aria-selected="<?php echo 'settings' === $tab ? 'true' : 'false'; ?>" aria-controls="glixform-tab-settings"><?php esc_html_e( 'Settings', 'glixform' ); ?></button>
				</nav>

				<div id="glixform-tab-fields" class="glixform-tab-panel" role="tabpanel"<?php echo 'fields' === $tab ? '' : ' hidden'; ?>>
					<div class="glixform-builder">
						<aside class="glixform-palette">
							<h2><?php esc_html_e( 'Add Fields', 'glixform' ); ?></h2>
							<div class="glixform-palette-buttons" id="glixform-palette"></div>
						</aside>
						<div class="glixform-canvas">
							<ol class="glixform-field-list" id="glixform-field-list"></ol>
							<p class="glixform-empty" id="glixform-empty" hidden></p>
						</div>
					</div>
				</div>

				<div id="glixform-tab-settings" class="glixform-tab-panel" role="tabpanel"<?php echo 'settings' === $tab ? '' : ' hidden'; ?>>
					<div class="glixform-settings-grid">
						<section class="glixform-card">
							<h2><?php esc_html_e( 'General', 'glixform' ); ?></h2>
							<p>
								<label for="glixform-submit-text"><?php esc_html_e( 'Submit button text', 'glixform' ); ?></label>
								<input type="text" class="regular-text" id="glixform-submit-text" name="settings[submit_text]" value="<?php echo esc_attr( $settings['submit_text'] ); ?>">
							</p>
							<p>
								<label><input type="checkbox" name="settings[store_entries]" value="1" <?php checked( $settings['store_entries'] ); ?>> <?php esc_html_e( 'Store entries in the database', 'glixform' ); ?></label>
							</p>
						</section>

						<section class="glixform-card">
							<h2><?php esc_html_e( 'Confirmation', 'glixform' ); ?></h2>
							<p class="description"><?php esc_html_e( 'What visitors see after submitting the form.', 'glixform' ); ?></p>
							<fieldset class="glixform-confirmation-type">
								<legend class="screen-reader-text"><?php esc_html_e( 'Confirmation type', 'glixform' ); ?></legend>
								<label><input type="radio" name="settings[confirmation][type]" value="message" <?php checked( $conf['type'], 'message' ); ?>> <?php esc_html_e( 'Show a message', 'glixform' ); ?></label>
								<label><input type="radio" name="settings[confirmation][type]" value="page" <?php checked( $conf['type'], 'page' ); ?>> <?php esc_html_e( 'Go to a page', 'glixform' ); ?></label>
								<label><input type="radio" name="settings[confirmation][type]" value="redirect" <?php checked( $conf['type'], 'redirect' ); ?>> <?php esc_html_e( 'Go to a URL', 'glixform' ); ?></label>
							</fieldset>
							<p data-confirmation="message">
								<label for="glixform-confirmation-message"><?php esc_html_e( 'Message', 'glixform' ); ?></label>
								<textarea id="glixform-confirmation-message" class="large-text" rows="4" name="settings[confirmation][message]"><?php echo esc_textarea( $conf['message'] ); ?></textarea>
							</p>
							<p data-confirmation="page">
								<label for="glixform-confirmation-page"><?php esc_html_e( 'Page', 'glixform' ); ?></label>
								<?php
								wp_dropdown_pages(
									array(
										'name'             => 'settings[confirmation][page_id]',
										'id'               => 'glixform-confirmation-page',
										'selected'         => (int) $conf['page_id'],
										'show_option_none' => esc_html__( '— Select a page —', 'glixform' ),
										'option_none_value' => '0',
									)
								);
								?>
							</p>
							<p data-confirmation="redirect">
								<label for="glixform-confirmation-url"><?php esc_html_e( 'URL', 'glixform' ); ?></label>
								<input type="url" class="large-text" id="glixform-confirmation-url" name="settings[confirmation][url]" value="<?php echo esc_attr( $conf['url'] ); ?>" placeholder="https://">
							</p>
						</section>

						<section class="glixform-card">
							<h2><?php esc_html_e( 'Email Notification', 'glixform' ); ?></h2>
							<p>
								<label><input type="checkbox" name="settings[notification][enabled]" value="1" <?php checked( $noti['enabled'] ); ?>> <?php esc_html_e( 'Send an email for each new entry', 'glixform' ); ?></label>
							</p>
							<p>
								<label for="glixform-noti-to"><?php esc_html_e( 'Send to (comma-separated)', 'glixform' ); ?></label>
								<input type="text" class="large-text" id="glixform-noti-to" name="settings[notification][to]" value="<?php echo esc_attr( $noti['to'] ); ?>">
							</p>
							<p>
								<label for="glixform-noti-subject"><?php esc_html_e( 'Subject', 'glixform' ); ?></label>
								<input type="text" class="large-text" id="glixform-noti-subject" name="settings[notification][subject]" value="<?php echo esc_attr( $noti['subject'] ); ?>">
							</p>
							<p>
								<label for="glixform-noti-from"><?php esc_html_e( 'From name', 'glixform' ); ?></label>
								<input type="text" class="large-text" id="glixform-noti-from" name="settings[notification][from_name]" value="<?php echo esc_attr( $noti['from_name'] ); ?>">
							</p>
							<p>
								<label for="glixform-noti-reply"><?php esc_html_e( 'Reply-to', 'glixform' ); ?></label>
								<input type="text" class="large-text" id="glixform-noti-reply" name="settings[notification][reply_to]" value="<?php echo esc_attr( $noti['reply_to'] ); ?>" placeholder="{field_id=&quot;2&quot;}">
							</p>
							<p>
								<label for="glixform-noti-message"><?php esc_html_e( 'Message', 'glixform' ); ?></label>
								<textarea id="glixform-noti-message" class="large-text" rows="5" name="settings[notification][message]"><?php echo esc_textarea( $noti['message'] ); ?></textarea>
							</p>
						</section>

						<section class="glixform-card glixform-smart-tags-help">
							<h2><?php esc_html_e( 'Smart Tags', 'glixform' ); ?></h2>
							<p class="description"><?php esc_html_e( 'Use these in the notification and confirmation. Field IDs are shown on each field in the Fields tab.', 'glixform' ); ?></p>
							<ul>
								<li><code>{all_fields}</code> <?php esc_html_e( 'all submitted values', 'glixform' ); ?></li>
								<li><code>{field_id="N"}</code> <?php esc_html_e( 'value of field N', 'glixform' ); ?></li>
								<li><code>{form_name}</code>, <code>{entry_id}</code>, <code>{date}</code>, <code>{page_url}</code></li>
								<li><code>{site_name}</code>, <code>{site_url}</code>, <code>{admin_email}</code></li>
							</ul>
						</section>
					</div>
				</div>
			</form>
		</div>
		<?php
	}
}

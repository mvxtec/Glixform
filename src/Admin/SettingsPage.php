<?php
/**
 * Global settings screen.
 *
 * @package Glixform
 */

namespace Glixform\Admin;

use Glixform\Plugin;

defined( 'ABSPATH' ) || exit;

/**
 * Settings stored in the "glixform_settings" option.
 */
class SettingsPage {

	/**
	 * Hook into WordPress.
	 */
	public function register_hooks() {
		add_action( 'admin_init', array( $this, 'register' ) );
	}

	/**
	 * Register the option.
	 */
	public function register() {
		register_setting(
			'glixform_settings',
			'glixform_settings',
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize' ),
				'default'           => Plugin::default_settings(),
			)
		);
	}

	/**
	 * Sanitize submitted settings.
	 *
	 * @param mixed $input Raw input.
	 * @return array
	 */
	public function sanitize( $input ) {
		$input = is_array( $input ) ? $input : array();
		return array(
			'store_ip'            => ! empty( $input['store_ip'] ),
			'min_submit_seconds'  => min( 60, absint( $input['min_submit_seconds'] ?? 2 ) ),
			'delete_on_uninstall' => ! empty( $input['delete_on_uninstall'] ),
		);
	}

	/**
	 * Render the screen.
	 */
	public function render() {
		Admin::check_permission();
		$settings = wp_parse_args( (array) get_option( 'glixform_settings', array() ), Plugin::default_settings() );
		?>
		<div class="wrap glixform-wrap">
			<h1><?php esc_html_e( 'Glixform Settings', 'glixform' ); ?></h1>
			<?php settings_errors(); ?>
			<form method="post" action="options.php">
				<?php settings_fields( 'glixform_settings' ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'Privacy', 'glixform' ); ?></th>
						<td>
							<label><input type="checkbox" name="glixform_settings[store_ip]" value="1" <?php checked( $settings['store_ip'] ); ?>> <?php esc_html_e( 'Store the IP address and browser of each visitor with their entry', 'glixform' ); ?></label>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="glixform-min-seconds"><?php esc_html_e( 'Spam protection', 'glixform' ); ?></label></th>
						<td>
							<input type="number" min="0" max="60" class="small-text" id="glixform-min-seconds" name="glixform_settings[min_submit_seconds]" value="<?php echo (int) $settings['min_submit_seconds']; ?>">
							<?php esc_html_e( 'seconds', 'glixform' ); ?>
							<p class="description"><?php esc_html_e( 'Reject submissions sent faster than this after the form loads. Bots are usually instant. Every form also has a hidden honeypot field.', 'glixform' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Uninstall', 'glixform' ); ?></th>
						<td>
							<label><input type="checkbox" name="glixform_settings[delete_on_uninstall]" value="1" <?php checked( $settings['delete_on_uninstall'] ); ?>> <?php esc_html_e( 'Delete all forms, entries and settings when the plugin is deleted', 'glixform' ); ?></label>
						</td>
					</tr>
				</table>
				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}
}

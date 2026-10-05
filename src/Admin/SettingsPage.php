<?php
/**
 * Global settings screen.
 *
 * @package Glixform
 */

namespace Glixform\Admin;

use Glixform\Plugin;
use Glixform\Process\Captcha;

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
	 * Sanitize submitted settings. An empty secret field keeps the stored secret.
	 *
	 * @param mixed $input Raw input.
	 * @return array
	 */
	public function sanitize( $input ) {
		$input    = is_array( $input ) ? $input : array();
		$current  = wp_parse_args( (array) get_option( 'glixform_settings', array() ), Plugin::default_settings() );
		$provider = (string) ( $input['captcha_provider'] ?? '' );
		$secret   = trim( sanitize_text_field( (string) ( $input['captcha_secret_key'] ?? '' ) ) );

		return array(
			'store_ip'               => ! empty( $input['store_ip'] ),
			'min_submit_seconds'     => min( 60, absint( $input['min_submit_seconds'] ?? 2 ) ),
			'delete_on_uninstall'    => ! empty( $input['delete_on_uninstall'] ),
			'captcha_provider'       => array_key_exists( $provider, Captcha::labels() ) ? $provider : '',
			'captcha_site_key'       => trim( sanitize_text_field( (string) ( $input['captcha_site_key'] ?? '' ) ) ),
			'captcha_secret_key'     => '' === $secret ? (string) $current['captcha_secret_key'] : $secret,
			'recaptcha_v3_threshold' => max( 0.1, min( 0.9, round( (float) ( $input['recaptcha_v3_threshold'] ?? 0.5 ), 1 ) ) ),
			'retention_days'         => min( 3650, absint( $input['retention_days'] ?? 0 ) ),
		);
	}

	/**
	 * Render the screen.
	 */
	public function render() {
		Admin::check_permission();
		$settings = wp_parse_args( (array) get_option( 'glixform_settings', array() ), Plugin::default_settings() );
		$name     = 'glixform_settings';
		?>
		<div class="wrap glixform-admin">
			<?php Admin::header( __( 'Settings', 'glixform' ) ); ?>
			<?php settings_errors(); ?>
			<form method="post" action="options.php" class="glixform-settings-form">
				<?php settings_fields( 'glixform_settings' ); ?>

				<section class="glixform-card glixform-settings-card">
					<header>
						<span class="dashicons dashicons-shield" aria-hidden="true"></span>
						<div>
							<h2><?php esc_html_e( 'Spam protection', 'glixform' ); ?></h2>
							<p><?php esc_html_e( 'Every form has a hidden honeypot and a time check. Add a CAPTCHA for extra protection, then turn it on per form in the builder (Settings → Spam protection).', 'glixform' ); ?></p>
						</div>
					</header>
					<div class="glixform-field-row">
						<label for="glixform-min-seconds"><?php esc_html_e( 'Minimum time to fill in a form', 'glixform' ); ?></label>
						<div>
							<input type="number" min="0" max="60" class="small-text" id="glixform-min-seconds" name="<?php echo esc_attr( $name ); ?>[min_submit_seconds]" value="<?php echo (int) $settings['min_submit_seconds']; ?>"> <?php esc_html_e( 'seconds', 'glixform' ); ?>
							<p class="description"><?php esc_html_e( 'Submissions faster than this are rejected. Bots are usually instant.', 'glixform' ); ?></p>
						</div>
					</div>
					<div class="glixform-field-row">
						<label for="glixform-captcha-provider"><?php esc_html_e( 'CAPTCHA provider', 'glixform' ); ?></label>
						<div>
							<select id="glixform-captcha-provider" name="<?php echo esc_attr( $name ); ?>[captcha_provider]">
								<?php foreach ( Captcha::labels() as $value => $label ) : ?>
									<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $settings['captcha_provider'], $value ); ?>><?php echo esc_html( $label ); ?></option>
								<?php endforeach; ?>
							</select>
							<p class="description"><?php esc_html_e( 'Cloudflare Turnstile is free, privacy-friendly and usually invisible to visitors.', 'glixform' ); ?></p>
						</div>
					</div>
					<div class="glixform-field-row" data-captcha-only>
						<label for="glixform-site-key"><?php esc_html_e( 'Site key', 'glixform' ); ?></label>
						<div><input type="text" class="regular-text code" id="glixform-site-key" name="<?php echo esc_attr( $name ); ?>[captcha_site_key]" value="<?php echo esc_attr( $settings['captcha_site_key'] ); ?>" autocomplete="off"></div>
					</div>
					<div class="glixform-field-row" data-captcha-only>
						<label for="glixform-secret-key"><?php esc_html_e( 'Secret key', 'glixform' ); ?></label>
						<div>
							<input type="password" class="regular-text code" id="glixform-secret-key" name="<?php echo esc_attr( $name ); ?>[captcha_secret_key]" value="" autocomplete="new-password" placeholder="<?php echo $settings['captcha_secret_key'] ? esc_attr__( 'Saved — leave empty to keep it', 'glixform' ) : ''; ?>">
						</div>
					</div>
					<div class="glixform-field-row" data-captcha-only="recaptcha_v3">
						<label for="glixform-threshold"><?php esc_html_e( 'reCAPTCHA v3 minimum score', 'glixform' ); ?></label>
						<div>
							<input type="number" min="0.1" max="0.9" step="0.1" class="small-text" id="glixform-threshold" name="<?php echo esc_attr( $name ); ?>[recaptcha_v3_threshold]" value="<?php echo esc_attr( (string) $settings['recaptcha_v3_threshold'] ); ?>">
							<p class="description"><?php esc_html_e( 'From 0.1 (lenient) to 0.9 (strict). 0.5 is a good default.', 'glixform' ); ?></p>
						</div>
					</div>
				</section>

				<section class="glixform-card glixform-settings-card">
					<header>
						<span class="dashicons dashicons-privacy" aria-hidden="true"></span>
						<div>
							<h2><?php esc_html_e( 'Privacy', 'glixform' ); ?></h2>
							<p><?php esc_html_e( 'Entries are included in WordPress’s Tools → Export/Erase Personal Data, matched by email address.', 'glixform' ); ?></p>
						</div>
					</header>
					<div class="glixform-field-row">
						<span class="glixform-row-label"><?php esc_html_e( 'Visitor details', 'glixform' ); ?></span>
						<div><label><input type="checkbox" name="<?php echo esc_attr( $name ); ?>[store_ip]" value="1" <?php checked( $settings['store_ip'] ); ?>> <?php esc_html_e( 'Store the IP address and browser with each entry', 'glixform' ); ?></label></div>
					</div>
					<div class="glixform-field-row">
						<label for="glixform-retention"><?php esc_html_e( 'Delete entries after', 'glixform' ); ?></label>
						<div>
							<input type="number" min="0" max="3650" class="small-text" id="glixform-retention" name="<?php echo esc_attr( $name ); ?>[retention_days]" value="<?php echo (int) $settings['retention_days']; ?>"> <?php esc_html_e( 'days', 'glixform' ); ?>
							<p class="description"><?php esc_html_e( '0 keeps entries forever. Old entries and their files are removed once a day.', 'glixform' ); ?></p>
						</div>
					</div>
				</section>

				<section class="glixform-card glixform-settings-card">
					<header>
						<span class="dashicons dashicons-trash" aria-hidden="true"></span>
						<div>
							<h2><?php esc_html_e( 'Uninstall', 'glixform' ); ?></h2>
						</div>
					</header>
					<div class="glixform-field-row">
						<span class="glixform-row-label"><?php esc_html_e( 'When deleting the plugin', 'glixform' ); ?></span>
						<div><label><input type="checkbox" name="<?php echo esc_attr( $name ); ?>[delete_on_uninstall]" value="1" <?php checked( $settings['delete_on_uninstall'] ); ?>> <?php esc_html_e( 'Also delete all forms, entries, uploaded files and settings', 'glixform' ); ?></label></div>
					</div>
				</section>

				<?php submit_button( __( 'Save settings', 'glixform' ) ); ?>
			</form>
		</div>
		<script>
		( function () {
			var select = document.getElementById( 'glixform-captcha-provider' );
			function sync() {
				document.querySelectorAll( '[data-captcha-only]' ).forEach( function ( row ) {
					var only = row.getAttribute( 'data-captcha-only' );
					row.hidden = ! select.value || ( only && only !== select.value );
				} );
			}
			select.addEventListener( 'change', sync );
			sync();
		} )();
		</script>
		<?php
	}
}

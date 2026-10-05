<?php
/**
 * Glixform → Email: SMTP settings, test email and email log.
 *
 * @package Glixform
 */

namespace Glixform\Admin;

use Glixform\Mail\Conflicts;
use Glixform\Mail\Crypto;
use Glixform\Mail\EmailLog;
use Glixform\Mail\Providers;
use Glixform\Mail\SmtpMailer;
use Glixform\Mail\SmtpSettings;

defined( 'ABSPATH' ) || exit;

/**
 * Email delivery screen.
 */
class EmailPage {

	const LOG_PER_PAGE = 20;

	/**
	 * Hook into WordPress.
	 */
	public function register_hooks() {
		add_action( 'admin_init', array( $this, 'register' ) );
		add_action( 'wp_ajax_glixform_email_test', array( $this, 'ajax_test' ) );
		add_action( 'admin_post_glixform_clear_email_log', array( $this, 'clear_log' ) );
	}

	/**
	 * Register the option.
	 */
	public function register() {
		register_setting(
			'glixform_email',
			SmtpSettings::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( SmtpSettings::class, 'sanitize' ),
				'default'           => SmtpSettings::defaults(),
			)
		);
	}

	/**
	 * Enqueue the screen's script.
	 */
	public function enqueue() {
		wp_enqueue_script( 'glixform-email', GLIXFORM_URL . 'assets/js/email-settings.js', array(), GLIXFORM_VERSION, true );
		wp_localize_script(
			'glixform-email',
			'glixformEmail',
			array(
				'ajaxUrl'   => admin_url( 'admin-ajax.php' ),
				'nonce'     => wp_create_nonce( 'glixform_email_test' ),
				'providers' => Providers::all(),
				'i18n'      => array(
					'sending'     => __( 'Sending…', 'glixform' ),
					'send'        => __( 'Send test email', 'glixform' ),
					'failed'      => __( 'The request failed. Please reload the page and try again.', 'glixform' ),
					'server'      => __( 'Server replies', 'glixform' ),
					'docs'        => __( 'Setup guide', 'glixform' ),
					'unsaved'     => __( 'You have unsaved changes.', 'glixform' ),
					'unsavedHint' => __( 'Click "Save settings" first, then send the test email.', 'glixform' ),
				),
			)
		);
	}

	/**
	 * Clear the email log.
	 */
	public function clear_log() {
		Admin::check_permission();
		check_admin_referer( 'glixform_clear_email_log' );
		EmailLog::clear();
		wp_safe_redirect( add_query_arg( 'glixform_notice', 'log_cleared', admin_url( 'admin.php?page=glixform-email' ) ) . '#glixform-email-log' );
		exit;
	}

	/**
	 * Send a test email and report the outcome in plain language.
	 */
	public function ajax_test() {
		if ( ! current_user_can( \Glixform\Plugin::capability() ) || ! check_ajax_referer( 'glixform_email_test', 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to do this.', 'glixform' ) ), 403 );
		}

		$settings = SmtpSettings::get();
		if ( '' === Conflicts::active_plugin() && ! SmtpSettings::uses_smtp( $settings ) && 'default' !== $settings['provider'] ) {
			wp_send_json_error(
				array(
					'message' => __( 'SMTP is not switched on yet: the SMTP host is empty.', 'glixform' ),
					'hint'    => __( 'Enter the SMTP host (for Gmail: smtp.gmail.com), click Save settings, then send the test again.', 'glixform' ),
				)
			);
		}

		$to = isset( $_POST['to'] ) ? sanitize_email( wp_unslash( $_POST['to'] ) ) : '';
		if ( ! is_email( $to ) ) {
			wp_send_json_error( array( 'message' => __( 'Please enter a valid email address to send the test to.', 'glixform' ) ) );
		}

		$error = '';
		$catch = static function ( $wp_error ) use ( &$error ) {
			$error = $wp_error->get_error_message();
		};
		add_action( 'wp_mail_failed', $catch );

		SmtpMailer::$transcript = array();
		EmailLog::set_source( 'test' );
		$sent = wp_mail(
			$to,
			/* translators: %s: site name. */
			sprintf( __( 'Glixform test email from %s', 'glixform' ), wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ) ),
			$this->test_body(),
			array( 'Content-Type: text/html; charset=UTF-8' )
		);
		EmailLog::set_source( '' );
		$transcript             = array_slice( (array) SmtpMailer::$transcript, -6 );
		SmtpMailer::$transcript = null;
		remove_action( 'wp_mail_failed', $catch );

		$via = $this->delivery_label();

		if ( $sent ) {
			wp_send_json_success(
				array(
					/* translators: 1: recipient, 2: how it was sent, e.g. "SMTP (smtp.gmail.com)". */
					'message' => sprintf( __( 'Test email sent to %1$s via %2$s. Check the inbox (and the spam folder, just in case).', 'glixform' ), $to, $via ),
				)
			);
		}

		wp_send_json_error(
			array(
				/* translators: %s: technical error message. */
				'message' => sprintf( __( 'The test email could not be sent: %s', 'glixform' ), $error ? $error : __( 'unknown error', 'glixform' ) ),
				'hint'    => ( 'default' === $settings['provider'] && '' === Conflicts::active_plugin() )
					? __( 'Glixform is still using the WordPress default mailer, which this server does not support. Choose your provider above (for example Gmail), fill in the details, click Save settings, and wait for the badge at the top to say "Sending via …" before testing.', 'glixform' )
					: self::hint( $error . ' ' . implode( ' ', array_slice( $transcript, -2 ) ) ),
				'server'  => $transcript,
			)
		);
	}

	/**
	 * Turn a technical SMTP error into advice.
	 *
	 * @param string $text Error message and the server's last reply (earlier replies,
	 *                     like the greeting listing AUTH/STARTTLS, would cause false matches).
	 * @return string
	 */
	public static function hint( $text ) {
		$text = strtolower( (string) $text );
		// Gmail splits long replies over several lines; check them together.
		$text = (string) preg_replace( '/\s+/', ' ', $text );
		$map  = array(
			array( array( 'application-specific password required', 'invalidsecondfactor' ), __( 'Gmail needs an App Password here, not your normal Google password. Go to myaccount.google.com, turn on 2-Step Verification, search for "App passwords", create one and paste the 16-character code into SMTP password, then save and test again.', 'glixform' ) ),
			array( array( 'badcredentials', 'username and password not accepted' ), __( 'Gmail rejected the username or App Password. Check the username is your full Gmail address and paste the App Password again (spaces do not matter). If you changed your Google password, App Passwords are revoked: create a new one.', 'glixform' ) ),
			array( array( 'could not authenticate', '535', '534', 'username and password not accepted', 'authentication failed', 'authentication unsuccessful' ), __( 'The username or password was rejected. Check them carefully. Gmail and many providers need an App Password instead of your normal password.', 'glixform' ) ),
			array( array( 'certificate', 'ssl routines', 'crypto', 'connection over tls', 'starttls failed', 'secure connection' ), __( 'The secure connection failed. Use TLS with port 587, or SSL with port 465, and check the host name is exactly what your provider gives.', 'glixform' ) ),
			array( array( 'failed to connect', 'could not connect', 'connection refused', 'timed out', 'timeout', 'network is unreachable', 'getaddrinfo', 'name or service not known' ), __( 'Could not reach the mail server. Check the host and port. Some hosts block port 587/465; try the other one, or port 2525, or ask your host to open it.', 'glixform' ) ),
			array( array( 'sender', 'from address', '550', '553', '554', 'not owned', 'relay' ), __( 'The server refused the sender. The From Email usually has to be the same address you log in with, or a sender you verified with your provider.', 'glixform' ) ),
			array( array( 'invalid address', 'you must provide at least one recipient' ), __( 'One of the email addresses is not valid.', 'glixform' ) ),
			array( array( 'could not instantiate mail function', 'mail()' ), __( 'This server cannot send email by itself. Choose an SMTP provider above to fix this.', 'glixform' ) ),
		);
		foreach ( $map as $item ) {
			foreach ( $item[0] as $needle ) {
				if ( false !== strpos( $text, $needle ) ) {
					return $item[1];
				}
			}
		}
		return __( 'Double-check every setting against your provider’s instructions, or contact your host.', 'glixform' );
	}

	/**
	 * How email is currently sent, for messages.
	 *
	 * @return string
	 */
	private function delivery_label() {
		$conflict = Conflicts::active_plugin();
		if ( $conflict ) {
			return $conflict;
		}
		$settings = SmtpSettings::get();
		if ( SmtpSettings::uses_smtp( $settings ) ) {
			/* translators: %s: SMTP host. */
			return sprintf( __( 'SMTP (%s)', 'glixform' ), $settings['host'] );
		}
		return __( 'the WordPress default mailer', 'glixform' );
	}

	/**
	 * Body of the test email.
	 *
	 * @return string
	 */
	private function test_body() {
		return '<div style="font-family:-apple-system,BlinkMacSystemFont,Segoe UI,Roboto,Helvetica,Arial,sans-serif;max-width:560px;margin:0 auto;padding:24px;background:#fff;border-radius:12px;border-top:4px solid #6d4aff;color:#111827">'
			. '<h1 style="font-size:20px;margin:0 0 12px">' . esc_html__( 'It works! 🎉', 'glixform' ) . '</h1>'
			. '<p style="font-size:15px;line-height:1.6;margin:0 0 12px">' . esc_html__( 'This test email was sent by Glixform. Your form notifications will be delivered the same way.', 'glixform' ) . '</p>'
			/* translators: %s: how the email was sent. */
			. '<p style="font-size:13px;color:#6b7280;margin:0">' . esc_html( sprintf( __( 'Sent via %s', 'glixform' ), $this->delivery_label() ) ) . ' · ' . esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) ) ) . '</p>'
			. '</div>';
	}

	/**
	 * Render the screen.
	 */
	public function render() {
		Admin::check_permission();

		$settings  = SmtpSettings::get();
		$conflict  = Conflicts::active_plugin();
		$providers = Providers::all();
		$name      = SmtpSettings::OPTION;
		$uses_smtp = SmtpSettings::uses_smtp( $settings );

		if ( $conflict ) {
			/* translators: %s: plugin name. */
			$status = '<span class="glixform-pill glixform-pill-info">' . esc_html( sprintf( __( 'Handled by %s', 'glixform' ), $conflict ) ) . '</span>';
		} elseif ( $uses_smtp ) {
			/* translators: %s: SMTP host. */
			$status = '<span class="glixform-pill">' . esc_html( sprintf( __( 'Sending via %s', 'glixform' ), $settings['host'] ) ) . '</span>';
		} else {
			$status = '<span class="glixform-pill glixform-pill-warn">' . esc_html__( 'WordPress default (may go to spam)', 'glixform' ) . '</span>';
		}
		?>
		<div class="wrap glixform-admin glixform-email">
			<?php
			Admin::header( __( 'Email delivery', 'glixform' ), array(), $status );
			settings_errors();
			Admin::notice( array( 'log_cleared' => __( 'Email log cleared.', 'glixform' ) ) );
			?>

			<?php if ( $conflict ) : ?>
				<div class="glixform-callout-card">
					<span class="dashicons dashicons-info-outline" aria-hidden="true"></span>
					<div>
						<?php /* translators: %s: plugin name. */ ?>
						<strong><?php echo esc_html( sprintf( __( '%s is active', 'glixform' ), $conflict ) ); ?></strong>
						<p><?php esc_html_e( 'Your emails, including Glixform notifications, are already sent by that plugin, so the settings below are not used. You can still send a test email and see the email log here.', 'glixform' ); ?></p>
					</div>
				</div>
			<?php endif; ?>

			<form method="post" action="options.php" class="glixform-settings-form" id="glixform-email-form">
				<?php settings_fields( 'glixform_email' ); ?>

				<section class="glixform-card glixform-settings-card">
					<header>
						<span class="dashicons dashicons-email-alt" aria-hidden="true"></span>
						<div>
							<h2><?php esc_html_e( 'Mailer', 'glixform' ); ?></h2>
							<p><?php esc_html_e( 'Choose how your site sends email. An SMTP service makes sure form notifications reach the inbox instead of spam.', 'glixform' ); ?></p>
						</div>
					</header>
					<fieldset class="glixform-provider-grid">
						<legend class="screen-reader-text"><?php esc_html_e( 'Mailer', 'glixform' ); ?></legend>
						<label class="glixform-provider">
							<input type="radio" name="<?php echo esc_attr( $name ); ?>[provider]" value="default" <?php checked( $settings['provider'], 'default' ); ?>>
							<?php echo $this->logo( 'default' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in logo(). ?>
							<span class="glixform-provider-text">
								<span class="glixform-provider-name"><?php esc_html_e( 'WordPress default', 'glixform' ); ?></span>
								<span class="glixform-provider-note"><?php esc_html_e( 'Not recommended', 'glixform' ); ?></span>
							</span>
						</label>
						<?php foreach ( $providers as $slug => $provider ) : ?>
							<label class="glixform-provider">
								<input type="radio" name="<?php echo esc_attr( $name ); ?>[provider]" value="<?php echo esc_attr( $slug ); ?>" <?php checked( $settings['provider'], $slug ); ?>>
								<?php echo $this->logo( $slug ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in logo(). ?>
								<span class="glixform-provider-text">
									<span class="glixform-provider-name"><?php echo esc_html( $provider['name'] ); ?></span>
									<?php if ( in_array( $slug, array( 'gmail', 'brevo', 'sendgrid' ), true ) ) : ?>
										<span class="glixform-provider-note"><?php esc_html_e( 'Popular', 'glixform' ); ?></span>
									<?php endif; ?>
								</span>
							</label>
						<?php endforeach; ?>
					</fieldset>
				</section>

				<section class="glixform-card glixform-settings-card" data-smtp-only>
					<header>
						<span class="dashicons dashicons-admin-network" aria-hidden="true"></span>
						<div>
							<h2><?php esc_html_e( 'Mail server', 'glixform' ); ?></h2>
							<p class="glixform-provider-help" id="glixform-provider-help"></p>
						</div>
					</header>
					<div class="glixform-field-row">
						<label for="glixform-smtp-host"><?php esc_html_e( 'SMTP host', 'glixform' ); ?></label>
						<div>
							<input type="text" class="regular-text code" id="glixform-smtp-host" name="<?php echo esc_attr( $name ); ?>[host]" value="<?php echo esc_attr( $settings['host'] ); ?>" placeholder="smtp.example.com" autocomplete="off" spellcheck="false">
						</div>
					</div>
					<div class="glixform-field-row">
						<span class="glixform-row-label" id="glixform-enc-label"><?php esc_html_e( 'Encryption', 'glixform' ); ?></span>
						<div>
							<div class="glixform-segmented" role="radiogroup" aria-labelledby="glixform-enc-label">
								<?php
								foreach ( array(
									'none' => __( 'None', 'glixform' ),
									'ssl'  => 'SSL',
									'tls'  => 'TLS',
								) as $value => $label ) :
									?>
									<label><input type="radio" name="<?php echo esc_attr( $name ); ?>[encryption]" value="<?php echo esc_attr( $value ); ?>" <?php checked( $settings['encryption'], $value ); ?>><span><?php echo esc_html( $label ); ?></span></label>
								<?php endforeach; ?>
							</div>
							<p class="description"><?php esc_html_e( 'TLS is recommended for most servers.', 'glixform' ); ?></p>
						</div>
					</div>
					<div class="glixform-field-row">
						<label for="glixform-smtp-port"><?php esc_html_e( 'SMTP port', 'glixform' ); ?></label>
						<div>
							<input type="number" min="1" max="65535" class="small-text" id="glixform-smtp-port" name="<?php echo esc_attr( $name ); ?>[port]" value="<?php echo (int) $settings['port']; ?>">
							<p class="description"><?php esc_html_e( 'Set automatically from the encryption (TLS 587, SSL 465). Change only if your provider says so.', 'glixform' ); ?></p>
						</div>
					</div>
					<div class="glixform-field-row">
						<span class="glixform-row-label"><?php esc_html_e( 'Authentication', 'glixform' ); ?></span>
						<div><label class="glixform-switch"><input type="checkbox" id="glixform-smtp-auth" name="<?php echo esc_attr( $name ); ?>[auth]" value="1" <?php checked( $settings['auth'] ); ?>><span><?php esc_html_e( 'Log in to the mail server (almost always needed)', 'glixform' ); ?></span></label></div>
					</div>
					<div class="glixform-field-row" data-auth-only>
						<label for="glixform-smtp-username"><?php esc_html_e( 'SMTP username', 'glixform' ); ?></label>
						<div>
							<input type="text" class="regular-text" id="glixform-smtp-username" name="<?php echo esc_attr( $name ); ?>[username]" value="<?php echo esc_attr( $settings['username'] ); ?>" autocomplete="off" spellcheck="false">
							<p class="description" id="glixform-username-hint"></p>
						</div>
					</div>
					<div class="glixform-field-row" data-auth-only>
						<label for="glixform-smtp-password"><?php esc_html_e( 'SMTP password', 'glixform' ); ?></label>
						<div>
							<?php if ( defined( 'GLIXFORM_SMTP_PASSWORD' ) ) : ?>
								<p><span class="glixform-pill"><?php esc_html_e( 'Set in wp-config.php', 'glixform' ); ?></span></p>
							<?php else : ?>
								<input type="password" class="regular-text" id="glixform-smtp-password" name="<?php echo esc_attr( $name ); ?>[new_password]" value="" autocomplete="new-password" placeholder="<?php echo SmtpSettings::has_password() ? esc_attr__( 'Saved — leave empty to keep it', 'glixform' ) : ''; ?>">
								<p class="description" id="glixform-password-hint"></p>
								<?php if ( SmtpSettings::has_password() ) : ?>
									<label class="glixform-inline-check"><input type="checkbox" name="<?php echo esc_attr( $name ); ?>[remove_password]" value="1"> <?php esc_html_e( 'Remove the saved password', 'glixform' ); ?></label>
								<?php endif; ?>
								<p class="description">
									<?php
									echo esc_html(
										Crypto::available()
											? __( 'Stored encrypted. It is never shown again after saving.', 'glixform' )
											: __( 'This server lacks OpenSSL, so the password is only obscured. Consider defining GLIXFORM_SMTP_PASSWORD in wp-config.php instead.', 'glixform' )
									);
									?>
								</p>
							<?php endif; ?>
						</div>
					</div>
				</section>

				<section class="glixform-card glixform-settings-card">
					<header>
						<span class="dashicons dashicons-id" aria-hidden="true"></span>
						<div>
							<h2><?php esc_html_e( 'Sender', 'glixform' ); ?></h2>
							<p><?php esc_html_e( 'The name and address your emails come from. For best delivery, use the same address you log in to the mail server with.', 'glixform' ); ?></p>
						</div>
					</header>
					<div class="glixform-field-row">
						<label for="glixform-from-name"><?php esc_html_e( 'From name', 'glixform' ); ?></label>
						<div>
							<input type="text" class="regular-text" id="glixform-from-name" name="<?php echo esc_attr( $name ); ?>[from_name]" value="<?php echo esc_attr( $settings['from_name'] ); ?>" placeholder="<?php echo esc_attr( wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ) ); ?>">
							<label class="glixform-switch"><input type="checkbox" name="<?php echo esc_attr( $name ); ?>[force_from_name]" value="1" <?php checked( $settings['force_from_name'] ); ?>><span><?php esc_html_e( 'Use for all emails, even when another plugin sets its own name', 'glixform' ); ?></span></label>
						</div>
					</div>
					<div class="glixform-field-row">
						<label for="glixform-from-email"><?php esc_html_e( 'From email', 'glixform' ); ?></label>
						<div>
							<input type="email" class="regular-text" id="glixform-from-email" name="<?php echo esc_attr( $name ); ?>[from_email]" value="<?php echo esc_attr( $settings['from_email'] ); ?>" placeholder="<?php echo esc_attr( get_option( 'admin_email' ) ); ?>">
							<label class="glixform-switch"><input type="checkbox" name="<?php echo esc_attr( $name ); ?>[force_from_email]" value="1" <?php checked( $settings['force_from_email'] ); ?>><span><?php esc_html_e( 'Use for all emails, even when another plugin sets its own address', 'glixform' ); ?></span></label>
						</div>
					</div>
				</section>

				<section class="glixform-card glixform-settings-card">
					<header>
						<span class="dashicons dashicons-list-view" aria-hidden="true"></span>
						<div>
							<h2><?php esc_html_e( 'Email log', 'glixform' ); ?></h2>
							<p><?php esc_html_e( 'Keeps the recipient, subject and result of every email the site sends, so you can see if a notification failed. Email contents are not stored.', 'glixform' ); ?></p>
						</div>
					</header>
					<div class="glixform-field-row">
						<span class="glixform-row-label"><?php esc_html_e( 'Logging', 'glixform' ); ?></span>
						<div><label class="glixform-switch"><input type="checkbox" name="<?php echo esc_attr( $name ); ?>[log_enabled]" value="1" <?php checked( $settings['log_enabled'] ); ?>><span><?php esc_html_e( 'Keep a log of sent emails', 'glixform' ); ?></span></label></div>
					</div>
					<div class="glixform-field-row">
						<label for="glixform-log-days"><?php esc_html_e( 'Keep entries for', 'glixform' ); ?></label>
						<div><input type="number" min="1" max="365" class="small-text" id="glixform-log-days" name="<?php echo esc_attr( $name ); ?>[log_days]" value="<?php echo (int) $settings['log_days']; ?>"> <?php esc_html_e( 'days', 'glixform' ); ?></div>
					</div>
				</section>

				<?php submit_button( __( 'Save settings', 'glixform' ) ); ?>
			</form>

			<section class="glixform-card glixform-settings-card glixform-test-card">
				<header>
					<span class="dashicons dashicons-yes-alt" aria-hidden="true"></span>
					<div>
						<h2><?php esc_html_e( 'Send a test email', 'glixform' ); ?></h2>
						<p><?php esc_html_e( 'Uses the saved settings. Save first if you changed anything above.', 'glixform' ); ?></p>
					</div>
				</header>
				<div class="glixform-field-row">
					<label for="glixform-test-to"><?php esc_html_e( 'Send to', 'glixform' ); ?></label>
					<div>
						<div class="glixform-inline-form">
							<input type="email" class="regular-text" id="glixform-test-to" value="<?php echo esc_attr( wp_get_current_user()->user_email ); ?>">
							<button type="button" class="button button-primary" id="glixform-test-send"><?php esc_html_e( 'Send test email', 'glixform' ); ?></button>
						</div>
						<div id="glixform-test-result" class="glixform-test-result" role="status" aria-live="polite" hidden></div>
					</div>
				</div>
			</section>

			<?php $this->render_log(); ?>
		</div>
		<?php
		Admin::confirm_script();
	}

	/**
	 * Logo for a mailer tile (assets/images/mailers/{slug}.svg).
	 *
	 * @param string $slug Provider slug, or "default".
	 * @return string
	 */
	private function logo( $slug ) {
		$file = 'assets/images/mailers/' . sanitize_key( $slug ) . '.svg';
		if ( ! file_exists( GLIXFORM_DIR . $file ) ) {
			$file = 'assets/images/mailers/other.svg';
		}
		return sprintf(
			'<span class="glixform-provider-logo"><img src="%s" alt="" width="28" height="28" loading="lazy" decoding="async"></span>',
			esc_url( GLIXFORM_URL . $file . '?ver=' . GLIXFORM_VERSION )
		);
	}

	/**
	 * The email log table.
	 */
	private function render_log() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only filters.
		$status = isset( $_GET['log_status'] ) ? sanitize_key( wp_unslash( $_GET['log_status'] ) ) : '';
		$search = isset( $_GET['log_s'] ) ? sanitize_text_field( wp_unslash( $_GET['log_s'] ) ) : '';
		$paged  = isset( $_GET['log_page'] ) ? max( 1, absint( $_GET['log_page'] ) ) : 1;
		// phpcs:enable

		list( $rows, $total ) = EmailLog::query(
			array(
				'status'   => $status,
				'search'   => $search,
				'page'     => $paged,
				'per_page' => self::LOG_PER_PAGE,
			)
		);
		$counts               = EmailLog::counts();
		$base                 = admin_url( 'admin.php?page=glixform-email' );
		$pages                = (int) ceil( $total / self::LOG_PER_PAGE );
		$clear                = wp_nonce_url( admin_url( 'admin-post.php?action=glixform_clear_email_log' ), 'glixform_clear_email_log' );
		$labels               = array(
			'glixform' => __( 'Form notification', 'glixform' ),
			'test'     => __( 'Test email', 'glixform' ),
			''         => __( 'Other', 'glixform' ),
		);
		?>
		<section class="glixform-card glixform-table-card glixform-email-log" id="glixform-email-log">
			<div class="glixform-log-head">
				<h2><?php esc_html_e( 'Recent emails', 'glixform' ); ?></h2>
				<ul class="subsubsub">
					<?php
					$views = array(
						''       => __( 'All', 'glixform' ),
						'sent'   => __( 'Sent', 'glixform' ),
						'failed' => __( 'Failed', 'glixform' ),
					);
					$links = array();
					foreach ( $views as $key => $label ) {
						$links[] = sprintf(
							'<li><a href="%s"%s>%s <span class="count">(%s)</span></a></li>',
							esc_url( ( $key ? add_query_arg( 'log_status', $key, $base ) : $base ) . '#glixform-email-log' ),
							$status === $key ? ' class="current" aria-current="page"' : '',
							esc_html( $label ),
							esc_html( number_format_i18n( $counts[ $key ? $key : 'all' ] ) )
						);
					}
					echo implode( ' | ', $links ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped above.
					?>
				</ul>
				<form method="get" class="glixform-log-search">
					<input type="hidden" name="page" value="glixform-email">
					<?php if ( $status ) : ?>
						<input type="hidden" name="log_status" value="<?php echo esc_attr( $status ); ?>">
					<?php endif; ?>
					<label class="screen-reader-text" for="glixform-log-s"><?php esc_html_e( 'Search emails', 'glixform' ); ?></label>
					<input type="search" id="glixform-log-s" name="log_s" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'Search recipient or subject', 'glixform' ); ?>">
					<button class="button"><?php esc_html_e( 'Search', 'glixform' ); ?></button>
					<?php if ( $counts['all'] ) : ?>
						<a class="button glixform-button-danger glixform-confirm" href="<?php echo esc_url( $clear ); ?>" data-confirm="<?php esc_attr_e( 'Delete the whole email log?', 'glixform' ); ?>"><?php esc_html_e( 'Clear log', 'glixform' ); ?></a>
					<?php endif; ?>
				</form>
			</div>

			<?php if ( ! $rows ) : ?>
				<p class="glixform-log-empty"><?php esc_html_e( 'No emails logged yet. Send a test email or submit a form to see it here.', 'glixform' ); ?></p>
			<?php else : ?>
				<table class="wp-list-table widefat">
					<thead>
						<tr>
							<th scope="col"><?php esc_html_e( 'Date', 'glixform' ); ?></th>
							<th scope="col"><?php esc_html_e( 'To', 'glixform' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Subject', 'glixform' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Type', 'glixform' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Status', 'glixform' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $rows as $row ) : ?>
							<tr>
								<td><?php echo esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $row['created_at'] . ' UTC' ) ) ); ?></td>
								<td><?php echo esc_html( $row['to_email'] ); ?></td>
								<td><?php echo esc_html( $row['subject'] ); ?></td>
								<td><?php echo esc_html( $labels[ $row['source'] ] ?? $labels[''] ); ?></td>
								<td>
									<?php if ( 'sent' === $row['status'] ) : ?>
										<span class="glixform-pill"><?php esc_html_e( 'Sent', 'glixform' ); ?></span>
									<?php else : ?>
										<span class="glixform-pill glixform-pill-spam"><?php esc_html_e( 'Failed', 'glixform' ); ?></span>
										<div class="glixform-log-error"><?php echo esc_html( $row['error'] ); ?></div>
									<?php endif; ?>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
				<?php if ( $pages > 1 ) : ?>
					<div class="glixform-log-pages">
						<?php
						echo wp_kses_post(
							paginate_links(
								array(
									'base'    => add_query_arg( 'log_page', '%#%', remove_query_arg( 'log_page' ) ) . '#glixform-email-log',
									'format'  => '',
									'current' => $paged,
									'total'   => $pages,
								)
							)
						);
						?>
					</div>
				<?php endif; ?>
			<?php endif; ?>
		</section>
		<?php
	}
}

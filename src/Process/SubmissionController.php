<?php
/**
 * Receives submissions over AJAX and plain POST.
 *
 * @package Glixform
 */

namespace Glixform\Process;

use Glixform\Frontend\Renderer;

defined( 'ABSPATH' ) || exit;

/**
 * Two entry points sharing one pipeline:
 * - AJAX (admin-ajax.php?action=glixform_submit), used when JavaScript runs;
 * - a regular POST to the page itself, so forms work without JavaScript.
 */
class SubmissionController {

	/**
	 * Processor.
	 *
	 * @var Submission
	 */
	private $submission;

	/**
	 * Renderer (receives errors to re-display after a non-AJAX POST).
	 *
	 * @var Renderer
	 */
	private $renderer;

	/**
	 * Constructor.
	 *
	 * @param Submission $submission Processor.
	 * @param Renderer   $renderer   Renderer.
	 */
	public function __construct( Submission $submission, Renderer $renderer ) {
		$this->submission = $submission;
		$this->renderer   = $renderer;
	}

	/**
	 * Hook into WordPress.
	 */
	public function register_hooks() {
		add_action( 'wp_ajax_glixform_submit', array( $this, 'ajax' ) );
		add_action( 'wp_ajax_nopriv_glixform_submit', array( $this, 'ajax' ) );
		add_action( 'template_redirect', array( $this, 'post' ), 5 );
	}

	/**
	 * Submitted data from the request.
	 *
	 * @return array|null
	 */
	private function request_data() {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Public form; protected by AntiSpam token instead of a nonce (see AntiSpam).
		if ( empty( $_POST['glixform'] ) || ! is_array( $_POST['glixform'] ) ) {
			return null;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Each value is sanitized by its field type.
		$data = wp_unslash( $_POST['glixform'] );

		// The CAPTCHA widget posts its token under the provider's own key.
		$key                      = Captcha::response_key();
		$data['captcha_response'] = $key && isset( $_POST[ $key ] ) && is_string( $_POST[ $key ] ) ? sanitize_text_field( wp_unslash( $_POST[ $key ] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing

		return $data;
	}

	/**
	 * Uploaded files grouped by field ID, as lists of name/tmp_name/size/error.
	 *
	 * @return array
	 */
	private function files() {
		$files = array();
		foreach ( $_FILES as $key => $upload ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			if ( ! preg_match( '/^glixform_file_(\d+)$/', (string) $key, $m ) || ! is_array( $upload ) || ! isset( $upload['name'] ) ) {
				continue;
			}
			$list = array();
			foreach ( (array) $upload['name'] as $index => $name ) {
				$list[] = array(
					'name'     => is_string( $name ) ? $name : '',
					'tmp_name' => (string) ( ( (array) $upload['tmp_name'] )[ $index ] ?? '' ),
					'size'     => (int) ( ( (array) $upload['size'] )[ $index ] ?? 0 ),
					'error'    => (int) ( ( (array) $upload['error'] )[ $index ] ?? UPLOAD_ERR_NO_FILE ),
				);
			}
			$files[ (int) $m[1] ] = $list;
		}
		return $files;
	}

	/**
	 * AJAX handler.
	 */
	public function ajax() {
		$data = $this->request_data();
		if ( null === $data ) {
			wp_send_json_error( array( 'message' => __( 'Invalid request.', 'glixform' ) ), 400 );
		}

		$result = $this->submission->process( $data, $this->files() );

		if ( $result['success'] ) {
			wp_send_json_success( $result['confirmation'] );
		}

		wp_send_json_error(
			array(
				'message' => $result['message'],
				'errors'  => (object) $result['errors'],
			)
		);
	}

	/**
	 * Non-JavaScript POST handler.
	 */
	public function post() {
		if ( ! isset( $_SERVER['REQUEST_METHOD'] ) || 'POST' !== $_SERVER['REQUEST_METHOD'] ) {
			return;
		}
		$data = $this->request_data();
		if ( null === $data ) {
			return;
		}

		$result = $this->submission->process( $data, $this->files() );

		if ( ! $result['success'] ) {
			$this->renderer->set_state( $result['form_id'], $result );
			return;
		}

		$confirmation = $result['confirmation'];

		if ( 'redirect' === $confirmation['type'] ) {
			wp_redirect( $confirmation['url'] ); // phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect -- URL is set by an administrator.
			exit;
		}

		// Post/Redirect/Get: keep the message briefly and show it after redirecting back.
		$key = wp_generate_password( 20, false );
		set_transient(
			'glixform_confirm_' . $key,
			array(
				'form_id' => $result['form_id'],
				'message' => $confirmation['message'],
			),
			10 * MINUTE_IN_SECONDS
		);

		$back = wp_validate_redirect( esc_url_raw( (string) ( $data['page_url'] ?? '' ) ), '' );
		if ( ! $back ) {
			$back = wp_get_referer();
		}
		if ( ! $back ) {
			$back = home_url( '/' );
		}
		$back = remove_query_arg( 'glixform_confirm', $back );

		wp_safe_redirect( add_query_arg( 'glixform_confirm', $key, $back ) . '#glixform-' . $result['form_id'] );
		exit;
	}
}

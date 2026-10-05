<?php
/**
 * Form preview for administrators.
 *
 * @package Glixform
 */

namespace Glixform\Frontend;

use Glixform\Forms\FormRepository;
use Glixform\Plugin;

defined( 'ABSPATH' ) || exit;

/**
 * Renders a form on a bare page at /?glixform_preview=ID.
 */
class Preview {

	/**
	 * Forms.
	 *
	 * @var FormRepository
	 */
	private $forms;

	/**
	 * Renderer.
	 *
	 * @var Renderer
	 */
	private $renderer;

	/**
	 * Constructor.
	 *
	 * @param FormRepository $forms    Forms.
	 * @param Renderer       $renderer Renderer.
	 */
	public function __construct( FormRepository $forms, Renderer $renderer ) {
		$this->forms    = $forms;
		$this->renderer = $renderer;
	}

	/**
	 * Hook into WordPress.
	 */
	public function register_hooks() {
		add_action( 'template_redirect', array( $this, 'maybe_render' ), 20 );
	}

	/**
	 * Preview URL for a form.
	 *
	 * @param int $form_id Form ID.
	 * @return string
	 */
	public static function url( $form_id ) {
		return add_query_arg( 'glixform_preview', absint( $form_id ), home_url( '/' ) );
	}

	/**
	 * Output the preview page.
	 */
	public function maybe_render() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Capability-checked, read-only.
		$form_id = isset( $_GET['glixform_preview'] ) ? absint( $_GET['glixform_preview'] ) : 0;
		if ( ! $form_id || ! current_user_can( Plugin::capability() ) ) {
			return;
		}
		$form = $this->forms->get( $form_id );
		if ( ! $form ) {
			return;
		}

		$html = $this->renderer->render( $form, array( 'title' => true ) );

		nocache_headers();
		?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<meta name="robots" content="noindex">
	<title><?php echo esc_html( $form['title'] ); ?> &ndash; <?php esc_html_e( 'Preview', 'glixform' ); ?></title>
		<?php wp_head(); ?>
	<style>body{background:#f4f4f5;margin:0;padding:40px 16px}.glixform-preview{max-width:720px;margin:0 auto;background:#fff;padding:32px;border-radius:8px;box-shadow:0 1px 3px rgba(0,0,0,.08)}.glixform-preview-bar{max-width:720px;margin:0 auto 16px;font:14px/1.4 -apple-system,BlinkMacSystemFont,sans-serif;color:#555}</style>
</head>
<body <?php body_class( 'glixform-preview-page' ); ?>>
	<div class="glixform-preview-bar"><?php esc_html_e( 'Form preview — only administrators can see this page.', 'glixform' ); ?></div>
	<div class="glixform-preview"><?php echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped by Renderer. ?></div>
		<?php wp_footer(); ?>
</body>
</html>
		<?php
		exit;
	}
}

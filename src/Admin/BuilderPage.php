<?php
/**
 * Form builder screen (React app in build/builder.js).
 *
 * @package Glixform
 */

namespace Glixform\Admin;

use Glixform\Forms\FormRepository;
use Glixform\Frontend\Preview;
use Glixform\Notifications\SmartTags;
use Glixform\Plugin;
use Glixform\Process\Akismet;
use Glixform\Process\Captcha;

defined( 'ABSPATH' ) || exit;

/**
 * Loads the builder app with everything it needs; saving goes through the REST API.
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
		add_filter( 'admin_body_class', array( $this, 'body_class' ) );
	}

	/**
	 * Full-height layout class on the builder screen.
	 *
	 * @param string $classes Classes.
	 * @return string
	 */
	public function body_class( $classes ) {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( $screen && false !== strpos( (string) $screen->id, 'glixform-builder' ) ) {
			$classes .= ' glixform-builder-screen';
		}
		return $classes;
	}

	/**
	 * Enqueue the builder app.
	 */
	public function enqueue() {
		$asset_file = GLIXFORM_DIR . 'build/builder.asset.php';
		if ( ! file_exists( $asset_file ) ) {
			return;
		}
		$asset = require $asset_file;

		wp_enqueue_script( 'glixform-builder', GLIXFORM_URL . 'build/builder.js', $asset['dependencies'], $asset['version'], true );
		wp_enqueue_style( 'glixform-builder', GLIXFORM_URL . 'build/style-builder.css', array( 'wp-components' ), $asset['version'] );
		wp_style_add_data( 'glixform-builder', 'rtl', 'replace' );
		wp_set_script_translations( 'glixform-builder', 'glixform', GLIXFORM_DIR . 'languages' );
		wp_enqueue_style( 'dashicons' );

		wp_add_inline_script( 'glixform-builder', 'window.glixformBuilderConfig = ' . wp_json_encode( $this->config() ) . ';', 'before' );
	}

	/**
	 * Data for the app.
	 *
	 * @return array
	 */
	private function config() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only.
		$form_id = isset( $_GET['form_id'] ) ? absint( $_GET['form_id'] ) : 0;
		$view    = isset( $_GET['view'] ) ? sanitize_key( wp_unslash( $_GET['view'] ) ) : '';
		// phpcs:enable

		$form = $form_id ? $this->plugin->forms->get( $form_id ) : null;

		$types = array();
		foreach ( $this->plugin->fields->all() as $type ) {
			$types[] = $type->to_builder_array();
		}

		$pages = array();
		foreach ( get_pages( array( 'sort_column' => 'post_title' ) ) as $page ) {
			$pages[] = array(
				'id'    => (int) $page->ID,
				'title' => $page->post_title ? $page->post_title : __( '(no title)', 'glixform' ),
			);
		}

		$provider = Captcha::provider();
		$labels   = Captcha::labels();

		return array(
			'form'              => $form ? array(
				'id'         => $form['id'],
				'title'      => $form['title'],
				'data'       => $form['data'],
				'shortcode'  => sprintf( '[glixform id="%d"]', $form['id'] ),
				'previewUrl' => Preview::url( $form['id'] ),
			) : null,
			'initialView'       => 'settings' === $view ? 'settings' : 'build',
			'types'             => $types,
			'templates'         => $form ? array() : $this->plugin->templates->all(),
			'pages'             => $pages,
			'smartTags'         => SmartTags::catalog(),
			'defaults'          => array(
				'notification' => FormRepository::default_notification( 0 ),
				'confirmation' => FormRepository::default_confirmation( 0 ),
			),
			'captchaConfigured' => '' !== $provider,
			'captchaProvider'   => $labels[ $provider ] ?? '',
			'akismetAvailable'  => Akismet::is_available(),
			'urls'              => array(
				'forms'    => admin_url( 'admin.php?page=glixform' ),
				'entries'  => admin_url( 'admin.php?page=glixform-entries' ),
				'settings' => admin_url( 'admin.php?page=glixform-settings' ),
			),
			'frontend'          => array(
				'css'      => GLIXFORM_URL . 'assets/css/frontend.css?ver=' . GLIXFORM_VERSION,
				'js'       => GLIXFORM_URL . 'assets/js/frontend.js?ver=' . GLIXFORM_VERSION,
				'settings' => \Glixform\Frontend\Assets::script_settings(),
			),
		);
	}

	/**
	 * Render the mount point.
	 */
	public function render() {
		Admin::check_permission();
		if ( ! file_exists( GLIXFORM_DIR . 'build/builder.js' ) ) {
			echo '<div class="wrap"><div class="notice notice-error gf-keep"><p>' . esc_html__( 'The builder has not been built. Run "npm install && npm run build" in the plugin folder.', 'glixform' ) . '</p></div></div>';
			return;
		}
		echo '<div id="glixform-builder-root"><noscript>' . esc_html__( 'The form builder needs JavaScript.', 'glixform' ) . '</noscript></div>';
	}
}

<?php
/**
 * Admin bootstrap: menus, assets and action handlers.
 *
 * @package Glixform
 */

namespace Glixform\Admin;

use Glixform\Plugin;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the Glixform admin screens.
 */
class Admin {

	/**
	 * Plugin.
	 *
	 * @var Plugin
	 */
	private $plugin;

	/**
	 * Screens.
	 *
	 * @var array
	 */
	private $pages = array();

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
		$this->pages = array(
			'forms'    => new FormsPage( $this->plugin ),
			'builder'  => new BuilderPage( $this->plugin ),
			'entries'  => new EntriesPage( $this->plugin ),
			'settings' => new SettingsPage(),
		);

		foreach ( $this->pages as $page ) {
			$page->register_hooks();
		}

		add_action( 'admin_menu', array( $this, 'menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'assets' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( GLIXFORM_FILE ), array( $this, 'action_links' ) );
	}

	/**
	 * Admin menu.
	 */
	public function menu() {
		$cap = Plugin::capability();

		add_menu_page(
			__( 'Glixform', 'glixform' ),
			__( 'Glixform', 'glixform' ),
			$cap,
			'glixform',
			array( $this->pages['forms'], 'render' ),
			'dashicons-feedback',
			58
		);
		add_submenu_page( 'glixform', __( 'All Forms', 'glixform' ), __( 'All Forms', 'glixform' ), $cap, 'glixform', array( $this->pages['forms'], 'render' ) );
		add_submenu_page( 'glixform', __( 'Form Builder', 'glixform' ), __( 'Add New', 'glixform' ), $cap, 'glixform-builder', array( $this->pages['builder'], 'render' ) );
		add_submenu_page( 'glixform', __( 'Entries', 'glixform' ), __( 'Entries', 'glixform' ), $cap, 'glixform-entries', array( $this->pages['entries'], 'render' ) );
		add_submenu_page( 'glixform', __( 'Settings', 'glixform' ), __( 'Settings', 'glixform' ), $cap, 'glixform-settings', array( $this->pages['settings'], 'render' ) );
	}

	/**
	 * Load admin assets on Glixform screens only.
	 *
	 * @param string $hook_suffix Current screen.
	 */
	public function assets( $hook_suffix ) {
		if ( false === strpos( (string) $hook_suffix, 'glixform' ) ) {
			return;
		}
		wp_enqueue_style( 'glixform-admin', GLIXFORM_URL . 'assets/css/admin.css', array(), GLIXFORM_VERSION );

		if ( false !== strpos( (string) $hook_suffix, 'glixform-builder' ) ) {
			$this->pages['builder']->enqueue();
		}
	}

	/**
	 * "Settings" link on the Plugins screen.
	 *
	 * @param array $links Links.
	 * @return array
	 */
	public function action_links( $links ) {
		array_unshift(
			$links,
			sprintf( '<a href="%s">%s</a>', esc_url( admin_url( 'admin.php?page=glixform' ) ), esc_html__( 'Forms', 'glixform' ) )
		);
		return $links;
	}

	/**
	 * Stop unless the current user may manage Glixform.
	 */
	public static function check_permission() {
		if ( ! current_user_can( Plugin::capability() ) ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'glixform' ), 403 );
		}
	}

	/**
	 * Print an admin notice from the "glixform_notice" query arg.
	 *
	 * @param array $messages code => message.
	 */
	public static function notice( array $messages ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Display only.
		$code = isset( $_GET['glixform_notice'] ) ? sanitize_key( wp_unslash( $_GET['glixform_notice'] ) ) : '';
		if ( $code && isset( $messages[ $code ] ) ) {
			printf( '<div class="notice notice-success is-dismissible"><p>%s</p></div>', esc_html( $messages[ $code ] ) );
		}
	}
}

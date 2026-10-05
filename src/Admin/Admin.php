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
			'tools'    => new ToolsPage( $this->plugin ),
		);

		foreach ( $this->pages as $page ) {
			$page->register_hooks();
		}

		add_action( 'admin_menu', array( $this, 'menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'assets' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( GLIXFORM_FILE ), array( $this, 'action_links' ) );
		add_filter( 'admin_body_class', array( $this, 'body_class' ) );
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
		add_submenu_page( 'glixform', __( 'Tools', 'glixform' ), __( 'Tools', 'glixform' ), $cap, 'glixform-tools', array( $this->pages['tools'], 'render' ) );
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
		if ( false !== strpos( (string) $hook_suffix, 'glixform-builder' ) ) {
			$this->pages['builder']->enqueue();
			return;
		}
		wp_enqueue_style( 'glixform-admin', GLIXFORM_URL . 'assets/css/admin.css', array(), GLIXFORM_VERSION );
	}

	/**
	 * Body class on Glixform screens (for the modern admin styles).
	 *
	 * @param string $classes Classes.
	 * @return string
	 */
	public function body_class( $classes ) {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( $screen && false !== strpos( (string) $screen->id, 'glixform' ) ) {
			$classes .= ' glixform-screen';
		}
		return $classes;
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
			printf( '<div class="glixform-toast-notice" role="status"><span class="dashicons dashicons-yes-alt" aria-hidden="true"></span>%s</div>', esc_html( $messages[ $code ] ) );
		}
	}

	/**
	 * Page header with title and action buttons.
	 *
	 * @param string $title   Title.
	 * @param array  $actions [ url, label, icon, primary ].
	 * @param string $extra   Extra HTML (already escaped) shown under the title.
	 */
	public static function header( $title, array $actions = array(), $extra = '' ) {
		echo '<div class="glixform-header"><div class="glixform-header-main"><h1>' . esc_html( $title ) . '</h1>';
		echo $extra; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped by the caller.
		echo '</div><div class="glixform-header-actions">';
		foreach ( $actions as $action ) {
			printf(
				'<a class="button%s" href="%s">%s%s</a>',
				empty( $action['primary'] ) ? '' : ' button-primary',
				esc_url( $action['url'] ),
				empty( $action['icon'] ) ? '' : '<span class="dashicons ' . esc_attr( $action['icon'] ) . '" aria-hidden="true"></span>',
				esc_html( $action['label'] )
			);
		}
		echo '</div></div><hr class="wp-header-end">';
	}

	/**
	 * Friendly empty state.
	 *
	 * @param string $icon  Dashicon class.
	 * @param string $title Title.
	 * @param string $text  Text.
	 * @param string $url   Button URL.
	 * @param string $label Button label.
	 */
	public static function empty_state( $icon, $title, $text, $url, $label ) {
		printf(
			'<div class="glixform-card glixform-empty-state"><span class="dashicons %1$s" aria-hidden="true"></span><h2>%2$s</h2><p>%3$s</p><a class="button button-primary button-hero" href="%4$s">%5$s</a></div>',
			esc_attr( $icon ),
			esc_html( $title ),
			esc_html( $text ),
			esc_url( $url ),
			esc_html( $label )
		);
	}

	/**
	 * Confirm before following links marked .glixform-confirm.
	 */
	public static function confirm_script() {
		?>
		<script>
		document.addEventListener( 'click', function ( e ) {
			var link = e.target.closest( '.glixform-confirm' );
			if ( link && ! window.confirm( link.getAttribute( 'data-confirm' ) ) ) {
				e.preventDefault();
			}
		} );
		</script>
		<?php
	}
}

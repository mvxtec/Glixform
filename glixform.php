<?php
/**
 * Plugin Name:       Glixform
 * Plugin URI:        https://github.com/mvxtec/Glixform
 * Description:       Drag-and-drop form builder for WordPress: build forms, collect entries and get email notifications.
 * Version:           0.3.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            Glixform
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       glixform
 * Domain Path:       /languages
 *
 * @package Glixform
 */

defined( 'ABSPATH' ) || exit;

define( 'GLIXFORM_VERSION', '0.3.0' );
define( 'GLIXFORM_FILE', __FILE__ );
define( 'GLIXFORM_DIR', plugin_dir_path( __FILE__ ) );
define( 'GLIXFORM_URL', plugin_dir_url( __FILE__ ) );

if ( version_compare( PHP_VERSION, '7.4', '<' ) ) {
	add_action(
		'admin_notices',
		static function () {
			echo '<div class="notice notice-error"><p>' . esc_html__( 'Glixform requires PHP 7.4 or newer.', 'glixform' ) . '</p></div>';
		}
	);
	return;
}

if ( file_exists( GLIXFORM_DIR . 'vendor/autoload.php' ) ) {
	require_once GLIXFORM_DIR . 'vendor/autoload.php';
} else {
	// Fallback PSR-4 autoloader so the plugin also runs straight from a git checkout.
	spl_autoload_register(
		static function ( $class_name ) {
			$prefix = 'Glixform\\';
			if ( strpos( $class_name, $prefix ) !== 0 ) {
				return;
			}
			$file = GLIXFORM_DIR . 'src/' . str_replace( '\\', '/', substr( $class_name, strlen( $prefix ) ) ) . '.php';
			if ( is_readable( $file ) ) {
				require_once $file;
			}
		}
	);
}

register_activation_hook( __FILE__, array( 'Glixform\\Install', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'Glixform\\Install', 'deactivate' ) );

add_action( 'plugins_loaded', array( 'Glixform\\Plugin', 'instance' ) );

/**
 * Accessor for the main plugin instance.
 *
 * @return \Glixform\Plugin
 */
function glixform() {
	return \Glixform\Plugin::instance();
}

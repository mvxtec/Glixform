<?php
/**
 * Storage for uploaded files.
 *
 * @package Glixform
 */

namespace Glixform\Support;

defined( 'ABSPATH' ) || exit;

/**
 * Files live in wp-content/uploads/glixform/{form_id}/ under random names.
 * The folder blocks direct access on Apache (.htaccess) and directory listing
 * everywhere (index.php); admins download files through a capability-checked
 * handler. Random names keep files unguessable on servers that ignore .htaccess.
 */
class Uploads {

	/**
	 * Absolute base directory.
	 *
	 * @return string
	 */
	public static function base_dir() {
		$uploads = wp_upload_dir( null, false );
		return trailingslashit( $uploads['basedir'] ) . 'glixform';
	}

	/**
	 * Create the folder for a form and protect it.
	 *
	 * @param int $form_id Form ID.
	 * @return string|false Absolute directory, or false on failure.
	 */
	public static function form_dir( $form_id ) {
		$base = self::base_dir();
		$dir  = $base . '/' . absint( $form_id );
		if ( ! wp_mkdir_p( $dir ) ) {
			return false;
		}
		self::protect( $base );
		self::protect( $dir );
		return $dir;
	}

	/**
	 * Write .htaccess and index.php into a directory.
	 *
	 * @param string $dir Directory.
	 */
	public static function protect( $dir ) {
		// phpcs:disable WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		if ( ! file_exists( $dir . '/.htaccess' ) ) {
			file_put_contents( $dir . '/.htaccess', "# Glixform: deny direct access.\n<IfModule mod_authz_core.c>\n\tRequire all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\n\tDeny from all\n</IfModule>\n" );
		}
		if ( ! file_exists( $dir . '/index.php' ) ) {
			file_put_contents( $dir . '/index.php', "<?php\n// Silence is golden.\n" );
		}
		// phpcs:enable
	}

	/**
	 * Absolute path of a stored file, only if it is inside the uploads folder.
	 *
	 * @param string $relative Path relative to the base directory.
	 * @return string|false
	 */
	public static function path( $relative ) {
		$base = realpath( self::base_dir() );
		$path = realpath( self::base_dir() . '/' . ltrim( (string) $relative, '/' ) );
		if ( ! $base || ! $path || 0 !== strpos( $path, $base . DIRECTORY_SEPARATOR ) || ! is_file( $path ) ) {
			return false;
		}
		return $path;
	}

	/**
	 * Delete every file referenced by an entry.
	 *
	 * @param array $entry Entry with a "fields" snapshot.
	 */
	public static function delete_for_entry( array $entry ) {
		foreach ( (array) ( $entry['fields'] ?? array() ) as $item ) {
			if ( 'file' !== ( $item['type'] ?? '' ) || ! is_array( $item['value'] ?? null ) ) {
				continue;
			}
			foreach ( $item['value'] as $file ) {
				$path = is_array( $file ) ? self::path( $file['file'] ?? '' ) : false;
				if ( $path ) {
					wp_delete_file( $path );
				}
			}
		}
	}
}

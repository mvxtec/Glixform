<?php
/**
 * Removes Glixform data when the plugin is deleted, if the site owner opted in.
 *
 * @package Glixform
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

/**
 * Delete this site's Glixform data.
 */
function glixform_uninstall_site() {
	global $wpdb;

	$settings = get_option( 'glixform_settings', array() );
	if ( empty( $settings['delete_on_uninstall'] ) ) {
		return;
	}

	$form_ids = get_posts(
		array(
			'post_type'      => 'glixform_form',
			'post_status'    => 'any',
			'posts_per_page' => -1,
			'fields'         => 'ids',
		)
	);
	foreach ( $form_ids as $form_id ) {
		wp_delete_post( $form_id, true );
	}

	// phpcs:disable WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}glixform_entry_fields" );
	$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}glixform_entries" );
	// phpcs:enable

	// Uploaded files.
	$uploads = wp_upload_dir( null, false );
	$dir     = trailingslashit( $uploads['basedir'] ) . 'glixform';
	if ( is_dir( $dir ) ) {
		$files = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $dir, FilesystemIterator::SKIP_DOTS ), RecursiveIteratorIterator::CHILD_FIRST );
		foreach ( $files as $file ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir
			$file->isDir() ? rmdir( $file->getPathname() ) : wp_delete_file( $file->getPathname() );
		}
		rmdir( $dir ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir
	}

	wp_clear_scheduled_hook( 'glixform_daily' );
	delete_option( 'glixform_settings' );
	delete_option( 'glixform_db_version' );
	delete_option( 'glixform_version' );
}

if ( is_multisite() ) {
	foreach ( get_sites(
		array(
			'fields' => 'ids',
			'number' => 0,
		)
	) as $glixform_site_id ) {
		switch_to_blog( $glixform_site_id );
		glixform_uninstall_site();
		restore_current_blog();
	}
} else {
	glixform_uninstall_site();
}

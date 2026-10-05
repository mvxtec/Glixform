<?php
/**
 * Installation and database schema.
 *
 * @package Glixform
 */

namespace Glixform;

defined( 'ABSPATH' ) || exit;

/**
 * Creates and upgrades the custom tables.
 */
class Install {

	/**
	 * Bump when the schema changes; dbDelta() applies the difference.
	 */
	const DB_VERSION = '1';

	/**
	 * Activation hook. Network activation is handled lazily by maybe_upgrade()
	 * on each site's first request.
	 */
	public static function activate() {
		self::install();
	}

	/**
	 * Run the installer when the stored schema version is outdated.
	 */
	public static function maybe_upgrade() {
		if ( get_option( 'glixform_db_version' ) !== self::DB_VERSION ) {
			self::install();
		}
	}

	/**
	 * Create or update tables and store the versions.
	 */
	public static function install() {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset = $wpdb->get_charset_collate();
		$entries = $wpdb->prefix . 'glixform_entries';
		$fields  = $wpdb->prefix . 'glixform_entry_fields';

		// dbDelta() is picky: two spaces after PRIMARY KEY, one field per line.
		dbDelta(
			"CREATE TABLE {$entries} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				form_id bigint(20) unsigned NOT NULL,
				status varchar(20) NOT NULL DEFAULT 'unread',
				fields longtext NOT NULL,
				user_id bigint(20) unsigned NOT NULL DEFAULT 0,
				ip_address varchar(128) NOT NULL DEFAULT '',
				user_agent varchar(255) NOT NULL DEFAULT '',
				page_url varchar(2048) NOT NULL DEFAULT '',
				created_at datetime NOT NULL,
				PRIMARY KEY  (id),
				KEY form_id (form_id),
				KEY status (status),
				KEY created_at (created_at)
			) {$charset};"
		);

		dbDelta(
			"CREATE TABLE {$fields} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				entry_id bigint(20) unsigned NOT NULL,
				form_id bigint(20) unsigned NOT NULL,
				field_id int(10) unsigned NOT NULL,
				value longtext NOT NULL,
				PRIMARY KEY  (id),
				KEY entry_id (entry_id),
				KEY form_field (form_id,field_id)
			) {$charset};"
		);

		update_option( 'glixform_db_version', self::DB_VERSION );
		update_option( 'glixform_version', GLIXFORM_VERSION );

		if ( false === get_option( 'glixform_settings' ) ) {
			add_option( 'glixform_settings', Plugin::default_settings() );
		}
	}
}

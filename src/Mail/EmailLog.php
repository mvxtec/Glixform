<?php
/**
 * Log of sent and failed emails.
 *
 * @package Glixform
 */

namespace Glixform\Mail;

defined( 'ABSPATH' ) || exit;

/**
 * Records recipient, subject, status and error for every email the site sends.
 * Message bodies are not stored, to avoid keeping visitors' data twice.
 */
class EmailLog {

	/**
	 * Source label for the email being sent ("glixform", "test" or "" for others).
	 *
	 * @var string
	 */
	private static $source = '';

	/**
	 * Table name.
	 *
	 * @return string
	 */
	public static function table() {
		global $wpdb;
		return $wpdb->prefix . 'glixform_email_log';
	}

	/**
	 * Mark the next emails as coming from a source; call with '' afterwards.
	 *
	 * @param string $source Source.
	 */
	public static function set_source( $source ) {
		self::$source = (string) $source;
	}

	/**
	 * Hook into WordPress.
	 */
	public function register_hooks() {
		add_action( 'wp_mail_succeeded', array( $this, 'on_success' ) );
		add_action( 'wp_mail_failed', array( $this, 'on_failure' ) );
		add_action( 'glixform_daily', array( $this, 'purge' ) );
	}

	/**
	 * Successful send.
	 *
	 * @param array $mail to, subject, message, headers, attachments.
	 */
	public function on_success( $mail ) {
		$this->insert( (array) $mail, 'sent', '' );
	}

	/**
	 * Failed send.
	 *
	 * @param \WP_Error $error Error with the mail data attached.
	 */
	public function on_failure( $error ) {
		if ( ! is_wp_error( $error ) ) {
			return;
		}
		$this->insert( (array) $error->get_error_data(), 'failed', $error->get_error_message() );
	}

	/**
	 * Write a row when logging is enabled.
	 *
	 * @param array  $mail   Mail data.
	 * @param string $status sent|failed.
	 * @param string $error  Error message.
	 */
	private function insert( array $mail, $status, $error ) {
		if ( empty( SmtpSettings::get()['log_enabled'] ) ) {
			return;
		}
		global $wpdb;
		$to = $mail['to'] ?? '';
		$to = is_array( $to ) ? implode( ', ', $to ) : (string) $to;

		$wpdb->insert(
			self::table(),
			array(
				'created_at' => current_time( 'mysql', true ),
				'to_email'   => substr( sanitize_text_field( $to ), 0, 1000 ),
				'subject'    => mb_substr( sanitize_text_field( (string) ( $mail['subject'] ?? '' ) ), 0, 255 ),
				'status'     => $status,
				'error'      => mb_substr( sanitize_text_field( (string) $error ), 0, 1000 ),
				'source'     => self::$source,
				'mailer'     => SmtpSettings::uses_smtp() && '' === Conflicts::active_plugin() ? 'smtp' : 'default',
			),
			array( '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
		);
	}

	/**
	 * Query the log.
	 *
	 * @param array $args status, search, per_page, page.
	 * @return array [ rows, total ]
	 */
	public static function query( array $args ) {
		global $wpdb;
		$args   = wp_parse_args(
			$args,
			array(
				'status'   => '',
				'search'   => '',
				'per_page' => 20,
				'page'     => 1,
			)
		);
		$where  = array( '1=1' );
		$params = array();
		if ( in_array( $args['status'], array( 'sent', 'failed' ), true ) ) {
			$where[]  = 'status = %s';
			$params[] = $args['status'];
		}
		if ( '' !== $args['search'] ) {
			$like     = '%' . $wpdb->esc_like( $args['search'] ) . '%';
			$where[]  = '( to_email LIKE %s OR subject LIKE %s )';
			$params[] = $like;
			$params[] = $like;
		}
		$table = self::table();
		$sql   = implode( ' AND ', $where );
		$limit = max( 1, (int) $args['per_page'] );

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- $sql only contains placeholders.
		$total = (int) ( $params ? $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE {$sql}", $params ) ) : $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" ) );
		$rows  = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE {$sql} ORDER BY id DESC LIMIT %d OFFSET %d",
				array_merge( $params, array( $limit, max( 0, ( (int) $args['page'] - 1 ) * $limit ) ) )
			),
			ARRAY_A
		);
		// phpcs:enable

		return array( (array) $rows, $total );
	}

	/**
	 * Count rows by status.
	 *
	 * @return int[] all, sent, failed
	 */
	public static function counts() {
		global $wpdb;
		$table = self::table();
		$rows  = $wpdb->get_results( "SELECT status, COUNT(*) AS total FROM {$table} GROUP BY status", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$out   = array(
			'all'    => 0,
			'sent'   => 0,
			'failed' => 0,
		);
		foreach ( (array) $rows as $row ) {
			$out[ $row['status'] ] = (int) $row['total'];
			$out['all']           += (int) $row['total'];
		}
		return $out;
	}

	/**
	 * Delete every row.
	 */
	public static function clear() {
		global $wpdb;
		$wpdb->query( 'DELETE FROM ' . self::table() ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	/**
	 * Delete rows older than the retention period.
	 *
	 * @return int Rows deleted.
	 */
	public function purge() {
		global $wpdb;
		$days  = (int) SmtpSettings::get()['log_days'];
		$table = self::table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return (int) $wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE created_at < %s", gmdate( 'Y-m-d H:i:s', time() - max( 1, $days ) * DAY_IN_SECONDS ) ) );
	}
}

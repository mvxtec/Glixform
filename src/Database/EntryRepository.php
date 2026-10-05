<?php
/**
 * Entry storage in custom tables.
 *
 * @package Glixform
 */

namespace Glixform\Database;

defined( 'ABSPATH' ) || exit;

/**
 * Reads and writes {prefix}glixform_entries and {prefix}glixform_entry_fields.
 *
 * The entries table keeps a JSON snapshot of every field (label, type, value) so an
 * entry stays readable after the form changes. entry_fields holds one row per value
 * for searching and filtering.
 */
class EntryRepository {

	/**
	 * Entries table name.
	 *
	 * @return string
	 */
	public function table() {
		global $wpdb;
		return $wpdb->prefix . 'glixform_entries';
	}

	/**
	 * Entry fields table name.
	 *
	 * @return string
	 */
	public function fields_table() {
		global $wpdb;
		return $wpdb->prefix . 'glixform_entry_fields';
	}

	/**
	 * Insert an entry.
	 *
	 * @param int   $form_id Form ID.
	 * @param array $fields  Snapshot: [ [ 'id', 'type', 'label', 'value' ], ... ].
	 * @param array $meta    user_id, ip_address, user_agent, page_url.
	 * @return int Entry ID, or 0 on failure.
	 */
	public function insert( $form_id, array $fields, array $meta = array() ) {
		global $wpdb;

		$ok = $wpdb->insert(
			$this->table(),
			array(
				'form_id'    => absint( $form_id ),
				'status'     => 'unread',
				'fields'     => wp_json_encode( array_values( $fields ) ),
				'user_id'    => absint( $meta['user_id'] ?? 0 ),
				'ip_address' => substr( (string) ( $meta['ip_address'] ?? '' ), 0, 128 ),
				'user_agent' => substr( (string) ( $meta['user_agent'] ?? '' ), 0, 255 ),
				'page_url'   => substr( (string) ( $meta['page_url'] ?? '' ), 0, 2048 ),
				'created_at' => current_time( 'mysql', true ),
			),
			array( '%d', '%s', '%s', '%d', '%s', '%s', '%s', '%s' )
		);

		if ( ! $ok ) {
			return 0;
		}

		$entry_id = (int) $wpdb->insert_id;

		foreach ( $fields as $field ) {
			$value = is_array( $field['value'] ) ? implode( "\n", $field['value'] ) : (string) $field['value'];
			if ( '' === $value ) {
				continue;
			}
			$wpdb->insert(
				$this->fields_table(),
				array(
					'entry_id' => $entry_id,
					'form_id'  => absint( $form_id ),
					'field_id' => absint( $field['id'] ),
					'value'    => $value,
				),
				array( '%d', '%d', '%d', '%s' )
			);
		}

		return $entry_id;
	}

	/**
	 * Get one entry.
	 *
	 * @param int $entry_id Entry ID.
	 * @return array|null
	 */
	public function get( $entry_id ) {
		global $wpdb;
		$table = $this->table();
		$row   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $entry_id ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return $row ? $this->hydrate( $row ) : null;
	}

	/**
	 * Query entries for a form.
	 *
	 * @param array $args form_id, search, status, per_page, page, order ('ASC'|'DESC').
	 * @return array
	 */
	public function query( array $args ) {
		global $wpdb;

		$args = wp_parse_args(
			$args,
			array(
				'form_id'  => 0,
				'search'   => '',
				'status'   => '',
				'per_page' => 20,
				'page'     => 1,
				'order'    => 'DESC',
			)
		);

		list( $where, $params ) = $this->where( $args );

		$order  = 'ASC' === strtoupper( $args['order'] ) ? 'ASC' : 'DESC';
		$limit  = max( 1, (int) $args['per_page'] );
		$offset = max( 0, ( (int) $args['page'] - 1 ) * $limit );
		$table  = $this->table();

		$params[] = $limit;
		$params[] = $offset;

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- $where only contains placeholders.
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE {$where} ORDER BY id {$order} LIMIT %d OFFSET %d", $params ), ARRAY_A );

		return array_map( array( $this, 'hydrate' ), (array) $rows );
	}

	/**
	 * Count entries matching query args.
	 *
	 * @param array $args Same as query().
	 * @return int
	 */
	public function count( array $args ) {
		global $wpdb;
		list( $where, $params ) = $this->where(
			wp_parse_args(
				$args,
				array(
					'form_id' => 0,
					'search'  => '',
					'status'  => '',
				)
			)
		);
		$table                  = $this->table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- $where only contains placeholders.
		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE {$where}", $params ) );
	}

	/**
	 * Entry counts per form ID.
	 *
	 * @return int[] form_id => count
	 */
	public function counts_by_form() {
		global $wpdb;
		$table  = $this->table();
		$rows   = $wpdb->get_results( "SELECT form_id, COUNT(*) AS total FROM {$table} GROUP BY form_id", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$counts = array();
		foreach ( (array) $rows as $row ) {
			$counts[ (int) $row['form_id'] ] = (int) $row['total'];
		}
		return $counts;
	}

	/**
	 * Build the WHERE clause.
	 *
	 * @param array $args Query args.
	 * @return array [ string $where, array $params ]
	 */
	private function where( array $args ) {
		global $wpdb;

		$where  = array( 'form_id = %d' );
		$params = array( absint( $args['form_id'] ) );

		if ( in_array( $args['status'], array( 'read', 'unread' ), true ) ) {
			$where[]  = 'status = %s';
			$params[] = $args['status'];
		}

		if ( '' !== (string) $args['search'] ) {
			$fields   = $this->fields_table();
			$where[]  = "id IN ( SELECT entry_id FROM {$fields} WHERE form_id = %d AND value LIKE %s )";
			$params[] = absint( $args['form_id'] );
			$params[] = '%' . $wpdb->esc_like( (string) $args['search'] ) . '%';
		}

		return array( implode( ' AND ', $where ), $params );
	}

	/**
	 * Set entry status.
	 *
	 * @param int[]  $ids    Entry IDs.
	 * @param string $status "read" or "unread".
	 * @return void
	 */
	public function set_status( array $ids, $status ) {
		global $wpdb;
		if ( ! in_array( $status, array( 'read', 'unread' ), true ) ) {
			return;
		}
		foreach ( array_map( 'absint', $ids ) as $id ) {
			$wpdb->update( $this->table(), array( 'status' => $status ), array( 'id' => $id ), array( '%s' ), array( '%d' ) );
		}
	}

	/**
	 * Delete entries.
	 *
	 * @param int[] $ids Entry IDs.
	 * @return int Number deleted.
	 */
	public function delete( array $ids ) {
		global $wpdb;
		$deleted = 0;
		foreach ( array_filter( array_map( 'absint', $ids ) ) as $id ) {
			$wpdb->delete( $this->fields_table(), array( 'entry_id' => $id ), array( '%d' ) );
			$deleted += (int) $wpdb->delete( $this->table(), array( 'id' => $id ), array( '%d' ) );
		}
		return $deleted;
	}

	/**
	 * Delete all entries of a form.
	 *
	 * @param int $form_id Form ID.
	 * @return void
	 */
	public function delete_by_form( $form_id ) {
		global $wpdb;
		$wpdb->delete( $this->fields_table(), array( 'form_id' => absint( $form_id ) ), array( '%d' ) );
		$wpdb->delete( $this->table(), array( 'form_id' => absint( $form_id ) ), array( '%d' ) );
	}

	/**
	 * Decode a database row.
	 *
	 * @param array $row Row.
	 * @return array
	 */
	private function hydrate( array $row ) {
		$fields         = json_decode( (string) $row['fields'], true );
		$row['fields']  = is_array( $fields ) ? $fields : array();
		$row['id']      = (int) $row['id'];
		$row['form_id'] = (int) $row['form_id'];
		$row['user_id'] = (int) $row['user_id'];
		return $row;
	}
}

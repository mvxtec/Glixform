<?php
/**
 * Entries list table.
 *
 * @package Glixform
 */

namespace Glixform\Admin;

use Glixform\Database\EntryRepository;
use Glixform\Notifications\SmartTags;

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( '\WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * Lists entries of one form. The first three non-hidden fields become columns.
 */
class EntriesListTable extends \WP_List_Table {

	const PER_PAGE = 20;

	/**
	 * Entries.
	 *
	 * @var EntryRepository
	 */
	private $entries;

	/**
	 * Form.
	 *
	 * @var array
	 */
	private $form;

	/**
	 * Constructor.
	 *
	 * @param EntryRepository $entries Entries.
	 * @param array           $form    Form.
	 */
	public function __construct( EntryRepository $entries, array $form ) {
		parent::__construct(
			array(
				'singular' => 'entry',
				'plural'   => 'entries',
				'ajax'     => false,
			)
		);
		$this->entries = $entries;
		$this->form    = $form;
	}

	/**
	 * Fields shown as columns.
	 *
	 * @return array
	 */
	private function column_fields() {
		$fields = array_filter(
			$this->form['data']['fields'],
			static function ( $field ) {
				return 'hidden' !== $field['type'];
			}
		);
		return array_slice( array_values( $fields ), 0, 3 );
	}

	/**
	 * Columns.
	 *
	 * @return array
	 */
	public function get_columns() {
		$columns = array( 'cb' => '<input type="checkbox">' );
		foreach ( $this->column_fields() as $field ) {
			$columns[ 'field_' . $field['id'] ] = esc_html( $field['label'] );
		}
		$columns['created_at'] = __( 'Date', 'glixform' );
		return $columns;
	}

	/**
	 * Bulk actions.
	 *
	 * @return array
	 */
	protected function get_bulk_actions() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$status = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : '';
		if ( 'spam' === $status ) {
			return array(
				'not_spam' => __( 'Not spam', 'glixform' ),
				'delete'   => __( 'Delete permanently', 'glixform' ),
			);
		}
		return array(
			'mark_read'   => __( 'Mark as read', 'glixform' ),
			'mark_unread' => __( 'Mark as unread', 'glixform' ),
			'mark_spam'   => __( 'Mark as spam', 'glixform' ),
			'delete'      => __( 'Delete', 'glixform' ),
		);
	}

	/**
	 * Status filter links.
	 *
	 * @return array
	 */
	protected function get_views() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$current = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : '';
		$base    = admin_url( 'admin.php?page=glixform-entries&form_id=' . $this->form['id'] );
		$views   = array();

		foreach (
			array(
				''       => __( 'All', 'glixform' ),
				'unread' => __( 'Unread', 'glixform' ),
				'read'   => __( 'Read', 'glixform' ),
				'spam'   => __( 'Spam', 'glixform' ),
			) as $status => $label
		) {
			$count                              = $this->entries->count(
				array(
					'form_id' => $this->form['id'],
					'status'  => $status,
				)
			);
			$url                                = $status ? add_query_arg( 'status', $status, $base ) : $base;
			$views[ $status ? $status : 'all' ] = sprintf(
				'<a href="%s"%s>%s <span class="count">(%s)</span></a>',
				esc_url( $url ),
				$current === $status ? ' class="current" aria-current="page"' : '',
				esc_html( $label ),
				esc_html( number_format_i18n( $count ) )
			);
		}
		return $views;
	}

	/**
	 * Load items.
	 */
	public function prepare_items() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		$args = array(
			'form_id' => $this->form['id'],
			'search'  => isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '',
			'status'  => isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : '',
		);
		// phpcs:enable

		$total = $this->entries->count( $args );

		$this->_column_headers = array( $this->get_columns(), array(), array() );
		$this->items           = $this->entries->query(
			$args + array(
				'per_page' => self::PER_PAGE,
				'page'     => $this->get_pagenum(),
			)
		);

		$this->set_pagination_args(
			array(
				'total_items' => $total,
				'per_page'    => self::PER_PAGE,
			)
		);
	}

	/**
	 * Checkbox column.
	 *
	 * @param array $item Entry.
	 * @return string
	 */
	public function column_cb( $item ) {
		return sprintf( '<input type="checkbox" name="entry_ids[]" value="%d">', (int) $item['id'] );
	}

	/**
	 * Field columns. The first one links to the entry and carries row actions.
	 *
	 * @param array  $item        Entry.
	 * @param string $column_name Column.
	 * @return string
	 */
	public function column_default( $item, $column_name ) {
		if ( 'created_at' === $column_name ) {
			return esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $item['created_at'] . ' UTC' ) ) );
		}

		$field_id = (int) substr( $column_name, 6 );
		$value    = '';
		foreach ( $item['fields'] as $field ) {
			if ( (int) $field['id'] === $field_id ) {
				$value = SmartTags::item_text( $field );
				break;
			}
		}

		$text = '' === $value ? '—' : esc_html( wp_trim_words( $value, 12 ) );

		$columns = array_keys( $this->get_columns() );
		if ( $columns[1] !== $column_name ) {
			return $text;
		}

		$view = EntriesPage::entry_url( $item['form_id'], $item['id'] );
		$html = sprintf(
			'<a href="%s" class="%s">%s</a>',
			esc_url( $view ),
			'unread' === $item['status'] ? 'glixform-unread' : '',
			$text
		);
		if ( 'unread' === $item['status'] ) {
			$html = '<strong>' . $html . '</strong>';
		}

		return $html . $this->row_actions(
			array(
				'view'   => sprintf( '<a href="%s">%s</a>', esc_url( $view ), esc_html__( 'View', 'glixform' ) ),
				'delete' => sprintf(
					'<a href="%s" class="glixform-confirm" data-confirm="%s">%s</a>',
					esc_url( EntriesPage::delete_url( $item['form_id'], $item['id'] ) ),
					esc_attr__( 'Delete this entry and its files?', 'glixform' ),
					esc_html__( 'Delete', 'glixform' )
				),
			)
		);
	}

	/**
	 * Empty state.
	 */
	public function no_items() {
		esc_html_e( 'No entries found.', 'glixform' );
	}
}

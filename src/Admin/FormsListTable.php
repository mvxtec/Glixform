<?php
/**
 * Forms list table.
 *
 * @package Glixform
 */

namespace Glixform\Admin;

use Glixform\Frontend\Preview;

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( '\WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * Lists all forms with entry counts.
 */
class FormsListTable extends \WP_List_Table {

	/**
	 * Entry counts by form ID.
	 *
	 * @var int[]
	 */
	private $counts;

	/**
	 * Forms.
	 *
	 * @var \WP_Post[]
	 */
	private $forms;

	/**
	 * Constructor.
	 *
	 * @param \WP_Post[] $forms  Forms.
	 * @param int[]      $counts Entry counts.
	 */
	public function __construct( array $forms, array $counts ) {
		parent::__construct(
			array(
				'singular' => 'form',
				'plural'   => 'forms',
				'ajax'     => false,
			)
		);
		$this->forms  = $forms;
		$this->counts = $counts;
	}

	/**
	 * Columns.
	 *
	 * @return array
	 */
	public function get_columns() {
		return array(
			'title'     => __( 'Name', 'glixform' ),
			'shortcode' => __( 'Shortcode', 'glixform' ),
			'entries'   => __( 'Entries', 'glixform' ),
			'date'      => __( 'Created', 'glixform' ),
		);
	}

	/**
	 * Load items.
	 */
	public function prepare_items() {
		$this->_column_headers = array( $this->get_columns(), array(), array() );
		$this->items           = $this->forms;
	}

	/**
	 * Title column with row actions.
	 *
	 * @param \WP_Post $item Form.
	 * @return string
	 */
	public function column_title( $item ) {
		$edit = admin_url( 'admin.php?page=glixform-builder&form_id=' . $item->ID );

		$actions = array(
			'edit'      => sprintf( '<a href="%s">%s</a>', esc_url( $edit ), esc_html__( 'Edit', 'glixform' ) ),
			'entries'   => sprintf( '<a href="%s">%s</a>', esc_url( admin_url( 'admin.php?page=glixform-entries&form_id=' . $item->ID ) ), esc_html__( 'Entries', 'glixform' ) ),
			'view'      => sprintf( '<a href="%s" target="_blank" rel="noopener">%s</a>', esc_url( Preview::url( $item->ID ) ), esc_html__( 'Preview', 'glixform' ) ),
			'duplicate' => sprintf( '<a href="%s">%s</a>', esc_url( FormsPage::action_url( 'duplicate', $item->ID ) ), esc_html__( 'Duplicate', 'glixform' ) ),
			'delete'    => sprintf(
				'<a href="%s" class="glixform-confirm" data-confirm="%s">%s</a>',
				esc_url( FormsPage::action_url( 'delete', $item->ID ) ),
				esc_attr__( 'Delete this form and all of its entries? This cannot be undone.', 'glixform' ),
				esc_html__( 'Delete', 'glixform' )
			),
		);

		return sprintf( '<strong><a class="row-title" href="%s">%s</a></strong>', esc_url( $edit ), esc_html( $item->post_title ) ) . $this->row_actions( $actions );
	}

	/**
	 * Shortcode column.
	 *
	 * @param \WP_Post $item Form.
	 * @return string
	 */
	public function column_shortcode( $item ) {
		return sprintf( '<code class="glixform-shortcode">[glixform id="%d"]</code>', (int) $item->ID );
	}

	/**
	 * Entries column.
	 *
	 * @param \WP_Post $item Form.
	 * @return string
	 */
	public function column_entries( $item ) {
		$count = $this->counts[ $item->ID ] ?? 0;
		return sprintf( '<a class="glixform-count-pill" href="%s">%s</a>', esc_url( admin_url( 'admin.php?page=glixform-entries&form_id=' . $item->ID ) ), esc_html( number_format_i18n( $count ) ) );
	}

	/**
	 * Date column.
	 *
	 * @param \WP_Post $item Form.
	 * @return string
	 */
	public function column_date( $item ) {
		return esc_html( get_the_date( '', $item ) );
	}

	/**
	 * No bulk actions or pagination, so no table navigation bars.
	 *
	 * @param string $which Top or bottom.
	 */
	protected function display_tablenav( $which ) {}

	/**
	 * Empty state.
	 */
	public function no_items() {
		esc_html_e( 'No forms yet.', 'glixform' );
	}
}

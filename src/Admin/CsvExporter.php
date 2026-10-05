<?php
/**
 * CSV export of entries.
 *
 * @package Glixform
 */

namespace Glixform\Admin;

use Glixform\Database\EntryRepository;
use Glixform\Notifications\SmartTags;

defined( 'ABSPATH' ) || exit;

/**
 * Streams all entries of a form as CSV.
 */
class CsvExporter {

	/**
	 * Entries.
	 *
	 * @var EntryRepository
	 */
	private $entries;

	/**
	 * Constructor.
	 *
	 * @param EntryRepository $entries Entries.
	 */
	public function __construct( EntryRepository $entries ) {
		$this->entries = $entries;
	}

	/**
	 * Neutralize spreadsheet formulas (CSV injection) by prefixing risky cells with a quote.
	 *
	 * @param string $value Cell value.
	 * @return string
	 */
	public static function escape_cell( $value ) {
		$value = (string) $value;
		if ( '' !== $value && in_array( $value[0], array( '=', '+', '-', '@', "\t", "\r" ), true ) ) {
			return "'" . $value;
		}
		return $value;
	}

	/**
	 * Header row and field IDs for a form. Fields that exist only in old entries are appended.
	 *
	 * @param array $form    Form.
	 * @param array $entries Entries.
	 * @return array [ field_id => label ]
	 */
	public function columns( array $form, array $entries = array() ) {
		$columns = array();
		foreach ( $form['data']['fields'] as $field ) {
			$columns[ (int) $field['id'] ] = $field['label'];
		}
		foreach ( $entries as $entry ) {
			foreach ( $entry['fields'] as $field ) {
				if ( ! isset( $columns[ (int) $field['id'] ] ) ) {
					$columns[ (int) $field['id'] ] = $field['label'];
				}
			}
		}
		return $columns;
	}

	/**
	 * Rows for a set of entries.
	 *
	 * @param array $columns field_id => label.
	 * @param array $entries Entries.
	 * @return array[]
	 */
	public function rows( array $columns, array $entries ) {
		$rows = array();
		foreach ( $entries as $entry ) {
			$values = array();
			foreach ( $entry['fields'] as $field ) {
				$values[ (int) $field['id'] ] = SmartTags::value_to_string( $field['value'] );
			}
			$row = array();
			foreach ( array_keys( $columns ) as $field_id ) {
				$row[] = self::escape_cell( $values[ $field_id ] ?? '' );
			}
			$row[]  = $entry['created_at'];
			$row[]  = (string) $entry['id'];
			$row[]  = self::escape_cell( $entry['ip_address'] );
			$rows[] = $row;
		}
		return $rows;
	}

	/**
	 * Send the CSV download and exit.
	 *
	 * @param array $form Form.
	 */
	public function download( array $form ) {
		$entries = array();
		$page    = 1;
		do {
			$batch   = $this->entries->query(
				array(
					'form_id'  => $form['id'],
					'per_page' => 500,
					'page'     => $page++,
					'order'    => 'ASC',
				)
			);
			$entries = array_merge( $entries, $batch );
			$fetched = count( $batch );
		} while ( 500 === $fetched );

		$columns  = $this->columns( $form, $entries );
		$filename = sanitize_file_name( sanitize_title( $form['title'] ) . '-entries-' . gmdate( 'Y-m-d' ) . '.csv' );

		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );

		$out = fopen( 'php://output', 'w' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		fwrite( $out, "\xEF\xBB\xBF" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite -- UTF-8 BOM so Excel detects the encoding.

		$header = array_map( array( __CLASS__, 'escape_cell' ), array_values( $columns ) );
		array_push( $header, __( 'Date (UTC)', 'glixform' ), __( 'Entry ID', 'glixform' ), __( 'IP Address', 'glixform' ) );
		fputcsv( $out, $header, ',', '"', '' );

		foreach ( $this->rows( $columns, $entries ) as $row ) {
			fputcsv( $out, $row, ',', '"', '' );
		}
		fclose( $out ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		exit;
	}
}

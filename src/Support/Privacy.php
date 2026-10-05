<?php
/**
 * Privacy tools: personal data export/erase and entry retention.
 *
 * @package Glixform
 */

namespace Glixform\Support;

use Glixform\Database\EntryRepository;
use Glixform\Notifications\SmartTags;
use Glixform\Plugin;

defined( 'ABSPATH' ) || exit;

/**
 * Hooks into WordPress's Tools → Export/Erase Personal Data, finding entries
 * where any field equals the requester's email address, and deletes entries
 * older than the configured retention period once a day.
 */
class Privacy {

	const PAGE_SIZE = 50;

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
	 * Hook into WordPress.
	 */
	public function register_hooks() {
		add_filter( 'wp_privacy_personal_data_exporters', array( $this, 'register_exporter' ) );
		add_filter( 'wp_privacy_personal_data_erasers', array( $this, 'register_eraser' ) );
		add_action( 'admin_init', array( $this, 'privacy_policy_text' ) );
		add_action( 'init', array( $this, 'schedule' ) );
		add_action( 'glixform_daily', array( $this, 'purge_old_entries' ) );
	}

	/**
	 * Register the exporter.
	 *
	 * @param array $exporters Exporters.
	 * @return array
	 */
	public function register_exporter( $exporters ) {
		$exporters['glixform'] = array(
			'exporter_friendly_name' => __( 'Glixform entries', 'glixform' ),
			'callback'               => array( $this, 'export' ),
		);
		return $exporters;
	}

	/**
	 * Register the eraser.
	 *
	 * @param array $erasers Erasers.
	 * @return array
	 */
	public function register_eraser( $erasers ) {
		$erasers['glixform'] = array(
			'eraser_friendly_name' => __( 'Glixform entries', 'glixform' ),
			'callback'             => array( $this, 'erase' ),
		);
		return $erasers;
	}

	/**
	 * Export entries containing the email address.
	 *
	 * @param string $email Email address.
	 * @param int    $page  Page.
	 * @return array
	 */
	public function export( $email, $page = 1 ) {
		$items   = array();
		$entries = $this->entries->find_by_value( (string) $email, self::PAGE_SIZE, (int) $page );
		foreach ( $entries as $entry ) {
			$data = array(
				array(
					'name'  => __( 'Submitted', 'glixform' ),
					'value' => $entry['created_at'] . ' UTC',
				),
			);
			foreach ( $entry['fields'] as $field ) {
				$data[] = array(
					'name'  => $field['label'],
					'value' => SmartTags::item_text( $field ),
				);
			}
			if ( $entry['ip_address'] ) {
				$data[] = array(
					'name'  => __( 'IP address', 'glixform' ),
					'value' => $entry['ip_address'],
				);
			}
			$items[] = array(
				'group_id'    => 'glixform-entries',
				'group_label' => __( 'Form entries', 'glixform' ),
				'item_id'     => 'glixform-entry-' . $entry['id'],
				'data'        => $data,
			);
		}
		return array(
			'data' => $items,
			'done' => count( $entries ) < self::PAGE_SIZE,
		);
	}

	/**
	 * Delete entries containing the email address. Always page 1: deleted rows disappear.
	 *
	 * @param string $email Email address.
	 * @param int    $page  Page (unused).
	 * @return array
	 */
	public function erase( $email, $page = 1 ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- Signature required by WordPress.
		$entries = $this->entries->find_by_value( (string) $email, self::PAGE_SIZE, 1 );
		$removed = $this->entries->delete( wp_list_pluck( $entries, 'id' ) );
		return array(
			'items_removed'  => $removed > 0,
			'items_retained' => false,
			'messages'       => array(),
			'done'           => count( $entries ) < self::PAGE_SIZE,
		);
	}

	/**
	 * Suggested text for the site's privacy policy.
	 */
	public function privacy_policy_text() {
		if ( ! function_exists( 'wp_add_privacy_policy_content' ) ) {
			return;
		}
		wp_add_privacy_policy_content(
			'Glixform',
			'<p>' . esc_html__( 'When you submit a form on this site, the information you enter is stored so we can respond. Depending on the form settings this may include your IP address and browser details. Submissions may be emailed to site staff.', 'glixform' ) . '</p>'
		);
	}

	/**
	 * Schedule the daily job.
	 */
	public function schedule() {
		if ( ! wp_next_scheduled( 'glixform_daily' ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'glixform_daily' );
		}
	}

	/**
	 * Delete entries older than the retention period (0 = keep forever).
	 *
	 * @return int Number deleted.
	 */
	public function purge_old_entries() {
		$days = absint( Plugin::setting( 'retention_days', 0 ) );
		if ( ! $days ) {
			return 0;
		}
		$before  = gmdate( 'Y-m-d H:i:s', time() - $days * DAY_IN_SECONDS );
		$deleted = 0;
		do {
			$ids      = $this->entries->ids_older_than( $before, 200 );
			$batch    = count( $ids );
			$deleted += $this->entries->delete( $ids );
		} while ( 200 === $batch );
		return $deleted;
	}
}

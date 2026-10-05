<?php
/**
 * Anti-spam token, smart tags, mail helpers and CSV escaping.
 *
 * @package Glixform
 */

namespace Glixform\Tests\Unit;

use Glixform\Admin\CsvExporter;
use Glixform\Notifications\Mailer;
use Glixform\Notifications\SmartTags;
use Glixform\Process\AntiSpam;
use PHPUnit\Framework\TestCase;

class ProcessingTest extends TestCase {

	public function test_token_requires_minimum_age_and_matching_form() {
		$this->assertTrue( AntiSpam::verify_token( 5, AntiSpam::token( 5, time() - 10 ) ) );
		$this->assertFalse( AntiSpam::verify_token( 5, AntiSpam::token( 5 ) ), 'too fast' );
		$this->assertFalse( AntiSpam::verify_token( 6, AntiSpam::token( 5, time() - 10 ) ), 'other form' );
		$this->assertFalse( AntiSpam::verify_token( 5, ( time() - 10 ) . ':forged' ) );
		$this->assertFalse( AntiSpam::verify_token( 5, '' ) );
	}

	public function test_honeypot() {
		$this->assertFalse( AntiSpam::honeypot_triggered( array() ) );
		$this->assertFalse( AntiSpam::honeypot_triggered( array( AntiSpam::HONEYPOT_NAME => '' ) ) );
		$this->assertTrue( AntiSpam::honeypot_triggered( array( AntiSpam::HONEYPOT_NAME => 'x' ) ) );
	}

	public function test_smart_tags_escape_values_in_html_mode() {
		$context = array(
			'form'   => array( 'id' => 3, 'title' => 'Contact' ),
			'fields' => array(
				array( 'id' => 1, 'type' => 'text', 'label' => 'Name', 'value' => '<b>Jane</b>' ),
				array( 'id' => 2, 'type' => 'checkbox', 'label' => 'Pick', 'value' => array( 'A', 'B' ) ),
			),
		);
		$tags = new SmartTags();
		$this->assertSame( 'Hi &lt;b&gt;Jane&lt;/b&gt; (A, B) via Contact', $tags->process( 'Hi {field_id="1"} ({field_id="2"}) via {form_name}', $context ) );
		$this->assertSame( 'Hi <b>Jane</b>', $tags->process( 'Hi {field_id="1"}', $context, 'text' ) );
		$this->assertSame( 'Missing: ', $tags->process( 'Missing: {field_id="99"}', $context ) );
		$this->assertStringContainsString( '<table', $tags->process( '{all_fields}', $context ) );
	}

	public function test_mailer_filters_addresses_and_strips_newlines() {
		$mailer = new Mailer( new SmartTags() );
		$this->assertSame( array( 'a@example.com', 'b@example.com' ), $mailer->email_list( 'a@example.com, nope, b@example.com,a@example.com' ) );
		$this->assertSame( 'Subject Bcc: x', $mailer->single_line( "Subject\r\nBcc: x" ) );
	}

	public function test_csv_cells_cannot_start_formulas() {
		$this->assertSame( "'=1+2", CsvExporter::escape_cell( '=1+2' ) );
		$this->assertSame( "'@SUM(A1)", CsvExporter::escape_cell( '@SUM(A1)' ) );
		$this->assertSame( "'-2+3", CsvExporter::escape_cell( '-2+3' ) );
		$this->assertSame( 'Plain', CsvExporter::escape_cell( 'Plain' ) );
		$this->assertSame( '', CsvExporter::escape_cell( '' ) );
	}
}

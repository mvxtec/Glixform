<?php
/**
 * Form definition sanitizing.
 *
 * @package Glixform
 */

namespace Glixform\Tests\Unit;

use Glixform\Fields\FieldRegistry;
use Glixform\Forms\FormRepository;
use PHPUnit\Framework\TestCase;

class FormRepositoryTest extends TestCase {

	private function repo() {
		return new FormRepository( new FieldRegistry() );
	}

	public function test_drops_unknown_types_and_fixes_duplicate_ids() {
		$data = $this->repo()->sanitize(
			array(
				'fields'        => array(
					array( 'id' => 1, 'type' => 'text', 'label' => 'A' ),
					array( 'id' => 1, 'type' => 'email', 'label' => 'B' ),
					array( 'id' => 2, 'type' => 'php-exec', 'label' => 'C' ),
					'not-an-array',
				),
				'next_field_id' => 2,
			)
		);
		$this->assertCount( 2, $data['fields'] );
		$this->assertSame( array( 1, 2 ), array_column( $data['fields'], 'id' ) );
		$this->assertSame( 3, $data['next_field_id'] );
	}

	public function test_settings_are_validated() {
		$data = $this->repo()->sanitize(
			array(
				'settings' => array(
					'submit_text'  => '',
					'confirmation' => array( 'type' => 'evil', 'url' => 'javascript:alert(1)' ),
					'notification' => array( 'enabled' => '', 'subject' => "Hi\nBcc: x" ),
				),
			)
		);
		$this->assertSame( 'Submit', $data['settings']['submit_text'] );
		$this->assertSame( 'message', $data['settings']['confirmation']['type'] );
		$this->assertSame( '', $data['settings']['confirmation']['url'] );
		$this->assertFalse( $data['settings']['notification']['enabled'] );
		$this->assertStringNotContainsString( "\n", $data['settings']['notification']['subject'] );
		$this->assertTrue( $data['settings']['store_entries'] );
	}

	public function test_decode_fills_defaults_for_bad_json() {
		$data = $this->repo()->decode( 'not json' );
		$this->assertSame( array(), $data['fields'] );
		$this->assertSame( 1, $data['next_field_id'] );
		$this->assertSame( 'message', $data['settings']['confirmation']['type'] );
	}
}

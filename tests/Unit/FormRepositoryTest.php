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
				'fields'   => array( array( 'id' => 1, 'type' => 'email', 'label' => 'Email' ) ),
				'settings' => array(
					'submit_text'   => '',
					'progress'      => 'fancy',
					'confirmations' => array( array( 'id' => 1, 'type' => 'evil', 'url' => 'javascript:alert(1)' ) ),
					'notifications' => array(
						array(
							'id'          => 1,
							'enabled'     => '',
							'subject'     => "Hi\nBcc: x",
							'conditional' => array(
								'enabled' => true,
								'rules'   => array( array( 'field' => 99, 'operator' => 'is', 'value' => 'x' ) ),
							),
						),
						array( 'id' => 1, 'name' => 'Duplicate id' ),
					),
				),
			)
		);
		$settings = $data['settings'];
		$this->assertSame( 'Submit', $settings['submit_text'] );
		$this->assertSame( 'bar', $settings['progress'] );
		$this->assertSame( 'message', $settings['confirmations'][0]['type'] );
		$this->assertSame( '', $settings['confirmations'][0]['url'] );
		$this->assertFalse( $settings['notifications'][0]['enabled'] );
		$this->assertStringNotContainsString( "\n", $settings['notifications'][0]['subject'] );
		$this->assertFalse( $settings['notifications'][0]['conditional']['enabled'], 'Rule pointing at a missing field is dropped' );
		$this->assertSame( array( 1, 2 ), array_column( $settings['notifications'], 'id' ) );
		$this->assertTrue( $settings['store_entries'] );
	}

	public function test_upgrades_version_0_1_forms() {
		$old  = json_encode(
			array(
				'fields'   => array( array( 'id' => 1, 'type' => 'text', 'label' => 'Name' ) ),
				'settings' => array(
					'notification' => array( 'enabled' => true, 'to' => 'boss@example.com', 'subject' => 'Hi' ),
					'confirmation' => array( 'type' => 'message', 'message' => 'Thanks!' ),
				),
			)
		);
		$data = $this->repo()->decode( $old );
		$this->assertCount( 1, $data['settings']['notifications'] );
		$this->assertSame( 'boss@example.com', $data['settings']['notifications'][0]['to'] );
		$this->assertSame( 'Thanks!', $data['settings']['confirmations'][0]['message'] );
		$this->assertArrayNotHasKey( 'notification', $data['settings'] );
		$this->assertFalse( $data['fields'][0]['conditional']['enabled'] );
	}

	public function test_conditions_only_reference_input_fields() {
		$data = $this->repo()->sanitize(
			array(
				'fields' => array(
					array( 'id' => 1, 'type' => 'divider', 'label' => 'Section' ),
					array( 'id' => 2, 'type' => 'select', 'label' => 'Topic', 'choices' => array( array( 'label' => 'A' ) ) ),
					array(
						'id'          => 3,
						'type'        => 'text',
						'label'       => 'Details',
						'conditional' => array(
							'enabled' => true,
							'action'  => 'show',
							'rules'   => array(
								array( 'field' => 1, 'operator' => 'is', 'value' => 'x' ),
								array( 'field' => 2, 'operator' => 'is', 'value' => 'A' ),
								array( 'field' => 3, 'operator' => 'is', 'value' => 'self' ),
								array( 'field' => 2, 'operator' => 'drop_table', 'value' => 'x' ),
							),
						),
					),
				),
			)
		);
		$rules = $data['fields'][2]['conditional']['rules'];
		$this->assertCount( 1, $rules );
		$this->assertSame( 2, $rules[0]['field'] );
	}

	public function test_decode_fills_defaults_for_bad_json() {
		$data = $this->repo()->decode( 'not json' );
		$this->assertSame( array(), $data['fields'] );
		$this->assertSame( 1, $data['next_field_id'] );
		$this->assertSame( 'message', $data['settings']['confirmations'][0]['type'] );
		$this->assertCount( 1, $data['settings']['notifications'] );
	}
}

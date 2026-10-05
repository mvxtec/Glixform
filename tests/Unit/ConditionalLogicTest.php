<?php
/**
 * Conditional logic rules and visibility.
 *
 * @package Glixform
 */

namespace Glixform\Tests\Unit;

use Glixform\Forms\ConditionalLogic;
use PHPUnit\Framework\TestCase;

class ConditionalLogicTest extends TestCase {

	private function rule( $operator, $value = '' ) {
		return array( 'field' => 1, 'operator' => $operator, 'value' => $value );
	}

	public function test_string_operators_are_case_insensitive_and_trimmed() {
		$this->assertTrue( ConditionalLogic::rule_matches( $this->rule( 'is', 'sales' ), ' Sales ' ) );
		$this->assertTrue( ConditionalLogic::rule_matches( $this->rule( 'is_not', 'sales' ), 'Support' ) );
		$this->assertTrue( ConditionalLogic::rule_matches( $this->rule( 'contains', 'EXAMPLE' ), 'a@example.com' ) );
		$this->assertTrue( ConditionalLogic::rule_matches( $this->rule( 'not_contains', 'spam' ), 'hello' ) );
		$this->assertTrue( ConditionalLogic::rule_matches( $this->rule( 'starts_with', 'he' ), 'Hello' ) );
		$this->assertTrue( ConditionalLogic::rule_matches( $this->rule( 'ends_with', '.com' ), 'a@b.COM' ) );
		$this->assertFalse( ConditionalLogic::rule_matches( $this->rule( 'contains', '' ), 'anything' ), 'Empty "contains" never matches' );
		$this->assertTrue( ConditionalLogic::rule_matches( $this->rule( 'empty' ), '   ' ) );
		$this->assertTrue( ConditionalLogic::rule_matches( $this->rule( 'not_empty' ), 'x' ) );
	}

	public function test_numeric_and_date_comparisons() {
		$this->assertTrue( ConditionalLogic::rule_matches( $this->rule( 'less_than', '4' ), '2' ) );
		$this->assertTrue( ConditionalLogic::rule_matches( $this->rule( 'greater_than', '9' ), '10' ), 'Numbers compare numerically, not as text' );
		$this->assertFalse( ConditionalLogic::rule_matches( $this->rule( 'greater_than', '4' ), '' ), 'Empty never compares' );
		$this->assertTrue( ConditionalLogic::rule_matches( $this->rule( 'greater_than', '2026-01-31' ), '2026-02-01' ) );
		$this->assertTrue( ConditionalLogic::rule_matches( $this->rule( 'less_than', '12:00' ), '09:30' ) );
	}

	public function test_list_values() {
		$picked = array( 'Design', 'Marketing' );
		$this->assertTrue( ConditionalLogic::rule_matches( $this->rule( 'is', 'design' ), $picked ) );
		$this->assertFalse( ConditionalLogic::rule_matches( $this->rule( 'is', 'Other' ), $picked ) );
		$this->assertTrue( ConditionalLogic::rule_matches( $this->rule( 'is_not', 'Other' ), $picked ) );
		$this->assertTrue( ConditionalLogic::rule_matches( $this->rule( 'contains', 'mark' ), $picked ) );
		$this->assertFalse( ConditionalLogic::rule_matches( $this->rule( 'not_contains', 'mark' ), $picked ) );
		$this->assertTrue( ConditionalLogic::rule_matches( $this->rule( 'empty' ), array() ) );
		$this->assertTrue( ConditionalLogic::rule_matches( $this->rule( 'not_empty' ), $picked ) );
	}

	public function test_all_any_and_hide() {
		$values = array( 1 => 'Sales', 2 => '5' );
		$all    = array(
			'enabled' => true,
			'action'  => 'show',
			'logic'   => 'all',
			'rules'   => array(
				array( 'field' => 1, 'operator' => 'is', 'value' => 'Sales' ),
				array( 'field' => 2, 'operator' => 'less_than', 'value' => '3' ),
			),
		);
		$this->assertFalse( ConditionalLogic::evaluate( $all, $values ) );
		$this->assertTrue( ConditionalLogic::evaluate( array( 'logic' => 'any' ) + $all, $values ) );
		$this->assertTrue( ConditionalLogic::evaluate( array( 'action' => 'hide' ) + $all, $values ) );
		$this->assertTrue( ConditionalLogic::evaluate( array( 'enabled' => false ) + $all, $values ), 'Disabled logic always shows' );
	}

	public function test_hidden_fields_count_as_empty_for_later_fields() {
		$fields = array(
			array( 'id' => 1, 'conditional' => array( 'enabled' => false ) ),
			array(
				'id'          => 2,
				'conditional' => array(
					'enabled' => true,
					'action'  => 'show',
					'logic'   => 'all',
					'rules'   => array( array( 'field' => 1, 'operator' => 'is', 'value' => 'yes' ) ),
				),
			),
			array(
				'id'          => 3,
				'conditional' => array(
					'enabled' => true,
					'action'  => 'show',
					'logic'   => 'all',
					'rules'   => array( array( 'field' => 2, 'operator' => 'not_empty', 'value' => '' ) ),
				),
			),
		);
		$values  = array( 1 => 'no', 2 => 'typed earlier', 3 => '' );
		$visible = ConditionalLogic::visibility( $fields, $values );
		$this->assertSame( array( 1 => true, 2 => false, 3 => false ), $visible );
		$this->assertSame( '', $values[2] );
	}

	public function test_sanitize() {
		$clean = ConditionalLogic::sanitize(
			array(
				'enabled' => 1,
				'action'  => 'explode',
				'logic'   => 'any',
				'rules'   => array_fill( 0, 30, array( 'field' => 2, 'operator' => 'is', 'value' => '<b>x</b>' ) ),
			),
			array( 2 ),
			1
		);
		$this->assertTrue( $clean['enabled'] );
		$this->assertSame( 'show', $clean['action'] );
		$this->assertSame( 'any', $clean['logic'] );
		$this->assertCount( ConditionalLogic::MAX_RULES, $clean['rules'] );
		$this->assertSame( 'x', $clean['rules'][0]['value'] );
		$this->assertFalse( ConditionalLogic::sanitize( array( 'enabled' => true, 'rules' => array() ), array( 2 ) )['enabled'] );
	}
}

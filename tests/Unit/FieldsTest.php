<?php
/**
 * Field validation and sanitization.
 *
 * @package Glixform
 */

namespace Glixform\Tests\Unit;

use Glixform\Fields\Checkbox;
use Glixform\Fields\Email;
use Glixform\Fields\Number;
use Glixform\Fields\Select;
use Glixform\Fields\Text;
use PHPUnit\Framework\TestCase;

class FieldsTest extends TestCase {

	public function test_required_text_rejects_whitespace() {
		$field = array( 'id' => 1, 'label' => 'Name', 'required' => true );
		$this->assertNotSame( '', ( new Text() )->validate( $field, '   ' ) );
		$this->assertSame( '', ( new Text() )->validate( $field, 'Jane' ) );
	}

	public function test_text_max_length_counts_characters() {
		$field = array( 'id' => 1, 'label' => 'Code', 'max_length' => 3 );
		$this->assertSame( '', ( new Text() )->validate( $field, 'äöü' ) );
		$this->assertNotSame( '', ( new Text() )->validate( $field, 'abcd' ) );
	}

	public function test_text_sanitize_rejects_arrays_and_strips_tags() {
		$field = array( 'id' => 1 );
		$this->assertSame( '', ( new Text() )->sanitize_value( $field, array( 'x' ) ) );
		$this->assertSame( 'Jane Doe', ( new Text() )->sanitize_value( $field, 'Jane <b>Doe</b>' ) );
	}

	public function test_email_validation() {
		$field = array( 'id' => 2, 'label' => 'Email', 'required' => false );
		$email = new Email();
		$this->assertSame( '', $email->validate( $field, '' ) );
		$this->assertSame( '', $email->validate( $field, 'a@example.com' ) );
		$this->assertNotSame( '', $email->validate( $field, 'not-an-email' ) );
		$this->assertNotSame( '', $email->validate( $field, "a@b.com\nBcc: x@y.com" ) );
	}

	public function test_number_range() {
		$field  = array( 'id' => 3, 'label' => 'N', 'min' => '1', 'max' => '10' );
		$number = new Number();
		$this->assertSame( '', $number->validate( $field, '5' ) );
		$this->assertSame( '', $number->validate( $field, '' ) );
		$this->assertNotSame( '', $number->validate( $field, '0' ) );
		$this->assertNotSame( '', $number->validate( $field, '11' ) );
		$this->assertNotSame( '', $number->validate( $field, 'abc' ) );
	}

	public function test_choice_fields_only_accept_configured_choices() {
		$field = array(
			'id'      => 4,
			'label'   => 'Topic',
			'choices' => array( array( 'label' => 'Sales' ), array( 'label' => 'Support' ) ),
		);
		$select = new Select();
		$this->assertSame( '', $select->validate( $field, 'Sales' ) );
		$this->assertNotSame( '', $select->validate( $field, 'Hacked' ) );

		$checkbox = new Checkbox();
		$this->assertSame( '', $checkbox->validate( $field, array( 'Sales', 'Support' ) ) );
		$this->assertNotSame( '', $checkbox->validate( $field, array( 'Sales', 'Nope' ) ) );
	}

	public function test_checkbox_sanitize_drops_nested_arrays() {
		$value = ( new Checkbox() )->sanitize_value( array( 'id' => 5 ), array( 'A', array( 'B' ), 'C' ) );
		$this->assertSame( array( 'A', 'C' ), $value );
	}

	public function test_choice_defaults() {
		$field = array(
			'choices' => array(
				array( 'label' => 'A', 'default' => false ),
				array( 'label' => 'B', 'default' => true ),
			),
		);
		$this->assertSame( 'B', ( new Select() )->default_value( $field ) );
		$this->assertSame( array( 'B' ), ( new Checkbox() )->default_value( $field ) );
	}

	public function test_sanitize_config_keeps_only_supported_options() {
		$config = ( new Email() )->sanitize_config(
			array(
				'id'         => '7',
				'type'       => 'something-else',
				'label'      => '<script>x</script>Email',
				'required'   => 1,
				'max_length' => 5,
				'evil'       => 'x',
			)
		);
		$this->assertSame( 7, $config['id'] );
		$this->assertSame( 'email', $config['type'] );
		$this->assertSame( 'xEmail', $config['label'] );
		$this->assertTrue( $config['required'] );
		$this->assertArrayNotHasKey( 'max_length', $config );
		$this->assertArrayNotHasKey( 'evil', $config );
	}

	public function test_render_escapes_values() {
		$html = ( new Text() )->render( array( 'id' => 1, 'label' => 'A "quote"', 'required' => true ), 9, '"><script>' );
		$this->assertStringNotContainsString( '"><script>', $html );
		$this->assertStringContainsString( 'id="glixform-9-field-1"', $html );
		$this->assertStringContainsString( 'aria-required="true"', $html );
	}
}

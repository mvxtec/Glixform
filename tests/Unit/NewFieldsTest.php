<?php
/**
 * Phase 2 field types.
 *
 * @package Glixform
 */

namespace Glixform\Tests\Unit;

use Glixform\Fields\Address;
use Glixform\Fields\DateTime;
use Glixform\Fields\FileUpload;
use Glixform\Fields\Gdpr;
use Glixform\Fields\Name;
use Glixform\Fields\PageBreak;
use Glixform\Fields\Phone;
use Glixform\Fields\Rating;
use Glixform\Fields\Url;
use PHPUnit\Framework\TestCase;

/**
 * File field whose "uploaded" check accepts test files.
 */
class TestableFileUpload extends FileUpload {
	protected function is_uploaded( $tmp_name ) {
		return '' !== $tmp_name;
	}
}

class NewFieldsTest extends TestCase {

	public function test_phone() {
		$f = array( 'id' => 1, 'label' => 'Phone' );
		$p = new Phone();
		$this->assertSame( '', $p->validate( $f, '+1 (555) 123-4567' ) );
		$this->assertSame( '', $p->validate( $f, '' ) );
		$this->assertNotSame( '', $p->validate( $f, '12345' ), 'Too short' );
		$this->assertNotSame( '', $p->validate( $f, 'call me' ) );
		$this->assertNotSame( '', $p->validate( $f, '1234567890123456' ), 'Longer than E.164' );
	}

	public function test_url_adds_scheme_and_rejects_other_schemes() {
		$f = array( 'id' => 1, 'label' => 'Site' );
		$u = new Url();
		$this->assertSame( 'https://example.com', $u->sanitize_value( $f, 'example.com' ) );
		$this->assertSame( '', $u->validate( $f, 'https://example.com/path?a=1' ) );
		$this->assertNotSame( '', $u->validate( $f, 'javascript://alert(1)' ) );
		$this->assertNotSame( '', $u->validate( $f, 'https://localhost' ) );
	}

	public function test_date_time_formats_and_range() {
		$d    = new DateTime();
		$date = array( 'id' => 1, 'label' => 'Day', 'format' => 'date', 'min' => '2026-01-01', 'max' => '2026-12-31' );
		$this->assertSame( '', $d->validate( $date, '2026-06-15' ) );
		$this->assertNotSame( '', $d->validate( $date, '2025-12-31' ), 'Before earliest' );
		$this->assertNotSame( '', $d->validate( $date, '2026-02-30' ), 'Impossible date' );
		$this->assertNotSame( '', $d->validate( $date, '15/06/2026' ) );

		$time = array( 'id' => 2, 'label' => 'Time', 'format' => 'time' );
		$this->assertSame( '14:30', $d->sanitize_value( $time, '14:30:00' ) );
		$this->assertSame( '', $d->validate( $time, '14:30' ) );
		$this->assertNotSame( '', $d->validate( $time, '25:00' ) );
		$this->assertSame( '2026-06-15', $d->format_value( $date, '2026-06-15' ) );
	}

	public function test_name_modes() {
		$n     = new Name();
		$parts = array( 'id' => 1, 'label' => 'Name', 'required' => true, 'format' => 'first-last' );
		$value = $n->sanitize_value( $parts, array( 'first' => ' Jane ', 'last' => 'Doe', 'evil' => 'x' ) );
		$this->assertSame( array( 'first' => 'Jane', 'last' => 'Doe' ), $value );
		$this->assertSame( 'Jane Doe', $n->format_value( $parts, $value ) );
		$this->assertNotSame( '', $n->validate( $parts, array( 'first' => 'Jane', 'last' => '' ) ) );

		$simple = array( 'id' => 1, 'label' => 'Name', 'required' => true, 'format' => 'simple' );
		$this->assertSame( 'Jane', $n->sanitize_value( $simple, 'Jane' ) );
		$this->assertNotSame( '', $n->validate( $simple, '' ) );
	}

	public function test_address() {
		$a     = new Address();
		$field = array( 'id' => 1, 'label' => 'Address', 'required' => true );
		$value = $a->sanitize_value( $field, array( 'line1' => '1 Main St', 'city' => 'Springfield', 'state' => 'IL', 'postal' => '62701', 'country' => 'USA' ) );
		$this->assertSame( '', $a->validate( $field, $value ) );
		$this->assertSame( "1 Main St\nSpringfield, IL 62701\nUSA", $a->format_value( $field, $value ) );
		$this->assertNotSame( '', $a->validate( $field, array_merge( $value, array( 'city' => '' ) ) ), 'City is required' );
		$this->assertSame( '', $a->validate( $field, array_merge( $value, array( 'state' => '' ) ) ), 'State is optional' );

		$no_country = $field + array( 'hide_country' => true );
		$this->assertArrayNotHasKey( 'country', $a->sanitize_value( $no_country, $value ) );
	}

	public function test_rating() {
		$r     = new Rating();
		$field = array( 'id' => 1, 'label' => 'Rate', 'required' => true, 'scale' => 5 );
		$this->assertSame( '', $r->validate( $field, '4' ) );
		$this->assertNotSame( '', $r->validate( $field, '6' ) );
		$this->assertNotSame( '', $r->validate( $field, '' ) );
		$this->assertSame( '4 / 5', $r->format_value( $field, '4' ) );
	}

	public function test_gdpr_is_always_required_and_stores_the_consent_text() {
		$g      = new Gdpr();
		$config = $g->sanitize_config( array( 'id' => 1, 'consent_text' => 'I agree', 'required' => false ) );
		$this->assertTrue( $config['required'] );
		$this->assertSame( 'I agree', $g->sanitize_value( $config, '1' ) );
		$this->assertSame( '', $g->sanitize_value( $config, 'yes' ) );
		$this->assertNotSame( '', $g->validate( $config, '' ) );
	}

	public function test_file_upload_validation() {
		$f     = new TestableFileUpload();
		$field = array( 'id' => 1, 'label' => 'CV', 'required' => true, 'allowed_extensions' => 'pdf, png, php', 'max_size' => 1, 'max_files' => 2 );
		$file  = function ( $name, $size = 1000, $error = UPLOAD_ERR_OK ) {
			return array( 'name' => $name, 'tmp_name' => '/tmp/x', 'size' => $size, 'error' => $error );
		};

		$this->assertNotSame( '', $f->validate( $field, $f->sanitize_value( $field, array( $file( '', 0, UPLOAD_ERR_NO_FILE ) ) ) ), 'Required' );
		$this->assertSame( '', $f->validate( $field, $f->sanitize_value( $field, array( $file( 'cv.pdf' ) ) ) ) );
		$this->assertNotSame( '', $f->validate( $field, $f->sanitize_value( $field, array( $file( 'photo.jpg' ) ) ) ), 'Not in the field list' );
		$this->assertNotSame( '', $f->validate( $field, $f->sanitize_value( $field, array( $file( 'shell.php' ) ) ) ), 'Never allowed even if listed' );
		$this->assertNotSame( '', $f->validate( $field, $f->sanitize_value( $field, array( $file( 'big.pdf', 2 * 1048576 ) ) ) ), 'Too big' );
		$this->assertNotSame( '', $f->validate( $field, $f->sanitize_value( $field, array( $file( 'a.pdf' ), $file( 'b.pdf' ), $file( 'c.pdf' ) ) ) ), 'Too many' );
		$this->assertSame( array( 'pdf', 'png', 'php' ), $f->allowed_extensions( $field ) );
		$this->assertSame( 'cv.pdf, b.png', $f->format_value( $field, array( array( 'name' => 'cv.pdf' ), array( 'name' => 'b.png' ) ) ) );
	}

	public function test_page_break_is_layout() {
		$p = new PageBreak();
		$this->assertFalse( $p->is_input() );
		$this->assertFalse( $p->supports_logic() );
		$config = $p->sanitize_config( array( 'id' => 4, 'label' => '', 'next_text' => '' ) );
		$this->assertSame( '', $config['label'] );
		$this->assertSame( 'Next', $config['next_text'] );
	}
}

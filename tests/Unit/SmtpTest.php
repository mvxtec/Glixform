<?php
/**
 * SMTP settings, password encryption and error hints.
 *
 * @package Glixform
 */

namespace Glixform\Tests\Unit;

use Glixform\Admin\EmailPage;
use Glixform\Mail\Crypto;
use Glixform\Mail\Providers;
use Glixform\Mail\SmtpSettings;
use PHPUnit\Framework\TestCase;

class SmtpTest extends TestCase {

	protected function tearDown(): void {
		unset( $GLOBALS['glixform_test_options']['glixform_email'] );
	}

	public function test_encryption_round_trip() {
		$secret = 'p@ss w0rd!" ünïcode';
		$stored = Crypto::encrypt( $secret );
		$this->assertStringStartsWith( Crypto::PREFIX, $stored );
		$this->assertStringNotContainsString( 'w0rd', $stored );
		$this->assertSame( $secret, Crypto::decrypt( $stored ) );
		$this->assertNotSame( $stored, Crypto::encrypt( $secret ), 'Random IV each time' );
		$this->assertSame( '', Crypto::encrypt( '' ) );
	}

	public function test_tampered_or_foreign_values_do_not_decrypt() {
		$stored          = Crypto::encrypt( 'secret' );
		$stored[ 20 ]    = 'A' === $stored[20] ? 'B' : 'A';
		$this->assertSame( '', Crypto::decrypt( $stored ) );
		$this->assertSame( '', Crypto::decrypt( 'plain text' ) );
	}

	public function test_sanitize_keeps_saved_password_when_left_empty() {
		$saved = Crypto::encrypt( 'old' );
		$GLOBALS['glixform_test_options']['glixform_email'] = array( 'password' => $saved );

		$clean = SmtpSettings::sanitize( array( 'provider' => 'gmail', 'host' => 'smtp.gmail.com', 'new_password' => '' ) );
		$this->assertSame( $saved, $clean['password'] );

		$clean = SmtpSettings::sanitize( array( 'provider' => 'gmail', 'new_password' => 'new one' ) );
		$this->assertSame( 'new one', Crypto::decrypt( $clean['password'] ) );

		$clean = SmtpSettings::sanitize( array( 'provider' => 'gmail', 'remove_password' => '1' ) );
		$this->assertSame( '', $clean['password'] );
	}

	public function test_sanitize_is_safe_to_run_twice() {
		$first  = SmtpSettings::sanitize( array( 'provider' => 'other', 'host' => 'mail.example.com', 'new_password' => 'secret', 'auth' => '1' ) );
		$second = SmtpSettings::sanitize( $first );
		$this->assertSame( 'secret', Crypto::decrypt( $second['password'] ), 'Not encrypted twice' );
	}

	public function test_sanitize_validates_values() {
		$clean = SmtpSettings::sanitize(
			array(
				'provider'   => 'evil',
				'host'       => " SMTP.Example.com/<script> ",
				'encryption' => 'rot13',
				'port'       => 99999,
				'from_email' => 'not-an-email',
				'log_days'   => 0,
			)
		);
		$this->assertSame( 'default', $clean['provider'] );
		$this->assertSame( 'smtp.example.comscript', $clean['host'] );
		$this->assertSame( 'tls', $clean['encryption'] );
		$this->assertSame( 587, $clean['port'] );
		$this->assertSame( '', $clean['from_email'] );
		$this->assertSame( 1, $clean['log_days'] );
		$this->assertFalse( $clean['auth'] );
	}

	public function test_uses_smtp_needs_provider_and_host() {
		$this->assertFalse( SmtpSettings::uses_smtp( array( 'provider' => 'default', 'host' => 'x' ) ) );
		$this->assertFalse( SmtpSettings::uses_smtp( array( 'provider' => 'gmail', 'host' => '' ) ) );
		$this->assertTrue( SmtpSettings::uses_smtp( array( 'provider' => 'gmail', 'host' => 'smtp.gmail.com' ) ) );
	}

	public function test_presets() {
		$this->assertSame( 'smtp.gmail.com', Providers::get( 'gmail' )['host'] );
		$this->assertSame( 'apikey', Providers::get( 'sendgrid' )['username'] );
		$this->assertSame( 465, Providers::default_port( 'ssl' ) );
		$this->assertSame( 587, Providers::default_port( 'tls' ) );
		$this->assertNull( Providers::get( 'nope' ) );
	}

	public function test_error_hints() {
		$this->assertStringContainsString( 'App Password', EmailPage::hint( 'SMTP Error: Could not authenticate.' ) );
		$this->assertStringContainsString( 'port', EmailPage::hint( 'SMTP Error: Could not connect to SMTP host. Failed to connect to server' ) );
		$this->assertStringContainsString( 'secure connection', EmailPage::hint( 'SSL routines::wrong version number' ) );
		$this->assertStringContainsString( 'sender', EmailPage::hint( 'SMTP Error: data not accepted. 553 sender address not owned' ) );
		$this->assertStringContainsString( 'App Password', EmailPage::hint( '535 5.7.8 Username and Password not accepted' ) );
		$this->assertStringContainsString( '2-Step Verification', EmailPage::hint( "SMTP Error: Could not authenticate. 534-5.7.9 Application-specific password required. For more information, go to\n534 5.7.9  https://support.google.com/mail/?p=InvalidSecondFactor" ) );
	}

	public function test_gmail_app_password_spaces_are_removed() {
		$clean = SmtpSettings::sanitize( array( 'provider' => 'gmail', 'new_password' => 'abcd efgh ijkl mnop' ) );
		$this->assertSame( 'abcdefghijklmnop', Crypto::decrypt( $clean['password'] ) );
		$clean = SmtpSettings::sanitize( array( 'provider' => 'other', 'host' => 'x.example.com', 'new_password' => 'my pass word' ) );
		$this->assertSame( 'my pass word', Crypto::decrypt( $clean['password'] ), 'Other providers keep spaces' );
	}
}

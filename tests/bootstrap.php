<?php
/**
 * Unit test bootstrap: loads the plugin classes with small stand-ins for the
 * WordPress functions they call, so the logic can be tested without WordPress.
 *
 * @package Glixform
 */

define( 'ABSPATH', __DIR__ . '/' );

require dirname( __DIR__ ) . '/vendor/autoload.php';

$GLOBALS['glixform_test_options'] = array(
	'admin_email' => 'admin@example.com',
	'date_format' => 'Y-m-d',
	'time_format' => 'H:i',
);

function __( $text ) {
	return $text;
}
function esc_html__( $text ) {
	return $text;
}
function esc_html( $text ) {
	return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
}
function esc_attr( $text ) {
	return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
}
function esc_textarea( $text ) {
	return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
}
function esc_url_raw( $url ) {
	return filter_var( $url, FILTER_VALIDATE_URL ) ? $url : '';
}
function absint( $value ) {
	return abs( (int) $value );
}
function sanitize_text_field( $value ) {
	return trim( preg_replace( '/[\r\n\t ]+/', ' ', strip_tags( (string) $value ) ) );
}
function sanitize_textarea_field( $value ) {
	return trim( strip_tags( (string) $value ) );
}
function sanitize_email( $value ) {
	return preg_replace( '/[^a-z0-9+_.@-]/i', '', (string) $value );
}
function is_email( $value ) {
	return (bool) filter_var( $value, FILTER_VALIDATE_EMAIL );
}
function wp_kses_post( $value ) {
	return preg_replace( '#<script\b[^>]*>.*?</script>#is', '', (string) $value );
}
function apply_filters( $hook, $value ) {
	return $value;
}
function do_action() {
}
function get_option( $name, $fallback = false ) {
	return $GLOBALS['glixform_test_options'][ $name ] ?? $fallback;
}
function wp_parse_args( $args, $defaults ) {
	return array_merge( $defaults, (array) $args );
}
function wp_salt() {
	return 'test-salt';
}
function home_url( $path = '' ) {
	return 'https://example.com' . $path;
}
function get_bloginfo() {
	return 'Test & Site';
}
function wp_specialchars_decode( $text ) {
	return htmlspecialchars_decode( $text, ENT_QUOTES );
}
function wp_date( $format ) {
	return gmdate( $format, 0 );
}
function selected( $a, $b, $display = true ) {
	return (string) $a === (string) $b ? ' selected=\'selected\'' : '';
}
function wpautop( $text ) {
	return '<p>' . $text . '</p>';
}
function get_current_user_id() {
	return 0;
}
if ( ! defined( 'MB_IN_BYTES' ) ) {
	define( 'MB_IN_BYTES', 1048576 );
}
function sanitize_html_class( $class ) {
	return preg_replace( '/[^A-Za-z0-9_-]/', '', (string) $class );
}
function sanitize_file_name( $name ) {
	return preg_replace( '/[^A-Za-z0-9._-]/', '-', (string) $name );
}
function sanitize_key( $key ) {
	return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $key ) );
}
function wp_json_encode( $data ) {
	return json_encode( $data );
}
function wp_parse_url( $url, $component = -1 ) {
	return parse_url( $url, $component );
}
function date_i18n( $format, $timestamp ) {
	return gmdate( $format, $timestamp );
}
function _n( $single, $plural, $number ) {
	return 1 === (int) $number ? $single : $plural;
}
function size_format( $bytes ) {
	return round( $bytes / 1048576 ) . ' MB';
}
function wp_max_upload_size() {
	return 64 * 1048576;
}
function get_allowed_mime_types() {
	return array(
		'jpg|jpeg|jpe' => 'image/jpeg',
		'png'          => 'image/png',
		'pdf'          => 'application/pdf',
	);
}
function wp_check_filetype_and_ext( $file, $filename ) {
	$ext   = strtolower( pathinfo( $filename, PATHINFO_EXTENSION ) );
	$types = array(
		'jpg' => 'image/jpeg',
		'png' => 'image/png',
		'pdf' => 'application/pdf',
		'php' => false,
	);
	return array(
		'ext'  => isset( $types[ $ext ] ) && $types[ $ext ] ? $ext : false,
		'type' => $types[ $ext ] ?? false,
	);
}
function wp_get_current_user() {
	return (object) array( 'ID' => 0 );
}
function wp_generate_password( $length = 12 ) {
	return substr( str_repeat( 'abcdefghij', 5 ), 0, $length );
}
function wp_strip_all_tags( $text ) {
	return strip_tags( (string) $text );
}
function esc_attr__( $text ) {
	return $text;
}

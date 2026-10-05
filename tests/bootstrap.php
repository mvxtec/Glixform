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

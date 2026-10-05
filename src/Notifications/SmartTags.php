<?php
/**
 * Smart tag replacement, e.g. {field_id="2"} or {admin_email}.
 *
 * @package Glixform
 */

namespace Glixform\Notifications;

use Glixform\Fields\AbstractField;

defined( 'ABSPATH' ) || exit;

/**
 * Replaces smart tags in notifications, confirmations and default values.
 */
class SmartTags {

	/**
	 * Tags listed in the builder: tag => description.
	 *
	 * @return array
	 */
	public static function catalog() {
		return array(
			'{all_fields}'           => __( 'All submitted fields', 'glixform' ),
			'{field_id="N"}'         => __( 'Value of field N', 'glixform' ),
			'{form_name}'            => __( 'Form name', 'glixform' ),
			'{form_id}'              => __( 'Form ID', 'glixform' ),
			'{entry_id}'             => __( 'Entry ID', 'glixform' ),
			'{date}'                 => __( 'Date and time of submission', 'glixform' ),
			'{page_url}'             => __( 'Page the form was submitted from', 'glixform' ),
			'{page_title}'           => __( 'Title of the current page', 'glixform' ),
			'{site_name}'            => __( 'Site name', 'glixform' ),
			'{site_url}'             => __( 'Site address', 'glixform' ),
			'{admin_email}'          => __( 'Site admin email', 'glixform' ),
			'{user_id}'              => __( 'Logged-in user ID', 'glixform' ),
			'{user_email}'           => __( 'Logged-in user email', 'glixform' ),
			'{user_display_name}'    => __( 'Logged-in user display name', 'glixform' ),
			'{user_first_name}'      => __( 'Logged-in user first name', 'glixform' ),
			'{user_last_name}'       => __( 'Logged-in user last name', 'glixform' ),
			'{user_ip}'              => __( 'Visitor IP address', 'glixform' ),
			'{query_var key="name"}' => __( 'Value of a URL parameter', 'glixform' ),
			'{unique_id}'            => __( 'Random unique ID', 'glixform' ),
		);
	}

	/**
	 * Replace tags.
	 *
	 * @param string $content Text with tags.
	 * @param array  $context form (array), fields (snapshot), entry_id, page_url, page_title, user_ip.
	 * @param string $mode    "html" escapes values for HTML output, "text" leaves them plain.
	 * @return string
	 */
	public function process( $content, array $context, $mode = 'html' ) {
		$content = (string) $content;
		if ( false === strpos( $content, '{' ) ) {
			return $content;
		}

		$escape = static function ( $value ) use ( $mode ) {
			return 'html' === $mode ? nl2br( esc_html( (string) $value ) ) : (string) $value;
		};

		$fields = (array) ( $context['fields'] ?? array() );
		$by_id  = array();
		foreach ( $fields as $field ) {
			$by_id[ (int) $field['id'] ] = $field;
		}

		$content = (string) preg_replace_callback(
			'/\{field_id="(\d+)"\}/',
			static function ( $m ) use ( $by_id, $escape ) {
				return isset( $by_id[ (int) $m[1] ] ) ? $escape( self::item_text( $by_id[ (int) $m[1] ] ) ) : '';
			},
			$content
		);

		$content = (string) preg_replace_callback(
			'/\{query_var key="([A-Za-z0-9_\-\[\]]+)"\}/',
			static function ( $m ) use ( $escape ) {
				// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only display of a URL parameter.
				$value = isset( $_GET[ $m[1] ] ) && is_scalar( $_GET[ $m[1] ] ) ? sanitize_text_field( wp_unslash( $_GET[ $m[1] ] ) ) : '';
				return $escape( $value );
			},
			$content
		);

		$form = (array) ( $context['form'] ?? array() );
		$user = wp_get_current_user();

		$tags = array(
			'{form_name}'         => $escape( (string) ( $form['title'] ?? '' ) ),
			'{form_id}'           => (string) ( $form['id'] ?? '' ),
			'{entry_id}'          => (string) ( $context['entry_id'] ?? '' ),
			'{site_name}'         => $escape( wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ) ),
			'{site_url}'          => $escape( home_url( '/' ) ),
			'{admin_email}'       => $escape( (string) get_option( 'admin_email' ) ),
			'{date}'              => $escape( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) ) ),
			'{page_url}'          => $escape( (string) ( $context['page_url'] ?? '' ) ),
			'{page_title}'        => $escape( (string) ( $context['page_title'] ?? '' ) ),
			'{user_id}'           => $user && $user->ID ? (string) $user->ID : '',
			'{user_email}'        => $escape( $user && $user->ID ? $user->user_email : '' ),
			'{user_display_name}' => $escape( $user && $user->ID ? $user->display_name : '' ),
			'{user_first_name}'   => $escape( $user && $user->ID ? $user->first_name : '' ),
			'{user_last_name}'    => $escape( $user && $user->ID ? $user->last_name : '' ),
			'{user_ip}'           => $escape( (string) ( $context['user_ip'] ?? '' ) ),
			'{unique_id}'         => wp_generate_password( 12, false ),
		);

		if ( false !== strpos( $content, '{all_fields}' ) ) {
			$tags['{all_fields}'] = $this->all_fields( $fields, $mode );
		}

		/**
		 * Filters smart tag replacements. Add custom tags as '{tag}' => 'value' (already escaped for $mode).
		 *
		 * @param array  $tags    Replacements.
		 * @param array  $context Context.
		 * @param string $mode    "html" or "text".
		 */
		$tags = apply_filters( 'glixform_smart_tags', $tags, $context, $mode );

		return strtr( $content, $tags );
	}

	/**
	 * Render all submitted fields.
	 *
	 * @param array  $fields Field snapshot.
	 * @param string $mode   "html" or "text".
	 * @return string
	 */
	public function all_fields( array $fields, $mode = 'html' ) {
		$out = '';
		foreach ( $fields as $field ) {
			if ( 'hidden' === ( $field['type'] ?? '' ) ) {
				continue;
			}
			$value = self::item_text( $field );
			if ( 'html' === $mode ) {
				$out .= sprintf(
					'<tr><td style="padding:14px 0;border-bottom:1px solid #eef0f4;"><div style="font-size:12px;font-weight:600;letter-spacing:.04em;text-transform:uppercase;color:#6b7280;margin-bottom:4px;">%s</div><div style="font-size:15px;color:#111827;line-height:1.5;">%s</div></td></tr>',
					esc_html( $field['label'] ),
					'' === $value ? '<span style="color:#9ca3af;">' . esc_html__( 'Empty', 'glixform' ) . '</span>' : nl2br( esc_html( $value ) )
				);
			} else {
				$out .= $field['label'] . ":\n" . ( '' === $value ? __( 'Empty', 'glixform' ) : $value ) . "\n\n";
			}
		}
		return 'html' === $mode ? '<table role="presentation" cellpadding="0" cellspacing="0" style="width:100%;border-collapse:collapse;">' . $out . '</table>' : trim( $out );
	}

	/**
	 * Display text of a snapshot item.
	 *
	 * @param array $item Snapshot item.
	 * @return string
	 */
	public static function item_text( array $item ) {
		return isset( $item['formatted'] ) ? (string) $item['formatted'] : AbstractField::flatten( $item['value'] ?? '' );
	}

	/**
	 * Flatten a stored value (kept for backwards compatibility).
	 *
	 * @param mixed $value Value.
	 * @return string
	 */
	public static function value_to_string( $value ) {
		return AbstractField::flatten( $value );
	}
}

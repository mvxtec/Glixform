<?php
/**
 * Smart tag replacement, e.g. {field_id="2"} or {admin_email}.
 *
 * @package Glixform
 */

namespace Glixform\Notifications;

defined( 'ABSPATH' ) || exit;

/**
 * Replaces smart tags in notification and confirmation text.
 *
 * Supported tags: {field_id="N"}, {all_fields}, {form_name}, {form_id},
 * {entry_id}, {site_name}, {site_url}, {admin_email}, {date}, {page_url}.
 */
class SmartTags {

	/**
	 * Replace tags.
	 *
	 * @param string $content Text with tags.
	 * @param array  $context form (array), fields (snapshot), entry_id, page_url.
	 * @param string $mode    "html" escapes values for HTML output, "text" leaves them plain.
	 * @return string
	 */
	public function process( $content, array $context, $mode = 'html' ) {
		$content = (string) $content;
		if ( false === strpos( $content, '{' ) ) {
			return $content;
		}

		$fields = (array) ( $context['fields'] ?? array() );
		$by_id  = array();
		foreach ( $fields as $field ) {
			$by_id[ (int) $field['id'] ] = $field;
		}

		$escape = function ( $value ) use ( $mode ) {
			return 'html' === $mode ? nl2br( esc_html( $value ) ) : $value;
		};

		$content = preg_replace_callback(
			'/\{field_id="(\d+)"\}/',
			static function ( $m ) use ( $by_id, $escape ) {
				if ( ! isset( $by_id[ (int) $m[1] ] ) ) {
					return '';
				}
				return $escape( self::value_to_string( $by_id[ (int) $m[1] ]['value'] ) );
			},
			$content
		);

		$form = (array) ( $context['form'] ?? array() );

		$tags = array(
			'{form_name}'   => $escape( (string) ( $form['title'] ?? '' ) ),
			'{form_id}'     => (string) ( $form['id'] ?? '' ),
			'{entry_id}'    => (string) ( $context['entry_id'] ?? '' ),
			'{site_name}'   => $escape( wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ) ),
			'{site_url}'    => $escape( home_url( '/' ) ),
			'{admin_email}' => $escape( (string) get_option( 'admin_email' ) ),
			'{date}'        => $escape( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) ) ),
			'{page_url}'    => $escape( (string) ( $context['page_url'] ?? '' ) ),
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
			$value = self::value_to_string( $field['value'] );
			if ( 'html' === $mode ) {
				$out .= sprintf(
					'<tr><th style="text-align:left;vertical-align:top;padding:8px 12px 8px 0;border-bottom:1px solid #e5e5e5;">%s</th><td style="padding:8px 0;border-bottom:1px solid #e5e5e5;">%s</td></tr>',
					esc_html( $field['label'] ),
					'' === $value ? '<em>' . esc_html__( 'Empty', 'glixform' ) . '</em>' : nl2br( esc_html( $value ) )
				);
			} else {
				$out .= $field['label'] . ":\n" . ( '' === $value ? __( 'Empty', 'glixform' ) : $value ) . "\n\n";
			}
		}
		return 'html' === $mode ? '<table cellpadding="0" cellspacing="0" style="width:100%;border-collapse:collapse;">' . $out . '</table>' : trim( $out );
	}

	/**
	 * Flatten a stored value.
	 *
	 * @param mixed $value Value.
	 * @return string
	 */
	public static function value_to_string( $value ) {
		return is_array( $value ) ? implode( ', ', $value ) : (string) $value;
	}
}

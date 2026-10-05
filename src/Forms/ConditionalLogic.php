<?php
/**
 * Conditional logic: show/hide fields, send notifications and pick confirmations
 * based on the visitor's answers.
 *
 * @package Glixform
 */

namespace Glixform\Forms;

defined( 'ABSPATH' ) || exit;

/**
 * A condition looks like this:
 * [ 'enabled' => true, 'action' => 'show'|'hide', 'logic' => 'all'|'any',
 *   'rules' => [ [ 'field' => 3, 'operator' => 'is', 'value' => 'Sales' ], ... ] ]
 *
 * The browser copy in assets/js/frontend.js mirrors evaluate() and visibility(),
 * so the browser and the server always agree on which fields are visible.
 */
class ConditionalLogic {

	const OPERATORS = array( 'is', 'is_not', 'empty', 'not_empty', 'contains', 'not_contains', 'starts_with', 'ends_with', 'greater_than', 'less_than' );

	const MAX_RULES = 20;

	/**
	 * Default (disabled) condition.
	 *
	 * @return array
	 */
	public static function defaults() {
		return array(
			'enabled' => false,
			'action'  => 'show',
			'logic'   => 'all',
			'rules'   => array(),
		);
	}

	/**
	 * Clean a condition. Rules pointing at unknown fields (or at the field itself) are dropped.
	 *
	 * @param mixed $raw       Raw condition.
	 * @param int[] $field_ids Field IDs rules may reference.
	 * @param int   $self_id   ID of the field the condition belongs to (0 for none).
	 * @return array
	 */
	public static function sanitize( $raw, array $field_ids, $self_id = 0 ) {
		$raw   = is_array( $raw ) ? $raw : array();
		$rules = array();

		foreach ( array_slice( (array) ( $raw['rules'] ?? array() ), 0, self::MAX_RULES ) as $rule ) {
			if ( ! is_array( $rule ) ) {
				continue;
			}
			$field    = absint( $rule['field'] ?? 0 );
			$operator = (string) ( $rule['operator'] ?? 'is' );
			if ( ! $field || $field === (int) $self_id || ! in_array( $field, $field_ids, true ) || ! in_array( $operator, self::OPERATORS, true ) ) {
				continue;
			}
			$rules[] = array(
				'field'    => $field,
				'operator' => $operator,
				'value'    => sanitize_text_field( isset( $rule['value'] ) && is_scalar( $rule['value'] ) ? (string) $rule['value'] : '' ),
			);
		}

		return array(
			'enabled' => ! empty( $raw['enabled'] ) && (bool) $rules,
			'action'  => 'hide' === ( $raw['action'] ?? '' ) ? 'hide' : 'show',
			'logic'   => 'any' === ( $raw['logic'] ?? '' ) ? 'any' : 'all',
			'rules'   => $rules,
		);
	}

	/**
	 * Whether the rules match ("all" or "any"). A disabled condition always matches.
	 *
	 * @param array $condition Condition.
	 * @param array $values    field_id => logic value (string or list).
	 * @return bool
	 */
	public static function rules_match( array $condition, array $values ) {
		if ( empty( $condition['enabled'] ) || empty( $condition['rules'] ) ) {
			return true;
		}
		$any = 'any' === ( $condition['logic'] ?? 'all' );
		foreach ( $condition['rules'] as $rule ) {
			$match = self::rule_matches( $rule, $values[ (int) $rule['field'] ] ?? '' );
			if ( $any && $match ) {
				return true;
			}
			if ( ! $any && ! $match ) {
				return false;
			}
		}
		return ! $any;
	}

	/**
	 * Apply the action: "show" when rules match, or "hide" when they match.
	 * Used for fields, notifications ("send") and confirmations ("use").
	 *
	 * @param array $condition Condition.
	 * @param array $values    field_id => logic value.
	 * @return bool True when the field is visible / the notification is sent.
	 */
	public static function evaluate( array $condition, array $values ) {
		if ( empty( $condition['enabled'] ) ) {
			return true;
		}
		$match = self::rules_match( $condition, $values );
		return 'hide' === ( $condition['action'] ?? 'show' ) ? ! $match : $match;
	}

	/**
	 * Visibility of every field, in form order. A hidden field counts as empty
	 * for the fields after it.
	 *
	 * @param array $fields Field configs in order.
	 * @param array $values field_id => logic value. Updated with empties for hidden fields.
	 * @return bool[] field_id => visible.
	 */
	public static function visibility( array $fields, array &$values ) {
		$visible = array();
		foreach ( $fields as $field ) {
			$id   = (int) $field['id'];
			$show = empty( $field['conditional']['enabled'] ) || self::evaluate( $field['conditional'], $values );

			$visible[ $id ] = $show;
			if ( ! $show ) {
				$values[ $id ] = is_array( $values[ $id ] ?? null ) ? array() : '';
			}
		}
		return $visible;
	}

	/**
	 * One rule.
	 *
	 * @param array        $rule  field, operator, value.
	 * @param string|array $value Field's logic value.
	 * @return bool
	 */
	public static function rule_matches( array $rule, $value ) {
		$operator = $rule['operator'];
		$expected = self::normalize( (string) $rule['value'] );

		if ( is_array( $value ) ) {
			$items = array_values( array_filter( array_map( array( __CLASS__, 'normalize' ), array_map( 'strval', array_filter( $value, 'is_scalar' ) ) ), 'strlen' ) );
			switch ( $operator ) {
				case 'empty':
					return ! $items;
				case 'not_empty':
					return (bool) $items;
				case 'is':
					return in_array( $expected, $items, true );
				case 'is_not':
					return ! in_array( $expected, $items, true );
				case 'not_contains':
					return ! self::rule_matches( array( 'operator' => 'contains' ) + $rule, $value );
				default:
					foreach ( $items as $item ) {
						if ( self::compare( $operator, $item, $expected ) ) {
							return true;
						}
					}
					return false;
			}
		}

		return self::compare( $operator, self::normalize( (string) $value ), $expected );
	}

	/**
	 * Compare two normalized strings.
	 *
	 * @param string $operator Operator.
	 * @param string $actual   Field value.
	 * @param string $expected Rule value.
	 * @return bool
	 */
	private static function compare( $operator, $actual, $expected ) {
		switch ( $operator ) {
			case 'is':
				return $actual === $expected;
			case 'is_not':
				return $actual !== $expected;
			case 'empty':
				return '' === $actual;
			case 'not_empty':
				return '' !== $actual;
			case 'contains':
				return '' !== $expected && false !== strpos( $actual, $expected );
			case 'not_contains':
				return '' === $expected || false === strpos( $actual, $expected );
			case 'starts_with':
				return '' !== $expected && 0 === strpos( $actual, $expected );
			case 'ends_with':
				return '' !== $expected && substr( $actual, -strlen( $expected ) ) === $expected;
			case 'greater_than':
			case 'less_than':
				if ( '' === $actual || '' === $expected ) {
					return false;
				}
				$cmp = ( is_numeric( $actual ) && is_numeric( $expected ) )
					? ( (float) $actual <=> (float) $expected )
					: strcmp( $actual, $expected ); // ISO dates and times compare correctly as strings.
				return 'greater_than' === $operator ? $cmp > 0 : $cmp < 0;
		}
		return false;
	}

	/**
	 * Case-insensitive, trimmed comparison value.
	 *
	 * @param string $value Value.
	 * @return string
	 */
	public static function normalize( $value ) {
		return function_exists( 'mb_strtolower' ) ? mb_strtolower( trim( $value ) ) : strtolower( trim( $value ) );
	}
}

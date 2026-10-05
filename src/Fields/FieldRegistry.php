<?php
/**
 * Registry of available field types.
 *
 * @package Glixform
 */

namespace Glixform\Fields;

defined( 'ABSPATH' ) || exit;

/**
 * Holds one instance per field type. Add-ons register types via the
 * "glixform_field_types" filter.
 */
class FieldRegistry {

	/**
	 * Field instances keyed by type.
	 *
	 * @var AbstractField[]|null
	 */
	private $types = null;

	/**
	 * All registered field types, built on first use so translations are loaded.
	 *
	 * @return AbstractField[]
	 */
	public function all() {
		if ( null === $this->types ) {
			$classes = array(
				Text::class,
				Textarea::class,
				Email::class,
				Number::class,
				Select::class,
				Radio::class,
				Checkbox::class,
				Hidden::class,
				Name::class,
				Phone::class,
				Url::class,
				DateTime::class,
				Address::class,
				FileUpload::class,
				Rating::class,
				Gdpr::class,
				PageBreak::class,
				Divider::class,
				Html::class,
			);

			/**
			 * Filters the field type classes. Each must extend AbstractField.
			 *
			 * @param string[] $classes Class names.
			 */
			$classes = apply_filters( 'glixform_field_types', $classes );

			$this->types = array();
			foreach ( $classes as $class ) {
				if ( is_string( $class ) && is_subclass_of( $class, AbstractField::class ) ) {
					$field                         = new $class();
					$this->types[ $field->type() ] = $field;
				}
			}
		}
		return $this->types;
	}

	/**
	 * Get one field type.
	 *
	 * @param string $type Type name.
	 * @return AbstractField|null
	 */
	public function get( $type ) {
		$all = $this->all();
		return $all[ $type ] ?? null;
	}
}

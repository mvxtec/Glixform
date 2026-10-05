import { __ } from '@wordpress/i18n';

/**
 * Data passed from PHP (BuilderPage::enqueue()).
 */
const raw = window.glixformBuilderConfig || {};

const types = {};
( raw.types || [] ).forEach( ( type ) => {
	types[ type.type ] = type;
} );

export const config = {
	...raw,
	types,
	typeList: raw.types || [],
};

/** Operators for conditional logic, in display order. */
export const operators = () => [
	[ 'is', __( 'is', 'glixform' ) ],
	[ 'is_not', __( 'is not', 'glixform' ) ],
	[ 'empty', __( 'is empty', 'glixform' ) ],
	[ 'not_empty', __( 'is not empty', 'glixform' ) ],
	[ 'contains', __( 'contains', 'glixform' ) ],
	[ 'not_contains', __( 'does not contain', 'glixform' ) ],
	[ 'starts_with', __( 'starts with', 'glixform' ) ],
	[ 'ends_with', __( 'ends with', 'glixform' ) ],
	[ 'greater_than', __( 'is greater than', 'glixform' ) ],
	[ 'less_than', __( 'is less than', 'glixform' ) ],
];

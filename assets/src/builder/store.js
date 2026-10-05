/**
 * Builder state: the form being edited plus undo/redo history.
 *
 * Every edit goes through the reducer. Rapid edits to the same input (typing)
 * share a "coalesce" key and collapse into one undo step.
 */

const HISTORY_LIMIT = 100;
const COALESCE_MS = 800;

export const clone = ( value ) => JSON.parse( JSON.stringify( value ) );

export function emptyCondition() {
	return { enabled: false, action: 'show', logic: 'all', rules: [] };
}

export function initialState( { formId, title, data } ) {
	return {
		formId: formId || 0,
		present: { title: title || '', data },
		past: [],
		future: [],
		dirty: false,
		selectedId: null,
		lastKey: null,
		lastTime: 0,
	};
}

/* ------------------------------------------------------------------ */
/* Pure helpers on form data                                          */
/* ------------------------------------------------------------------ */

function nextFieldId( data ) {
	const max = data.fields.reduce( ( m, f ) => Math.max( m, f.id ), 0 );
	return Math.max( data.next_field_id || 1, max + 1 );
}

function nextListId( list ) {
	return list.reduce( ( m, item ) => Math.max( m, item.id ), 0 ) + 1;
}

function edit( present, fn ) {
	const next = clone( present );
	fn( next );
	return next;
}

/* ------------------------------------------------------------------ */
/* Reducer                                                            */
/* ------------------------------------------------------------------ */

function apply( present, action, types ) {
	switch ( action.type ) {
		case 'SET_TITLE':
			return { ...present, title: action.title };

		case 'LOAD':
			return { title: action.title, data: clone( action.data ) };

		case 'ADD_FIELD':
			return edit( present, ( p ) => {
				const id = nextFieldId( p.data );
				const field = {
					...clone( types[ action.fieldType ].defaults ),
					id,
					type: action.fieldType,
					conditional: emptyCondition(),
				};
				const at =
					action.index === undefined
						? p.data.fields.length
						: action.index;
				p.data.fields.splice( at, 0, field );
				p.data.next_field_id = id + 1;
				action.createdId = id;
			} );

		case 'UPDATE_FIELD':
			return edit( present, ( p ) => {
				const field = p.data.fields.find( ( f ) => f.id === action.id );
				if ( field ) {
					Object.assign( field, action.patch );
				}
			} );

		case 'DUPLICATE_FIELD':
			return edit( present, ( p ) => {
				const index = p.data.fields.findIndex(
					( f ) => f.id === action.id
				);
				if ( index === -1 ) {
					return;
				}
				const id = nextFieldId( p.data );
				const copy = { ...clone( p.data.fields[ index ] ), id };
				p.data.fields.splice( index + 1, 0, copy );
				p.data.next_field_id = id + 1;
				action.createdId = id;
			} );

		case 'DELETE_FIELD':
			return edit( present, ( p ) => {
				p.data.fields = p.data.fields.filter(
					( f ) => f.id !== action.id
				);
				// Drop rules that pointed at the deleted field.
				const scrub = ( cond ) => {
					if ( cond && cond.rules ) {
						cond.rules = cond.rules.filter(
							( r ) => r.field !== action.id
						);
						if ( ! cond.rules.length ) {
							cond.enabled = false;
						}
					}
				};
				p.data.fields.forEach( ( f ) => scrub( f.conditional ) );
				p.data.settings.notifications.forEach( ( n ) =>
					scrub( n.conditional )
				);
				p.data.settings.confirmations.forEach( ( c ) =>
					scrub( c.conditional )
				);
			} );

		case 'MOVE_FIELD':
			return edit( present, ( p ) => {
				const [ moved ] = p.data.fields.splice( action.from, 1 );
				p.data.fields.splice( action.to, 0, moved );
			} );

		case 'UPDATE_SETTINGS':
			return edit( present, ( p ) => {
				Object.assign( p.data.settings, action.patch );
			} );

		case 'ADD_LIST_ITEM':
			return edit( present, ( p ) => {
				const list = p.data.settings[ action.list ];
				const id = nextListId( list );
				const base = action.copyOf
					? clone( list.find( ( i ) => i.id === action.copyOf ) )
					: clone( action.item );
				list.push( { ...base, id, name: action.name || base.name } );
				action.createdId = id;
			} );

		case 'UPDATE_LIST_ITEM':
			return edit( present, ( p ) => {
				const item = p.data.settings[ action.list ].find(
					( i ) => i.id === action.id
				);
				if ( item ) {
					Object.assign( item, action.patch );
				}
			} );

		case 'DELETE_LIST_ITEM':
			return edit( present, ( p ) => {
				p.data.settings[ action.list ] = p.data.settings[
					action.list
				].filter( ( i ) => i.id !== action.id );
			} );
	}
	return present;
}

export function createReducer( types ) {
	return function reducer( state, action ) {
		switch ( action.type ) {
			case 'SELECT':
				return { ...state, selectedId: action.id };

			case 'UNDO': {
				if ( ! state.past.length ) {
					return state;
				}
				const previous = state.past[ state.past.length - 1 ];
				return {
					...state,
					present: previous,
					past: state.past.slice( 0, -1 ),
					future: [ state.present, ...state.future ],
					dirty: true,
					lastKey: null,
					selectedId: previous.data.fields.some(
						( f ) => f.id === state.selectedId
					)
						? state.selectedId
						: null,
				};
			}

			case 'REDO': {
				if ( ! state.future.length ) {
					return state;
				}
				const [ next, ...rest ] = state.future;
				return {
					...state,
					present: next,
					past: [ ...state.past, state.present ].slice(
						-HISTORY_LIMIT
					),
					future: rest,
					dirty: true,
					lastKey: null,
					selectedId: next.data.fields.some(
						( f ) => f.id === state.selectedId
					)
						? state.selectedId
						: null,
				};
			}

			case 'SAVED':
				return {
					...state,
					formId: action.formId,
					// Keep the client copy so the cursor/selection doesn't jump; the server copy is equivalent.
					present: action.replace
						? { title: action.title, data: action.data }
						: state.present,
					dirty: action.changedSince ? state.dirty : false,
				};

			case 'RESET_HISTORY':
				return {
					...state,
					past: [],
					future: [],
					dirty: !! action.dirty,
					lastKey: null,
				};
		}

		const present = apply( state.present, action, types );
		if ( present === state.present ) {
			return state;
		}

		const now = Date.now();
		const coalesce =
			action.coalesce &&
			action.coalesce === state.lastKey &&
			now - state.lastTime < COALESCE_MS;

		let selectedId = state.selectedId;
		if ( action.createdId && action.type !== 'ADD_LIST_ITEM' ) {
			selectedId = action.createdId;
		}
		if (
			action.type === 'DELETE_FIELD' &&
			state.selectedId === action.id
		) {
			selectedId = null;
		}

		return {
			...state,
			present,
			past: coalesce
				? state.past
				: [ ...state.past, state.present ].slice( -HISTORY_LIMIT ),
			future: [],
			dirty: true,
			selectedId,
			lastKey: action.coalesce || null,
			lastTime: now,
		};
	};
}

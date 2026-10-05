/**
 * Glixform form builder.
 *
 * Keeps the field list in `state.fields`, renders it as editable cards and
 * serializes it to JSON into a hidden input when the form is saved. The server
 * sanitizes everything again (FormRepository::sanitize()).
 */
( function () {
	'use strict';

	var config = window.glixformBuilder;
	if ( ! config ) {
		return;
	}

	var i18n = config.i18n;
	var types = {};
	config.types.forEach( function ( type ) {
		types[ type.type ] = type;
	} );

	var state = {
		fields: ( config.fields || [] ).filter( function ( field ) {
			return types[ field.type ];
		} ),
		nextId: parseInt( config.nextId, 10 ) || 1,
		open: null,
		dirty: false,
	};

	var form = document.getElementById( 'glixform-builder-form' );
	var list = document.getElementById( 'glixform-field-list' );
	var palette = document.getElementById( 'glixform-palette' );
	var empty = document.getElementById( 'glixform-empty' );
	var dragIndex = null;

	/* ---------- helpers ---------- */

	function el( tag, attrs, children ) {
		var node = document.createElement( tag );
		Object.keys( attrs || {} ).forEach( function ( key ) {
			var value = attrs[ key ];
			if ( key === 'text' ) {
				node.textContent = value;
			} else if ( key === 'className' ) {
				node.className = value;
			} else if ( key.indexOf( 'on' ) === 0 ) {
				node.addEventListener( key.slice( 2 ), value );
			} else if ( value === true ) {
				node.setAttribute( key, '' );
			} else if ( value !== false && value !== null && value !== undefined ) {
				node.setAttribute( key, value );
			}
		} );
		( children || [] ).forEach( function ( child ) {
			if ( child ) {
				node.appendChild( typeof child === 'string' ? document.createTextNode( child ) : child );
			}
		} );
		return node;
	}

	function clone( value ) {
		return JSON.parse( JSON.stringify( value ) );
	}

	function markDirty() {
		state.dirty = true;
	}

	function indexOfField( id ) {
		for ( var i = 0; i < state.fields.length; i++ ) {
			if ( state.fields[ i ].id === id ) {
				return i;
			}
		}
		return -1;
	}

	/* ---------- actions ---------- */

	function addField( type ) {
		var field = clone( types[ type ].defaults );
		field.id = state.nextId++;
		field.type = type;
		state.fields.push( field );
		state.open = field.id;
		markDirty();
		render();
		focusField( field.id );
	}

	function duplicateField( id ) {
		var index = indexOfField( id );
		var copy = clone( state.fields[ index ] );
		copy.id = state.nextId++;
		state.fields.splice( index + 1, 0, copy );
		state.open = copy.id;
		markDirty();
		render();
		focusField( copy.id );
	}

	function deleteField( id ) {
		if ( ! window.confirm( i18n.confirmDelete ) ) {
			return;
		}
		var index = indexOfField( id );
		state.fields.splice( index, 1 );
		markDirty();
		render();
		var next = state.fields[ Math.min( index, state.fields.length - 1 ) ];
		if ( next ) {
			focusField( next.id );
		}
	}

	function moveField( from, to ) {
		if ( to < 0 || to >= state.fields.length || from === to ) {
			return;
		}
		var moved = state.fields.splice( from, 1 )[ 0 ];
		state.fields.splice( to, 0, moved );
		markDirty();
		render();
	}

	function focusField( id ) {
		var toggle = list.querySelector( '[data-field-id="' + id + '"] .glixform-field-toggle' );
		if ( toggle ) {
			toggle.focus();
			toggle.scrollIntoView( { block: 'nearest' } );
		}
	}

	/* ---------- option editors ---------- */

	function optionInput( field, option, card ) {
		var id = 'glixform-opt-' + field.id + '-' + option;
		var value = field[ option ];
		var input;

		if ( option === 'required' ) {
			return el( 'p', { className: 'glixform-option' }, [
				el( 'label', {}, [
					el( 'input', {
						type: 'checkbox',
						checked: !! value,
						onchange: function ( e ) {
							field.required = e.target.checked;
							markDirty();
							updateHeader( card, field );
						},
					} ),
					' ' + i18n.required,
				] ),
			] );
		}

		if ( option === 'choices' ) {
			return choicesEditor( field );
		}

		if ( option === 'description' || ( option === 'default_value' && field.type === 'textarea' ) ) {
			input = el( 'textarea', { id: id, rows: 2, className: 'large-text' } );
			input.value = value || '';
		} else {
			input = el( 'input', {
				id: id,
				type: [ 'min', 'max', 'step', 'max_length' ].indexOf( option ) !== -1 ? 'number' : 'text',
				className: 'large-text',
				min: option === 'max_length' ? 0 : null,
				step: option === 'max_length' ? 1 : ( [ 'min', 'max', 'step' ].indexOf( option ) !== -1 ? 'any' : null ),
			} );
			input.value = value === undefined || value === null ? '' : value;
		}

		input.addEventListener( 'input', function ( e ) {
			field[ option ] = e.target.value;
			markDirty();
			if ( option === 'label' ) {
				updateHeader( card, field );
			}
		} );

		return el( 'p', { className: 'glixform-option' }, [ el( 'label', { for: id, text: i18n[ option ] || option } ), input ] );
	}

	function choicesEditor( field ) {
		var multiple = field.type === 'checkbox';
		var wrap = el( 'div', { className: 'glixform-option glixform-choices-editor' } );
		var rows = el( 'ul' );

		field.choices = field.choices || [];

		field.choices.forEach( function ( choice, index ) {
			var labelInput = el( 'input', {
				type: 'text',
				className: 'regular-text',
				'aria-label': i18n.choices + ' ' + ( index + 1 ),
				oninput: function ( e ) {
					choice.label = e.target.value;
					markDirty();
				},
			} );
			labelInput.value = choice.label;

			var defaultInput = el( 'input', {
				type: multiple ? 'checkbox' : 'radio',
				name: 'glixform-default-' + field.id,
				checked: !! choice.default,
				title: i18n.defaultChoice,
				'aria-label': i18n.defaultChoice,
				onclick: function ( e ) {
					if ( ! multiple ) {
						// Radio-style: clicking the selected default again clears it.
						var wasDefault = choice.default;
						field.choices.forEach( function ( c ) {
							c.default = false;
						} );
						choice.default = ! wasDefault;
						e.target.checked = choice.default;
					} else {
						choice.default = e.target.checked;
					}
					markDirty();
				},
			} );

			rows.appendChild(
				el( 'li', {}, [
					defaultInput,
					labelInput,
					el( 'button', {
						type: 'button',
						className: 'button-link glixform-icon-button',
						'aria-label': i18n.removeChoice,
						title: i18n.removeChoice,
						onclick: function () {
							field.choices.splice( index, 1 );
							markDirty();
							render();
						},
					}, [ el( 'span', { className: 'dashicons dashicons-minus', 'aria-hidden': 'true' } ) ] ),
				] )
			);
		} );

		wrap.appendChild( el( 'span', { className: 'glixform-option-label', text: i18n.choices } ) );
		wrap.appendChild( rows );
		wrap.appendChild(
			el( 'button', {
				type: 'button',
				className: 'button',
				onclick: function () {
					field.choices.push( { label: i18n.newChoice + ' ' + ( field.choices.length + 1 ), default: false } );
					markDirty();
					render();
					var inputs = list.querySelectorAll( '[data-field-id="' + field.id + '"] .glixform-choices-editor input[type=text]' );
					if ( inputs.length ) {
						inputs[ inputs.length - 1 ].select();
					}
				},
			}, [ i18n.addChoice ] )
		);
		return wrap;
	}

	/* ---------- rendering ---------- */

	function updateHeader( card, field ) {
		card.querySelector( '.glixform-field-label' ).textContent = field.label || types[ field.type ].name;
		card.querySelector( '.glixform-field-required' ).hidden = ! field.required;
	}

	function iconButton( icon, label, onClick, disabled ) {
		return el( 'button', {
			type: 'button',
			className: 'button-link glixform-icon-button',
			'aria-label': label,
			title: label,
			disabled: !! disabled,
			onclick: onClick,
		}, [ el( 'span', { className: 'dashicons ' + icon, 'aria-hidden': 'true' } ) ] );
	}

	function fieldCard( field, index ) {
		var type = types[ field.type ];
		var isOpen = state.open === field.id;
		var bodyId = 'glixform-field-body-' + field.id;

		var card = el( 'li', {
			className: 'glixform-field-card' + ( isOpen ? ' is-open' : '' ),
			'data-field-id': field.id,
		} );

		var handle = el( 'span', {
			className: 'glixform-drag-handle dashicons dashicons-menu',
			title: i18n.dragHandle,
			'aria-hidden': 'true',
			onmousedown: function () {
				card.setAttribute( 'draggable', 'true' );
			},
		} );

		var header = el( 'div', { className: 'glixform-field-header' }, [
			handle,
			el( 'button', {
				type: 'button',
				className: 'glixform-field-toggle',
				'aria-expanded': isOpen ? 'true' : 'false',
				'aria-controls': bodyId,
				onclick: function () {
					state.open = isOpen ? null : field.id;
					render();
					focusField( field.id );
				},
			}, [
				el( 'span', { className: 'dashicons ' + type.icon, 'aria-hidden': 'true' } ),
				el( 'span', { className: 'glixform-field-label', text: field.label || type.name } ),
				el( 'span', { className: 'glixform-field-required', text: '*', hidden: ! field.required } ),
				el( 'span', { className: 'glixform-field-meta', text: type.name + ' · ' + i18n.fieldId + ' ' + field.id } ),
			] ),
			el( 'span', { className: 'glixform-field-actions' }, [
				iconButton( 'dashicons-arrow-up-alt2', i18n.moveUp, function () {
					moveField( index, index - 1 );
					focusField( field.id );
				}, index === 0 ),
				iconButton( 'dashicons-arrow-down-alt2', i18n.moveDown, function () {
					moveField( index, index + 1 );
					focusField( field.id );
				}, index === state.fields.length - 1 ),
				iconButton( 'dashicons-admin-page', i18n.duplicate, function () {
					duplicateField( field.id );
				} ),
				iconButton( 'dashicons-trash', i18n.delete, function () {
					deleteField( field.id );
				} ),
			] ),
		] );

		card.appendChild( header );

		var body = el( 'div', { className: 'glixform-field-body', id: bodyId, hidden: ! isOpen } );
		if ( isOpen ) {
			type.options.forEach( function ( option ) {
				body.appendChild( optionInput( field, option, card ) );
			} );
		}
		card.appendChild( body );

		// HTML5 drag and drop (the arrow buttons cover keyboard users).
		card.addEventListener( 'dragstart', function ( e ) {
			dragIndex = index;
			card.classList.add( 'is-dragging' );
			e.dataTransfer.effectAllowed = 'move';
			e.dataTransfer.setData( 'text/plain', String( field.id ) );
		} );
		card.addEventListener( 'dragend', function () {
			card.removeAttribute( 'draggable' );
			card.classList.remove( 'is-dragging' );
			dragIndex = null;
		} );
		card.addEventListener( 'dragover', function ( e ) {
			if ( dragIndex !== null ) {
				e.preventDefault();
				card.classList.add( 'is-drop-target' );
			}
		} );
		card.addEventListener( 'dragleave', function () {
			card.classList.remove( 'is-drop-target' );
		} );
		card.addEventListener( 'drop', function ( e ) {
			e.preventDefault();
			card.classList.remove( 'is-drop-target' );
			if ( dragIndex !== null ) {
				moveField( dragIndex, index );
			}
		} );

		return card;
	}

	function render() {
		list.innerHTML = '';
		state.fields.forEach( function ( field, index ) {
			list.appendChild( fieldCard( field, index ) );
		} );
		empty.textContent = i18n.noFields;
		empty.hidden = state.fields.length > 0;
	}

	function renderPalette() {
		config.types.forEach( function ( type ) {
			palette.appendChild(
				el( 'button', {
					type: 'button',
					className: 'button glixform-palette-button',
					onclick: function () {
						addField( type.type );
					},
				}, [ el( 'span', { className: 'dashicons ' + type.icon, 'aria-hidden': 'true' } ), type.name ] )
			);
		} );
	}

	/* ---------- tabs ---------- */

	function initTabs() {
		var tabs = document.querySelectorAll( '.glixform-tabs [data-tab]' );
		tabs.forEach( function ( tab ) {
			tab.addEventListener( 'click', function () {
				tabs.forEach( function ( other ) {
					var active = other === tab;
					other.classList.toggle( 'nav-tab-active', active );
					other.setAttribute( 'aria-selected', active ? 'true' : 'false' );
					document.getElementById( other.getAttribute( 'aria-controls' ) ).hidden = ! active;
				} );
				document.getElementById( 'glixform-active-tab' ).value = tab.getAttribute( 'data-tab' );
			} );
		} );
	}

	function initConfirmationToggle() {
		var radios = form.querySelectorAll( 'input[name="settings[confirmation][type]"]' );
		function sync() {
			var current = form.querySelector( 'input[name="settings[confirmation][type]"]:checked' );
			var value = current ? current.value : 'message';
			form.querySelectorAll( '[data-confirmation]' ).forEach( function ( row ) {
				row.hidden = row.getAttribute( 'data-confirmation' ) !== value;
			} );
		}
		radios.forEach( function ( radio ) {
			radio.addEventListener( 'change', sync );
		} );
		sync();
	}

	/* ---------- save ---------- */

	form.addEventListener( 'input', function ( e ) {
		if ( ! list.contains( e.target ) ) {
			markDirty();
		}
	} );

	form.addEventListener( 'submit', function () {
		document.getElementById( 'glixform-form-fields' ).value = JSON.stringify( state.fields );
		document.getElementById( 'glixform-next-field-id' ).value = state.nextId;
		state.dirty = false;
	} );

	window.addEventListener( 'beforeunload', function ( e ) {
		if ( state.dirty ) {
			e.preventDefault();
			e.returnValue = i18n.unsaved;
		}
	} );

	renderPalette();
	initTabs();
	initConfirmationToggle();
	render();
} )();

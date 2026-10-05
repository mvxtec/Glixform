/**
 * Glixform front end: client-side validation and AJAX submission.
 * Without JavaScript the form still posts normally and is validated on the server.
 */
( function () {
	'use strict';

	var settings = window.glixformSettings || { i18n: {} };
	var i18n = settings.i18n;

	function fieldWrappers( form ) {
		return form.querySelectorAll( '.glixform-field[data-field-id]' );
	}

	function setError( wrapper, message ) {
		var errorEl = wrapper.querySelector( '.glixform-error' );
		var inputs = wrapper.querySelectorAll( 'input, select, textarea' );
		var describedBy;

		wrapper.classList.toggle( 'glixform-has-error', !! message );
		if ( errorEl ) {
			errorEl.textContent = message || '';
			errorEl.hidden = ! message;
		}

		inputs.forEach( function ( input ) {
			if ( message ) {
				input.setAttribute( 'aria-invalid', 'true' );
			} else {
				input.removeAttribute( 'aria-invalid' );
			}
		} );

		// Link the error to the control (or fieldset) for screen readers.
		var target = wrapper.querySelector( 'fieldset' ) || inputs[ 0 ];
		if ( target && errorEl ) {
			describedBy = ( target.getAttribute( 'aria-describedby' ) || '' ).split( ' ' ).filter( function ( id ) {
				return id && id !== errorEl.id;
			} );
			if ( message ) {
				describedBy.push( errorEl.id );
			}
			if ( describedBy.length ) {
				target.setAttribute( 'aria-describedby', describedBy.join( ' ' ) );
			} else {
				target.removeAttribute( 'aria-describedby' );
			}
		}
	}

	function validateField( wrapper ) {
		var required = wrapper.classList.contains( 'glixform-field-required' );
		var group = wrapper.querySelectorAll( 'input[type=radio], input[type=checkbox]' );
		var input;

		if ( group.length ) {
			var checked = Array.prototype.some.call( group, function ( el ) {
				return el.checked;
			} );
			return required && ! checked ? i18n.required : '';
		}

		input = wrapper.querySelector( 'input, select, textarea' );
		if ( ! input ) {
			return '';
		}
		var value = input.value.trim();

		if ( required && value === '' ) {
			return i18n.required;
		}
		if ( value === '' ) {
			return '';
		}
		if ( input.type === 'email' && ! /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test( value ) ) {
			return i18n.email;
		}
		if ( input.validity && ! input.validity.valid ) {
			return input.validationMessage || i18n.number;
		}
		return '';
	}

	function validateForm( form ) {
		var firstInvalid = null;
		fieldWrappers( form ).forEach( function ( wrapper ) {
			var message = validateField( wrapper );
			setError( wrapper, message );
			if ( message && ! firstInvalid ) {
				firstInvalid = wrapper;
			}
		} );
		return firstInvalid;
	}

	function focusField( wrapper ) {
		var input = wrapper.querySelector( 'input, select, textarea' );
		if ( input ) {
			input.focus();
		}
	}

	function showNotice( form, message ) {
		var notice = form.querySelector( '.glixform-notice' );
		if ( ! notice ) {
			return;
		}
		notice.textContent = message || '';
		notice.hidden = ! message;
	}

	function showServerErrors( form, errors ) {
		var first = null;
		fieldWrappers( form ).forEach( function ( wrapper ) {
			var message = errors[ wrapper.getAttribute( 'data-field-id' ) ] || '';
			setError( wrapper, message );
			if ( message && ! first ) {
				first = wrapper;
			}
		} );
		if ( first ) {
			focusField( first );
		}
	}

	function showConfirmation( form, html ) {
		var container = form.parentNode;
		var box = document.createElement( 'div' );
		box.className = 'glixform-confirmation';
		box.setAttribute( 'role', 'status' );
		box.setAttribute( 'tabindex', '-1' );
		box.innerHTML = html; // Sanitized with wp_kses_post() on the server.
		container.replaceChild( box, form );
		box.focus();
	}

	function onSubmit( event ) {
		var form = event.target;
		var button = form.querySelector( '.glixform-button' );
		var invalid = validateForm( form );

		event.preventDefault();

		if ( invalid ) {
			showNotice( form, i18n.fixErrors );
			focusField( invalid );
			return;
		}

		showNotice( form, '' );
		var label = button.textContent;
		button.disabled = true;
		button.textContent = i18n.sending;
		form.setAttribute( 'aria-busy', 'true' );

		fetch( settings.ajaxUrl, {
			method: 'POST',
			body: new FormData( form ),
			credentials: 'same-origin',
		} )
			.then( function ( response ) {
				return response.json();
			} )
			.then( function ( json ) {
				if ( json.success ) {
					if ( json.data.type === 'redirect' && json.data.url ) {
						window.location.href = json.data.url;
						return;
					}
					showConfirmation( form, json.data.message );
					return;
				}
				var data = json.data || {};
				showNotice( form, data.message || i18n.networkErr );
				showServerErrors( form, data.errors || {} );
			} )
			.catch( function () {
				showNotice( form, i18n.networkErr );
			} )
			.finally( function () {
				button.disabled = false;
				button.textContent = label;
				form.removeAttribute( 'aria-busy' );
			} );
	}

	function init() {
		document.querySelectorAll( 'form.glixform' ).forEach( function ( form ) {
			if ( form.dataset.glixformReady ) {
				return;
			}
			form.dataset.glixformReady = '1';
			form.setAttribute( 'novalidate', 'novalidate' );
			form.addEventListener( 'submit', onSubmit );

			// Clear a field's error as soon as it becomes valid. Listening to "input"
			// (not only "change", which fires on blur) avoids a layout jump while the
			// visitor is clicking the next field.
			var clearIfValid = function ( event ) {
				var wrapper = event.target.closest( '.glixform-field' );
				if ( wrapper && wrapper.classList.contains( 'glixform-has-error' ) && ! validateField( wrapper ) ) {
					setError( wrapper, '' );
				}
			};
			form.addEventListener( 'input', clearIfValid );
			form.addEventListener( 'change', clearIfValid );
		} );

		var confirmation = document.querySelector( '.glixform-confirmation' );
		if ( confirmation ) {
			confirmation.focus();
		}
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
} )();

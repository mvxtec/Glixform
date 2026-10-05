/**
 * Glixform front end: conditional logic, multi-page navigation, client-side
 * validation and AJAX submission. Without JavaScript the form still posts
 * normally and the server does all of this itself.
 *
 * The conditional logic here mirrors src/Forms/ConditionalLogic.php; keep both in sync.
 */
( function () {
	'use strict';

	var settings = window.glixformSettings || { i18n: {} };
	var i18n = settings.i18n || {};

	/* ------------------------------------------------------------------ *
	 * Conditional logic
	 * ------------------------------------------------------------------ */

	var NUMERIC = /^[+-]?(\d+\.?\d*|\.\d+)([eE][+-]?\d+)?$/;

	function normalize( value ) {
		return String( value == null ? '' : value ).trim().toLowerCase();
	}

	function compare( operator, actual, expected ) {
		switch ( operator ) {
			case 'is':
				return actual === expected;
			case 'is_not':
				return actual !== expected;
			case 'empty':
				return actual === '';
			case 'not_empty':
				return actual !== '';
			case 'contains':
				return expected !== '' && actual.indexOf( expected ) !== -1;
			case 'not_contains':
				return expected === '' || actual.indexOf( expected ) === -1;
			case 'starts_with':
				return expected !== '' && actual.indexOf( expected ) === 0;
			case 'ends_with':
				return expected !== '' && actual.length >= expected.length && actual.slice( -expected.length ) === expected;
			case 'greater_than':
			case 'less_than':
				if ( actual === '' || expected === '' ) {
					return false;
				}
				var cmp;
				if ( NUMERIC.test( actual ) && NUMERIC.test( expected ) ) {
					cmp = parseFloat( actual ) - parseFloat( expected );
				} else {
					cmp = actual < expected ? -1 : ( actual > expected ? 1 : 0 );
				}
				return operator === 'greater_than' ? cmp > 0 : cmp < 0;
		}
		return false;
	}

	function ruleMatches( rule, value ) {
		var expected = normalize( rule.value );
		if ( Array.isArray( value ) ) {
			var items = value.map( normalize ).filter( function ( v ) {
				return v !== '';
			} );
			switch ( rule.operator ) {
				case 'empty':
					return items.length === 0;
				case 'not_empty':
					return items.length > 0;
				case 'is':
					return items.indexOf( expected ) !== -1;
				case 'is_not':
					return items.indexOf( expected ) === -1;
				case 'not_contains':
					return ! ruleMatches( { operator: 'contains', value: rule.value }, value );
				default:
					return items.some( function ( item ) {
						return compare( rule.operator, item, expected );
					} );
			}
		}
		return compare( rule.operator, normalize( value ), expected );
	}

	function evaluate( condition, values ) {
		if ( ! condition || ! condition.enabled || ! condition.rules || ! condition.rules.length ) {
			return true;
		}
		var any = condition.logic === 'any';
		var match = any ? false : true;
		for ( var i = 0; i < condition.rules.length; i++ ) {
			var rule = condition.rules[ i ];
			var ok = ruleMatches( rule, values[ rule.field ] !== undefined ? values[ rule.field ] : '' );
			if ( any && ok ) {
				match = true;
				break;
			}
			if ( ! any && ! ok ) {
				match = false;
				break;
			}
		}
		return condition.action === 'hide' ? ! match : match;
	}

	/** Value of a field as the server's logic_value() sees it. */
	function fieldValue( wrapper ) {
		var type = wrapper.getAttribute( 'data-field-type' );
		var inputs = wrapper.querySelectorAll( 'input, select, textarea' );
		if ( ! inputs.length ) {
			return '';
		}
		if ( type === 'checkbox' ) {
			return Array.prototype.filter.call( inputs, function ( el ) {
				return el.checked;
			} ).map( function ( el ) {
				return el.value;
			} );
		}
		if ( type === 'radio' || type === 'rating' ) {
			var checked = wrapper.querySelector( 'input:checked' );
			return checked ? checked.value : '';
		}
		if ( type === 'gdpr' ) {
			var box = wrapper.querySelector( 'input[type=checkbox]' );
			var label = wrapper.querySelector( 'li label' );
			return box && box.checked ? ( label ? label.textContent : '1' ) : '';
		}
		if ( type === 'file' ) {
			var input = inputs[ 0 ];
			return input.files ? Array.prototype.map.call( input.files, function ( f ) {
				return f.name;
			} ).join( ', ' ) : '';
		}
		if ( wrapper.querySelector( '.glixform-parts' ) ) {
			return Array.prototype.map.call( inputs, function ( el ) {
				return el.value.trim();
			} ).filter( Boolean ).join( ' ' );
		}
		return inputs[ 0 ].value;
	}

	function inputWrappers( form ) {
		return Array.prototype.filter.call( form.querySelectorAll( '.glixform-field[data-field-id]' ), function ( w ) {
			return [ 'divider', 'html' ].indexOf( w.getAttribute( 'data-field-type' ) ) === -1;
		} );
	}

	function setHidden( wrapper, hidden ) {
		if ( wrapper.hidden === hidden ) {
			return;
		}
		wrapper.hidden = hidden;
		wrapper.classList.toggle( 'glixform-logic-hidden', hidden );
		wrapper.querySelectorAll( 'input, select, textarea' ).forEach( function ( el ) {
			el.disabled = hidden;
		} );
		if ( hidden ) {
			setError( wrapper, '' );
		}
	}

	function applyLogic( form ) {
		var values = {};
		inputWrappers( form ).forEach( function ( w ) {
			values[ w.getAttribute( 'data-field-id' ) ] = fieldValue( w );
		} );
		form.querySelectorAll( '.glixform-field[data-field-id]' ).forEach( function ( wrapper ) {
			var raw = wrapper.getAttribute( 'data-conditional' );
			if ( ! raw ) {
				return;
			}
			var condition;
			try {
				condition = JSON.parse( raw );
			} catch ( e ) {
				return;
			}
			var show = evaluate( condition, values );
			setHidden( wrapper, ! show );
			if ( ! show ) {
				var id = wrapper.getAttribute( 'data-field-id' );
				values[ id ] = Array.isArray( values[ id ] ) ? [] : '';
			}
		} );
	}

	/* ------------------------------------------------------------------ *
	 * Validation
	 * ------------------------------------------------------------------ */

	function setError( wrapper, message ) {
		var errorEl = wrapper.querySelector( '.glixform-error' );
		var inputs = wrapper.querySelectorAll( 'input, select, textarea' );

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

		var target = wrapper.querySelector( 'fieldset' ) || inputs[ 0 ];
		if ( target && errorEl ) {
			var describedBy = ( target.getAttribute( 'aria-describedby' ) || '' ).split( ' ' ).filter( function ( id ) {
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

	function validateFile( wrapper, input ) {
		var box = wrapper.querySelector( '.glixform-file' );
		var files = input.files ? Array.prototype.slice.call( input.files ) : [];
		var required = wrapper.classList.contains( 'glixform-field-required' );
		if ( ! files.length ) {
			return required ? i18n.fileRequired : '';
		}
		var maxFiles = parseInt( box.getAttribute( 'data-max-files' ), 10 ) || 1;
		var maxBytes = parseInt( box.getAttribute( 'data-max-bytes' ), 10 ) || 0;
		var exts = ( box.getAttribute( 'data-extensions' ) || '' ).split( ',' ).filter( Boolean );
		if ( files.length > maxFiles ) {
			return ( i18n.tooManyFiles || '' ).replace( '%d', maxFiles );
		}
		for ( var i = 0; i < files.length; i++ ) {
			var ext = files[ i ].name.split( '.' ).pop().toLowerCase();
			if ( exts.length && exts.indexOf( ext ) === -1 ) {
				return ( i18n.fileType || '' ).replace( '%s', exts.join( ', ' ) );
			}
			if ( maxBytes && files[ i ].size > maxBytes ) {
				return ( i18n.fileSize || '' ).replace( '%s', Math.round( maxBytes / 1048576 ) + ' MB' );
			}
		}
		return '';
	}

	function validateField( wrapper ) {
		if ( wrapper.hidden ) {
			return '';
		}
		var type = wrapper.getAttribute( 'data-field-type' );
		var required = wrapper.classList.contains( 'glixform-field-required' );

		if ( type === 'file' ) {
			return validateFile( wrapper, wrapper.querySelector( 'input[type=file]' ) );
		}

		var group = wrapper.querySelectorAll( 'input[type=radio], input[type=checkbox]' );
		if ( group.length ) {
			var anyChecked = Array.prototype.some.call( group, function ( el ) {
				return el.checked;
			} );
			if ( type === 'gdpr' ) {
				return anyChecked ? '' : i18n.consent;
			}
			return required && ! anyChecked ? i18n.required : '';
		}

		var inputs = wrapper.querySelectorAll( 'input, select, textarea' );
		if ( ! inputs.length ) {
			return '';
		}

		// Composite fields: every required part must be filled in.
		if ( wrapper.querySelector( '.glixform-parts' ) ) {
			var missing = Array.prototype.some.call( inputs, function ( el ) {
				return el.hasAttribute( 'data-part-required' ) && el.value.trim() === '';
			} );
			return missing ? i18n.parts : '';
		}

		var input = inputs[ 0 ];
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
		if ( input.type === 'tel' ) {
			var digits = value.replace( /\D/g, '' ).length;
			if ( ! /^\+?[0-9\s().\-]+$/.test( value ) || digits < 7 || digits > 15 ) {
				return i18n.phone;
			}
		}
		if ( input.type === 'url' && ! /^(https?:\/\/)?[^\s/$.?#]+\.[^\s]+$/i.test( value ) ) {
			return i18n.url;
		}
		if ( input.validity && ! input.validity.valid ) {
			return input.validationMessage || i18n.invalid;
		}
		return '';
	}

	function validateScope( scope ) {
		var first = null;
		scope.querySelectorAll( '.glixform-field[data-field-id]' ).forEach( function ( wrapper ) {
			var message = validateField( wrapper );
			setError( wrapper, message );
			if ( message && ! first ) {
				first = wrapper;
			}
		} );
		return first;
	}

	function focusField( wrapper ) {
		var input = wrapper.querySelector( 'input:not([type=hidden]), select, textarea' );
		if ( input ) {
			input.focus( { preventScroll: true } );
		}
		wrapper.scrollIntoView( { behavior: 'smooth', block: 'center' } );
	}

	function showNotice( form, message ) {
		var notice = form.querySelector( '.glixform-notice' );
		if ( ! notice ) {
			return;
		}
		notice.textContent = message || '';
		notice.hidden = ! message;
	}

	/* ------------------------------------------------------------------ *
	 * Pages
	 * ------------------------------------------------------------------ */

	function pages( form ) {
		return Array.prototype.slice.call( form.querySelectorAll( '.glixform-page' ) );
	}

	function pageHasVisibleFields( page ) {
		return Array.prototype.some.call( page.querySelectorAll( '.glixform-field' ), function ( w ) {
			return ! w.hidden && w.getAttribute( 'data-field-type' ) !== 'hidden';
		} );
	}

	function nextIndex( form, from, step ) {
		var list = pages( form );
		for ( var i = from + step; i >= 0 && i < list.length; i += step ) {
			if ( pageHasVisibleFields( list[ i ] ) ) {
				return i;
			}
		}
		return -1;
	}

	function showPage( form, index, focus ) {
		var list = pages( form );
		if ( list.length < 2 ) {
			return;
		}
		var isLast = nextIndex( form, index, 1 ) === -1;
		list.forEach( function ( page, i ) {
			page.hidden = i !== index;
			var nav = page.querySelector( '.glixform-page-nav' );
			if ( nav ) {
				nav.hidden = isLast;
			}
		} );
		form.dataset.page = index;

		var submit = form.querySelector( '.glixform-submit' );
		submit.hidden = ! isLast;
		var prevLast = submit.querySelector( '.glixform-prev-last' );
		if ( prevLast ) {
			prevLast.hidden = index === 0;
		}

		var progress = form.querySelector( '.glixform-progress' );
		if ( progress ) {
			progress.hidden = false;
			var total = list.length;
			var label = progress.querySelector( '.glixform-progress-label' );
			if ( label ) {
				label.textContent = label.getAttribute( 'data-template' ).replace( '{current}', index + 1 ).replace( '{total}', total );
				var title = progress.querySelector( '.glixform-progress-title' );
				title.textContent = list[ index ].getAttribute( 'data-title' ) || '';
				progress.querySelector( '.glixform-progress-fill' ).style.width = ( ( index + 1 ) / total * 100 ) + '%';
				progress.querySelector( '.glixform-progress-track' ).setAttribute( 'aria-valuenow', index + 1 );
			}
			progress.querySelectorAll( '.glixform-step' ).forEach( function ( step, i ) {
				step.classList.toggle( 'is-active', i === index );
				step.classList.toggle( 'is-done', i < index );
				if ( i === index ) {
					step.setAttribute( 'aria-current', 'step' );
				} else {
					step.removeAttribute( 'aria-current' );
				}
			} );
		}

		if ( focus ) {
			form.closest( '.glixform-container' ).scrollIntoView( { behavior: 'smooth', block: 'start' } );
			var first = list[ index ].querySelector( '.glixform-field:not([hidden]) input:not([type=hidden]), .glixform-field:not([hidden]) select, .glixform-field:not([hidden]) textarea' );
			if ( first ) {
				first.focus( { preventScroll: true } );
			}
		}
	}

	function currentPage( form ) {
		return parseInt( form.dataset.page || '0', 10 );
	}

	function pageOf( form, wrapper ) {
		return pages( form ).indexOf( wrapper.closest( '.glixform-page' ) );
	}

	/* ------------------------------------------------------------------ *
	 * Submission
	 * ------------------------------------------------------------------ */

	function showServerErrors( form, errors ) {
		var first = null;
		form.querySelectorAll( '.glixform-field[data-field-id]' ).forEach( function ( wrapper ) {
			var message = errors[ wrapper.getAttribute( 'data-field-id' ) ] || '';
			setError( wrapper, message );
			if ( message && ! first ) {
				first = wrapper;
			}
		} );
		if ( first ) {
			if ( pages( form ).length > 1 ) {
				showPage( form, pageOf( form, first ), false );
			}
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
		container.scrollIntoView( { behavior: 'smooth', block: 'start' } );
	}

	function resetCaptcha() {
		try {
			if ( window.turnstile ) {
				window.turnstile.reset();
			}
			if ( window.hcaptcha ) {
				window.hcaptcha.reset();
			}
			if ( window.grecaptcha && window.grecaptcha.reset && ! document.querySelector( '.glixform-recaptcha-v3' ) ) {
				window.grecaptcha.reset();
			}
		} catch ( e ) {}
	}

	function recaptchaV3( form ) {
		var input = form.querySelector( '.glixform-recaptcha-v3' );
		if ( ! input || ! window.grecaptcha ) {
			return Promise.resolve();
		}
		return new Promise( function ( resolve ) {
			window.grecaptcha.ready( function () {
				window.grecaptcha.execute( input.getAttribute( 'data-sitekey' ), { action: 'glixform' } ).then( function ( token ) {
					input.value = token;
					resolve();
				}, resolve );
			} );
		} );
	}

	function setBusy( form, busy ) {
		var button = form.querySelector( 'button[type=submit]' );
		var label = button.querySelector( '.glixform-button-label' );
		if ( busy ) {
			button.dataset.label = label.textContent;
			label.textContent = button.getAttribute( 'data-processing' ) || i18n.sending;
			button.disabled = true;
			button.classList.add( 'is-busy' );
			form.setAttribute( 'aria-busy', 'true' );
		} else {
			label.textContent = button.dataset.label || label.textContent;
			button.disabled = false;
			button.classList.remove( 'is-busy' );
			form.removeAttribute( 'aria-busy' );
		}
	}

	function onSubmit( event ) {
		var form = event.target;
		event.preventDefault();
		applyLogic( form );

		var invalid = validateScope( form );
		if ( invalid ) {
			showNotice( form, i18n.fixErrors );
			if ( pages( form ).length > 1 ) {
				showPage( form, pageOf( form, invalid ), false );
			}
			focusField( invalid );
			return;
		}

		if ( form.getAttribute( 'data-preview' ) ) {
			showNotice( form, '' );
			var note = form.querySelector( '.glixform-preview-note' ) || document.createElement( 'div' );
			note.className = 'glixform-preview-note';
			note.textContent = i18n.previewNote;
			form.querySelector( '.glixform-submit' ).appendChild( note );
			return;
		}

		showNotice( form, '' );
		setBusy( form, true );

		recaptchaV3( form )
			.then( function () {
				return fetch( settings.ajaxUrl, {
					method: 'POST',
					body: new FormData( form ),
					credentials: 'same-origin',
				} );
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
				resetCaptcha();
				showNotice( form, data.message || i18n.networkErr );
				showServerErrors( form, data.errors || {} );
			} )
			.catch( function () {
				resetCaptcha();
				showNotice( form, i18n.networkErr );
			} )
			.finally( function () {
				if ( form.isConnected ) {
					setBusy( form, false );
				}
			} );
	}

	/* ------------------------------------------------------------------ *
	 * Widgets
	 * ------------------------------------------------------------------ */

	function initRating( form ) {
		form.querySelectorAll( '.glixform-stars' ).forEach( function ( stars ) {
			var labels = Array.prototype.slice.call( stars.querySelectorAll( '.glixform-star' ) );
			var inputs = Array.prototype.slice.call( stars.querySelectorAll( 'input' ) );
			function paint( upto ) {
				labels.forEach( function ( label, i ) {
					label.classList.toggle( 'is-on', i < upto );
				} );
			}
			function checkedCount() {
				var n = 0;
				inputs.forEach( function ( input, i ) {
					if ( input.checked ) {
						n = i + 1;
					}
				} );
				return n;
			}
			labels.forEach( function ( label, i ) {
				label.addEventListener( 'mouseenter', function () {
					paint( i + 1 );
				} );
			} );
			stars.addEventListener( 'mouseleave', function () {
				paint( checkedCount() );
			} );
			stars.addEventListener( 'change', function () {
				paint( checkedCount() );
			} );
			paint( checkedCount() );
		} );
	}

	function initFiles( form ) {
		form.querySelectorAll( '.glixform-file' ).forEach( function ( box ) {
			var input = box.querySelector( 'input[type=file]' );
			var list = document.createElement( 'ul' );
			list.className = 'glixform-file-list';
			box.appendChild( list );
			input.addEventListener( 'change', function () {
				list.innerHTML = '';
				Array.prototype.forEach.call( input.files || [], function ( file ) {
					var li = document.createElement( 'li' );
					li.textContent = file.name + ' · ' + ( file.size < 1048576 ? Math.max( 1, Math.round( file.size / 1024 ) ) + ' KB' : ( file.size / 1048576 ).toFixed( 1 ) + ' MB' );
					list.appendChild( li );
				} );
				box.classList.toggle( 'has-files', !! ( input.files && input.files.length ) );
			} );
			[ 'dragenter', 'dragover' ].forEach( function ( type ) {
				box.addEventListener( type, function () {
					box.classList.add( 'is-dragover' );
				} );
			} );
			[ 'dragleave', 'drop' ].forEach( function ( type ) {
				box.addEventListener( type, function () {
					box.classList.remove( 'is-dragover' );
				} );
			} );
		} );
	}

	/* ------------------------------------------------------------------ *
	 * Init
	 * ------------------------------------------------------------------ */

	function init( root ) {
		( root || document ).querySelectorAll( 'form.glixform' ).forEach( function ( form ) {
			if ( form.dataset.glixformReady ) {
				return;
			}
			form.dataset.glixformReady = '1';
			form.setAttribute( 'novalidate', 'novalidate' );
			form.classList.add( 'glixform-js' );
			form.addEventListener( 'submit', onSubmit );

			var refresh = function ( event ) {
				applyLogic( form );
				// Clear a field's error as soon as it becomes valid. Listening to "input"
				// (not only "change", which fires on blur) avoids a layout jump while the
				// visitor is clicking the next field.
				var wrapper = event && event.target.closest ? event.target.closest( '.glixform-field' ) : null;
				if ( wrapper && wrapper.classList.contains( 'glixform-has-error' ) && ! validateField( wrapper ) ) {
					setError( wrapper, '' );
				}
				if ( pages( form ).length > 1 ) {
					showPage( form, currentPage( form ), false );
				}
			};
			form.addEventListener( 'input', refresh );
			form.addEventListener( 'change', refresh );

			form.addEventListener( 'click', function ( event ) {
				var next = event.target.closest( '.glixform-next' );
				var prev = event.target.closest( '.glixform-prev' );
				if ( ! next && ! prev ) {
					return;
				}
				var index = currentPage( form );
				if ( next ) {
					var invalid = validateScope( pages( form )[ index ] );
					if ( invalid ) {
						focusField( invalid );
						return;
					}
					showNotice( form, '' );
				}
				var target = nextIndex( form, index, next ? 1 : -1 );
				if ( target !== -1 ) {
					showPage( form, target, true );
				}
			} );

			initRating( form );
			initFiles( form );
			applyLogic( form );

			if ( pages( form ).length > 1 ) {
				// After a failed no-JS post, open the page with the first error.
				var firstError = form.querySelector( '.glixform-has-error' );
				showPage( form, firstError ? pageOf( form, firstError ) : 0, false );
			}
		} );

		var confirmation = document.querySelector( '.glixform-confirmation' );
		if ( confirmation ) {
			confirmation.focus();
		}
	}

	window.glixform = { init: init, evaluate: evaluate };

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', function () {
			init();
		} );
	} else {
		init();
	}
} )();

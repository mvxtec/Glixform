/**
 * Glixform → Email: provider presets, automatic port and the test email button.
 */
( function () {
	'use strict';

	var data = window.glixformEmail;
	var form = document.getElementById( 'glixform-email-form' );
	if ( ! data || ! form ) {
		return;
	}

	var i18n = data.i18n;
	var host = document.getElementById( 'glixform-smtp-host' );
	var port = document.getElementById( 'glixform-smtp-port' );
	var user = document.getElementById( 'glixform-smtp-username' );
	var auth = document.getElementById( 'glixform-smtp-auth' );
	var help = document.getElementById( 'glixform-provider-help' );
	var userHint = document.getElementById( 'glixform-username-hint' );
	var passHint = document.getElementById( 'glixform-password-hint' );
	var DEFAULT_PORTS = { none: 25, ssl: 465, tls: 587 };
	var presetHosts = Object.keys( data.providers ).map( function ( key ) {
		return data.providers[ key ].host;
	} );

	function checked( name ) {
		var el = form.querySelector( 'input[name="glixform_email[' + name + ']"]:checked' );
		return el ? el.value : '';
	}

	function setEncryption( value ) {
		var radio = form.querySelector( 'input[name="glixform_email[encryption]"][value="' + value + '"]' );
		if ( radio ) {
			radio.checked = true;
		}
	}

	function showHelp( provider ) {
		var preset = data.providers[ provider ];
		help.textContent = preset ? preset.help : '';
		if ( preset && preset.docs ) {
			var link = document.createElement( 'a' );
			link.href = preset.docs;
			link.target = '_blank';
			link.rel = 'noopener noreferrer';
			link.textContent = ' ' + i18n.docs + ' ↗';
			help.appendChild( link );
		}
		userHint.textContent = preset ? preset.username_hint : '';
		if ( passHint ) {
			passHint.textContent = preset ? preset.password_hint : '';
		}
	}

	function toggle() {
		var provider = checked( 'provider' );
		form.querySelectorAll( '[data-smtp-only]' ).forEach( function ( el ) {
			el.hidden = provider === 'default';
		} );
		form.querySelectorAll( '[data-auth-only]' ).forEach( function ( el ) {
			el.hidden = ! auth.checked;
		} );
		form.querySelectorAll( '.glixform-provider' ).forEach( function ( label ) {
			label.classList.toggle( 'is-selected', label.querySelector( 'input' ).checked );
		} );
		showHelp( provider );
	}

	// Choosing a provider fills in its server, unless the owner typed a custom host.
	form.addEventListener( 'change', function ( event ) {
		var target = event.target;
		if ( target.name === 'glixform_email[provider]' ) {
			var preset = data.providers[ target.value ];
			if ( preset && ( host.value === '' || presetHosts.indexOf( host.value ) !== -1 ) ) {
				host.value = preset.host;
				setEncryption( preset.encryption );
				port.value = preset.port;
				if ( preset.username && ! user.value ) {
					user.value = preset.username;
				}
			}
		}
		if ( target.name === 'glixform_email[encryption]' ) {
			// Only replace a port that is one of the standard ones.
			var current = parseInt( port.value, 10 );
			if ( ! current || [ 25, 465, 587 ].indexOf( current ) !== -1 ) {
				port.value = DEFAULT_PORTS[ target.value ];
			}
		}
		toggle();
	} );

	toggle();

	// Remember unsaved changes so the test is not sent with old settings.
	var dirty = false;
	form.addEventListener( 'input', function () {
		dirty = true;
	} );
	form.addEventListener( 'change', function () {
		dirty = true;
	} );

	// Test email.
	var button = document.getElementById( 'glixform-test-send' );
	var to = document.getElementById( 'glixform-test-to' );
	var result = document.getElementById( 'glixform-test-result' );

	function show( ok, json ) {
		result.hidden = false;
		result.className = 'glixform-test-result ' + ( ok ? 'is-success' : 'is-error' );
		result.innerHTML = '';
		var message = document.createElement( 'p' );
		message.className = 'glixform-test-message';
		message.textContent = json.message || i18n.failed;
		result.appendChild( message );
		if ( json.hint ) {
			var hint = document.createElement( 'p' );
			hint.className = 'glixform-test-hint';
			hint.textContent = json.hint;
			result.appendChild( hint );
		}
		if ( json.server && json.server.length ) {
			var details = document.createElement( 'details' );
			var summary = document.createElement( 'summary' );
			summary.textContent = i18n.server;
			var pre = document.createElement( 'pre' );
			pre.textContent = json.server.join( '\n' );
			details.appendChild( summary );
			details.appendChild( pre );
			result.appendChild( details );
		}
	}

	button.addEventListener( 'click', function () {
		if ( dirty ) {
			show( false, { message: i18n.unsaved, hint: i18n.unsavedHint } );
			form.querySelector( '#submit' ).focus();
			return;
		}
		var body = new FormData();
		body.append( 'action', 'glixform_email_test' );
		body.append( 'nonce', data.nonce );
		body.append( 'to', to.value );
		button.disabled = true;
		button.textContent = i18n.sending;
		result.hidden = true;

		fetch( data.ajaxUrl, { method: 'POST', body: body, credentials: 'same-origin' } )
			.then( function ( response ) {
				return response.json();
			} )
			.then( function ( json ) {
				show( !! json.success, json.data || {} );
			} )
			.catch( function () {
				show( false, { message: i18n.failed } );
			} )
			.finally( function () {
				button.disabled = false;
				button.textContent = i18n.send;
			} );
	} );
} )();

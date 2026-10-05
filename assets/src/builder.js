/**
 * Entry point for the Glixform form builder (built to build/builder.js).
 */
import { createRoot, render } from '@wordpress/element';
import App from './builder/App';
import './builder/style.scss';

function mount() {
	const node = document.getElementById( 'glixform-builder-root' );
	if ( ! node ) {
		return;
	}
	if ( createRoot ) {
		createRoot( node ).render( <App /> );
	} else {
		render( <App />, node );
	}
}

if ( document.readyState === 'loading' ) {
	document.addEventListener( 'DOMContentLoaded', mount );
} else {
	mount();
}

/**
 * Live preview: the server renders the unsaved form exactly like the front
 * end, and it is shown in an isolated iframe running the real frontend.js,
 * so conditional logic and page navigation can be tried out. Clicking a
 * field in the preview selects it in the builder.
 */
import { useEffect, useRef, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';
import { Button, Spinner } from '@wordpress/components';
import { desktop, mobile } from '@wordpress/icons';
import { config } from '../config';

function skeleton() {
	const f = config.frontend;
	return `<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<link rel="stylesheet" href="${ f.css }">
<style>
html,body{margin:0;background:#fff;}
body{padding:28px 28px 40px;font:16px/1.5 -apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Helvetica,Arial,sans-serif;color:#1f2330;}
.glixform-container{margin:0}
.glixform-field{cursor:pointer;border-radius:12px;outline:2px solid transparent;outline-offset:8px;transition:outline-color .15s}
.glixform-field:hover{outline-color:rgba(109,74,255,.25)}
.glixform-field.gf-selected{outline-color:#6d4aff}
.gf-empty{color:#8a8fa3;text-align:center;padding:60px 0;font-size:15px}
</style>
<script>window.glixformSettings=${ JSON.stringify( f.settings ) };</script>
<script src="${ f.js }"></script></head>
<body><div id="root"></div>
<script>
document.addEventListener('click',function(e){
  var w=e.target.closest('.glixform-field[data-field-id]');
  if(w){parent.postMessage({glixform:'select',id:parseInt(w.getAttribute('data-field-id'),10)},'*');}
},true);
</script></body></html>`;
}

export default function Preview( { title, data, selectedId, onSelect } ) {
	const frame = useRef();
	const [ ready, setReady ] = useState( false );
	const [ loading, setLoading ] = useState( false );
	const [ device, setDevice ] = useState( 'desktop' );
	const [ error, setError ] = useState( '' );
	const [ html, setHtml ] = useState( '' );

	// Fetch rendered HTML, debounced.
	useEffect( () => {
		const controller =
			typeof AbortController !== 'undefined'
				? new AbortController()
				: null;
		const timer = setTimeout( () => {
			setLoading( true );
			apiFetch( {
				path: '/glixform/v1/preview',
				method: 'POST',
				data: { title, data },
				signal: controller ? controller.signal : undefined,
			} )
				.then( ( res ) => {
					setHtml( res.html );
					setError( '' );
				} )
				.catch( ( err ) => {
					if ( err && err.name !== 'AbortError' ) {
						setError(
							__( 'The preview could not be loaded.', 'glixform' )
						);
					}
				} )
				.finally( () => setLoading( false ) );
		}, 350 );
		return () => {
			clearTimeout( timer );
			if ( controller ) {
				controller.abort();
			}
		};
	}, [ title, data ] );

	// Insert HTML into the iframe without reloading it (keeps scroll position).
	useEffect( () => {
		if ( ! ready || ! frame.current ) {
			return;
		}
		const doc = frame.current.contentDocument;
		const win = frame.current.contentWindow;
		const root = doc && doc.getElementById( 'root' );
		if ( ! root ) {
			return;
		}
		const hasFields = data.fields.some( ( f ) => f.type !== 'hidden' );
		root.innerHTML = hasFields
			? html
			: `<div class="gf-empty">${ __(
					'Add fields to see your form here.',
					'glixform'
			  ) }</div>`;
		if ( win.glixform ) {
			win.glixform.init( root );
		}
		highlight();
	}, [ html, ready ] ); // eslint-disable-line react-hooks/exhaustive-deps

	const highlight = () => {
		const doc = frame.current && frame.current.contentDocument;
		if ( ! doc ) {
			return;
		}
		doc.querySelectorAll( '.gf-selected' ).forEach( ( el ) =>
			el.classList.remove( 'gf-selected' )
		);
		if ( selectedId ) {
			const el = doc.querySelector(
				`.glixform-field[data-field-id="${ selectedId }"]`
			);
			if ( el ) {
				el.classList.add( 'gf-selected' );
				// Open the page that contains the field.
				const page = el.closest( '.glixform-page' );
				const form = el.closest( 'form' );
				if (
					page &&
					form &&
					page.hidden &&
					frame.current.contentWindow.glixform
				) {
					form.querySelectorAll( '.glixform-page' ).forEach(
						( p ) => ( p.hidden = p !== page )
					);
				}
				el.scrollIntoView( { block: 'nearest', behavior: 'smooth' } );
			}
		}
	};

	useEffect( highlight, [ selectedId ] ); // eslint-disable-line react-hooks/exhaustive-deps

	useEffect( () => {
		const onMessage = ( e ) => {
			if (
				frame.current &&
				e.source === frame.current.contentWindow &&
				e.data &&
				e.data.glixform === 'select'
			) {
				onSelect( e.data.id );
			}
		};
		window.addEventListener( 'message', onMessage );
		return () => window.removeEventListener( 'message', onMessage );
	}, [ onSelect ] );

	return (
		<div className="gf-preview">
			<div className="gf-preview-bar">
				<span className="gf-preview-title">
					{ __( 'Live preview', 'glixform' ) }
					{ loading && <Spinner /> }
				</span>
				<div
					className="gf-segmented"
					role="group"
					aria-label={ __( 'Preview size', 'glixform' ) }
				>
					<Button
						icon={ desktop }
						label={ __( 'Desktop', 'glixform' ) }
						isPressed={ device === 'desktop' }
						onClick={ () => setDevice( 'desktop' ) }
						size="compact"
					/>
					<Button
						icon={ mobile }
						label={ __( 'Mobile', 'glixform' ) }
						isPressed={ device === 'mobile' }
						onClick={ () => setDevice( 'mobile' ) }
						size="compact"
					/>
				</div>
			</div>
			{ error && <div className="gf-preview-error">{ error }</div> }
			<div className={ 'gf-preview-stage is-' + device }>
				<iframe
					ref={ frame }
					title={ __( 'Form preview', 'glixform' ) }
					srcDoc={ skeleton() }
					onLoad={ () => setReady( true ) }
				/>
			</div>
		</div>
	);
}

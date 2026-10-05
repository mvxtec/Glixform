/**
 * Glixform form builder.
 */
import {
	useCallback,
	useEffect,
	useReducer,
	useRef,
	useState,
} from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';
import { Button, Spinner } from '@wordpress/components';
import {
	Icon,
	arrowLeft,
	undo as undoIcon,
	redo as redoIcon,
	external,
	check,
	copy as copyIcon,
} from '@wordpress/icons';
import { config } from './config';
import { createReducer, initialState } from './store';
import { Palette, FieldOptions } from './components/Sidebar';
import FieldList from './components/FieldList';
import Preview from './components/Preview';
import SettingsPanel from './components/SettingsPanel';
import TemplatePicker from './components/TemplatePicker';

const reducer = createReducer( config.types );

function useToasts() {
	const [ toasts, setToasts ] = useState( [] );
	const push = useCallback( ( message, options = {} ) => {
		const id = Date.now() + Math.random();
		setToasts( ( list ) => [
			...list.slice( -2 ),
			{ id, message, ...options },
		] );
		setTimeout(
			() => setToasts( ( list ) => list.filter( ( t ) => t.id !== id ) ),
			options.duration || 4000
		);
	}, [] );
	const dismiss = ( id ) =>
		setToasts( ( list ) => list.filter( ( t ) => t.id !== id ) );
	return [ toasts, push, dismiss ];
}

function isTyping( target ) {
	return (
		target &&
		( target.isContentEditable ||
			[ 'INPUT', 'TEXTAREA', 'SELECT' ].includes( target.tagName ) )
	);
}

export default function App() {
	const [ picking, setPicking ] = useState( ! config.form );
	const [ state, dispatch ] = useReducer(
		reducer,
		config.form
			? {
					formId: config.form.id,
					title: config.form.title,
					data: config.form.data,
			  }
			: { formId: 0, title: '', data: null },
		initialState
	);
	const [ view, setView ] = useState(
		config.initialView === 'settings' ? 'settings' : 'build'
	);
	const [ sidebarTab, setSidebarTab ] = useState( 'add' );
	const [ saving, setSaving ] = useState( false );
	const [ meta, setMeta ] = useState(
		config.form
			? {
					shortcode: config.form.shortcode,
					previewUrl: config.form.previewUrl,
			  }
			: null
	);
	const [ toasts, toast, dismissToast ] = useToasts();
	const stateRef = useRef( state );
	stateRef.current = state;

	const { title, data } = state.present;
	const selected = data
		? data.fields.find( ( f ) => f.id === state.selectedId )
		: null;

	// Show the options tab whenever a field gets selected.
	useEffect( () => {
		if ( state.selectedId ) {
			setSidebarTab( 'options' );
		}
	}, [ state.selectedId ] );

	const save = useCallback( async () => {
		const current = stateRef.current;
		if ( saving || ! current.present.data ) {
			return;
		}
		setSaving( true );
		const snapshot = current.present;
		try {
			const res = await apiFetch( {
				path: current.formId
					? `/glixform/v1/forms/${ current.formId }`
					: '/glixform/v1/forms',
				method: 'POST',
				data: { title: snapshot.title, data: snapshot.data },
			} );
			const changedSince = stateRef.current.present !== snapshot;
			dispatch( {
				type: 'SAVED',
				formId: res.id,
				title: res.title,
				data: res.data,
				replace: ! changedSince,
				changedSince,
			} );
			setMeta( { shortcode: res.shortcode, previewUrl: res.previewUrl } );
			if ( ! current.formId ) {
				const url = new URL( window.location.href );
				url.searchParams.set( 'form_id', res.id );
				window.history.replaceState( null, '', url.toString() );
			}
			toast( __( 'Form saved', 'glixform' ), { icon: check } );
		} catch ( error ) {
			toast(
				( error && error.message ) ||
					__( 'The form could not be saved.', 'glixform' ),
				{ error: true, duration: 7000 }
			);
		} finally {
			setSaving( false );
		}
	}, [ saving, toast ] );

	// Keyboard shortcuts: save, undo, redo.
	useEffect( () => {
		const onKey = ( e ) => {
			const mod = e.metaKey || e.ctrlKey;
			if ( ! mod || picking ) {
				return;
			}
			const key = e.key.toLowerCase();
			if ( key === 's' ) {
				e.preventDefault();
				save();
			} else if ( key === 'z' && ! isTyping( e.target ) ) {
				e.preventDefault();
				dispatch( { type: e.shiftKey ? 'REDO' : 'UNDO' } );
			} else if ( key === 'y' && ! isTyping( e.target ) ) {
				e.preventDefault();
				dispatch( { type: 'REDO' } );
			}
		};
		window.addEventListener( 'keydown', onKey );
		return () => window.removeEventListener( 'keydown', onKey );
	}, [ save, picking ] );

	// Warn before leaving with unsaved changes.
	useEffect( () => {
		const onUnload = ( e ) => {
			if ( stateRef.current.dirty ) {
				e.preventDefault();
				e.returnValue = '';
			}
		};
		window.addEventListener( 'beforeunload', onUnload );
		return () => window.removeEventListener( 'beforeunload', onUnload );
	}, [] );

	const addField = ( fieldType, index ) => {
		let at = index;
		if ( at === undefined ) {
			const sel = data.fields.findIndex(
				( f ) => f.id === state.selectedId
			);
			at = sel === -1 ? data.fields.length : sel + 1;
		}
		dispatch( { type: 'ADD_FIELD', fieldType, index: at } );
	};

	const wrappedDispatch = ( action ) => {
		dispatch( action );
		if ( action.type === 'DELETE_FIELD' ) {
			toast( __( 'Field deleted', 'glixform' ), {
				action: {
					label: __( 'Undo', 'glixform' ),
					onClick: () => dispatch( { type: 'UNDO' } ),
				},
			} );
		}
	};

	const onSelectFromPreview = useCallback(
		( id ) => dispatch( { type: 'SELECT', id } ),
		[]
	);

	if ( picking ) {
		return (
			<div className="gf-app">
				<TemplatePicker
					onPick={ ( template, name ) => {
						dispatch( {
							type: 'LOAD',
							title: name,
							data: template.data,
						} );
						// Start history fresh; a template is unsaved work until the first save.
						dispatch( { type: 'RESET_HISTORY', dirty: true } );
						setPicking( false );
					} }
				/>
			</div>
		);
	}

	let status = __( 'All changes saved', 'glixform' );
	if ( saving ) {
		status = __( 'Saving…', 'glixform' );
	} else if ( state.dirty || ! state.formId ) {
		status = __( 'Unsaved changes', 'glixform' );
	}

	return (
		<div className="gf-app">
			<header className="gf-topbar">
				<div className="gf-topbar-left">
					<a
						className="gf-back"
						href={ config.urls.forms }
						aria-label={ __( 'Back to all forms', 'glixform' ) }
					>
						<Icon icon={ arrowLeft } />
					</a>
					<div className="gf-title-wrap">
						<input
							className="gf-title"
							value={ title }
							placeholder={ __( 'Untitled form', 'glixform' ) }
							aria-label={ __( 'Form name', 'glixform' ) }
							onChange={ ( e ) =>
								dispatch( {
									type: 'SET_TITLE',
									title: e.target.value,
									coalesce: 'title',
								} )
							}
						/>
						<span
							className={
								'gf-status' +
								( state.dirty || ! state.formId
									? ' is-dirty'
									: '' )
							}
							role="status"
						>
							{ status }
						</span>
					</div>
				</div>

				<div
					className="gf-tabs"
					role="tablist"
					aria-label={ __( 'Builder view', 'glixform' ) }
				>
					<button
						type="button"
						role="tab"
						aria-selected={ view === 'build' }
						className={ view === 'build' ? 'is-active' : '' }
						onClick={ () => setView( 'build' ) }
					>
						{ __( 'Build', 'glixform' ) }
					</button>
					<button
						type="button"
						role="tab"
						aria-selected={ view === 'settings' }
						className={ view === 'settings' ? 'is-active' : '' }
						onClick={ () => setView( 'settings' ) }
					>
						{ __( 'Settings', 'glixform' ) }
					</button>
				</div>

				<div className="gf-topbar-right">
					<Button
						icon={ undoIcon }
						label={ __( 'Undo (Ctrl+Z)', 'glixform' ) }
						disabled={ ! state.past.length }
						onClick={ () => dispatch( { type: 'UNDO' } ) }
					/>
					<Button
						icon={ redoIcon }
						label={ __( 'Redo (Ctrl+Shift+Z)', 'glixform' ) }
						disabled={ ! state.future.length }
						onClick={ () => dispatch( { type: 'REDO' } ) }
					/>
					<span className="gf-divider" />
					{ state.formId > 0 && meta && (
						<>
							<Button
								className="gf-shortcode"
								icon={ copyIcon }
								label={ __( 'Copy shortcode', 'glixform' ) }
								showTooltip
								onClick={ () => {
									if ( window.navigator.clipboard ) {
										window.navigator.clipboard
											.writeText( meta.shortcode )
											.then( () =>
												toast(
													__(
														'Shortcode copied',
														'glixform'
													)
												)
											);
									}
								} }
							>
								<code>{ meta.shortcode }</code>
							</Button>
							<Button
								variant="tertiary"
								icon={ external }
								iconPosition="right"
								href={ meta.previewUrl }
								target="_blank"
								rel="noopener"
							>
								{ __( 'Preview', 'glixform' ) }
							</Button>
						</>
					) }
					<Button
						variant="primary"
						className="gf-save"
						onClick={ save }
						disabled={ saving }
						aria-keyshortcuts="Control+S"
					>
						{ saving ? <Spinner /> : null }
						{ state.formId
							? __( 'Save', 'glixform' )
							: __( 'Save form', 'glixform' ) }
					</Button>
				</div>
			</header>

			{ view === 'build' ? (
				<div className="gf-workspace">
					<aside className="gf-sidebar">
						<div className="gf-sidebar-tabs" role="tablist">
							<button
								type="button"
								role="tab"
								aria-selected={ sidebarTab === 'add' }
								className={
									sidebarTab === 'add' ? 'is-active' : ''
								}
								onClick={ () => setSidebarTab( 'add' ) }
							>
								{ __( 'Add fields', 'glixform' ) }
							</button>
							<button
								type="button"
								role="tab"
								aria-selected={ sidebarTab === 'options' }
								className={
									sidebarTab === 'options' ? 'is-active' : ''
								}
								onClick={ () => setSidebarTab( 'options' ) }
							>
								{ __( 'Field options', 'glixform' ) }
							</button>
						</div>
						<div className="gf-sidebar-body">
							{ sidebarTab === 'add' && (
								<Palette onAdd={ ( t ) => addField( t ) } />
							) }
							{ sidebarTab === 'options' &&
								( selected ? (
									<FieldOptions
										key={ selected.id }
										field={ selected }
										fields={ data.fields }
										dispatch={ dispatch }
										onBack={ () => setSidebarTab( 'add' ) }
									/>
								) : (
									<div className="gf-sidebar-empty">
										<span
											className="dashicons dashicons-edit"
											aria-hidden="true"
										/>
										<p>
											{ __(
												'Select a field in the form to edit its options.',
												'glixform'
											) }
										</p>
									</div>
								) ) }
						</div>
					</aside>

					<main className="gf-canvas">
						<div className="gf-canvas-head">
							<h2>{ __( 'Form fields', 'glixform' ) }</h2>
							<span className="gf-muted">
								{ sprintf(
									/* translators: %d: number of fields */
									__( '%d fields', 'glixform' ),
									data.fields.length
								) }
							</span>
						</div>
						<FieldList
							fields={ data.fields }
							selectedId={ state.selectedId }
							dispatch={ wrappedDispatch }
							onDropNew={ ( t, i ) => addField( t, i ) }
						/>
					</main>

					<section
						className="gf-preview-col"
						aria-label={ __( 'Live preview', 'glixform' ) }
					>
						<Preview
							title={ title }
							data={ data }
							selectedId={ state.selectedId }
							onSelect={ onSelectFromPreview }
						/>
					</section>
				</div>
			) : (
				<SettingsPanel data={ data } dispatch={ dispatch } />
			) }

			<div className="gf-toasts" aria-live="polite">
				{ toasts.map( ( t ) => (
					<div
						key={ t.id }
						className={
							'gf-toast' + ( t.error ? ' is-error' : '' )
						}
					>
						{ t.icon && <Icon icon={ t.icon } size={ 20 } /> }
						<span>{ t.message }</span>
						{ t.action && (
							<button
								type="button"
								onClick={ () => {
									t.action.onClick();
									dismissToast( t.id );
								} }
							>
								{ t.action.label }
							</button>
						) }
					</div>
				) ) }
			</div>
		</div>
	);
}

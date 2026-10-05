/**
 * Form settings: general, confirmations, notifications and spam protection.
 */
import { useEffect, useRef, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import {
	Button,
	SelectControl,
	TextControl,
	ToggleControl,
} from '@wordpress/components';
import { copy, plus, trash } from '@wordpress/icons';
import { config } from '../config';
import { SmartInput, Section } from './controls';
import ConditionEditor from './ConditionEditor';

const common = { __nextHasNoMarginBottom: true };

/**
 * True right after an item was added to the list, so the new item opens.
 *
 * @param {number} length Current list length.
 * @return {boolean} Whether the list just grew.
 */
function useJustGrew( length ) {
	const previous = useRef( length );
	const grew = length > previous.current;
	useEffect( () => {
		previous.current = length;
	}, [ length ] );
	return grew;
}

function General( { settings, update, hasPages } ) {
	return (
		<div className="gf-panel">
			<h2>{ __( 'General', 'glixform' ) }</h2>
			<TextControl
				{ ...common }
				__next40pxDefaultSize
				label={ __( 'Submit button text', 'glixform' ) }
				value={ settings.submit_text }
				onChange={ ( v ) =>
					update( { submit_text: v }, 'submit_text' )
				}
			/>
			<TextControl
				{ ...common }
				__next40pxDefaultSize
				label={ __( 'Submit button text while sending', 'glixform' ) }
				value={ settings.processing_text }
				onChange={ ( v ) =>
					update( { processing_text: v }, 'processing_text' )
				}
			/>
			<ToggleControl
				{ ...common }
				label={ __( 'Store entries in the database', 'glixform' ) }
				help={ __( 'Turn off to only send emails.', 'glixform' ) }
				checked={ !! settings.store_entries }
				onChange={ ( v ) => update( { store_entries: v } ) }
			/>
			<SelectControl
				{ ...common }
				__next40pxDefaultSize
				label={ __( 'Multi-page progress indicator', 'glixform' ) }
				help={
					hasPages
						? ''
						: __(
								'Shown when the form has page breaks.',
								'glixform'
						  )
				}
				value={ settings.progress }
				options={ [
					{ value: 'bar', label: __( 'Progress bar', 'glixform' ) },
					{
						value: 'steps',
						label: __( 'Numbered steps', 'glixform' ),
					},
					{ value: 'none', label: __( 'None', 'glixform' ) },
				] }
				onChange={ ( v ) => update( { progress: v } ) }
			/>
		</div>
	);
}

function ListHeader( { title, description, onAdd, addLabel } ) {
	return (
		<div className="gf-panel-head">
			<div>
				<h2>{ title }</h2>
				<p className="gf-help">{ description }</p>
			</div>
			<Button variant="secondary" icon={ plus } onClick={ onAdd }>
				{ addLabel }
			</Button>
		</div>
	);
}

function ItemActions( { onDuplicate, onDelete, canDelete } ) {
	return (
		<span className="gf-item-actions">
			<Button
				size="small"
				icon={ copy }
				label={ __( 'Duplicate', 'glixform' ) }
				onClick={ onDuplicate }
			/>
			<Button
				size="small"
				icon={ trash }
				isDestructive
				disabled={ ! canDelete }
				label={ __( 'Delete', 'glixform' ) }
				onClick={ onDelete }
			/>
		</span>
	);
}

function Confirmations( { settings, fields, dispatch } ) {
	const list = settings.confirmations;
	const grew = useJustGrew( list.length );
	const up = ( id, key ) => ( value ) =>
		dispatch( {
			type: 'UPDATE_LIST_ITEM',
			list: 'confirmations',
			id,
			patch: { [ key ]: value },
			coalesce: `conf-${ id }-${ key }`,
		} );

	return (
		<div className="gf-panel">
			<ListHeader
				title={ __( 'Confirmations', 'glixform' ) }
				description={ __(
					'What visitors see after submitting. Confirmations with conditions are checked first; otherwise the first one without conditions is used.',
					'glixform'
				) }
				addLabel={ __( 'Add confirmation', 'glixform' ) }
				onAdd={ () =>
					dispatch( {
						type: 'ADD_LIST_ITEM',
						list: 'confirmations',
						item: {
							...config.defaults.confirmation,
							conditional: {
								enabled: false,
								action: 'show',
								logic: 'all',
								rules: [],
							},
						},
						name: sprintf(
							/* translators: %d: number */ __(
								'Confirmation %d',
								'glixform'
							),
							list.length + 1
						),
					} )
				}
			/>
			{ list.map( ( item, index ) => (
				<Section
					key={ item.id }
					initialOpen={
						index === 0 || ( grew && index === list.length - 1 )
					}
					title={ item.name || __( 'Untitled', 'glixform' ) }
					badge={
						item.conditional?.enabled ? (
							<span className="gf-badge gf-badge-logic">
								{ __( 'Conditional', 'glixform' ) }
							</span>
						) : null
					}
				>
					<div className="gf-item-toolbar">
						<TextControl
							{ ...common }
							__next40pxDefaultSize
							label={ __(
								'Name (only you see this)',
								'glixform'
							) }
							value={ item.name }
							onChange={ up( item.id, 'name' ) }
						/>
						<ItemActions
							canDelete={ list.length > 1 }
							onDuplicate={ () =>
								dispatch( {
									type: 'ADD_LIST_ITEM',
									list: 'confirmations',
									copyOf: item.id,
									name:
										item.name +
										' ' +
										__( '(copy)', 'glixform' ),
								} )
							}
							onDelete={ () =>
								dispatch( {
									type: 'DELETE_LIST_ITEM',
									list: 'confirmations',
									id: item.id,
								} )
							}
						/>
					</div>
					<div
						className="gf-segmented gf-segmented-text"
						role="radiogroup"
						aria-label={ __( 'Confirmation type', 'glixform' ) }
					>
						{ [
							[ 'message', __( 'Show a message', 'glixform' ) ],
							[ 'page', __( 'Go to a page', 'glixform' ) ],
							[ 'redirect', __( 'Go to a URL', 'glixform' ) ],
						].map( ( [ v, l ] ) => (
							<Button
								key={ v }
								role="radio"
								aria-checked={ item.type === v }
								isPressed={ item.type === v }
								onClick={ () => up( item.id, 'type' )( v ) }
							>
								{ l }
							</Button>
						) ) }
					</div>
					{ item.type === 'message' && (
						<SmartInput
							multiline
							label={ __( 'Message', 'glixform' ) }
							value={ item.message }
							fields={ fields }
							onChange={ up( item.id, 'message' ) }
						/>
					) }
					{ item.type === 'page' && (
						<SelectControl
							{ ...common }
							__next40pxDefaultSize
							label={ __( 'Page', 'glixform' ) }
							value={ String( item.page_id || 0 ) }
							options={ [
								{
									value: '0',
									label: __(
										'— Select a page —',
										'glixform'
									),
								},
								...config.pages.map( ( p ) => ( {
									value: String( p.id ),
									label: p.title,
								} ) ),
							] }
							onChange={ ( v ) =>
								up( item.id, 'page_id' )( parseInt( v, 10 ) )
							}
						/>
					) }
					{ item.type === 'redirect' && (
						<TextControl
							{ ...common }
							__next40pxDefaultSize
							type="url"
							label={ __( 'URL', 'glixform' ) }
							placeholder="https://"
							value={ item.url }
							onChange={ up( item.id, 'url' ) }
						/>
					) }
					<div className="gf-subsection">
						<h4>{ __( 'Conditions', 'glixform' ) }</h4>
						<ConditionEditor
							mode="confirmation"
							condition={ item.conditional }
							fields={ fields }
							onChange={ ( conditional ) =>
								dispatch( {
									type: 'UPDATE_LIST_ITEM',
									list: 'confirmations',
									id: item.id,
									patch: { conditional },
								} )
							}
						/>
					</div>
				</Section>
			) ) }
		</div>
	);
}

function Notifications( { settings, fields, dispatch } ) {
	const list = settings.notifications;
	const grew = useJustGrew( list.length );
	const up = ( id, key ) => ( value ) =>
		dispatch( {
			type: 'UPDATE_LIST_ITEM',
			list: 'notifications',
			id,
			patch: { [ key ]: value },
			coalesce: `noti-${ id }-${ key }`,
		} );

	return (
		<div className="gf-panel">
			<ListHeader
				title={ __( 'Notifications', 'glixform' ) }
				description={ __(
					'Emails sent for each new entry, for example to your team and a copy to the visitor.',
					'glixform'
				) }
				addLabel={ __( 'Add notification', 'glixform' ) }
				onAdd={ () =>
					dispatch( {
						type: 'ADD_LIST_ITEM',
						list: 'notifications',
						item: {
							...config.defaults.notification,
							conditional: {
								enabled: false,
								action: 'show',
								logic: 'all',
								rules: [],
							},
						},
						name: sprintf(
							/* translators: %d: number */ __(
								'Notification %d',
								'glixform'
							),
							list.length + 1
						),
					} )
				}
			/>
			{ ! list.length && (
				<p className="gf-help">
					{ __(
						'No notifications: no emails will be sent.',
						'glixform'
					) }
				</p>
			) }
			{ list.map( ( item, index ) => (
				<Section
					key={ item.id }
					initialOpen={
						index === 0 || ( grew && index === list.length - 1 )
					}
					title={ item.name || __( 'Untitled', 'glixform' ) }
					badge={
						<>
							{ ! item.enabled && (
								<span className="gf-badge gf-badge-off">
									{ __( 'Off', 'glixform' ) }
								</span>
							) }
							{ item.conditional?.enabled && (
								<span className="gf-badge gf-badge-logic">
									{ __( 'Conditional', 'glixform' ) }
								</span>
							) }
						</>
					}
				>
					<div className="gf-item-toolbar">
						<TextControl
							{ ...common }
							__next40pxDefaultSize
							label={ __(
								'Name (only you see this)',
								'glixform'
							) }
							value={ item.name }
							onChange={ up( item.id, 'name' ) }
						/>
						<ItemActions
							canDelete
							onDuplicate={ () =>
								dispatch( {
									type: 'ADD_LIST_ITEM',
									list: 'notifications',
									copyOf: item.id,
									name:
										item.name +
										' ' +
										__( '(copy)', 'glixform' ),
								} )
							}
							onDelete={ () =>
								dispatch( {
									type: 'DELETE_LIST_ITEM',
									list: 'notifications',
									id: item.id,
								} )
							}
						/>
					</div>
					<ToggleControl
						{ ...common }
						label={ __( 'Enabled', 'glixform' ) }
						checked={ !! item.enabled }
						onChange={ up( item.id, 'enabled' ) }
					/>
					<SmartInput
						label={ __( 'Send to', 'glixform' ) }
						help={ __(
							'Separate several addresses with commas. Use an Email field’s smart tag to send a copy to the visitor.',
							'glixform'
						) }
						value={ item.to }
						fields={ fields }
						onChange={ up( item.id, 'to' ) }
					/>
					<SmartInput
						label={ __( 'Subject', 'glixform' ) }
						value={ item.subject }
						fields={ fields }
						onChange={ up( item.id, 'subject' ) }
					/>
					<div className="gf-two-cols">
						<SmartInput
							label={ __( 'From name', 'glixform' ) }
							value={ item.from_name }
							fields={ fields }
							onChange={ up( item.id, 'from_name' ) }
						/>
						<SmartInput
							label={ __( 'Reply-to', 'glixform' ) }
							placeholder={ '{field_id="2"}' }
							value={ item.reply_to }
							fields={ fields }
							onChange={ up( item.id, 'reply_to' ) }
						/>
					</div>
					<SmartInput
						multiline
						label={ __( 'Message', 'glixform' ) }
						value={ item.message }
						fields={ fields }
						onChange={ up( item.id, 'message' ) }
					/>
					<div className="gf-subsection">
						<h4>{ __( 'Conditions', 'glixform' ) }</h4>
						<ConditionEditor
							mode="notification"
							condition={ item.conditional }
							fields={ fields }
							onChange={ ( conditional ) =>
								dispatch( {
									type: 'UPDATE_LIST_ITEM',
									list: 'notifications',
									id: item.id,
									patch: { conditional },
								} )
							}
						/>
					</div>
				</Section>
			) ) }
		</div>
	);
}

function Spam( { settings, update } ) {
	return (
		<div className="gf-panel">
			<h2>{ __( 'Spam protection', 'glixform' ) }</h2>
			<div className="gf-callout">
				<span
					className="dashicons dashicons-shield-alt"
					aria-hidden="true"
				/>
				<div>
					<strong>{ __( 'Always on', 'glixform' ) }</strong>
					<p>
						{ __(
							'Every form has an invisible honeypot field and a signed time check that stop most bots without bothering visitors.',
							'glixform'
						) }
					</p>
				</div>
			</div>
			<ToggleControl
				{ ...common }
				label={ __( 'Use CAPTCHA', 'glixform' ) }
				disabled={ ! config.captchaConfigured }
				checked={ !! settings.captcha && config.captchaConfigured }
				help={
					config.captchaConfigured ? (
						sprintf(
							/* translators: %s: provider name */ __(
								'Uses %s, as set in Glixform → Settings.',
								'glixform'
							),
							config.captchaProvider
						)
					) : (
						<a href={ config.urls.settings }>
							{ __(
								'Set up a CAPTCHA provider in Glixform → Settings first.',
								'glixform'
							) }
						</a>
					)
				}
				onChange={ ( v ) => update( { captcha: v } ) }
			/>
			<ToggleControl
				{ ...common }
				label={ __( 'Check entries with Akismet', 'glixform' ) }
				disabled={ ! config.akismetAvailable }
				checked={ !! settings.akismet && config.akismetAvailable }
				help={
					config.akismetAvailable
						? __(
								'Entries Akismet flags go to the Spam folder and send no emails.',
								'glixform'
						  )
						: __(
								'Install and connect the Akismet plugin to use this.',
								'glixform'
						  )
				}
				onChange={ ( v ) => update( { akismet: v } ) }
			/>
		</div>
	);
}

export default function SettingsPanel( { data, dispatch } ) {
	const [ tab, setTab ] = useState( 'general' );
	const settings = data.settings;
	const update = ( patch, key ) =>
		dispatch( {
			type: 'UPDATE_SETTINGS',
			patch,
			coalesce: key ? `settings-${ key }` : undefined,
		} );
	const tabs = [
		[ 'general', __( 'General', 'glixform' ), 'dashicons-admin-generic' ],
		[
			'confirmations',
			__( 'Confirmations', 'glixform' ),
			'dashicons-yes-alt',
			settings.confirmations.length,
		],
		[
			'notifications',
			__( 'Notifications', 'glixform' ),
			'dashicons-email-alt',
			settings.notifications.length,
		],
		[ 'spam', __( 'Spam protection', 'glixform' ), 'dashicons-shield' ],
	];

	return (
		<div className="gf-settings">
			<nav
				className="gf-settings-nav"
				aria-label={ __( 'Settings sections', 'glixform' ) }
			>
				{ tabs.map( ( [ key, label, icon, count ] ) => (
					<button
						key={ key }
						type="button"
						className={ tab === key ? 'is-active' : '' }
						aria-current={ tab === key ? 'page' : undefined }
						onClick={ () => setTab( key ) }
					>
						<span
							className={ 'dashicons ' + icon }
							aria-hidden="true"
						/>
						<span>{ label }</span>
						{ count !== undefined && (
							<span className="gf-count">{ count }</span>
						) }
					</button>
				) ) }
			</nav>
			<div className="gf-settings-body">
				{ tab === 'general' && (
					<General
						settings={ settings }
						update={ update }
						hasPages={ data.fields.some(
							( f ) => f.type === 'pagebreak'
						) }
					/>
				) }
				{ tab === 'confirmations' && (
					<Confirmations
						settings={ settings }
						fields={ data.fields }
						dispatch={ dispatch }
					/>
				) }
				{ tab === 'notifications' && (
					<Notifications
						settings={ settings }
						fields={ data.fields }
						dispatch={ dispatch }
					/>
				) }
				{ tab === 'spam' && (
					<Spam settings={ settings } update={ update } />
				) }
			</div>
		</div>
	);
}

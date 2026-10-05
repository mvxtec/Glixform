/**
 * Left sidebar: "Add fields" palette and the selected field's options.
 */
import { useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { Button, SearchControl } from '@wordpress/components';
import { arrowLeft } from '@wordpress/icons';
import { config } from '../config';
import { OptionControl, Section } from './controls';
import ConditionEditor from './ConditionEditor';

const GROUPS = () => [
	[ 'standard', __( 'Standard fields', 'glixform' ) ],
	[ 'fancy', __( 'Fancy fields', 'glixform' ) ],
	[ 'layout', __( 'Layout', 'glixform' ) ],
];

export function Palette( { onAdd } ) {
	const [ search, setSearch ] = useState( '' );
	const term = search.trim().toLowerCase();

	return (
		<div className="gf-palette">
			<SearchControl
				__nextHasNoMarginBottom
				value={ search }
				onChange={ setSearch }
				placeholder={ __( 'Search fields', 'glixform' ) }
			/>
			{ GROUPS().map( ( [ group, title ] ) => {
				const items = config.typeList.filter(
					( t ) =>
						t.category === group &&
						( ! term || t.name.toLowerCase().includes( term ) )
				);
				if ( ! items.length ) {
					return null;
				}
				return (
					<div key={ group } className="gf-palette-group">
						<h3>{ title }</h3>
						<div className="gf-palette-grid">
							{ items.map( ( type ) => (
								<button
									key={ type.type }
									type="button"
									className="gf-palette-item"
									onClick={ () => onAdd( type.type ) }
									draggable
									onDragStart={ ( e ) => {
										e.dataTransfer.setData(
											'application/x-glixform-field',
											type.type
										);
										e.dataTransfer.effectAllowed = 'copy';
									} }
								>
									<span
										className={ 'dashicons ' + type.icon }
										aria-hidden="true"
									/>
									<span>{ type.name }</span>
								</button>
							) ) }
						</div>
					</div>
				);
			} ) }
			<p className="gf-help gf-palette-tip">
				{ __(
					'Click a field to add it below the selected one, or drag it into the form.',
					'glixform'
				) }
			</p>
		</div>
	);
}

export function FieldOptions( { field, fields, dispatch, onBack } ) {
	const type = config.types[ field.type ];
	const update = ( key ) => ( value ) =>
		dispatch( {
			type: 'UPDATE_FIELD',
			id: field.id,
			patch: { [ key ]: value },
			coalesce: `field-${ field.id }-${ key }`,
		} );
	const entries = Object.entries( type.options );
	const basic = entries.filter( ( [ , def ] ) => def.group !== 'advanced' );
	const advanced = entries.filter(
		( [ , def ] ) => def.group === 'advanced'
	);

	return (
		<div className="gf-field-options">
			<div className="gf-field-options-head">
				<Button
					icon={ arrowLeft }
					label={ __( 'Back to fields', 'glixform' ) }
					onClick={ onBack }
					size="compact"
				/>
				<span
					className={ 'dashicons ' + type.icon }
					aria-hidden="true"
				/>
				<div>
					<strong>{ type.name }</strong>
					<span className="gf-muted">
						{ sprintf(
							/* translators: %d: field ID */ __(
								'Field ID %d',
								'glixform'
							),
							field.id
						) }
					</span>
				</div>
			</div>

			<Section title={ __( 'General', 'glixform' ) }>
				{ basic.map( ( [ key, def ] ) => (
					<OptionControl
						key={ key }
						name={ key }
						def={ def }
						value={ field[ key ] }
						field={ field }
						onChange={ update( key ) }
					/>
				) ) }
			</Section>

			{ type.supportsLogic && (
				<Section
					title={ __( 'Conditional logic', 'glixform' ) }
					initialOpen={ !! field.conditional?.enabled }
					badge={
						field.conditional?.enabled ? (
							<span className="gf-badge">
								{ __( 'On', 'glixform' ) }
							</span>
						) : null
					}
				>
					<ConditionEditor
						condition={ field.conditional }
						fields={ fields }
						selfId={ field.id }
						onChange={ ( conditional ) =>
							dispatch( {
								type: 'UPDATE_FIELD',
								id: field.id,
								patch: { conditional },
							} )
						}
					/>
				</Section>
			) }

			{ advanced.length > 0 && (
				<Section
					title={ __( 'Advanced', 'glixform' ) }
					initialOpen={ false }
				>
					{ advanced.map( ( [ key, def ] ) => (
						<OptionControl
							key={ key }
							name={ key }
							def={ def }
							value={ field[ key ] }
							field={ field }
							onChange={ update( key ) }
						/>
					) ) }
				</Section>
			) }
		</div>
	);
}

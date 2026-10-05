/**
 * Small form controls used across the builder.
 */
import { useRef, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import {
	Button,
	Dropdown,
	MenuGroup,
	MenuItem,
	TextControl,
	TextareaControl,
	ToggleControl,
	SelectControl,
} from '@wordpress/components';
import {
	Icon,
	chevronUp,
	chevronDown,
	closeSmall,
	plus,
} from '@wordpress/icons';
import { config } from '../config';

const common = { __nextHasNoMarginBottom: true };

/**
 * Text input or textarea with a smart-tag picker that inserts at the cursor.
 *
 * @param {Object}   props
 * @param {string}   props.label
 * @param {string}   props.value
 * @param {Function} props.onChange
 * @param {boolean}  props.multiline
 * @param {Array}    props.fields      Field configs, offered as {field_id="N"} tags.
 * @param {string}   props.help
 * @param {string}   props.placeholder
 */
export function SmartInput( {
	label,
	value,
	onChange,
	multiline,
	fields = [],
	help,
	placeholder,
} ) {
	const ref = useRef();
	const [ id ] = useState(
		() => 'gf-smart-' + Math.random().toString( 36 ).slice( 2 )
	);
	const Tag = multiline ? 'textarea' : 'input';

	const insert = ( tag ) => {
		const el = ref.current;
		const current = value || '';
		const start = el ? el.selectionStart ?? current.length : current.length;
		const end = el ? el.selectionEnd ?? current.length : current.length;
		const next = current.slice( 0, start ) + tag + current.slice( end );
		onChange( next );
		window.requestAnimationFrame( () => {
			if ( el ) {
				el.focus();
				el.setSelectionRange( start + tag.length, start + tag.length );
			}
		} );
	};

	const inputFields = fields.filter(
		( f ) => config.types[ f.type ]?.isInput
	);

	return (
		<div className="gf-control gf-smart-input">
			<div className="gf-control-head">
				<label htmlFor={ id } className="gf-control-label">
					{ label }
				</label>
				<Dropdown
					popoverProps={ { placement: 'bottom-end' } }
					renderToggle={ ( { isOpen, onToggle } ) => (
						<Button
							size="small"
							variant="tertiary"
							className="gf-tag-toggle"
							onClick={ onToggle }
							aria-expanded={ isOpen }
						>
							{ __( 'Smart tags', 'glixform' ) }
						</Button>
					) }
					renderContent={ ( { onClose } ) => (
						<div className="gf-tag-menu">
							{ inputFields.length > 0 && (
								<MenuGroup
									label={ __( 'Form fields', 'glixform' ) }
								>
									{ inputFields.map( ( f ) => (
										<MenuItem
											key={ f.id }
											onClick={ () => {
												insert(
													`{field_id="${ f.id }"}`
												);
												onClose();
											} }
										>
											{ f.label ||
												config.types[ f.type ].name }
										</MenuItem>
									) ) }
								</MenuGroup>
							) }
							<MenuGroup label={ __( 'General', 'glixform' ) }>
								{ Object.entries( config.smartTags )
									.filter(
										( [ tag ] ) => tag !== '{field_id="N"}'
									)
									.map( ( [ tag, description ] ) => (
										<MenuItem
											key={ tag }
											info={ tag }
											onClick={ () => {
												insert( tag );
												onClose();
											} }
										>
											{ description }
										</MenuItem>
									) ) }
							</MenuGroup>
						</div>
					) }
				/>
			</div>
			<Tag
				id={ id }
				ref={ ref }
				className={ multiline ? 'gf-textarea' : 'gf-input' }
				value={ value || '' }
				placeholder={ placeholder }
				rows={ multiline ? 5 : undefined }
				onChange={ ( e ) => onChange( e.target.value ) }
			/>
			{ help && <p className="gf-help">{ help }</p> }
		</div>
	);
}

/**
 * Editor for a choice list: reorder, rename, set defaults, add, remove.
 *
 * @param {Object}   props
 * @param {Array}    props.value
 * @param {Function} props.onChange
 * @param {boolean}  props.multiple Several defaults allowed (checkboxes).
 * @param {string}   props.label
 */
export function ChoicesEditor( { value = [], onChange, multiple, label } ) {
	const update = ( index, patch ) =>
		onChange(
			value.map( ( c, i ) => ( i === index ? { ...c, ...patch } : c ) )
		);
	const move = ( from, to ) => {
		const next = [ ...value ];
		const [ item ] = next.splice( from, 1 );
		next.splice( to, 0, item );
		onChange( next );
	};
	const setDefault = ( index, checked ) => {
		onChange(
			value.map( ( c, i ) => {
				if ( i === index ) {
					return { ...c, default: checked };
				}
				// Radio-style choices allow only one default.
				return { ...c, default: multiple ? c.default : false };
			} )
		);
	};

	return (
		<div className="gf-control gf-choices">
			<span className="gf-control-label">{ label }</span>
			<ul>
				{ value.map( ( choice, index ) => (
					<li key={ index }>
						<input
							type={ multiple ? 'checkbox' : 'radio' }
							checked={ !! choice.default }
							title={ __( 'Selected by default', 'glixform' ) }
							aria-label={ __(
								'Selected by default',
								'glixform'
							) }
							onChange={ () => {} }
							onClick={ () =>
								setDefault( index, ! choice.default )
							}
						/>
						<input
							type="text"
							className="gf-input"
							value={ choice.label }
							aria-label={ sprintf(
								/* translators: %d: choice number */ __(
									'Choice %d',
									'glixform'
								),
								index + 1
							) }
							onChange={ ( e ) =>
								update( index, { label: e.target.value } )
							}
							onKeyDown={ ( e ) => {
								if ( e.key === 'Enter' ) {
									e.preventDefault();
									const next = [ ...value ];
									next.splice( index + 1, 0, {
										label: '',
										default: false,
									} );
									onChange( next );
									setTimeout( () => {
										const inputs = e.target
											.closest( 'ul' )
											.querySelectorAll(
												'input[type=text]'
											);
										if ( inputs[ index + 1 ] ) {
											inputs[ index + 1 ].focus();
										}
									} );
								}
							} }
						/>
						<span className="gf-choice-actions">
							<Button
								size="small"
								icon={ chevronUp }
								label={ __( 'Move up', 'glixform' ) }
								disabled={ index === 0 }
								onClick={ () => move( index, index - 1 ) }
							/>
							<Button
								size="small"
								icon={ chevronDown }
								label={ __( 'Move down', 'glixform' ) }
								disabled={ index === value.length - 1 }
								onClick={ () => move( index, index + 1 ) }
							/>
							<Button
								size="small"
								icon={ closeSmall }
								label={ __( 'Remove choice', 'glixform' ) }
								disabled={ value.length < 2 }
								onClick={ () =>
									onChange(
										value.filter( ( _, i ) => i !== index )
									)
								}
							/>
						</span>
					</li>
				) ) }
			</ul>
			<Button
				variant="secondary"
				size="compact"
				icon={ plus }
				onClick={ () =>
					onChange( [
						...value,
						{
							label: sprintf(
								/* translators: %d: choice number */ __(
									'Choice %d',
									'glixform'
								),
								value.length + 1
							),
							default: false,
						},
					] )
				}
			>
				{ __( 'Add choice', 'glixform' ) }
			</Button>
			<p className="gf-help">
				{ __(
					'Tip: press Enter in a choice to add one below it.',
					'glixform'
				) }
			</p>
		</div>
	);
}

/**
 * One option from a field's PHP schema.
 *
 * @param {Object}   props
 * @param {string}   props.name     Option key.
 * @param {Object}   props.def      Definition: type, label, help, choices, min, max, step.
 * @param {*}        props.value
 * @param {Function} props.onChange
 * @param {Object}   props.field    Field config (for choice multiplicity).
 */
export function OptionControl( { name, def, value, onChange, field } ) {
	switch ( def.type ) {
		case 'toggle':
			return (
				<ToggleControl
					{ ...common }
					label={ def.label }
					help={ def.help }
					checked={ !! value }
					onChange={ onChange }
				/>
			);
		case 'textarea':
		case 'html':
			return (
				<TextareaControl
					{ ...common }
					label={ def.label }
					help={ def.help }
					value={ value ?? '' }
					rows={ name === 'content' ? 6 : 3 }
					onChange={ onChange }
				/>
			);
		case 'select':
			return (
				<SelectControl
					{ ...common }
					__next40pxDefaultSize
					label={ def.label }
					help={ def.help }
					value={ String( value ) }
					options={ Object.entries( def.choices || {} ).map(
						( [ v, l ] ) => ( { value: v, label: l } )
					) }
					onChange={ onChange }
				/>
			);
		case 'number':
			return (
				<TextControl
					{ ...common }
					__next40pxDefaultSize
					type="number"
					label={ def.label }
					help={ def.help }
					value={ value ?? '' }
					min={ def.min }
					max={ def.max }
					step={ def.step ?? 'any' }
					onChange={ ( v ) => {
						if ( v === '' ) {
							onChange( '' );
						} else {
							onChange(
								Number.isInteger( def.default )
									? parseInt( v, 10 ) || 0
									: v
							);
						}
					} }
				/>
			);
		case 'choices':
			return (
				<ChoicesEditor
					label={ def.label }
					value={ value || [] }
					multiple={ config.types[ field.type ]?.isMultiple }
					onChange={ onChange }
				/>
			);
		default:
			return (
				<TextControl
					{ ...common }
					__next40pxDefaultSize
					label={ def.label }
					help={ def.help }
					value={ value ?? '' }
					onChange={ onChange }
				/>
			);
	}
}

/**
 * Collapsible section.
 *
 * @param {Object}  props
 * @param {string}  props.title
 * @param {boolean} props.initialOpen
 * @param {*}       props.children
 * @param {*}       props.badge
 */
export function Section( { title, initialOpen = true, children, badge } ) {
	const [ open, setOpen ] = useState( initialOpen );
	return (
		<section className={ 'gf-section' + ( open ? ' is-open' : '' ) }>
			<button
				type="button"
				className="gf-section-toggle"
				aria-expanded={ open }
				onClick={ () => setOpen( ! open ) }
			>
				<span>{ title }</span>
				{ badge }
				<Icon icon={ open ? chevronUp : chevronDown } size={ 20 } />
			</button>
			{ open && <div className="gf-section-body">{ children }</div> }
		</section>
	);
}

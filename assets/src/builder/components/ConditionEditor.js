/**
 * Conditional logic editor, shared by fields, notifications and confirmations.
 */
import { __ } from '@wordpress/i18n';
import { Button, ToggleControl } from '@wordpress/components';
import { closeSmall, plus } from '@wordpress/icons';
import { config, operators } from '../config';
import { emptyCondition } from '../store';

const NO_VALUE = [ 'empty', 'not_empty' ];

/**
 * Possible values for a rule on a field: choices, star numbers, or free text.
 *
 * @param {Object} field Field config.
 * @return {Array|null} Options, or null for a text input.
 */
function valueOptions( field ) {
	if ( ! field ) {
		return null;
	}
	if ( field.type === 'rating' ) {
		return Array.from( { length: field.scale || 5 }, ( _, i ) =>
			String( i + 1 )
		);
	}
	if ( Array.isArray( field.choices ) ) {
		return field.choices.map( ( c ) => c.label );
	}
	return null;
}

/**
 * @param {Object}   props
 * @param {Object}   props.condition Current condition.
 * @param {Function} props.onChange
 * @param {Array}    props.fields    All field configs.
 * @param {number}   props.selfId    Field being edited (excluded from targets).
 * @param {string}   props.mode      "field" | "notification" | "confirmation".
 */
export default function ConditionEditor( {
	condition,
	onChange,
	fields,
	selfId = 0,
	mode = 'field',
} ) {
	const cond = { ...emptyCondition(), ...( condition || {} ) };
	const targets = fields.filter(
		( f ) => f.id !== selfId && config.types[ f.type ]?.isInput
	);
	const set = ( patch ) => onChange( { ...cond, ...patch } );
	const setRule = ( index, patch ) =>
		set( {
			rules: cond.rules.map( ( r, i ) =>
				i === index ? { ...r, ...patch } : r
			),
		} );

	const actionLabels = {
		field: [
			[ 'show', __( 'Show this field', 'glixform' ) ],
			[ 'hide', __( 'Hide this field', 'glixform' ) ],
		],
		notification: [
			[ 'show', __( 'Send this notification', 'glixform' ) ],
			[ 'hide', __( 'Don’t send this notification', 'glixform' ) ],
		],
		confirmation: [
			[ 'show', __( 'Use this confirmation', 'glixform' ) ],
			[ 'hide', __( 'Don’t use this confirmation', 'glixform' ) ],
		],
	}[ mode ];

	const addRule = () => {
		const first = targets[ 0 ];
		set( {
			enabled: true,
			rules: [
				...cond.rules,
				{ field: first ? first.id : 0, operator: 'is', value: '' },
			],
		} );
	};

	if ( ! targets.length ) {
		return (
			<p className="gf-help">
				{ __(
					'Add a field that collects a value to use conditional logic.',
					'glixform'
				) }
			</p>
		);
	}

	return (
		<div className="gf-condition">
			<ToggleControl
				__nextHasNoMarginBottom
				label={ __( 'Enable conditional logic', 'glixform' ) }
				checked={ !! cond.enabled }
				onChange={ ( enabled ) =>
					set(
						enabled && ! cond.rules.length
							? {
									enabled,
									rules: [
										{
											field: targets[ 0 ].id,
											operator: 'is',
											value: '',
										},
									],
							  }
							: { enabled }
					)
				}
			/>
			{ cond.enabled && (
				<div className="gf-condition-body">
					<div className="gf-condition-sentence">
						<select
							className="gf-select"
							value={ cond.action }
							onChange={ ( e ) =>
								set( { action: e.target.value } )
							}
							aria-label={ __( 'Action', 'glixform' ) }
						>
							{ actionLabels.map( ( [ v, l ] ) => (
								<option key={ v } value={ v }>
									{ l }
								</option>
							) ) }
						</select>
						<span>{ __( 'if', 'glixform' ) }</span>
						<select
							className="gf-select"
							value={ cond.logic }
							onChange={ ( e ) =>
								set( { logic: e.target.value } )
							}
							aria-label={ __( 'Match', 'glixform' ) }
						>
							<option value="all">
								{ __( 'all', 'glixform' ) }
							</option>
							<option value="any">
								{ __( 'any', 'glixform' ) }
							</option>
						</select>
						<span>
							{ __( 'of these rules match:', 'glixform' ) }
						</span>
					</div>
					<ol className="gf-rules">
						{ cond.rules.map( ( rule, index ) => {
							const target = fields.find(
								( f ) => f.id === rule.field
							);
							const options = valueOptions( target );
							return (
								<li key={ index } className="gf-rule">
									<select
										className="gf-select"
										value={ rule.field }
										aria-label={ __( 'Field', 'glixform' ) }
										onChange={ ( e ) =>
											setRule( index, {
												field: parseInt(
													e.target.value,
													10
												),
												value: '',
											} )
										}
									>
										{ targets.map( ( f ) => (
											<option key={ f.id } value={ f.id }>
												{ ( f.label ||
													config.types[ f.type ]
														.name ) +
													' (#' +
													f.id +
													')' }
											</option>
										) ) }
									</select>
									<select
										className="gf-select"
										value={ rule.operator }
										aria-label={ __(
											'Operator',
											'glixform'
										) }
										onChange={ ( e ) =>
											setRule( index, {
												operator: e.target.value,
											} )
										}
									>
										{ operators().map( ( [ v, l ] ) => (
											<option key={ v } value={ v }>
												{ l }
											</option>
										) ) }
									</select>
									{ ! NO_VALUE.includes( rule.operator ) &&
										( options ? (
											<select
												className="gf-select"
												value={ rule.value }
												aria-label={ __(
													'Value',
													'glixform'
												) }
												onChange={ ( e ) =>
													setRule( index, {
														value: e.target.value,
													} )
												}
											>
												<option value="">
													{ __(
														'— Choose —',
														'glixform'
													) }
												</option>
												{ options.map( ( o ) => (
													<option
														key={ o }
														value={ o }
													>
														{ o }
													</option>
												) ) }
											</select>
										) : (
											<input
												className="gf-input"
												value={ rule.value }
												aria-label={ __(
													'Value',
													'glixform'
												) }
												placeholder={ __(
													'Value',
													'glixform'
												) }
												onChange={ ( e ) =>
													setRule( index, {
														value: e.target.value,
													} )
												}
											/>
										) ) }
									<Button
										size="small"
										icon={ closeSmall }
										label={ __(
											'Remove rule',
											'glixform'
										) }
										onClick={ () => {
											const rules = cond.rules.filter(
												( _, i ) => i !== index
											);
											set( {
												rules,
												enabled: rules.length > 0,
											} );
										} }
									/>
								</li>
							);
						} ) }
					</ol>
					<Button
						variant="secondary"
						size="compact"
						icon={ plus }
						onClick={ addRule }
					>
						{ __( 'Add rule', 'glixform' ) }
					</Button>
				</div>
			) }
		</div>
	);
}

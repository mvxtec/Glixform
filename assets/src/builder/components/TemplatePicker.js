/**
 * First screen for a new form: name it and pick a template.
 */
import { useState } from '@wordpress/element';
import { __, _n, sprintf } from '@wordpress/i18n';
import { config } from '../config';

export default function TemplatePicker( { onPick } ) {
	const [ name, setName ] = useState( '' );
	const countInputs = ( data ) =>
		data.fields.filter( ( f ) => config.types[ f.type ]?.isInput ).length;
	const pages = ( data ) =>
		data.fields.filter( ( f ) => f.type === 'pagebreak' ).length + 1;

	return (
		<div className="gf-templates">
			<div className="gf-templates-hero">
				<span className="gf-eyebrow">
					{ __( 'New form', 'glixform' ) }
				</span>
				<h1>{ __( 'What would you like to build?', 'glixform' ) }</h1>
				<p>
					{ __(
						'Start from a template and make it your own — every field and setting can be changed.',
						'glixform'
					) }
				</p>
				<div className="gf-templates-name">
					<label htmlFor="gf-template-name">
						{ __( 'Form name', 'glixform' ) }
					</label>
					<input
						id="gf-template-name"
						className="gf-input"
						value={ name }
						placeholder={ __( 'e.g. Contact us', 'glixform' ) }
						onChange={ ( e ) => setName( e.target.value ) }
					/>
				</div>
			</div>
			<ul className="gf-template-grid">
				{ config.templates.map( ( template ) => {
					const fields = countInputs( template.data );
					const steps = pages( template.data );
					return (
						<li key={ template.slug }>
							<button
								type="button"
								className={
									'gf-template' +
									( template.slug === 'blank'
										? ' is-blank'
										: '' )
								}
								onClick={ () =>
									onPick(
										template,
										name.trim() ||
											( template.slug === 'blank'
												? __(
														'Untitled form',
														'glixform'
												  )
												: template.name )
									)
								}
							>
								<span
									className={
										'gf-template-icon dashicons ' +
										template.icon
									}
									aria-hidden="true"
								/>
								<span className="gf-template-name">
									{ template.name }
								</span>
								<span className="gf-template-desc">
									{ template.description }
								</span>
								{ template.slug !== 'blank' && (
									<span className="gf-template-meta">
										{ sprintf(
											/* translators: %d: number of fields */ _n(
												'%d field',
												'%d fields',
												fields,
												'glixform'
											),
											fields
										) }
										{ steps > 1 &&
											' · ' +
												sprintf(
													/* translators: %d: number of steps */ _n(
														'%d step',
														'%d steps',
														steps,
														'glixform'
													),
													steps
												) }
									</span>
								) }
								<span className="gf-template-cta">
									{ template.slug === 'blank'
										? __( 'Start blank', 'glixform' )
										: __(
												'Use template',
												'glixform'
										  ) }{ ' ' }
									→
								</span>
							</button>
						</li>
					);
				} ) }
			</ul>
		</div>
	);
}

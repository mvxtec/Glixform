/**
 * "Glixform" block for the block editor. No build step: uses the wp.* globals.
 */
( function ( wp ) {
	'use strict';

	var el = wp.element.createElement;
	var __ = wp.i18n.__;
	var InspectorControls = wp.blockEditor.InspectorControls;
	var useBlockProps = wp.blockEditor.useBlockProps;
	var PanelBody = wp.components.PanelBody;
	var SelectControl = wp.components.SelectControl;
	var ToggleControl = wp.components.ToggleControl;
	var Placeholder = wp.components.Placeholder;
	var ServerSideRender = wp.serverSideRender;
	var data = window.glixformBlock || { forms: [], newFormUrl: '' };

	var options = [ { value: 0, label: __( '— Select a form —', 'glixform' ) } ].concat(
		data.forms.map( function ( form ) {
			return { value: form.id, label: form.title };
		} )
	);

	function formSelect( props ) {
		return el( SelectControl, {
			label: __( 'Form', 'glixform' ),
			value: props.attributes.formId,
			options: options,
			onChange: function ( value ) {
				props.setAttributes( { formId: parseInt( value, 10 ) || 0 } );
			},
		} );
	}

	wp.blocks.registerBlockType( 'glixform/form', {
		edit: function ( props ) {
			var blockProps = useBlockProps();
			var inspector = el(
				InspectorControls,
				null,
				el(
					PanelBody,
					{ title: __( 'Form settings', 'glixform' ) },
					formSelect( props ),
					el( ToggleControl, {
						label: __( 'Show form title', 'glixform' ),
						checked: props.attributes.showTitle,
						onChange: function ( value ) {
							props.setAttributes( { showTitle: value } );
						},
					} )
				)
			);

			if ( ! props.attributes.formId ) {
				return el(
					'div',
					blockProps,
					inspector,
					el(
						Placeholder,
						{ icon: 'feedback', label: __( 'Glixform', 'glixform' ) },
						data.forms.length
							? formSelect( props )
							: el( 'a', { href: data.newFormUrl, target: '_blank', rel: 'noopener' }, __( 'Create your first form', 'glixform' ) )
					)
				);
			}

			return el(
				'div',
				blockProps,
				inspector,
				el( ServerSideRender, { block: 'glixform/form', attributes: props.attributes } )
			);
		},
		save: function () {
			return null;
		},
	} );
} )( window.wp );

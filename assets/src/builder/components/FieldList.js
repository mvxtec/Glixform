/**
 * The form's field list: select, reorder (drag or keyboard), duplicate, delete.
 * Fields can also be dropped here from the palette.
 */
import { useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { Button } from '@wordpress/components';
import { Icon, copy, trash, dragHandle } from '@wordpress/icons';
import {
	DndContext,
	KeyboardSensor,
	PointerSensor,
	closestCenter,
	useSensor,
	useSensors,
} from '@dnd-kit/core';
import {
	SortableContext,
	sortableKeyboardCoordinates,
	useSortable,
	verticalListSortingStrategy,
} from '@dnd-kit/sortable';
import { CSS } from '@dnd-kit/utilities';
import { config } from '../config';

function FieldCard( { field, page, selected, dispatch } ) {
	const type = config.types[ field.type ] || {
		name: field.type,
		icon: 'dashicons-warning',
	};
	const {
		attributes,
		listeners,
		setNodeRef,
		setActivatorNodeRef,
		transform,
		transition,
		isDragging,
	} = useSortable( { id: field.id } );
	const style = {
		transform: CSS.Transform.toString( transform ),
		transition,
	};
	const isBreak = field.type === 'pagebreak';

	const classes = [ 'gf-card' ];
	if ( selected ) {
		classes.push( 'is-selected' );
	}
	if ( isDragging ) {
		classes.push( 'is-dragging' );
	}
	if ( isBreak ) {
		classes.push( 'is-pagebreak' );
	}

	return (
		<li
			ref={ setNodeRef }
			style={ style }
			className={ classes.join( ' ' ) }
			data-field-id={ field.id }
		>
			<button
				type="button"
				ref={ setActivatorNodeRef }
				className="gf-card-handle"
				aria-label={ __(
					'Drag to reorder. Use space and arrow keys with the keyboard.',
					'glixform'
				) }
				{ ...attributes }
				{ ...listeners }
			>
				<Icon icon={ dragHandle } size={ 20 } />
			</button>
			<button
				type="button"
				className="gf-card-main"
				aria-pressed={ selected }
				onClick={ () => dispatch( { type: 'SELECT', id: field.id } ) }
			>
				<span
					className={ 'gf-card-icon dashicons ' + type.icon }
					aria-hidden="true"
				/>
				<span className="gf-card-text">
					<span className="gf-card-label">
						{ isBreak
							? sprintf(
									/* translators: %d: page number */ __(
										'Page %d',
										'glixform'
									),
									page
							  ) + ( field.label ? ' · ' + field.label : '' )
							: field.label || type.name }
						{ field.required && (
							<span
								className="gf-required"
								aria-label={ __( 'Required', 'glixform' ) }
							>
								*
							</span>
						) }
					</span>
					<span className="gf-card-meta">
						{ type.name } · #{ field.id }
						{ field.conditional?.enabled && (
							<span className="gf-badge gf-badge-logic">
								{ __( 'Conditional', 'glixform' ) }
							</span>
						) }
					</span>
				</span>
			</button>
			<span className="gf-card-actions">
				<Button
					size="small"
					icon={ copy }
					label={ __( 'Duplicate', 'glixform' ) }
					onClick={ () =>
						dispatch( { type: 'DUPLICATE_FIELD', id: field.id } )
					}
				/>
				<Button
					size="small"
					icon={ trash }
					isDestructive
					label={ __( 'Delete', 'glixform' ) }
					onClick={ () =>
						dispatch( { type: 'DELETE_FIELD', id: field.id } )
					}
				/>
			</span>
		</li>
	);
}

export default function FieldList( {
	fields,
	selectedId,
	dispatch,
	onDropNew,
} ) {
	const [ dropIndex, setDropIndex ] = useState( null );
	const sensors = useSensors(
		useSensor( PointerSensor, { activationConstraint: { distance: 4 } } ),
		useSensor( KeyboardSensor, {
			coordinateGetter: sortableKeyboardCoordinates,
		} )
	);

	const onDragEnd = ( { active, over } ) => {
		if ( ! over || active.id === over.id ) {
			return;
		}
		const from = fields.findIndex( ( f ) => f.id === active.id );
		const to = fields.findIndex( ( f ) => f.id === over.id );
		dispatch( { type: 'MOVE_FIELD', from, to } );
	};

	// Palette drops use native drag and drop.
	const indexFromEvent = ( e ) => {
		const cards = Array.from(
			e.currentTarget.querySelectorAll( '.gf-card' )
		);
		for ( let i = 0; i < cards.length; i++ ) {
			const box = cards[ i ].getBoundingClientRect();
			if ( e.clientY < box.top + box.height / 2 ) {
				return i;
			}
		}
		return cards.length;
	};

	let page = 1;
	const pages = fields.map( ( f ) =>
		f.type === 'pagebreak' ? ++page : page
	);
	const hasPages = page > 1;

	return (
		<div
			className={
				'gf-field-list-wrap' +
				( dropIndex !== null ? ' is-drop-target' : '' )
			}
			onDragOver={ ( e ) => {
				if (
					e.dataTransfer.types.includes(
						'application/x-glixform-field'
					)
				) {
					e.preventDefault();
					setDropIndex( indexFromEvent( e ) );
				}
			} }
			onDragLeave={ ( e ) => {
				if ( ! e.currentTarget.contains( e.relatedTarget ) ) {
					setDropIndex( null );
				}
			} }
			onDrop={ ( e ) => {
				const type = e.dataTransfer.getData(
					'application/x-glixform-field'
				);
				if ( type ) {
					e.preventDefault();
					onDropNew( type, indexFromEvent( e ) );
				}
				setDropIndex( null );
			} }
		>
			{ hasPages && (
				<div className="gf-page-label">
					{ __( 'Page 1', 'glixform' ) }
				</div>
			) }
			{ fields.length === 0 ? (
				<div className="gf-empty">
					<span
						className="dashicons dashicons-welcome-add-page"
						aria-hidden="true"
					/>
					<strong>{ __( 'Your form is empty', 'glixform' ) }</strong>
					<span>
						{ __(
							'Pick a field on the left, or drag one here.',
							'glixform'
						) }
					</span>
				</div>
			) : (
				<DndContext
					sensors={ sensors }
					collisionDetection={ closestCenter }
					onDragEnd={ onDragEnd }
				>
					<SortableContext
						items={ fields.map( ( f ) => f.id ) }
						strategy={ verticalListSortingStrategy }
					>
						<ol className="gf-cards">
							{ fields.map( ( field, index ) => (
								<FieldCardWithDrop
									key={ field.id }
									showDrop={ dropIndex === index }
									field={ field }
									page={ pages[ index ] }
									selected={ field.id === selectedId }
									dispatch={ dispatch }
								/>
							) ) }
							{ dropIndex === fields.length && (
								<li
									className="gf-drop-line"
									aria-hidden="true"
								/>
							) }
						</ol>
					</SortableContext>
				</DndContext>
			) }
		</div>
	);
}

function FieldCardWithDrop( { showDrop, ...props } ) {
	return (
		<>
			{ showDrop && <li className="gf-drop-line" aria-hidden="true" /> }
			<FieldCard { ...props } />
		</>
	);
}

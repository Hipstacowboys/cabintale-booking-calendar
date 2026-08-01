/**
 * Block editor UI for cabintale/widget.
 *
 * Written as plain JavaScript on purpose — no JSX, no build step. The editor
 * surface is a placeholder card and four controls, which is not worth a
 * toolchain, and unbuilt source is the easiest thing for a plugin reviewer to
 * read.
 */
( function ( blocks, element, blockEditor, components, i18n ) {
	'use strict';

	var el = element.createElement;
	var __ = i18n.__;

	var KINDS = [
		{ value: 'place', label: __( 'Place — availability calendar', 'cabintale-booking-calendar' ) },
		{ value: 'service', label: __( 'Service — time slots', 'cabintale-booking-calendar' ) },
		{ value: 'checkout', label: __( 'Product — checkout button', 'cabintale-booking-calendar' ) },
	];

	function kindLabel( kind ) {
		for ( var i = 0; i < KINDS.length; i++ ) {
			if ( KINDS[ i ].value === kind ) {
				return KINDS[ i ].label;
			}
		}
		return KINDS[ 0 ].label;
	}

	/**
	 * The editor deliberately shows a static card rather than a live iframe of
	 * the widget. The editor canvas is itself an iframe in current WordPress, and
	 * the booking dialog opens as a full-viewport overlay driven by postMessage —
	 * nesting that inside the editor invites the stacked-overlay bugs Cabintale
	 * has already paid for once.
	 */
	function edit( props ) {
		var atts = props.attributes;
		var setAttributes = props.setAttributes;
		var blockProps = blockEditor.useBlockProps( { className: 'cabintale-widget-placeholder' } );

		var inspector = el(
			blockEditor.InspectorControls,
			null,
			el(
				components.PanelBody,
				{ title: __( 'Widget', 'cabintale-booking-calendar' ), initialOpen: true },
				el( components.SelectControl, {
					label: __( 'Type', 'cabintale-booking-calendar' ),
					value: atts.kind,
					options: KINDS,
					onChange: function ( value ) {
						setAttributes( { kind: value } );
					},
					__nextHasNoMarginBottom: true,
				} ),
				el( components.TextControl, {
					label: __( 'Widget ID', 'cabintale-booking-calendar' ),
					value: atts.token,
					onChange: function ( value ) {
						setAttributes( { token: value.trim() } );
					},
					help: __(
						'Leave empty to use the default widget from Settings → Cabintale.',
						'cabintale-booking-calendar'
					),
					__nextHasNoMarginBottom: true,
				} ),
				el( components.ToggleControl, {
					label: __( 'Show border', 'cabintale-booking-calendar' ),
					checked: atts.border,
					onChange: function ( value ) {
						setAttributes( { border: value } );
					},
					__nextHasNoMarginBottom: true,
				} ),
				'place' === atts.kind
					? el( components.ToggleControl, {
							label: __( 'Availability only (no booking form)', 'cabintale-booking-calendar' ),
							checked: atts.availabilityOnly,
							onChange: function ( value ) {
								setAttributes( { availabilityOnly: value } );
							},
							__nextHasNoMarginBottom: true,
					  } )
					: null
			)
		);

		// Both states use core's Placeholder so the block inherits WordPress admin
		// typography and spacing instead of carrying styles of its own.
		var body = atts.token
			? el( components.Placeholder, {
					icon: 'calendar-alt',
					label: kindLabel( atts.kind ),
					instructions:
						__( 'Widget', 'cabintale-booking-calendar' ) +
						' ' +
						atts.token.slice( 0, 8 ) +
						'… — ' +
						__( 'the live widget appears on the published page.', 'cabintale-booking-calendar' ),
			  } )
			: el(
					components.Placeholder,
					{
						icon: 'calendar-alt',
						label: __( 'Cabintale booking widget', 'cabintale-booking-calendar' ),
						instructions: __(
							'Add your widget ID in the block settings, or set a default widget in Settings → Cabintale. You need a free Cabintale account to get one.',
							'cabintale-booking-calendar'
						),
					},
					el(
						components.ExternalLink,
						{ href: 'https://cabintale.com/' },
						__( 'Create a free Cabintale account', 'cabintale-booking-calendar' )
					)
			  );

		return el( 'div', blockProps, inspector, body );
	}

	blocks.registerBlockType( 'cabintale/widget', {
		edit: edit,
		// Dynamic block: PHP renders the front end, so nothing is saved to post
		// content. That also keeps wp_kses from stripping <cabintale-root>.
		save: function () {
			return null;
		},
	} );
} )( window.wp.blocks, window.wp.element, window.wp.blockEditor, window.wp.components, window.wp.i18n );

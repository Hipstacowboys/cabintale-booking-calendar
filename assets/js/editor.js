/**
 * Block editor UI for cabintale/widget.
 *
 * Written as plain JavaScript on purpose — no JSX, no build step. The editor
 * surface is a placeholder card and a few controls, which is not worth a
 * toolchain, and unbuilt source is the easiest thing for a plugin reviewer to
 * read.
 *
 * The widget list comes from this site's own REST route, not from Cabintale
 * directly: the API token stays on the server and the browser never sees it.
 */
( function ( blocks, element, blockEditor, components, i18n, apiFetch ) {
	'use strict';

	var el = element.createElement;
	var useState = element.useState;
	var useEffect = element.useEffect;
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
	 * "Chata Beskydy — Main calendar", falling back sensibly when either half is
	 * missing, so the dropdown never shows a bare dash.
	 */
	function widgetLabel( widget ) {
		if ( widget.group && widget.name ) {
			return widget.group + ' — ' + widget.name;
		}
		return widget.name || widget.group || widget.token.slice( 0, 8 ) + '…';
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

		var state = useState( { loading: true, connected: false, widgets: [] } );
		var connection = state[ 0 ];
		var setConnection = state[ 1 ];

		useEffect( function () {
			var cancelled = false;

			apiFetch( { path: '/cabintale/v1/widgets' } )
				.then( function ( data ) {
					if ( ! cancelled ) {
						setConnection( {
							loading: false,
							connected: !! data.connected,
							widgets: data.widgets || [],
						} );
					}
				} )
				.catch( function () {
					if ( ! cancelled ) {
						setConnection( { loading: false, connected: false, widgets: [] } );
					}
				} );

			return function () {
				cancelled = true;
			};
		}, [] );

		// Picking a widget sets its type too — the two always describe the same
		// widget, and letting them drift is how you get a 404 inside the iframe.
		function chooseWidget( token ) {
			for ( var i = 0; i < connection.widgets.length; i++ ) {
				if ( connection.widgets[ i ].token === token ) {
					setAttributes( { token: token, kind: connection.widgets[ i ].kind } );
					return;
				}
			}
			setAttributes( { token: token } );
		}

		var picker;

		if ( connection.loading ) {
			picker = el( components.Spinner );
		} else if ( connection.connected && connection.widgets.length ) {
			var options = [ { value: '', label: __( '— Select a widget —', 'cabintale-booking-calendar' ) } ];

			connection.widgets.forEach( function ( widget ) {
				options.push( { value: widget.token, label: widgetLabel( widget ) } );
			} );

			picker = el( components.SelectControl, {
				label: __( 'Widget', 'cabintale-booking-calendar' ),
				value: atts.token,
				options: options,
				onChange: chooseWidget,
				__nextHasNoMarginBottom: true,
			} );
		} else {
			picker = el(
				'p',
				null,
				connection.connected
					? __( 'No widgets on your Cabintale account yet.', 'cabintale-booking-calendar' )
					: __( 'Connect your Cabintale account in Settings → Cabintale to choose widgets by name.', 'cabintale-booking-calendar' )
			);
		}

		var inspector = el(
			blockEditor.InspectorControls,
			null,
			el(
				components.PanelBody,
				{ title: __( 'Widget', 'cabintale-booking-calendar' ), initialOpen: true },
				picker,
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
			),
			el(
				components.PanelBody,
				{ title: __( 'Advanced', 'cabintale-booking-calendar' ), initialOpen: false },
				el( components.SelectControl, {
					label: __( 'Type', 'cabintale-booking-calendar' ),
					value: atts.kind,
					options: KINDS,
					onChange: function ( value ) {
						setAttributes( { kind: value } );
					},
					help: __(
						'Set automatically when you pick a widget above. It must match the widget the ID belongs to.',
						'cabintale-booking-calendar'
					),
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
				} )
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
						instructions: connection.connected
							? __( 'Choose a widget in the block settings.', 'cabintale-booking-calendar' )
							: __(
									'Connect your Cabintale account in Settings → Cabintale, then choose a widget here. A free account takes a couple of minutes.',
									'cabintale-booking-calendar'
							  ),
					},
					connection.connected
						? null
						: el(
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
} )(
	window.wp.blocks,
	window.wp.element,
	window.wp.blockEditor,
	window.wp.components,
	window.wp.i18n,
	window.wp.apiFetch
);

( function ( wp ) {
	'use strict';
	const { createElement: el } = wp.element;
	const { __ } = wp.i18n;
	const { InspectorControls, useBlockProps } = wp.blockEditor;
	const { PanelBody, SelectControl, RangeControl } = wp.components;
	const ServerSideRender = wp.serverSideRender;
	const consoles = [
		[ '', __( 'All', 'you-emulator' ) ],
		[ 'nes', 'NES' ],
		[ 'snes', 'SNES' ],
		[ 'segaMD', 'Sega Genesis' ],
		[ 'gba', 'Game Boy Advance' ],
		[ 'psx', 'PlayStation' ],
		[ 'psp', 'PSP' ],
		[ 'n64', 'Nintendo 64' ],
	];

	wp.blocks.registerBlockType( 'you-emulator/library', {
		apiVersion: 3,
		title: __( 'Retro game library', 'you-emulator' ),
		icon: 'games',
		category: 'widgets',
		attributes: {
			console: { type: 'string', default: '' },
			perPage: { type: 'number', default: 0 },
		},
		edit( props ) {
			const { attributes: a, setAttributes: set } = props;
			return el(
				'div',
				useBlockProps(),
				el(
					InspectorControls,
					null,
					el(
						PanelBody,
						{ title: __( 'Library', 'you-emulator' ) },
						el( SelectControl, {
							label: __( 'Initial console', 'you-emulator' ),
							value: a.console,
							options: consoles.map( ( [ value, label ] ) => ( { value, label } ) ),
							onChange: ( v ) => set( { console: v } ),
						} ),
						el( RangeControl, {
							label: __( 'Games per page (0 = default)', 'you-emulator' ),
							value: a.perPage,
							min: 0,
							max: 120,
							onChange: ( v ) => set( { perPage: v || 0 } ),
						} )
					)
				),
				el( ServerSideRender, { block: 'you-emulator/library', attributes: a } )
			);
		},
		save: () => null,
	} );
}( window.wp ) );

/**
 * Gamepad combos (default LB+RB+X save, LB+RB+B load, LB+RB+Y fullscreen).
 *
 * While all modifier buttons of a combo are held, its trigger button is hidden
 * from the emulator so the game does not receive it.
 */
( function () {
	'use strict';

	const nativeGet = navigator.getGamepads ? navigator.getGamepads.bind( navigator ) : null;
	let combos = {};
	let masked = new Set();

	const pressed = ( pad, i ) => !! ( pad && pad.buttons[ i ] && ( pad.buttons[ i ].pressed || pad.buttons[ i ].value > 0.5 ) );

	function computeMask( pad ) {
		const out = new Set();
		Object.values( combos ).forEach( ( combo ) => {
			const mods = combo.slice( 0, -1 );
			if ( mods.length && mods.every( ( b ) => pressed( pad, b ) ) ) {
				out.add( combo[ combo.length - 1 ] );
			}
		} );
		return out;
	}

	function wrapPad( pad ) {
		const mask = computeMask( pad );
		if ( ! mask.size ) {
			return pad;
		}
		const buttons = Array.from( pad.buttons, ( b, i ) => ( mask.has( i ) ? { pressed: false, touched: false, value: 0 } : b ) );
		return {
			id: pad.id,
			index: pad.index,
			connected: pad.connected,
			mapping: pad.mapping,
			timestamp: pad.timestamp,
			axes: pad.axes,
			buttons,
			vibrationActuator: pad.vibrationActuator,
			hapticActuators: pad.hapticActuators,
		};
	}

	if ( nativeGet ) {
		try {
			navigator.getGamepads = function () {
				return Array.from( nativeGet() || [], ( p ) => ( p ? wrapPad( p ) : p ) );
			};
		} catch ( e ) {
			// Read-only in some browsers; combos still work without masking.
		}
	}

	window.YouemGamepad = {
		attach( comboMap, handlers ) {
			combos = comboMap || {};
			const active = {};
			let raf = 0;
			const loop = () => {
				const pads = nativeGet ? nativeGet() || [] : [];
				Object.keys( combos ).forEach( ( action ) => {
					const combo = combos[ action ];
					let on = false;
					for ( const pad of pads ) {
						if ( pad && combo.every( ( b ) => pressed( pad, b ) ) ) {
							on = true;
							break;
						}
					}
					if ( on && ! active[ action ] && typeof handlers[ action ] === 'function' ) {
						handlers[ action ]( 'gamepad' );
					}
					active[ action ] = on;
				} );
				raf = requestAnimationFrame( loop );
			};
			const start = () => {
				if ( ! raf ) {
					raf = requestAnimationFrame( loop );
				}
			};
			window.addEventListener( 'gamepadconnected', start );
			if ( nativeGet && Array.from( nativeGet() || [] ).some( Boolean ) ) {
				start();
			}
			return () => {
				cancelAnimationFrame( raf );
				raf = 0;
				masked = new Set();
			};
		},
	};
}() );

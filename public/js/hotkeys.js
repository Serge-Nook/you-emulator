/**
 * Keyboard shortcuts: Shift+1 quick save, Shift+2 quick load, Shift+3 fullscreen.
 */
( function () {
	'use strict';

	const MAP = { Digit1: 'save', Digit2: 'load', Digit3: 'fullscreen', Numpad1: 'save', Numpad2: 'load', Numpad3: 'fullscreen' };

	function isTyping( el ) {
		if ( ! el || el === document.body ) {
			return false;
		}
		const tag = el.tagName;
		if ( tag === 'TEXTAREA' || tag === 'SELECT' || el.isContentEditable ) {
			return true;
		}
		if ( tag === 'INPUT' ) {
			return ! [ 'button', 'checkbox', 'radio', 'range', 'submit', 'reset', 'color' ].includes( ( el.type || '' ).toLowerCase() );
		}
		return false;
	}

	window.YouemHotkeys = {
		attach( handlers ) {
			const held = new Set();
			const down = ( e ) => {
				const action = MAP[ e.code ];
				if ( ! action || ! e.shiftKey || e.ctrlKey || e.altKey || e.metaKey || isTyping( e.target ) ) {
					return;
				}
				e.preventDefault();
				e.stopImmediatePropagation();
				if ( e.repeat || held.has( e.code ) ) {
					return;
				}
				held.add( e.code );
				if ( typeof handlers[ action ] === 'function' ) {
					handlers[ action ]( 'keyboard' );
				}
			};
			const up = ( e ) => {
				if ( held.delete( e.code ) ) {
					e.stopImmediatePropagation();
				}
			};
			window.addEventListener( 'keydown', down, true );
			window.addEventListener( 'keyup', up, true );
			window.addEventListener( 'blur', () => held.clear() );
			return () => {
				window.removeEventListener( 'keydown', down, true );
				window.removeEventListener( 'keyup', up, true );
			};
		},
	};
}() );

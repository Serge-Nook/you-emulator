( function () {
	'use strict';
	const sel = document.getElementById( 'youem-provider' );
	if ( ! sel ) {
		return;
	}
	const sync = () => {
		document.querySelectorAll( '[class^="youem-provider-"]' ).forEach( ( row ) => {
			row.hidden = ! row.classList.contains( 'youem-provider-' + sel.value );
		} );
	};
	sel.addEventListener( 'change', sync );
	sync();
}() );

( function () {
	'use strict';
	const root = document.querySelector( '.youem-controls' );
	if ( ! root ) {
		return;
	}
	const { __ } = window.wp.i18n;
	const labels = JSON.parse( root.dataset.keys || '{}' );
	// Same keyCode table as EmulatorJS keyMap.
	const codes = { 8: 'backspace', 9: 'tab', 13: 'enter', 16: 'shift', 17: 'ctrl', 18: 'alt', 19: 'pause/break', 20: 'caps lock', 27: 'escape', 32: 'space', 33: 'page up', 34: 'page down', 35: 'end', 36: 'home', 37: 'left arrow', 38: 'up arrow', 39: 'right arrow', 40: 'down arrow', 45: 'insert', 46: 'delete', 91: 'left window key', 92: 'right window key', 93: 'select key', 106: 'multiply', 107: 'add', 109: 'subtract', 110: 'decimal point', 111: 'divide', 144: 'num lock', 145: 'scroll lock', 186: 'semi-colon', 187: 'equal sign', 188: 'comma', 189: 'dash', 190: 'period', 191: 'forward slash', 192: 'grave accent', 219: 'open bracket', 220: 'back slash', 221: 'close braket', 222: 'single quote' };
	for ( let i = 0; i <= 9; i++ ) {
		codes[ 48 + i ] = String( i );
		codes[ 96 + i ] = 'numpad ' + i;
	}
	for ( let i = 0; i < 26; i++ ) {
		codes[ 65 + i ] = String.fromCharCode( 97 + i );
	}
	for ( let i = 1; i <= 12; i++ ) {
		codes[ 111 + i ] = 'f' + i;
	}

	const label = ( key ) => ( key ? labels[ key ] || key : '—' );

	const set = ( row, key ) => {
		row.querySelector( '.youem-controls__key' ).value = key;
		row.querySelector( '.youem-controls__capture' ).textContent = label( key );
	};

	const check = ( panel ) => {
		const seen = {};
		const rows = panel.querySelectorAll( '.youem-controls__row' );
		rows.forEach( ( row ) => {
			[ row.querySelector( '.youem-controls__key' ).value, 'pad:' + row.querySelector( '.youem-controls__pad' ).value ].forEach( ( v ) => {
				if ( v && v !== 'pad:' ) {
					seen[ v ] = ( seen[ v ] || 0 ) + 1;
				}
			} );
		} );
		rows.forEach( ( row ) => {
			const key = row.querySelector( '.youem-controls__key' ).value;
			const pad = row.querySelector( '.youem-controls__pad' ).value;
			row.querySelector( '.youem-controls__capture' ).classList.toggle( 'is-duplicate', !! key && seen[ key ] > 1 );
			row.querySelector( '.youem-controls__pad' ).classList.toggle( 'is-duplicate', !! pad && seen[ 'pad:' + pad ] > 1 );
		} );
	};

	let active = null;
	const stop = () => {
		if ( active ) {
			active.classList.remove( 'is-listening' );
			set( active.closest( '.youem-controls__row' ), active.closest( '.youem-controls__row' ).querySelector( '.youem-controls__key' ).value );
			active = null;
		}
	};

	root.addEventListener( 'click', ( e ) => {
		const tab = e.target.closest( '.youem-controls__tab' );
		if ( tab ) {
			root.querySelectorAll( '.youem-controls__tab' ).forEach( ( t ) => {
				const on = t === tab;
				t.classList.toggle( 'is-active', on );
				t.setAttribute( 'aria-selected', on ? 'true' : 'false' );
			} );
			root.querySelectorAll( '.youem-controls__panel' ).forEach( ( p ) => {
				p.hidden = p.dataset.console !== tab.dataset.console;
			} );
			return;
		}
		const capture = e.target.closest( '.youem-controls__capture' );
		if ( capture ) {
			stop();
			active = capture;
			capture.focus();
			capture.classList.add( 'is-listening' );
			capture.textContent = __( 'Press a key…', 'you-emulator' );
			return;
		}
		const clear = e.target.closest( '.youem-controls__clear' );
		if ( clear ) {
			stop();
			set( clear.closest( '.youem-controls__row' ), '' );
			check( clear.closest( '.youem-controls__panel' ) );
			return;
		}
		const reset = e.target.closest( '.youem-controls__reset' );
		if ( reset ) {
			stop();
			const panel = reset.closest( '.youem-controls__panel' );
			panel.querySelectorAll( '.youem-controls__row' ).forEach( ( row ) => {
				set( row, row.dataset.defaultKey );
				row.querySelector( '.youem-controls__pad' ).value = row.dataset.defaultPad;
			} );
			check( panel );
		}
	} );

	root.addEventListener( 'keydown', ( e ) => {
		if ( ! active || e.target !== active ) {
			return;
		}
		if ( e.key === 'Tab' && ! e.shiftKey && ! e.ctrlKey ) {
			stop();
			return;
		}
		e.preventDefault();
		const row = active.closest( '.youem-controls__row' );
		if ( e.keyCode === 27 ) {
			stop();
			return;
		}
		const key = e.keyCode === 8 ? '' : codes[ e.keyCode ];
		if ( key === undefined ) {
			return;
		}
		row.querySelector( '.youem-controls__key' ).value = key;
		stop();
		check( row.closest( '.youem-controls__panel' ) );
	} );

	root.addEventListener( 'focusout', ( e ) => {
		if ( e.target === active ) {
			stop();
		}
	} );
	root.addEventListener( 'change', ( e ) => {
		if ( e.target.classList.contains( 'youem-controls__pad' ) ) {
			check( e.target.closest( '.youem-controls__panel' ) );
		}
	} );
	root.querySelectorAll( '.youem-controls__panel' ).forEach( check );
}() );

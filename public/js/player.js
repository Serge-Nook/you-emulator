/* global youemPlayer, wp */
( function () {
	'use strict';

	const { __, sprintf } = wp.i18n;
	const P = window.youemPlayer;
	const root = document.querySelector( '.youem-game[data-game="' + P.gameId + '"]' );
	if ( ! root || ! P.romUrl ) {
		return;
	}
	const stage = root.querySelector( '.youem-stage' );
	const toast = root.querySelector( '.youem-toast' );
	const panel = root.querySelector( '.youem-saves' );
	const list = root.querySelector( '.youem-saves__list' );
	const toggle = root.querySelector( '.youem-saves-toggle' );
	const fsBtn = root.querySelector( '.youem-fullscreen' );
	let slots = P.slots || [];
	let started = false;
	let busy = false;
	let toastTimer;

	function say( msg, type ) {
		toast.textContent = msg;
		toast.className = 'youem-toast is-visible' + ( type ? ' is-' + type : '' );
		clearTimeout( toastTimer );
		toastTimer = setTimeout( () => ( toast.className = 'youem-toast' ), 2600 );
	}

	function emu() {
		return window.EJS_emulator && window.EJS_emulator.gameManager ? window.EJS_emulator : null;
	}

	const slotName = ( s ) => ( s === 0 ? __( 'Quick save', 'you-emulator' ) : sprintf( /* translators: %d: slot number. */ __( 'Slot %d', 'you-emulator' ), s ) );

	async function screenshot( e ) {
		try {
			if ( typeof e.takeScreenshot === 'function' ) {
				const r = await e.takeScreenshot( 'canvas', 'webp', 1 );
				const data = r.blob || r.screenshot;
				if ( data ) {
					return data instanceof Blob ? data : new Blob( [ data ], { type: 'image/' + ( r.format || 'png' ) } );
				}
			}
			const png = await e.gameManager.screenshot();
			return new Blob( [ png ], { type: 'image/png' } );
		} catch ( err ) {
			return null;
		}
	}

	async function upload( slot, state, shot ) {
		const body = new FormData();
		body.append( 'slot', String( slot ) );
		body.append( 'state', new Blob( [ state ], { type: 'application/octet-stream' } ), 'state' );
		if ( shot && shot.size < 1048576 ) {
			body.append( 'screenshot', shot, 'screenshot' );
		}
		await window.youem.api( 'games/' + P.gameId + '/save', { method: 'POST', body } );
		await refresh();
	}

	async function save( slot ) {
		const e = emu();
		if ( ! e || ! started ) {
			say( __( 'Start the game first.', 'you-emulator' ), 'error' );
			return;
		}
		if ( busy ) {
			return;
		}
		busy = true;
		try {
			const state = e.gameManager.getState();
			const shot = await screenshot( e );
			await upload( slot, state, shot );
			say( sprintf( /* translators: %s: slot name. */ __( 'Saved: %s', 'you-emulator' ), slotName( slot ) ), 'ok' );
		} catch ( err ) {
			say( err.message || __( 'Could not save.', 'you-emulator' ), 'error' );
		} finally {
			busy = false;
		}
	}

	async function load( slot ) {
		const e = emu();
		if ( ! e || ! started ) {
			say( __( 'Start the game first.', 'you-emulator' ), 'error' );
			return;
		}
		if ( busy ) {
			return;
		}
		busy = true;
		try {
			const res = await window.youem.api( 'games/' + P.gameId + '/load?slot=' + slot, { raw: true, cache: 'no-store' } );
			const data = new Uint8Array( await res.arrayBuffer() );
			e.gameManager.loadState( data );
			say( sprintf( /* translators: %s: slot name. */ __( 'Loaded: %s', 'you-emulator' ), slotName( slot ) ), 'ok' );
		} catch ( err ) {
			say( err.status === 404 ? __( 'This slot is empty.', 'you-emulator' ) : err.message, 'error' );
		} finally {
			busy = false;
		}
	}

	async function remove( slot ) {
		if ( ! window.confirm( __( 'Delete this save?', 'you-emulator' ) ) ) {
			return;
		}
		try {
			await window.youem.api( 'games/' + P.gameId + '/save?slot=' + slot, { method: 'DELETE' } );
			await refresh();
		} catch ( err ) {
			say( err.message, 'error' );
		}
	}

	async function refresh() {
		try {
			slots = ( await window.youem.api( 'games/' + P.gameId + '/saves', { cache: 'no-store' } ) ).slots;
		} catch ( e ) {}
		render();
	}

	function render() {
		list.innerHTML = '';
		slots.forEach( ( s ) => {
			const li = document.createElement( 'li' );
			li.className = 'youem-slot' + ( s.used ? ' is-used' : '' );
			const thumb = document.createElement( 'div' );
			thumb.className = 'youem-slot__thumb';
			if ( s.hasScreenshot ) {
				const img = document.createElement( 'img' );
				img.alt = '';
				img.loading = 'lazy';
				img.src = window.youem.url( 'games/' + P.gameId + '/saves/' + s.slot + '/screenshot?_wpnonce=' + window.youem.cfg.nonce + '&t=' + encodeURIComponent( s.updatedAt || '' ) );
				thumb.appendChild( img );
			}
			const info = document.createElement( 'div' );
			info.className = 'youem-slot__info';
			const name = document.createElement( 'strong' );
			name.textContent = slotName( s.slot );
			const time = document.createElement( 'span' );
			time.textContent = s.used && s.updatedAt ? new Date( s.updatedAt ).toLocaleString() : __( 'Empty', 'you-emulator' );
			info.append( name, time );
			const actions = document.createElement( 'div' );
			actions.className = 'youem-slot__actions';
			const btn = ( label, fn, cls ) => {
				const b = document.createElement( 'button' );
				b.type = 'button';
				b.className = 'youem-btn youem-btn--sm' + ( cls ? ' ' + cls : '' );
				b.textContent = label;
				b.addEventListener( 'click', fn );
				actions.appendChild( b );
				return b;
			};
			btn( __( 'Save', 'you-emulator' ), () => save( s.slot ), 'youem-btn--primary' );
			btn( __( 'Load', 'you-emulator' ), () => load( s.slot ) ).disabled = ! s.used;
			if ( s.used ) {
				btn( '×', () => remove( s.slot ), 'youem-btn--ghost' ).setAttribute( 'aria-label', __( 'Delete', 'you-emulator' ) );
			}
			li.append( thumb, info, actions );
			list.appendChild( li );
		} );
	}

	function isFs() {
		return document.fullscreenElement === stage || document.webkitFullscreenElement === stage;
	}

	function fullscreen() {
		if ( isFs() ) {
			( document.exitFullscreen || document.webkitExitFullscreen ).call( document );
			return;
		}
		const req = stage.requestFullscreen || stage.webkitRequestFullscreen;
		if ( req ) {
			Promise.resolve( req.call( stage, { navigationUI: 'hide' } ) ).catch( () => say( __( 'Fullscreen is not available.', 'you-emulator' ), 'error' ) );
		}
	}
	const onFs = () => {
		stage.classList.toggle( 'is-fullscreen', isFs() );
		fsBtn.setAttribute( 'aria-pressed', isFs() ? 'true' : 'false' );
	};
	document.addEventListener( 'fullscreenchange', onFs );
	document.addEventListener( 'webkitfullscreenchange', onFs );
	fsBtn.addEventListener( 'click', fullscreen );

	toggle.addEventListener( 'click', () => {
		const open = panel.hidden;
		panel.hidden = ! open;
		toggle.setAttribute( 'aria-expanded', open ? 'true' : 'false' );
		if ( open ) {
			refresh();
		}
	} );

	// The administrator's layout wins over bindings EmulatorJS restored from localStorage.
	const applyControls = () => {
		const emu = window.EJS_emulator;
		if ( ! P.controls || ! emu || typeof emu.setupKeys !== 'function' ) {
			return;
		}
		emu.controls = JSON.parse( JSON.stringify( P.controls ) );
		emu.setupKeys();
		if ( typeof emu.checkGamepadInputs === 'function' ) {
			emu.checkGamepadInputs();
		}
	};

	const actions = { save: () => save( 0 ), load: () => load( 0 ), fullscreen };
	window.YouemHotkeys.attach( actions );
	window.YouemGamepad.attach( P.combos, actions );

	if ( P.threads && ! window.crossOriginIsolated ) {
		say( __( 'This system needs HTTPS and cross-origin isolation. Ask the site administrator.', 'you-emulator' ), 'error' );
	}

	window.EJS_player = '#youem-ejs-' + P.gameId;
	window.EJS_core = P.core;
	window.EJS_gameUrl = P.romUrl;
	window.EJS_gameName = P.title;
	window.EJS_pathtodata = P.dataPath;
	window.EJS_language = P.language;
	window.EJS_volume = P.volume;
	window.EJS_threads = !! P.threads;
	window.EJS_disableDatabases = true;
	window.EJS_color = getComputedStyle( root ).getPropertyValue( '--youem-accent' ).trim() || undefined;
	if ( P.biosUrl ) {
		window.EJS_biosUrl = P.biosUrl;
	}
	window.EJS_defaultOptions = { shader: P.shader };
	window.EJS_Buttons = { fullscreen: false, exitEmulation: false, gamepad: false };
	if ( P.controls ) {
		window.EJS_defaultControls = P.controls;
	}
	window.EJS_onGameStart = () => {
		started = true;
		applyControls();
		root.classList.add( 'is-running' );
	};
	window.EJS_onSaveState = () => save( 0 );
	window.EJS_onLoadState = () => load( 0 );

	document.addEventListener( 'youem:theme', () => {
		const c = getComputedStyle( root ).getPropertyValue( '--youem-accent' ).trim();
		if ( c && window.EJS_emulator && window.EJS_emulator.elements && window.EJS_emulator.elements.parent ) {
			window.EJS_emulator.elements.parent.style.setProperty( '--ejs-primary-color', c );
		}
	} );

	render();
	const s = document.createElement( 'script' );
	s.src = P.dataPath + 'loader.js';
	s.crossOrigin = 'anonymous';
	document.body.appendChild( s );
}() );

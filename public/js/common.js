/* global youemConfig */
( function () {
	'use strict';

	const cfg = window.youemConfig || {};

	function api( path, opts = {} ) {
		return fetch( cfg.rest + path, {
			credentials: 'same-origin',
			...opts,
			headers: { 'X-WP-Nonce': cfg.nonce, ...( opts.headers || {} ) },
		} ).then( async ( res ) => {
			if ( opts.raw ) {
				if ( ! res.ok ) {
					const data = await res.json().catch( () => ( {} ) );
					throw Object.assign( new Error( data.message || 'HTTP ' + res.status ), { status: res.status } );
				}
				return res;
			}
			const data = await res.json().catch( () => ( {} ) );
			if ( ! res.ok ) {
				throw Object.assign( new Error( data.message || 'HTTP ' + res.status ), { status: res.status } );
			}
			return data;
		} );
	}

	function applyTheme( slug ) {
		const re = /(^|\s)youem-theme-[\w-]+/g;
		document.body.className = document.body.className.replace( re, '' ) + ' youem-theme-' + slug;
		document.querySelectorAll( '.youem-theme-select' ).forEach( ( s ) => {
			s.value = slug;
		} );
		cfg.theme = slug;
		document.dispatchEvent( new CustomEvent( 'youem:theme', { detail: slug } ) );
	}

	function setTheme( slug ) {
		applyTheme( slug );
		if ( cfg.loggedIn ) {
			api( 'me/theme', {
				method: 'POST',
				headers: { 'Content-Type': 'application/json' },
				body: JSON.stringify( { theme: slug } ),
			} ).catch( () => {} );
		}
	}

	document.addEventListener( 'change', ( e ) => {
		if ( e.target.classList && e.target.classList.contains( 'youem-theme-select' ) ) {
			setTheme( e.target.value );
		}
	} );

	if ( cfg.theme && document.body && ! /youem-theme-/.test( document.body.className ) && document.querySelector( '.youem:not(.youem-guest)' ) ) {
		applyTheme( cfg.theme );
	}

	window.youem = { cfg, api, setTheme };
}() );

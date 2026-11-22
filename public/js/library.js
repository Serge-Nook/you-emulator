/* global wp */
( function () {
	'use strict';

	const { __ } = wp.i18n;

	function card( g ) {
		const a = document.createElement( 'a' );
		a.className = 'youem-card';
		a.href = g.link;
		a.dataset.id = g.id;
		a.dataset.console = g.console;
		const cover = document.createElement( 'div' );
		cover.className = 'youem-card__cover';
		if ( g.cover && g.cover.url ) {
			const img = document.createElement( 'img' );
			img.loading = 'lazy';
			img.decoding = 'async';
			img.alt = '';
			img.src = g.cover.url;
			if ( g.cover.srcset ) {
				img.srcset = g.cover.srcset;
				img.sizes = '(max-width: 600px) 50vw, 240px';
			}
			if ( g.cover.width && g.cover.height ) {
				img.width = g.cover.width;
				img.height = g.cover.height;
			}
			cover.appendChild( img );
		} else {
			const ph = document.createElement( 'span' );
			ph.className = 'youem-card__placeholder';
			ph.textContent = g.title.slice( 0, 1 ).toUpperCase();
			cover.appendChild( ph );
		}
		const badge = document.createElement( 'span' );
		badge.className = 'youem-badge';
		badge.textContent = g.consoleLabel;
		cover.appendChild( badge );
		const play = document.createElement( 'span' );
		play.className = 'youem-card__play';
		play.setAttribute( 'aria-hidden', 'true' );
		play.textContent = '▶';
		cover.appendChild( play );
		a.appendChild( cover );
		const body = document.createElement( 'div' );
		body.className = 'youem-card__body';
		const h = document.createElement( 'h3' );
		h.className = 'youem-card__title';
		h.textContent = g.title;
		body.appendChild( h );
		const meta = [ g.year, ( g.genres || [] ).map( ( x ) => x.name ).join( ', ' ) ].filter( Boolean ).join( ' · ' );
		if ( meta ) {
			const p = document.createElement( 'p' );
			p.className = 'youem-card__meta';
			p.textContent = meta;
			body.appendChild( p );
		}
		a.appendChild( body );
		return a;
	}

	function openModal( g ) {
		const wrap = document.createElement( 'div' );
		wrap.className = 'youem youem-modal';
		wrap.setAttribute( 'role', 'dialog' );
		wrap.setAttribute( 'aria-modal', 'true' );
		wrap.setAttribute( 'aria-label', g.title );
		const close = document.createElement( 'button' );
		close.type = 'button';
		close.className = 'youem-icon-btn youem-modal__close';
		close.setAttribute( 'aria-label', __( 'Close', 'you-emulator' ) );
		close.textContent = '×';
		const frame = document.createElement( 'iframe' );
		frame.className = 'youem-modal__frame';
		frame.src = g.link + ( g.link.indexOf( '?' ) === -1 ? '?' : '&' ) + 'youem_embed=1';
		frame.allow = 'fullscreen; gamepad; autoplay';
		frame.setAttribute( 'allowfullscreen', '' );
		frame.title = g.title;
		wrap.append( close, frame );
		document.body.appendChild( wrap );
		document.documentElement.classList.add( 'youem-modal-open' );
		const prev = document.activeElement;
		const done = () => {
			wrap.remove();
			document.documentElement.classList.remove( 'youem-modal-open' );
			document.removeEventListener( 'keydown', esc );
			if ( prev ) {
				prev.focus();
			}
		};
		const esc = ( e ) => {
			if ( e.key === 'Escape' && ! document.fullscreenElement ) {
				done();
			}
		};
		close.addEventListener( 'click', done );
		document.addEventListener( 'keydown', esc );
		frame.addEventListener( 'load', () => frame.focus() );
		close.focus();
	}

	function init( root ) {
		const opts = JSON.parse( root.dataset.youemLibrary || '{}' );
		const grid = root.querySelector( '.youem-grid' );
		const empty = root.querySelector( '.youem-empty' );
		const more = root.querySelector( '.youem-more' );
		const search = root.querySelector( '.youem-search' );
		const genre = root.querySelector( '.youem-genre' );
		const tabs = Array.from( root.querySelectorAll( '.youem-tab' ) );
		const games = new Map();
		const state = { console: opts.console || '', genre: opts.genre || '', search: '', page: 1, pages: 1 };
		let seq = 0;

		const params = new URLSearchParams( window.location.search );
		if ( params.get( 'yc' ) !== null ) {
			state.console = params.get( 'yc' );
		}
		if ( params.get( 'yg' ) ) {
			state.genre = params.get( 'yg' );
		}
		if ( params.get( 'ys' ) ) {
			state.search = params.get( 'ys' );
			search.value = state.search;
		}
		if ( genre ) {
			genre.value = state.genre;
		}

		function syncUrl() {
			const p = new URLSearchParams( window.location.search );
			[ [ 'yc', state.console ], [ 'yg', state.genre ], [ 'ys', state.search ] ].forEach( ( [ k, v ] ) => ( v ? p.set( k, v ) : p.delete( k ) ) );
			const qs = p.toString();
			window.history.replaceState( null, '', window.location.pathname + ( qs ? '?' + qs : '' ) + window.location.hash );
		}

		function load( append ) {
			const my = ++seq;
			grid.setAttribute( 'aria-busy', 'true' );
			root.classList.add( 'is-loading' );
			const q = new URLSearchParams( { page: String( state.page ), per_page: String( opts.perPage || 24 ) } );
			if ( state.console ) {
				q.set( 'console', state.console );
			}
			if ( state.genre ) {
				q.set( 'genre', state.genre );
			}
			if ( state.search ) {
				q.set( 'search', state.search );
			}
			window.youem
				.api( 'games?' + q.toString() )
				.then( ( res ) => {
					if ( my !== seq ) {
						return;
					}
					if ( ! append ) {
						grid.innerHTML = '';
						games.clear();
					}
					res.items.forEach( ( g ) => {
						games.set( String( g.id ), g );
						grid.appendChild( card( g ) );
					} );
					state.pages = res.pages;
					more.hidden = state.page >= res.pages;
					empty.hidden = res.total > 0;
				} )
				.catch( ( e ) => {
					grid.innerHTML = '';
					empty.hidden = false;
					empty.textContent = e.message;
				} )
				.finally( () => {
					if ( my === seq ) {
						grid.setAttribute( 'aria-busy', 'false' );
						root.classList.remove( 'is-loading' );
					}
				} );
		}

		function reset() {
			state.page = 1;
			syncUrl();
			load( false );
		}

		function selectTab() {
			tabs.forEach( ( t ) => t.setAttribute( 'aria-selected', t.dataset.console === state.console ? 'true' : 'false' ) );
		}

		tabs.forEach( ( t, i ) => {
			t.addEventListener( 'click', () => {
				state.console = t.dataset.console;
				selectTab();
				reset();
			} );
			t.addEventListener( 'keydown', ( e ) => {
				const d = { ArrowRight: 1, ArrowLeft: -1 }[ e.key ];
				if ( d ) {
					e.preventDefault();
					const n = tabs[ ( i + d + tabs.length ) % tabs.length ];
					n.focus();
					n.click();
				}
			} );
		} );
		let timer;
		search.addEventListener( 'input', () => {
			clearTimeout( timer );
			timer = setTimeout( () => {
				state.search = search.value.trim();
				reset();
			}, 300 );
		} );
		if ( genre ) {
			genre.addEventListener( 'change', () => {
				state.genre = genre.value;
				reset();
			} );
		}
		more.addEventListener( 'click', () => {
			state.page++;
			load( true );
		} );
		if ( 'IntersectionObserver' in window ) {
			new IntersectionObserver( ( entries ) => {
				if ( entries.some( ( e ) => e.isIntersecting ) && ! more.hidden && grid.getAttribute( 'aria-busy' ) !== 'true' ) {
					more.click();
				}
			}, { rootMargin: '400px' } ).observe( more );
		}
		grid.addEventListener( 'click', ( e ) => {
			const a = e.target.closest( '.youem-card' );
			if ( ! a || opts.playerMode !== 'modal' || e.ctrlKey || e.metaKey || e.shiftKey || e.button !== 0 ) {
				return;
			}
			const g = games.get( a.dataset.id );
			if ( ! g || g.console === 'psp' ) {
				return;
			}
			e.preventDefault();
			openModal( g );
		} );

		selectTab();
		load( false );
	}

	document.querySelectorAll( '[data-youem-library]' ).forEach( init );
}() );

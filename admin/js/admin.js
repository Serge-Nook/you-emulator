/* global youemAdmin, wp, jQuery */
( function ( $ ) {
	'use strict';

	const { __, sprintf } = wp.i18n;
	const cfg = window.youemAdmin;
	const api = ( path, opts = {} ) =>
		fetch( cfg.rest + path, {
			credentials: 'same-origin',
			...opts,
			headers: { 'X-WP-Nonce': cfg.nonce, ...( opts.headers || {} ) },
		} ).then( async ( r ) => {
			const data = await r.json().catch( () => ( {} ) );
			if ( ! r.ok ) {
				throw new Error( data.message || 'HTTP ' + r.status );
			}
			return data;
		} );

	const fmt = ( n ) => {
		const u = [ 'B', 'KB', 'MB', 'GB' ];
		let i = 0;
		while ( n >= 1024 && i < u.length - 1 ) {
			n /= 1024;
			i++;
		}
		return n.toFixed( i ? 1 : 0 ) + ' ' + u[ i ];
	};

	function gameId() {
		const el = document.querySelector( '.youem-rom' );
		const id = el ? parseInt( el.dataset.game, 10 ) : 0;
		return id || parseInt( $( '#post_ID' ).val(), 10 );
	}

	/* Console / core selects */
	const $console = $( '#youem_console' );
	const $core = $( '#youem_core' );
	const help = document.querySelector( '.youem-drop__help' );

	function syncConsole() {
		const def = cfg.consoles[ $console.val() ];
		$core.find( 'option' ).each( function () {
			const v = this.value;
			this.hidden = !! v && ( ! def || def.cores.indexOf( v ) === -1 );
		} );
		if ( $core.find( 'option:selected' ).prop( 'hidden' ) ) {
			$core.val( '' );
		}
		if ( help ) {
			help.textContent = def
				? sprintf( /* translators: %s: extensions. */ __( 'Allowed: %s', 'you-emulator' ), def.ext.map( ( e ) => '.' + e ).join( ' ' ) )
				: __( 'Select a console first.', 'you-emulator' );
		}
	}
	$console.on( 'change', syncConsole );
	syncConsole();

	/* Uploads */
	const drop = document.querySelector( '.youem-drop' );
	const input = document.querySelector( '.youem-drop__input' );
	const list = document.querySelector( '.youem-uploads' );
	let busy = false;

	async function handleFiles( files ) {
		if ( busy || ! files.length ) {
			return;
		}
		const code = $console.val();
		const def = cfg.consoles[ code ];
		if ( ! def ) {
			window.alert( __( 'Select a console first.', 'you-emulator' ) );
			return;
		}
		const id = gameId();
		if ( ! id ) {
			return;
		}
		for ( const f of files ) {
			const ext = f.name.split( '.' ).pop().toLowerCase();
			if ( def.ext.indexOf( ext ) === -1 && ! ( code === 'psx' && ext === 'bin' ) ) {
				window.alert( sprintf( /* translators: %s: file name. */ __( 'File type is not allowed for this console: %s', 'you-emulator' ), f.name ) );
				return;
			}
			if ( f.size > cfg.maxSize ) {
				window.alert( sprintf( /* translators: %s: file name. */ __( 'File is too large: %s', 'you-emulator' ), f.name ) );
				return;
			}
		}
		busy = true;
		drop.classList.add( 'is-busy' );
		list.innerHTML = '';
		const parts = [];
		try {
			for ( const f of files ) {
				const li = document.createElement( 'li' );
				li.innerHTML = '<span class="youem-uploads__name"></span> <progress max="100" value="0"></progress> <span class="youem-uploads__pct">0%</span>';
				li.querySelector( '.youem-uploads__name' ).textContent = f.name + ' (' + fmt( f.size ) + ')';
				list.appendChild( li );
				const up = new window.YouemUploader.Upload( f, cfg );
				parts.push(
					await up.run( ( p ) => {
						li.querySelector( 'progress' ).value = Math.round( p * 100 );
						li.querySelector( '.youem-uploads__pct' ).textContent = Math.round( p * 100 ) + '%';
					} )
				);
			}
			const status = document.createElement( 'li' );
			status.textContent = __( 'Checking and saving…', 'you-emulator' );
			list.appendChild( status );
			const res = await window.YouemUploader.assemble( cfg, id, code, parts );
			status.textContent = __( 'Done.', 'you-emulator' );
			document.querySelector( '.youem-rom__current' ).textContent = sprintf( /* translators: 1: file name, 2: size. */ __( 'Current file: %1$s (%2$s)', 'you-emulator' ), res.name, res.sizeHuman );
			if ( ! $( '#title' ).val() && res.title ) {
				$( '#title' ).val( res.title ).trigger( 'input' );
				$( '#title-prompt-text' ).addClass( 'screen-reader-text' );
			}
		} catch ( e ) {
			const err = document.createElement( 'li' );
			err.className = 'youem-error';
			err.textContent = e.message;
			list.appendChild( err );
		} finally {
			busy = false;
			drop.classList.remove( 'is-busy' );
			input.value = '';
		}
	}

	if ( drop ) {
		drop.addEventListener( 'click', () => input.click() );
		drop.addEventListener( 'keydown', ( e ) => {
			if ( e.key === 'Enter' || e.key === ' ' ) {
				e.preventDefault();
				input.click();
			}
		} );
		input.addEventListener( 'change', () => handleFiles( Array.from( input.files ) ) );
		[ 'dragenter', 'dragover' ].forEach( ( t ) =>
			drop.addEventListener( t, ( e ) => {
				e.preventDefault();
				drop.classList.add( 'is-over' );
			} )
		);
		[ 'dragleave', 'drop' ].forEach( ( t ) =>
			drop.addEventListener( t, ( e ) => {
				e.preventDefault();
				drop.classList.remove( 'is-over' );
			} )
		);
		drop.addEventListener( 'drop', ( e ) => handleFiles( Array.from( e.dataTransfer.files ) ) );
	}

	/* Cover */
	const $coverId = $( '#youem_cover_id' );
	const $coverSrc = $( '#youem_cover_source' );
	const $preview = $( '.youem-cover__preview' );
	const $remove = $( '.youem-cover__remove' );
	let frame;
	$( '.youem-cover__pick' ).on( 'click', () => {
		if ( ! frame ) {
			frame = wp.media( { title: __( 'Choose cover', 'you-emulator' ), library: { type: 'image' }, multiple: false } );
			frame.on( 'select', () => {
				const a = frame.state().get( 'selection' ).first().toJSON();
				const url = ( a.sizes && a.sizes.medium ? a.sizes.medium : a ).url;
				$coverId.val( a.id );
				$coverSrc.val( 'manual' );
				$preview.empty().append( $( '<img alt="">' ).attr( 'src', url ) );
				$remove.prop( 'hidden', false );
			} );
		}
		frame.open();
	} );
	$remove.on( 'click', () => {
		$coverId.val( '' );
		$coverSrc.val( '' );
		$preview.empty();
		$remove.prop( 'hidden', true );
	} );

	const $msg = $( '.youem-scrape__msg' );
	$( '.youem-scrape' ).on( 'click', function () {
		const btn = this;
		const title = $( '#title' ).val();
		if ( ! title || ! $console.val() ) {
			$msg.text( __( 'Enter the title and select a console first.', 'you-emulator' ) );
			return;
		}
		btn.disabled = true;
		$( btn ).next( '.spinner' ).addClass( 'is-active' );
		api( 'admin/scrape', {
			method: 'POST',
			headers: { 'Content-Type': 'application/json' },
			body: JSON.stringify( { game_id: gameId(), title, console: $console.val() } ),
		} )
			.then( ( res ) => {
				const i = res.info || {};
				[ 'year', 'developer', 'players' ].forEach( ( k ) => {
					const $f = $( '#youem_' + k );
					if ( ! $f.val() && i[ k ] ) {
						$f.val( i[ k ] );
					}
				} );
				if ( res.cover && res.coverId && $coverSrc.val() !== 'manual' ) {
					$coverId.val( res.coverId );
					$coverSrc.val( 'scraped' );
					$preview.empty().append( $( '<img alt="">' ).attr( 'src', res.cover.src ) );
					$remove.prop( 'hidden', false );
				}
				$msg.text( sprintf( /* translators: %s: game title. */ __( 'Found: %s. Reload the page to see new genres.', 'you-emulator' ), i.title || title ) );
			} )
			.catch( ( e ) => $msg.text( e.message ) )
			.finally( () => {
				btn.disabled = false;
				$( btn ).next( '.spinner' ).removeClass( 'is-active' );
			} );
	} );

	/* Access */
	$( 'input[name="youem_visibility"]' ).on( 'change', function () {
		$( '.youem-access__private' ).prop( 'hidden', this.value !== 'private' );
	} );
	const $results = $( '.youem-user-results' );
	const $chips = $( '.youem-user-chips' );
	let timer;
	$( '.youem-user-search' ).on( 'input', function () {
		clearTimeout( timer );
		const q = this.value.trim();
		if ( q.length < 2 ) {
			$results.empty();
			return;
		}
		timer = setTimeout( () => {
			api( 'admin/users?search=' + encodeURIComponent( q ) ).then( ( users ) => {
				$results.empty();
				users.forEach( ( u ) => {
					if ( $chips.find( 'input[value="' + u.id + '"]' ).length ) {
						return;
					}
					$( '<li><button type="button" class="button-link"></button></li>' )
						.find( 'button' )
						.text( u.label )
						.on( 'click', () => {
							const li = $( '<li></li>' ).text( u.label + ' ' );
							li.prepend( $( '<input type="hidden" name="youem_users[]">' ).val( u.id ) );
							li.append( $( '<button type="button" class="youem-chip-remove">&times;</button>' ).attr( 'aria-label', __( 'Remove', 'you-emulator' ) ) );
							$chips.append( li );
							$results.empty();
						} )
						.end()
						.appendTo( $results );
				} );
			} );
		}, 250 );
	} );
	$chips.on( 'click', '.youem-chip-remove', function () {
		$( this ).closest( 'li' ).remove();
	} );
}( jQuery ) );

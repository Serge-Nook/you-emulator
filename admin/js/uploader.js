/**
 * Resumable chunked uploader for You Emulator.
 */
( function () {
	'use strict';

	const sleep = ( ms ) => new Promise( ( r ) => setTimeout( r, ms ) );

	async function fingerprint( file ) {
		const raw = [ file.name, file.size, file.lastModified ].join( '|' );
		if ( window.crypto && crypto.subtle ) {
			const buf = await crypto.subtle.digest( 'SHA-256', new TextEncoder().encode( raw ) );
			return Array.from( new Uint8Array( buf ) ).slice( 0, 16 ).map( ( b ) => b.toString( 16 ).padStart( 2, '0' ) ).join( '' );
		}
		let h = 0;
		for ( let i = 0; i < raw.length; i++ ) {
			h = ( Math.imul( 31, h ) + raw.charCodeAt( i ) ) | 0;
		}
		return ( 'f' + ( h >>> 0 ).toString( 16 ) + file.size.toString( 16 ) ).padEnd( 32, '0' );
	}

	class Upload {
		constructor( file, opts ) {
			this.file = file;
			this.opts = opts;
			this.chunk = opts.chunkSize;
			this.total = Math.max( 1, Math.ceil( file.size / this.chunk ) );
			this.sent = 0;
			this.aborted = false;
		}

		headers() {
			return { 'X-WP-Nonce': this.opts.nonce };
		}

		async status() {
			const url = this.opts.rest + 'admin/upload-status?upload_id=' + encodeURIComponent( this.id );
			const res = await fetch( url, { headers: this.headers(), credentials: 'same-origin' } );
			if ( ! res.ok ) {
				return [];
			}
			return ( await res.json() ).received || [];
		}

		async sendChunk( index ) {
			const start = index * this.chunk;
			const body = new FormData();
			body.append( 'upload_id', this.id );
			body.append( 'index', String( index ) );
			body.append( 'total', String( this.total ) );
			body.append( 'chunk', this.file.slice( start, start + this.chunk ), 'chunk' );
			for ( let attempt = 0; attempt < 5; attempt++ ) {
				if ( this.aborted ) {
					throw new Error( 'aborted' );
				}
				try {
					const res = await fetch( this.opts.rest + 'admin/upload-chunk', {
						method: 'POST',
						headers: this.headers(),
						credentials: 'same-origin',
						body,
					} );
					if ( res.ok ) {
						return;
					}
					const err = await res.json().catch( () => ( {} ) );
					if ( res.status >= 400 && res.status < 500 && res.status !== 408 && res.status !== 429 ) {
						throw Object.assign( new Error( err.message || 'HTTP ' + res.status ), { fatal: true } );
					}
				} catch ( e ) {
					if ( e.fatal ) {
						throw e;
					}
				}
				await sleep( 1000 * Math.pow( 2, attempt ) );
			}
			throw new Error( 'Network error' );
		}

		async run( onProgress ) {
			this.id = await fingerprint( this.file );
			const done = new Set( await this.status() );
			const queue = [];
			for ( let i = 0; i < this.total; i++ ) {
				if ( ! done.has( i ) ) {
					queue.push( i );
				}
			}
			this.sent = done.size;
			onProgress( this.sent / this.total );
			const workers = Array.from( { length: Math.min( 3, queue.length ) }, async () => {
				while ( queue.length ) {
					const index = queue.shift();
					await this.sendChunk( index );
					this.sent++;
					onProgress( this.sent / this.total );
				}
			} );
			await Promise.all( workers );
			return { id: this.id, total: this.total, name: this.file.name };
		}

		abort() {
			this.aborted = true;
		}
	}

	window.YouemUploader = {
		Upload,
		async assemble( opts, gameId, consoleCode, parts ) {
			const res = await fetch( opts.rest + 'admin/assemble', {
				method: 'POST',
				headers: { 'X-WP-Nonce': opts.nonce, 'Content-Type': 'application/json' },
				credentials: 'same-origin',
				body: JSON.stringify( { game_id: gameId, console: consoleCode, files: parts } ),
			} );
			const data = await res.json().catch( () => ( {} ) );
			if ( ! res.ok ) {
				throw new Error( data.message || 'HTTP ' + res.status );
			}
			return data;
		},
	};
}() );

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

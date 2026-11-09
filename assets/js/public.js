/**
 * AI First SaaS Directory — small helpers that run alongside Alpine.
 *
 * @since 0.4.0
 */
( function () {
	'use strict';

	// Clear the flash message in the URL after showing it once so a refresh
	// doesn't re-trigger the banner.
	try {
		const params = new URLSearchParams( window.location.search );
		if ( params.has( 'aifirst_msg' ) || params.has( 'aifirst_submit' ) || params.has( 'aifirst_report' ) ) {
			setTimeout( function () {
				params.delete( 'aifirst_msg' );
				params.delete( 'aifirst_submit' );
				params.delete( 'aifirst_report' );
				const qs = params.toString();
				const url = window.location.pathname + ( qs ? '?' + qs : '' ) + window.location.hash;
				window.history.replaceState( {}, '', url );
			}, 3000 );
		}
	} catch ( err ) {
		// older browsers — ignore.
	}

	/**
	 * Fire a transient toast in the bottom-right corner.
	 *
	 * @since 0.4.1
	 * @param {string} message - Already-escaped plain text to display.
	 */
	window.AIFirstToast = function ( message ) {
		var el = document.createElement( 'div' );
		el.className = 'aifirst-toast';
		el.setAttribute( 'role', 'status' );
		el.textContent = String( message || '' );
		document.body.appendChild( el );
		// force reflow so the transition kicks in
		void el.offsetWidth;
		el.classList.add( 'is-visible' );
		setTimeout( function () {
			el.classList.remove( 'is-visible' );
			setTimeout( function () { el.remove(); }, 220 );
		}, 3600 );
	};

	// Small helper for the Compare page's selector submit.
	window.AIFirstGoCompare = function ( a, b, c ) {
		if ( ! window.AIFirstPublic ) return;
		let url = window.AIFirstPublic.compareBaseUrl;
		if ( a ) url += encodeURIComponent( a ) + '/';
		if ( a && b ) url += encodeURIComponent( b ) + '/';
		if ( a && b && c ) url += encodeURIComponent( c ) + '/';
		window.location.href = url;
	};
} )();

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

	/**
	 * Alpine factory for the "Report a change" modal on tool pages.
	 * The template passes a small JSON config: ajaxUrl + localized strings.
	 *
	 * @since 0.4.2
	 */
	window.AIFirstReportModal = function ( config ) {
		return {
			open:  false,
			busy:  false,
			error: '',
			async submit( e ) {
				if ( this.busy ) {
					return;
				}
				this.busy  = true;
				this.error = '';
				try {
					const fd = new FormData( e.target );
					fd.append( 'action', 'aifirst_submit_report' );
					const res = await fetch( config.ajaxUrl, {
						method:      'POST',
						credentials: 'same-origin',
						body:        fd,
					} );
					const data = await res.json().catch( function () { return null; } );
					if ( ! res.ok || ! data || ! data.success ) {
						this.error = ( data && data.data && data.data.message )
							? data.data.message
							: config.errorText;
						this.busy = false;
						return;
					}
					this.open = false;
					this.busy = false;
					e.target.reset();
					if ( window.AIFirstToast ) {
						window.AIFirstToast(
							data.data && data.data.message
								? data.data.message
								: config.successText
						);
					}
				} catch ( err ) {
					this.error = config.networkErrorText;
					this.busy  = false;
				}
			}
		};
	};

	/**
	 * Alpine factory for the public newsletter signup form.
	 * Posts to the aifirst_subscribe AJAX endpoint and shows a toast.
	 *
	 * @since 0.5.0
	 */
	window.AIFirstNewsletter = function ( config ) {
		return {
			sent:  false,
			busy:  false,
			email: '',
			error: '',
			async submit( e ) {
				if ( this.busy ) {
					return;
				}
				this.busy  = true;
				this.error = '';
				try {
					const fd = new FormData( e.target );
					fd.append( 'action', 'aifirst_subscribe' );
					const res = await fetch( config.ajaxUrl, {
						method:      'POST',
						credentials: 'same-origin',
						body:        fd,
					} );
					const data = await res.json().catch( function () { return null; } );
					if ( ! res.ok || ! data || ! data.success ) {
						this.error = ( data && data.data && data.data.message )
							? data.data.message
							: config.errorText;
						this.busy = false;
						return;
					}
					this.sent = true;
					this.busy = false;
					if ( window.AIFirstToast ) {
						window.AIFirstToast( config.successText );
					}
				} catch ( err ) {
					this.error = config.networkErrorText;
					this.busy  = false;
				}
			}
		};
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

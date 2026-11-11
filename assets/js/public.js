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

	/**
	 * Cookie helpers for the stack. Cookie name = 'aifirst_stack', value
	 * is a comma-separated slug list; mirrors to localStorage as a fallback.
	 *
	 * @since 0.6.0
	 */
	const STACK_COOKIE = 'aifirst_stack';
	const STACK_LOCAL  = 'aifirst_stack_v1';
	const STACK_MAX    = 50;
	const STACK_TTL_D  = 180; // days

	function aifReadCookie( name ) {
		try {
			const parts = ( document.cookie || '' ).split( ';' );
			for ( let i = 0; i < parts.length; i++ ) {
				const bits = parts[ i ].trim().split( '=' );
				if ( bits[ 0 ] === name ) {
					return decodeURIComponent( bits.slice( 1 ).join( '=' ) || '' );
				}
			}
		} catch ( err ) { /* ignore */ }
		return '';
	}

	function aifWriteCookie( name, value, days ) {
		try {
			const exp = new Date( Date.now() + days * 86400 * 1000 ).toUTCString();
			const secure = ( location.protocol === 'https:' ) ? '; Secure' : '';
			document.cookie = name + '=' + encodeURIComponent( value ) + '; expires=' + exp + '; path=/; SameSite=Lax' + secure;
		} catch ( err ) { /* ignore */ }
	}

	function aifReadStack() {
		let raw = aifReadCookie( STACK_COOKIE );
		if ( ! raw ) {
			try { raw = window.localStorage.getItem( STACK_LOCAL ) || ''; } catch ( err ) { /* ignore */ }
		}
		if ( ! raw ) { return []; }
		return raw.split( ',' )
			.map( function ( s ) { return String( s || '' ).trim().toLowerCase().replace( /[^a-z0-9-]/g, '' ); } )
			.filter( function ( s, i, arr ) { return s !== '' && arr.indexOf( s ) === i; } )
			.slice( 0, STACK_MAX );
	}

	function aifWriteStack( slugs ) {
		const value = slugs.join( ',' );
		aifWriteCookie( STACK_COOKIE, value, STACK_TTL_D );
		try { window.localStorage.setItem( STACK_LOCAL, value ); } catch ( err ) { /* ignore */ }
	}

	// Expose a tiny API for templates that need to clear / read the stack.
	window.AIFirstStack = {
		read:  aifReadStack,
		write: aifWriteStack,
		clear: function () {
			aifWriteCookie( STACK_COOKIE, '', -1 );
			try { window.localStorage.removeItem( STACK_LOCAL ); } catch ( err ) { /* ignore */ }
		},
		max: STACK_MAX,
	};

	// Register the shared Alpine store the moment Alpine is ready.
	document.addEventListener( 'alpine:init', function () {
		if ( ! window.Alpine ) { return; }
		const slugs = aifReadStack();
		window.Alpine.store( 'aifStack', {
			slugs: slugs,
			count: slugs.length,
			has( slug ) { return this.slugs.indexOf( slug ) >= 0; },
			add( slug ) {
				if ( this.slugs.length >= STACK_MAX ) { return 'full'; }
				if ( this.slugs.indexOf( slug ) >= 0 ) { return 'exists'; }
				this.slugs.push( slug );
				this.count = this.slugs.length;
				aifWriteStack( this.slugs );
				return 'added';
			},
			remove( slug ) {
				const i = this.slugs.indexOf( slug );
				if ( i < 0 ) { return 'missing'; }
				this.slugs.splice( i, 1 );
				this.count = this.slugs.length;
				aifWriteStack( this.slugs );
				return 'removed';
			}
		} );
	} );

	/**
	 * Alpine factory for the stack-toggle button.
	 *
	 * @since 0.6.0
	 */
	window.AIFirstStackToggle = function ( config ) {
		return {
			cfg: config,
			get inStack() {
				if ( ! this.$store || ! this.$store.aifStack ) { return false; }
				return this.$store.aifStack.has( this.cfg.toolId );
			},
			toggle() {
				if ( ! this.$store || ! this.$store.aifStack ) { return; }
				const already = this.$store.aifStack.has( this.cfg.toolId );
				if ( already ) {
					this.$store.aifStack.remove( this.cfg.toolId );
					if ( window.AIFirstToast ) { window.AIFirstToast( this.cfg.removedMsg ); }
					return;
				}
				const r = this.$store.aifStack.add( this.cfg.toolId );
				if ( 'full' === r ) {
					if ( window.AIFirstToast ) { window.AIFirstToast( this.cfg.fullMsg ); }
					return;
				}
				if ( window.AIFirstToast ) { window.AIFirstToast( this.cfg.addedMsg ); }
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

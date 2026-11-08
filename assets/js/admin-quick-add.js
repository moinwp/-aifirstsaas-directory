/**
 * AI First — admin Quick Add + Settings interactions.
 *
 * @since 0.2.0
 */
( function ( $ ) {
	'use strict';

	if ( typeof window.AIFirstAdmin === 'undefined' ) {
		return;
	}

	const cfg = window.AIFirstAdmin;

	// ------------- Helpers -------------

	function getFieldValue( $el ) {
		const type = ( $el.attr( 'type' ) || '' ).toLowerCase();
		if ( type === 'checkbox' ) {
			return $el.is( ':checked' ) ? 1 : 0;
		}
		return $el.val();
	}

	function setFieldValue( $el, value ) {
		const type = ( $el.attr( 'type' ) || '' ).toLowerCase();
		if ( type === 'checkbox' ) {
			$el.prop( 'checked', value === 1 || value === '1' || value === true );
			return;
		}
		if ( Array.isArray( value ) ) {
			$el.val( value.join( ', ' ) );
			return;
		}
		if ( value === null || typeof value === 'undefined' ) {
			$el.val( '' );
			return;
		}
		$el.val( value );
	}

	function clone( obj ) {
		return JSON.parse( JSON.stringify( obj ) );
	}

	// ------------- Form <-> record mapping -------------

	function fillForm( record ) {
		// Top-level fields with dot paths.
		$( '[data-aifirst-field]' ).each( function () {
			const $el = $( this );
			const path = $el.data( 'aifirstField' );
			const parts = path.split( '.' );
			let cursor = record;
			for ( let i = 0; i < parts.length; i++ ) {
				if ( cursor === null || typeof cursor !== 'object' ) {
					cursor = '';
					break;
				}
				cursor = cursor[ parts[ i ] ];
			}
			setFieldValue( $el, typeof cursor === 'undefined' ? '' : cursor );
		} );

		// Repeaters.
		[ 'plans', 'faq', 'radar', 'cannot_do', 'relationships', 'tool_features', 'maturity_breakdown', 'industries', 'sentiment', 'quotes' ].forEach( function ( group ) {
			const rows = Array.isArray( record[ group ] ) ? record[ group ] : [];
			const $rep = $( '[data-aifirst-repeater="' + group + '"]' );
			const $rows = $rep.find( '.aifirst-rows' );
			$rows.empty();
			rows.forEach( function ( row ) {
				addRow( $rep, row );
			} );
		} );
	}

	function readForm() {
		const record = clone( cfg.skeleton );

		$( '[data-aifirst-field]' ).each( function () {
			const $el = $( this );
			const path = $el.data( 'aifirstField' );
			const parts = path.split( '.' );
			let cursor = record;
			for ( let i = 0; i < parts.length - 1; i++ ) {
				cursor = cursor[ parts[ i ] ];
			}
			const leaf = parts[ parts.length - 1 ];
			let val = getFieldValue( $el );
			if ( leaf === 'categories' || leaf === 'ai_features' ) {
				val = String( val || '' )
					.split( /[,|\n]+/ )
					.map( function ( s ) {
						return s.trim();
					} )
					.filter( function ( s ) {
						return s.length > 0;
					} );
			}
			cursor[ leaf ] = val;
		} );

		[ 'plans', 'faq', 'radar', 'cannot_do', 'relationships', 'tool_features', 'maturity_breakdown', 'industries', 'sentiment', 'quotes' ].forEach( function ( group ) {
			record[ group ] = [];
			$( '[data-aifirst-repeater="' + group + '"] .aifirst-row' ).each( function () {
				const $row = $( this );
				const row = {};
				$row.find( '[data-aifirst-col]' ).each( function () {
					const $col = $( this );
					row[ $col.data( 'aifirstCol' ) ] = getFieldValue( $col );
				} );
				record[ group ].push( row );
			} );
		} );

		return record;
	}

	function addRow( $rep, data ) {
		const tpl = $rep.find( '.aifirst-row-template' )[ 0 ];
		if ( ! tpl || ! ( 'content' in tpl ) ) {
			return;
		}
		const frag = document.importNode( tpl.content, true );
		const $row = $( frag ).find( '.aifirst-row' );
		if ( data && typeof data === 'object' ) {
			$row.find( '[data-aifirst-col]' ).each( function () {
				const $col = $( this );
				setFieldValue( $col, data[ $col.data( 'aifirstCol' ) ] );
			} );
		}
		$rep.find( '.aifirst-rows' ).append( $row );
	}

	// ------------- Wire-up -------------

	$( document ).on( 'click', '.aifirst-add-row', function ( e ) {
		e.preventDefault();
		addRow( $( this ).closest( '[data-aifirst-repeater]' ), null );
	} );

	$( document ).on( 'click', '.aifirst-remove-row', function ( e ) {
		e.preventDefault();
		$( this ).closest( '.aifirst-row' ).remove();
	} );

	$( '#aifirst-parse-btn' ).on( 'click', function ( e ) {
		e.preventDefault();
		const $btn = $( this );
		const $status = $( '#aifirst-parse-status' );
		const raw = $( '#aifirst-raw-text' ).val();
		const url = $( '#aifirst-website-url' ).val();

		if ( ! raw || ! String( raw ).trim() ) {
			$status.removeClass( 'is-success' ).addClass( 'is-error' ).text( '⚠ Paste some text first.' );
			return;
		}

		$btn.prop( 'disabled', true );
		$status.removeClass( 'is-error is-success' ).text( cfg.i18n.parsing );

		$.post( cfg.ajaxUrl, {
			action:      'aifirst_parse_text',
			nonce:       cfg.nonce,
			raw_text:    raw,
			website_url: url,
		} )
			.done( function ( res ) {
				if ( ! res || ! res.success ) {
					const msg = res && res.data && res.data.message ? res.data.message : 'Unknown error';
					$status.addClass( 'is-error' ).text( cfg.i18n.parseFail + ' ' + msg );
					return;
				}
				fillForm( res.data.record || cfg.skeleton );
				if ( res.data.warning ) {
					$status.addClass( 'is-error' ).text( res.data.warning );
				} else {
					$status.addClass( 'is-success' ).text( '✓ Parsed. Review the form below.' );
				}
			} )
			.fail( function ( xhr ) {
				const msg = xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message
					? xhr.responseJSON.data.message
					: xhr.statusText;
				$status.addClass( 'is-error' ).text( cfg.i18n.parseFail + ' ' + msg );
			} )
			.always( function () {
				$btn.prop( 'disabled', false );
			} );
	} );

	$( document ).on( 'click', '[data-aifirst-save]', function ( e ) {
		e.preventDefault();
		const $btn = $( this );
		const mode = $btn.data( 'aifirstSave' );
		const $status = $( '#aifirst-save-status' );
		const $result = $( '#aifirst-save-result' );

		const record = readForm();
		record.tool.status = mode === 'publish' ? 'publish' : 'draft';

		$btn.prop( 'disabled', true );
		$status.removeClass( 'is-error is-success' ).text( cfg.i18n.saving );
		$result.hide().removeClass( 'is-error' ).empty();

		$.post( cfg.ajaxUrl, {
			action: 'aifirst_save_record',
			nonce:  cfg.nonce,
			record: JSON.stringify( record ),
		} )
			.done( function ( res ) {
				if ( ! res || ! res.success ) {
					const msg = res && res.data && res.data.message ? res.data.message : 'Unknown error';
					$status.addClass( 'is-error' ).text( cfg.i18n.saveFail + ' ' + msg );
					if ( res && res.data && res.data.errors ) {
						$result.addClass( 'is-error' ).show().html(
							'<strong>Validation errors:</strong><ul><li>' + res.data.errors.map( function ( e ) { return $( '<div>' ).text( e ).html(); } ).join( '</li><li>' ) + '</li></ul>'
						);
					}
					return;
				}
				const data = res.data;
				$status.addClass( 'is-success' ).text( '✓ ' + data.created_or_updated + ' tool "' + data.tool_id + '".' );
				const editUrl = data.edit_url || ( cfg.editBaseUrl + '?post=' + data.post_id + '&action=edit' );
				$result.show().html(
					'<p><strong>' + data.created_or_updated + ':</strong> ' + $( '<div>' ).text( data.tool_id ).html() +
					' (post #' + data.post_id + ') — <a href="' + editUrl + '">Edit in WP</a> · ' +
					'<a href="' + cfg.listUrl + '">All tools</a></p>' +
					'<p><code>' + JSON.stringify( data.counts ) + '</code></p>'
				);
			} )
			.fail( function ( xhr ) {
				const msg = xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message
					? xhr.responseJSON.data.message
					: xhr.statusText;
				$status.addClass( 'is-error' ).text( cfg.i18n.saveFail + ' ' + msg );
			} )
			.always( function () {
				$btn.prop( 'disabled', false );
			} );
	} );

	// Settings page — Test connection.
	$( '#aifirst-test-connection' ).on( 'click', function ( e ) {
		e.preventDefault();
		const $btn = $( this );
		const $out = $( '#aifirst-test-result' );
		$btn.prop( 'disabled', true );
		$out.removeClass( 'is-success is-error' ).text( 'Testing…' );

		$.post( cfg.ajaxUrl, {
			action: 'aifirst_test_connection',
			nonce:  cfg.nonce,
		} )
			.done( function ( res ) {
				if ( res && res.success ) {
					$out.addClass( 'is-success' ).text( '✓ ' + res.data.message + ' (' + res.data.provider + '/' + res.data.model + ')' );
				} else {
					const msg = res && res.data && res.data.message ? res.data.message : 'Unknown error';
					$out.addClass( 'is-error' ).text( '✗ ' + msg );
				}
			} )
			.fail( function ( xhr ) {
				const msg = xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message
					? xhr.responseJSON.data.message
					: xhr.statusText;
				$out.addClass( 'is-error' ).text( '✗ ' + msg );
			} )
			.always( function () {
				$btn.prop( 'disabled', false );
			} );
	} );

	// Start the paste tab with an empty record form so it is usable without AI.
	if ( $( '#aifirst-record-form' ).length ) {
		fillForm( clone( cfg.skeleton ) );
	}
} )( jQuery );

/* RAR Admin Studio: settings screen (tabs, image pickers, colour preview, menu suggestions). */
( function ( $ ) {
	'use strict';
	var cfg = window.rarAsSettings || {};

	/* Tabs: switch without reloading; keep the tab after saving. */
	var $tabs = $( '.rar-as-tabs a[role="tab"]' );
	function showTab( key, focus ) {
		$tabs.each( function () {
			var on = this.getAttribute( 'data-tab' ) === key;
			this.setAttribute( 'aria-selected', on ? 'true' : 'false' );
			this.setAttribute( 'tabindex', on ? '0' : '-1' );
			if ( on && focus ) {
				this.focus();
			}
		} );
		$( '.rar-as-panel' ).each( function () {
			this.hidden = this.id !== 'rar-as-panel-' + key;
		} );
		var url = new URL( window.location.href );
		url.searchParams.set( 'tab', key );
		url.searchParams.delete( 'rar_as_msg' );
		url.searchParams.delete( 'settings-updated' );
		window.history.replaceState( null, '', url.toString() );
		$( '.rar-as-referer' ).val( url.pathname.replace( /^.*\/wp-admin\//, '' ) + url.search );
	}
	$tabs.on( 'click', function ( e ) {
		e.preventDefault();
		showTab( this.getAttribute( 'data-tab' ) );
	} );
	$tabs.on( 'keydown', function ( e ) {
		var i = $tabs.index( this );
		if ( e.key === 'ArrowRight' || e.key === 'ArrowLeft' ) {
			e.preventDefault();
			var n = ( i + ( e.key === 'ArrowRight' ? 1 : -1 ) + $tabs.length ) % $tabs.length;
			showTab( $tabs.eq( n ).attr( 'data-tab' ), true );
		}
	} );
	$tabs.each( function () {
		this.setAttribute( 'tabindex', this.getAttribute( 'aria-selected' ) === 'true' ? '0' : '-1' );
	} );

	/* Image pickers. */
	$( '.rar-as-image' ).each( function () {
		var $box = $( this );
		var $input = $box.find( 'input[type="hidden"]' );
		var $preview = $box.find( '.rar-as-image__preview' );
		var $clear = $box.find( '.rar-as-image__clear' );
		var frame;
		$box.find( '.rar-as-image__pick' ).on( 'click', function () {
			if ( ! window.wp || ! wp.media ) {
				return;
			}
			if ( ! frame ) {
				frame = wp.media( {
					title: cfg.choose,
					button: { text: cfg.use },
					library: { type: 'image' },
					multiple: false
				} );
				frame.on( 'select', function () {
					var a = frame.state().get( 'selection' ).first().toJSON();
					var src = ( a.sizes && a.sizes.medium ) ? a.sizes.medium.url : a.url;
					$input.val( a.id );
					$preview.empty().append( $( '<img alt="">' ).attr( 'src', src ) );
					$clear.prop( 'hidden', false );
				} );
			}
			frame.open();
		} );
		$clear.on( 'click', function () {
			$input.val( '0' );
			$preview.empty();
			$clear.prop( 'hidden', true );
		} );
	} );

	/* Colour pickers with a live preview of the side menu, button and chart. */
	var $preview = $( '.rar-as-preview' );
	var map = { color_primary: '--as-primary', color_sidebar: '--as-side', color_accent: '--as-accent' };
	function readable( hex ) {
		var m = /^#?([0-9a-f]{6})$/i.exec( hex || '' );
		if ( ! m ) {
			return '#ffffff';
		}
		var n = parseInt( m[ 1 ], 16 );
		var c = [ ( n >> 16 ) & 255, ( n >> 8 ) & 255, n & 255 ].map( function ( v ) {
			v /= 255;
			return v <= 0.03928 ? v / 12.92 : Math.pow( ( v + 0.055 ) / 1.055, 2.4 );
		} );
		var l = 0.2126 * c[ 0 ] + 0.7152 * c[ 1 ] + 0.0722 * c[ 2 ];
		return ( 1.05 / ( l + 0.05 ) ) >= 4.5 ? '#ffffff' : '#10201a';
	}
	function apply( key, val ) {
		if ( ! $preview.length || ! map[ key ] || ! val ) {
			return;
		}
		$preview[ 0 ].style.setProperty( map[ key ], val );
		if ( key === 'color_primary' ) {
			$preview[ 0 ].style.setProperty( '--as-on-primary', readable( val ) );
		}
	}
	$( '.rar-as-color' ).each( function () {
		var key = this.getAttribute( 'data-var' );
		$( this ).wpColorPicker( {
			change: function ( e, ui ) {
				apply( key, ui.color.toString() );
			},
			clear: function () {
				apply( key, $( this ).closest( '.wp-picker-container' ).find( '.rar-as-color' ).attr( 'data-default-color' ) );
			}
		} );
	} );

	/* Menu table helpers. */
	var keep = ( cfg.keep || [] ).map( function ( s ) {
		return String( s ).toLowerCase();
	} );
	function isKept( slug ) {
		slug = String( slug ).toLowerCase();
		return keep.some( function ( k ) {
			return slug === k || slug.indexOf( k ) !== -1;
		} );
	}
	$( '.rar-as-suggest' ).on( 'click', function () {
		var role = this.getAttribute( 'data-role' );
		$( 'input[data-role="' + role + '"]' ).each( function () {
			var slug = this.getAttribute( 'data-slug' );
			var top = slug.split( '::' )[ 0 ];
			// A child stays visible when its parent stays visible, unless it is a settings-type screen.
			var child = slug.indexOf( '::' ) !== -1 ? slug.split( '::' )[ 1 ] : '';
			var hide = ! isKept( top ) && ! isKept( child );
			if ( child && isKept( top ) ) {
				hide = /(wc-settings|wc-status|wc-addons|extensions|options|tools)/i.test( child );
			}
			this.checked = hide;
		} );
	} );
	$( '.rar-as-none' ).on( 'click', function () {
		$( 'input[data-role="' + this.getAttribute( 'data-role' ) + '"]' ).prop( 'checked', false );
	} );
} )( jQuery );

/* RAR Admin Studio: sign-in page helpers (Caps Lock hint, busy state on submit). */
( function () {
	'use strict';
	var i18n = window.rarAsLogin || {};

	function capsHint( input ) {
		var hint = document.createElement( 'p' );
		hint.className = 'rar-as-caps';
		hint.setAttribute( 'role', 'status' );
		hint.hidden = true;
		hint.textContent = i18n.caps || 'Caps Lock is on.';
		var anchor = input.closest( '.wp-pwd' ) || input;
		anchor.parentNode.insertBefore( hint, anchor.nextSibling );
		function check( e ) {
			if ( e.getModifierState ) {
				hint.hidden = ! e.getModifierState( 'CapsLock' );
			}
		}
		input.addEventListener( 'keyup', check );
		input.addEventListener( 'keydown', check );
		input.addEventListener( 'blur', function () {
			hint.hidden = true;
		} );
	}

	document.querySelectorAll( '#user_pass, #pass1' ).forEach( capsHint );

	var form = document.getElementById( 'loginform' );
	if ( form ) {
		form.addEventListener( 'submit', function () {
			var btn = document.getElementById( 'wp-submit' );
			if ( ! btn || btn.getAttribute( 'aria-busy' ) === 'true' ) {
				return;
			}
			var original = btn.value;
			btn.setAttribute( 'aria-busy', 'true' );
			btn.value = i18n.signing || original;
			// Restore if the browser shows this page again from its back/forward cache.
			window.addEventListener( 'pageshow', function () {
				btn.removeAttribute( 'aria-busy' );
				btn.value = original;
			} );
		} );
	}
} )();

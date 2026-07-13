/**
 * FlyGit 2.0 — Admin interactions (vanilla JS, no dependencies).
 */
( function () {
	'use strict';

	/**
	 * Copy-to-clipboard buttons.
	 */
	document.querySelectorAll( '.flygit-copy' ).forEach( function ( button ) {
		button.addEventListener( 'click', function () {
			var targetId = button.getAttribute( 'data-copy-target' );
			var attr = button.getAttribute( 'data-copy-attr' );
			var target = document.getElementById( targetId );

			if ( ! target ) {
				return;
			}

			var text = attr ? target.getAttribute( attr ) : target.textContent;

			if ( ! text ) {
				return;
			}

			var restore = function () {
				window.setTimeout( function () {
					button.textContent = button.getAttribute( 'data-original-label' );
				}, 1500 );
			};

			if ( ! button.getAttribute( 'data-original-label' ) ) {
				button.setAttribute( 'data-original-label', button.textContent );
			}

			if ( navigator.clipboard && navigator.clipboard.writeText ) {
				navigator.clipboard.writeText( text ).then(
					function () {
						button.textContent = '✓';
						restore();
					},
					function () {
						fallbackCopy( text, button, restore );
					}
				);
			} else {
				fallbackCopy( text, button, restore );
			}
		} );
	} );

	/**
	 * Legacy clipboard fallback.
	 *
	 * @param {string}   text    Text to copy.
	 * @param {Element}  button  Trigger button.
	 * @param {Function} restore Label restore callback.
	 */
	function fallbackCopy( text, button, restore ) {
		var textarea = document.createElement( 'textarea' );
		textarea.value = text;
		textarea.setAttribute( 'readonly', '' );
		textarea.style.position = 'absolute';
		textarea.style.left = '-9999px';
		document.body.appendChild( textarea );
		textarea.select();

		try {
			document.execCommand( 'copy' );
			button.textContent = '✓';
		} catch ( err ) {
			button.textContent = '✗';
		}

		document.body.removeChild( textarea );
		restore();
	}

	/**
	 * Reveal-secret buttons.
	 */
	document.querySelectorAll( '.flygit-reveal' ).forEach( function ( button ) {
		button.addEventListener( 'click', function () {
			var target = document.getElementById( button.getAttribute( 'data-reveal-target' ) );

			if ( ! target ) {
				return;
			}

			var secret = target.getAttribute( 'data-secret' ) || '';
			var revealed = target.getAttribute( 'data-revealed' ) === '1';

			if ( revealed ) {
				target.textContent = '••••••••••••';
				target.setAttribute( 'data-revealed', '0' );
				button.textContent = button.getAttribute( 'data-label-show' ) || 'Anzeigen';
			} else {
				if ( ! button.getAttribute( 'data-label-show' ) ) {
					button.setAttribute( 'data-label-show', button.textContent );
				}
				target.textContent = secret;
				target.setAttribute( 'data-revealed', '1' );
				button.textContent = 'Verbergen';
			}
		} );
	} );

	/**
	 * Confirm-guarded forms (delete, secret regeneration).
	 */
	document.querySelectorAll( 'form.flygit-confirm' ).forEach( function ( form ) {
		form.addEventListener( 'submit', function ( event ) {
			var text = form.getAttribute( 'data-confirm' ) || 'Sicher?';

			if ( ! window.confirm( text ) ) {
				event.preventDefault();
			}
		} );
	} );

	/**
	 * Disable submit buttons on submit to prevent double-fire.
	 */
	document.querySelectorAll( '.flygit-wrap form' ).forEach( function ( form ) {
		form.addEventListener( 'submit', function () {
			var buttons = form.querySelectorAll( 'button[type="submit"]' );

			window.setTimeout( function () {
				buttons.forEach( function ( b ) {
					b.disabled = true;
				} );
			}, 0 );
		} );
	} );
} )();

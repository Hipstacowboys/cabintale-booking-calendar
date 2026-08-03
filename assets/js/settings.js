/**
 * Interaction for the settings screen.
 *
 * Two jobs: show that a navigating button is working, and reveal a live widget
 * preview on demand. Both lean on components WordPress already ships — the
 * core `.spinner` and `wp.a11y.speak()` — rather than inventing motion or
 * ARIA plumbing.
 */
( function () {
	'use strict';

	function speak( message ) {
		if ( window.wp && window.wp.a11y && window.wp.a11y.speak ) {
			window.wp.a11y.speak( message );
		}
	}

	/**
	 * Connect, Disconnect and "Create my booking page" are ordinary links that
	 * hit the server and redirect. That round trip takes a second or two, during
	 * which the button looked completely inert — so people click again, or
	 * assume it is broken.
	 *
	 * The element stays a real link with a real href: if this script never runs,
	 * every button still works.
	 */
	function bindBusyButtons() {
		var buttons = document.querySelectorAll( '[data-cabintale-busy]' );

		Array.prototype.forEach.call( buttons, function ( button ) {
			button.addEventListener( 'click', function ( event ) {
				// Second click while the first is still in flight — swallow it
				// rather than firing another request at Cabintale.
				if ( 'true' === button.getAttribute( 'aria-disabled' ) ) {
					event.preventDefault();
					return;
				}

				var label = button.getAttribute( 'data-cabintale-busy' );

				button.setAttribute( 'aria-disabled', 'true' );

				if ( label ) {
					button.textContent = label;
					speak( label );
				}

				var spinner = button.parentNode.querySelector( '.spinner' );

				if ( spinner ) {
					spinner.classList.add( 'is-active' );
				}

				// No preventDefault: the navigation must still happen.
			} );
		} );
	}

	/**
	 * Widget previews load on click, never on page load.
	 *
	 * Each preview is a real request to Cabintale. Loading one per widget as
	 * soon as the screen opens would put unsolicited third-party requests on an
	 * admin page, which is exactly what this plugin promises not to do on the
	 * front end. A click is the trigger, and opening one closes the others so
	 * only a single iframe is ever live.
	 */
	function bindPreviews() {
		var toggles = document.querySelectorAll( '[data-cabintale-preview]' );

		Array.prototype.forEach.call( toggles, function ( toggle ) {
			toggle.addEventListener( 'click', function ( event ) {
				event.preventDefault();

				var panel = document.getElementById( toggle.getAttribute( 'aria-controls' ) );

				if ( ! panel ) {
					return;
				}

				var isOpen = 'true' === toggle.getAttribute( 'aria-expanded' );

				closeAll();

				if ( isOpen ) {
					return;
				}

				toggle.setAttribute( 'aria-expanded', 'true' );
				panel.hidden = false;

				var frame = panel.querySelector( 'iframe' );
				var src = frame ? frame.getAttribute( 'data-src' ) : '';

				// Load once, then keep it — reopening should not re-request.
				if ( frame && src && ! frame.getAttribute( 'src' ) ) {
					var loading = panel.querySelector( '[data-cabintale-loading]' );
					var failed  = panel.querySelector( '[data-cabintale-preview-error]' );

					// A cross-origin iframe reports nothing when it is blocked —
					// no error event, no readable document — so a preview that
					// never arrives is indistinguishable from one still loading.
					// Waiting is the only signal available. Ten seconds is long
					// enough for a slow connection and short enough that nobody
					// stares at "Loading preview…" wondering.
					var giveUp = window.setTimeout( function () {
						if ( loading ) {
							loading.hidden = true;
						}

						if ( failed ) {
							failed.hidden = false;

							// The live region was `hidden` when the page parsed,
							// so revealing it is not reliably announced on its
							// own — say it out loud too.
							speak( failed.textContent );
						}
					}, 10000 );

					frame.addEventListener( 'load', function () {
						window.clearTimeout( giveUp );

						if ( loading ) {
							loading.hidden = true;
						}

						// It arrived late, after the timeout already gave up.
						if ( failed ) {
							failed.hidden = true;
						}
					} );

					frame.setAttribute( 'src', src );
				}

				speak( toggle.getAttribute( 'data-cabintale-preview' ) );
			} );
		} );

		function closeAll() {
			Array.prototype.forEach.call( toggles, function ( other ) {
				var panel = document.getElementById( other.getAttribute( 'aria-controls' ) );

				other.setAttribute( 'aria-expanded', 'false' );

				if ( panel ) {
					panel.hidden = true;
				}
			} );
		}
	}

	/**
	 * Copy a widget's shortcode. This is the action that works in every editor
	 * — Elementor, Bricks, Divi, classic, block — so it is the one most people
	 * will actually use.
	 */
	function bindCopyButtons() {
		var buttons = document.querySelectorAll( '[data-cabintale-copy]' );

		Array.prototype.forEach.call( buttons, function ( button ) {
			button.addEventListener( 'click', function () {
				var text = button.getAttribute( 'data-cabintale-copy' );
				var done = button.getAttribute( 'data-cabintale-copied' );
				var original = button.textContent;

				function confirmCopy() {
					button.textContent = done;
					speak( done );

					window.setTimeout( function () {
						button.textContent = original;
					}, 2000 );
				}

				if ( navigator.clipboard && navigator.clipboard.writeText ) {
					navigator.clipboard.writeText( text ).then( confirmCopy, fallback );
					return;
				}

				fallback();

				// Older browsers, and any context where the async clipboard is
				// blocked. The shortcode is visible next to the button either
				// way, so worst case it can be selected by hand.
				function fallback() {
					var field = document.createElement( 'textarea' );

					field.value = text;
					field.setAttribute( 'readonly', '' );
					field.style.position = 'absolute';
					field.style.left = '-9999px';
					document.body.appendChild( field );
					field.select();

					try {
						document.execCommand( 'copy' );
						confirmCopy();
					} catch ( e ) {
						// Leave the label alone — the text is on screen to copy.
					}

					document.body.removeChild( field );
				}
			} );
		} );
	}

	document.addEventListener( 'DOMContentLoaded', function () {
		bindBusyButtons();
		bindPreviews();
		bindCopyButtons();
	} );
} )();

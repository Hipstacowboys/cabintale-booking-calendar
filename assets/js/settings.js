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
					frame.addEventListener( 'load', function () {
						var loading = panel.querySelector( '[data-cabintale-loading]' );

						if ( loading ) {
							loading.hidden = true;
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

	document.addEventListener( 'DOMContentLoaded', function () {
		bindBusyButtons();
		bindPreviews();
	} );
} )();

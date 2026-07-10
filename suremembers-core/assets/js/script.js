( function ( $ ) {
	// Enable popup.
	function enablePopup( e ) {
		e.preventDefault();

		if ( ! $( 'body' ).children( '.suremember-login-container-popup' ).length ) {
			const popup = $( '.suremember-login-container-popup' );
			popup.find( 'p, br' ).remove();
			$( '.suremember-login-container-popup' ).appendTo( document.body );
		}
		$( '.suremember-login-container-popup' ).addClass( 'active' );

		// Clear any previous error messages.
		$( '.suremember-login-container-popup .field-error' ).remove();

		// Render Turnstile - wait for script to load if needed.
		function renderTurnstile( attempts = 0 ) {
			if ( typeof window.turnstile !== 'undefined' ) {
				// Check for Simple Turnstile widgets (they have class cf-turnstile but not cf-turnstile-manual)
				const simpleTurnstileWidgets = $( '.suremember-login-container-popup .cf-turnstile' ).not( '.cf-turnstile-manual' );

				if ( simpleTurnstileWidgets.length > 0 ) {
					// Simple Turnstile widget exists, check if it needs rendering
					simpleTurnstileWidgets.each( function() {
						const widget = $( this );
						// Check if widget is empty (needs rendering)
						if ( widget.children().length === 0 && widget.data( 'sitekey' ) ) {
							try {
								window.turnstile.render( widget[ 0 ], {
									sitekey: widget.data( 'sitekey' ),
									theme: widget.data( 'theme' ) || 'auto',
									language: widget.data( 'language' ) || 'auto',
									action: widget.data( 'action' ) || 'submit',
									size: widget.data( 'size' ) || 'normal',
									appearance: widget.data( 'appearance' ) || 'always',
									callback: widget.data( 'callback' ) || 'cfturnstileCallback',
									'error-callback': widget.data( 'error-callback' ) || 'cfturnstileErrorCallback',
								} );
							} catch ( error ) {
								console.warn( 'Simple Turnstile render error:', error );
							}
						}
					} );
				} else {
					// No Simple Turnstile, check for SureMembers Turnstile
					const turnstileContainer = $( '.suremember-login-container-popup .cf-turnstile-manual' );
					if ( turnstileContainer.length && window.turnstileReady ) {
						// Check if already rendered by looking for the hidden input.
						const existingInput = turnstileContainer.find( 'input[name="cf-turnstile-response"]' );

						if ( existingInput.length === 0 ) {
							const siteKey = turnstileContainer.data( 'sitekey' );
							const theme = turnstileContainer.data( 'theme' ) || 'light';

							// Clear any existing content first.
							turnstileContainer.empty();
							turnstileContainer.addClass( 'cf-turnstile' );

							try {
								window.turnstile.render( turnstileContainer[ 0 ], {
									sitekey: siteKey,
									theme,
								} );
							} catch ( error ) {
								console.warn( 'SureMembers Turnstile render error:', error );
							}
						}
					}
				}
			} else if ( attempts < 50 ) {
				setTimeout( () => renderTurnstile( attempts + 1 ), 100 );
			}
		}

		// Always try to render Turnstile when popup opens
		renderTurnstile();
	}

	// Submit user login form.
	function loginSubmit( e ) {
		e.preventDefault();
		const form = $( this );
		const getSubmitBtn = $( '.suremember-user-form-submit' );
		getSubmitBtn.addClass( 'submit-loading' );
		const formData = new FormData( form[ 0 ] );
		$.ajax( {
			method: 'POST',
			url: suremembers_login.ajax_url,
			data: formData,
			dataType: 'json',
			cache: false,
			contentType: false,
			processData: false,
			success( response ) {
				getSubmitBtn.removeClass( 'submit-loading' );
				if ( true === response.success ) {
					window.location.reload();
				} else if ( response?.data?.result ) {
					for ( const field in response.data.result ) {
						const heading = $( '.suremember-login-heading' );
						heading.after( `<span class='field-error'>${ response.data.result[ field ] }</span>` );
					}

					// Reset Turnstile on error
					if ( typeof window.turnstile !== 'undefined' ) {
						const turnstileContainer = $( '.suremember-login-container-popup .cf-turnstile-manual, .suremember-login-container-popup .cf-turnstile' );
						if ( turnstileContainer.length ) {
							// Get the widget ID from the container
							const widgetId = turnstileContainer.find( 'input[name="cf-turnstile-response"]' ).attr( 'id' );
							if ( widgetId ) {
								const widgetIdValue = widgetId.replace( '_response', '' );
								window.turnstile.reset( widgetIdValue );
							} else {
								// Fallback to container reset
								window.turnstile.reset( turnstileContainer[ 0 ] );
							}
						}
					}
				}
			},
		} );
	}

	function logoutSubmit( e ) {
		e.preventDefault();

		const button = $( this ),
			  processingText = button.data( 'processing' ),
			  nonce = button.data( 'nonce' );
		button.text( processingText );
		const formData = new FormData();
		formData.append( 'action', 'suremembers_user_logout' );
		formData.append( 'logout_nonce', nonce );
		$.ajax( {
			method: 'POST',
			url: suremembers_login.ajax_url,
			data: formData,
			dataType: 'json',
			cache: false,
			contentType: false,
			processData: false,
			success( response ) {
				if ( true === response.success ) {
					window.location.reload();
				} else if ( response?.data?.result ) {
					button.after( `<span class='field-error'>${ response?.data?.result?.message || 'Something went wrong. Please try again.' }</span>` );
				}
			},
		} );
	}

	// When click outside hide popup.
	$( document ).on( 'click', '.suremember-login-container-popup.active', function ( e ) {
		const inner = $( '.suremember-login-wrapper' );
		if ( ! inner.is( e.target ) && inner.has( e.target ).length === 0 ) {
			$( this ).removeClass( 'active' );
		}
	} );

	// Close by button.
	$( document ).on( 'click', '.suremember-login-wrapper-close', function() {
		$( '.suremember-login-container-popup' ).removeClass( 'active' );
	} );

	// Hide show password.
	function showHidePwd( event ) {
		event.preventDefault();
		const button = $( this );
		const input = button.siblings( 'input' );
		button.toggleClass( 'show-pwd' );
		if ( 'text' === input.attr( 'type' ) ) {
			input.attr( 'type', 'password' );
			button.find( 'span' ).removeClass( 'dashicons-hidden' );
			button.find( 'span' ).addClass( 'dashicons-visibility' );
		} else {
			input.attr( 'type', 'text' );
			button.find( 'span' ).removeClass( 'dashicons-visibility' );
			button.find( 'span' ).addClass( 'dashicons-hidden' );
		}
	}

	$( document ).on( 'submit', '.suremember-user-login-form', loginSubmit );
	$( document ).on( 'click', '.suremembers-logout-button', logoutSubmit );
	$( document ).on( 'click', '.suremembers-open-login-popup', enablePopup );
	$( document ).on( 'click', '.suremembers-hide-if-no-js', showHidePwd );
}( jQuery ) );

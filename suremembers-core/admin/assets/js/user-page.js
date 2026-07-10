jQuery( document ).ready( function () {
	jQuery( '#suremembers_access_groups' ).select2( {
		minimumInputLength: 1,
		ajax: {
			url: ajaxurl,
			type: 'POST',
			delay: 250,
			data( params ) {
				const query = {
					security: suremembers_menu_items.security,
					action: 'queried_access_groups',
					search: params.term,
					exclude: suremembers_menu_items.user_access_groups,
				};
				return query;
			},
			processResults( response ) {
				return {
					results: response.data,
				};
			},
		},
	} );
} );
const afterDOMInitiated = ( cb ) => {
	if ( /comp|inter|loaded/.test( document.readyState ) ) {
		cb();
	} else {
		document.addEventListener( 'DOMContentLoaded', cb, false );
	}
};
( function ( $ ) {
	const userApp = {
		_log( message ) {
			console.log( message );
		},
		// Store removed access groups that need to be removed on form submit
		pendingRemovals: [],
		// Store pending expiration date changes
		pendingExpireDateChanges: {},
		// Store original expiration dates for restoration
		originalExpireDates: {},
		// Flag to track if we're currently saving pending changes
		isSavingPendingChanges: false,
		// Store form that's waiting to be submitted after save
		pendingFormSubmit: null,
		bind() {
			$( '.suremembers-user-actions' ).on( 'click', function ( e ) {
				e.preventDefault();
				const $this = $( this );
				const action = $this.data( 'action' );
				const accessId = $this.data( 'access' );
				const userData = {
					userID: $this.data( 'user' ),
					action,
					access: accessId,
				};

				const $row = $this.closest( 'tr' );

				// For remove_access, mark for removal and hide the row (temporary)
				if ( 'remove_access' === action ) {
					// Store row data for potential restoration
					const rowData = {
						row: $row,
						accessId,
						userId: $this.data( 'user' ),
					};

					// Mark row with data attribute
					$row.data( 'pending-removal', true );
					$row.data( 'row-data', rowData );

					// Hide the row immediately for visual feedback
					$row.fadeOut( 300, function() {
						// After row is hidden, check if table is empty
						userApp.checkAndShowEmptyTable();
					} );

					// Add to pending removals
					userApp.pendingRemovals.push( {
						accessId,
						userId: $this.data( 'user' ),
						row: $row,
					} );

					// Show update button and notice
					userApp.showPendingChangesNotice();

					// Store removed access group ID in hidden input for form submission
					const $hiddenInput = $( '<input type="hidden" name="suremembers_remove_access_groups[]" value="' + accessId + '">' );
					$( '#suremembers-user-access-list' ).append( $hiddenInput );

					$this.removeClass( 'suremembers-updating-inline' );
					return; // Don't make AJAX call immediately
				}

				// For other actions (grant/revoke), make immediate AJAX call
				const _ajax_nonce = $( '#suremembers-user-access-list' ).data( 'nonce' );
				$.ajax( {
					url: ajaxurl,
					type: 'POST',
					data: {
						action: 'suremembers_users_edit_actions',
						_ajax_nonce,
						data: userData,
					},
					beforeSend() {
						$this.addClass( 'suremembers-updating-inline' );
					},
				} ).done( function ( rp ) {
					if ( rp.success ) {
						// For other actions, refresh the table
						userApp.refreshTable( $this.data( 'user' ) );
					} else {
						userApp.refreshTable( $this.data( 'user' ) );
					}
					$this.removeClass( 'suremembers-updating-inline' );
				} ).fail( function() {
					userApp.refreshTable( $this.data( 'user' ) );
					$this.removeClass( 'suremembers-updating-inline' );
				} );
			} );

			$( '#suremembers-add-access-group' ).off( 'click' ).on( 'click', function ( e ) {
				const $this = $( this );
				e.preventDefault();
				const selectIDs = $( '#suremembers_access_groups' ).val();
				const _ajax_nonce = $( '#suremembers-user-access-list' ).data( 'nonce' );
				$.ajax( {
					url: ajaxurl,
					type: 'POST',
					data: {
						action: 'suremembers_users_edit_add_access_groups',
						_ajax_nonce,
						accessIDs: selectIDs,
						userID: $this.data( 'user' ),
					},
					beforeSend() {
						$this.addClass( 'suremembers-updating-inline' );
					},
				} ).done( function ( response ) {
					if ( response.success ) {
						userApp.refreshTable( $this.data( 'user' ) );
						if ( response.data.added_ids.length > 0 ) {
							for ( let i = 0; i < response.data.added_ids.length; i++ ) {
								const id = response.data.added_ids[ i ];
								$( "#suremembers_access_groups option[value='" + id + "']" ).remove();
							}
							suremembers_menu_items.user_access_group_count = parseInt( suremembers_menu_items.user_access_group_count ) + parseInt( response.data.added_ids.length );
							userApp.refreshSelectWrapper();
							suremembers_menu_items.user_access_groups = [ ...suremembers_menu_items.user_access_groups, ...response.data.added_ids ];
						}
					} else if ( response.data?.message ) {
						alert( response.data.message );
					}
					$this.removeClass( 'suremembers-updating-inline' );
				} );
			} );

			$( '.suremembers-expire-date' ).off( 'change' ).on( 'change', function ( e ) {
				e.preventDefault();
				const $this = $( this );
				const accessId = $this.data( 'access' );
				const userId = $this.data( 'user' );
				const newDate = $this.val();

				// Store original date if not already stored
				if ( ! userApp.originalExpireDates[ accessId ] ) {
					userApp.originalExpireDates[ accessId ] = $this.attr( 'data-original-value' ) || $this.val();
				}

				// Store pending change
				userApp.pendingExpireDateChanges[ accessId ] = {
					userId,
					date: newDate,
					accessId,
				};

				// Visual feedback - highlight changed input
				$this.addClass( 'suremembers-pending-change' );

				// Show update button and notice
				userApp.showPendingChangesNotice();
			} );
		},
		refreshSelectWrapper() {
			if ( suremembers_menu_items.user_access_group_count === parseInt( suremembers_menu_items.published_access_groups_count ) ) {
				$( '#suremembers-add-access-group-select' ).hide();
			}
		},
		init() {
			this.bind();
			this.bindFormSubmit();
		},
		checkAndShowEmptyTable() {
			const $tableBody = $( '#suremembers-user-access-list #the-list' );
			// Count visible rows (excluding pending removals and no-items row)
			const visibleRows = $tableBody.find( 'tr:visible' ).not( '.no-items' ).filter( function() {
				return ! $( this ).data( 'pending-removal' );
			} );

			// If no visible rows, show "No items found" message
			if ( visibleRows.length === 0 ) {
				const noItemsText = ( typeof wp !== 'undefined' && wp.i18n )
					? wp.i18n.__( 'No memberships found.', 'suremembers' )
					: 'No memberships found.';

				// Remove existing no-items row if any
				$tableBody.find( '.no-items' ).remove();

				// Add no-items row
				$tableBody.append( '<tr class="no-items"><td class="colspanchange" colspan="7">' +
					noItemsText + '</td></tr>' );
			} else {
				// Remove no-items row if there are visible rows
				$tableBody.find( '.no-items' ).remove();
			}
		},
		showPendingChangesNotice() {
			// Remove existing notice if any
			$( '.suremembers-pending-changes-notice' ).remove();

			const hasPendingChanges = Object.keys( userApp.pendingExpireDateChanges ).length > 0 || userApp.pendingRemovals.length > 0;

			if ( ! hasPendingChanges ) {
				return;
			}

			// Show notice
			const noticeText = ( typeof wp !== 'undefined' && wp.i18n )
				? wp.i18n.__( 'You have unsaved changes. Click "Update Profile" to save changes or refresh to discard them.', 'suremembers' )
				: 'You have unsaved changes. Click "Update Profile" to save changes or refresh to discard them.';

			const $notice = $( '<div class="notice notice-warning is-dismissible suremembers-pending-changes-notice"><p>' + noticeText + '</p></div>' );

			// Insert notice before the table
			$( '#suremembers-user-access-list' ).before( $notice );

			// Add dismiss handler
			$notice.on( 'click', '.notice-dismiss', function() {
				$notice.remove();
			} );
		},
		savePendingChangesAndSubmit( $form ) {
			// Set flag to prevent form submit alert
			userApp.isSavingPendingChanges = true;
			userApp.pendingFormSubmit = $form;

			const $submitButton = $( '#your-profile #submit, #profile-page #submit, #submit' );
			const originalButtonText = $submitButton.val() || $submitButton.text();

			// Disable button and show loading
			$submitButton.prop( 'disabled', true );
			if ( $submitButton.is( 'input' ) ) {
				$submitButton.val( ( typeof wp !== 'undefined' && wp.i18n )
					? wp.i18n.__( 'Updating…', 'suremembers' )
					: 'Updating...' );
			} else {
				$submitButton.text( ( typeof wp !== 'undefined' && wp.i18n )
					? wp.i18n.__( 'Updating…', 'suremembers' )
					: 'Updating...' );
			}

			userApp.savePendingChanges( originalButtonText, $submitButton );
		},
		savePendingChanges( originalButtonText, $submitButton ) {
			// Set flag to prevent form submit alert
			userApp.isSavingPendingChanges = true;

			const _ajax_nonce = $( '#suremembers-user-access-list' ).data( 'nonce' );

			if ( ! $submitButton ) {
				$submitButton = $( '#your-profile #submit, #profile-page #submit, #submit' );
			}
			if ( ! originalButtonText ) {
				originalButtonText = $submitButton.val() || $submitButton.text();
			}

			// Disable button and show loading
			$submitButton.prop( 'disabled', true );
			if ( $submitButton.is( 'input' ) ) {
				$submitButton.val( ( typeof wp !== 'undefined' && wp.i18n )
					? wp.i18n.__( 'Updating…', 'suremembers' )
					: 'Updating...' );
			} else {
				$submitButton.text( ( typeof wp !== 'undefined' && wp.i18n )
					? wp.i18n.__( 'Updating…', 'suremembers' )
					: 'Updating...' );
			}

			const promises = [];

			// Save all pending expiration date changes
			Object.keys( userApp.pendingExpireDateChanges ).forEach( function( accessId ) {
				const change = userApp.pendingExpireDateChanges[ accessId ];
				promises.push(
					$.ajax( {
						url: ajaxurl,
						type: 'POST',
						data: {
							action: 'suremembers_add_expire_date_to_user',
							_ajax_nonce,
							data: {
								userID: change.userId,
								date: change.date,
								access: change.accessId,
							},
						},
					} )
				);
			} );

			// Save all pending removals
			userApp.pendingRemovals.forEach( function( removal ) {
				promises.push(
					$.ajax( {
						url: ajaxurl,
						type: 'POST',
						data: {
							action: 'suremembers_users_edit_actions',
							_ajax_nonce,
							data: {
								userID: removal.userId,
								action: 'remove_access',
								access: removal.accessId,
							},
						},
					} )
				);
			} );

			// Wait for all promises to complete
			$.when.apply( $, promises ).done( function() {
				// Clear pending changes
				userApp.pendingExpireDateChanges = {};
				userApp.pendingRemovals = [];
				userApp.originalExpireDates = {};

				// Remove visual indicators
				$( '.suremembers-pending-change' ).removeClass( 'suremembers-pending-change' );

				// Hide notice
				$( '.suremembers-pending-changes-notice' ).remove();

				// Show success message
				const successMsg = ( typeof wp !== 'undefined' && wp.i18n )
					? wp.i18n.__( 'Changes saved successfully!', 'suremembers' )
					: 'Changes saved successfully!';
				const $successNotice = $( '<div class="notice notice-success is-dismissible"><p>' + successMsg + '</p></div>' );
				$( '#suremembers-user-access-list' ).before( $successNotice );

				// Auto-dismiss success message
				setTimeout( function() {
					$successNotice.fadeOut( 300, function() {
						$( this ).remove();
					} );
				}, 3000 );

				// Refresh the table
				const userId = $( 'input[name="user_id"]' ).val() || $( '#user_id' ).val() || $( '.suremembers-expire-date' ).first().data( 'user' );
				if ( userId ) {
					userApp.refreshTable( userId );
				}

				// Restore button
				$submitButton.prop( 'disabled', false );
				if ( $submitButton.is( 'input' ) ) {
					$submitButton.val( originalButtonText );
				} else {
					$submitButton.text( originalButtonText );
				}

				// Reset flag
				userApp.isSavingPendingChanges = false;

				// If form was waiting to submit, submit it now
				if ( userApp.pendingFormSubmit ) {
					const formToSubmit = userApp.pendingFormSubmit;
					userApp.pendingFormSubmit = null;
					formToSubmit.off( 'submit.suremembers' );
					formToSubmit.submit();
				}
			} ).fail( function() {
				// Show error message
				const errorMsg = ( typeof wp !== 'undefined' && wp.i18n )
					? wp.i18n.__( 'Failed to save some changes. Please try again.', 'suremembers' )
					: 'Failed to save some changes. Please try again.';
				const $errorNotice = $( '<div class="notice notice-error is-dismissible"><p>' + errorMsg + '</p></div>' );
				$( '#suremembers-user-access-list' ).before( $errorNotice );

				// Restore button
				$submitButton.prop( 'disabled', false );
				if ( $submitButton.is( 'input' ) ) {
					$submitButton.val( originalButtonText );
				} else {
					$submitButton.text( originalButtonText );
				}

				// Reset flag
				userApp.isSavingPendingChanges = false;

				// If form was waiting to submit, don't submit on error - let user try again
				if ( userApp.pendingFormSubmit ) {
					userApp.pendingFormSubmit = null;
				}
			} );
		},
		bindFormSubmit() {
			// Listen for WordPress user profile form submission
			$( '#your-profile, #profile-page' ).off( 'submit.suremembers' ).on( 'submit.suremembers', function( e ) {
				// Don't show alert if changes are being saved
				if ( userApp.isSavingPendingChanges ) {
					return true;
				}

				// If there are pending changes, save them first before form submission
				const hasPendingChanges = Object.keys( userApp.pendingExpireDateChanges ).length > 0 || userApp.pendingRemovals.length > 0;

				if ( hasPendingChanges ) {
					// Prevent form submission temporarily
					e.preventDefault();

					// Store form reference for later submission
					const $form = $( this );

					// Save pending changes first, then submit form
					userApp.savePendingChangesAndSubmit( $form );

					return false;
				}

				const $form = $( this );
				const removedAccessGroups = [];

				// Collect all pending removals
				$( 'input[name="suremembers_remove_access_groups[]"]' ).each( function() {
					removedAccessGroups.push( $( this ).val() );
				} );

				if ( removedAccessGroups.length > 0 ) {
					// Prevent default form submission temporarily
					e.preventDefault();

					const userId = $( 'input[name="user_id"]' ).val() || $( '#user_id' ).val();
					const _ajax_nonce = $( '#suremembers-user-access-list' ).data( 'nonce' );

					// Remove all pending access groups via AJAX
					// Process each access group removal
					const removalPromises = [];
					removedAccessGroups.forEach( function( accessId ) {
						removalPromises.push(
							$.ajax( {
								url: ajaxurl,
								type: 'POST',
								data: {
									action: 'suremembers_users_edit_actions',
									_ajax_nonce,
									data: {
										userID: userId,
										action: 'remove_access',
										access: accessId,
									},
								},
							} )
						);
					} );

					// Wait for all removals to complete
					$.when.apply( $, removalPromises ).done( function() {
						const rp = arguments[ 0 ]; // Get first response
						if ( rp.success ) {
							// Now submit the form normally
							$form.off( 'submit' );
							$form.submit();
						} else {
							// Show error and allow form submission anyway
							alert( rp.data?.message || 'Failed to remove some memberships. Please try again.' );
							$form.off( 'submit' );
							$form.submit();
						}
					} ).fail( function() {
						// On failure, still allow form submission
						alert( 'Failed to remove memberships. Please try again.' );
						$form.off( 'submit' );
						$form.submit();
					} );
				}
			} );
		},
		getAccessListByID( id ) {
			const _ajax_nonce = $( '#suremembers-user-access-list' ).data( 'nonce' );
			return $.ajax( {
				url: ajaxurl,
				type: 'POST',
				data: {
					action: 'get_access_groups_by_id',
					_ajax_nonce,
					userID: id,
				},
			} ).done( function ( response ) {
				return response.success ? response?.data : [];
			} );
		},
		async refreshTable( user_id ) {
			const freshList = await userApp.getAccessListByID( user_id );
			const $el = $( '#suremembers-user-access-list #the-list' );

			if ( freshList?.data?.access_groups && freshList.data.access_groups.length > 0 ) {
				const template = wp.template( 'suremembers-users-access-group-row' );
				$el.html( template( { access_groups: freshList.data.access_groups } ) );
			} else {
				// If no access groups, show empty table or placeholder
				const noItemsText = ( typeof wp !== 'undefined' && wp.i18n )
					? wp.i18n.__( 'No memberships found.', 'suremembers' )
					: 'No memberships found.';
				$el.html( '<tr class="no-items"><td class="colspanchange" colspan="7">' +
					noItemsText + '</td></tr>' );
			}
			userApp.bind();

			// Store original expiration date values
			$( '.suremembers-expire-date' ).each( function() {
				const $input = $( this );
				const accessId = $input.data( 'access' );
				if ( accessId && ! userApp.originalExpireDates[ accessId ] ) {
					$input.attr( 'data-original-value', $input.val() );
					userApp.originalExpireDates[ accessId ] = $input.val();
				}
			} );
		},
	};

	afterDOMInitiated( () => {
		userApp.init();
	} );
}( jQuery ) );

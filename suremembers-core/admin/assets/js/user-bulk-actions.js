/**
 * This file contains the functions needed for the inline editing of users.
 *
 * @since 1.2.0
 */

window.wp = window.wp || {};

/**
 * Manages the quick edit and bulk edit windows for editing users table.
 *
 * @namespace smBulkEditUsers
 *
 * @since 1.2.0
 *
 * @type {Object}
 *
 * @property {string} type The type of inline editor.
 * @property {string} what The prefix before the post ID.
 *
 */
( function ( $, wp ) {
	const smBulkEditUsers = {

		/**
		 * Initializes the inline and bulk users editor.
		 *
		 * Binds event handlers to the Escape key to close the inline editor
		 * and to the save and close buttons. Changes DOM to be ready for inline
		 * editing. Adds event handler to bulk edit.
		 *
		 * @since 1.2.0
		 *
		 * @memberof smBulkEditUsers
		 *
		 * @return {void}
		 */
		init() {
			const t = this,
				bulkRow = $( '#suremembers-access-groups-bulk-edit' );

			/**
			 * Binds the Escape key to revert the changes and close the bulk editor.
			 *
			 * @return {boolean} The result of revert.
			 */
			bulkRow.on( 'keyup', function ( e ) {
				// Revert changes if Escape key is pressed.
				if ( e.which === 27 ) {
					return smBulkEditUsers.revert();
				}
			} );

			/**
			 * Adds onclick events to the apply buttons.
			 */
			$( '#doaction' ).on( 'click', function ( e ) {
				t.whichBulkButtonId = $( this ).attr( 'id' );
				const n = t.whichBulkButtonId.substr( 2 );

				if ( 'suremembers_edit_users' === $( 'select[name="' + n + '"]' ).val() ) {
					e.preventDefault();
					t.setBulk();
				} else if ( $( 'form#posts-filter tr.inline-editor' ).length > 0 ) {
					t.revert();
				}
			} );
		},

		/**
		 * Binds the template related clicks.
		 */
		bindClicks() {
			const bulkRow = $( '#suremembers-access-groups-bulk-edit' );
			/**
			 * Reverts changes and close the bulk editor if the cancel button is clicked.
			 *
			 * @return {boolean} The result of revert.
			 */
			$( '.cancel', bulkRow ).on( 'click', function () {
				return smBulkEditUsers.revert();
			} );

			/**
			 * Binds on click events to handle the list of items to bulk edit.
			 *
			 * @listens click
			 */
			$( '#suremembers-bulk-titles .ntdelbutton' ).click( function () {
				const $this = $( this ),
					id = $this.attr( 'id' ).substr( 1 ),
					$prev = $this.parent().prev().children( '.ntdelbutton' ),
					$next = $this.parent().next().children( '.ntdelbutton' );

				$( 'table.widefat input[value="' + id + '"]' ).prop( 'checked', false );
				$( '#_' + id ).parent().remove();
				wp.a11y.speak( wp.i18n.__( 'Item removed.', 'suremembers' ), 'assertive' );

				// Move focus to a proper place when items are removed.
				if ( $next.length ) {
					$next.focus();
				} else if ( $prev.length ) {
					$prev.focus();
				} else {
					$( '#suremembers-bulk-titles-list' ).remove();
					smBulkEditUsers.revert();
					wp.a11y.speak( wp.i18n.__( 'All selected items have been removed. Select new items to use Bulk Actions.', 'suremembers' ) );
				}
			} );

			/**
			 * Select2 initialize
			 */
			$( '#suremembers_access_groups' ).select2( {
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

			$( '#suremembers-access-groups-bulk-edit .select2.select2-container' ).attr( 'style', 'width:100%' );

			$( '[name="bulk_grant_access"]' ).on( 'click', function ( e ) {
				e.preventDefault();
				smBulkEditUsers.sendRequest( $( this ), 'suremembers_grant_bulk_access' );
			} );

			$( '[name="bulk_revoke_access"]' ).on( 'click', function ( e ) {
				e.preventDefault();
				smBulkEditUsers.sendRequest( $( this ), 'suremembers_revoke_bulk_access' );
			} );
		},

		sendRequest( $this, usr_action ) {
			const postData = smBulkEditUsers.getData();

			if ( 0 === postData.access_groups.length ) {
				alert( wp.i18n.__( 'Select memberships first.', 'suremembers' ) );
				return;
			}

			postData.user_action = usr_action;

			$.ajax( {
				url: ajaxurl,
				type: 'POST',
				data: postData,
				beforeSend() {
					$this.addClass( 'suremembersupdatinginline' );
				},
			} ).done( function ( rp ) {
				if ( rp.success ) {
					window.location.reload();
				} else {
					alert( rp.data.message );
				}
				$this.removeClass( 'suremembersupdatinginline' );
			} );
		},

		getData() {
			const user_ids = [];
			$( '.suremembers-button-link.ntdelbutton' ).each( function () {
				user_ids.push( smBulkEditUsers.getId( this.id ) );
			} );

			return {
				action: 'suremembers_handle_bulk_access_edit',
				user_ids,
				access_groups: $( '[name="access_group[]"]' ).val(),
				nonce: $( '.inline-edit-save [name="_wpnonce"]' ).val(),
				referrer: $( '.inline-edit-save [name="_wp_http_referer"]' ).val(),
			};
		},

		/**
		 * Creates the bulk editor row to edit multiple posts at once.
		 *
		 * @since 1.2.0
		 *
		 * @memberof smBulkEditUsers
		 */
		setBulk() {
			const template_data = [];
			let c = true;
			this.revert();

			/**
			 * Create a HTML div with the title and a link(delete-icon) for each selected
			 * post.
			 *
			 * Get the selected posts based on the checked checkboxes in the post table.
			 */
			$( 'tbody th.check-column input[type="checkbox"]' ).each( function () {
				// If the checkbox for a post is selected, add the post to the edit list.
				if ( $( this ).prop( 'checked' ) ) {
					c = false;
					const user_id = $( this ).val();
					template_data.push( {
						id: user_id,
						theTitle: $( '#user-' + user_id + ' .column-username a' ).html() || wp.i18n.__( '(no title)', 'suremembers' ),
						buttonVisuallyHiddenText: wp.i18n.sprintf(
							/* translators: %s: Post title. */
							wp.i18n.__( 'Remove &#8220;%s&#8221; from Bulk Edit', 'suremembers' ),
							$( '#user-' + user_id + ' .column-name' ).html() || wp.i18n.__( '(no title)', 'suremembers' )
						),
						firstHeadColSpan: $( 'th:visible, td:visible', '.widefat:first thead' ).length,
					} );
				}
			} );

			// If no checkboxes where checked, just hide the quick/bulk edit rows.
			if ( c || '' === template_data ) {
				return this.revert();
			}

			// Add template on top of the table with data.
			const template = wp.template( 'suremembers_users_bulk_edit_template' );
			const $el = $( 'table.widefat tbody' );
			$el.prepend( template( template_data ) );

			smBulkEditUsers.bindClicks();

			// Set initial focus on the Bulk Edit region.
			$( '#suremembers-access-groups-bulk-edit .inline-edit-wrapper' ).attr( 'tabindex', '-1' ).focus();
			// Scrolls to the top of the table where the editor is rendered.
			$( 'html, body' ).animate( { scrollTop: 0 }, 'fast' );
		},

		/**
		 * Hides and empties the Quick Edit and/or Bulk Edit windows.
		 *
		 * @since 1.2.0
		 *
		 * @memberof smBulkEditUsers
		 *
		 * @return {boolean} Always returns false.
		 */
		revert() {
			const $tableWideFat = $( '.widefat' );
			let id = $( '.inline-editor', $tableWideFat ).attr( 'id' );

			if ( id ) {
				$( '.spinner', $tableWideFat ).removeClass( 'is-active' );

				if ( 'suremembers-access-groups-bulk-edit' === id ) {
					// Hide the bulk editor.
					$( '#suremembers-access-groups-bulk-edit', $tableWideFat ).removeClass( 'inline-editor' ).hide().siblings( '.hidden' ).remove();
					$( '#suremembers-bulk-titles' ).empty();

					// Move focus back to the Bulk Action button that was activated.
					$( '#' + smBulkEditUsers.whichBulkButtonId ).trigger( 'focus' );
				} else {
					// Remove both the inline-editor and its hidden tr siblings.
					$( '#' + id ).siblings( 'tr.hidden' ).addBack().remove();
					id = id.substr( id.lastIndexOf( '-' ) + 1 );

					// Show the post row and move focus back to the Quick Edit button.
					$( this.what + id ).show().find( '.editinline' )
						.attr( 'aria-expanded', 'false' )
						.trigger( 'focus' );
				}
			}

			return false;
		},

		/**
		 * Gets the ID for a the post that you want to quick edit from the row in the quick
		 * edit table.
		 *
		 * @since 1.2.0
		 *
		 * @memberof smBulkEditUsers
		 *
		 * @param {string} id ID to format.
		 * @return {string} Formatted ID.
		 */
		getId( id ) {
			const parts = id.split( '_' );
			return parts[ parts.length - 1 ];
		},
	};

	$( function () {
		smBulkEditUsers.init();
	} );
}( jQuery, window.wp ) );

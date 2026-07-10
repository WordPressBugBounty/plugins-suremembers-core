( function ( $ ) {
	$( document ).ready( function() {
		initializeSelect2();

		$( document ).on( 'menu-item-added', function() {
			initializeSelect2();
		} );
	} );

	function initializeSelect2() {
		$( '.suremembers-select2' ).select2( {
			minimumInputLength: 1,
			ajax: {
				url: suremembers_menu_items.ajax_url,
				type: 'POST',
				delay: 250,
				data ( params ) {
					const query = {
						security: suremembers_menu_items.security,
						action: 'queried_access_groups',
						search: params.term,
					};

					return query;
				},
				processResults ( response ) {
					return {
					  	results: response.data,
					};
				},
			  },
		} );
	}
}( jQuery ) );

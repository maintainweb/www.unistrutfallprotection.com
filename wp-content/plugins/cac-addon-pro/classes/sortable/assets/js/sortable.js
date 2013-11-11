/*
 * Fires when the dom is ready
 *
 */
jQuery(document).ready(function() {

	// init
	cpac_sorting_events();

	// re-init on changing column type
	jQuery(document).on( 'change', '.column_type select', function(){
		// delay will make sure column has changed
		setTimeout("cpac_sorting_events()", 300);
	});

});

/*
 * Form Events
 *
 * @since 2.0.0
 */
function cpac_sorting_events() {

	jQuery( '.column_sorting .input label' ).click( function(){

		var val = jQuery( 'input', this ).val();

		if ( 'on' == val ) {
			jQuery( this ).closest( '.cpac-column' ).find( '.column-meta .column_label .sorting' ).addClass( 'on' );
		} else {
			jQuery( this ).closest( '.cpac-column' ).find( '.column-meta .column_label .sorting' ).removeClass( 'on' );
		}
	});

};
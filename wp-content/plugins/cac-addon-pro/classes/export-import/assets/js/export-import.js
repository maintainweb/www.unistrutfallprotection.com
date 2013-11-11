/*
 *	Fires when the dom is ready
 *
 */
jQuery(document).ready(function() {

	if ( jQuery('#cpac').length === 0 )
		return false;

	cpac_export_multiselect();
	cpac_import();
});

/*
 * Export Multiselect
 *
 * @since 1.5
 */
function cpac_export_multiselect() {

	if( jQuery('#cpac_export_types').length === 0 )
		return;

	var export_types = jQuery('#cpac_export_types');

	// init
	export_types.multiSelect();

	// click events
	jQuery('#export-select-all').click( function(e){
		export_types.multiSelect('select_all');
		e.preventDefault();
	});
}

/*
 * Import
 *
 * @since 1.5
 */
function cpac_import() {
	var container = jQuery('#cpac_import_input');

	jQuery('#upload', container).change(function () {
		if ( jQuery(this).val() )
			jQuery('#import-submit', container).addClass('button-primary');
		else
			jQuery('#import-submit', container).removeClass('button-primary');
	});
}
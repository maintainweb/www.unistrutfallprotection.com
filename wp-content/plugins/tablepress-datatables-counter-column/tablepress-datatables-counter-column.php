<?php
/*
Plugin Name: TablePress Extension: DataTables Counter Column
Plugin URI: http://tablepress.org/extensions/datatables-counter-column/
Description: Custom Extension for TablePress to make it possible to have a fixed "counter column" for DataTables
Version: 1.0
Author: Tobias Bäthge
Author URI: http://tobias.baethge.com/
*/

/**
 * Register necessary Plugin Filters
 */
add_filter( 'tablepress_shortcode_table_default_shortcode_atts', 'tablepress_add_shortcode_parameters_counter_column' );
add_filter( 'tablepress_table_js_options', 'tablepress_add_counter_column_js_options', 10, 3 );
add_filter( 'tablepress_datatables_parameters', 'tablepress_add_counter_column_js_parameter', 10, 4 );

/**
 * Add "datatables_counter_column" as a valid parameter to the [table /] Shortcode
 */
function tablepress_add_shortcode_parameters_counter_column( $default_atts ) {
	$default_atts['datatables_counter_column'] = false;
	return $default_atts;
}

/**
 * Pass "datatables_counter_column" from Shortcode parameters to JavaScript arguments
 */
function tablepress_add_counter_column_js_options( $js_options, $table_id, $render_options ) {
	$js_options['datatables_counter_column'] = $render_options['datatables_counter_column'];
	return $js_options;
}

/**
 * Evaluate "datatables_counter_column" parameter and add corresponding JavaScript code, if needed
 */
function tablepress_add_counter_column_js_parameter( $parameters, $table_id, $html_id, $js_options ) {

	if ( empty( $js_options['datatables_counter_column'] ) )
		return $parameters;

	$parameters['fnDrawCallback'] = <<<JS
\n"fnDrawCallback": function ( oSettings ) {
	/* Need to redo the counters if filtered or sorted */
	if ( oSettings.bSorted || oSettings.bFiltered ) {
		for ( var i=0, iLen=oSettings.aiDisplay.length ; i<iLen ; i++ ) {
			$('td:eq(0)', oSettings.aoData[ oSettings.aiDisplay[i] ].nTr ).html( i+1 );
		}
	}
}
JS;
	$parameters['aoColumnDefs'] = <<<JS
\n"aoColumnDefs": [
	{ "bSortable": false, "aTargets": [ 0 ] }
]
JS;

	return $parameters;
}
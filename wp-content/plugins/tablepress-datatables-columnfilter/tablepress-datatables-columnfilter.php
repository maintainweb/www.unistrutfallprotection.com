<?php
/*
Plugin Name: TablePress Extension: DataTables Column Filter
Plugin URI: http://tablepress.org/extensions/datatables-column-filter/
Description: Custom Extension for TablePress to add the DataTables Column Filter plugin
Version: 1.0
Author: Tobias Bäthge
Author URI: http://tobias.baethge.com/
*/

// see http://jquery-datatables-column-filter.googlecode.com/svn/trunk/default.html

/**
 * Register necessary Plugin Filters
 */
add_filter( 'tablepress_shortcode_table_default_shortcode_atts', 'tablepress_add_shortcode_parameters_columnfilter' );
add_filter( 'tablepress_table_js_options', 'tablepress_add_columnfilter_js_options', 10, 3 );
add_filter( 'tablepress_datatables_command', 'tablepress_add_columnfilter_js_command', 10, 5 );

/**
 * Add "datatables_columnfilter" as a valid parameter to the [table /] Shortcode
 */
function tablepress_add_shortcode_parameters_columnfilter( $default_atts ) {
	$default_atts['datatables_columnfilter'] = '';
	return $default_atts;
}

/**
 * Pass "datatables_columnfilter" from Shortcode parameters to JavaScript arguments
 */
function tablepress_add_columnfilter_js_options( $js_options, $table_id, $render_options ) {
	$js_options['datatables_columnfilter'] = $render_options['datatables_columnfilter'];

	// register the JS
	if ( '' != $js_options['datatables_columnfilter'] ) {
		$js_columnfilter_url = plugins_url( 'columnfilter.js', __FILE__ );
		wp_enqueue_script( 'tablepress-columnfilter', $js_columnfilter_url, array( 'tablepress-datatables' ), '1.5.0', true );
	}

	return $js_options;
}

/**
 * Evaluate "datatables_columnfilter" parameter and add corresponding JavaScript code, if needed
 */
function tablepress_add_columnfilter_js_command( $command, $html_id, $parameters, $table_id, $js_options ) {

	if ( empty( $js_options['datatables_columnfilter'] ) )
		return $command;

	// get columnfilter parameters from Shortcode attribute, except if it's just set to "true"
	$columnfilter_parameters = '';
	if ( true !== $js_options['datatables_columnfilter'] )
		$columnfilter_parameters = $js_options['datatables_columnfilter'];

	$command = "$('#{$html_id}').dataTable({$parameters}).columnFilter({$columnfilter_parameters});";
	return $command;
}
<?php
/*
Plugin Name: TablePress Extension: DataTables HTML Numbers in Tables
Plugin URI: http://tablepress.org/extensions/datatables-numhtml/
Description: Custom Extension for TablePress to add the DataTables "HTML Numbers in Tables" functionality
Version: 1.1
Author: Tobias Bäthge
Author URI: http://tobias.baethge.com/
*/

/**
 * Register necessary Plugin Filters
 */
add_filter( 'tablepress_table_js_options', 'tablepress_enqueue_datatables_numhtml_js', 10, 3 );

function tablepress_enqueue_datatables_numhtml_js( $js_options, $table_id, $render_options ) {
	$suffix = ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) ? '' : '.min';
	$js_url = plugins_url( "jquery.datatables.numhtml{$suffix}.js", __FILE__ );
	wp_enqueue_script( 'tablepress-datatables-numhtml', $js_url, array( 'tablepress-datatables' ), '1.1', true );
	return $js_options;
}
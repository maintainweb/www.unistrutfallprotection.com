<?php
/*
Plugin Name: TablePress Extension: DataTables ColVis
Plugin URI: http://tablepress.org/extensions/datatables-colvis/
Description: Custom Extension for TablePress to add the DataTables ColVis functionality
Version: 1.0
Author: Tobias Bäthge
Author URI: http://tobias.baethge.com/
*/

/**
 * Register necessary Plugin Filters
 */
add_filter( 'tablepress_shortcode_table_default_shortcode_atts', 'tablepress_add_shortcode_parameters_colvis' );
add_filter( 'tablepress_table_js_options', 'tablepress_add_colvis_js_options', 10, 3 );
add_filter( 'tablepress_datatables_parameters', 'tablepress_add_colvis_js_parameters', 10, 4 );
if ( ! is_admin() )
	add_action( 'wp_enqueue_scripts', 'tablepress_enqueue_colvis_css' );

/**
 * Add "datatables_colvis" as a valid parameter to the [table /] Shortcode
 */
function tablepress_add_shortcode_parameters_colvis( $default_atts ) {
	$default_atts['datatables_colvis'] = false;
	return $default_atts;
}

/**
 * Pass "datatables_colvis" from Shortcode parameters to JavaScript arguments
 */
function tablepress_add_colvis_js_options( $js_options, $table_id, $render_options ) {
	$js_options['datatables_colvis'] = $render_options['datatables_colvis'];

	// register the JS
	if ( $js_options['datatables_colvis'] ) {
		$js_colvis_url = plugins_url( 'js/ColVis.min.js', __FILE__ );
		wp_enqueue_script( 'tablepress-colvis', $js_colvis_url, array( 'tablepress-datatables' ), '1.0.8', true );
	}

	return $js_options;
}

/**
 * Evaluate "datatables_colvis" parameter and add corresponding JavaScript code, if needed
 */
function tablepress_add_colvis_js_parameters( $parameters, $table_id, $html_id, $js_options ) {
	if ( $js_options['datatables_colvis'] )
		$parameters['sDom'] = '"sDom": \'C<"ColVis_clear">lfrtip\'';

	return $parameters;
}

function tablepress_enqueue_colvis_css() {
	$suffix = ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) ? '' : '.min';
	$colvis_css_url = plugins_url( "css/ColVis{$suffix}.css", __FILE__ );
	wp_enqueue_style( 'tablepress-colvis-css', $colvis_css_url, array(), '1.0.8' );
}

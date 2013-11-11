<?php
/*
Plugin Name: TablePress Extension: Responsive Tables
Plugin URI: http://tablepress.org/extensions/responsive-tables/
Description: Custom Extension for TablePress to add a possibility to make tables responsive
Version: 1.1
Author: Tobias Bäthge
Author URI: http://tobias.baethge.com/
*/

// from http://dbushell.com/demos/tables/rt_05-01-12.html

// [table id=1 responsive="tablet" /]
// The parameter "responsive" (from 'none', 'phone', 'tablet', 'desktop', 'all') determines that largest device that shall show the changed table

/**
 * Register necessary Plugin Filters
 */
add_filter( 'tablepress_shortcode_table_default_shortcode_atts', 'tablepress_responsive_tables_add_shortcode_parameter' );
add_filter( 'tablepress_table_render_options', 'tablepress_responsive_tables_add_extra_css_class', 10, 2 );
if ( ! is_admin() )
	add_action( 'wp_print_scripts', 'tablepress_responsive_tables_enqueue_css' );

/**
 * Add "responsive" as a valid parameter to the [table /] Shortcode
 */
function tablepress_responsive_tables_add_shortcode_parameter( $default_atts ) {
	$default_atts['responsive'] = 'none'; // 'phone', 'tablet', 'desktop', 'all'
	return $default_atts;
}

/**
 * Enqueue the CSS file with the responsive CSS
 */
function tablepress_responsive_tables_enqueue_css() {
	$suffix = ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) ? '' : '.min';
	$css_url = plugins_url( "tablepress-responsive{$suffix}.css", __FILE__ );
	wp_enqueue_style( 'tablepress-responsive', $css_url, array( 'tablepress-default' ), '1.1' );
	echo "<!--[if !IE]><!-->\n";
	wp_print_styles( 'tablepress-responsive' );
	echo "<!--<![endif]-->\n";
}

/*
 * Add extra CSS class, if "responsive" Shortcode parameter is set
 */
function tablepress_responsive_tables_add_extra_css_class( $render_options, $table ) {
	if ( in_array( $render_options['responsive'], array( 'phone', 'tablet', 'desktop', 'all' ) ) ) {
		if ( '' != $render_options['extra_css_classes'] )
			$render_options['extra_css_classes'] .= ' ';
		$render_options['extra_css_classes'] .= "tablepress-responsive-{$render_options['responsive']}";
	}

	return $render_options;
}
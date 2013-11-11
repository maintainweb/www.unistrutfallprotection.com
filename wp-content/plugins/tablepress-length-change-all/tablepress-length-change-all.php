<?php
/*
Plugin Name: TablePress Extension: Pagination Length Change "All" entry
Plugin URI: http://tablepress.org/extensions/length-change-all-entry/
Description: Custom Extension for TablePress to add a Pagination Length Change "All" entry
Version: 1.1
Author: Tobias Bäthge
Author URI: http://tobias.baethge.com/
*/

add_filter( 'tablepress_datatables_parameters', 'tablepress_pagination_length_change_all_entry', 10, 4 );

function tablepress_pagination_length_change_all_entry( $parameters, $table_id, $html_id, $js_options ) {
	if ( $js_options['datatables_paginate'] && $js_options['datatables_lengthchange'] ) {
		$length_menu = array( 10, 25, 50, 100 );
		if ( ! in_array( $js_options['datatables_paginate_entries'], $length_menu, true ) ) {
			$length_menu[] = $js_options['datatables_paginate_entries'];
			sort( $length_menu, SORT_NUMERIC );
		}
		$length_menu_keys = $length_menu_values = array_values( $length_menu );
		$length_menu_keys[] = -1;
		$length_menu_values[] = '"All"';
		$parameters['aLengthMenu'] = '"aLengthMenu":[[' . implode( ',', $length_menu_keys ) . '],[' . implode( ',', $length_menu_values ) . ']]';
	}
	return $parameters;
}
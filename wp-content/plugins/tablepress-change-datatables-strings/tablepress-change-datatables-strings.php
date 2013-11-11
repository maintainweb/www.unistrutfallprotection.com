<?php
/*
Plugin Name: TablePress Extension: Change DataTables strings
Plugin URI: http://tablepress.org/extensions/change-datatables-strings/
Description: Custom Extension for TablePress to change strings in the DataTables JS library
Version: 1.0
Author: Tobias Bäthge
Author URI: http://tobias.baethge.com/
*/

add_action( 'tablepress_datatables_language_file', 'tablepress_change_datatables_language_file', 10, 3 );

function tablepress_change_datatables_language_file( $language_file, $datatables_locale, $path ) {
	$changed_language_file = plugin_dir_path( __FILE__ ) . "lang-{$datatables_locale}.js";
	if ( file_exists( $changed_language_file ) )
		$language_file = $changed_language_file;
	return $language_file;
}
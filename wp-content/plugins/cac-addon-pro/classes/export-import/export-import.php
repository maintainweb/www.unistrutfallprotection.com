<?php
/*
Plugin Name: 		Codepress Admin Columns - Export Import
Version: 			1.0
Description: 		Adds Export / Import functionality
Author: 			Codepress
Author URI: 		http://www.codepresshq.com
Plugin URI: 		http://www.codepresshq.com/wordpress-plugins/admin-columns/
Domain Path: 		/languages
*/

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

define( 'CAC_EI_VERSION', 	 	CAC_PRO_VERSION );
define( 'CAC_EI_TEXTDOMAIN', 	CAC_PRO_TEXTDOMAIN );
define( 'CAC_EI_URL', 			plugin_dir_url( __FILE__ ) );
define( 'CAC_EI_DIR', 			plugin_dir_path( __FILE__ ) );

// only run plugin in the admin interface
if ( ! is_admin() )
	return false;

/**
 * Init
 *
 * Loads CPAC into the constructor
 *
 * @since
 */
function init_cpac_export_import( $cpac ) {

	require_once CAC_EI_DIR . 'classes/export_import.php';
	new CAC_Export_Import( $cpac );
}
add_action( 'cac/controllers', 'init_cpac_export_import' );


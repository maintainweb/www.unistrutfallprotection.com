<?php
/*
Plugin Name: 		Codepress Admin Columns - Pro add-on
Version: 			1.0
Description: 		Adds Pro functionality for Admin Columns.
Author: 			Codepress
Author URI: 		http://www.codepresshq.com
Plugin URI: 		http://www.codepresshq.com/wordpress-plugins/admin-columns/
Text Domain: 		cac-addon-pro
Domain Path: 		/languages
*/

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

define( 'CAC_PRO_VERSION', 	 	'1.0' );
define( 'CAC_PRO_TEXTDOMAIN', 	'cac-addon-pro' );
define( 'CAC_PRO_URL', 			plugin_dir_url( __FILE__ ) );
define( 'CAC_PRO_DIR', 			plugin_dir_path( __FILE__ ) );

// only run plugin in the admin interface
if ( ! is_admin() )
	return false;

// Enables automatic plugin updates
include_once 'classes/update.php';
new CAC_Addon_Update( array(
	'store_url'			=> 'http://www.codepresshq.com',
	'product_id'		=> 'cac-pro',
	'version'			=> CAC_PRO_VERSION,
	'secret_key'		=> 'jhdsh23489hsdfkja9HHe',
	'product_name'		=> 'Pro Add-on',
	'file'				=> __FILE__
));

/**
 * Addon class
 *
 * @since 1.0
 *
 */
class CAC_Addon_Pro {

	/**
	 * Constructor
	 *
	 * @since 1.0
	 */
	function __construct() {

		load_plugin_textdomain( CAC_PRO_TEXTDOMAIN, false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );

		// init
		$this->init();

		// add to admin columns list
		add_filter( 'cac/addon_list', array( $this, 'add_addon' ) );

		// deactivate sortorder
		add_action( 'plugins_loaded', array( $this, 'deactivate_sortorder_addon' ) );
	}

	/**
	 * Init
	 *
	 * @since 1.0
	 */
	function init() {

		if ( ! class_exists('CAC_Export_Import') ) {
			include_once 'classes/export-import/export-import.php';
		}
		if ( ! class_exists('CAC_Addon_Filtering') ) {
			include_once 'classes/filtering/filtering.php';
		}
		if ( ! class_exists('CAC_Addon_Sortable') ) {
			include_once 'classes/sortable/sortable.php';
		}
	}

	/**
	 * Deactivation notice
	 *
	 * @since 1.0
	 */
	function deactivation_notice() {
		 echo '<div class="updated"><p>' . __( "Sortorder has been <strong>deactivated</strong>. You are using the Pro add-on, which contains the same functionality.", CAC_PRO_TEXTDOMAIN ) . '</p></div>';
	}

	/**
	 * Deactivate
	 *
	 * @since 1.0
	 */
	function deactivate_sortorder_addon() {

		$plugin = 'cac-addon-sortable/cac-addon-sortable.php';
		if( function_exists('is_plugin_active') && is_plugin_active( $plugin ) ) {
			deactivate_plugins( $plugin );

			add_action('admin_notices', array( $this, 'deactivation_notice' ) );

		}
	}

	/**
	 * Add Addon to Admin Columns list
	 *
	 * @since 1.0
	 */
	public function add_addon( $addons ) {

		$addons[ 'cac-addon-pro' ] = __( 'Pro add-on', CAC_PRO_TEXTDOMAIN );

		return $addons;
	}
}

new CAC_Addon_Pro();

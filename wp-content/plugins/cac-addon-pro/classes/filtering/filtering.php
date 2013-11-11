<?php
/*
Plugin Name: 		Codepress Admin Columns - Filtering add-on
Version: 			1.0
Description: 		Add support for multiple columns.
Author: 			Codepress
Author URI: 		http://www.codepresshq.com
Plugin URI: 		hhttp://www.codepresshq.com/wordpress-plugins/admin-columns/#addons
Text Domain: 		cac-addon-filtering
Domain Path: 		/languages
*/

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

define( 'CAC_FC_VERSION', 	 	CAC_PRO_VERSION );
define( 'CAC_FC_TEXTDOMAIN', 	CAC_PRO_TEXTDOMAIN );
define( 'CAC_FC_URL', 			plugins_url( '', __FILE__ ) );
define( 'CAC_FC_DIR', 			plugin_dir_path( __FILE__ ) );

/**
 * Addon class
 *
 * @since 1.0
 */
class CAC_Addon_Filtering {

	function __construct() {

		// styling & scripts
		add_action( "admin_print_styles-settings_page_codepress-admin-columns", array( $this, 'scripts' ) );

		// Add column properties
		add_filter( 'cac/column/properties', array( $this, 'set_column_default_properties' ) );

		// Add column options
		add_filter( 'cac/column/options', array( $this, 'set_column_default_options' ) );

		// Add setting field
		add_action( 'cac/column/settings_after', array( $this, 'add_settings_field' ), 9 );

		// add setting sort indicator
		add_action( 'cac/column/label', array( $this, 'add_label_filter_indicator' ), 9 );

		// init addon
		add_action( 'cac/loaded', array( $this, 'init_addon_filtering' ) );
	}

	/**
	 * Add Addon to Admin Columns list
	 *
	 * @since 1.0
	 */
	public function add_addon( $addons ) {

		// Key is also used by Addon feed.
		$addons[ 'cac-filtering' ] = __( 'Filtering add-on', CAC_FC_TEXTDOMAIN );

		return $addons;
	}

	/**
	 * Register scripts
	 *
	 * @since 1.0
	 */
	public function scripts() {

		wp_enqueue_style( 'cac-addon-filtering-css', CAC_FC_URL . '/assets/css/filtering.css', array(), CAC_FC_VERSION, 'all' );wp_enqueue_script( 'cac-addon-filtering-js', CAC_FC_URL . '/assets/js/filtering.js', array( 'jquery' ), CAC_FC_VERSION, 'all' );
	}

	/**
	 * Set column properties
	 *
	 * @since 1.0
	 */
	function set_column_default_properties( $properties ) {

		$properties['is_filterable'] = false;

		return $properties;
	}

	/**
	 * Set column options
	 *
	 * @since 1.0
	 */
	function set_column_default_options( $options ) {

		$options['filter'] = 'off';

		return $options;
	}

	/**
	 * Settings
	 *
	 * @since 1.0
	 */
	function add_settings_field( $column ) {

		if ( ! $column->properties->is_filterable )
			return false;

		$sort = isset( $column->options->filter ) ? $column->options->filter : '';

		?>

		<tr class="column_filtering">
			<?php $column->label_view( __( 'Enable filtering?', CAC_FC_TEXTDOMAIN ), __( 'This will make the column support filering.', CAC_FC_TEXTDOMAIN ), 'filter' ); ?>
			<td class="input">
				<label for="<?php $column->attr_id( 'filter' ); ?>-on">
					<input type="radio" value="on" name="<?php $column->attr_name( 'filter' ); ?>" id="<?php $column->attr_id( 'filter' ); ?>-on"<?php checked( $column->options->filter, 'on' ); ?>>
					<?php _e( 'Yes'); ?>
				</label>
				<label for="<?php $column->attr_id( 'filter' ); ?>-off">
					<input type="radio" value="off" name="<?php $column->attr_name( 'filter' ); ?>" id="<?php $column->attr_id( 'filter' ); ?>-off"<?php checked( $column->options->filter, '' ); ?><?php checked( $column->options->filter, 'off' ); ?>>
					<?php _e( 'No'); ?>
				</label>
			</td>
		</tr>

	<?php
	}

	/**
	 * Meta Label
	 *
	 * @since 1.0
	 */
	function add_label_filter_indicator( $column ) {

		if ( ! $column->properties->is_filterable )
			return false;
		?>

		<span class="filtering <?php echo $column->options->filter; ?>"><?php _e( 'filter', CAC_FC_TEXTDOMAIN ); ?></span>

		<?php
	}

	/**
	 * Init Addons
	 *
	 * @since 1.0
	 */
	function init_addon_filtering( $cpac ) {

		// Abstract
		include_once 'classes/model.php';

		// Childs
		include_once 'classes/post.php';
		include_once 'classes/user.php';

		// Posts
		foreach ( $cpac->get_post_types() as $post_type ) {

			if ( $storage_model = $cpac->get_storage_model( $post_type ) )
				new CAC_Filtering_Model_Post( $storage_model );
		}

		// User
		if ( $storage_model = $cpac->get_storage_model( 'wp-users' ) )
			new CAC_Filtering_Model_User( $storage_model );

	}
}

new CAC_Addon_Filtering;
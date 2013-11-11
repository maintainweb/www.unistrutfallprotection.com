<?php
/*
Plugin Name: 		Codepress Admin Columns - Sortable add-on
Version: 			1.0
Description: 		Add support for multiple columns.
Author: 			Codepress
Author URI: 		http://www.codepresshq.com
Plugin URI: 		http://www.codepresshq.com/wordpress-plugins/admin-columns/sortable-add-on/
Text Domain: 		cac-addon-sortable
Domain Path: 		/languages
*/

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

define( 'CAC_SC_VERSION', 	 	CAC_PRO_VERSION );
define( 'CAC_SC_TEXTDOMAIN', 	CAC_PRO_TEXTDOMAIN );
define( 'CAC_SC_URL', 			plugin_dir_url( __FILE__ ) );
define( 'CAC_SC_DIR', 			plugin_dir_path( __FILE__ ) );

// only run plugin in the admin interface
if ( ! is_admin() )
	return false;

/**
 * Addon class
 *
 * @since 1.0
 *
 */
class CAC_Addon_Sortable {

	/**
	 * Constructor
	 *
	 * @since 1.0
	 */
	function __construct() {

		// styling & scripts
		add_action( "admin_print_styles-settings_page_codepress-admin-columns", array( $this, 'scripts' ) );

		// add column properties
		add_filter( 'cac/column/properties', array( $this, 'set_column_default_properties' ) );

		// add column options
		add_filter( 'cac/column/options', array( $this, 'set_column_default_options' ) );

		// add setting field
		add_action( 'cac/column/settings_after', array( $this, 'add_settings_field' ), 9 );

		// add setting sort indicator
		add_action( 'cac/column/label', array( $this, 'add_label_sort_indicator' ), 9 );

		// init addon
		add_action( 'cac/loaded', array( $this, 'init_addon_sortables' ) );
	}

	/**
	 * Add Addon to Admin Columns list
	 *
	 * @since 1.0
	 */
	public function add_addon( $addons ) {

		$addons[ 'cac-sortable' ] = __( 'Sortable add-on', CAC_SC_TEXTDOMAIN );

		return $addons;
	}

	/**
	 * Register scripts
	 *
	 * @since 1.0
	 */
	public function scripts() {

		if ( isset( $_GET['page'] ) && in_array( $_GET['page'], array( 'cpac-settings', 'codepress-admin-columns' ) ) ) {

			wp_enqueue_style( 'cac-addon-sortable-columns-css', CAC_SC_URL . 'assets/css/sortable.css', array(), CAC_SC_VERSION, 'all' );
			wp_enqueue_script( 'cac-addon-sortable-columns-js', CAC_SC_URL . 'assets/js/sortable.js', array( 'jquery' ), CAC_SC_VERSION, 'all' );
		}
	}

	/**
	 * Set column properties
	 *
	 * @since 1.0
	 */
	function set_column_default_properties( $properties ) {

		$properties['is_sortable'] = false;

		return $properties;
	}

	/**
	 * Set column options
	 *
	 * @since 1.0
	 */
	function set_column_default_options( $options ) {

		$options['sort'] = 'off';

		return $options;
	}

	/**
	 * Settings
	 *
	 * @since 1.0
	 */
	function add_settings_field( $column ) {

		if ( ! $column->properties->is_sortable )
			return false;

		$sort = isset( $column->options->sort ) ? $column->options->sort : '';
		?>
		<tr class="column_sorting">
			<?php $column->label_view( __( 'Enable sorting?', CAC_SC_TEXTDOMAIN ), __( 'This will make the column support sorting.', CAC_SC_TEXTDOMAIN ), 'sorting' ); ?>
			<td class="input">
				<label for="<?php $column->attr_id( 'sort' ); ?>-on">
					<input type="radio" value="on" name="<?php $column->attr_name( 'sort' ); ?>" id="<?php $column->attr_id( 'sort' ); ?>-on"<?php checked( $column->options->sort, 'on' ); ?> />
					<?php _e( 'Yes'); ?>
				</label>
				<label for="<?php $column->attr_id( 'sort' ); ?>-off">
					<input type="radio" value="off" name="<?php $column->attr_name( 'sort' ); ?>" id="<?php $column->attr_id( 'sort' ); ?>-off"<?php checked( $column->options->sort, '' ); ?><?php checked( $column->options->sort, 'off' ); ?> />
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
	function add_label_sort_indicator( $column ) {

		if ( ! $column->properties->is_sortable )
			return false;

		?>
		<span class="sorting <?php echo $column->options->sort; ?>"><?php _e( 'sort', CAC_SC_TEXTDOMAIN ); ?></span>
		<?php
	}

	/**
	 * Init Addons
	 *
	 * @since 1.0
	 */
	function init_addon_sortables( $cpac ) {

		// Abstract
		include_once 'classes/model.php';

		// Childs
		include_once 'classes/post.php';
		include_once 'classes/media.php';
		include_once 'classes/user.php';
		include_once 'classes/comment.php';
		include_once 'classes/link.php';

		// Posts
		foreach ( $cpac->get_post_types() as $post_type ) {

			if ( $storage_model = $cpac->get_storage_model( $post_type ) )
				new CAC_Sortable_Model_Post( $storage_model );
		}

		// Media
		if ( $storage_model = $cpac->get_storage_model( 'wp-media' ) )
			new CAC_Sortable_Model_Media( $storage_model );

		// User
		if ( $storage_model = $cpac->get_storage_model( 'wp-users' ) )
			new CAC_Sortable_Model_User( $storage_model );

		// Comment
		if ( $storage_model = $cpac->get_storage_model( 'wp-comments' ) )
			new CAC_Sortable_Model_Comment( $storage_model );

		// Link
		if ( $storage_model = $cpac->get_storage_model( 'wp-links' ) )
			new CAC_Sortable_Model_Link( $storage_model );
	}
}

new CAC_Addon_Sortable();



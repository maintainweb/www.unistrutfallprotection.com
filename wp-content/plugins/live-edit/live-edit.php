<?php

/*
Plugin Name: Live Edit
Plugin URI: http://www.elliotcondon.com/
Description: Edit the title, content and any ACF fields from the front end of your website!
Version: 2.0.0
Author: Elliot Condon
Author URI: http://www.elliotcondon.com/
License: GPL
Copyright: Elliot Condon
*/

$live_edit = new live_edit();

class live_edit
{ 
	var $dir,
		$path,
		$version,
		$acf_version,
		$data;
	
	
	/*
	*  Constructor
	*
	*  @description: 
	*  @since 1.0.0
	*  @created: 23/06/12
	*/
	
	function __construct()
	{

		// vars
		$this->dir = plugins_url('',__FILE__);
		$this->path = plugin_dir_path(__FILE__);
		$this->version = '2.0.0';
		$this->defaults = array(
			'panel_width'	=>	600,
		);
		
		foreach( $this->defaults as $k => $v )
		{
			$db_v = get_option('live_edit_' . $k, $v);
			
			if( $db_v )
			{
				$v = $db_v;
			}
			
			$this->data[ $k ] = $v;
		}
		
		
		// set text domain
		load_plugin_textdomain('live-edit', false, basename(dirname(__FILE__)).'/lang' );
		
		
		// actions
		add_action('init', array($this,'init'));
		
		
		return true;
	}
	
	
	/*
	*  init
	*
	*  @description: 
	*  @created: 7/09/12
	*/
	
	function init()
	{
		// must be logged in
		if( is_user_logged_in() )
		{
			// actions
			add_action('admin_head', array($this,'admin_head'));
			add_action('admin_menu', array($this,'admin_menu'));
			
			
			add_action('wp_enqueue_scripts', array($this,'wp_enqueue_scripts'));
			add_action('wp_head', array($this,'wp_head'));
			add_action('wp_footer', array($this,'wp_footer'));
			add_action('wp_ajax_live_edit_update_width', array($this, 'ajax_update_width'));
		}
	}
	
	
	/*
	*  admin_head
	*
	*  @description:
	*  @since 1.0.0
	*  @created: 25/07/12
	*/
	
	function admin_head()
	{
		echo '<style type="text/css">#menu-settings a[href="options-general.php?page=live-edit-panel"] { display:none; }</style>';
	}
	
	
	/*
	*  admin_menu
	*
	*  @description:
	*  @since 1.0.0
	*  @created: 25/07/12
	*/
	
	function admin_menu()
	{
		global $pagenow;
		
		$slug = add_options_page(__("Live Edit Panel",'le'), __("Live Edit Panel",'le'), 'edit_posts', 'live-edit-panel', array($this, 'view_panel'));
		
		
		if( $pagenow == 'options-general.php' && isset($_GET['page']) && $_GET['page'] == 'live-edit-panel')
		{
			add_action('admin_enqueue_scripts', array($this, 'page_admin_enqueue_scripts'));
			add_action('admin_head', array($this, 'page_admin_head'));
		}
	}
	
	
	/*
	*  admin_enqueue_scripts
	*
	*  @description: run after post query but before any admin script / head actions. A good place to register all actions.
	*  @since: 3.6
	*  @created: 26/01/13
	*/
	
	function page_admin_enqueue_scripts()
	{
		// actions
		do_action('acf/input/admin_enqueue_scripts');
	}
	
	
	/*
	*  save_post
	*
	*  {description}
	*
	*  @since: 4.0.3
	*  @created: 16/05/13
	*/
	
	function save_post()
	{
		// validate
		if( !isset($_POST['post_id']) )
		{
			return;
		}
		
		
		// vars
		$post_id = $_POST['post_id'];
		$post_data = array();
		
		
		foreach( array('post_title', 'post_content', 'post_excerpt') as $v )
		{
			if( isset($_POST['fields'][ $v ]) )
			{
				$post_data[ $v ] = $_POST['fields'][ $v ];
				
				unset( $_POST['fields'][ $v ] );	
			}
		}
		
		
		// update post
		if( !empty($post_data) )
		{
			$post_data['ID'] = $post_id;
			wp_update_post( $post_data );
		}
		
		
		// save custom fields
		do_action('acf/save_post', $post_id);
		
		
		// set var
		$this->data['save_post'] = true;
		
	}
	
	
	/*
	*  page_admin_head
	*
	*  @description: 
	*  @since: 3.6
	*  @created: 17/03/13
	*/
	
	function page_admin_head()
	{	
		// save
		if( isset($_POST['nonce']) && wp_verify_nonce($_POST['nonce'], 'live-edit') )
		{
			$this->save_post();
		}
		
		
		// vars
		$options = array(
			'fields' => false,
			'post_id' => 0,
		);
		$options = array_merge($options, $_GET);
		
		
		// global vars
		global $acf;
		
	
		// Style
		echo '<link rel="stylesheet" type="text/css" href="'.$this->dir.'/css/style.admin.css?ver=' . $this->version . '" />';
	
	
		// Javascript
		echo '<script type="text/javascript" src="'.$this->dir.'/js/functions.admin.js?ver=' . $this->version . '" ></script>';
		
		
		do_action('acf/input/admin_head');
		
		
	}
	
	
	/*
	*  wp_enqueue_scripts
	*
	*  @description:
	*  @since 1.0.0
	*  @created: 25/07/12
	*/
	
	function wp_enqueue_scripts() {
		
		wp_enqueue_script(array(
			'jquery',
			'jquery-ui-core',
			'jquery-ui-widget',
			'jquery-ui-mouse',
			'jquery-ui-resizable'
		));

	}
	
	
	/*
	*  wp_head
	*
	*  @description:
	*  @since 1.0.0
	*  @created: 25/07/12
	*/
	
	function wp_head()
	{
		// Javascript
		echo '<script type="text/javascript">
			var live_edit = {
				ajaxurl : "' . admin_url( 'admin-ajax.php' ) . '",
				panel_url : "' . admin_url( 'options-general.php?page=live-edit-panel' ) . '",
				panel_width : ' . $this->data['panel_width'] . '
			};
		</script>';
		echo '<script type="text/javascript" src="' . $this->dir . '/js/functions.front.js?ver=' . $this->version . '" ></script>';
		
		
		// Style
		echo '<link rel="stylesheet" type="text/css" href="' . $this->dir . '/css/style.front.css?ver=' . $this->version . '" />';
		
	}
	
	
	/*--------------------------------------------------------------------------------------
	*
	*	wp_footer
	*
	*	@author Elliot Condon
	*	@since 1.0.0
	* 
	*-------------------------------------------------------------------------------------*/
	
	function wp_footer()
	{
		?>
		<div id="live_edit-panel">
			<div id="live_edit-iframe-cover"></div>
			<iframe id="live_edit-iframe"></iframe>
		</div>
		<div id="live_edit-vail"></div>
		<?php
		
	}
	
	
	/*--------------------------------------------------------------------------------------
	*
	*	ajax_update_width
	*
	*	@author Elliot Condon
	*	@since 1.0.0
	* 
	*-------------------------------------------------------------------------------------*/
	
	function ajax_update_width()
	{
		// vars
		$options = array(
			'live_edit_panel_width' => 600
		);
		
		$options = array_merge($options, $_POST);
		
		
		// update option
		update_option( 'live_edit_panel_width', $options['panel_width'] );
		
		
		echo "1";
		die;
	}
	
	
	/*
	*  render_live_edit_panel
	*
	*  @description: 
	*  @created: 7/09/12
	*/
	
	function view_panel()
	{
		global $acf;
		
		
		// vars
		$options = array(
			'fields' => false,
			'post_id' => 0,
		);
		$options = array_merge($options, $_GET);
		
		
		// validate
		if( !$options['post_id'] )
		{
			wp_die( "Error: No post_id parameter found" );
		}
		
		if( !$options['fields'] )
		{
			wp_die( "Error: No fields parameter found" );
		}
		
		
		// loop through and load all fields as objects
		$fields = explode(',',$options['fields']);

		if( $fields )
		{
			foreach( $fields as $k => $field_name )
			{
				$field = null;
				
				
				if( $field_name == "post_title" ) // post_title
				{
					$field = array(
						'key' => 'post_title',
						'label' => 'Post Title',
						'name' => 'post_title',
						'value' => get_post_field('post_title', $options['post_id']),
						'type'	=>	'text',
						'required' => 1
					);
				}
				elseif( $field_name == "post_content" ) // post_content
				{
					$field = array(
						'key' => 'post_content',
						'label' => 'Post Content',
						'name' => 'post_content',
						'value' => get_post_field('post_content', $options['post_id']),
						'type'	=>	'wysiwyg',
					);
				}
				elseif( $field_name == "post_excerpt" ) // post_excerpt
				{
					$field = array(
						'key' => 'post_excerpt',
						'label' => 'Post Excerpt',
						'name' => 'post_excerpt',
						'value' => get_post_field('post_excerpt', $options['post_id']),
						'type'	=>	'textarea',
					);
				}
				else // acf field
				{
					$field = get_field_object( $field_name, $options['post_id'], array( 'load_value' => false, 'format_value' => false ));
				}
				
				
				// load defualts (for post_title, etc)
				$field = apply_filters('acf/load_field_defaults', $field);
				
				$fields[ $k ] = $field;
			}
		}
	
		// render fields
?>
<div class="wrap no_move">
	
	<?php if( isset($this->data['save_post']) ): ?>
		<div class="inner-padding">
			<div id="message" class="updated"><p><?php _e("Fields updated", 'live-edit'); ?></p></div>
		</div>
	<?php endif; ?>
			
	<form id="post" method="post" name="post">
	
		<div style="display:none;">
			<input type="hidden" name="post_id" value="<?php echo $options['post_id']; ?>" />
			<input type="hidden" name="nonce" value="<?php echo wp_create_nonce( 'live-edit' ); ?>" />
		</div>
		<div class="metabox-holder" id="poststuff">
				
			<!-- Main -->
			<div id="post-body">
			<div id="post-body-content">
				<div class="acf_postbox">
				
					<?php
					
					do_action('acf/create_fields', $fields, $options['post_id']);
					
					?>	
									
					<div id="field-save">
						<ul class="hl clearfix">
							<li>
								<a class="le-button grey" href="#" id="live_edit-close">
									<?php echo isset($this->data['save_post']) ? __("Close", 'live-edit') : __("Cancel", 'live-edit') ?>
								</a>
							</li>
							<li class="right">
								<input type="submit" name="live_edit-save" class="le-button" id="live_edit-save" value="<?php esc_attr_e("Update", 'live-edit') ?>" />
							</li>
							<li class="right" id="saving-message">
								<?php _e("Saving", 'live-edit'); ?>...
							</li>
						</ul>
					</div>
					
				</div>
			</div>
			</div>
		
		</div>
	</form>
	
	<?php if( isset($this->data['save_post']) ): ?>
		<script type="text/javascript">
		(function($){
		
		// does parent exist?
		if( !parent )
		{
			return;
		}
		
		// update the div
		parent.live_edit.update_div();
		
		})(jQuery);
		</script>
	<?php endif; ?>

</div>
<?php

	}
	
}


/*
*  live_edit
*
*  @description:
*  @since 1.0.0
*  @created: 25/07/12
*/

function live_edit( $fields = false, $post_id = false )
{
	// validate fields
	if( !$fields )
	{
		return false;
	}
	
	
	// filter post_id
	$post_id = acf_filter_post_id( $post_id );
	
	
	// turn array into string
	if( is_array($fields) )
	{
		$fields = implode(',', $fields);
	}
	
	
	// remove any white spaces from $fields
	$fields = str_replace(' ', '', $fields);
	
	
	// build atts
	$atts = ' data-live_edit-fields="' . $fields . '" data-live_edit-post_id="' . $post_id . '" ';
	
	
	// echo
	echo $atts;
	
}

?>

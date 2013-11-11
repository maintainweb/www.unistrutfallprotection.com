<?php
/*
Plugin Name: Bulk-Select Featured Image
Plugin URI: http://wordpress.org/extend/plugins/bulk-select-featured-image/
Description: Allows you to select Featued Image directly from the media library view.
Version: 1.1.1
Author: Ulf Benjaminsson
Author URI: http://www.ulfben.com
Author Email: ulf@ulfben.com
License: GPL2
  
  Copyright 2012-2013 (ulf@ulfben.com)

  This program is free software; you can redistribute it and/or modify
  it under the terms of the GNU General Public License, version 2, as 
  published by the Free Software Foundation.

  This program is distributed in the hope that it will be useful,
  but WITHOUT ANY WARRANTY; without even the implied warranty of
  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
  GNU General Public License for more details.

  You should have received a copy of the GNU General Public License
  along with this program; if not, write to the Free Software
  Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/
class BulkSelectFeaturedImage {
	function __construct() {			
		load_plugin_textdomain( 'BulkSelectFeaturedImage', false, dirname( plugin_basename( __FILE__ ) ) . '/lang' );		    					
		register_activation_hook( __FILE__, array( &$this, 'activate' ) );
		register_deactivation_hook( __FILE__, array( &$this, 'deactivate' ) );
		add_action( 'after_setup_theme', array( $this, 'add_thumbnail_support'));	//http://codex.wordpress.org/Function_Reference/add_theme_support must be called before init.		
	    add_filter( 'media_row_actions', array( $this, 'addMediaLibraryAction' ),10,3 );
	    add_filter( 'admin_print_scripts-upload.php', array( $this, 'printProxyScript' ) );
	}
	function activate( $network_wide ) {
		$this->add_thumbnail_support();	
	}	
	function deactivate( $network_wide ) {		
	}	
	function add_thumbnail_support(){
		if(function_exists('add_theme_support')){
			add_theme_support('post-thumbnails');
		}	
	}
	function printProxyScript(){
		echo 
		'<script type="text/javascript">
			function WPSetThumbnailHTML(html){};
			function WPSetThumbnailID(id){};
			var post_id = -1;
			function SelectFeaturedProxy(parent_id, attachment_id, ajax_nonce){
				post_id = parent_id;
				WPSetAsThumbnail(attachment_id, ajax_nonce);
				return false;
			}				
		</script>';
	}
	function addMediaLibraryAction($actions, $post, $isdetached){	
		if(!current_user_can( 'edit_post', $post->ID ) || $isdetached){
			return $actions;		
		}
		$action = '';
		$attachment_id = $post->ID;
		$calling_post_id = $post->post_parent;
		if($calling_post_id && current_theme_supports( 'post-thumbnails', get_post_type( $calling_post_id ) )
			&& post_type_supports( get_post_type( $calling_post_id ), 'thumbnail' ) && get_post_thumbnail_id( $calling_post_id ) != $attachment_id ) {
			$ajax_nonce = wp_create_nonce( "set_post_thumbnail-$calling_post_id" );
			$action = "<a id='wp-post-thumbnail-" . $attachment_id . "' href='#' onclick='SelectFeaturedProxy(\"$calling_post_id\",\"$attachment_id\", \"$ajax_nonce\");return false;'>" . esc_html__( "Use as featured image", 'BulkSelectFeaturedImage') . "</a>";
		}
		if($action != ''){
			$actions['set_featured'] = $action;
			wp_enqueue_script('set-post-thumbnail' );		
		}
		return $actions;	
	} 
}
new BulkSelectFeaturedImage();
?>
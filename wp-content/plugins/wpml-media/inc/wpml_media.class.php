<?php


class WPML_media{


    function __construct($ext = false){
        add_action('init', array($this,'init'));
        
        global $pagenow, $wpdb;
        if(!get_option('_wpml_media_starting_help') && (empty($_GET['page']) || $_GET['page'] != 'wpml-media') ){
            
            $total_attachments = $wpdb->get_var("
                SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'attachment' AND ID NOT IN 
                (SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = 'wpml_media_processed')");
            
            if($total_attachments){
                add_action('admin_notices', array($this,'first_time_notice'));        
            }else{
                update_option('_wpml_media_starting_help', 1);
            }
            
        }   
        
    }

    function __destruct(){
        return;
    }

    function init(){
		global $sitepress, $pagenow;
        $this->plugin_localization();
		
        // Check if WPML is active. If not display warning message and don't load WPML-media
        if(!defined('ICL_SITEPRESS_VERSION') || empty($sitepress)){
            add_action('admin_notices', array($this, '_no_wpml_warning'));
            return false;            
        }elseif(version_compare(ICL_SITEPRESS_VERSION, '2.0.5', '<')){
            add_action('admin_notices', array($this, '_old_wpml_warning'));
            return false;            
        }        

		$this->languages = null;
		
        if(is_admin()){        
	
	        add_action('admin_head',array($this,'js_scripts'));  
			
			if (1 < count($sitepress->get_active_languages())) {				

				add_action('admin_menu', array($this,'menu'));
				add_filter('manage_media_columns', array($this, 'manage_media_columns'), 10 , 1);
				add_action('manage_media_custom_column', array($this, 'manage_media_custom_column'), 10, 2);
				//add_filter('manage_upload_sortable_columns', array($this, 'manage_upload_sortable_columns'));
                add_action('parse_query', array($this, 'parse_query'));
	            add_filter('posts_where', array($this,'posts_where_filter'));
				add_filter('views_upload', array($this, 'views_upload'));
				add_action('icl_post_languages_options_after', array($this, 'language_options'));
				
				// Post/page save actions
				add_action('save_post', array($this,'save_post_actions'), 10, 2);
                
                add_action('add_attachment', array($this,'save_attachment_actions'));
                add_action('edit_attachment', array($this,'save_attachment_actions'));
                //delete file filter
                add_filter('wp_delete_file',array($this,'delete_file'));            

				
                if($pagenow == 'media-upload.php'){
                    add_action('media_upload_library', array($this,'language_filter'), 99);
				}
				
				if($pagenow == 'media.php') {
	                add_action('admin_footer', array($this,'media_language_options'));            
				}
                
                if($pagenow == 'upload.php') {
                    //add language filter
                    add_action('admin_footer', array($this,'language_filter_upload_page'));  
				}
                
                add_action('wp_ajax_wpml_media_dismiss_staring_help', array($this, 'dismiss_wpml_media_starting_help'));
                add_action('wp_ajax_wpml_media_set_initial_language', array($this, 'batch_set_initial_language'));
                add_action('wp_ajax_wpml_media_duplicate_media', array($this, 'batch_duplicate_media'));
                add_action('wp_ajax_wpml_media_duplicate_featured_images', array($this, 'batch_duplicate_featured_images'));
                add_action('wp_ajax_wpml_media_mark_processed', array($this, 'batch_mark_processed'));
                add_action('wp_ajax_wpml_media_scan_prepare', array($this, 'batch_scan_prepare'));
                
                add_action( 'wp_ajax_set-post-thumbnail', array($this, 'set_post_thumbnail_actions'), 0);
                add_action( 'wp_ajax_find_posts', array($this, 'find_posts_filter'), 0);
                
				
			}
			
		}
		
		//add_filter('get_post_metadata', array($this, 'get_post_metadata'), 10, 4);
        add_filter('WPML_filter_link', array($this, 'filter_link'), 10, 2);
		add_filter('icl_ls_languages', array($this, 'icl_ls_languages'), 10, 1);
		add_action('icl_pro_translation_saved', array($this, 'icl_pro_translation_saved'), 10, 1);

    }
	
    function first_time_notice(){
        ?>
        <div class="updated message">
            <p>
                <?php _e('WPML Media Translation needs to set languages to existing media in your site.', 'wpml-media') ?>
                <a href="<?php echo admin_url('admin.php?page=wpml-media') ?>" class="button-secondary"><?php _e('Set media languages', 'wpml-media') ?></a>                
                
                <a id="wpml_media_dismiss_1" style="float: right;" href="#" onclick="jQuery.ajax({url:ajaxurl,method:'POST',data:{action:'wpml_media_dismiss_staring_help'},success:function(){jQuery('#wpml_media_dismiss_1').closest('.message').fadeOut()}}); return false;"><?php _e("Dismiss", 'wpml-media') ?></a>
            </p>
        </div>
        <?php
    }
    
    function dismiss_wpml_media_starting_help(){
        update_option('_wpml_media_starting_help', 1);
        exit;
    }
    
    function batch_scan_prepare(){
        global $wpdb;
        
        $response = array();
        $wpdb->query("DELETE FROM {$wpdb->postmeta} WHERE meta_key='wpml_media_processed'");

        $attachments = $wpdb->get_col("
            SELECT SQL_CALC_FOUND_ROWS ID FROM {$wpdb->posts} WHERE post_type = 'attachment' AND ID NOT IN 
            (SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = 'wpml_media_lang') LIMIT 1");
                    
        if(!$attachments){
            $response['message'] = __('Duplicating media.', 'wpml-media');
        }else{
            $response['message'] = __('Started...', 'wpml-media');
        }        
        
        
        echo json_encode($response);
        exit;
    }
    
    function batch_set_initial_language(){
        global $wpdb, $sitepress;
        
        $limit = 10;
        
        $response = array();
        $attachments = $wpdb->get_col("
            SELECT SQL_CALC_FOUND_ROWS ID FROM {$wpdb->posts} WHERE post_type = 'attachment' AND ID NOT IN 
            (SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = 'wpml_media_lang') LIMIT {$limit}");
        
        $found = $wpdb->get_var("SELECT FOUND_ROWS()");
        
        foreach($attachments as $att){
            update_post_meta($att, 'wpml_media_lang', $sitepress->get_current_language());
        }
        $response['left'] = max($found - $limit, 0);
        if($response['left']){
            $response['message'] = sprintf(__('Setting language to media. %d left', 'wpml-media'), $response['left']);    
        }else{
            $response['message'] = __('Duplicating media.', 'wpml-media');
        }
        
        
        echo json_encode($response);
        exit;
    }
    
    function batch_duplicate_media(){
        global $wpdb, $sitepress;
        
        $limit = 10;
        
        $response = array();
        
        
        $attachments = $wpdb->get_results("
            SELECT SQL_CALC_FOUND_ROWS p1.ID, p1.post_parent FROM {$wpdb->posts} p1 WHERE post_type = 'attachment' AND ID NOT IN 
            (SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = 'wpml_media_processed')
            ORDER BY p1.ID ASC LIMIT {$limit}
        ");
        $found = $wpdb->get_var("SELECT FOUND_ROWS()");
        
        if($attachments){
            foreach($attachments as $attachment){
                $this->create_duplicate_media($attachment);
            }
        }     
        
        $response['left'] = max($found - $limit, 0);
        if($response['left']){
            $response['message'] = sprintf(__('Duplicating media. %d left', 'wpml-media'), $response['left']);
        }else{
            $response['message'] = __('Duplicating featured images', 'wpml-media');
        }
        
        echo json_encode($response);
        exit;
    }

    function batch_duplicate_featured_images(){
        global $wpdb, $sitepress;
        
        $limit = 10;
        
        $response = array();

        $count = 0;
        
        $featured_images = $wpdb->get_results("SELECT * FROM {$wpdb->postmeta} WHERE meta_key = '_thumbnail_id' LIMIT {$limit}");
        $found = $wpdb->get_var("SELECT FOUND_ROWS()");
        
        $thumbnails = array();
        foreach ($featured_images as $featured) {
            $thumbnails[$featured->post_id] = $featured->meta_value;
        }
        
        if (sizeof($thumbnails)) {
            $ids = implode(', ', array_keys($thumbnails));
            $posts = $wpdb->get_results("SELECT ID, post_type FROM {$wpdb->posts} WHERE ID in ({$ids})");
            foreach ($posts as $post) {
                $row = $wpdb->get_row("SELECT trid, source_language_code FROM {$wpdb->prefix}icl_translations WHERE element_id={$post->ID} AND element_type = 'post_$post->post_type'");
                if ($row && $row->trid && ($row->source_language_code == null || $row->source_language_code == "")) {
            
                    $translations = $sitepress->get_element_translations($row->trid, 'post_' . $post->post_type);
                    foreach ($translations as $translation) {
                        if ($translation->element_id != $post->ID) {
                            if (!in_array($translation->element_id, array_keys($thumbnails))) {
                                // translation doesn't have a feature image
                                $t_thumbnail_id = $wpdb->get_var( $wpdb->prepare( 
                                    "
                                        SELECT pm.post_id 
                                        FROM $wpdb->postmeta AS pm 
                                        INNER JOIN $wpdb->posts AS p 
                                        ON pm.post_id = p.ID
                                        WHERE 
                                        pm.meta_key = 'wpml_media_duplicate_of' 
                                        AND pm.meta_value = %s
                                        AND p.post_parent = %s
                                        AND p.post_type = 'attachment'
                                    ", 
                                    $thumbnails[$post->ID], 
                                    $translation->element_id
                                ) );
                                update_post_meta($translation->element_id, '_thumbnail_id', $t_thumbnail_id);
                                $count += 1;
                                
                            }
                        }
                    }
                }
                
            }
        }
        
        $response['left'] = max($found - $limit, 0);
        if($response['left']){
            $response['message'] = sprintf(__('Duplicating featured images. %d left', 'wpml-media'), $response['left']);    
        }else{
            $response['message'] = __('Finishing...', 'wpml-media');   
        }
        
        
        echo json_encode($response);
        exit;
    }
    
    function batch_mark_processed(){
        global $wpdb;
        
        $response = array();
        $atts = $wpdb->get_col("SELECT ID FROM {$wpdb->posts} WHERE post_type='attachment'");
        foreach($atts as $att){
            update_post_meta($att, 'wpml_media_processed', 1);
        }
        
        update_option('_wpml_media_starting_help', 1);           
        
        $response['message'] = __('Done!', 'wpml-media');
        
        echo json_encode($response);
        
        exit;
        
        
    }
    
    function set_post_thumbnail_actions(){
        global $sitepress;
        
        $post_id = isset($_POST['post_id']) ? $_POST['post_id'] : false;
        if($post_id && get_post_meta($post_id, '_wpml_media_featured', true)){
            
            $thumbnail_id = isset($_POST['thumbnail_id']) ? $_POST['thumbnail_id'] : false;            
            $trid = $sitepress->get_element_trid($post_id, 'post_' . get_post_type($post_id));            
            $translations = $sitepress->get_element_translations($trid, 'post_' . get_post_type($post_id));
            
            // is origiginal            
            $is_original = false;
            foreach($translations as $translation){
                if($translation->original == 1 && $translation->element_id == $post_id){
                    $is_original = true;
                }
            }    
            
            if($is_original){
                foreach($translations as $language => $translation){
                    if(!$translation->original && $translation->element_id){
                         
                        if($thumbnail_id == -1){
                            delete_post_meta($translation->element_id, '_thumbnail_id');
                        }else{
                            
                            $translated_thumbnail_id = get_post_meta($translation->element_id, '_thumbnail_id', true);                            
                            if($thumbnail_id != get_post_meta($translated_thumbnail_id, 'wpml_media_duplicate_of', true)){   
                                // create a duplicate attachment (if not exists)
                                $translated_thumbnail_id = $this->create_duplicate_attachment($thumbnail_id, $translation->element_id, $language);
                                update_post_meta($translation->element_id, '_thumbnail_id', $translated_thumbnail_id);
                            }
                        }
                        
                    }
                }    
            }
            
        }
        
    }
    
    function find_posts_filter(){
        add_action('pre_get_posts', array($this, 'pre_get_posts'));
    }
    
    function pre_get_posts($query){
        $query->query['suppress_filters'] = 0;
        $query->query_vars['suppress_filters'] = 0;
    }
    
	function media_language_options() {
		global $sitepress;
		
		$att_id = $_GET['attachment_id'];
		$translations = $this->_get_translations($att_id);
		$current_lang = '';
		foreach($translations as $lang => $id) {
			if ($id == $att_id) {
				$current_lang = $lang;
				unset($translations[$lang]);
				break;
			}
		}
		
		$active_languages = icl_get_languages('orderby=id&order=asc&skip_missing=0');
		$lang_links = '';
		
		if ($current_lang) {
			
			$lang_links = '<strong>' . $active_languages[$current_lang]['native_name'] . '</strong>';
			
		}
		
		foreach ($translations as $lang => $id) {
			$lang_links .= ' | <a href="' . admin_url('media.php?attachment_id=' . $id . '&action=edit') .  '">' . $active_languages[$lang]['native_name'] . '</a>';
		}
		


		echo '<div id="icl_lang_options" style="display:none">' . $lang_links . '</div>';	
	}
	
	function icl_pro_translation_saved($new_post_id) {
		global $wpdb;
		
        $post_type = $wpdb->get_var("SELECT post_type FROM {$wpdb->posts} WHERE ID = " . $new_post_id);
		$trid = $_POST['trid'];
		$lang = $_POST['lang'];
		
		$source_lang = $wpdb->get_var("SELECT language_code FROM {$wpdb->prefix}icl_translations WHERE trid={$trid} AND source_language_code IS NULL");
		
		$this->duplicate_post_attachments($new_post_id, $trid, $source_lang, $lang);
	}

    function save_post_actions($pidd, $post){
        global $wpdb, $sitepress;
		
        list($post_type, $post_status) = $wpdb->get_row("SELECT post_type, post_status FROM {$wpdb->posts} WHERE ID = " . $pidd, ARRAY_N);            
        
        //checking - if translation and not saved before
        if (isset($_GET['trid']) && !empty($_GET['trid']) && $post_status == 'auto-draft') {
            
            //get source language
            if (isset($_GET['source_lang']) && !empty($_GET['source_lang'])) {
                $src_lang = $_GET['source_lang'];
            } else {
                $src_lang = $sitepress->get_default_language();
            }
            
            //get source id
            $src_id = $wpdb->get_var("SELECT element_id FROM {$wpdb->prefix}icl_translations WHERE trid={$_GET['trid']} AND language_code='{$src_lang}'");
            

            //delete exist auto-draft post media                
            $results = $wpdb->get_results("SELECT p.id FROM {$wpdb->posts} AS p LEFT JOIN {$wpdb->posts} AS p1 ON p.post_parent = p1.id WHERE p1.post_status = 'auto-draft'", ARRAY_A);
            $attachments = array();
            if (!empty($results)) {
                foreach ($results as $result) {
                    $attachments[] = $result["id"];
                }
                if (!empty($attachments)) {
                    $wpdb->query("DELETE FROM {$wpdb->posts} WHERE id IN (" . join(',', $attachments) . ")");
                    $wpdb->query("DELETE FROM {$wpdb->postmeta} WHERE post_id IN (" . join(',', $attachments) . ")");
                }
            }

            //checking - if set dublicate media    
            if (get_post_meta($src_id, '_wpml_media_duplicate', true)){                
                //dublicate media before first save
                $this->duplicate_post_attachments($pidd, $_GET['trid'], $src_lang, $sitepress->get_language_for_element($pidd, 'post_' . $post_type));
            }
        }

        // exceptions
        if(
               !$sitepress->is_translated_post_type($post_type)
            || isset($_POST['autosave'])
            || (isset($_POST['post_ID']) && $_POST['post_ID']!=$pidd) || (isset($_POST['post_type']) && $_POST['post_type']=='revision')
            || $post_type == 'revision'
            || get_post_meta($pidd, '_wp_trash_meta_status', true)
            || ( isset($_GET['action']) && $_GET['action']=='restore')
            || $post_status == 'auto-draft'
        ){
            return;
        }
		
		if (isset($_POST['icl_trid'])) {
			// save the post from the edit screen.
			if (isset($_POST['icl_duplicate_attachments'])) {
				update_post_meta($pidd, '_wpml_media_duplicate', intval($_POST['icl_duplicate_attachments']));
			} else {
				update_post_meta($pidd, '_wpml_media_duplicate', "0");
			}
			
			if (isset($_POST['icl_duplicate_featured_image'])) {
				update_post_meta($pidd, '_wpml_media_featured', intval($_POST['icl_duplicate_featured_image']));
			} else {
				update_post_meta($pidd, '_wpml_media_featured', "0");
			}

			$icl_trid = $_POST['icl_trid'];
		} else {
			// get trid from database.
			$icl_trid = $wpdb->get_var("SELECT trid FROM {$wpdb->prefix}icl_translations WHERE element_id={$pidd} AND element_type = 'post_$post_type'");
		}
		
		if ($icl_trid) {
			$this->duplicate_post_attachments($pidd, $icl_trid);
		}
	}
	
    /*
    function edit_attachment_actions($post_id){
        global $sitepress;
        
        $post = get_post($post_id);
        
        if($post->post_parent){
                
            // duplicate?
            if(get_post_meta($post->post_parent, '_wpml_media_duplicate', true)){

                $trid = $sitepress->get_element_trid($post->post_parent, 'post_' . get_post_type($post->post_parent));            
                $translations = $sitepress->get_element_translations($trid, 'post_' . get_post_type($post->post_parent));
                
                // is origiginal            
                $is_original = false;
                foreach($translations as $translation){
                    if($translation->original == 1 && $translation->element_id == $post_id){
                        $is_original = true;
                    }
                }  
                
                if($is_original){
                    foreach($translations as $language => $translation){
                        if(!$translation->original && $translation->element_id){
                             
                            $translated_media_id = get_post_meta($translation->element_id, '_thumbnail_id', true);                            
                            if($thumbnail_id != get_post_meta($translated_thumbnail_id, 'wpml_media_duplicate_of', true)){   
                                // create a duplicate attachment (if not exists)
                                $translated_thumbnail_id = $this->create_duplicate_attachment($thumbnail_id, $translation->element_id, $language);
                                update_post_meta($translation->element_id, '_thumbnail_id', $translated_thumbnail_id);
                            }
                            
                        }
                    }    
                }
                  
                
                
                
            }
                            
        }
        
        
        
    }
    */
    
    function save_attachment_actions($post_id){
        global $wpdb, $sitepress;     
        
        $wpml_media_lang = get_post_meta($post_id, 'wpml_media_lang', true);
        
        if(empty($wpml_media_lang)){
            $parent_post = $wpdb->get_row($wpdb->prepare(
                "SELECT p2.ID, p2.post_type FROM $wpdb->posts p1 JOIN $wpdb->posts p2 ON p1.post_parent =p2.ID WHERE p1.ID=%d"
                , $post_id));
                
            if($parent_post){
                $wpml_media_lang = $sitepress->get_language_for_element($parent_post->ID, 'post_' . $parent_post->post_type);
            }

            if(empty($wpml_media_lang)){
                $wpml_media_lang = $sitepress->get_admin_language_cookie();
            }
            if(empty($wpml_media_lang)){
                $wpml_media_lang = $sitepress->get_default_language();
            }
            
        }
        
        if(!empty($wpml_media_lang)){
            update_post_meta($post_id, 'wpml_media_lang', $wpml_media_lang);
        }
                
    }
    	
	function duplicate_post_attachments($pidd, $icl_trid, $source_lang = null, $lang = null) {
        global $wpdb, $sitepress;
		if ($icl_trid == "") {
			return;
		}
		
		if (!$source_lang) {
			$source_lang = $wpdb->get_var("SELECT source_language_code FROM {$wpdb->prefix}icl_translations WHERE element_id = $pidd AND trid = $icl_trid");
		}
		
		if ($source_lang == null || $source_lang == "") {
			// This is the original see if we should copy to translations
			
			$duplicate = get_post_meta($pidd, '_wpml_media_duplicate', true);
			$featured = get_post_meta($pidd, '_wpml_media_featured', true);
			if ($duplicate || $featured) {
				$translations = $wpdb->get_col("SELECT element_id FROM {$wpdb->prefix}icl_translations WHERE trid = $icl_trid");
				
				foreach ($translations as $translation_id) {
					if ($translation_id && $translation_id != $pidd) {
						$duplicate_t = $duplicate;
						if ($duplicate_t) {
							// See if the translation is marked for duplication
							$duplicate_t = get_post_meta($translation_id, '_wpml_media_duplicate', true);
						}
						
						$lang = $wpdb->get_var("SELECT language_code FROM {$wpdb->prefix}icl_translations WHERE element_id = $translation_id AND trid = $icl_trid");
						if ($duplicate_t || $duplicate_t == '') {
							$source_attachments = $wpdb->get_col("SELECT ID FROM $wpdb->posts WHERE post_parent = $pidd AND post_type = 'attachment'");
							$attachments = $wpdb->get_col("SELECT ID FROM $wpdb->posts WHERE post_parent = $translation_id AND post_type = 'attachment'");
	
							foreach ($source_attachments as $source_att_id) {
								$found = false;
								foreach($attachments as $att_id) {
									$duplicate_of = get_post_meta($att_id, 'wpml_media_duplicate_of', true);
									if ($duplicate_of == $source_att_id) {
										$found = true;
									}
								}
								
								if (!$found) {
									$this->create_duplicate_attachment($source_att_id, $translation_id, $lang);
								}
							}
						}
						
						$featured_t = $featured;
						if ($featured_t) {
							// See if the translation is marked for duplication
							$featured_t = get_post_meta($translation_id, '_wpml_media_featured', true);
						}
						if ($featured_t || $featured_t == '') {
							$thumbnail_id = get_post_meta($pidd, '_thumbnail_id', true);
							if ($thumbnail_id) {
								$t_thumbnail_id = $wpdb->get_var( $wpdb->prepare( 
									"
										SELECT pm.post_id 
										FROM $wpdb->postmeta AS pm 
										INNER JOIN $wpdb->posts AS p 
										ON pm.post_id = p.ID
										WHERE 
										pm.meta_key = 'wpml_media_duplicate_of' 
										AND pm.meta_value = %s
										AND p.post_parent = %s
										AND p.post_type = 'attachment'
									", 
									$thumbnail_id, 
									$translation_id
								) );
								update_post_meta($translation_id, '_thumbnail_id', $t_thumbnail_id);
							}
						}
					}
				}
			}
			
		} else {
			// This is a translation.
			
			$source_id = $wpdb->get_var("SELECT element_id FROM {$wpdb->prefix}icl_translations WHERE language_code = '$source_lang' AND trid = $icl_trid");
			
			if (!$lang) {
				$lang = $wpdb->get_var("SELECT language_code FROM {$wpdb->prefix}icl_translations WHERE element_id = $pidd AND trid = $icl_trid");
			}

			$duplicate = get_post_meta($pidd, '_wpml_media_duplicate', true);
			if ($duplicate === "") {
				// check the original state
				$duplicate = get_post_meta($source_id, '_wpml_media_duplicate', true);
			}
			
			if ($duplicate) {
				$attachments = $wpdb->get_col("SELECT ID FROM $wpdb->posts WHERE post_parent = $pidd AND post_type = 'attachment'");
				$source_attachments = $wpdb->get_col("SELECT ID FROM $wpdb->posts WHERE post_parent = $source_id AND post_type = 'attachment'");
				
				foreach ($source_attachments as $source_att_id) {
					$found = false;
					foreach($attachments as $att_id) {
						$duplicate_of = get_post_meta($att_id, 'wpml_media_duplicate_of', true);
						if ($duplicate_of == $source_att_id) {
							$found = true;
						}
					}
					
					if (!$found) {
						$this->create_duplicate_attachment($source_att_id, $pidd, $lang);
					}
					
				}
			}
			
			$featured = get_post_meta($pidd, '_wpml_media_featured', true);
			if ($featured === "") {
				// check the original state
				$featured = get_post_meta($source_id, '_wpml_media_featured', true);
			}
			
			if ($featured) {
				$thumbnail_id = get_post_meta($source_id, '_thumbnail_id', true);
				if ($thumbnail_id) {
					$t_thumbnail_id = $wpdb->get_var( $wpdb->prepare( 
						"
							SELECT pm.post_id 
							FROM $wpdb->postmeta AS pm 
							INNER JOIN $wpdb->posts AS p 
							ON pm.post_id = p.ID
							WHERE 
							pm.meta_key = 'wpml_media_duplicate_of' 
							AND pm.meta_value = %s
							AND p.post_parent = %s
							AND p.post_type = 'attachment'
						", 
						$thumbnail_id, 
						$pidd
					) );
					update_post_meta($pidd, '_thumbnail_id', $t_thumbnail_id);
				}
				
			}
			
		}

	}
	
	function language_options() {
		global $icl_meta_box_globals, $wpdb;
		
		$translation = false;
		$source_id = null;
		$translated_id = null;
		if (sizeof($icl_meta_box_globals['translations']) > 0) {
			if (!isset($icl_meta_box_globals['translations'][$icl_meta_box_globals['selected_language']])) {
				// We are creating a new translation
				$translation = true;
				// find the original
				foreach ($icl_meta_box_globals['translations'] as $trans_data) {
					if ($trans_data->original == '1') {
						$source_id = $trans_data->element_id;
						break;
					}
				}
			} else {
				$trans_data = $icl_meta_box_globals['translations'][$icl_meta_box_globals['selected_language']];
				// see if this is an original or a translation.
				if ($trans_data->original == '0') {
					// double check that it's not the original
					// This is because the source_language_code field in icl_translations is not always being set to null.
					
					$source_language_code = $wpdb->get_var("SELECT source_language_code FROM {$wpdb->prefix}icl_translations WHERE translation_id = $trans_data->translation_id");
					$translation = !($source_language_code == "" || $source_language_code == null);
					if ($translation) {
						$source_id = $icl_meta_box_globals['translations'][$source_language_code]->element_id;
						$translated_id = $trans_data->element_id;
					} else {
						$source_id = $trans_data->element_id;
					}
				} else {
					$source_id = $trans_data->element_id;
				}
			}
		}
		
		echo '<br /><br /><strong>' . __('Media attachments', 'wpml-media') . '</strong>';
		
		$checked = '';
		if ($translation) {
			if ($translated_id) {
				$duplicate = get_post_meta($translated_id, '_wpml_media_duplicate', true);
				if ($duplicate === "") {
					// check the original state
					$duplicate = get_post_meta($source_id, '_wpml_media_duplicate', true);
				}
				$featured = get_post_meta($translated_id, '_wpml_media_featured', true);
				if ($featured === "") {
					// check the original state
					$featured = get_post_meta($source_id, '_wpml_media_featured', true);
				}
				
			} else {
				// This is a new translation.
				$duplicate = get_post_meta($source_id, '_wpml_media_duplicate', true);
				$featured = get_post_meta($source_id, '_wpml_media_featured', true);
			}
			
			if ($duplicate) {
				$checked = ' checked="checked"';
			}
            echo '<br /><label><input name="icl_duplicate_attachments" type="checkbox" value="1" '.$checked . '/>&nbsp;' . __('Duplicate uploaded media from original', 'wpml-media') . '</label>'; 
			
			if ($featured) {
				$checked = ' checked="checked"';
			} else {
				$checked = '';
			}
            echo '<br /><label><input name="icl_duplicate_featured_image" type="checkbox" value="1" '.$checked . '/>&nbsp;' . __('Duplicate featured image from original', 'wpml-media') . '</label>'; 
		} else {

			$duplicate = get_post_meta($source_id, '_wpml_media_duplicate', true);
			if ($duplicate) {
				$checked = ' checked="checked"';
			}
            echo '<br /><label><input name="icl_duplicate_attachments" type="checkbox" value="1" '.$checked . '/>&nbsp;' . __('Duplicate uploaded media to translations', 'wpml-media') . '</label>'; 

			$featured = get_post_meta($source_id, '_wpml_media_featured', true);
			if ($featured) {
				$checked = ' checked="checked"';
			} else {
				$checked = '';
			}
            echo '<br /><label><input name="icl_duplicate_featured_image" type="checkbox" value="1" '.$checked . '/>&nbsp;' . __('Duplicate featured image to translations', 'wpml-media') . '</label>'; 
		}
	}
	
	function manage_media_columns($posts_columns) {
		$posts_columns['language'] = __('Language', 'wpml-media');
		return $posts_columns;
	}
	
	function manage_media_custom_column($column_name, $id) {
		if ($column_name == 'language') {
			global $wpdb, $sitepress; 
            if(!empty($this->languages[$id])){           
			    echo $sitepress->get_display_language_name($this->languages[$id], $sitepress->get_admin_language());
            }else{
                echo __('None', 'wpml-media');
            }
		}
	}
	
	//function manage_upload_sortable_columns($sortable_columns) {
	//	$sortable_columns['language'] = 'language';
	//	return $sortable_columns;
	//}
	
    function parse_query($q){
        global $wp_query, $wpdb, $pagenow, $sitepress;
        if($pagenow == 'upload.php' || $pagenow == 'media-upload.php' || (isset($_POST['action']) && $_POST['action']=='query-attachments')) {
			
			$this->_get_lang_info();			
			
		}
	}
	
    function _get_lang_info() {
	    global $wpdb, $sitepress;
		
	    // get the attachment languages.
        //if query-attachments need display all attachments
        if ((isset($_POST['action']) && $_POST['action'] == 'query-attachments')) {
            $results = $wpdb->get_results("SELECT ID, post_parent FROM {$wpdb->posts} WHERE post_type='attachment'");
        } else {
            //don't display attachments auto-draft posts
            $results = $wpdb->get_results("SELECT p.ID, p.post_parent FROM {$wpdb->posts} AS p LEFT JOIN {$wpdb->posts} AS p1 ON p.post_parent = p1.id WHERE p1.post_status <> 'auto-draft' AND p.post_type='attachment'");
        }
        $this->parents = array();
        $this->unattached = array();
        foreach ($results as $result) {            
            $this->parents[$result->ID] = $result->post_parent;
            if (!$result->post_parent) {
                $this->unattached[] = $result->ID;
            }
        }
        if ((isset($_POST['action']) && $_POST['action'] == 'query-attachments')) {
        //don't display attachments auto-draft posts 
        $results = $wpdb->get_results("SELECT post_id, meta_value FROM {$wpdb->postmeta} AS pm LEFT JOIN {$wpdb->posts} AS p ON pm.post_id = p.id LEFT JOIN {$wpdb->posts} AS p1 ON p.post_parent = p1.id WHERE p1.post_status <> 'auto-draft' AND pm.meta_key='wpml_media_lang'");
        } else {
            $results = $wpdb->get_results("SELECT post_id, meta_value FROM {$wpdb->postmeta} AS pm LEFT JOIN {$wpdb->posts} AS p ON pm.post_id = p.id WHERE  pm.meta_key='wpml_media_lang'");
        }
                
        
        
        $this->languages = array();
        foreach ($results as $result) {
            $this->languages[$result->post_id] = $result->meta_value;
        }
		
        // determine list of att without language set (with their parents)
        foreach($this->parents as $att_id => $parent_id) {
            if (!isset($this->languages[$att_id]) && isset($parent_langs[$parent_id]) ) {
                $missing_langs[$att_id] = $parent_id; 
            }
        }   
        // get language of their parents
        if(!empty($missing_langs)){     
            $results = $wpdb->get_results("
                SELECT p.ID, t.language_code 
                FROM {$wpdb->posts} p JOIN {$wpdb->prefix}icl_translations t ON p.ID = t.element_id AND t.element_type = CONCAT('post_', p.post_type)
                WHERE p.ID IN(".join(',', $missing_langs).")
            ");
            foreach($results as $row){
                $parent_langs[$row->ID] = $row->language_code;
            }
        }
        
        // set language of their parents
        if(isset($parent_langs))
        foreach($this->parents as $att_id => $parent_id) {
            if (!isset($this->languages[$att_id])) {
                $this->languages[$att_id] = $parent_langs[$parent_id];
            }
        }
		
	}
	
    /**
     *Add a filter to fix the links for attachments in the language switcher so
     *they point to the corresponding pages in different languages.
     */
    function filter_link($url, $lang_info) {
		global $wp_query, $sitepress;
		
		$current_lang = $sitepress->get_current_language();
		if ($wp_query->is_attachment && $lang_info['language_code'] != $current_lang) {
			$att_id = $wp_query->queried_object_id;
			// is this a duplicate of another attachment
			$translations = $this->_get_translations($att_id);
			
			if (isset($translations[$lang_info['language_code']])) {
				$att_id = $translations[$lang_info['language_code']];
			}
			$link = get_attachment_link($att_id);
			$link = str_replace('?lang='.$current_lang, '', $link);
			$link = str_replace('&lang='.$current_lang, '', $link);
			$link = str_replace('&amp;lang='.$current_lang, '', $link);
			$link = str_replace('/'.$current_lang.'/', '/', $link);
			$url = $sitepress->convert_url($link, $lang_info['language_code']);
			
		}
		
		return $url;
	}
	
	function _get_translations($att_id) {
		global $wpdb;
		
		if ($this->languages == null) {
			$this->_get_lang_info();
		}
		
		$duplicates = array();
		$duplicate_of = $wpdb->get_var("SELECT meta_value FROM {$wpdb->postmeta} WHERE post_id={$att_id} AND meta_key='wpml_media_duplicate_of'");
		if ($duplicate_of) {
			$duplicates = $wpdb->get_col("SELECT post_id FROM {$wpdb->postmeta} WHERE meta_value={$duplicate_of} AND meta_key='wpml_media_duplicate_of'");
			$duplicates[] = $duplicate_of;
			
		} else {
			// this might be an original
			$duplicates = $wpdb->get_col("SELECT post_id FROM {$wpdb->postmeta} WHERE meta_value={$att_id} AND meta_key='wpml_media_duplicate_of'");
			$duplicates[] = $att_id;
		}
		
		$translations = array();
		foreach ($duplicates as $duplicate) {
			if (isset($this->languages[$duplicate])) {
				$translations[$this->languages[$duplicate]] = $duplicate;
			}
		}
		
		return $translations;
	}

	function icl_ls_languages($w_active_languages) {
		static $doing_it = false;
		
		if(is_attachment() && !$doing_it){
			$doing_it = true;
			// Always include missing languages.
			$w_active_languages = icl_get_languages('skip_missing=0');
			$doing_it = false;
		}
		
		return $w_active_languages;
	}
		
    function posts_where_filter($where){
        global $wpdb, $pagenow, $sitepress;
		
        if($pagenow == 'upload.php' || $pagenow == 'media-upload.php' || isset($_POST['action']) && $_POST['action']=='query-attachments'){
            
            if(isset($_POST['action']) && $_POST['action']=='query-attachments'){
                
                $lang_code = $sitepress->get_language_for_element($_POST['post_id'], 'post_' . get_post_type($_POST['post_id']));
                
            }elseif ($pagenow == 'upload.php' || $pagenow == 'media-upload.php') {
                			    
			    if (isset($_GET['lang'])) {
				    $lang_code = $_GET['lang'];
			    } else {
				    if (method_exists($sitepress, 'get_admin_language_cookie')) {
					    $lang_code = $sitepress->get_admin_language_cookie();
				    }
			    }
            }
            
            if(empty($lang_code)){
                $lang_code = $sitepress->get_current_language();    
            }

            //if choose "display to this post" not need add "_posts.id in (ids)" in query where	
            if ($lang_code != "" && $lang_code != "all" && !isset($_POST['query']['post_parent'])) {
			    
                $att_ids = $wpdb->get_col($wpdb->prepare("SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key='wpml_media_lang' AND meta_value=%s", $lang_code));
			    
			    if (sizeof($att_ids) > 0) {
				    $att_ids = '(' . implode(',', $att_ids) . ')';
				    
				    $where .= " AND {$wpdb->posts}.ID in {$att_ids}";
			    } else {
				    // Add a where clause that wont return any matches.
				    $where .= " AND {$wpdb->posts}.ID = -1";
			    }
		    }
            
        }
        
		return $where;
	}	
	
	function get_post_metadata($value, $object_id, $meta_key, $single) {
		if ($meta_key == '_thumbnail_id') {
		
			global $wpdb;
			
			$thumbnail = $wpdb->get_var("SELECT meta_value FROM {$wpdb->postmeta} WHERE post_id = {$object_id} AND meta_key = '{$meta_key}'");
			
			if ($thumbnail == null) {
				// see if it's available in the original language.
				
				$post_type = $wpdb->get_var("SELECT post_type FROM {$wpdb->posts} WHERE ID = $object_id");
				$trid = $wpdb->get_row("SELECT trid, source_language_code FROM {$wpdb->prefix}icl_translations WHERE element_id={$object_id} AND element_type = 'post_$post_type'");
				if ($trid) {
				
					global $sitepress;
					
					$translations = $sitepress->get_element_translations($trid->trid, 'post_' . $post_type);
					if (isset($translations[$trid->source_language_code])) {
						$translation = $translations[$trid->source_language_code];
						// see if the original has a thumbnail.
						$thumbnail = $wpdb->get_var("SELECT meta_value FROM {$wpdb->postmeta} WHERE post_id = {$translation->element_id} AND meta_key = '{$meta_key}'");
						if ($thumbnail) {
							$value = $thumbnail;
						}
					}
				
				}				
				
			} else {
				$value = $thumbnail;
			}
			
		}
		return $value;
	}
	
    function menu(){
        $top_page = apply_filters('icl_menu_main_page', basename(ICL_PLUGIN_PATH).'/menu/languages.php');
		
        add_submenu_page($top_page,
							__('Media translation','wpml-media'), 
							__('Media translation','wpml-media'), 'manage_options',
							'wpml-media', array($this,'menu_content'));
    }
    
    function menu_content(){
        global $wpdb;
		
        /*
		$not_processed = $wpdb->get_var("
            SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'attachment' AND ID NOT IN 
			(SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = 'wpml_media_processed')");
        */
            
        $orphan_attachments = $wpdb->get_var("
            SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'attachment' AND ID NOT IN 
            (SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = 'wpml_media_lang')");
            
												
        include WPML_MEDIA_PATH . '/menu/management.php';
    }

	function create_duplicate_media($attachment) {
        global $wpdb, $sitepress;
        
		static $parents_processed = array();
		
		if ($attachment->post_parent && !in_array($attachment->post_parent, $parents_processed)) {			
			
			// see if we have translations.
			
			$post_type = $wpdb->get_var("SELECT post_type FROM {$wpdb->posts} WHERE ID = $attachment->post_parent");
            $trid = $wpdb->get_var("SELECT trid FROM {$wpdb->prefix}icl_translations WHERE element_id={$attachment->post_parent} AND element_type = 'post_$post_type'");
			if ($trid) {
				
				$attachments = $wpdb->get_col("SELECT ID FROM {$wpdb->posts} WHERE post_type = 'attachment' AND post_parent = $attachment->post_parent");

				$translations = $sitepress->get_element_translations($trid, 'post_' . $post_type);
				foreach ($translations as $translation) {
					if ($translation->element_id && $translation->element_id != $attachment->post_parent) {
						
						$attachments_in_translation = $wpdb->get_col("SELECT ID FROM {$wpdb->posts} WHERE post_type = 'attachment' AND post_parent = $translation->element_id");
						if (sizeof($attachments_in_translation) == 0) {
							// only duplicate attachments if there a none already.
							foreach ($attachments as $att_id) {
								// duplicate the attachement
								
								$this->create_duplicate_attachment($att_id, $translation->element_id, $translation->language_code);
								
							}
						}							
					}
				}
			}
			
			$parents_processed[] = $attachment->post_parent;
			
		}else{
            
            // no parent - set to default language
            update_post_meta($attachment->ID, 'wpml_media_lang', $sitepress->get_default_language());
            
        }
		update_post_meta($attachment->ID, 'wpml_media_processed', 1);
	}

	function create_duplicate_attachment($att_id, $parent_id, $lang) {
		$post = get_post($att_id);
		$post->post_parent = $parent_id;
		$post->ID = NULL;
		$dup_att_id = wp_insert_post($post);
		// duplicate the post meta data.
		$meta = get_post_meta($att_id, '_wp_attachment_metadata', true);
		add_post_meta($dup_att_id, '_wp_attachment_metadata', $meta);
		update_post_meta($dup_att_id, 'wpml_media_processed', 1);
		update_post_meta($dup_att_id, 'wpml_media_lang', $lang);
		update_post_meta($dup_att_id, 'wpml_media_duplicate_of', $att_id);
		$attached_file = get_post_meta($att_id, '_wp_attached_file', true);
		update_post_meta($dup_att_id, '_wp_attached_file', $attached_file);
        
        do_action('wpml_media_create_duplicate_attachment', $att_id, $dup_att_id);
        
        return $dup_att_id;
	}
	
    function js_scripts(){
		global $pagenow;
		if ($pagenow == 'media.php') {
			?>
			<script type="text/javascript">
				addLoadEvent(function(){                     
					jQuery('#icl_lang_options').insertBefore(jQuery('#post_id'));
					jQuery('#icl_lang_options').fadeIn();
				});
			</script>
			
			<?php
		}
		if (isset($_GET['page']) && $_GET['page'] == 'wpml-media') {
			?>
			<script type="text/javascript">
				addLoadEvent(function(){                     
                    jQuery('#wpml_media_options_form .wpml_media_options_language :checkbox').change(function(){
                        var set_language_required_missing = !jQuery('#wpml_media_options_form input[name=set_language_info]').attr('disabled') &&
                                 !jQuery('#wpml_media_options_form input[name=set_language_info]').attr('checked')
                        if(!jQuery('#wpml_media_options_form .wpml_media_options_language :checkbox:checked').length || set_language_required_missing){
                            jQuery('#wpml_media_options_form :submit').attr('disabled', 'disabled');            
                        }else{
                            jQuery('#wpml_media_options_form :submit').removeAttr('disabled');            
                        }
                    });
                    jQuery('#wpml_media_options_form').submit(function(){

                        if(!jQuery('#wpml_media_options_form :submit').attr('disabled')){                            
                            wpml_media_options_form_working()
                            wpml_media_options_form_scan_prepare();
                        }
                        return false;
                    })
                    
					
				});
                
                function wpml_media_options_form_working(){
                    jQuery('#wpml_media_options_form').find('.status').html('');             
                    jQuery('#wpml_media_options_form :submit').attr('disabled', 'disabled');    
                    jQuery('#wpml_media_options_form').find('.progress').fadeIn();
                }
                
                function wpml_media_options_form_finished(status){
                    jQuery('#wpml_media_options_form :submit').removeAttr('disabled');    
                    jQuery('#wpml_media_options_form').find('.progress').fadeOut();       
                    jQuery('#wpml_media_options_form').find('.status').html(status);             
                    window.setTimeout("jQuery('#wpml_media_options_form').find('.status').fadeOut();", 1000);
                    
                }
                
                function wpml_media_options_form_scan_prepare(){
                    
                    jQuery.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {action:'wpml_media_scan_prepare'},
                        dataType:'json',
                        success: function(ret){                            
                            jQuery('#wpml_media_options_form').find('.status').html(ret.message);             
                            if(jQuery('#wpml_media_options_form input[name=no_lang_attachments]').val() > 0){
                                // step 1
                                wpml_media_set_ititial_language();                                
                            }else{
                                // step 2
                                wpml_media_duplicate_media();
                            }
                        }
                        
                    });
                    
                }
                
                function wpml_media_set_ititial_language(){
                    
                    jQuery.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {action:'wpml_media_set_initial_language'},
                        dataType:'json',
                        success: function(ret){                            
                            jQuery('#wpml_media_options_form').find('.status').html(ret.message);             
                            if(ret.left > 0){                                
                                wpml_media_set_ititial_language();                                
                            }else{
                                // step 2
                                wpml_media_duplicate_media();
                            }
                        }
                        
                    });
                    
                }
                
                function wpml_media_duplicate_media(){

                    jQuery.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {action:'wpml_media_duplicate_media'},
                        dataType:'json',
                        success: function(ret){                            
                            jQuery('#wpml_media_options_form').find('.status').html(ret.message);                                         
                            if(ret.left > 0){                                
                                wpml_media_duplicate_media();
                            }else{
                                // step 3
                                wpml_media_duplicate_featured_images();
                            }                  
                        }
                        
                    });
                    
                }
                
                function wpml_media_duplicate_featured_images(){
                    
                    jQuery.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {action:'wpml_media_duplicate_featured_images'},
                        dataType:'json',
                        success: function(ret){
                            jQuery('#wpml_media_options_form').find('.status').html(ret.message);             
                            if(ret.left > 0){                                
                                wpml_media_duplicate_featured_images();
                            }else{
                                wpml_media_mark_processed();
                            }
                        }
                        
                    });
                    
                }
                
                function wpml_media_mark_processed(){
                    
                    jQuery.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {action:'wpml_media_mark_processed'},
                        dataType:'json',
                        success: function(ret){
                            wpml_media_options_form_finished(ret.message);                            
                            jQuery('#wpml_media_all_done').fadeIn();
                        }
                        
                    });
                    
                    
                }
			</script>
			<?php
		}
    }
    
    function _no_wpml_warning(){
        ?>
        <div class="message error"><p><?php printf(__('WPML Media is enabled but not effective. It requires <a href="%s">WPML</a> in order to work.', 'wpml-translation-management'), 
            'http://wpml.org/'); ?></p></div>
        <?php
    }
    
    function _old_wpml_warning(){
        ?>
        <div class="message error"><p><?php printf(__('WPML Media is enabled but not effective. It is not compatible with  <a href="%s">WPML</a> versions prior 2.0.5.', 'wpml-translation-management'), 
            'http://wpml.org/'); ?></p></div>
        <?php
    }    
    // Localization
    function plugin_localization(){
        load_plugin_textdomain( 'wpml-media', false, WPML_MEDIA_FOLDER . '/locale');
    }

    function language_filter(){
        global $sitepress;
	
		if (isset($_GET['lang'])) {
			$lang_code = $_GET['lang'];
		} else {
			if (method_exists($sitepress, 'get_admin_language_cookie')) {
				$lang_code = $sitepress->get_admin_language_cookie();
			}
		}

        $active_languages = $sitepress->get_active_languages();


        $active_languages[] = array('code'=>'all','display_name'=>__('All languages','sitepress'));
        foreach($active_languages as $lang){
            if($lang['code'] == $lang_code){
                $px = '<strong>';
                $sx = ' <span class="count">('. $lang['code'] .')<\/span><\/strong>';
            } else {
				$px = '<a href="' . $_SERVER['REQUEST_URI'] . '&lang=' . $lang['code']. '">';
                $sx = '<\/a> <span class="count">('. $lang['code'] .')<\/span>';
			}
            $as[] =  $px . $lang['display_name'] . $sx;
        }

        $allas = join(' | ', $as);
		
        $prot_link = '';
        ?>
        <script type="text/javascript">
            jQuery(".subsubsub").append('<br /><span id="icl_subsubsub"><?php echo $allas ?><\/span><br /><?php echo $prot_link ?>');
        </script>
        <?php
    }

    function views_upload($views) {
        global $sitepress, $wpdb,$pagenow;
        
        if ($pagenow == 'upload.php') {
            //get current language
            $lang = $sitepress->get_current_language();

            foreach ($views as $key => $view) {
                if ($lang != 'all') {
                    if ($key == 'all') {
                        //select all attachments
                        $sql = "SELECT COUNT(p.id) FROM {$wpdb->posts} AS p LEFT JOIN {$wpdb->postmeta} AS pm ON pm.post_id = p.id WHERE p.post_type = 'attachment' AND pm.meta_key='wpml_media_lang' AND pm.meta_value='" . $lang . "'";
                    } elseif ($key == 'detached') {
                        //select detached attachments
                        $sql .= " AND p.post_parent = 0 ";
                    } else {
                        //select mime type(image,etc) attachments
                        $sql .= " AND p.post_mime_type LIKE '" . $key . "%'";
                    }
                    $res = $wpdb->get_col($sql);
                    //replace count
                    $view = preg_replace('/\((\d+)\)/', '(' . $res[0] . ')', $view);
                }
                //replace href link
                if ($key == 'all') {
                    $views[$key] = preg_replace('/(href=["\'])([\s\S]+?)(["\'])/', '$1$2?lang=' . $lang . '$3', $view);
                } else {
                    $views[$key] = preg_replace('/(href=["\'])([\s\S]+?)(["\'])/', '$1$2&lang=' . $lang . '$3', $view);
                }
            }
        }
        return $views;
    }
    
    function language_filter_upload_page() {
        global $sitepress, $wpdb;

        //get language code
        if (isset($_GET['lang'])) {
            $lang_code = $_GET['lang'];
        } else {
            if (method_exists($sitepress, 'get_admin_language_cookie')) {
                $lang_code = $sitepress->get_admin_language_cookie();
            }
            if (empty($lang_code)) {
                $lang_code = $sitepress->get_default_language();
            }
        }

        $active_languages = $sitepress->get_active_languages();
        $active_languages[] = array('code' => 'all', 'display_name' => __('All languages', 'sitepress'));




        $sql = '';
        $langc['all'] = 0;
        foreach ($active_languages as $lang) {
            //select all attachments
            $sql = "SELECT COUNT(p.id) FROM {$wpdb->posts} AS p LEFT JOIN {$wpdb->postmeta} AS pm ON pm.post_id = p.id WHERE p.post_type = 'attachment' AND pm.meta_key='wpml_media_lang' AND pm.meta_value='" . $lang['code'] . "'";
            //select detached attachments
            if (isset($_GET['detached']))
                $sql .= " AND p.post_parent = 0 ";
            //select mime type(image,etc) attachments
            if (isset($_GET['post_mime_type']))
                $sql .= " AND p.post_mime_type LIKE '" . $_GET['post_mime_type'] . "%'";
            $res = $wpdb->get_col($sql);
            
            //count attachments
            if ($lang['code'] != 'all')
                $langc[$lang['code']] = $res[0];
            $langc['all'] += $res[0];
            
            //generation language block
            if ($lang['code'] == $lang_code) {
                $px = '<strong>';
                $sx = ' <span class="count">(' . $langc[$lang['code']] . ')<\/span><\/strong>';
            } else {
                if (isset($_GET['post_mime_type'])) {
                    $px = '<a href="?post_mime_type=' . $_GET['post_mime_type'] . '&lang=' . $lang['code'] . '">';
                } elseif (isset($_GET['detached'])) {
                    $px = '<a href="?detached=' . $_GET['detached'] . '&lang=' . $lang['code'] . '">';
                } else {
                    $px = '<a href="?lang=' . $lang['code'] . '">';
                }
                $sx = '<\/a> <span class="count">(' . $langc[$lang['code']] . ')<\/span>';
            }
            $as[] = $px . $lang['display_name'] . $sx;
        }

        $allas = join(' | ', $as);

        $prot_link = '';
        //append language block
        ?>
                        <script type="text/javascript">
                            jQuery(".subsubsub").append('<br /><span id="icl_subsubsub"><?php echo $allas ?><\/span><br /><?php echo $prot_link ?>');
                        </script>
        <?php
    }

    //check if the image is not duplicated to another post before deleting it physically 
    function delete_file($file) {
        global $wpdb;
        //get file name from full name
        $file_name = preg_replace('/^(.+)\-\d+x\d+(\.\w+)$/', '$1$2', $file);
        $file_name = preg_replace('/^[\s\S]+(\/.+)$/', '$1', $file_name);
        //check file name in DB
        $attachment = $wpdb->get_row("SELECT pm.meta_id FROM {$wpdb->postmeta} AS pm WHERE pm.meta_value LIKE '%" . $file_name . "'");
        //if exist return NULL(do not delete physically)
        if (!empty($attachment))
            $file = null;
        return $file;
    }



}

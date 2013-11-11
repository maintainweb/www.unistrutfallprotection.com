<?php

define('WPML_TT_TAXONOMIES_NOT_TRANSLATED', 1);
define('WPML_TT_TAXONOMIES_ALL', 0);
define('WPML_TT_TERMS_PER_PAGE', 20);

class WPML_Taxonomy_Translation{
    
    var $taxonomy;
    var $args;
    
    function __construct($taxonomy = '', $args = array()){
        global $wpdb, $sitepress, $sitepress_settings;
        
        if(empty($taxonomy)){
            
            global $wp_taxonomies;
            foreach($wp_taxonomies as $tax_key => $tax){
                if($sitepress->is_translated_taxonomy($tax_key)){
                    $this->taxonomy = $tax_key;
                    break;
                }                
            } 
            
        }else{
            
            $this->taxonomy = $taxonomy;
            
        }
                
        $this->args     = $args;
        
        $this->show_selector = isset($args['taxonomy_selector']) ? $args['taxonomy_selector'] : true;
        $this->show_tax_sync = isset($args['taxonomy_sync']) ? $args['taxonomy_sync'] : true;
        
        
        $this->taxonomy_obj = get_taxonomy($this->taxonomy);

        // filters
        $this->status = isset($this->args['status']) ? $this->args['status'] : WPML_TT_TAXONOMIES_NOT_TRANSLATED;     
        
        if(isset($this->args['languages']) && $this->args['languages']){
            foreach($sitepress->get_active_languages() as $language){
                if(in_array($language['code'], $args['languages'])){
                    $selected_languages[$language['code']] = $language;
                }
            }
        }
        $_active_languages = $sitepress->get_active_languages();
        unset($_active_languages[$sitepress->get_default_language()]);
        $this->selected_languages = !empty($selected_languages) ? $selected_languages : $_active_languages;
        
        if(defined('WPML_ST_FOLDER')){        
            // get labels translations
            //
            //
            if($sitepress->get_admin_language() != $sitepress_settings['st']['strings_language']){
                $singular_original = $wpdb->get_var($wpdb->prepare("SELECT s.value FROM {$wpdb->prefix}icl_strings s 
                    JOIN {$wpdb->prefix}icl_string_translations t ON t.string_id = s.id 
                    WHERE s.context='WordPress' and s.name LIKE %s AND t.language=%s", 'taxonomy singular name: %', $sitepress->get_admin_language()));
                $general_original = $wpdb->get_var($wpdb->prepare("SELECT s.value FROM {$wpdb->prefix}icl_strings s 
                    JOIN {$wpdb->prefix}icl_string_translations t ON t.string_id = s.id 
                    WHERE s.context='WordPress' and s.name LIKE %s AND t.language=%s", 'taxonomy general name: %', $sitepress->get_admin_language()));
            }else{
                $singular_original = $this->taxonomy_obj->labels->singular_name;            
                $general_original = $this->taxonomy_obj->labels->name;
            }

            $this->taxonomy_obj->labels_translations[$sitepress_settings['st']['strings_language']]['singular'] = $singular_original;
            $this->taxonomy_obj->labels_translations[$sitepress_settings['st']['strings_language']]['general'] = $general_original;
        
            $languages_pool = array_diff(array_merge(array_keys($this->selected_languages), array($sitepress->get_default_language())), array($sitepress_settings['st']['strings_language']));
            foreach($languages_pool as $language){
                    
                    $singular = $wpdb->get_var($wpdb->prepare("SELECT t.value FROM {$wpdb->prefix}icl_string_translations t 
                        JOIN {$wpdb->prefix}icl_strings s ON t.string_id = s.id 
                        WHERE s.context='WordPress' and s.name=%s AND t.language=%s", 'taxonomy singular name: ' . $singular_original, $language));
                    $general = $wpdb->get_var($wpdb->prepare("SELECT t.value FROM {$wpdb->prefix}icl_string_translations t 
                        JOIN {$wpdb->prefix}icl_strings s ON t.string_id = s.id 
                        WHERE s.context='WordPress' and s.name=%s AND t.language=%s", 'taxonomy general name: ' . $general_original, $language));
                    $this->taxonomy_obj->labels_translations[$language]['singular'] = $singular ? $singular : '';
                    $this->taxonomy_obj->labels_translations[$language]['general'] = $general ? $general : '';

            }
            
        }
                
        // build list of exclusion based on filters        
        foreach($this->selected_languages as $language){            
            $lcode_alias = str_replace('-', '', $language['code']);
            $joins[]    = " LEFT JOIN {$wpdb->prefix}icl_translations t{$lcode_alias} ON t{$lcode_alias}.trid = t.trid AND t{$lcode_alias}.language_code='{$language['code']}'";
            $selects[]  = "t{$lcode_alias}.element_id AS element_id_{$lcode_alias}";
        }
        $joins      = join(' ', $joins);
        $selects    = join(', ', $selects);
        if($this->status == WPML_TT_TAXONOMIES_NOT_TRANSLATED){
            $res = $wpdb->get_results($wpdb->prepare("
                
                SELECT t.element_id, {$selects}
                FROM {$wpdb->prefix}icl_translations t
                    {$joins}
                WHERE t.element_type = %s AND t.language_code = %s
                
                
            ", 'tax_' . $this->taxonomy, $sitepress->get_default_language()));
            
            foreach($res as $row){
                $translations = 0;
                foreach($row as $r){
                    if($r > 0 ) $translations++;
                }
                if($translations == count($this->selected_languages) + 1){
                    $excludes[] = $row->element_id;
                }
            }
            
            if(!empty($excludes)){                
                $get_terms_args['exclude'] = $wpdb->get_col($wpdb->prepare("SELECT term_id FROM {$wpdb->term_taxonomy} WHERE taxonomy=%s AND term_taxonomy_id IN (" .  join(',', $excludes) . ")", $this->taxonomy));
            }
            
        }
                

        // get_terms args        
        $get_terms_args['hide_empty'] = false;
        $get_terms_args['orderby'] = 'name';
        if(!empty($this->args['search'])){
            $get_terms_args['search'] = $this->args['search'];
            $this->search = $args['search'];
        }
        
        // getting the total number
        $_terms = get_terms($this->taxonomy, $get_terms_args);
        $this->terms_count = count($_terms);
        unset($_terms);
        
        $this->current_page = isset($this->args['page']) ? $this->args['page'] : 1;
        if(isset($this->args['page'])){
            $get_terms_args['offset'] = ($args['page'] - 1) * WPML_TT_TERMS_PER_PAGE;
        }
        
        $get_terms_args['number'] = WPML_TT_TERMS_PER_PAGE;
        
        // get the terms to display
        
        $this->terms = get_terms($this->taxonomy, $get_terms_args);
        
        
        if(is_wp_error($this->terms)){
            $this->error = sprintf(__('Unknown taxonomy: %s'), $this->taxonomy);
            return false;            
        } 
        
        if(empty($this->terms) || is_wp_error($this->terms)) return;
        
        // limit for pagination?

        // get term taxonomy ids
        foreach($this->terms as $term){
            $tt_ids[] = $term->term_taxonomy_id;
        }

        // get list of matching trids
        $trids = $wpdb->get_col($wpdb->prepare("SELECT trid FROM {$wpdb->prefix}icl_translations WHERE element_type = %s AND element_id IN (" . join(',', $tt_ids) . ")", 'tax_' . $this->taxonomy));

        // get terms by trids
        $res = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$wpdb->prefix}icl_translations WHERE element_type = %s AND trid IN (" . join(',', $trids) . ")", 'tax_' . $this->taxonomy));
        foreach($res as $row){
            $terms_by_trid[$row->trid][$row->language_code] = $row->element_id;
        }

        // get translations for each term
        foreach($this->terms as $k => $term){
            foreach($terms_by_trid as $trid_group){
                if($trid_group[$sitepress->get_default_language()] == $term->term_taxonomy_id){
                    foreach($trid_group as $language => $element){
                        if($language != $sitepress->get_default_language()){
                            $term_id = $wpdb->get_var($wpdb->prepare("SELECT term_id FROM {$wpdb->term_taxonomy} WHERE term_taxonomy_id=%d AND taxonomy = %s", $element, $this->taxonomy));
                            $this->terms[$k]->translations[$language] = get_term($term_id, $this->taxonomy);              
                        }
                    }
                }
            }
            
        }        
        
        
    }
    
    function render(){
        global $sitepress, $sitepress_settings;

        if(!empty($this->error)){
            
            echo '<div class="icl_error_text">' . $this->error . '</div>';            
            
        }        
        elseif(!$this->taxonomy_obj){
            
            echo '<div class="icl_error_text">' . sprintf(__('Unknown taxonomy: %s', 'sitepress'), $this->taxonomy ) . '</div>';            
            
        }else{
            
            $active_languages = $sitepress->get_active_languages();
            
            include ICL_PLUGIN_PATH . '/menu/taxonomy-translation-content.php';
            
        }
        
        
    }
    
    function show_terms(){
        $taxonomy = isset($_POST['taxonomy']) ? $_POST['taxonomy'] : false;        
        
        $args = array();
        if(!empty($_POST['language'])){
            $args['languages'] = array($_POST['language']);
        }
        $args['status'] = isset($_POST['status']) ? $_POST['status'] : WPML_TT_TAXONOMIES_ALL;
        
        $args['search'] = isset($_POST['search']) ? $_POST['search'] : '';
        
        if(isset($_POST['page'])){
            $args['page'] = $_POST['page'];
        }
        
        $inst = new WPML_Taxonomy_Translation($taxonomy, $args);
        
        ob_start();
        $inst->render();
        $html = ob_get_contents();
        ob_end_clean();
        
        echo json_encode(array('html' => $html));
        exit;
        
        
    }
    
    public static function save_term_translation(){
        global $sitepress, $wpdb;
        
        $original_element   = $_POST['translation_of'];
        $taxonomy           = $_POST['taxonomy'];
        $language           = $_POST['language'];
        $trid = $sitepress->get_element_trid($original_element, 'tax_' . $taxonomy);
        $translations = $sitepress->get_element_translations($trid, 'tax_' . $taxonomy);

        $_POST['icl_tax_' . $taxonomy . '_language'] = $language;
        $_POST['icl_trid'] = $trid;
        $_POST['icl_translation_of'] = $original_element;
        
        $errors = '';
        
        $term_args = array(
              'name'        => $_POST['name'],
              'slug'        => $_POST['slug'],
              'description' => $_POST['description']
        ); 
        
        // hierarchy - parents
        if(is_taxonomy_hierarchical($_POST['taxonomy'])){
            // fix hierarchy
            $original_tax = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->term_taxonomy} WHERE taxonomy=%s AND term_taxonomy_id = %d", $_POST['taxonomy'], $original_element));
            if($original_tax->parent){
                $original_parent_translated = icl_object_id($original_tax->parent, $_POST['taxonomy'], false, $_POST['language']);
                if($original_parent_translated){
                    $term_args['parent'] = $original_parent_translated;    
                }
            }
            
        }
                
        if(isset($translations[$language])){
            
            $result = wp_update_term($translations[$language]->term_id, $taxonomy, $term_args);           
            
        }else{
            
            $result = wp_insert_term($_POST['name'], $taxonomy, $term_args);
            
        }
        
        if(is_wp_error($result)){
            foreach($result->errors as $ers){
                $errors .= join('<br />', $ers);
            }
            $errors .= '<br />'   ;
        }else{
            
            // hiearchy - children            
            if(is_taxonomy_hierarchical($_POST['taxonomy'])){
                
                // get children of original
                $children = $wpdb->get_col($wpdb->prepare("SELECT term_id FROM {$wpdb->term_taxonomy} WHERE taxonomy=%s AND parent=%d", $_POST['taxonomy'], $original_element));
                
                if($children) foreach($children as $child){
                    $child_translated = icl_object_id($child, $_POST['taxonomy'], false, $_POST['language']);
                    if($child_translated){
                        $wpdb->update($wpdb->term_taxonomy, array('parent' => $result['term_id']), array('taxonomy' => $_POST['taxonomy'], 'term_id' => $child_translated));
                    }    
                }
                
                delete_option($_POST['taxonomy'] . '_children');
                
            }
            
            
            $term = get_term($result['term_id'], $_POST['taxonomy']);
                        
        }
        
        $html = '';
                
        echo json_encode(array('html' => $html, 'slug' => isset($term) ? urldecode($term->slug) : '',  'errors' => $errors));
        exit;
        
    }
    
    public static function save_labels_translation(){
        
        $errors = '';
        
        if(empty($_POST['singular']) || empty($_POST['general'])){
            $errors .= __('Please fill in all fields!', 'sitepress') . '<br />';
        }
        
        
        $string_id = icl_register_string('WordPress', 'taxonomy singular name: ' . $_POST['singular_original'], $_POST['singular_original']);
        icl_add_string_translation($string_id, $_POST['language'], $_POST['singular'], ICL_STRING_TRANSLATION_COMPLETE);
        
        $string_id = icl_register_string('WordPress', 'taxonomy general name: ' . $_POST['general_original'], $_POST['general_original']);
        icl_add_string_translation($string_id, $_POST['language'], $_POST['general'], ICL_STRING_TRANSLATION_COMPLETE);
        
        $html = '';
                
        echo json_encode(array('html' => $html, 'errors' => $errors));
        exit;
        
        
    }
    
    public static function sync_taxonomies_in_content_preview(){
        global $wp_taxonomies;
        
        $html = $message = $errors = '';
        
        
        if(isset($wp_taxonomies[$_POST['taxonomy']])){
            $object_types = $wp_taxonomies[$_POST['taxonomy']]->object_type;    
            
            foreach($object_types as $object_type){
                                
                $html .= self::render_assignment_status($object_type, $_POST['taxonomy'], $preview = true);
                
            }
            
        }else{
            $errors = sprintf(__('Invalid taxonomy %s', 'sitepress'), $_POST['taxonomy']);
        }
        
        
        echo json_encode(array('html' => $html, 'message'=> $message, 'errors' => $errors));
        exit;
        
        
    }
    
    public static function sync_taxonomies_in_content(){
        global $wp_taxonomies;
        
        $html = $message = $errors = '';
        
        if(isset($wp_taxonomies[$_POST['taxonomy']])){
            $html .= self::render_assignment_status($_POST['post'], $_POST['taxonomy'], $preview = false);
            
        }else{
            $errors .= sprintf(__('Invalid taxonomy %s', 'sitepress'), $_POST['taxonomy']);
        }
        
        
        echo json_encode(array('html' => $html, 'errors' => $errors));
        exit;
        
        
    }
    
    
    public static function render_assignment_status($object_type, $taxonomy, $preview = true){
        global $sitepress, $wp_post_types, $wp_taxonomies;
        
        $posts = get_posts(array('post_type' => $object_type, 'suppress_filters' => false));
        
        foreach($posts as $post){
            
            $terms = wp_get_post_terms($post->ID, $taxonomy);
            
            $term_ids = array();
            foreach($terms as $term){
                $term_ids[] = $term->term_id;
            }
            
            
            $trid = $sitepress->get_element_trid($post->ID, 'post_' . $post->post_type);
            $translations = $sitepress->get_element_translations($trid, 'post_' . $post->post_type, true, true);
            
            foreach($translations as $language => $translation){
                
                if($language != $sitepress->get_default_language() && $translation->element_id){
                    
                    $terms_of_translation =  wp_get_post_terms($translation->element_id, $taxonomy);
                    
                    $translation_term_ids = array();
                    foreach($terms_of_translation as $term){
                        
                        $term_id_original = icl_object_id($term->term_id, $taxonomy, false, $sitepress->get_default_language());
                        if(!$term_id_original || !in_array($term_id_original, $term_ids)){
                            // remove term
                            
                            if($preview){
                                $needs_sync = true;  
                                break(3);  
                            }
                            
                            $current_terms = wp_get_post_terms($translation->element_id, $taxonomy);
                            $updated_terms = array();
                            foreach($current_terms as $cterm){
                                if($cterm->term_id != $term->term_id){
                                    $updated_terms[] = is_taxonomy_hierarchical($taxonomy) ? $term->term_id : $term->term_name;        
                                } 
                                if(!$preview){
                                    wp_set_post_terms($translation->element_id, $updated_terms, $taxonomy);                                                                 
                                }  
                                
                            }
                            
                            
                        }else{
                            $translation_term_ids[] = $term_id_original;
                        }                                                
                        
                    }
                    
                    foreach($term_ids as $term_id){
                        
                        if(!in_array($term_id, $translation_term_ids)){
                            // add term
                            
                            if($preview){
                                $needs_sync = true;    
                                break(3);  
                            }
                            
                            $term_id_translated = icl_object_id($term_id, $taxonomy, false, $language);
                            if(!is_taxonomy_hierarchical($taxonomy)){
                                $term_details = get_term($term_id_translated, $taxonomy);
                                $term_id_translated = $term->name;
                            }
                            if(!$preview){
                                wp_set_post_terms($translation->element_id, $term_id_translated, $taxonomy, true);
                            }
                            
                        }
                        
                    }
                    
                }
                
                
            }
            
            
        }
        
        $out = '';
        
                
        if($preview){
                
            $out .= '<div class="icl_tt_sync_row">';            
            if(!empty($needs_sync)){
                $out .= '<form class="icl_tt_do_sync">';
                $out .= '<input type="hidden" name="post" value="' . $object_type . '" />';
                $out .= '<input type="hidden" name="taxonomy" value="' . $taxonomy . '" />';
                $out .= sprintf(__('Some translated %s have different %s assignments.', 'sitepress'), 
                    '<strong>' . strtolower($wp_post_types[$object_type]->labels->name) . '</strong>',
                    '<strong>' . strtolower($wp_taxonomies[$taxonomy]->labels->name) . '</strong>');
                $out .= '&nbsp;<a class="submit button-secondary" href="#">' . sprintf(__('Update %s for all translated %s', 'sitepress'), 
                    '<strong>' . strtolower($wp_taxonomies[$taxonomy]->labels->name) . '</strong>',
                    '<strong>' . strtolower($wp_post_types[$object_type]->labels->name) . '</strong>') . '</a>' . 
                    '&nbsp;<img src="'. ICL_PLUGIN_URL . '/res/img/ajax-loader.gif" alt="loading" height="16" width="16" class="wpml_tt_spinner" />';
                $out .= "</form>";
            }else{
                $out .= sprintf(__('All %s have the same %s assignments.', 'sitepress'), 
                    '<strong>' . strtolower($wp_taxonomies[$taxonomy]->labels->name) . '</strong>', 
                    '<strong>' . strtolower($wp_post_types[$object_type]->labels->name) . '</strong>');
            }
            $out .= "</div>";
            
        }else{
            
            $out .= sprintf(__('Successfully updated %s for all translated %s.', 'sitepress'), $wp_taxonomies[$taxonomy]->labels->name, $wp_post_types[$object_type]->labels->name);
            
        }
        
        return $out;
        
    }
    
    
    
}  


add_action('wp_ajax_wpml_tt_show_terms', array('WPML_Taxonomy_Translation', 'show_terms'));

add_action('wp_ajax_wpml_tt_save_term_translation', array('WPML_Taxonomy_Translation', 'save_term_translation'));
add_action('wp_ajax_wpml_tt_save_labels_translation', array('WPML_Taxonomy_Translation', 'save_labels_translation'));

add_action('wp_ajax_wpml_tt_sync_taxonomies_in_content_preview', array('WPML_Taxonomy_Translation', 'sync_taxonomies_in_content_preview'));
add_action('wp_ajax_wpml_tt_sync_taxonomies_in_content', array('WPML_Taxonomy_Translation', 'sync_taxonomies_in_content'));


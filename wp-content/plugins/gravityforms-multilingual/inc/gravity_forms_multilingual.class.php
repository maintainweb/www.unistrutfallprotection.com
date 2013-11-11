<?php


class Gravity_forms_multilingual{


    function __construct($ext = false){
        add_action('init', array($this,'init'));
    }

    function __destruct(){
        return;
    }

    function init(){
        add_filter('WPML_get_translatable_types', array($this,'get_translatable_types'));
        add_filter('WPML_get_translatable_items', array($this,'get_translatable_items'), 10, 3);
        add_filter('WPML_get_translatable_item', array($this,'get_translatable_item'), 10, 2);
        add_filter('WPML_get_link', array($this,'get_link'), 10, 4);

        add_filter('gform_pre_render', array($this, 'gform_pre_render'));
		add_filter('gform_pre_submission_filter', array($this, 'gform_pre_render'));
        add_filter('gform_confirmation', array($this, 'gform_confirmation'), 10, 4);

    }


    function get_translatable_types($types) {
        // Tell WPML that we want gravity forms translated
        $types['gravity_form'] = 'Gravity form';
        return $types;
    }

	function _get_form_strings($form_id) {
		
		$form = RGFormsModel::get_form_meta($form_id, true);
		$form = RGFormsModel::add_default_properties($form);
		
		$string_data = array();

		$form_keys = array('title', 'description', 'limitEntriesMessage',
						   'scheduleMessage',
						   'postTitleTemplate',
						   'postContentTemplate',
						   'confirmation-message',
						   'autoResponder-subject',
						   'autoResponder-message',
						   'button-text'
						   );
						   
		foreach ($form_keys as $key) {
			$parts = explode('-', $key);
			if (sizeof($parts) == 1) {
				if (isset($form[$key]) && $form[$key] != '') {
					$string_data[$key] = $form[$key];
				}
			} else {
				if (isset($form[$parts[0]][$parts[1]]) && $form[$parts[0]][$parts[1]] != '') {
					$string_data[$key] = $form[$parts[0]][$parts[1]];
				}
			}
		}
		
		
		///- Paging Page Names           - $form["pagination"]["pages"][i]

		$keys = array('label', 'description', 'defaultValue', 'errorMessage');
		
		foreach ($form['fields'] as $id => $field) {
			
			foreach ($keys as $key) {
				if (isset($field[$key]) && $field[$key] != '') {
					$string_data['field-' . $field['id'] . '-' . $key] = $field[$key];
				}
			}
				
			switch ($field['type']) {
				case 'text':
				case 'textarea':
				case 'email':
				case 'number':
				case 'section':
					break;

				case 'html':
					$string_data['field-' . $field['id'] . '-content'] = $field['content'];
					break;

				case 'page':
					$string_data['field-' . $field['id'] . '-nextButton'] = $field['nextButton']['text'];
					$string_data['field-' . $field['id'] . '-previousButton'] = $field['previousButton']['text'];
					break;

				case 'select':
				case 'checkbox':
				case 'radio':
				case 'product':
                    if(isset($field['choices']) && is_array($field['choices']))
					foreach ($field['choices'] as $index => $choice) {
						$string_data['field-' . $field['id'] . '-choice-' . $choice['value']] = $choice['text'];
					}
					break;
				
				case 'post_custom_field':
					$string_data['field-' . $field['id'] . '-customFieldTemplate'] = $field["customFieldTemplate"];
					break;
					
			}
			
		}
		
		return $string_data;
		
	}

	function gform_pre_render($form) {

        if (function_exists('icl_t')) {

			$form_keys = array('title', 'description', 'limitEntriesMessage',
							   'scheduleMessage',
							   'postTitleTemplate',
							   'postContentTemplate',
							   'confirmation-message',
							   'autoResponder-subject',
							   'autoResponder-message',
							   'button-text'
							   );
							   
			foreach ($form_keys as $key) {
				$parts = explode('-', $key);
				if (sizeof($parts) == 1) {
					if (isset($form[$key]) && $form[$key] != '') {
			            $form[$key] = icl_t('gravity_form', $form['id'] . '_' . $key, $form[$key]);
					}
				} else {
					if (isset($form[$parts[0]][$parts[1]]) && $form[$parts[0]][$parts[1]] != '') {
			            $form[$parts[0]][$parts[1]] = icl_t('gravity_form', $form['id'] . '_' . $key, $form[$parts[0]][$parts[1]]);
					}
				}
			}
			

			$keys = array('label', 'description', 'defaultValue', 'errorMessage');

			foreach ($form['fields'] as $id => $field) {

				foreach ($keys as $key) {
					if (isset($field[$key]) && $field[$key] != '') {
						$form['fields'][$id][$key] = icl_t('gravity_form', $form['id'] . '_field-' . $field['id'] . '-' . $key, $field[$key]);
					}
				}
				
                
                
				switch ($field['type']) {
					case 'text':
					case 'textarea':
					case 'email':
					case 'number':
					case 'section':
						break;
						
					case 'html':
						$form['fields'][$id]['content'] = icl_t('gravity_form', $form['id'] . '_field-' . $field['id'] . '-content', $field['content']);
						break;
						
					case 'page':
						$form['fields'][$id]['nextButton']['text'] = icl_t('gravity_form', $form['id'] . '_field-' . $field['id'] . '-nextButton', $field['nextButton']['text']);
						$form['fields'][$id]['previousButton']['text'] = icl_t('gravity_form', $form['id'] . '_field-' . $field['id'] . '-previousButton', $field['previousButton']['text']);
						break;
						
					case 'select':
					case 'checkbox':
					case 'radio':
					case 'product':
						foreach ($field['choices'] as $index => $choice) {
							$string_name = substr($form['id'] . '_field-' . $field['id'] . '-choice-' . $choice['value'], 0, 160); // limit to 160 chars
							$form['fields'][$id]['choices'][$index]['text'] = icl_t('gravity_form', $string_name, $choice['text']);
						}
						break;
						
				}
				
			}
		}
		
		return $form;
	}
	
	function gform_confirmation($confirmation, $form, $lead, $ajax){
		
        if (function_exists('icl_t')) {
			if (isset($form['confirmation']['message']) && $form['confirmation']['message'] != '') {
				if (is_string($confirmation)) {
					$original_message = $form['confirmation']['message'];
					$translation = icl_t('gravity_form', $form['id'] . '_confirmation-message', $original_message);
					
					if ($translation != $original_message) {
						$confirmation = str_replace($original_message, $translation, $confirmation);
					}
				}
			} else if(!empty($form["confirmation"]["pageId"])){
				$url = get_permalink(icl_object_id($form["confirmation"]["pageId"],'page',true));
				$confirmation = array("redirect" => $url);
			}
		}
		return $confirmation;
	}
	
    function get_translatable_items($items, $type, $filter) {

        if (function_exists('icl_st_is_registered_string')) {
            // Only return items if string translation is available.

            global $sitepress, $wpdb;

            if ($type == 'gravity_form') {

                $default_lang = $sitepress->get_default_language();
                $languages = $sitepress->get_active_languages();

                global $wpdb;
                $g_forms = $wpdb->get_results($wpdb->prepare("
                    SELECT *
                    FROM {$wpdb->prefix}rg_form
                "));
                foreach($g_forms as $k=>$v){
                    $new_item = new stdClass();

                    $new_item->external_type = true;
                    $new_item->type = 'gravity_form';
                    $new_item->id = $v->id;
                    $new_item->post_type = 'gravity_form';
                    $new_item->post_id = 'external_' . $new_item->post_type . '_' . $v->id;
                    $new_item->post_date = $v->date_created;
                    $new_item->post_status = $v->is_active ? __('Active', 'gravity-forms-ml') : __('Inactive', 'gravity-forms-ml');
                    $new_item->post_title = $v->title;
					$new_item->is_translation = false;
                    
					$new_item->string_data = $this->_get_form_strings($v->id);

                    // add to the translation table if required
                    $post_trid = $sitepress->get_element_trid($new_item->id, 'post_' . $new_item->post_type);
                    if (!$post_trid) {
                        $sitepress->set_element_language_details($new_item->id, 'post_' . $new_item->post_type, false, $default_lang, null, false);
                    }

                    // register the strings with WPML

                    if (function_exists('icl_st_is_registered_string')) {
                        foreach ($new_item->string_data as $key => $value) {
                            if (!icl_st_is_registered_string('gravity_form', $new_item->id . '_' . $key)) {
                                icl_register_string('gravity_form', $new_item->id . '_' . $key, $value);
                            }
                        }
                    }

                    $post_trid = $sitepress->get_element_trid($new_item->id, 'post_' . $new_item->post_type);
                    $post_translations = $sitepress->get_element_translations($post_trid, 'post_' . $new_item->post_type);

                    global $iclTranslationManagement;

                    $md5 = $iclTranslationManagement->post_md5($new_item);

                    foreach ($post_translations as $lang => $translation) {
                        $res = $wpdb->get_row("SELECT status, needs_update, md5 FROM {$wpdb->prefix}icl_translation_status WHERE translation_id={$translation->translation_id}");
                        if ($res) {
                            if (!$res->needs_update) {
                                // see if the md5 has changed.
                                if ($md5 != $res->md5) {
                                    $res->needs_update = 1;
                                    $wpdb->update($wpdb->prefix.'icl_translation_status', array('needs_update'=>1), array('translation_id'=>$translation->translation_id));
                                }
                            }
                            $_suffix = str_replace('-','_',$lang);
                            $index = 'status_' . $_suffix;
                            $new_item->$index = $res->status;
                            $index = 'needs_update_' . $_suffix;
                            $new_item->$index = $res->needs_update;
                        }
                    }

                    $items[] = $new_item;

                }

            }
        }
        return $items;
    }

    function get_translatable_item($item, $id) {
        if ($item == null) {
            $parts = explode('_', $id);
            if ($parts[0] == 'external') {
                $id = array_pop($parts);

                unset($parts[0]);

                $type = implode('_', $parts);

                if ($type == 'gravity_form') {
                    // this is ours.

                    global $wpdb;
					$g_form = $wpdb->get_row($wpdb->prepare("
						SELECT *
						FROM {$wpdb->prefix}rg_form
                        WHERE id = %d
                        ", (int)$id));

                    $item = new stdClass();

                    $item->external_type = true;
                    $item->type = 'gravity_form';
                    $item->id = $g_form->id;
                    $item->ID = $g_form->id;
                    $item->post_type = 'gravity_form';
                    $item->post_id = 'external_' . $item->post_type . '_' . $item->id;
                    $item->post_date = $g_form->modified;
                    $item->post_status = $g_form->is_active ? __('Active', 'gravity-forms-ml') : __('Inactive', 'gravity-forms-ml');
                    $item->post_title = $g_form->title;
					$item->is_translation = false;

					$item->string_data = $this->_get_form_strings($item->id);

                }
            }
        }

        return $item;

    }
	
    function get_link($item, $id, $anchor, $hide_empty) {
        if ($item == "") {
            $parts = explode('_', $id);
            if ($parts[0] == 'external') {
                $id = array_pop($parts);

                unset($parts[0]);

                $type = implode('_', $parts);

                if ($type == 'gravity_form') {
                    // this is ours.

					if (false === $anchor) {
						global $wpdb;
						$g_form = $wpdb->get_row($wpdb->prepare("
							SELECT *
							FROM {$wpdb->prefix}rg_form
							WHERE id = %d
							", (int)$id));
						$anchor = $g_form->title;
					}

                    $item = sprintf('<a href="%s">%s</a>', 'admin.php?page=gf_edit_forms&id=' . $id, $anchor);
                }
            }
        }
        return $item;
    }
	

}

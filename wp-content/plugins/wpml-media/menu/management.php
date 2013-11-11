
<script type="text/javascript">
var wpml_media_ajxloaderimg_src = '<?php echo WPML_MEDIA_URL ?>/res/img/ajax-loader.gif';
var wpml_media_ajxloaderimg = '<img src="'+wpml_media_ajxloaderimg_src+'" alt="loading" width="16" height="16" />';
</script>

<div class="wrap">

    <div id="icon-wpml" class="icon32"><br /></div>
    <h2><?php echo __('Media translation', 'wpml-media') ?></h2>    

    <?php if($orphan_attachments): ?>
    
    <p><?php _e("The Media Translation plugin needs to add languages to your site's media. Without this language information, existing media files will not be displayed in the WordPress admin.", 'wpml-media') ?></p>
    
    <?php else: ?>
    
    <p><?php _e('You can check if some attachments can be duplicated to translated content:', 'wpml-media') ?></p>
    
    <?php endif ?>
    
    <form id="wpml_media_options_form">
    <input type="hidden" name="no_lang_attachments" value="<?php echo $orphan_attachments ?>" />
    <table>
    
    <tr>
        <td colspan="2">                        
            <ul class="wpml_media_options_language">        
                <li><label><input type="checkbox" name="set_language_info" value="1" <?php if(!empty($orphan_attachments)): ?>checked="checked"<?php endif; ?> <?php if(empty($orphan_attachments)): ?>disabled="disabled"<?php endif ?> />&nbsp;<?php _e('Set language information for existing media', 'wpml-media') ?></label></li>
                <li><label><input type="checkbox" name="duplicate_media" value="1" checked="checked" />&nbsp;<?php _e('Duplicate existing media for translated content', 'wpml-media') ?></label></li>
                <li><label><input type="checkbox" name="duplicate_featured" value="1" checked="checked" />&nbsp;<?php _e('Duplicate the featured images for translated content', 'wpml-media') ?></label></li>        
            </ul>
        </td>
    
    </tr>
    
    <tr>
        <td><a href="http://wpml.org/documentation/getting-started-guide/media-translation/"><?php _e('Media Translation Documentation') ?></a></td>
        <td align="right">            
                <input class="button-primary" type="submit" value="<?php esc_attr_e('Start'); ?> &raquo;" />                
        </td>
    
    </tr>
    
    <tr>
        <td colspan="2">
            <img class="progress" src="<?php echo WPML_MEDIA_URL ?>/res/img/ajax-loader.gif" width="16" height="16" alt="loading" style="display: none;" />
                &nbsp;<span class="status"></span>            
        </td>
    </tr>
    
    </table>
    
    <p id="wpml_media_all_done" class="hidden icl_cyan_box" style=""><?php _e("You're all done. Now that the Media Translation plugin is running, all new media files that you upload to content will receive a language. You can automatically duplicate them to translations from the post-edit screen.") ?></p>
    
    </form>
    
    
    
    
</div>
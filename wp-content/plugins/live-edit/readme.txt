=== Live Edit ===
Contributors: Elliot Condon
Tags: live, edit, front, end, mange, title, content, acf, advanced, custom, fields, editor
Requires at least: 3.0
Tested up to: 3.4.2
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Edit the title, content and any ACF fields from the front end of your website!

== Description ==

The Live Edit plugin provides a "slide out" panel to edit the title / content and any Advanced Custom Fields on any template file!
Instead of automatically adding an "edit" button to the page or post, this plugin allow you to specify a "region" (div element) which can be edited. This means you can have multiple "edit regions" within the one template file.

A good example of this is to imagine an archive page where multiple posts are shown. This plugin would allow you to quickly edit any visible post's fields!

= Demo =
[vimeo https://vimeo.com/50590785]

= Instructions =
To create an "editable region", use the live_edit() function. This function is placed inside the tag where attributes can be created. eg:
`<div <?php live_edit('field_name1, field_name2, etc'); ?>>
	<p>An edit buton will appear in this div! All HTML inside the div will update refresh after updated!</p>
	<p>Field name 1: <?php the_field('field_name1'); ?></p>
</div>`

= live_edit() =
This function generates the atributes neccessary for the "edit panel" to work. It accepts 2 parameters:

**$fields** - An array or string (comma seperated list) of field names to show in the edit panel. To edit the post title use "post_title", to edit the post content use "post_content", to edit the post excerpt use "post_excerpt", for all ACF fields use "$field_name" - (required)

**$post_id** - A number refering to the post / page to save values against. This can be any $post_id value used in ACF. This includes: "option", "user_$userID", '$taxonomy_$term', 123. Defaults to current $post->ID - (optional)

= if(function_exists("live_edit")) =
It is always good practise to wrap the live_edit() function in a conditional statement. This allows the plugin to be deativated without crashing your website. eg:
`<div class="somthing" <?php if(function_exists("live_edit")){ live_edit('post_title, repeater_field'); }?>>
	<p>..</p>
</div>`

= Tested on =
* Mac Firefox 	:)
* Mac Safari 	:)
* Mac Chrome	:)
* PC Firefox	Not yet
* PC ie7		Not yet

= Please Vote and Enjoy =
Your votes really make a difference! Thanks.


== Installation ==

1. Upload 'live-edit' to the '/wp-content/plugins/' directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Edit your theme templates and create "editable regions" with the live_edit() function


== Frequently Asked Questions ==



== Screenshots ==
1. The Live Edit Panel


== Changelog ==

= 2.0.0 =
* [Updated] Updated all code to now be compatible with ACF v4 and above. If using v3.5.8.2 or below, please do not use this version, instead continue to use v1.0.4

= 1.0.4 =
* Fixed issue where post_content, post_title and post_excerpt were not saving
* Fixed JS bug where the div would not AJAX update after the seccond save

= 1.0.3 =
* Fixed compatibility issues with new ACF > 3.5.5

= 1.0.2 =
* Fixed bug where fields were not showing with ACF > 3.5.0

= 1.0.1 =
* Original Commit

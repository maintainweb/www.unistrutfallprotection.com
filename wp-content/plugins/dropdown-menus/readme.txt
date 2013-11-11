=== Dropdown Menus ===
Contributors: sanchothefat
Tags: menus, dropdowns, mobile, ui, navigation, nav-menus
Requires at least: 3.0
Tested up to: 3.5
Stable tag: 1.0

Display your WordPress menus as a dropdown select box. Great for mobile designs.

== Description ==

Sometimes for mobile design or more generally small-screen design it can be beneficial to save space by using a dropdown for your navigation.

This plugin provides a way to display your custom menus as dropdowns either using a widget or a function call and can be used as an include in any theme.

= Usage =

If you are using the plugin with a theme you can use the function `dropdown_menu()` in place of calls to `wp_nav_menu()`.

The `dropdown_menu()` function takes the same arguments as `wp_nav_menu()` with the addition of three extras:

`<?php
dropdown_menu( array(

    // You can alter the blanking text eg. "- Menu Name -" using the following
    'dropdown_title' => '-- Main Menu --',

    // indent_string is a string that gets output before the title of a
    // sub-menu item. It is repeated twice for sub-sub-menu items and so on
    'indent_string' => '- ',

    // indent_after is an optional string to output after the indent_string
    // if the item is a sub-menu item
    'indent_after' => ''

) );
?>`

You can extend and alter the output of the dropdowns using the output filters available in the code.

There are also plenty of styling hooks like in the standard list type menus with the addition of classes for targetting items at a certain depth in the menu (`.menu-item-depth-1` for example) aswell the usual `.current-menu-item` and `.current-menu-ancestor` classes.

= Filters/Hooks =

**dropdown_blank_item_text**

`<?php
add_filter( 'dropdown_blank_item_text', 10, 2 );
function my_dropdown_blank_text( $title, $args ) {
    return __( '- Browse -' );
}
?>`

If you want to show the menu title as the blank item text use the follwing code:

`<?php
add_filter( 'dropdown_blank_item_text', 'dropdown_menu_use_menu_title', 10, 2 );
function dropdown_menu_use_menu_title( $title, $args ) {
	return '- ' . $args->menu->name . ' -';
}
?>`

**dropdown_menus_indent_string**

`<?php
add_filter( 'dropdown_menus_indent_string', 10, 4 );
function my_dropdown_indent_string( $indent_string, $item, $depth, $args ) {
    return str_repeat( '&nbsp;&nbsp;', $depth );
}
?>`

**dropdown_menus_indent_after**

`<?php
add_filter( 'dropdown_menus_indent_after', 10, 4 );
function my_dropdown_indent_after( $indent_after, $item, $depth, $args ) {
    return '-';
}
?>`

**dropdown_menus_class**

Use this if you find you get class name or CSS conflicts, for example with Twitter Bootstrap.

`<?php
add_filter( 'dropdown_menus_class', create_function( '$c', 'return "my-dropdown-menu-class";' ) );
?>`

**dropdown_menus_select_current**

Use this filter to stop the output of the `selected="selected"` attribute. Useful if you prefer to show the blank option on every page.

`<?php
add_filter( 'dropdown_menus_select_current', create_function( '$bool', 'return false;' ) );
?>`


= Can I make sure this plugin is available to my theme? =

If your theme requires this plugin to be available it will work as a simple include. Just place the plugin into your theme directory and include dropdown-menus.php from your functions.php file.

If you place the plugin folder into your theme's directory you would use the following code in your functions.php file:

`<?php
if ( ! function_exists( 'dropdown_menu' ) )
	include( 'dropdown-menus/dropdown-menus.php' );
?>`


== Changelog ==

= 1.0 =
* Added check for dropdown_menu being declared already to avoid issues if plugin is used and theme has it embedded

= 0.9 =
* Fixed debug code left in the JS that was affecting IE9 and below. Rookie mistake - really sorry folks.

= 0.8 =
* Dropdown selector explicitly set to work on <select> elements to avoid conflict with twitter bootstrap
* Added a filter to choose whether or not to use the selected attribute

= 0.7 =
* Added a filter for the dropdown menu class name as it very generic and can cause conflicts

= 0.6 =
* Fixed the echo argument, thanks to squingynaut for the tip
* Fixed the ability to call the menu via the theme location name only

= 0.5 =
* Better cross browser & old browser support using getElementsByClassName by Rob Nyman (http://code.google.com/p/getelementsbyclassname/)

= 0.4 =
* Made the menu use the menu title as the blanking option text
* Fixed the menu title usage for dropdowns called via a theme location by populating the menu object

= 0.3 =
* Improved the filter on the blanking item text by passing in the menu $args
* Added a dropdown_title argument to alter the blanking item text from its default
* changed the standard widget class name to 'dropdown-menu-widget'

= 0.2 =
* Removed optgroup tags altogether as nested optgroups are invalid markup
* Added class name for targetting items by depth
* Added configurable indentation text to indicate visually the depth of an item in the menu
* Added extra filters to provide fine-grained control for developers

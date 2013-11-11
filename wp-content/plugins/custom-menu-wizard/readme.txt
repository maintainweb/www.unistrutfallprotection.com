=== Custom Menu Wizard Widget ===
Contributors: wizzud
Tags: menu,widget,widgets,navigation,nav,custom menus,custom menu,partial menu,menu level,menu branch
Requires at least: 3.0.1
Tested up to: 3.6
Stable tag: 2.0.2
License: GPLv2 or Later

Show branches or levels of your menu in a widget, or in content using a shortcode, with full customisation.

== Description ==

This plugin is a boosted version of the WordPress "Custom Menu" widget. 
It provides full control over most of the parameters available when calling WP's [wp_nav_menu()](http://codex.wordpress.org/Function_Reference/wp_nav_menu) function, as well as providing pre-filtering of the menu items in order to be able to select a specific portion of the custom menu. It also automatically adds a couple of custom classes. And there is now (v1.2.0) a shortcode that enables you to include the widget's output in your content.

Features include:

* Display the entire menu, just a branch of it, or even just a specific level of it
* Select a branch based on a specific menu item, or the "current" item (currently displayed page)
* Specify a level to start at, and the number of levels to output
* Select hierarchicial or flat output, both options still abiding by the specified number of levels to output
* Allow the widget title to be entered but not output, or even to be set from the parent item of a menu's sub-branch
* Optionally disable widget output completely if there are no matching menu items found (WordPress < v3.6)
* Include a sub-branch's parent and/or ancestor item(s) in the output, over and above any start level setting
* Automatically add cmw-level-N and cmw-has-submenu classes to output menu items
* Add/specify custom class(es) for the widget block, the menu container, and the menu itself
* Modify the link's output with additional HTML around the link's text and/or the link element itself
* Use Ordered Lists (OL) for the top and/or sub levels instead of Unordered Lists (UL)
* *As of v1.1.0* : Select a branch based on the ultimate ancestor (root level) of the "current" item
* *As of v1.2.0* : Shortcode, [custom_menu_wizard], available to run the widget from within content
* *As of v2.0.0* : Make the output conditional upon the "current" item appearing in the selected/included items
* *As of v2.0.0* : Specify specific menu items
* *As of v2.0.0* : Use the widget's interactive "assist" to help with the widget settings or shortcode definition 

Documentation for the Widget Options, and the associated Shortcode Parameters, can be found under 
[Other Notes](http://wordpress.org/plugins/custom-menu-wizard/other_notes/).

== Widget Options ==

There are quite a few options, which makes the widget settings box very long. I have therefore grouped most of the options into
logical sections and made each section collapsible (with remembered state once saved). As of v1.2.1, only the Filter section is
open by default; all sections below that start off collapsed.

* **Title**

    Set the title for your widget.

* **Hide** *(checkbox)*

    Prevents the entered `Title` being displayed in the front-end widget output.
    
    In the Widgets admin page, I find it useful to still be able to see the `Title` in the sidebar when the widget is collapsed, but I
    don't necessarily want that `Title` to actually be output when the widget is displayed at the front-end. Hence this checkbox.

* **Select Menu** *(select)*

    Choose the appropriate menu from the dropdown list of Custom Menus currently defined in your WordPress application. The
    first one available (alphabetically) is already selected for you by default.

* **Filter**

    * **Show all** *(radio, default)*
    
        Don't apply the `Children of` filter. If you check this option, the `Children of` select will be disabled, as will the `Include Parent`,
        `Include Ancestors` and `Title from Parent Item` checkboxes, as they are only applicable to a `Children of` filter.
    
    * **Children of** *(radio & select)*

        The dropdown list will present a "Current Item" option (default), a "Current Root Item" (v1.1.0)
        and "Current Parent Item" (v1.1.0) option, followed by all the available items from the menu chosen in `Select Menu`.
        The widget will output the *children* of the selected item, always assuming that they lie within the bounds of any other parameters set.

        * "Current Item" is the menu item that WordPress recognises as being currently on display (current menu item)

        * "Current Parent Item" (v1.1.0) is the *immediate* ancestor (within `Select Menu`) of the current menu item

        * "Current Root Item" (v1.1.0) is the *ultimate* ancestor (within `Select Menu`) of the current menu item.

        Obviously, if the current menu item (as determined by WordPress) does not
        appear in the `Select Menu` then there is going to be no output for any of these options.
        
        If you change `Select Menu`, the options presented in this dropdown will change accordingly and the selected option will revert to the default.

    * **Items** (radio & text input)* as of v2.0.0

        Takes a comma- or space-delimited list of menu item ids, specifiying the specific menu items that are required. This is intended
        for situations that it is not possible to handle using other option settings. The simplest way to determine the menu item ids is
        to use the "assist" facility.
        
        Note that the `Starting Level` and `Depth` options (including `Relative to "Current" Item`) have no effect if `Items` is set.

    * **Starting Level** *(select, default: "1")*

        This is the level within the chosen menu (from `Select Menu`) that the widget will start looking for items to keep. Obviously, level 1
        is the root level (ie. those items that have no parent item); level 2 is all the immediate children of the root level items, and so on.
        Note that for a `Children of` filter there is no difference between level 1 and level 2 (because there are no children at level 1).
        Also note that this option does not apply if `Items` is set.

    * **For Depth** *(select, default: "unlimited")*

        This is the maximum depth of the eventual output structure after filtering, and in the case of `Flat` output being requested it is
        still applied - as if the output were `Hierarchical` and then flattened at the very last moment.
        
        You need to be aware that, by default, the
        `For Depth` setting is applied relative to the level at which the first item to be kept is found. For example, say you were to set
        `Children of` to "Current Item", `Starting Level` to "2", and `For Depth` to "2 levels" : if the current item was found at level 3,
        then you would get the current item's immediate children (from level 4), plus *their* immediate children (from level 5).
        Note that this option does not apply if `Items` is set.

    * **Relative to "Current" Item** *(checkbox)* as of v2.0.0
        
        This changes the `For Depth` option such that depth is applied relative to the current menu item, instead of relative to the
        first item found that is to be kept. It only has any effect when `For Depth` is set to something other than "unlimited", and when
        the current menu item is within the filtered items (before taking `For Depth` into account).

* **Fallbacks**

    Fallback for `Children of` being set to either *Current Root Item* or *Current Parent Item*, and current item not having an ancestor:

    * **Switch to Current Item** *(checkbox)* as of v1.1.0

        If enabled,
        it provides a fallback of effectively switching the filter to "Current Item" if the current menu item 
        (as determined by WordPress) is at Level 1
        (top level) of the selected menu. For example, say you were to set `Children of` to "Current Parent Item", with `Starting Level`
        at "1" and `For Depth` at "unlimited" : if the current menu item was found at level 1 (root level of the menu) then ordinarily
        there would be no output because the current item has no parent! If you were to enable the `Switch to Current Item` fallback then you
        *would* have some output - the entire branch below the current item.

        * **Include Parent...** *(checkbox)* as of v1.1.0

            This option extends the `Switch to Current Item` option (above). If the enabled fallback is actually used, this option can
            temporarily override the equivalent **Output** option to ON. Note that if the **Output** options are already set to include
            the parent item (with or without siblings), this option has absolutely no effect.

        * **& its Siblings** *(checkbox)* as of v1.1.0

            This option extends the `Switch to Current Item` option (above). If the enabled fallback is actually used, this option can
            temporarily override the equivalent **Output** option to ON. Note that if the equivalent **Output** option is already enabled,
            this option has absolutely no effect.

    Fallback for `Children of` being set to *Current Item*, and current item not having any children:

    * **Switch to Current Parent Item** *(checkbox)* as of v1.2.0

        If enabled, it provides a fallback of effectively switching the filter to "Current Parent Item" if looking for children
        of Current Item and there aren't any. For example, say you were to set `Children of` to "Current Item", with `Starting Level`
        at "1" and `For Depth` at "unlimited" : if the current menu item has no children then ordinarily
        there would be no output! If you were to enable the `Switch to Current Item` fallback then you
        *would* have some output - the current item and its siblings.
        
        Please note that there is one difference between this fallback and the normal "Current Parent Item" filter : if the current item
        has no ancestor (as well as no children) then you will always get the current item and its siblings, regardless of any other settings!

        * **Include Parent...** *(checkbox)* as of v1.2.0

            This option extends the `Switch to Current Parent Item` option (above). If the enabled fallback is actually used, this option can
            temporarily override the equivalent **Output** option to ON. Note that if the **Output** options are already set to include
            the parent item (with or without siblings), this option has absolutely no effect.

        * **& its Siblings** *(checkbox)* as of v1.2.0

            This option extends the `Switch to Current Parent Item` option (above). If the enabled fallback is actually used, this option can
            temporarily override the equivalent **Output** option to ON. Note that if the equivalent **Output** option is already enabled,
            this option has absolutely no effect.

* **Output**

    * **Hierarchical** *(radio, default)*

        Output in the standard nested list format.

    * **Flat** *(radio)*

        Output in a single list format, ignoring any parent-child relationship other than to maintain the same physical order as would be
        presented in a `Hierarchical` output.

    * **Must Contain "Current" Item** *(checkbox)* as of v2.0.0
    
        If checked, the widget will not list any menu items unless the current menu item appears somewhere in the list.

    * **Include Parent...** *(checkbox)*

        If checked, include the parent item in the output. Only applies to a successful `Children of` filter.

    * **& its Siblings** *(checkbox)* as of v1.1.0

        If checked, include the parent item **and** its siblings in the output. Only applies to a successful `Children of` filter.

    * **Include Ancestors** *(checkbox)*

        Same as `Include Parent` except that all ancestors, right back to root level, are included. Only applies to a successful
        `Children of` filter.

    * **Title from Parent** *(checkbox)*

        Again, this only applies to a successful `Children of` filter. If checked, use the title of the parent item as the widget's
        title when displaying the output. This will override (ie. ignore) the `Hide` checkbox setting!
        
        Please note that this is **not** the same as asking for the title from "the parent of the current menu item"!

    * **Title from "Current" Item** *(checkbox)*

        If checked, use the title of the current menu item (as determined by WordPress) as the widget's
        title when displaying the output. This will override (ie. ignore) the `Hide` checkbox setting!
        
        Note that the current menu item is not required to be within the resultant output, merely within the `Select Menu`.
        Also, `Title from Parent` (if applicable, and if available) takes priority over this option.

    * **Change UL to OL**

        The standard for menus is to use UL (unordered list) elements to display the output. These settings give you the option to swap
        the ULs out for OLs (ordered lists).
        
        * **Top Level** *(checkbox)*

            If checked, swap the outermost UL for an OL.
            
        * **Sub-Levels** *(checkbox)*

            If checked, swap any nested (ie. not the outermost) ULs for an OLs.

    * **Hide Widget if Empty** *(checkbox)*

        If checked, the widget will not output *any* HTML unless it finds at least one menu item that matches the Filter settings.
        
        Please note that as of WordPress v3.6, this option becomes superfluous and will **not** be presented (the wp_nav_menu() function
        has been modified to automatically suppress all HTML output if there are no items to be displayed). The widget will retain
        the setting used on earlier WP versions (in case reversion from WP v3.6 might be required) but *will not present the option
        for WP v3.6+*.

* **Container**

    * **Element** *(default: "div")*

        This menu list is usually wrapped in a "container" element, and this is the tag for that element. You may change it for another
        tag, or you may clear it out and the container will be completely removed. Please note that WordPress is set by default to only
        accept "div" or "nav", but that could be changed or extended by any template or plugin.

    * **Unique ID**

        This allows you to specify your own id (which should be unique) for the container.

    * **Class**

        This allows you to add your own class to the container element.

* **Classes**

    * **Menu Class** *(default: "menu-widget")*

        This is the class that will be applied to the list element that holds the entire menu.
        
    * **Widget Class**

        This allows you to add your own class to the outermost element of the widget, the one that wraps the entire widget output.

* **Links**

    * **Before the Link**

        Text or HTML that will be placed immediately before each menu item's link.

    * **After the Link**

        Text or HTML that will be placed immediately after each menu item's link.

    * **Before the Link Text**

        Text or HTML that will be placed immediately before each menu item's link text.

    * **After the Link Text**

        Text or HTML that will be placed immediately after each menu item's link text.

== Shortcode Parameters ==

The shortcode is **`[custom_menu_wizard]`**. Most of the attributes reflect the options available to the widget, but some have been simplified for easier use in the shortcode format.
Please note that the `Hide Widget if Empty` option is not available to the shortcode : it is set to enabled, and if there are no menu items found then there will be no output from the shortcode.

The simplest way to build a shortcode is to use the widget's "assist" facility (new in v2.0.0). The facilty is available even when the widget is in 
the Inactive Widgets area, so you don't have to add an unwanted instance of the widget to a sidebar.

* **title** *(string)*

    The output's `Title`, which may be overridden by `title_from`. Note that there is no shortcode equivalent of the widget's `Hide` option for the title.

* **menu** *(string | integer)*

    Accepts a menu name (most likely usage) or id. If not provided, the shortcode will attempt to find the first menu (ordered by name) 
    that has menu items attached to it, and use that.

* **children_of** *(string | integer)*

    If not empty then it specifies a `Children of` filter. If neither `children_of` nor `items` are supplied (or are empty) then the 
    filter defaults back to `Show all` (see above). Note that `items`, if supplied, will take precedence over `children_of`.
  
    * If numeric, it is taken as being the id of a menu item. The widget will look for the `Children of` that menu item (within `menu`).
      (Hint : In Menus Admin, hover over the item's **Remove** link and note the number after *menu-item=* in the URL)
  
    * Certain specific strings have the following meanings:
  
        * *'current'* or *'current-item'* : a `Children of` "Current Item" filter

        * *'parent'* or *'current-parent'* : a `Children of` "Current Parent Item" filter

        * *'root'* or *'current-ancestor'* : a `Children of` "Current Root Item" filter

    * If any other string, it is taken to be the title of a menu item. The widget will look for the `Children of` that menu item
      (within `menu`). Please note that the code looks for a *caseless* title match, so specifying `children_of="my menu item"` will
      match against a menu item with the title "My Menu Item". Also note that the first match found (hierarchically) is the one that
      gets used (it is quite possible to have same-named items within a menu structure).

* **items** *(string)* See widget's `Items` option, under **Filter** above.

* **fallback_parent** *(string | integer)*

    This is the fallback option for when `Children of` is set to either *Current Root Item* or *Current Parent Item*, and
    the current item has no ancestors (see `Switch to Current Item` under **Fallbacks** above).

    * Any "truthy" value (eg. 1, *'true'*, *'on'*, *'parent'*, *'siblings'*) : Enables widget's `Switch to Current Item` **Fallbacks** option
    
    * *'parent'* : Enables widget's `Include Parent...` **Fallbacks** extension option (in addition to the above)

    * *'siblings'* : Enables widget's `& its Siblings` **Fallbacks** extension option (in addition to the above)

* **fallback_current** *(string | integer)*

    This is the fallback option for when `Children of` is set to *Current Item*, and
    the current item has no children (see `Switch to Current Parent Item` under **Fallbacks** above).

    * Any "truthy" value (eg. 1, *'true'*, *'on'*, *'parent'*, *'siblings'*) : Enables widget's `Switch to Current Parent Item` **Fallbacks** option
    
    * *'parent'* : Enables widget's `Include Parent...` **Fallbacks** extension option (in addition to the above)

    * *'siblings'* : Enables widget's `& its Siblings` **Fallbacks** extension option (in addition to the above)

* **start_level** *(integer, default 1)* See widget's `Starting Level` option, under **Filter** above.

* **depth** *(integer, default 0)* See widget's `For Depth` option, under **Filter** above.

* **depth_rel_current** *(switch, off by default, 1 to enable)* See widget's `Relative to "Currrent" Item` option, under **Filter** above.
    
* **flat_output** *(switch, off by default, 1 to enable)* See widget's `Flat` option, under **Output** above.

* **contains_current** *(switch, off by default, 1 to enable)* See widget's `Must Contain "Current" Item` option, under **Output** above.

* **include** *(string)*

    * *'parent'* : Enables widget's `Include Parent...` **Output** option

    * *'siblings'* : Enables widget's `& its Siblings` **Output** option

    * *'ancestors'* : Enables widget's `Include Ancestors` **Output** option
    
    Supply more than one by separating them with a comma, space or hyphen, eg. `include="siblings ancestors"`.

* **title_from** *(string)*

    * *'parent'* : Enables widget's `Title from Parent` **Output** option

    * *'current'* : Enables widget's `Title from "Current" Item` **Output** option

    Supply more than one by separating them with a comma, space or hyphen, eg. `title_from="parent,current"`.

* **ol_root** *(switch, off by default, 1 to enable)* See widget's `Top Level` option, under **Output** above.

* **ol_sub** *(switch, off by default, 1 to enable)* See widget's `Sub-Levels` option, under **Output** above.

* **container** *(string)* See widget's `Element` option, under **Container** above.

* **container_id** *(string)* See widget's `Unique ID` option, under **Container** above.

* **container_class** *(string)* See widget's `Class` option, under **Container** above.

* **menu_class** *(string)* See widget's `Menu Class` option, under **Classes** above.

* **widget_class** *(string)* See widget's `Widget Class` option, under **Classes** above.

* **wrap_link** *(string)*

    This is an optional tag name (eg. *'div'*, *'p'*, *'span'*) that, if provided, will be made into HTML start/end tags
    and sent through to the widget as its `Before the Link` and `After the Link` options. Please note that the shortcode usage - a simple
    tag name - is much more restrictive than the widget's options, which allow HTML.

* **wrap_link_text** *(string)*

    This is an optional tag name (eg. *'span'*, *'em'*, *'strong'*) that, if provided, will be made into HTML start/end tags
    and sent through to the widget as its `Before the Link Text` and `After the Link Text` options. Please note that the shortcode usage - a
    simple tag name - is much more restrictive than the widget's options, which allow HTML.

**Shortcode Examples**

* Show the entire "main" menu :

    `
    [custom_menu_wizard menu=main]
    `

* Show the children of the Current Item within the "main" menu, for unlimited depth, and include the Current Item's parent :

    `
    [custom_menu_wizard menu=main children_of=current include=parent]
    `

* From the "animals" menu, show all the items *immediately* below (depth=1) "Small Dogs", plus "Small Dogs" and its sibling items, as ordered lists :

    `
    [custom_menu_wizard menu="animals" children_of="small dogs" depth=1 include="siblings" ol_root=1 ol_sub=1]
    `

== Installation ==

1. EITHER Upload the zip package via 'Plugins > Add New > Upload' in your WP Admin

    OR Extract the zip package and upload `custom-menu-wizard` folder to the `/wp-content/plugins/` directory

1. Activate the plugin through the 'Plugins' menu in your WP Admin

The widget will now be available in the 'Widgets' admin page. 
As long as you already have at least one Menu defined, you can add the new widget to a sidebar and configure it however you want. 
Alternatively, you can use the shortcode in your content.

== Frequently Asked Questions ==

If you have a question or problem that is not covered here, please use the integrated Support forum.

= Are there any known problems?

Yep, 'fraid so :

1. The widget will only recognise one "current" item (as of v2.0.2, that's the first one encountered; prior to that it was the last one found). It is perfectly possible to have more than one menu item marked as "current" (adding a page twice to a menu, for example), but if CMW has been configured to filter on anything related to a "current item" it can only choose one. This may also cause problems with some plugins that can affect a menu (PolyLang's language-switcher is one known example).

2. The widget does not play well with PolyLang's language-switcher when it has been added to a menu that the widget is filtering.

= Why isn't it working? Why is there no output? =

I don't know. With all due respect (and a certain amount of confidence in the widget) I would venture to suggest that it is probably due to 
the option settings on the widget/shortcode. The quickest way to resolve any such issues is to use the widget's interactive "assist", and 
ensure that you set the current menu item correctly for the page(s) that you are having problems with. However, I am well aware that I not 
infallible, and if you still have problems then please let me have as much information as possible and I will endeavour to help. (Please 
note that simply reporting "It doesn't work" is not the most useful of feedbacks, and is unlikely to get a response other than, possibly, 
a request for more details).

= How do I use the "assist"? =

The widget's interactive "assist" is specific to each widget instance. It is a javascript-driven *emulator* that uses the widget instance's 
option settings - including the menu selected - to build a pictorial representation of the menu and show you, in blue, which menu items will 
be output according to the current option settings. It also shows a very basic output list of those menu items, although it will not apply 
some of the more advanced HTML-modifying options such as can be found under the Container, Classes or Links sections.
Any of the displayed menu items can be designated as the "current menu item" simply by clicking on it (click again to deselect, or another 
item to change). The "current menu item" is shaded red, with its parent shaded orange and ancestors shaded yellow. All changes in the 
"current menu item" and the widget options are immediately reflected by the "assist" (text fields in the widget options simply need to lose 
focus).

Once you are happy with the results, having tested all possible settings of "current menu item" (if it applies), then simply Save the widget. 
Alternatively, simply copy-paste the shortcode code produced by the "assist" straight into your post (you do not need to Save the widget!).
The widget does not have to Saved to *test* any of the options.

= Is there an easy way to construct the shortcode to get the results that I want? =

Yes. Use the widget's interactive "assist" capability (see above). Note that you do not need to have the widget in a sidebar : the 
"assist" also works off a widget that is in the Inactive Widgets area of the widget admin page.

= How do I get the menu item ids for the `Items` option? =

Use the widget's interactive "assist" (see above). Within the representation menu structure, each menu item's id is set in its title 
attribute, so should be seen when the cursor is moved over the item. A simpler way is to check the `Items` option : the "assist" will 
then show a checkbox beside each menu item and you simply [un]check the items as required. Each selection will be reflected back into the 
widget's `Items` settings, and also in the shortcode code.

Alternatively, go to Appearance, Menus and select the relevant menu; hover over the edit, Remove, or Cancel link for an item and look in 
the URL (the link's href) for `menu-item=NNN` ... the NNN is the menu item id.

= Why is the `Must Contain Current Item` option in the Output section and not in the Filter section? =

It was a close call, but since the Output options can extend the final list - and the check for "current menu item" is made against the 
*entire* resultant list - I decided that `Must contain Current Item` was more of a "final output" check than an initial filter.

== Screenshots ==

1. Widget options

2. More widget options

3. Even more widget options

4. Widget's "assist"

== Changelog ==

= 2.0.2 =

* bugfix : the Include Ancestors option was not automatically including the Parent

* bugfix : the "assist" was incorrectly calculating Depth Relative to Current Item when the current menu item was outside the scope of the Filtered items

* behaviour change : only recognise the first "current" item found (used to allow subsequent "current" items to override any already encountered)

= 2.0.1 =

* bugfix : an incorrect test for a specific-items filter prevented show-all producing any output

= 2.0.0 =

* **! Possible Breaker !** The calculation of `Start Level` has been made consistent across the `Show all` and `Children of` filters : if you previously had a setup where you were filtering for the children of an item at level 2, with start level set to 4, there would have been no output because the immediate children (at level 3) were outside the start level. Now, there *will* be output, starting with the grand-children (at level 4).

* **! Possible Breaker !** There is now deemed to be an artificial "root" item above the level 1 items, which mean that a `Children of` filter set to "Current Parent Item" or "Current Root Item" will no longer fail for a top-level "current menu item". If you have the "no ancestor" fallback set then this change will have no impact (but you may now want to consider turning the fallback off?); if you *don't* currently use the "no ancestor" fallback, then where there was previously no output there will now be some!

* added new option : Items, a comma- or space-delimited list of menu item ids, as an alternative Filter

* added new option : Depth Relative to Current Item to the Filter section (depth_rel_current=1 in the shortcode)

* added new option : Must Contain Current Item to the Output section (contains_current=1 in the shortcode)

* changed the widget's "demo" facility to "assist" and brought it into WordPress admin, with full interactivity with the widget

* refactored code

= 1.2.2 =

* bugfix : fallback for Current Item with no children was failing because the parent's children weren't being picked out correctly

= 1.2.1 =

* added some extra custom classes, when applicable : cmw-fellback-to-current & cmw-fellback-to-parent (on outer UL/OL) and cmw-the-included-parent, cmw-an-included-parent-sibling & cmw-an-included-ancestor (on relevant LIs)

* corrected 'show all from start level 1' processing so that custom classes get applied and 'Title from "Current" Item' works (regardless of filter settings)

* changed the defaults for new widgets such that only the Filter section is open by default; all the others are collapsed

* in demo.html, added output of the shortcode applicable to the selections made

* in demo.html, added a link to the documentation page

* corrected 2 of the shortcode examples in the readme.txt, and made emulator (demo) available from the readme

= 1.2.0 =

* added custom_menu_wizard shortcode, to run the widget from within content

* moved the 'no ancestor' fallback into new Fallback collapsible section, and added a fallback for Current Item with no children

* added an option allowing setting of title from current menu item's title

* fixed a bug with optgroups/options made available for the 'Children of' selector after the widget has been saved (also affected disabled fields and styling)

* don't include menus with no items

* updated demo.html

= 1.1.0 =

* added 'Current Root Item' and 'Current Parent Item' to the `Children of` filter

* added `Fallback to Current Item` option, with subsidiary options for overriding a couple of Output options, as a means to enable Current Root & Current Parent to match a Current Item at root level

* added an Output option to include both the parent item **and** the parent's siblings (for a successful `Children of` filter)

* added max-width style (100%) to the `Children of` SELECT in the widget options

* added widget version to the admin js enqueuer

* ignore/disable Hide Empty option for WP >= v3.6 (wp_nav_menu() now does it automatically)

* included a stand-alone helper/demo html page

* rebuilt the `Children of` SELECT in the widget options to cope with IE's lack of OPTGROUP/OPTION styling

* moved the setting of 'disabled' attributes on INPUTs/SELECTs from PHP into javascript

= 1.0.0 =

Initial release

== Upgrade Notice ==

= 2.0.2 =

Fixed a bug with the Include Ancestors option, where it was not automatically including the Parent.
Fixed a bug in the "assist", where it was incorrectly calculating Depth Relative to Current Item when the current menu item was outside the scope of the Filtered items.
Changed determination of the "current" item such that only the first one encountered is recognised, rather than allowing subsequent "current" items to override previous ones.

= 2.0.1 =

Fixed a bug whereby a test for a specific-items filter prevented show-all from producing any output.

= 2.0.0 =

**! Possible Breaker !** My apologies if this affects you, but there are 2 possible scenarios where settings that previously resulted in no output *might* now produce output : 
+ if you have set a `Children of` filter, **and** you have changed the `Start Level` to a level greater than 2, or
+ if you have set the `Children of` filter to Current Parent/Root Item, and you have **not** set the "no ancestor" fallback.
*__If you think you may be impacted, please check the [Changelog](http://wordpress.org/plugins/custom-menu-wizard/changelog/) for a fuller explanation of what has changed.__*

New options :
+ `Items` allows specific menu item ids to be listed, as an alternative to the other filters
+ `Relative to "Current" Item` allows a limited Depth to be calculated relative to the current menu item
+ `Must Contain "Current" Item` requires that there be no output unless the resultant list contains the current menu item.
Rebuilt the "demo" facility as an "assist" wizard for the widget It is now fully interactive with the widget instance, and generates the entire shortcode according to the widget instance settings.

= 1.2.2 =

Bugfix : The fallback for Current Item with no children was failing because the parent's children weren't being picked out correctly

= 1.2.1 =

Added a few extra custom classes, and changed the defaults for new widgets such that only the Filter section is open by default. 
Fixed Show All processing so that custom classes always get applied, and 'Title from "Current" Item' works regardless of filter settings. 
Fixed a couple of the shortcode examples in the readme.txt, and added display of the applicable shortcode settings to the demo.html.

= 1.2.0 =

Added custom_menu_wizard shortcode, to run the widget from within content.
Added a new fallback for Current Item having no children, and moved all fallbacks into a collapsible Fallbacks section.
Fixed a bug with optgroups/options made available for the 'Children of' selector after the widget has been saved (also affected disabled fields and styling).

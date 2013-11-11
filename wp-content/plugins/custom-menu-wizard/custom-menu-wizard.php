<?php
/*
 * Plugin Name: Custom Menu Wizard
 * Plugin URI: http://wordpress.org/plugins/custom-menu-wizard/
 * Description: Show any part of a custom menu in a Widget, or in content using a Shortcode. Customise the output with extra classes or html; filter by current menu item or a specific item; set a depth, show the parent(s), change the list style, etc. Use the included emulator to assist with the filter settings.
 * Version: 2.0.2
 * Author: Roger Barrett
 * Author URI: http://www.wizzud.com/
 * License: GPL2+
*/

/*
 * v2.0.2 change log:
 * - fixed bug where Include Ancestors was not automatically including the Parent
 * - fixed bug where the "assist" was incorrectly calculating Depth Relative to Current Item when the current menu item was outside the scope of the Filtered items
 * - behaviour change : only recognise the first "current" item found (used to allow subsequent "current" items to override any already encountered)
 * 
 * v2.0.1 change log:
 * - fixed bug that set a specific items filter when it shouldn't have been set, and prevented show-all working
 * 
 * v2.0.0 change log:
 * - Possible Breaker! : start level has been made consistent for showall and kids-off filters. Previously, a kids-of filter on an item at level 2,
 *   with start level set to 4, would return no output because the immediate kids (at level 3) were outside the start level; now, there will
 *   be output, starting with the grand-kids (at level 4)
 * - Possible Breaker! : there is now an artificial "root" above the top level menu items, which means that a parent or root children-of filter will no 
 *   longer fail for a top-level current menu item; this may well obviate the need for the current item fallback, but it has been left in for
 *   backward compatibility.
 * - added option for calculating depth relative to current menu item
 * - added option allowing list output to be dependent on current menu item being present somewhere in the list
 * - refactored the code
 * 
 * v1.2.2 change log:
 * - bugfix : fallback for Current Item with no children was failing because the parent's children weren't being picked out correctly
 * 
 * v1.2.1 change log:
 * - added some extra custom classes, when applicable : cmw-fellback-to-current & cmw-fellback-to-parent (on outer UL/OL) and cmw-the-included-parent, cmw-an-included-parent-sibling & cmw-an-included-ancestor (on relevant LIs)
 * - corrected 'show all from start level 1' processing so that custom classes get applied and 'Title from "Current" Item' works (regardless of filter settings)
 * - changed the defaults for new widgets such that only the Filter section is open by default; all the others are collapsed
 * - updated demo.html and readme.txt, and made demo available from readme
 * 
 * v1.2.0 change log:
 * - added custom_menu_wizard shortcode, to run the widget from within content
 * - moved the 'no ancestor' fallback into new Fallback collapsible section, and added a fallback for Current Item with no children
 * - fixed bug with optgroups/options made available for the 'Children of' selector after the widget has been saved (also affecting disabled fields & styling)
 * - don't include menus with no items
 * - updated demo.html
 * 
 * v1.1.0 change log:
 * - added 'Current Root Item' and 'Current Parent Item' to the 'Children of' filter
 * - added an Output option to include both the parent item and the parent's siblings (for a successful 'Children of' filter)
 * - added Fallback to Current Item option, and subsidiary Output option overrides, to enable Current Root & Current Parent to match a Current Item at root level
 * - added max-width style (100%) to the 'Children of' SELECT in the widget options
 * - added widget version to the admin js enqueuer
 * - ignore/disable hide_empty for WP >= v3.6 (wp_nav_menu() does it automatically)
 * - rebuilt 'Children of' SELECT to account for IE's lack of OPTGROUP/OPTION styling
 * - moved the setting of 'disabled' attributes on INPUTs/SELECTs from PHP into javascript
 */

$Custom_Menu_Wizard_Widget_Version = '2.0.2';

/**
 * registers the widget and adds the shortcode
 */
function custom_menu_wizard_register_widget() {
	register_widget('Custom_Menu_Wizard_Widget');
	add_shortcode('custom_menu_wizard', 'custom_menu_wizard_widget_shortcode');
}
add_action('widgets_init', 'custom_menu_wizard_register_widget');

/**
 * enqueues script file for the widget admin
 */
function custom_menu_wizard_widget_admin_script(){
	global $wp_scripts;
	wp_enqueue_style('custom-menu-wizard-plugin-styles', plugins_url('/custom-menu-wizard.css', __FILE__), array(), $Custom_Menu_Wizard_Widget_Version);
	wp_enqueue_script('custom-menu-wizard-plugin-script', plugins_url('/custom-menu-wizard.min.js', __FILE__), array('jquery-ui-dialog'), $Custom_Menu_Wizard_Widget_Version);
	if( !wp_style_is( 'jquery-ui', 'registered' ) ) {
		$jquery_ui_version = isset( $wp_scripts->registered['jquery-ui-core']->ver ) ? $wp_scripts->registered['jquery-ui-core']->ver : '1.9.2';
		wp_register_style( 'jquery-ui', 'http://ajax.googleapis.com/ajax/libs/jqueryui/' . $jquery_ui_version . '/themes/smoothness/jquery-ui.css' );
	}
	wp_enqueue_style( 'jquery-ui' );
}
add_action('admin_print_scripts-widgets.php', 'custom_menu_wizard_widget_admin_script');

/**
 * puts the contents of the Upgrade Notice (from readme.txt) for a new version under the widget's entry in Appearances - Widgets
 */
function custom_menu_wizard_update_message($plugin_data, $r){
	global $Custom_Menu_Wizard_Widget_Version;
	$readme = wp_remote_fopen( 'http://plugins.svn.wordpress.org/custom-menu-wizard/trunk/readme.txt' );
//	$readme = file_get_contents( plugins_url( '/readme.txt', __FILE__ ) );
	if(!empty($readme)){
		//grab the Upgrade Notice section from the readme...
		if(preg_match('/== upgrade notice ==(.+)(==|$)/ims', $readme, $match) > 0){
			$readme = $match[1];
		}else{
			$readme = '';
		}
	}
	if(!empty($readme)){
		//if there's a heading for the currently installed version, take anything above it...
		if(($match = strpos($readme, "= $Custom_Menu_Wizard_Widget_Version =")) !== false){
			$readme = substr($readme, 0, $match);
		}
		//trim it...
		$readme = trim(str_replace("\r", '', $readme), " \n");
	}
	if(!empty($readme)){
		$readme = preg_replace(
			array(
				'/^= (\d+\.\d+\.\d+.*) =/m',    // => /P H4 Upgrade Notice ... /H4 P
				'/(__|\*\*)!\s?([^*]+!)\1/',    // => STRONG red ... /STRONG
				'/(__|\*\*)([^*]+)\1/',         // => STRONG ... /STRONG
				'/\*([^*]+)\*/',                // => EM ... /EM
				'/`([^`]+)`/',                  // => CODE ... /CODE
				'/\[([^\]]+)\]\(([^\)]+)\)/',   // => A ... /A
				'/\n\+\s+/',                    // => SPAN indented bullet
				'/\n[ \n]*/',                   // => BR
				//remove breaks that immediately follow/precede a paragraph start/end tag...
				'/(<p[^>]*>)<br\s\/>/',         // => P
				'/<br\s\/>(<\/p>)/'             // => /P
			),
			array(
				'</p><h4 style="margin:0;"><em>' . __("Upgrade Notice") . ' $1</em></h4><p style="margin:0.25em 1em;">',
				'<strong style="color:#cc0000;">$2</strong>',
				'<strong>$2</strong>',
				'<em>$1</em>',
				'<code>$1</code>',
				'<a href="$2">$1</a>',
				"\n" . '&nbsp;<span style="margin:0 0.5em;">&bull;</span>',
				'<br />',
				'$1',
				'$1'
			),
			//convert html chars...
			esc_html($readme)
			);
		//remove the *first* P end tag...
		$readme = preg_replace('/<\/p>/', '', $readme . '</p>', 1);
	}
	//show if not empty...
	if(!empty($readme)){
?>
<div style="font-weight:normal;background-color:#fff0c0;border:1px solid #ff9933;border-radius:0.5em;margin:0.5em;">
	<div style="margin:0.5em 0.5em 0.5em 1em;max-height:12em;overflow:auto;">
		<?php echo $readme; ?>
	</div>
</div>
<?php
	}
}
/**
 * if the plugin has an update...
 */
function custom_menu_wizard_admin_menu(){
	add_action('in_plugin_update_message-' . plugin_basename(__FILE__), 'custom_menu_wizard_update_message', 10, 2);
}
add_action('admin_menu', 'custom_menu_wizard_admin_menu');


/*
 * Custom Menu Wizard Walker class
 * NB: Walker_Nav_Menu class is in wp-includes/nav-menu-template.php, and is itself an 
 *     extension of the Walker class (wp-includes/class-wp-walker.php)
 */
class Custom_Menu_Wizard_Walker extends Walker_Nav_Menu {

	/**
	 * opens a sub-level with a UL or OL start-tag
	 *
	 * @param string $output Passed by reference. Used to append additional content.
	 * @param int $depth Depth of page. Used for padding.
	 */
	function start_lvl( &$output, $depth = 0, $args = array() ) {
		$indent = str_repeat("\t", $depth);
		$listtag = empty( $args->_custom_menu_wizard['ol_sub'] ) ? 'ul' : 'ol';
		$output .= "\n$indent<$listtag class=\"sub-menu\">\n";
	}

	/**
	 * closes a sub-level with a UL or OL end-tag
	 *
	 * @param string $output Passed by reference. Used to append additional content.
	 * @param int $depth Depth of page. Used for padding.
	 */
	function end_lvl( &$output, $depth = 0, $args = array() ) {
		$indent = str_repeat("\t", $depth);
		$listtag = empty( $args->_custom_menu_wizard['ol_sub'] ) ? 'ul' : 'ol';
		$output .= "$indent</$listtag>\n";
	}

	/**
	 * pre-filters elements then calls parent::walk()
	 * 
	 * @filters : custom_menu_wizard_walker_items          array of filtered menu elements; array of args
	 * 
	 * @param array $elements Menu items
	 * @param integer $max_depth
	 * @return string
	 */
	function walk($elements, $max_depth){

		$args = array_slice(func_get_args(), 2);
		$args = $args[0];

		if( $max_depth >= -1 && !empty( $elements ) && isset($args->_custom_menu_wizard) ){

			$cmw =& $args->_custom_menu_wizard;
			//in $cmw (array) :
			//  filter : 0 = show all; 1 = kids of (current [root|parent] item or specific item); -1 = specific items (v2.0.0)
			//  filter_item : 0 = current item, -1 = parent of current (v1.1.0), -2 = root ancestor of current (v1.1.0); else a menu item id
			//  flat_output : true = equivalent of $max_depth == -1
			//  include_parent : true = include the filter_item menu item
			//  include_parent_siblings : true = include the siblings (& parent) of the filter_item menu item
			//  include_ancestors : true = include the filter_item menu item plus all it's ancestors
			//  title_from_parent : true = widget wants parent's title
			//  title_from_current : true = widget wants current item's title (v1.2.0)
			//  start_level : integer, 1+
			//  depth : integer, replacement for max_depth and also applied to 'flat' output
			//  depth_rel_current : true = changes depth calc from "relative to first filtered item found" to "relative to current item's level" (if current item is found below level/branch) (v2.0.0)
			//  fallback_no_ancestor : true = if looking for an ancestor (root or parent) of a top-level current item, fallback to current item (v1.1.0)
			//  fallback_include_parent : true = if fallback_no_ancestor comes into play then force include_parent to true (v1.1.0)
			//  fallback_include_parent_siblings : true = if fallback_no_ancestor comes into play then force include_parent_siblings to true (v1.1.0)
			//  fallback_no_children : true = if looking for a current item, and that item turns out to have no children, fallback to current parent (v1.2.0)
			//  fallback_nc_include_parent : true = if fallback_no_children comes into play then force include_parent to true (v1.2.0)
			//  fallback_nc_include_parent_siblings : true = if fallback_no_children comes into play then force include_parent_siblings to true (v1.2.0)
			//  contains_current : true = the output - both Filtered and any Included items - must contain the current menu item (v2.0.0)
			//  items : comma-or-space delimited list of item ids
			//
			//  _walker (array) : for anything that only the walker can determine and that needs to be communicated back to the widget instance
			//
			//$elements is an array of objects, indexed by position within the menu (menu_order),
			//starting at 1 and incrementing sequentially regardless of parentage (ie. first item is [1],
			//second item is [2] whether it's at root or subordinate to first item)
			$cmw['_walker']['fellback'] = false;

			$find_kids_of = $cmw['filter'] > 0;
			$find_specific_items = $cmw['filter'] < 0; //v2.0.0 //v2.0.1:bug fixed (changed < 1 to < 0)
			$find_current_item = $find_kids_of && empty( $cmw['filter_item'] );
			$find_current_parent = $find_kids_of && $cmw['filter_item'] == -1; //v1.1.0
			$find_current_root = $find_kids_of && $cmw['filter_item'] == -2; //v1.1.0
			$depth_rel_current = $cmw['depth_rel_current'] && $cmw['depth'] > 0; //v2.0.0
			//these could change depending on whether a fallback comes into play (v1.1.0)
			$include_parent = $cmw['include_parent'] || $cmw['include_ancestors'];
			$include_parent_siblings = $cmw['include_parent_siblings'];

			$id_field = $this->db_fields['id']; //eg. = 'db_id'
			$parent_field = $this->db_fields['parent']; //eg. = 'menu_item_parent'

			$structure = array(0 => array(
				'level' => 0,
				'ancestors' => array(),
				'kids' => array(),
				'element' => -1,
				'keepCount' => 0
				));
			$levels = array(
				array() //for the artificial level-0
				); 
			$allLevels = 9999;
			$startWithKidsOf = -1;

			foreach( $elements as $i=>$item ){
				$itemID = $item->$id_field;
				$parentID = empty( $item->$parent_field ) ? 0 : $item->$parent_field;


				//if $structure[] hasn't been set then it's an orphan; in order to keep orphans, max_depth must be 0 (ie. unlimited)
				//note that if a child is an orphan then all descendants of that child are also considered to be orphans!
				//also note that orphans (in the original menu) are ignored by this widget!
				if( isset( $structure[ $parentID ] ) ){
					//keep track of current item (as a structure key)...
					if( $item->current && empty( $currentItem ) ){
						$currentItem = $itemID;
					}
					//this level...
					$thisLevel = $structure[ $parentID ]['level'] + 1;
					if( empty( $levels[ $thisLevel ] ) ){
						$levels[ $thisLevel ] = array();
					}
					$levels[ $thisLevel ][] = $itemID;

					$structure[ $itemID ] = array(
						//level within structure...
						'level' => $thisLevel,
						//ancestors (from the artificial level-0, right down to parent, inclusive) within structure...
						'ancestors' => $structure[ $parentID ]['ancestors'],
						//kids within structure, ie array of itemID's...
						'kids' => array(),
						//item within elements...
						'element' => $i,
						//assume no matches...
						'keep' => false
						);
					$structure[ $itemID ]['ancestors'][] = $parentID;
					$structure[ $parentID ]['kids'][] = $itemID;
				}
			} //end foreach

			//no point doing much more if we need the current item and we haven't found it, or if we're looking for specific items with none given...
			$continue = true;
			if( empty( $currentItem ) && ( $find_current_item || $find_current_parent || $find_current_root || $cmw['contains_current'] ) ){
				$continue = false;
			}elseif( $find_specific_items && empty( $cmw['items'] ) ){
				$continue = false;
			}

			// IMPORTANT : as of v2.0.0, start level has been rationalised so that it acts the same across all filters (except for specific items!). 
			// Previously ...
			//   start level for a show-all filter literally started at the specified level and reported all levels until depth was reached.
			//   however, start level for a kids-of filter specified the level that the *immediate* kids of the selected filter had to be at
			//   or below. That was consistent for a specific item, current-item and current-parent filter, but for a current-root filter what
			//   it actually did was test the current item against the start level, not the current item's root ancestor! Inconsistent!
			//   But regardless of the current-root filter's use of start level, there was still the inconsistency between show-all and
			//   kids-of usage.
			// Now (as of v2.0.0) ...
			//   start level and depth have been changed to definitively be secondary filters to the show-all & kids-of primary filter.
			//   The primary filter - show-all, or a kids-of - will provide the initial set of items, and the secondary - start level & depth -
			//   will further refine that set, with start level being an absolute, and depth still being relative to the first item found.
			//   The sole exception to this is when Depth Relative to Current Menu Item is set, which modifies the calculation of depth (only)
			//   such that it becomes relative to the level at which the current menu item can be found (but only if it can be found at or
			//   below start level).
			// The effects of this change are that previously, filtering for kids of an item that was at level 2, with a start level of 4,
			// would fail to return any items because the immediate kids (at level 3) were outside the start level. Now, the returned items
			// will begin with the grand-kids (ie. those at level 4).
			// Note that neither start level nor depth are applicable to a specific items filter (also new at v2.0.0)!
			
			//the kids-of filters...
			if( $continue && $find_kids_of ){
				//specific item...
				if( $cmw['filter_item'] > 0 && isset( $structure[ $cmw['filter_item'] ] ) && !empty( $structure[ $cmw['filter_item'] ]['kids'] ) ){
					$startWithKidsOf = $cmw['filter_item'];
				}
				if( $find_current_item ){
					if( !empty( $structure[ $currentItem ]['kids'] ) ){
						$startWithKidsOf = $currentItem;
					}elseif( $cmw['fallback_no_children'] ){
						//no kids,  and fallback to current parent is set...
						//note that there is no "double fallback", so current parent "can" be the artifical zero element (level-0) *if*
						//     the current item is a singleton( ie. no kids & no ancestors)!
						$ancestor = array_slice( $structure[ $currentItem ]['ancestors'], -1, 1 );
						$startWithKidsOf = $ancestor[0]; //can be zero!
						$include_parent = $include_parent || $cmw['fallback_nc_include_parent'];
						$include_parent_siblings = $include_parent_siblings || $cmw['fallback_nc_include_parent_siblings'];
						$cmw['_walker']['fellback'] = 'to-parent';
					}
				}elseif( $find_current_parent || $find_current_root ){
					//as of v2.0.0 the fallback to current item - for current menu items at the top level - is deprecated, but
					//retained for a while to maintain backward compatibility
					//if no parent : fall back to current item (if set)...
					if( $structure[ $currentItem ]['level'] == 1 && $cmw['fallback_no_ancestor'] ){
						$startWithKidsOf = $currentItem;
						$include_parent = $include_parent || $cmw['fallback_include_parent'];
						$include_parent_siblings = $include_parent_siblings || $cmw['fallback_include_parent_siblings'];
						$cmw['_walker']['fellback'] = 'to-current';
					}else{
						//as of v2.0.0, the artificial level-0 counts as parent of a top-level current menu item...
						if( $find_current_parent ){
							$ancestor = -1;
						}elseif( $structure[ $currentItem ]['level'] > 1 ){
							$ancestor = 1;
						}else{
							$ancestor = 0;
						}
						$ancestor = array_slice( $structure[ $currentItem ]['ancestors'], $ancestor, 1 );
						if( !empty( $ancestor ) ){
							$startWithKidsOf = $ancestor[0]; //as of v2.0.0, this can now be zero!
						}
					}
				}
			}

			if( $continue ){
				//right, let's set the keep flags
				//for specific items, go straight in on the item id (start level and depth do not apply here)...
				if( $find_specific_items ){
					foreach( preg_split('/[,\s]+/', $cmw['items'] ) as $itemID ){
						if( isset( $structure[ $itemID ] ) ){
							$structure[ $itemID ]['keep'] = true;
							$structure[0]['keepCount']++;
						}
					}
				//for show-all filter, just use the levels...
				}elseif( !$find_kids_of ){
					//prior to v2.0.0, depth was always related to the first item found, and still is *unless* depth_rel_current is set
					if( $depth_rel_current && !empty( $currentItem ) && $structure[ $currentItem ]['level'] >= $cmw['start_level'] ){
						$bottomLevel = $structure[ $currentItem ]['level'] + $cmw['depth'] - 1;
					}else{
						$bottomLevel = $cmw['depth'] > 0 ? $cmw['start_level'] + $cmw['depth'] - 1 : $allLevels;
					}
					for( $i = $cmw['start_level']; isset( $levels[ $i ] ) && $i <= $bottomLevel; $i++ ){
						foreach( $levels[ $i ] as $itemID ){
							$structure[ $itemID ]['keep'] = true;
							$structure[0]['keepCount']++;
						}
					}
				//for kids-of filters, run a recursive through the structure's kids...
				}elseif( $startWithKidsOf > -1 ){
					//prior to v2.0.0, depth was always related to the first item found, and still is *unless* depth_rel_current is set
					//NB the in_array() of ancestors prevents depth_rel_current when startWithKidsOf == currentItem
					if( $depth_rel_current && !empty( $currentItem ) && $structure[ $currentItem ]['level'] >= $cmw['start_level'] 
							&& in_array( $startWithKidsOf, $structure[ $currentItem ]['ancestors'] ) ){
						$bottomLevel = $structure[ $currentItem ]['level'] - 1 + $cmw['depth'];
					}else{
						$bottomLevel = $cmw['depth'] > 0 
							? max( $structure[ $startWithKidsOf ]['level'] + $cmw['depth'], $cmw['start_level'] + $cmw['depth'] - 1 ) 
							: $allLevels;
					}
					//$structure[0]['keepCount'] gets incremented in this recursive method...
					$this->_cmw_set_keep_kids( $structure, $startWithKidsOf, $cmw['start_level'], $bottomLevel );
				}
			
				if( $structure[0]['keepCount'] > 0 ){
					//we have some items! we now may need to set some more keep flags, depending on the include settings...

					//do we need to include parent, parent siblings, and/or ancestors?...
					//NB these are not restricted by start_level!
					if( $find_kids_of && $startWithKidsOf > 0 ){
						if( $include_parent ){
							$structure[ $startWithKidsOf ]['keep'] = true;
							//add the class directly to the elements item...
							$elements[ $structure[ $startWithKidsOf ]['element'] ]->classes[] = 'cmw-the-included-parent';
						}
						if( $include_parent_siblings ){
							$ancestor = array_slice( $structure[ $startWithKidsOf ]['ancestors'], -1, 1);
							foreach($structure[ $ancestor[0] ]['kids'] as $itemID ){
								//may have already been kept by include_parent...
								if( !$structure[ $itemID ]['keep'] ){
									$structure[ $itemID ]['keep'] = true;
									//add the class directly to the elements item...
									$elements[ $structure[ $itemID ]['element'] ]->classes[] = 'cmw-an-included-parent-sibling';
								}
							}
						}
						if( $cmw['include_ancestors'] ){
							foreach( $structure[ $startWithKidsOf ]['ancestors'] as $itemID ){
								if( $itemID > 0 && !$structure[ $itemID ]['keep'] ){
									$structure[ $itemID ]['keep'] = true;
									//add the class directly to the elements item...
									$elements[ $structure[ $itemID ]['element'] ]->classes[] = 'cmw-an-included-parent-ancestor';
								}
							}
						}
					}
				}
			}

			$substructure = array();
			//check that (a) we have items, and (b) if we must have current menu item, we've got it...
			if( $structure[0]['keepCount'] > 0 && ( !$cmw['contains_current'] || $structure[ $currentItem ]['keep'] ) ){

				//might we want the parent's title as the widget title?...
				if( $find_kids_of && $cmw['title_from_parent'] && $startWithKidsOf > 0 ){
					$cmw['_walker']['parent_title'] = apply_filters(
						'the_title',
						$elements[ $structure[ $startWithKidsOf ]['element'] ]->title,
						$elements[ $structure[ $startWithKidsOf ]['element'] ]->ID
						);
				}
				//might we want the current item's title as the widget title?...
				if( !empty( $currentItem ) && $cmw['title_from_current'] ){
					$cmw['_walker']['current_title'] = apply_filters(
						'the_title',
						$elements[ $structure[ $currentItem ]['element'] ]->title,
						$elements[ $structure[ $currentItem ]['element'] ]->ID
						);
				}

				//now we need to gather together all the 'keep' items from structure;
				//while doing so, we need to set up levels and kids, ready for adding classes...
				foreach( $structure as $k=>$v ){
					if( $v['keep'] ){
						$substructure[ $k ] = $v;
						//take a copy of the elements item...
						$substructure[ $k ]['element'] = $elements[ $v['element'] ];
						//use kids as a has-submenu flag...
						$substructure[ $k ]['kids'] = 0;
						//any surviving parent (except the artificial level-0) should have submenu class set on it...
						array_shift( $v['ancestors'] ); //remove the level-0
						for( $i = count( $v['ancestors'] ) - 1; $i >= 0; $i-- ){
							if( $substructure[ $v['ancestors'][ $i ] ]['keep'] ){
								$substructure[ $v['ancestors'][ $i ] ]['kids']++;
							}else{
								//not a 'kept' ancestor so remove it...
								array_splice( $v['ancestors'], $i, 1 );
							}
						}
						//ancestors now only has 'kept' ancestors...
						$substructure[ $k ]['level'] = count( $v['ancestors'] ) + 1;
						//need to ensure that the parent_field of all the new top-level (ie. root) items is set to
						//zero, otherwise the parent::walk() will assume they're orphans and ignore them.
						//however, we also need to check - especially for a specific-items filter (v2.0.0) - that parent_field of a 
						//child actually points to the closest 'kept' ancestor; otherwise, given A (kept) > B (not kept) > C (kept)
						//the parent_field of C would point to a non-existent B and would subsequently be considered an orphan!
						if( $substructure[ $k ]['level'] == 1){
							$substructure[ $k ]['element']->$parent_field = 0;
						}else{
							//NB even though this really only needs to be done for $find_specific_items, I'm doing it regardless.
							//set to the closest ancestor, ie. the new(?) parent...
							$ancestor = array_slice( $v['ancestors'], -1, 1 );
							$substructure[ $k ]['element']->$parent_field = $ancestor[0];
						}
					}
				}
			}

			//put substructure's elements back into $elements (remember that it's a 1-based array!)...
			$elements = array();
			$i = 1;
			foreach( $substructure as $k=>$v ){
				$elements[ $i ] = $v['element'];
				//add the submenu class?...
				if( $v['kids'] > 0 ){
					$elements[ $i ]->classes[] = 'cmw-has-submenu';
				}
				//add the level class...
				$elements[ $i ]->classes[] = 'cmw-level-' . $v['level'];
				$i++;
			}
			unset( $structure, $substructure );

			//since we've done all the depth filtering, set max_depth to unlimited (unless 'flat' was requested!)...
			if( !$cmw['flat_output'] ){
				$max_depth = 0;
			}
		} //ends the check for bad max depth, empty elements, or empty cmw args

		return empty( $elements ) ? '' : parent::walk( apply_filters( 'custom_menu_wizard_walker_items', $elements, $args ), $max_depth, $args );
	}

	/**
	 * recursively set the keep flag if within specified level/depth
	 */
	function _cmw_set_keep_kids( &$structure, $itemId, $topLevel, $bottomLevel ){
		$ct = count( $structure[ $itemId ]['kids'] );
		for( $i = 0; $i < $ct; $i++ ){
			$j = $structure[ $itemId ]['kids'][ $i ];
			if( $structure[ $j ]['level'] <= $bottomLevel ){
				$structure[ $j ]['keep'] = $structure[ $j ]['level'] >= $topLevel;
				if( $structure[ $j ]['keep'] ){
					$structure[0]['keepCount']++;
				}
			}
			if( $structure[ $j ]['level'] < $bottomLevel ){
				$this->_cmw_set_keep_kids( $structure, $j, $topLevel, $bottomLevel );
			}
		}
	}

} //end Custom_Menu_Wizard_Walker class

/**
 * Custom Menu Wizard Widget class
 */
 class Custom_Menu_Wizard_Widget extends WP_Widget {

	var $_cmw_switches = array(
		'hide_title' => 0,
		'contains_current' => 0, //v2.0.0 added
		'depth_rel_current' => 0, //v2.0.0 added
		'fallback_no_ancestor' => 0, //v1.1.0 added
		'fallback_include_parent' => 0, //v1.1.0 added
		'fallback_include_parent_siblings' => 0, //v1.1.0 added
		'fallback_no_children' => 0, //v1.2.0 added
		'fallback_nc_include_parent' => 0, //v1.2.0 added
		'fallback_nc_include_parent_siblings' => 0, //v1.2.0 added
		'flat_output' => 0,
		'include_parent' => 0,
		'include_parent_siblings' => 0, //v1.1.0 added
		'include_ancestors' => 0,
		'hide_empty' => 0, //v1.1.0: this now only has relevance prior to WP v3.6
		'title_from_parent' => 0,
		'title_from_current' => 0, //v1.2.0 added
		'ol_root' => 0,
		'ol_sub' => 0,
		//field section toggles...
		'fs_filter' => 0,
		'fs_fallbacks' => 1, //v1.2.0 added
		'fs_output' => 1,
		'fs_container' => 1,
		'fs_classes' => 1,
		'fs_links' => 1
		);
	var $_cmw_strings = array(
		'title' => '',
		'items' => '', //v2.0.0 added
		'container' => 'div',
		'container_id' => '',
		'container_class' => '',
		'menu_class' => 'menu-widget',
		'widget_class' => ''
		);
	var $_cmw_html = array(
		'before' => '',
		'after' => '',
		'link_before' => '',
		'link_after' => ''
		);
	var $_cmw_integers = array(
		'depth' => 0,
		'filter' => -1, //v2.0.0 changed from switch
		'filter_item' => -2, //v1.1.0 changed from 0
		'menu' => 0,
		'start_level' => 1
		);

	//v1.2.1 holds information determined by the walker...
	var $_cmw_walker = array();

	/**
	 * class constructor
	 */
	function __construct() {
		parent::__construct(
			'custom-menu-wizard',
			'Custom Menu Wizard',
			array(
				'classname' => 'widget_custom_menu_wizard',
				'description' => __('Add a custom menu, or part of one, as a widget')
			)
		);
	}

	/**
	 * v1.2.1 stores any walker-determined information back into the widget instance
	 * gets run by the walker, on the filtered array of menu items, just before running parent::walk()
	 * only gets run *if* there are menu items found
	 * 
	 * @param array $items Filtered menu items
	 * @param object $args
	 * @return array Menu items
	 */
	function cmw_filter_walker_items($items, $args){
		if( !empty( $args->_custom_menu_wizard['_walker'] ) ){
			$this->_cmw_walker = $args->_custom_menu_wizard['_walker'];
		}
		return $items;
	}

	/**
	 * this (filter: wp_nav_menu) merely removes itself from the filters and returns an empty string
	 * it gets added by the cmw_filter_check_for_no_items method below, and only
	 * ever gets run when hide_empty is set on the widget instance
	 * 
	 * v1.1.0  As of WP v3.6 this method becomes superfluous because wp_nav_menu() has had code added to immediately
	 *         cop out (return false) if the output from wp_nav_menu_{$menu->slug}_items filter(s) is empty.
	 *         However, it stays in so as to cope with versions < 3.6
	 * 
	 * @param string $nav_menu HTML for the menu
	 * @param object $args
	 * @return string HTML for the menu
	 */
	function cmw_filter_no_output_when_empty($nav_menu, $args){
		remove_filter( 'wp_nav_menu', array( $this, 'cmw_filter_no_output_when_empty' ), 65532, 2 );
		return empty( $args->_custom_menu_wizard ) ? $nav_menu : '';
	}

	/**
	 * this gets run (filter: wp_nav_menu_{$menu->slug}_items) if hide_empty is set
	 * if $items is empty then add a wp_nav_menu filter to do the actual return of an empty string
	 * it gets run before the wp_nav_menu filter, but it gets the $items array whereas the wp_nav_menu filter does not
	 * it gets added by $this->widget() before wp_nav_menu() is called, and removed immediately after wp_nav_menu() returns
	 * 
	 * v1.1.0  As of WP v3.6 this method becomes superfluous because wp_nav_menu() has had code added to immediately
	 *         cop out (return false) if the output from wp_nav_menu_{$menu->slug}_items filter(s) is empty.
	 *         However, it stays in so as to cope with versions < 3.6
	 * 
	 * @param array $items Menu items
	 * @param object $args
	 * @return array Menu items 
	 */
	function cmw_filter_check_for_no_items($items, $args){
		if( !empty( $args->_custom_menu_wizard ) && empty( $items ) ){
			add_filter( 'wp_nav_menu', array( $this, 'cmw_filter_no_output_when_empty' ), 65532, 2 );
		}
		return $items;
	}

	/**
	 * produces the widget HTML at the front end
	 * 
	 * @filters : custom_menu_wizard_nav_params           array of params that will be sent to wp_nav_menu()
	 * 
	 * @param object $args Widget arguments
	 * @param array $instance Configuration for this widget instance
	 */
	function widget($args, $instance) {
		extract( $args, EXTR_SKIP );

		//switches...
		foreach( $this->_cmw_switches as $k=>$v ){
			$instance[ $k ] = !empty( $instance[ $k ] );
		}
		//integers...
		foreach( $this->_cmw_integers as $k=>$v ){
			$instance[ $k ] = max( $v, intval( $instance[ $k ] ) );
		}
		//strings...
		foreach( $this->_cmw_strings as $k=>$v ){
			$instance[ $k ] = empty( $instance[ $k ] ) ? $v : trim( $instance[ $k ] );
		}
		//html strings...
		foreach( $this->_cmw_html as $k=>$v ){
			$instance[ $k ] = empty( $instance[ $k ] ) ? $v : trim( $instance[ $k ] );
		}

		//v1.1.0  As of WP v3.6, wp_nav_menu() automatically prevents any HTML output if there are no items...
		$instance['hide_empty'] = $instance['hide_empty'] && $this->_pre_3point6();

		//fetch menu...
		if( !empty($instance['menu'] ) ){
			$menu = wp_get_nav_menu_object( $instance['menu'] );

			//no menu, no output...
			if ( !empty( $menu ) ){

				if( !empty( $instance['widget_class'] ) ){
					//$before_widget is usually just a DIV start-tag, with an id and a class; if it
					//gets more complicated than that then this may not work as expected...
					if( preg_match( '/^<[^>]+?class=["\']/', $before_widget ) > 0 ){
						$before_widget = preg_replace( '/(class=["\'])/', '$1' . $instance['widget_class'] . ' ', $before_widget, 1 );
					}else{
						$before_widget = preg_replace( '/^(<\w+)(\s|>)/', '$1 class="' . $instance['widget_class'] . '"$2', $before_widget );
					}
				}
				
				if( !empty( $instance['container_class'] ) ){
					$instance['container_class'] = "menu-{$menu->slug}-container {$instance['container_class']}";
				}
				
				$instance['menu_class'] = preg_split( '/\s+/', $instance['menu_class'], -1, PREG_SPLIT_NO_EMPTY );
				if( $instance['fallback_no_ancestor'] || $instance['fallback_no_children'] ){
					//v1.2.1 add a cmw-fellback-maybe class to the menu and we'll remove or replace it later...
					$instance['menu_class'][] = 'cmw-fellback-maybe';
				}
				$instance['menu_class'] = implode( ' ', $instance['menu_class'] );

				$walker = new Custom_Menu_Wizard_Walker;
				$params = array(
					'menu' => $menu,
					'container' => $instance['container'] == 'none' ? false : $instance['container'],
					'container_id' => $instance['container_id'],
					'menu_class' => $instance['menu_class'],
					'echo' => false,
					'fallback_cb' => false,
					'before' => $instance['before'],
					'after' => $instance['after'],
					'link_before' => $instance['link_before'],
					'link_after' => $instance['link_after'],
					'depth' => empty( $instance['flat_output'] ) ? $instance['depth'] : -1,
					'walker' =>$walker,
					//widget specific stuff...
					'_custom_menu_wizard' => array(
						'filter' => $instance['filter'],
						'filter_item' => $instance['filter_item'],
						'fallback_no_ancestor' => $instance['fallback_no_ancestor'], //v1.1.0
						'fallback_include_parent' => $instance['fallback_include_parent'], //v1.1.0
						'fallback_include_parent_siblings' => $instance['fallback_include_parent_siblings'], //v1.1.0
						'fallback_no_children' => $instance['fallback_no_children'], //v1.2.0
						'fallback_nc_include_parent' => $instance['fallback_nc_include_parent'], //v1.2.0
						'fallback_nc_include_parent_siblings' => $instance['fallback_nc_include_parent_siblings'], //v1.2.0
						'include_parent' => $instance['include_parent'],
						'include_parent_siblings' => $instance['include_parent_siblings'], //v1.1.0
						'include_ancestors' => $instance['include_ancestors'],
						'title_from_parent' => $instance['title_from_parent'],
						'title_from_current' => $instance['title_from_current'], //v1.2.0
						'ol_root' => $instance['ol_root'],
						'ol_sub' => $instance['ol_sub'],
						'flat_output' => $instance['flat_output'],
						'start_level' => $instance['start_level'],
						'depth' => $instance['depth'],
						'depth_rel_current' => $instance['depth_rel_current'], //v2.0.0
						'contains_current' => $instance['contains_current'], //v2.0.0
						'items' => $instance['items'], //v2.0.0
						//v1.2.1 this is for the walker's use... 
						'_walker' => array()
						)
					);
				if( $instance['ol_root'] ){
					$params['items_wrap'] = '<ol id="%1$s" class="%2$s">%3$s</ol>';
				}
				if( !empty( $instance['container_class'] ) ){
					$params['container_class'] = $instance['container_class'];
				}

				add_filter('custom_menu_wizard_walker_items', array( $this, 'cmw_filter_walker_items' ), 10, 2);
				if( $instance['hide_empty'] ){
					add_filter( "wp_nav_menu_{$menu->slug}_items", array( $this, 'cmw_filter_check_for_no_items' ), 65532, 2 );
				}

				//NB: wp_nav_menu() is in wp-includes/nav-menu-template.php
				$out = wp_nav_menu( apply_filters( 'custom_menu_wizard_nav_params', $params ) );

				remove_filter('custom_menu_wizard_walker_items', array( $this, 'cmw_filter_walker_items' ), 10, 2);
				if( $instance['hide_empty'] ){
					remove_filter( "wp_nav_menu_{$menu->slug}_items", array( $this, 'cmw_filter_check_for_no_items' ), 65532, 2 );
				}

				//only put something out if there is something to put out...
				if( !empty( $out ) ){

					//title from : 'from parent' has priority over 'from current'...
					//note that 'parent' is whatever you are getting the children of and therefore doesn't apply to a ShowAll, whereas
					//'current' is the current menu item (as determined by WP); also note that neither parent nor current actually has
					//to be present in the results
					if( $instance['title_from_parent'] && !empty( $this->_cmw_walker['parent_title'] ) ){
						$title = $this->_cmw_walker['parent_title'];
					}
					if( empty( $title ) && $instance['title_from_current'] && !empty( $this->_cmw_walker['current_title'] ) ){
						$title = $this->_cmw_walker['current_title'];
					}
					if( empty( $title ) ){
						$title = $instance['hide_title'] ? '' : $instance['title'];
					}

					//remove/replace the cmw-fellback-maybe class...
					$out = str_replace(
						'cmw-fellback-maybe',
						empty( $this->_cmw_walker['fellback'] ) ? '' : 'cmw-fellback-' . $this->_cmw_walker['fellback'],
						$out );

					echo $before_widget;
					if ( !empty($title) ){
						echo $before_title . apply_filters('widget_title', $title, $instance, $this->id_base) . $after_title;
					}
					echo $out . $after_widget;
				}
			}
		}
	}

	/**
	 * updates the widget settings sent from the backend admin
	 */
	function update( $new_instance, $old_instance ) {
		$instance = $old_instance;

		//switches...
		foreach( $this->_cmw_switches as $k=>$v ){
			$instance[ $k ] = empty( $new_instance[ $k ] ) ? 0 : 1;
		}
		//integers...
		foreach( $this->_cmw_integers as $k=>$v ){
			$instance[ $k ] = isset( $new_instance[ $k ]) ? max( $v, intval( $new_instance[ $k ] ) ) : $v;
		}
		//strings...
		foreach( $this->_cmw_strings as $k=>$v ){
			$instance[ $k ] = isset( $new_instance[ $k ] ) ? strip_tags( trim( $new_instance[ $k ] ) ) : $v;
		}
		//html strings...
		foreach( $this->_cmw_html as $k=>$v ){
			$instance[ $k ] = isset( $new_instance[ $k ] ) ? trim( $new_instance[ $k ] ) : $v;
		}
		//items special case...
		if( !empty( $instance['items'] ) ){
			$sep = preg_match( '/(^\d+$|,)/', $instance['items'] ) > 0 ? ',' : ' ';
			$a = array();
			foreach( preg_split('/[,\s]+/', $instance['items'], -1, PREG_SPLIT_NO_EMPTY ) as $v ){
				$i = intval( $v );
				if( $i > 0 ){
					$a[] = $i;
				}
			}
			$instance['items'] = implode( $sep, $a );
		}

		return $instance;
	}

	/**
	 * produces the backend admin form(s)
	 */
	function form( $instance ) {

		//switches...
		foreach( $this->_cmw_switches as $k=>$v ){
			$instance[ $k ] = isset( $instance[ $k ] ) ? !empty( $instance[ $k ] ) : !empty( $v );
		}
		//integers...
		foreach( $this->_cmw_integers as $k=>$v ){
			$instance[ $k ] = isset( $instance[ $k ]) ? max( $v, intval( $instance[ $k ] ) ) : max($v, 0);
		}
		//strings...
		foreach( $this->_cmw_strings as $k=>$v ){
			$instance[ $k ] = isset( $instance[ $k ] ) ? esc_attr( trim( $instance[ $k ] ) ) : $v;
		}
		//html strings...
		foreach( $this->_cmw_html as $k=>$v ){
			$instance[ $k ] = isset( $instance[ $k ] ) ? esc_html( trim( $instance[ $k ] ) ) : $v;
		}

		//get menus...
		$menus = wp_get_nav_menus( array( 'orderby' => 'name' ) );
		$noitems = true;
		if( !empty( $menus ) ){
			foreach( $menus as $i=>$menu ){
				$menus[ $i ]->_items = wp_get_nav_menu_items( $menu->term_id );
				if( !empty( $menus[ $i ]->_items ) ){
					$noitems = false;
				}
			}
		}

		//if no populated menus exist, suggest the user go create one...
		if( $noitems ){
			echo '<p>'. sprintf( __('No populated menus have been created yet. <a href="%s">Create one</a>.'), admin_url('nav-menus.php') ) .'</p>';
			return;
		}

?>
	<div class="widget-<?php echo $this->id_base; ?>-onchange"
			data-cmw-dialog-title='<?php _e('Selected Menu : '); ?>'
			data-cmw-dialog-prompt='<?php _e('Click an item to toggle &quot;Current Menu Item&quot;'); ?>'
			data-cmw-dialog-output='<?php _e('Basic Output'); ?>'
			data-cmw-dialog-fallback='<?php _e('Fallback invoked'); ?>'
			data-cmw-dialog-trigger='#<?php echo $this->get_field_id('filter_item'); ?>'
			data-cmw-dialog-id='<?php echo $this->get_field_id('dialog'); ?>'>
<?php

		/**
		 * permanently visible section : Title (with Hide) and Menu
		 */
?>
		<p>
			<label for="<?php echo $this->get_field_id('title'); ?>"><?php _e('Title:') ?></label>
			<label class="alignright">
				<input id="<?php echo $this->get_field_id('hide_title'); ?>" name="<?php echo $this->get_field_name('hide_title'); ?>"
					type="checkbox" value="1" <?php checked( $instance['hide_title'] ); ?> />
				<?php _e('Hide'); ?></label>
			<input id="<?php echo $this->get_field_id('title'); ?>" class="widefat" name="<?php echo $this->get_field_name('title'); ?>"
				type="text" value="<?php echo $instance['title']; ?>" />
			<small><em><?php _e('Title can be set, but need not be displayed'); ?></em></small>
		</p>

		<p>
			<small class="cmw-toggle-assist">
				<a class="widget-<?php echo $this->id_base; ?>-toggle-assist" href="#"><?php _e('assist'); ?></a>
			</small>
			<label for="<?php echo $this->get_field_id('menu'); ?>"><?php _e('Select Menu:'); ?></label>
			<select id="<?php echo $this->get_field_id('menu'); ?>"
					class="widget-<?php echo $this->id_base; ?>-selectmenu widget-<?php echo $this->id_base; ?>-listen"
					name="<?php echo $this->get_field_name('menu'); ?>">
<?php
		foreach( $menus as $i=>$menu ){
			if( !empty( $menu->_items ) ){
?>
				<option <?php selected($instance['menu'], $menu->term_id); ?> value="<?php echo $menu->term_id; ?>"><?php echo $menu->name; ?></option>
<?php
			}
		}
?>
			</select>
		</p>

<?php
		/**
		 * start collapsible section : 'Filter'
		 */
		$this->_open_a_field_section($instance, 'Filter', 'fs_filter');
?>
		<p>
			<small class="cmw-toggle-assist">
				<a class="widget-<?php echo $this->id_base; ?>-toggle-assist" href="#"><?php _e('assist'); ?></a>
			</small>
			<label>
				<input id="<?php echo $this->get_field_id('filter'); ?>_0"
					class="widget-<?php echo $this->id_base; ?>-showall widget-<?php echo $this->id_base; ?>-listen"
					name="<?php echo $this->get_field_name('filter'); ?>" type="radio" value="0" <?php checked($instance['filter'], 0); ?> />
				<?php _e('Show all'); ?></label>
			<br /><label>
				<input id="<?php echo $this->get_field_id('filter'); ?>_1" class="widget-<?php echo $this->id_base; ?>-listen"
					name="<?php echo $this->get_field_name('filter'); ?>" type="radio" value="1" <?php checked($instance['filter'], 1); ?> />
				<?php _e('Children of:'); ?></label>
			<select id="<?php echo $this->get_field_id('filter_item'); ?>"
					class="widget-<?php echo $this->id_base; ?>-childrenof widget-<?php echo $this->id_base; ?>-listen"
					name="<?php echo $this->get_field_name('filter_item'); ?>">
				<option value="0" <?php selected( $instance['filter_item'], 0 ); ?>><?php _e('Current Item'); ?></option>
				<option value="-2" <?php selected( $instance['filter_item'], -2 ); ?>><?php _e('Current Root Item'); ?></option>
				<option value="-1" <?php selected( $instance['filter_item'], -1 ); ?>><?php _e('Current Parent Item'); ?></option>
<?php
//v1.1.0
// IE is a pita when it comes to SELECTs because it ignores any styling on OPTGROUPs and OPTIONs, so I'm changing the way
// that this SELECT works by introducing a copy from which the javascript can pick the relevant OPTGROUP
		$menuOptions = array(); 

		$maxlevel = 1;
		foreach( $menus as $i=>$menu ){
			//as of v1.2.0 : no items, no optgroup!
			if( !empty( $menu->_items ) ){
				$grpdata = array();
				$itemindents = array( '0' => array( 'level'=>0, 'grpkey'=>'' ) );
				$menuGrpOpts = array();
				foreach( $menu->_items as $item ){
					//exclude orpans!
					if( isset($itemindents[ $item->menu_item_parent ])){
						$title = apply_filters( 'the_title', $item->title, $item->ID );
						$level = $itemindents[ $item->menu_item_parent ]['level'] + 1;
						$grpkey = $item->ID . '|' . $title;
						$grpdata[ $grpkey ] = array();
						if( !empty( $itemindents[ $item->menu_item_parent ]['grpkey'] )){
							$grpdata[ $itemindents[ $item->menu_item_parent ]['grpkey'] ][ $grpkey ] = array();
						}

						$itemindents[ $item->ID ] = array( 'level'=>$level, 'grpkey'=>$grpkey );
						$maxlevel = max( $maxlevel, $level );
						//v2.0.0 indents changed to non-breaking spaces...
						$menuGrpOpts[] = '<option value="' . $item->ID . '" ' .
							selected( $instance['filter_item'], $item->ID, false ) . '>' .
							str_repeat( '&nbsp;', ($level - 1) * 3 ) . $title . '</option>';
					}
				}

				//the menu had items, but they might all have been orphans?...
				if( !empty( $menuGrpOpts ) ){
					foreach( array_reverse( $grpdata ) as $k=>$v ){
						if( empty( $v ) ){
							$grpdata[ $k ] = false;
						}else{
							foreach( $v as $n=>$j ){
								$grpdata[ $k ][ $n ] = $grpdata[ $n ];
								unset( $grpdata[ $n ] );
							}
						}
					}
					$grpdata = json_encode( $grpdata );
					$menuOptions[] = '<optgroup label="' . $menu->name . '" data-cmw-optgroup-index="' . $i . '" data-cmw-items="' . esc_attr($grpdata) . '">';
					$menuOptions[] = implode("\n", $menuGrpOpts);
					$menuOptions[] = '</optgroup>';
				}
				unset( $menuGrpOpts, $grpdata, $itemindents );
			}
		}
		$menuOptions = implode("\n", $menuOptions);
		echo $menuOptions;
?>		
			</select>
			<br /><label>
				<input id="<?php echo $this->get_field_id('filter'); ?>_2" 
					class="widget-<?php echo $this->id_base; ?>-showspecific widget-<?php echo $this->id_base; ?>-listen"
					name="<?php echo $this->get_field_name('filter'); ?>" type="radio" value="-1" <?php checked($instance['filter'], -1); ?> />
				<?php _e('Items:'); ?></label>
			<input id="<?php echo $this->get_field_id('items'); ?>" class="widget-<?php echo $this->id_base; ?>-setitems"
				name="<?php echo $this->get_field_name('items'); ?>" type="text" value="<?php echo $instance['items']; ?>" />

			<select id="<?php echo $this->get_field_id('filter_item_ignore'); ?>" disabled="disabled"
					class='cmw-off-the-page' name="<?php echo $this->get_field_name('filter_item_ignore'); ?>">
			<?php echo $menuOptions; ?>
			</select>
		</p>

		<p class="widget-<?php echo $this->id_base; ?>-disableif-ss">
			<label for="<?php echo $this->get_field_id('start_level'); ?>"><?php _e('Starting Level:'); ?></label>
			<select id="<?php echo $this->get_field_id('start_level'); ?>" name="<?php echo $this->get_field_name('start_level'); ?>">
<?php
		$j = max( $maxlevel, $instance['start_level'] );
		for( $i = 1; $i <= $j; $i++ ){
?>
				<option value="<?php echo $i; ?>" <?php selected($instance['start_level'], $i); ?>><?php echo $i; ?></option>
<?php
		}
?>
			</select>
			<br /><small><em><?php _e('Level to start testing items for inclusion'); ?></em></small>
		</p>

		<p class="widget-<?php echo $this->id_base; ?>-disableif-ss">
			<label for="<?php echo $this->get_field_id('depth'); ?>"><?php _e('For Depth:'); ?></label>
			<select id="<?php echo $this->get_field_id('depth'); ?>" name="<?php echo $this->get_field_name('depth'); ?>">
				<option value="0" <?php selected( $instance['depth'], 0 ); ?>><?php _e('unlimited'); ?></option>
<?php
		$j = max( $j, $instance['depth'] );
		for( $i = 1; $i <= $j; $i++ ){
?>
				<option value="<?php echo $i; ?>" <?php selected( $instance['depth'], $i ); ?>><?php echo $i; ?> <?php _e($i > 1 ? 'levels' : 'level'); ?></option>
<?php
		}
?>
			</select>
			<br /><small><em><?php _e('Relative to first Filter item found, <strong>unless</strong>&hellip;'); ?></em></small>
			<br /><label>
				<input id="<?php echo $this->get_field_id('depth_rel_current'); ?>"
					name="<?php echo $this->get_field_name('depth_rel_current'); ?>" type="checkbox" value="1"
					<?php checked($instance['depth_rel_current']); ?> />
				<?php _e('Relative to &quot;Current&quot; Item <small><em>(if found)</em></small>'); ?></label>
		</p>
<?php $this->_close_a_field_section(); ?>

<?php
		/**
		 * v1.2.0 start collapsible section : 'Fallbacks'
		 */
		$this->_open_a_field_section($instance, 'Fallbacks', 'fs_fallbacks');
?>
		<p class="clear widget-<?php echo $this->id_base; ?>-disableifnot-rp">
			<small class="cmw-toggle-assist">
				<a class="widget-<?php echo $this->id_base; ?>-toggle-assist" href="#"><?php _e('assist'); ?></a>
			</small>
			<small><strong><?php _e( 'If &quot;Children of&quot; is <em>Current Root / Parent Item</em>, and no ancestor exists' ); ?> :</strong></small>
			<br /><label>
				<input id="<?php echo $this->get_field_id('fallback_no_ancestor'); ?>"
					name="<?php echo $this->get_field_name('fallback_no_ancestor'); ?>" type="checkbox" value="1"
					<?php checked($instance['fallback_no_ancestor']); ?> />
				<?php _e('Switch to Current Item, and'); ?></label>
			<br /><label class="cmw-pad-left-1">
				<input id="<?php echo $this->get_field_id('fallback_include_parent'); ?>"
					name="<?php echo $this->get_field_name('fallback_include_parent'); ?>" type="checkbox" value="1"
					<?php checked($instance['fallback_include_parent']); ?> />
				<?php _e('Include Parent...'); ?> </label>
			<label>
				<input id="<?php echo $this->get_field_id('fallback_include_parent_siblings'); ?>"
					name="<?php echo $this->get_field_name('fallback_include_parent_siblings'); ?>" type="checkbox" value="1"
					<?php checked($instance['fallback_include_parent_siblings']); ?> />
				<?php _e('with Siblings'); ?></label>
		</p>

		<p class="widget-<?php echo $this->id_base; ?>-disableifnot-ci">
			<small><strong><?php _e( 'If &quot;Children of&quot; is <em>Current Item</em>, and current item has no children' ); ?> :</strong></small>
			<br /><label>
				<input id="<?php echo $this->get_field_id('fallback_no_children'); ?>"
					name="<?php echo $this->get_field_name('fallback_no_children'); ?>" type="checkbox" value="1"
					<?php checked($instance['fallback_no_children']); ?> />
				<?php _e('Switch to Current Parent Item, and'); ?></label>
			<br /><label class="cmw-pad-left-1">
				<input id="<?php echo $this->get_field_id('fallback_nc_include_parent'); ?>"
					name="<?php echo $this->get_field_name('fallback_nc_include_parent'); ?>" type="checkbox" value="1"
					<?php checked($instance['fallback_nc_include_parent']); ?> />
				<?php _e('Include Parent...'); ?> </label>
			<label>
				<input id="<?php echo $this->get_field_id('fallback_nc_include_parent_siblings'); ?>"
					name="<?php echo $this->get_field_name('fallback_nc_include_parent_siblings'); ?>" type="checkbox" value="1"
					<?php checked($instance['fallback_nc_include_parent_siblings']); ?> />
				<?php _e('with Siblings'); ?></label>
		</p>


<?php $this->_close_a_field_section(); ?>

<?php
		/**
		 * start collapsible section : 'Output'
		 */
		$this->_open_a_field_section($instance, 'Output', 'fs_output');
?>
		<p>
			<small class="cmw-toggle-assist">
				<a class="widget-<?php echo $this->id_base; ?>-toggle-assist" href="#"><?php _e('assist'); ?></a>
			</small>
			<label>
				<input id="<?php echo $this->get_field_id('flat_output'); ?>_0" name="<?php echo $this->get_field_name('flat_output'); ?>"
					type="radio" value="0" <?php checked(!$instance['flat_output']); ?> />
				<?php _e('Hierarchical'); ?></label>
			&nbsp;<label>
				<input id="<?php echo $this->get_field_id('flat_output'); ?>_1" name="<?php echo $this->get_field_name('flat_output'); ?>"
					type="radio" value="1" <?php checked($instance['flat_output']); ?> />
				<?php _e('Flat'); ?></label>
		</p>

		<p>
			<label>
				<input id="<?php echo $this->get_field_id('contains_current'); ?>"
					name="<?php echo $this->get_field_name('contains_current'); ?>" type="checkbox"
					value="1" <?php checked($instance['contains_current']); ?> />
				<?php _e('Must Contain &quot;Current&quot; Item'); ?></label>
			<br /><small><em><?php _e('Checks both Filtered and Included items'); ?></em></small>
		</p>

		<p class="widget-<?php echo $this->id_base; ?>-disableif">
			<label>
				<input id="<?php echo $this->get_field_id('include_parent'); ?>"
					name="<?php echo $this->get_field_name('include_parent'); ?>" type="checkbox"
					value="1" <?php checked($instance['include_parent']); ?> />
				<?php _e('Include Parent...'); ?> </label>
			<label>
				<input id="<?php echo $this->get_field_id('include_parent_siblings'); ?>"
					name="<?php echo $this->get_field_name('include_parent_siblings'); ?>" type="checkbox"
					value="1" <?php checked($instance['include_parent_siblings']); ?> />
				<?php _e('with Siblings'); ?></label>
			<br /><label>
				<input id="<?php echo $this->get_field_id('include_ancestors'); ?>"
					name="<?php echo $this->get_field_name('include_ancestors'); ?>" type="checkbox"
					value="1" <?php checked($instance['include_ancestors']); ?> />
				<?php _e('Include Ancestors'); ?></label>
			<br /><label>
				<input id="<?php echo $this->get_field_id('title_from_parent'); ?>"
					name="<?php echo $this->get_field_name('title_from_parent'); ?>" type="checkbox"
					value="1" <?php checked($instance['title_from_parent']); ?> />
				<?php _e('Title from Parent'); ?></label>
			<br /><small><em><?php _e('Only if the &quot;Children of&quot; Filter returns items'); ?></em></small>
		</p>

		<p>
			<label>
				<input id="<?php echo $this->get_field_id('title_from_current'); ?>"
					name="<?php echo $this->get_field_name('title_from_current'); ?>" type="checkbox"
					value="1" <?php checked($instance['title_from_current']); ?> />
				<?php _e('Title from &quot;Current&quot; Item'); ?></label>
			<br /><small><em><?php _e('Lower priority than &quot;Title from Parent&quot;'); ?></em></small>
		</p>

		<p>
			<?php _e('Change UL to OL:'); ?>
			<br /><label>
				<input id="<?php echo $this->get_field_id('ol_root'); ?>" name="<?php echo $this->get_field_name('ol_root'); ?>"
					type="checkbox" value="1" <?php checked($instance['ol_root']); ?> />
				<?php _e('Top Level'); ?></label>
			&nbsp;<label>
				<input id="<?php echo $this->get_field_id('ol_sub'); ?>" name="<?php echo $this->get_field_name('ol_sub'); ?>"
					type="checkbox" value="1" <?php checked($instance['ol_sub']); ?> />
				<?php _e('Sub-Levels'); ?></label>
		</p>

<?php
		//v1.1.0  As of WP v3.6, wp_nav_menu() automatically cops out (without outputting any HTML) if there are no items,
		//        so the hide_empty option becomes superfluous; however, I'll keep the previous setting (if there was one)
		//        in case of reversion to an earlier version of WP...
		if( $this->_pre_3point6() ){
?>
		<p>
			<label>
				<input id="<?php echo $this->get_field_id('hide_empty'); ?>" name="<?php echo $this->get_field_name('hide_empty'); ?>"
					type="checkbox" value="1" <?php checked($instance['hide_empty']); ?> />
				<?php _e('Hide Widget if Empty'); ?></label>
			<br /><small><em><?php _e('Prevents any output when no items are found'); ?></em></small>
		</p>
<?php }else{ ?>
		<input id="<?php echo $this->get_field_id('hide_empty'); ?>" name="<?php echo $this->get_field_name('hide_empty'); ?>"
			type="hidden" value="<?php echo $instance['hide_empty'] ? '1' : ''; ?>" />
<?php } ?>

<?php $this->_close_a_field_section(); ?>

<?php
		/**
		 * start collapsible section : 'Container'
		 */
		$this->_open_a_field_section($instance, 'Container', 'fs_container');
?>
			<p>
				<label for="<?php echo $this->get_field_id('container'); ?>"><?php _e('Element:') ?></label>
				<input id="<?php echo $this->get_field_id('container'); ?>" name="<?php echo $this->get_field_name('container'); ?>"
					type="text" value="<?php echo $instance['container']; ?>" />
				<br /><small><em><?php _e( 'Eg. div or nav; leave empty for no container' ); ?></em></small>
			</p>
			<p>
				<label for="<?php echo $this->get_field_id('container_id'); ?>"><?php _e('Unique ID:') ?></label>
				<input id="<?php echo $this->get_field_id('container_id'); ?>" name="<?php echo $this->get_field_name('container_id'); ?>"
					type="text" value="<?php echo $instance['container_id']; ?>" />
				<br /><small><em><?php _e( 'An optional ID for the container' ); ?></em></small>
			</p>
			<p>
				<label for="<?php echo $this->get_field_id('container_class'); ?>"><?php _e('Class:') ?></label>
				<input id="<?php echo $this->get_field_id('container_class'); ?>" name="<?php echo $this->get_field_name('container_class'); ?>"
					type="text" value="<?php echo $instance['container_class']; ?>" />
				<br /><small><em><?php _e( 'Extra class for the container' ); ?></em></small>
			</p>
<?php $this->_close_a_field_section(); ?>

<?php
		/**
		 * start collapsible section : 'Classes'
		 */
		$this->_open_a_field_section($instance, 'Classes', 'fs_classes');
?>
		<p>
			<label for="<?php echo $this->get_field_id('menu_class'); ?>"><?php _e('Menu Class:') ?></label>
			<input id="<?php echo $this->get_field_id('menu_class'); ?>" name="<?php echo $this->get_field_name('menu_class'); ?>"
				type="text" value="<?php echo $instance['menu_class']; ?>" />
			<br /><small><em><?php _e( 'Class for the list element forming the menu' ); ?></em></small>
		</p>
		<p>
			<label for="<?php echo $this->get_field_id('widget_class'); ?>"><?php _e('Widget Class:') ?></label>
			<input id="<?php echo $this->get_field_id('widget_class'); ?>" name="<?php echo $this->get_field_name('widget_class'); ?>"
				type="text" value="<?php echo $instance['widget_class']; ?>" />
			<br /><small><em><?php _e( 'Extra class for the widget itself' ); ?></em></small>
		</p>
<?php $this->_close_a_field_section(); ?>

<?php
		/**
		 * start collapsible section : 'Links'
		 */
		$this->_open_a_field_section($instance, 'Links', 'fs_links');
?>
		<p>
			<label for="<?php echo $this->get_field_id('before'); ?>"><?php _e('Before the Link:') ?></label>
			<input id="<?php echo $this->get_field_id('before'); ?>" class="widefat" name="<?php echo $this->get_field_name('before'); ?>"
				type="text" value="<?php echo $instance['before']; ?>" />
			<small><em><?php _e( htmlspecialchars('Text/HTML to go before the <a> of the link') ); ?></em></small>
		</p>
		<p>
			<label for="<?php echo $this->get_field_id('after'); ?>"><?php _e('After the Link:') ?></label>
			<input id="<?php echo $this->get_field_id('after'); ?>" class="widefat" name="<?php echo $this->get_field_name('after'); ?>"
				type="text" value="<?php echo $instance['after']; ?>" />
			<small><em><?php _e( htmlspecialchars('Text/HTML to go after the </a> of the link') ); ?></em></small>
		</p>
		<p>
			<label for="<?php echo $this->get_field_id('link_before'); ?>"><?php _e('Before the Link Text:') ?></label>
			<input id="<?php echo $this->get_field_id('link_before'); ?>" class="widefat" name="<?php echo $this->get_field_name('link_before'); ?>"
				type="text" value="<?php echo $instance['link_before']; ?>" />
			<small><em><?php _e( 'Text/HTML to go before the link text' ); ?></em></small>
		</p>
		<p>
			<label for="<?php echo $this->get_field_id('link_after'); ?>"><?php _e('After the Link Text:') ?></label>
			<input id="<?php echo $this->get_field_id('link_after'); ?>" class="widefat" name="<?php echo $this->get_field_name('link_after'); ?>"
				type="text" value="<?php echo $instance['link_after']; ?>" />
			<small><em><?php _e( 'Text/HTML to go after the link text' ); ?></em></small>
		</p>	
<?php $this->_close_a_field_section(); ?>

	</div>

<?php
	} //end form()

	/**
	 * outputs the HTML to begin a collapsible/expandable group of settings
	 * 
	 * @param array $instance
	 * @param string $text Label
	 * @param string $fname Field name
	 */
	function _open_a_field_section( &$instance, $text, $fname ){
?>
<div class="stuffbox widget-<?php echo $this->id_base; ?>-collapsible-fieldset" title="<?php _e( 'Click to show/hide' ); ?>">
	<input id="<?php echo $this->get_field_id($fname); ?>" class="hidden-field" name="<?php echo $this->get_field_name($fname); ?>"
		type="checkbox" value="1" <?php checked( $instance[$fname] ); ?> />
	<div style="background-image:url(images/arrows.png);" class="<?php echo $instance[$fname] ? 'cmw-collapsed-fieldset' : ''; ?>"></div>
	<h3><?php _e( $text ); ?></h3>
</div>
<div class="<?php echo $instance[$fname] ? 'cmw-start-fieldset-collapsed' : ''; ?>">
<?php
	} //end _open_a_field_section()

	/**
	 * outputs the HTML to close off a collapsible/expandable group of settings
	 */
	function _close_a_field_section(){
?>
</div>
<?php
	} //end _close_a_field_section()
	
	/**
	 * returns true if the version of WP is lower than 3.6 (ie. 3.5* or below)
	 */
	function _pre_3point6(){
		global $wp_version;

		return version_compare( strtolower( $wp_version ), '3.6a', '<' );
	} //end _pre_3point6()

} //end of class

/** 
 * as of v1.2.0
 * shortcode processing for [custom_menu_wizard option="" option="" ...]
 * see wp-includes/widgets.php for the_widget() code
 * Note that hide_empty is set to ON and can not be overridden!
 * 
 * default (ie. no options) is:
 *  - show all
 *  - of first populated menu found (alphabetically)
 *  - from root, for unlimited depth
 *  - as hierarchical nested ULs inside a DIV.widget_custom_menu_wizard.shortcode_custom_menu_wizard
 * 
 * @filters : custom_menu_wizard_shortcode_attributes        array of attributes supplied to the shortcode
 *            custom_menu_wizard_shortcode_settings          array of widget settings derived from the attributes
 *            custom_menu_wizard_shortcode_widget_args       array of the sidebar args used to wrap widgets and their titles (before|after_widget, before|after_title)
 *
 * @param array $atts options supplied to the shortcode
 * @param string $content Within start-end shortcode tags
 * @param string $tag Shortcode tag
 * @return string HTML
 */
function custom_menu_wizard_widget_shortcode($atts, $content, $tag){
	$html = '';
	$ok = false;
	$instance = shortcode_atts( array(
		'title' => '',
		'menu' => 0, // menu id, slug or name
		//determines filter & filter_item ('items' takes precedence over 'children_of' because it's more specific)...
		'children_of' => '', // empty = show all (dep. on 'items'); menu item id or title (caseless), or current|current-item|parent|current-parent|root|current-ancestor
		'items' => '', // v2.0.0 empty = show all (dep. on 'children_of'); comma- or space-separated list of menu item ids (start level and depth don't apply)
		'start_level' => 1,
		'depth' => 0, // 0 = unlimited
		//only if children_of is (parent|current-parent|root|current-ancestor); determines fallback_no_ancestor, fallback_include_parent & fallback_include_parent_siblings...
		'fallback_parent' => 0, // 1 = use current-item; 'parent' = *and* include parent, 'siblings' = *and* include both parent and its siblings
		//only if children_of is (current|current-item); determines fallback_no_children, fallback_nc_include_parent & fallback_nc_include_parent_siblings...
		'fallback_current' => 0, // 1 = use current-parent; 'parent' = *and* include parent (if available), 'siblings' = *and* include both parent (if available) and its siblings
		//switches...
		'flat_output' => 0,
		'contains_current' => 0, // v2.0.0
		//determines include_parent, include_parent_siblings & include_ancestors...
		'include' =>'', //comma|space|hyphen separated list of 'parent', 'siblings', 'ancestors'
		'ol_root' => 0,
		'ol_sub' => 0,
		//determines title_from_parent & title_from_current...
		'title_from' => '', //comma|space|hyphen separated list of 'parent', 'current'
		'depth_rel_current' => 0, // v2.0.0
		//strings...
		'container' => 'div', // a tag : div|nav are WP restrictions, not the widget's; '' =  no container
		'container_id' => '',
		'container_class' => '',
		'menu_class' => 'menu-widget',
		'widget_class' => '',
		//determines before & after...
		'wrap_link' => '', // a tag name (eg. div, p, span, etc)
		//determines link_before & link_after...
		'wrap_link_text' => '' // a tag name (eg. span, em, strong)
		), $atts );

	$instance = apply_filters( 'custom_menu_wizard_shortcode_attributes', $instance );

	if( empty( $instance['menu'] ) ){
		//gonna find the first menu (alphabetically) that has items...
		$menus = wp_get_nav_menus( array( 'orderby' => 'name' ) );
	}else{
		//allow for menu being something other than an id (eg. slug or name), but we need the id for the widget...
		$menus = wp_get_nav_menu_object( $instance['menu'] );
		if( !empty( $menus) ){
			$menus = array( $menus );
		}
	}
	if( !empty( $menus ) ){
		foreach( $menus as $i=>$menu ){
			$items = wp_get_nav_menu_items( $menu->term_id );
			$ok = !empty( $items );
			if( $ok ){
				$instance['menu'] = $menu->term_id;
				break;
			}
		}
	}
	unset( $menus );

	if( $ok ){
		$instance['filter'] = $instance['filter_item'] = 0;
		if( empty( $instance['items'] ) ){
			//children_of => filter & filter_item...
			if( empty( $instance['children_of'] ) ){
				$instance['children_of'] = '';
			}
			switch( $instance['children_of'] ){
				case '':
					break;
				case 'root': case 'current-ancestor':
					--$instance['filter_item']; //ends up as -2
				case 'parent': case 'current-parent':
					--$instance['filter_item']; //ends up as -1
				case 'current': case 'current-item':
					$instance['filter'] = 1;
					break;
				default:
					$instance['filter'] = 1;
					$instance['filter_item'] = strtolower( $instance['children_of'] );
			}
			//if filter_item is non-numeric then it could be the title of a menu item, but we need it to be the menu item's id...
			if( !is_numeric( $instance['filter_item'] ) ){
				foreach( $items as $item ){
					$ok = strtolower( $item->title ) == $instance['filter_item'];
					if( $ok ){
						$instance['filter_item'] = $item->ID;
						break;
					}
				}
			}
		}else{
			$instance['filter'] = -1;
		}
		unset( $instance['children_of'] );
	}

	if( $ok ){
		//fallback_parent => fallback_no_ancestor switch (and extension switches)...
		$instance['fallback_no_ancestor'] = $instance['fallback_include_parent'] = $instance['fallback_include_parent_siblings'] = 0;
		if( $instance['filter_item'] < 0 && !empty( $instance['fallback_parent'] ) ){
			$instance['fallback_no_ancestor'] = 1;
			$i = preg_split( '/[\s,-]+/', strtolower( $instance['fallback_parent'] ), -1, PREG_SPLIT_NO_EMPTY );
			foreach( $i as $j ){
				if( $j == 'parent' ){
					$instance['fallback_include_parent'] = 1;
				}elseif( $j == 'siblings' ){
					$instance['fallback_include_parent_siblings'] = 1;
				}
			}
		}
		//fallback_current => fallback_no_children switch (and extension switches)...
		$instance['fallback_no_children'] = $instance['fallback_nc_include_parent'] = $instance['fallback_nc_include_parent_siblings'] = 0;
		if( $instance['filter'] == 1 && $instance['filter_item'] == 0 && !empty( $instance['fallback_current'] ) ){
			$instance['fallback_no_children'] = 1;
			$i = preg_split( '/[\s,-]+/', strtolower( $instance['fallback_current'] ), -1, PREG_SPLIT_NO_EMPTY );
			foreach( $i as $j ){
				if( $j == 'parent' ){
					$instance['fallback_nc_include_parent'] = 1;
				}elseif( $j == 'siblings' ){
					$instance['fallback_nc_include_parent_siblings'] = 1;
				}
			}
		}
		unset( $instance['fallback_parent'], $instance['fallback_current'] );
		//include => include_* ...
		$instance['include_parent'] = $instance['include_parent_siblings'] = $instance['include_ancestors'] = 0;
		if( $instance['filter'] == 1 && !empty( $instance['include'] ) ){
			$i = preg_split( '/[\s,-]+/', strtolower( $instance['include'] ), -1, PREG_SPLIT_NO_EMPTY );
			foreach( $i as $j ){
				if( $j == 'parent' ){
					$instance['include_parent'] = 1;
				}elseif( $j == 'siblings' ){
					$instance['include_parent_siblings'] = 1;
				}elseif( $j == 'ancestors' ){
					$instance['include_ancestors'] = 1;
				}
			}
		}
		unset( $instance['include'] );
		//title_from => title_from_parent, title_from_current ...
		$instance['title_from_parent'] = $instance['title_from_current'] = 0;
		if( !empty( $instance['title_from'] ) ){
			$i = preg_split( '/[\s,-]+/', strtolower( $instance['title_from'] ), -1, PREG_SPLIT_NO_EMPTY );
			foreach( $i as $j ){
				if( $j == 'parent' ){
					$instance['title_from_parent'] = 1;
				}elseif( $j == 'current' ){
					$instance['title_from_current'] = 1;
				}
			}
		}
		unset( $instance['title_from'] );
		//wrap_link => before & after...
		$instance['before'] = $instance['after'] = '';
		$instance['wrap_link'] = esc_attr( trim( $instance['wrap_link'] ) );
		if( !empty( $instance['wrap_link'] ) ){
			$instance['before'] = '<' . $instance['wrap_link'] . '>';
			$instance['after'] = '</' . $instance['wrap_link'] . '>';
		}
		//wrap_link_text => link_before & link_after...
		$instance['link_before'] = $instance['link_after'] = '';
		$instance['wrap_link_text'] = esc_attr( trim( $instance['wrap_link_text'] ) );
		if( !empty( $instance['wrap_link_text'] ) ){
			$instance['link_before'] = '<' . $instance['wrap_link_text'] . '>';
			$instance['link_after'] = '</' . $instance['wrap_link_text'] . '>';
		}

		//handle widget_class here because we have full control over $before_widget...
		$before_widget_class = array(
			'widget_custom_menu_wizard',
			'shortcode_custom_menu_wizard'
			);
		$instance['widget_class'] = empty( $instance['widget_class'] ) ? '' : esc_attr( trim ( $instance['widget_class'] ) );
		if( !empty( $instance['widget_class'] ) ){
			foreach( explode(' ', $instance['widget_class'] ) as $i ){
				if( !empty( $i ) && !in_array( $i, $before_widget_class ) ){
					$before_widget_class[] = $i;
				}
			} 
		}
		$instance['widget_class'] = '';
		//turn on hide_empty...
		$instance['hide_empty'] = 1;
	}

	if( $ok ){
		//apart from before_title, these are lifted from the_widget()...
		$sidebar_args = array(
			'before_widget' => '<div class="' . implode( ' ', $before_widget_class ) . '">',
			'after_widget' => '</div>',
			'before_title' => '<h2 class="widgettitle">',
			'after_title' => '</h2>'
			);
		ob_start();
		the_widget(
			'Custom_Menu_Wizard_Widget',
			apply_filters('custom_menu_wizard_shortcode_settings', $instance ),
			apply_filters('custom_menu_wizard_shortcode_widget_args', $sidebar_args )
			);
		$html = ob_get_clean();
	}
 	return empty($html) ? '' : $html;
}

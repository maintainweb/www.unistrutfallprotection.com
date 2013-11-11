/* Plugin Name: Custom Menu Wizard
 * Version: 2.0.2
 * Author: Roger Barrett
 * 
 * Script for controlling this widget's options (in Admin -> Widgets)
*/
/*global jQuery, window, document */
/*jslint forin: true, nomen: true, plusplus: true, regexp: true, unparam: true, sloppy: true, white: true */
jQuery(function($){
	var dotPrefix = '.widget-custom-menu-wizard',
			assist = {
				/**
				 * gets a widget form's dialog element as a jQuery object
				 * @param {Object} fm jQuery of the widget form
				 * @return (Object) jQuery of the dialog element
				 */
				getDialog : function(fm){
					return $( '#' + fm.find(dotPrefix + '-onchange').data().cmwDialogId );
				},
				/**
				 * gets the widget form's values
				 * @param {Object} fm jQuery of the widget form
				 * @return {Object} key=>value pairs of the form element values 
				 */
				getSettings : function(fm){
					var settings = {};
					$.each(fm.serializeArray(), function(i, v){
						var name = v.name.replace(/.*\[([^\]]+)\]$/, '$1'),
								val = name !== 'items' && /^-?\d+$/.test(v.value) ? parseInt(v.value, 10) : v.value;
						settings[name] = val;
						if(name === 'items'){
							settings._items_sep = !val || /(^\d+$|,)/.test($.trim(val)) ? ',' : ' ';
							val = $.map(val.split(/[,\s]+/), function(x){
								x = x ? parseInt(x, 10) : 0;
								return isNaN(x) || x < 1 ? null : x;
							});
							settings._items = val.join(settings._items_sep);
						}
					});
					return settings;
				},
				/**
				 * recursively build LI items for the menu structure (kicked off by createMenu())
				 * @param {Object} items Set of menu items with same parent
				 * @param {integer} level Level of parent within the structure (1-based)
				 * @return {string} HTML 
				 */
				buildRecurse : function(items, level, trace){
					var rtn = '', n, i;
					level = (level || 0) + 1;
					trace = trace || '';
					for(n in items){
						i = n.split('|')[0];
						rtn += '<li class="level-' + level + '" data-itemid="' + i + '" data-level="' + level + '" data-trace="' + trace + '">';
						rtn += '<a class="ui-corner-all" href="#"><span class="ui-corner-all" title="#' + i + '">' + n.replace(/^\d+\|/, '');
						rtn += '</span></a><input type="checkbox" value="' + i + '" />';
						if(items[n]){
							rtn += '<ul>' + assist.buildRecurse(items[n], level, i + (trace ? ',' : '') + trace) + '</ul>';
						}
						rtn += '</li>';
					}
					return rtn;
				},
				/**
				 * change handler for an item's checkbox in the menu structure
				 * @this {Element} Input checkbox element
				 * @param {Object} e Event object
				 */
				changeMenu : function(e){
					var self = $(this),
							itemsField = $(self.closest('.ui-dialog-content').data().cmwTriggerChange).closest('form').find(dotPrefix + '-setitems'),
							currVal = $.trim(itemsField.val()),
							sep = !currVal || /(^\d+$|,)/.test(currVal) ? ',' : ' ';
					itemsField.val(
							self.closest('.cmw-demo-themenu').find('input').map(function(){
								return this.checked ? this.value : null;
							}).get().join(sep)
						).trigger('change');
				},
				/**
				 * click handler for an item in the menu structure : sets or clears current menu item and its ancestors
				 * @this {Element} Anchor element clicked on
				 * @param {Object} e Event object
				 * @return {boolean} false 
				 */
				clickMenu : function(e){
					var self = $(this),
							cls = ['current-menu-item', 'current-menu-parent', 'current-menu-ancestor'],
							dialog = self.closest('.ui-dialog-content'),
							themenu = dialog.find('.cmw-demo-themenu'),
							inPath = self.find('span').not('.' + cls[0]).parentsUntil(themenu, 'li'),
							i, n,
							appendCls = function(){
								this.title = this.title + ' ' + n.replace(' ', ' & ').replace(/-/g, ' ');
							};
					themenu.find('.' + cls.join(',.')).removeClass(cls.join(' ')).each(function(){
						this.title = this.title.replace(/\s.*$/, '');
					});
					for(i = 0; i < inPath.length; i++){
						n = i === 1 ? cls.join(' ') : cls[0];
						inPath.eq(i).children('a').find('span').addClass(n).each(appendCls);
						if(cls.length > 1){
							cls.shift();
						}
					}
					//trigger a change in the widget form, causing update() to be run...
					$( dialog.data().cmwTriggerChange ).trigger('change');
					return false;
				},
				/**
				 * click handler for an item in the Basic Output list : triggers a click on the respective menu structure item
				 * @this {Element} Anchor element clicked on
				 * @param {Object} e Event object
				 * @return {boolean} false
				 */
				clickOutput : function(e){
					var indx = this.href.split('#')[1];
					$(this).closest('.ui-dialog-content').find('.cmw-demo-themenu a').eq(indx).not(':has(.current-menu-item)').trigger('click');
					this.blur();
					return false;
				},
				/**
				 * creates a new list of menu items and inserts it into the dialog content in place of any previous one
				 * @param {Object} dialog jQuery object of the dialog
				 * @param {Object} fm jQuery object of the widget form 
				 */
				createMenu : function(dialog, fm){
					var data = dialog.data(),
							themenu = dialog.find('.cmw-demo-themenu'),
							selectmenu = fm.find(dotPrefix + '-selectmenu'),
							menuid = parseInt(selectmenu.val(), 10),
							currentmenu = themenu.find('ul').eq(0),
							mdata = themenu.data(),
							menu;
					if(!currentmenu.length || currentmenu.data('menuid') !== menuid){
						menu = $('<ul>' + assist.buildRecurse( fm.find(dotPrefix + '-childrenof optgroup').data().cmwItems || {} ) + '</ul>');
						currentmenu.remove();
						dialog.dialog('option', 'title', data.cmwTitlePrefix + selectmenu.find('option:selected').text() );
						mdata.maxLevel = 0;
						themenu.append( menu.data('menuid', menuid) )
							.find('a').each(function(i){
								var level = $(this).parent('li').data().level;
								$(this).data('indx', i);
								if(level && level > mdata.maxLevel){
									mdata.maxLevel = level;
								}
							});
					}
				},
				/**
				 * toggles the assist dialog open/closed, creating it if necessary
				 * @this {Element} A -toggle-assist anchor
				 * @param {Object} e Event object
				 * @return {boolean} false
				 */
				init : function(e){
					var self = $(this),
							data = self.closest(dotPrefix + '-onchange').data(),
							dialog = $( '#' + data.cmwDialogId ),
							fm = self.closest('form');
					if(!dialog.length){
						//create it...
						dialog = $('<div/>', {id:data.cmwDialogId}).addClass(dotPrefix.substr(1) + '-dialog')
							.data({cmwTriggerChange:data.cmwDialogTrigger, cmwTitlePrefix:data.cmwDialogTitle})
							.append( $('<div/>').addClass('cmw-demo-theoutput').html('<strong>' + data.cmwDialogOutput + '</strong> &hellip;<div class="cmw-demo-theoutput-wrap ui-corner-all"></div><div class="cmw-demo-fallback"><small>' + data.cmwDialogFallback + '</small></div>') )
							.append( $('<div/>').addClass('cmw-demo-themenu').html('<small><em>' + data.cmwDialogPrompt + '</em></small>') )
							.append( $('<div/>').addClass('cmw-demo-theshortcode').html('<code class="ui-corner-all">[custom_menu_wizard]</code>') );
						dialog.find('.cmw-demo-themenu').on('click', 'a', assist.clickMenu);
						dialog.find('.cmw-demo-themenu').on('change', 'input', assist.changeMenu);
						dialog.find('.cmw-demo-theoutput').on('click', 'a', assist.clickOutput);
						dialog.dialog({autoOpen:false, width:Math.min($(window).width() * 0.8, 600), modal:false});
					}
					if(dialog.dialog('isOpen')){
						dialog.dialog('close');
					}else{
						assist.createMenu(dialog, fm);
						dialog.dialog('open');
						$(data.cmwDialogTrigger).trigger('change');
					}
					this.blur();
					return false;
				},
				/**
				 * create and show the shortcode equivalent
				 * @param {Object} fm jQuery object of the widget form
				 * @param {Object} settings Form element values
				 */
				shortcode : function(fm, settings){
					var args = {
								'menu' : [settings.menu]
							},
							v, m, n;
					if(settings.title){
						args.title = settings.title;
					}
					if(settings.filter > 0){
						switch(settings.filter_item){
							case 0: args.children_of = 'current'; break;
							case -1: args.children_of = 'parent'; break;
							case -2: args.children_of = 'root'; break;
							default:
								args.children_of = [settings.filter_item];
						}
					}
					if(settings.filter < 0){
						args.items = settings._items;
					}
					if(settings.filter > 0 && settings.filter_item < 0 && settings.fallback_no_ancestor){
						if(settings.fallback_include_parent_siblings){
							args.fallback_parent = 'siblings';
						}else if(settings.fallback_include_parent){
							args.fallback_parent = 'parent';
						}else{
							args.fallback_parent = [1];
						}
					}
					if(settings.filter > 0 && !settings.filter_item && settings.fallback_no_children){
						if(settings.fallback_nc_include_parent_siblings){
							args.fallback_current = 'siblings';
						}else if(settings.fallback_nc_include_parent){
							args.fallback_current = 'parent';
						}else{
							args.fallback_current = [1];
						}
					}
					if(settings.start_level > 1){
						args.start_level = [settings.start_level];
					}
					if(settings.depth > 0){
						args.depth = [settings.depth];
					}
					if(settings.depth_rel_current && settings.depth > 0){
						args.depth_rel_current = [1];
					}
					n = [];
					if(settings.filter > 0){
						if(settings.include_parent_siblings){
							n.push('siblings');
						}else if(settings.include_parent){
							n.push('parent');
						}
						if(settings.include_ancestors){
							n.push('ancestors');
						}
						if(n.length){
							args.include = n.join(' ');
						}
					}
					n = [];
					if(settings.filter > 0 && settings.title_from_parent){
						n.push('parent');
					}
					if(settings.title_from_current){
						n.push('current');
					}
					if(n.length){
						args.title_from = n.join(' ');
					}
					for(n in {flat_output:1, contains_current:1, ol_root:1, ol_sub:1}){
						if(settings[n]){
							args[n] = [1];
						}
					}
					v = {container:'div', container_id:'', container_class:'', menu_class:'menu-widget', widget_class:''};
					for(n in v){
						if(settings[n] !== v[n]){
							args[n] = settings[n];
						}
					}
					v = {wrap_link:'before', wrap_link_text:'link_before'};
					for(n in v){
						m = settings[v[n]].toString().match(/^<(\w+)/);
						if(m && m[1]){
							args[n] = m[1];
						}
					}
					v = [];
					for(n in args){
						//array indicates 'as is', otherwise surround it in double quotes...
						v.push( $.isArray(args[n]) ? n + '=' + args[n][0] : n + '="' + args[n] + '"' );
					}
					assist.getDialog(fm).find('code').text('[custom_menu_wizard ' + v.join(' ') + ']');
				},
				/**
				 *
				 * @this {Element}
				 * @param {Object} e Event object
				 * @param {Object} fm jQuery of widget form
				 * @param {Object} settings Form element values 
				 */
				show : function(e, fm, settings){ //scope is a widget form element
					//hide_empty is assumed to be On (WP < v3.6) or will automatically be On (WP v3.6+)
					fm = fm || $(this).closest('form');
					settings = settings || assist.getSettings(fm);
					var dialog = assist.getDialog(fm),
							themenu = dialog.find('.cmw-demo-themenu'),
							items = themenu.find('.picked'),
							html = '',
							title = '',
							currLevel = 0,
							output = dialog.find('.cmw-demo-theoutput-wrap').empty(),
							listClass = ['menu-widget'],
							itemList = {};
					if(items.length && output.length){
						if(settings.filter > 0 && settings.title_from_parent){
							title = themenu.find('.the-parent').children('a').text() || '';
						}
						if(!title && settings.title_from_current){
							title = themenu.find('.current-menu-item').text() || '';
						}
						if(!title && !settings.hide_title){
							title = settings.title || '';
						}
						items.each(function(i){
							var self = $(this),
									data = self.data(),
									trace = data.trace ? data.trace.toString().split(',') : [],
									iid = data.itemid.toString(),
									level = 1,
									anchor = self.children('a');
							if(!settings.flat_output){
								itemList[iid] = 1;
								for(i = 0; i < trace.length; i++){
									if(itemList[trace[i]]){
										level++;
									}
								}
							}
							if(currLevel){
								if(level > currLevel){
									html += settings.ol_sub ? '<ol>' : '<ul>';
								}else{
									while(currLevel > level){
										--currLevel;
										html += '</li>' + (settings.ol_sub ? '</ol>' : '</ul>');
									}
									html += '</li>';
								}
							}
							html += '<li class="cmw-level-' + level + (data.included || '') + '"><a href="#' + anchor.data('indx') + '">' + anchor.text() + '</a>';
							currLevel = level;
						});
						while(currLevel > 1){
							--currLevel;
							html += '</li>' + (settings.ol_sub ? '</ol>' : '</ul>');
						}
						html += '</li>';
						listClass.push( dialog.find('.cmw-demo-fallback').data('fellback') );
						html = (settings.ol_root ? '<ol' : '<ul') + ' class="' + $.trim(listClass.join(' ')) + '">' + html + (settings.ol_root ? '</ol>' : '</ul>');
						output.html(html);
						if(title){
							output.prepend('<h3>' + title + '</h3>');
						}
						output.find('li').filter(function(){
							return !!$(this).children('ul, ol').length;
						}).addClass('cmw-has-submenu');
					}
					assist.shortcode(fm, settings);
				},
				/**
				 * updates the graphic menu structure from the widget form data
				 * @this {Element} An input (radio or checkbox) or select element from the widget form
				 * @param {Object} e Event object
				 */
				update : function(e){
					var fm = $(this).closest('form'),
							dialog = assist.getDialog(fm),
							maxLevel, settings, includeParent, includeParentSiblings, themenu, items,
							currentItemLI, currentItemLevel, fallback, parent, i, j;
					if(!dialog.length || !dialog.dialog('isOpen')){
						return;
					}

					if($(e.target).hasClass(dotPrefix.substr(1) + '-selectmenu')){
						assist.createMenu(dialog, fm);
					}
					settings = assist.getSettings(fm);
					includeParent = settings.include_parent;
					includeParentSiblings = settings.include_parent_siblings;
					themenu = dialog.find('.cmw-demo-themenu');
					maxLevel = themenu.data().maxLevel;
					currentItemLI = themenu.find('.current-menu-item').closest('li');
					currentItemLevel = currentItemLI.length ? currentItemLI.data().level : -1;
					items = themenu.find('li').removeData('included').removeClass('the-parent');

					if(settings.filter < 0){
						items = items.filter(function(){
							var checkbox = $(this).children('input'),
									checked = (settings._items_sep + settings._items + settings._items_sep).indexOf(settings._items_sep + checkbox[0].value + settings._items_sep) > -1;
							checkbox.prop('checked', checked);
							return checked;
						});
						if(!settings._items){
							items = $([]);
						}
					}

					if(items.length && !currentItemLI.length && (settings.contains_current || (settings.filter > 0 && settings.filter_item < 1))){
						items = $([]);
					}

					if(items.length && settings.filter > 0){
						//kids of...
						if(settings.filter_item > 0){
							//specific item...
							parent = items.filter('[data-itemid=' + settings.filter_item + ']');
						}else if(!settings.filter_item){
							//current...
							if(currentItemLI.find('li').length){
								parent = currentItemLI;
							}else if(settings.fallback_no_children){
								//fall back to current parent...
								parent = themenu.find('.current-menu-parent').closest('li');
								if(!parent.length){
									parent = themenu; //beware!
								}
								includeParent = includeParent || settings.fallback_nc_include_parent;
								includeParentSiblings = includeParentSiblings || settings.fallback_nc_include_parent_siblings;
								fallback = 'cmw-fellback-to-parent';
							}
						}else{
							//parent or root...
							if(currentItemLevel === 1 && settings.fallback_no_ancestor){
								parent = currentItemLI;
								includeParent = includeParent || settings.fallback_include_parent;
								includeParentSiblings = includeParentSiblings || settings.fallback_include_parent_siblings;
								fallback = 'cmw-fellback-to-current';
							}else if(currentItemLevel === 1){
								parent = themenu; //beware!
							}else if(settings.filter_item < -1){
								parent = themenu.find('.current-menu-ancestor').eq(0).closest('li');
							}else{
								parent = themenu.find('.current-menu-parent').closest('li');
							}
						}
					}

					if(items.length){
						if(!settings.filter){
							//showall : use the levels...
							if(settings.depth_rel_current && settings.depth && currentItemLI.length && currentItemLevel >= settings.start_level){
								j = currentItemLevel + settings.depth - 1;
							}else{
								j = settings.depth ? settings.start_level + settings.depth - 1 : 9999;
							}
							for(i = 1; i <= maxLevel; i++){
								if(i < settings.start_level || i > j){
									items = items.not('.level-' + i);
								}
							}
						}else if(parent && parent.length){
							//kids of...
							if(settings.depth_rel_current && settings.depth && currentItemLI.length && parent.has(currentItemLI[0]).length){
								j = currentItemLevel - 1 + settings.depth;
							}else{
								j = settings.depth ? Math.max( (parent.data().level || 0) + settings.depth, settings.start_level + settings.depth - 1 ) : 9999;
							}
							items = parent.find('li').filter(function(){
								var level = $(this).data().level;
								return level >= settings.start_level && level <= j;
							});
						}else if(settings.filter > 0){
							//kids-of, but no parent found...
							items = $([]);
						}
					}

					if(items.length){
						if(settings.filter > 0 && parent && parent.is('li')){
							//kids of an item...
							if(includeParentSiblings){
								items = items.add( parent.siblings('li').data('included', ' cmw-an-included-parent-sibling') );
								includeParent = true;
							}
							if(settings.include_ancestors){
								items = items.add( parent.parentsUntil(themenu, 'li').data('included', ' cmw-an-included-ancestor') );
								includeParent = true;
							}
							if(includeParent){
								items = items.add( parent.data('included', ' cmw-the-included-parent') );
							}
						}
					}

					//must contain current item?...
					if(items.length && settings.contains_current && (!currentItemLI.length || !items.filter(currentItemLI).length)){
						items = $([]);
					}

					if(items.length && parent && parent.is('li')){
						parent.addClass('the-parent');
					}
					fallback = items.length ? fallback : '';
					dialog.find('.cmw-demo-fallback').data('fellback', fallback).toggleClass('cmw-demo-fellback', !!fallback);
					themenu.toggleClass('cmw-demo-filteritems', settings.filter < 0)
						.find('.picked').not( items.addClass('picked') ).removeClass('picked');
					assist.show.call(this, e, fm, settings);
				}
			};

	$(document)
		//fieldsets...
		.on('click', dotPrefix + '-collapsible-fieldset', function(){
			var self = $(this),
					chkbox = self.find('input').eq(0),
					collapse = !chkbox.prop('checked');
			if(chkbox.length){
				chkbox.prop('checked', collapse);
				self.find('div').toggleClass('cmw-collapsed-fieldset', collapse);
				self.next('div')[collapse?'slideUp':'slideDown']();
			}
			this.blur();
			return false;
		})
		//change of menu, and enableif / disableif...
		.on('change', dotPrefix + '-listen', function(){
			var fm = $(this.form),
					selectMenu = fm.find(dotPrefix + '-selectmenu'),
					showAll = fm.find(dotPrefix + '-showall').prop('checked'),
					showSpecific = fm.find(dotPrefix + '-showspecific').prop('checked'),
					filterItem = fm.find(dotPrefix + '-childrenof'),
					fiVal = parseInt(filterItem.val(), 10),
					groupClone;
			if(selectMenu.is(this)){
				//change of menu : swap out the childrenof's optgroup for the new one...
				selectMenu = this.selectedIndex;
				if(!filterItem.find('optgroup').filter(function(){
							var keep = $(this).data('cmwOptgroupIndex') === selectMenu;
							if(!keep){
								$(this).remove();
							}
							return keep;
						}).length){
					groupClone = $('#' + filterItem.attr('id') + '_ignore').find('optgroup').eq(selectMenu).clone();
					if(groupClone.length){
						if(fiVal > 0){
							fiVal = 0;
							filterItem.val(fiVal);
						}
						groupClone.find('option[selected]').removeAttr('selected').prop('selected', false);
						filterItem.append(groupClone);
					}
				}
			}
			$.each(
				{	'' : showAll || showSpecific,
					'-ss' : showSpecific,
					'not-rp' : showAll || showSpecific || fiVal >= 0,
					'not-ci' : showAll || showSpecific || !!fiVal
				},
				function(k, v){
					$(dotPrefix + '-disableif' + k, this.form).toggleClass('cmw-colour-grey', v).find('input,select').prop('disabled', v);
				});
		})
		//any change event on inputs or selects...
		.on('change', dotPrefix + '-onchange', assist.update)
		//assist dialog...
		.on('click', dotPrefix + '-toggle-assist', assist.init)
		//when a widget is closed, close its open dialog...
		.on('click', '.widget-action, .widget-control-close', function(){
			$(this).closest('div.widget').find('.widget-custom-menu-wizard-onchange').each(function(){
				var dialog = $('#' + $(this).data().cmwDialogId);
				if(dialog.length && dialog.dialog('isOpen')){
					dialog.dialog('close');
				}
			});
		});

	//1. when a widget is opened or saved, trigger change on the filter_item select
	//2. when a widget is deleted, destroy its dialog
	//To achieve this I've elected to modify WP's window.wpWidgets object and intercept some of its methods
	// - for (1), the fixLabels() method, which handily gets called whenever a widget is opened or saved
	// - for (2), the save() method
	if(window.wpWidgets){
		if(window.wpWidgets.fixLabels && !window.wpWidgets._cmw_fixLabels){
			//save the original...
			window.wpWidgets._cmw_fixLabels = window.wpWidgets.fixLabels;
			//replace the original...
			window.wpWidgets.fixLabels = function(widget){
				//trigger change on selectmenu...
				widget.find('.widget-custom-menu-wizard-selectmenu').trigger('change');
				//run the original...
				window.wpWidgets._cmw_fixLabels(widget);
    	};
    }
		if(window.wpWidgets.save && !window.wpWidgets._cmw_save){
			//save the original...
			window.wpWidgets._cmw_save = window.wpWidgets.save;
			//replace the original...
			window.wpWidgets.save = function(widget, del, animate, order){
				//destroy dialog if deleting the widget...
				if(del){
					widget.find('.widget-custom-menu-wizard-onchange').each(function(){
						var dialog = $('#' + $(this).data().cmwDialogId);
						if(dialog.length){
							dialog.dialog('destroy');
							dialog.remove();
						}
					});
				}
				//run the original...
				window.wpWidgets._cmw_save(widget, del, animate, order);
			};
		}
	}else{
		//one-off fallback...
		$(dotPrefix + '-selectmenu').trigger('change');
	}
});

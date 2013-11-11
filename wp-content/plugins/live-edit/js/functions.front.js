/*
*  Live Edit
*
*  @description: 
*  @created: 27/07/12
*/
	
(function($){

	/*
	*  Exists
	*  
	*  @since			1.0.0
	*  @description		returns true or false on a element's existance
	*/
	
	$.fn.exists = function()
	{
		return $(this).length>0;
	};
	
	
	/*
	*  uniqid
	*  
	*  @since			1.0.0
	*  @description		Returns a unique ID (secconds of time)
	*/
	
	function uniqid()
   {
    	var newDate = new Date();
    	return newDate.getTime();
    }
	
	
	
	/*
	*  Click Edit
	*
	*  @description: 
	*  @created: 6/09/12
	*/
	
	$('.live_edit-edit-button').live('click', function(){
		
		// vars
		var button = $(this);
		
		
		// update live_Edit vars
		live_edit.div = button.parent('.live_edit-enabled');
		live_edit.fields = live_edit.div.attr('data-live_edit-fields');
		live_edit.post_id = live_edit.div.attr('data-live_edit-post_id');
		
		
		// open panel
		live_edit.open_panel();
		
		
		// set iframe url
		$('#live_edit-iframe').attr('src', live_edit.panel_url + '&fields=' + live_edit.fields + '&post_id=' + live_edit.post_id);
		
		
		return false;
		
	});
	
	
	/*
	*  Open Panel
	*
	*  @description: 
	*  @created: 7/09/12
	*/
	
	live_edit.open_panel = function()
	{
		// animate out panel
		$('#live_edit-iframe').css({
			width : live_edit.panel_width
		});
		
		$('#live_edit-panel').animate({
			width : live_edit.panel_width
		}, 250, function(){
			
			$('#live_edit-iframe').css({
				width : '100%'
			});
			
		});
		
		
		// move across body
		$('html').animate({
			'margin-left' : live_edit.panel_width
		}, 250);
		
		// disable resizable
		$('#live_edit-panel').resizable("option", "disabled", false);
	};
	
	
	/*
	*  close_panel
	*
	*  @description: 
	*  @created: 7/09/12
	*/
	
	live_edit.close_panel = function()
	{
		// animate away panel
		$('#live_edit-iframe').css({
			width : live_edit.panel_width
		});
		
		$('#live_edit-panel').animate({
			width : 0
		}, 250 );
		
		
		// move across body
		$('html').animate({
			'margin-left' : 0
		}, 250);
		
		// disable resizable
		$('#live_edit-panel').resizable("option", "disabled", true);
	};
	
	
	/*
	*  add_edit_buttons
	*
	*  @description: 
	*  @created: 7/09/12
	*/
	
	function add_edit_buttons()
	{
		$('[data-live_edit-fields]').each(function(){
			
			//vars
			var div = $(this);
			
			
			// ignore if already setup
			if( div.hasClass('live_edit-enabled') )
			{
				return;
			}
			
			
			// add class
			$(this).addClass('live_edit-enabled');
			
			
			// add button
			div.prepend('<span class="live_edit-edit-button">Edit</span>');
		});
	};
	
	
	/*
	*  update_div
	*
	*  @description: 
	*  @created: 8/09/12
	*/
	
	live_edit.update_div = function(){

		// add css to div
		live_edit.div.append('<div class="live_edit-updating"></div>');
		
		
		// fetch new div via ajax
		var html = $('<div></div>');
		
		html.load( window.location + ' [data-live_edit-post_id="' + live_edit.post_id + '"][data-live_edit-fields="' + live_edit.fields + '"]', function(){
			
			var div = $( html.html() );
			
			live_edit.div.replaceWith( div );
			
			live_edit.div = div;
			
			// add buttons
			add_edit_buttons();
			
		});
		

	};
	
	
	/*
	*  Document Ready
	*  
	*  @since			1.0.0
	*  @description		Returns a unique ID (secconds of time)
	*/
	
	$(document).ready(function(){
		
		// add buttons
		add_edit_buttons();
		
		
		// resizable
		$('#live_edit-panel').resizable({
			handles : 'e',
			start: function(event, ui){
				$('#live_edit-iframe-cover').css({'display':'block'});
			},
			resize: function(event, ui){
				$('html').css({
					'margin-left' : $('#live_edit-panel').width()
				}, 250);
			},
			stop: function(event, ui){
				$('#live_edit-iframe-cover').css({'display':'none'});
				
				var data = {
					action	: 'live_edit_update_width',
					panel_width	: $('#live_edit-panel').width()
				};
		
				
				$.post(live_edit.ajaxurl, data, function() {
					// do nothing
				});
				
				
				// update local variable
				live_edit.panel_width = data.panel_width;
			}
		});
		
		// disable resizable
		$('#live_edit-panel').resizable("option", "disabled", true);
	
		
	});

})(jQuery);

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
    	var newDate = new Date;
    	return newDate.getTime();
    }
	
	
	/*
	*  Update button
	*
	*  @description: 
	*  @created: 8/09/12
	*/
	
	$('#live_edit-save').live('click', function(){
		
		// show saving message
		$('#saving-message').show();
		
	});
	
	
	$('#live_edit-close').live('click', function(){
		
		// does parent exist?
		if( !parent )
		{
			return;
		}
		
		// update the div
		parent.live_edit.close_panel();
		
		return false;
		
	});
	
	
	/*
	*  Document Ready
	*
	*  @description: adds ajax data
	*  @created: 1/03/2011
	*/
	
	$(document).ready(function(){

		// hide acf stuff
		$('#poststuff .acf-hidden').removeClass('acf-hidden');
	
	});
	

})(jQuery);

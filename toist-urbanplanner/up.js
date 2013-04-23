jQuery(document).ready(function($){
	var $meta = $("#toist_planner.postbox");
	
	$meta
		.on("click","td",function(){$(this).toggleClass("selected")})
		.on("click",".button-primary",function(ev){
			ev.preventDefault();
			ev.stopPropagation();
			make_planner();
		});
		
		
	function make_planner(){
		var start = $meta.find("td.selected:first").attr("data-date");
		var end = $meta.find("td.selected:last").attr("data-date");
		
		var payload = {
			action:	'toist_planner',
			nonce: toistUP.UPNonce
		}
		
		if(start !== end){
			payload.start = start;
			payload.end = end;
		}else{
			payload.date = start;
		}
				
		$.get(
			toistUP.ajaxurl,
			payload,
			function(res){
				window.send_to_editor(res);
			}
		)
		//get the UP from server
		//add it to the editor window.send_to_editor
	}
	
	if(typeof(QTags) != 'undefined'){
		function event_more_tag(e,c,ed){
			this.tagStart = '<!--more--><!--event_more-->';
			QTags.TagButton.prototype.callback.call(this,e,c,ed);
		}
		QTags.addButton('event_more','Event details',event_more_tag,'','');
	}

});



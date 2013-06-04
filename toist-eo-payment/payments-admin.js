function message(type,text){
	jQuery("#messages").append('<div class="'+type+'"><p>'+text+'</p></div>');
}

jQuery(document).ready(function(){
	var $ = jQuery;
	$("#sponsored-listing").on("click","span.refund",function(event){
		$(this).next().show();
	});
	
	$("#sponsored-listing").on("click","a.response",function(event){
		$(this).parent().hide();
	});
	
	$("#sponsored-listing").on("click","a.confirm",function(event){
		var data = {
			action:	'eopayment_refund',
			refundNonce:	toistEOPay.refundNonce,
			post_id	:	$(this).attr('data-postid')
		}
		var spinner = $('<div class="spinner"></div>')
			.prependTo($(this).closest(".row-actions").parent());
		$.post(
			toistEOPay.target,
			data,
			function(res){
				spinner.remove();
				switch(res.status){
					case "success":
						//tell the user that the refund went through
						message('updated',res.note);
						
						//hide the post row
						$("#sponsored-listing #post-"+data.post_id).hide("slow");
						break;
					case "failure":
						message('error',res.reason);
						$("#sponsored-listing #post-"+data.post_id).hide("slow");
						break;
					case "error":
						message('error',res.reason);
						break;
					default:
						break;
				}
			},'json'
		)
	});
});

jQuery(document).ready(function($){
	var $form = $("form");
	var $preview = $("#preview");
	$form
		.on("click","#bg-uploader",function(){
			formfield = $("#background-image").attr('name');
			tb_show('','media-upload.php?type=image&TB_iframe=true');
			return false;
		})
		.on('change','input,textarea',function(){
			make_preview();
		});
	
	window.send_to_editor = function(html){
		var imgurl = $('img',html).attr('src');
		if(typeof imgurl == 'undefined'){
			imgurl = $(html).attr('src');
		}
		$('#background-image').val(imgurl);
		//show preview
		tb_remove();
		make_preview();
	}
	
	function make_preview(){
		var settings = $form.serializeArray();
			var banner = {};
			$(settings).each(function(){
				banner[this.name] = this.value;
			});
			data = {
				banner:banner,
				action: 'page_banner_preview'
				};
	
			$.post(
				toistBanner.target,
				data,
				function(res){
					$preview.html(res);
				}
			);
	}
});

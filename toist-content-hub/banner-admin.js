jQuery(document).ready(function($){
	var $form = $("form");
	var $preview = $("#preview");
	$form
		.on("focus","#background-image",function(){
			formfield = $("#background-image").attr('name');
			tb_show('','media-upload.php?type=image&TB_iframe=true');
			return false;
		})
		.on('change','input,textarea',function(){
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
		});
	
	window.send_to_editor = function(html){
		var imgurl = $('img',html).attr('src');
		$('#background-image').val(imgurl);
		//show preview
		tb_remove();
	}
});

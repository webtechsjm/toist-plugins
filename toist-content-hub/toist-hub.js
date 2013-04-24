jQuery(document).ready(function($){
	if($(".redirect_dropdown").length > 0){
		$(".redirect_dropdown").on("submit",function(ev){
			ev.stopPropagation();
			ev.preventDefault();
			var a = document.createElement('a');
			var url = $(this).find("select").val()
			a.href = url;
			if(a.hostname == toistHub.hostname){
				window.location = url;
			}
			
		});
	
	}
});

if (!String.prototype.format) {
  String.prototype.format = function() {
    var args = arguments;
    return this.replace(/{(\d+)}/g, function(match, number) { 
      return typeof args[number] != 'undefined'
        ? args[number]
        : match
      ;
    });
  };
}

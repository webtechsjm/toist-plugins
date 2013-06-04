jQuery(document).ready(function(){
	var $ = jQuery;
	var units = $("#ad-units");
	var tier = $("#ad-tier");
	var total = $("#ad-runtime-total")
	
	$("#minor-publishing").on("change",".ad-runtime",function(){
		var tiername = tier.val();
		var unitcount = units.val();
		var unit_price = toistEOPost.prices[tiername];
		var price = unit_price * unitcount;
		
		total.html('$'+price);
	});
});

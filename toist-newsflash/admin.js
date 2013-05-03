jQuery(document).ready(function($){
	$table = $("table#links");
	
	$("#links tbody").on("click","a",function(){remove_row(this)});
	$("#links tfoot").on("click","a",function(){add_row()});
	
	function remove_row(obj){
		$(obj).closest("tr").remove();
	}
	
	function add_row(){
		var row = '<tr><td><input type="text" name="newsflash[link][label][]" /></td><td><input type="text" name="newsflash[link][url][]" /></td><td><a href="#" class="button">-</a></td></tr>';
		$table.append(row);
	}
});

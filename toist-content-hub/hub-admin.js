jQuery(document).ready(function($){
	var $hub_window = $overlay = $add_pane = $page_nav = $page_num = $page_max = $layout_pane = $posts = $meta_id = $footer = $post_search = false,
			$used = [],
			numbers = ["zero","one","two","three","four","five","six","seven","eight","nine","ten","eleven","twelve"];
			
	
	$("#toist_hub_button").on("click",function(){
		if($overlay){$overlay.show();
		}else{
			$hub_window = $('<div class="media-modal wp-core-ui"></div>');
			$add_pane = $("<div></div>");
			var add_container = $('<section id="add_pane"><h1>Content</h1><div><input id="post_search" placeholder="Search for posts, pages, and any other type of content" /><button id="submit_search">Search</button></div></section>').append($add_pane);
			$page_num = $('<input id="page_num" type="number" />');
			$page_max = $('<span id="page_max"></span>');
			$page_nav = $('<div id="page-nav"></div>').append('<a class="before">&laquo;</a>').append($page_num).append($page_max).append('<a class="after">&raquo;</a>').insertBefore($add_pane);
			$layout_pane = $('<div></div>').sortable();
			var layout_container = $('<section id="layout"></section>').append($layout_pane);
			$hub_window.append(add_container).append(layout_container);
			$overlay = $('<div id="_toist-hub" class="supports-drag-drop" tabindex="0"></div>').append($hub_window).appendTo("body");
			
			//var load_hub = $('<select id="load"><option>Select a hub to load</option></select>');
			var load_hub = $('<a class="load button">Load</a>');
			
			$footer = $('<footer></footer>')
				.append('<a class="close button">Close</a>')
				.append('<a class="save button-primary">Save</a>')
				.append(load_hub)
				.appendTo($hub_window);
			//load_add_pane();
			
			$post_search = $("#post_search");
			
			activate_triggers();
		}
	});
	
	function activate_triggers(){
		$hub_window
			.on("click",".close",function(){$overlay.hide();})
			.on("click",".save",function(){save_hub();})
			.on("click",".load",function(){load_hub();})
			//.on("change","#post_search",function(){search_posts($);});
			.on("click","#submit_search",function(ev){
				ev.stopPropagation();
				ev.preventDefault();
				search_posts($post_search.val());
			});
		$add_pane.on("click","a",function(){
			var pid = $(this).attr("data-id");
			var post = $.grep($posts,function(p){return p.id == pid});
			//$used.push(post);
			add_to_hub(post);
			});
		$layout_pane.on("change","input,textarea,select",function(){
			var box = $(this);
			var article = box.closest("article");
			
			if(box.hasClass("cols")){
				article.attr("data-cols",box.val())
				}
			if(box.hasClass("rows")){
				article.attr("data-rows",box.val())
				}
			if(box.hasClass("cols") || box.hasClass("rows")){
				article.removeAttr("class");
				article.addClass(numbers[article.attr("data-cols")]).addClass("column");
				if(article.attr("data-rows")){
					article.addClass(numbers[article.attr("data-rows")]+"-rows");
				}
			}
			if(box.hasClass("title")){
				article.find("h1").html($used[article.attr("data-id")][box.val()]);
			}
			if(box.hasClass("text")){
				var customBox = article.find(".custom-text");
				if(box.val() != 'custom'){
					article.find("p:first").html($used[article.attr("data-id")][box.val()]).show();
					customBox.hide().html("");
				}else{
					if(customBox.length > 0){
						var text = article.find("p:first").hide().html();
						customBox.show().html(text);
					}else{
						article.find("p:first").hide().after('<textarea class="custom-text" name="{0}-customtext">{1}</textarea>'.format(article.attr("data-id"),article.find("p:first").html()));
					}
				}
			}
		})
			.on("click",".remove",function(){
				$(this).closest("article").remove();
			})
			.on("click","a.advanced",function(){
				$(this).parent().siblings("div.advanced").show();
			})
			.on("click",".close",function(ev){
				$(this).closest("div").hide();
				ev.stopPropagation();
			});
		$page_nav.on("click","a",function(){
			var data = {};
			if($(this).hasClass("before")){
				data.hubsearch_page = $page_num.val()*1 - 1;
				
			}else{
				data.hubsearch_page = $page_num.val()*1 + 1;
			}
			if($post_search.val() != ""){
				data.keyword = $post_search.val();
			}
			update_add_pane(data);
		});
	}
	
	function load_add_pane(){
		//var post_types = ['posts','pages','events'];
		//Maybe we should get the 100 most recent published/draft/pending posts?
		//Also, a search function
		update_add_pane({});
	}
	
	function update_add_pane(data){
		data.action = 'toist_hub_query';
		data.nonce = toistHub.nonce;
		
		$.post(
			toistHub.target,
			data,
			function(res){
				var list = '';
				$posts = res.results;
				$($posts).each(function(){
					list += '<p><a data-id="{0}" title="Add {1} to layout">{1}</a></p>'.format(
						this.id,
						this.title
					);
				});
				if(res.num_pages > 1){
					if(data.hubsearch_page){
						$page_num.val(data.hubsearch_page);
					}else{$page_num.val(1);}
					$page_max.html(res.num_pages);
					$page_nav.show();
				}else{$page_nav.hide();}
				$add_pane.html(list);
			},'json'
		);
	}
	
	function select_builder(id,name,options,current=false){
		var opts = '';
		$(options).each(function(){
			var selected = '';
			//console.log(this.name,this.value,selected);
			if(this.name == current){selected = ' selected="selected"';}
			opts += '<option value="{0}"{2}>{1}</option>'.format(this.name,this.value,selected);
		});
		return '<select id="{0}-{1}" class="{1}" name="{0}-{1}">{2}</select>'.format(id,name,opts);
	}
	
	function add_to_hub(post,settings = {}){
		var defaults = {rows: '',columns:12}
		var opts = {};
		if(post[0]) post = post[0];
		for(var att in defaults){opts[att] = defaults[att];}
		for(var att in settings){opts[att] = settings[att];}
		$used[post.id] = post;
	
		var block = '<article class="{7} column" data-cols="{4}" data-rows="{5}" data-id="{0}"><header>{3}<h1>{1}</h1></header><div><p>{2}</p><p><label for="num_cols_{0}">Columns:</label><input id="num_cols_{0}" name="{0}-columns" class="cols" value="{4}" /><label for="num_rows_{0}">Rows:</label><input id="num_rows_{0}" name="{0}-rows" class="rows" value="{5}" /></p><p><label for="ids_{0}">Post IDs:</label><input id="ids_{0}" value="{6}" name="{0}-ids" class="ids" /></p></div><div><a class="remove">Remove</a> | <a class="advanced">Advanced</a></div></article>'.format(
			post.id,
			post.title,
			post.content,
			post.thumb,
			opts.columns ? opts.columns: '',
			opts.rows ? opts.rows: '',
			opts.ids ? opts.ids: post.id,
			opts.columns ? numbers[opts.columns] : 'twelve'
		);
		//$layout_pane.append(block);
		//console.log(opts.text);
		var advanced = $('<div class="advanced"><a class="close">X</a></div>');
		var title_opt = '<label>Title</label>'+select_builder(post.id,'title',
			[{name:'title',value:'Title'},
			{name:'alt_title',value:'Alt Title'}],
			opts.title ? opts.title : '');
		var text_opt = '<label>Body text</label>'+select_builder(post.id,'text',
			[{name:'content',value:'Body text'},
			{name:'dek',value:'Dek'},
			{name:'alt_dek',value:'Alt Dek'},
			{name:'custom',value:'Custom'}],
			opts.text ? opts.text : '');
		var background = '<label for="{0}-bg">Background</label><input id="{0}-bg" name="{0}-bg" class="bg" value="{1}" />'.format(post.id,opts.bg?opts.bg:'');
		var scroll = '<label>Allow scrolling</label>'+select_builder(post.id,'scroll',
			[{name:'false',value:'No scrolling'},
			{name:'true',value:'Scrolling enabled'}],
			opts.scroll ? opts.scroll : '');
		advanced.append(title_opt).append(text_opt).append(scroll).append(background);
		$(block).append(advanced).appendTo($layout_pane);
	}
	
	function save_hub(){
		var fields = $layout_pane.find("input,textarea,select").serializeArray();
		var layout = {};
		var lastObj = false;
		var i = -1;
				
		$(fields).each(function(){
			var names = this.name.split('-');
			if(this.value.length > 0){
				var propName = names[1];
				var objName = names[0];
				if(objName != lastObj){
					i++;
					lastObj = objName;
				} 
				
				if(layout[i]){layout[i][propName] = this.value;}
				else{
					var temp = {};
					temp[propName] = this.value;
					layout[i] = temp;
				}
			} //layout[names[0]][names[1]] = this.value
		});
		
		var data = {
			hub:	layout,
			action: 'toist_hub_save',
			nonce: toistHub.nonce,
			post_id:	toistHub.post_id
		}
		
		$.post(
			toistHub.target,
			data,
			function(res){
				var message = $('<div class="message">Saved!</div>');
				$footer.append(message);
				message.fadeOut(3000,function(){$(this).remove()});
				if($meta_id){
					$("#meta-"+$meta_id).find("textarea").val(res);
				};
				
			}
		);
	}
	
	function load_hub(){
		var data = {
			action: 'toist_hub_load',
			nonce: toistHub.nonce,
			post_id:	toistHub.post_id
		}
		
		$.post(
			toistHub.target,
			data,
			function(res){
				$(res.structure).each(function(){
					var struct = {};
					struct.ids = this.ids;
					if(this.columns) struct.columns = this.columns;
					if(this.rows) struct.rows = this.rows;
					if(this.title) struct.title = this.title;
					if(this.text) struct.text = this.text;
					if(this.bg) struct.bg = this.bg;
					
					if(this.ids.indexOf(',') == -1){
						add_to_hub(res.posts[this.ids],struct);
					}else{
						var ids = this.ids.split(',');
						add_to_hub(res.posts[ids[0]],struct);
					}
				});
				if(res.meta_id) $meta_id = res.meta_id;
			},'json'
		);
	}
	
	function search_posts(keyword){
		var data = {keyword: keyword}
		update_add_pane(data);
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

<?php
/**

@package Torontoist

Plugin Name: Torontoist Content Hub
Plugin URI: http://torontoist.com
Description: Creates an easy way to layout hub pages
Version: 0.1
Author: Senning Luk
Author URI: http://puppydogtales.ca
License: GPLv2 or later
*/

class Toist_Hub{
	function __construct(){
		add_action('media_buttons_context', array($this,'add_hub_button'));
		add_action('admin_enqueue_scripts',array($this,'queue_scripts'));
		add_action('wp_ajax_toist_hub_load',array($this,'ajax_load_hub'));
		add_action('wp_ajax_toist_hub_save',array($this,'ajax_save_hub'));
		add_action('wp_ajax_toist_hub_query',array($this,'ajax_wp_query'));
		
		//add_action('admin_init',array($this,'options'));
		add_action('admin_menu',array($this,'hub_banner_page'));
		add_action('wp_ajax_toist_banner_preview',array($this,'preview_banner'));
	}
	
	function add_hub_button($context){
		return $context . '<a class="button" id="toist_hub_button">Add Hub</a>';
	}
	
	function queue_scripts($hook){
		if($hook == "post-new.php" || $hook == "post.php"){
			wp_enqueue_style('torontoist_hub_admin',plugins_url('hub-admin.css',__FILE__),array(),'0.2');
			wp_enqueue_script('torontoist_hub_admin',plugins_url('hub-admin.js',__FILE__));
		}
		wp_localize_script('torontoist_hub_admin','toistHub',array(
			'target'					=>	admin_url('admin-ajax.php'),
			'nonce'						=>	wp_create_nonce('torontoist_hub_admin'),
			'post_id'					=>	get_the_ID()
			));
	}
	
	function ajax_load_hub(){
		$this->ajax_validate();
		/*
		if(isset($_POST['hub'])){
			echo $this->load_layout($_POST['hub'],true);
		}
		*/
		if(!isset($_POST['post_id'])) die('No post specified');
		
		$post_id = intval($_POST['post_id']);
		
		$hub = array();
		$structure = json_decode(get_post_meta($post_id,'toist_hub',true));
		
		foreach($structure as $block){
			//test for commas; can specific multiple IDs
			$post_ids[] = intval($block->ids);
		}
		
		//remove duplicate IDs
		remove_all_filters('pre_get_posts');
		$posts = new WP_Query(array(
			'post_type'	=>	'any',
			'post__in' 	=> $post_ids
		));
		
		$post_list = array();
		foreach($posts->posts as $post){
			$thumb = get_the_post_thumbnail($post->ID,'full');
			if($thumb == ''){
				preg_match('|<img[^>]+>|',$post_list[$block->ids]['content'],$matches);
				if(is_array($matches)) $thumb = $matches[0];
			}
			
			
			$post_list[$post->ID] = array(
				'id'				=>	$post->ID,
				'author'		=>	$post->post_author,
				'title'			=>	$post->post_title,
				'content'		=>	strip_tags($post->post_content),
				'date'			=>	$post->post_date,
				'thumb'			=>	$thumb,
				'permalink'	=>	get_permalink($id),
				'dek'				=>	get_post_meta(get_the_ID(),'dek',true),
				'alt_dek'		=>	get_post_meta(get_the_ID(),'alt_dek',true),
				'alt_title'	=>	get_post_meta(get_the_ID(),'alt_title',true)
			);
		}
		
		
		global $wpdb;
		$meta_id = $wpdb->get_var($wpdb->prepare(
			"SELECT meta_id FROM $wpdb->postmeta WHERE post_id=%d AND meta_key = %s",
			$post_id,
			'toist_hub'
			));
		if($meta_id != '') $hub['meta_id'] = $meta_id;
		
		$hub['posts'] = $post_list;
		$hub['structure'] = $structure;
		echo json_encode($hub);
		exit;
		
	}
	
	function ajax_save_hub(){
		$this->ajax_validate();
		
		if(!isset($_POST['hub'])){die('No hub specification');}
		if(!isset($_POST['post_id'])){die('No post specified');}
		//if a hub ID is specified, check against the existant hubs and replace
		
		$hub = $_POST['hub'];
		$post = intval($_POST['post_id']);
		$i = 0;
		
		update_post_meta($post,'toist_hub',json_encode($hub));
		
		echo json_encode($hub);
		
		exit;
	}
	
	function load_layout($layout,$admin=false){
		//given an array that looks like the following, lay out blocks as specified:
		// [{columns:8,rows:2,ids:'123,124'},{columns:4,rows:2,ids:'125'},{columns:3,ids:'126'},{columns:3,ids:'127'},{columns:3,ids:'128'},{columns:3,ids:'129'}]
		
		foreach($layout as $block){
			if($admin) $this->make_admin_block();
			else $this->make_block();
		}
	}
	
	function make_block($block,$post='null',$format='block'){
		if(!isset($block->type)){return self::make_post_block($block,$post,$format);
		}else{
			switch($block->type){
				case "dropdown": return self::make_dropdown_block($block,$post,$format);
				default: return self::make_post_block($block,$post,$format); break;
			}
		}
	}
			
	function make_post_block($block,$post,$format){
		$layout = false;
		$numbers = array("zero", "one", "two", "three", "four", "five", "six", "seven", "eight", "nine", "ten", "eleven", "twelve");
		$img_size = 'medium';
		$columns_num = intval($block->columns);
		
		if(isset($block->classes)){
			$classes = explode(' ',$block->classes);
		}else{$classes = array();}
		
		if(in_array('wide',$classes)){
			$layout = 'top';
		} elseif(in_array('tall',$classes)){
			if(in_array('top',$classes)) $layout = 'top';
			else $layout = 'bottom';
			$img_size = 'large';
		} elseif($columns_num > 3){
			$classes[] = 'wide';
		}else{
			$classes[] = 'tall';
		}
		
		if($format == 'sub' && $columns_num < 5){
			$img_size = 'thumbnail';
		}
		if(
			in_array('tall',$classes) 
			&& $columns_num < 4
			&& !in_array('notstrict',$classes) 
			&& !in_array('strict',$classes)
		){
			$classes[] = 'strict';
		}
		if(
			in_array('wide',$classes) 
			&& $columns_num > 4
			&& !in_array('notexpanded',$classes) 
			&& !in_array('expanded',$classes)
		){
			$classes[] = 'expanded';
		}
		
		
		//title
		if($block->title == 'alt_title'){$title = $post['alt_title'];
		}else{$title = $post['title'];}
					
		//text
		switch($block->text){
			case "dek": $content = $post['dek']; break;
			case "alt_dek": $content = $post['alt_dek']; break;
			case "custom": $content = stripslashes($block->customtext); break;
			default: $content = $post['content']; break;
		}
		
		//image
		if(isset($block->hideImg) && $block->hideImg == 'true'){
			$thumbnail = '';
		}else{
			$thumbnail = get_the_post_thumbnail($post['id'],$img_size);
			if($thumbnail == ''){
				preg_match('|<img[^>]+>|',$post['content'],$matches);
				if(is_array($matches)) $thumbnail = $matches[0];
			}
			if($thumbnail) $block_class .= "has_thumb ";
		}
		
		//class
			//If admins can add arbitrary classes, explode on spaces and merge with this array
		if($block->columns && $format == 'block') $classes[] = $numbers[$block->columns]." columns";
		if($block->rows) $classes[] = "r".$block->rows." rows";
		if($block->scroll=='true') $classes[] = "scroll";
					
		//background
		if($block->bg){
			$bg = sprintf(' style="background:%s" ',$block->bg);
			$classes[] = "has_bg";
		}else{$bg='';}
		
		
		switch($layout){
			case 'top':
				$block_format = '<article class="%1$s"%5$s>%6$s%7$s<h1><a href="%2$s">%3$s</a></h1><div class="excerpt">%4$s</div></article>';
				break;
			case 'bottom':
				$block_format = '<article class="%1$s"%5$s><h1><a href="%2$s">%3$s</a></h1><div class="excerpt">%4$s</div>%6$s%7$s</article>';
				break;
			default: 
				$block_format = '<article class="%1$s"%5$s>%6$s<h1><a href="%2$s">%3$s</a></h1><div class="excerpt">%4$s</div>%7$s</article>'; 
				break;
		}
		//format
		return sprintf($block_format,							//format
			join(' ',$classes),												//1. class
			$post['permalink'],											//2. permalink
			$title,																	//3. title
			strip_shortcodes($content),							//4. content
			$bg,																		//5. bg
			$format == 'sub' ? $thumbnail : '',			//6. pre-content
			$format == 'block' ? $thumbnail : ''		//7. post-content
			);
	}
	
	function make_dropdown_block($block,$post,$format){
		/*
		this needs to go before content, so we might need a hook to check for the URL, sadly
		
		if($_POST['redirect']){
			$url = parse_url($_POST['redirect']);
			$site = parse_url(site_url());
			if($url['hostname'] == $site['hostname']) header('Location',$_POST['redirect']);
		}
		*/
		if(!wp_script_is('toist-hub')){
			$url = parse_url(site_url());
			wp_enqueue_script('toist-hub',plugins_url('toist-hub.js',__FILE__),array('jquery'),'0.1',true);
			wp_localize_script('toist-hub','toistHub',array(
				'hostname'				=>	$url['host']
				));
		}
		
	
		$query = array('post_type'=>'any','posts_per_page'=>-1);
		$numbers = array("zero", "one", "two", "three", "four", "five", "six", "seven", "eight", "nine", "ten", "eleven", "twelve");
		$posts = false;
		$return = '<form method="post" class="redirect_dropdown %s" style="background:%s"><h1>%s</h1><select name="redirect">%s</select><input type="submit" class="submit" value="%s" /></form>';
		$query['tag_slug__in'] = explode(',',$block->tag);
		
		
		$class = array();
		if($block->columns) $class[] = $numbers[$block->columns]." columns";
		if($block->rows) $class[] = "r".$block->rows." rows";
		if($block->scroll=='true') $class[] = "scroll";
		if(intval($block->columns) > 3 ){$class[] = "long";}			
		else{$class []= "tall ";}
				
		switch($block->cf){
			case "stars":
				$query['meta_key'] = 'stars';
				$query['orderby'] = 'meta_value_num';
				$query['order']	= 'DESC';
				$posts = get_posts($query);
				//echo $posts->request;
				var_dump($posts);
				break;
			case '': 
				//alphabetical
				$posts = get_posts($query);
				usort($posts,function($a,$b){
					return (strip_tags($a->post_title) < strip_tags($b->post_title)) ? -1 : 1;
				});
				//map: strip_tags, use titles as key. Sort.
				break;
			default:
				//order by arbitrary meta key
				break;
		}
		if($posts && count($posts) > 0){
			$options = '<option></option>';
			foreach($posts as $post) 
				$options .= sprintf('<option value="%s">%s (%s)</option>',
					get_permalink($post->ID),
					strip_tags($post->post_title),
					self::create_stars(get_post_meta($post->ID,'stars',true))
					);
		}
		
		$return = sprintf($return,
			join(' ',$class),
			$block->bg ?: '',
			$block->customtext,
			$options,
			($block->cta && $block->cta != '') ?: 'Go'
			);
		
		return $return;
		
	}
	
	function create_stars($num){
		$return = '';
		for($i = 0; $i < 5; $i++){
			if($num - $i >= 1){$return .= '&#9733;';}
			elseif($num - $i > 0){ $return .= '&#189;';}
			else{$return .= '&#9734';}
		}
		return $return;
	}
	
	function make_html_block($block,$post,$format){
		//block->type = 'html'
		//block->content = <code>; sanitize before outputting
	}
	
	function make_loop_block($block,$post,$format){
		//Criteria:
			//By tag: tag slug
			//By cat: category slug
			//By custom field: CF name, value
			//By other meta: meta name, value
		//Title
		//Pre/post text
		
	}
		
	function make_blocks(){
		$numbers = array("zero", "one", "two", "three", "four", "five", "six", "seven", "eight", "nine", "ten", "eleven", "twelve");
		//at some point, we'll want to get hub id from the shortcode attr
		$return = '';
		
		//$return = get_transient('toist_hub_'.get_the_ID());
		//if($return === false){
			$hub = json_decode(get_post_meta(get_the_ID(),'toist_hub',true));
			$post_ids = array();
		
			//when we support multiple hubs per page, let's load all the ids up in here
			foreach($hub as $block){
				//test for commas; can specific multiple IDs
			
				if(!strpos($block->ids,',')){
					$post_ids[] = intval($block->ids);
				}else{
					$ids = explode(',',$block->ids);
					$post_ids = array_merge($post_ids,$ids);
				}
			}
		
			remove_filter('pre_get_posts','noindex_remover');
			//remove duplicate IDs
			$posts = new WP_Query(array(
				'post_type'		=>	'any',
				'post_status'	=>	'any',
				'post__in' 		=> $post_ids
			));
			add_filter('pre_get_posts','noindex_remover');
		
			$post_list = array();
		
		
			foreach($posts->posts as $post){
				$post_list[$post->ID] = array(
					'id'				=>	$post->ID,
					'author'		=>	$post->post_author,
					'title'			=>	$post->post_title,
					'content'		=>	$post->post_content,
					'date'			=>	$post->post_date,
					'permalink'	=>	get_permalink($post->ID),
					'alt_title'	=>	get_post_meta($post->ID,'alt_title',true),
					'dek'				=>	get_post_meta($post->ID,'dek',true),
					'alt_dek'		=>	get_post_meta($post->ID,'alt_dek',true)
				);
			}
							
			//render the blocks
			$block_container = '<div class="%1$s"%3$s>%2$s</div>';
			
		
			$return .= '<div class="hub-container"><div class="row">';
			$grid_cols = 0;
			foreach($hub as $block){
				
				//new row if the next block would overflow the grid
				if($grid_cols + $block->columns > 12){
					$grid_cols = $block->columns;
					$return .= '</div><div class="row">';
				}else{$grid_cols += $block->columns;}
				
				if(!strpos($block->ids,',')){
					$return .= self::make_block($block,$post_list[$block->ids]);
				}else{
					$ids = explode(',',$block->ids);
					$class[] = $numbers[$block->columns]." columns";
					if($block->rows) $class[] = "r".$block->rows." rows";
					if($block->scroll=='true') $class[] = "scroll";
					
					$subblock = '';
					foreach($ids as $id){
						$subblock .= self::make_block($block,$post_list[$id],'sub');
					}
					$return .= sprintf($block_container,
						join(' ',$class),
						$subblock,
						$bg
					
						);
					

				}
			}
		
			$return .= '</div><hr /></div>'; //end .hub-container
			//set_transient('toist_hub_'.get_the_ID(),$return,15 * MINUTE_IN_SECONDS);
		//}
		return $return;
	}
	
	function dummy(){}
	
	function ajax_wp_query(){
		$this->ajax_validate();
		$args = array(
			'post_type'			=>	'any',
			'post_status'		=> 	array('publish','draft','pending','future'),
			'posts_per_page'=>	'25'
		);
		if(isset($_POST['keyword'])) $args['s'] = $_POST['keyword'];
		//we might want to change this later to allow using ways to filter by cats/tags
		if(isset($_POST['hubsearch_page'])) $args['paged'] = intval($_POST['hubsearch_page']);
		
		remove_all_filters('pre_get_posts');	
		$posts = new WP_Query($args);
		
		$return = array();
		if($posts->have_posts()) while($posts->have_posts()): $posts->the_post();
			$return['results'][] = array(
				'id'				=>	get_the_ID(),
				'title'			=>	get_the_title() ?: "(no title)",
				'content'		=>	get_the_content(),
				'thumb'			=>	get_the_post_thumbnail(get_the_ID(),'large'),
				'dek'				=>	get_post_meta(get_the_ID(),'dek',true),
				'alt_dek'		=>	get_post_meta(get_the_ID(),'alt_dek',true),
				'alt_title'	=>	get_post_meta(get_the_ID(),'alt_title',true)
			);
		endwhile;
		$return['num_pages'] = $posts->max_num_pages;
		
		echo json_encode($return);
		exit;
	}

	function is_hex($string){
		$ret = preg_match('/^#[a-fA-F0-9]{3}$|^#[a-fA-F0-9]{6}$/',$string);
		return $ret == 1;
	}
	
	function hub_banner_page(){
		add_theme_page(
			"Customize Banner",
			"Banner",
			"edit_theme_options",
			"hub-banner",
			array($this,"hub_banner")
			);
	}

	function hub_banner(){
		//save settings
		if(
			isset($_POST['nonce']) 
			&& wp_verify_nonce($_POST['nonce'],'bannerNonce')
			){
			$save = array();
			$formatting = '<em><strong>';
			if(isset($_POST['label'])) 
				$save['label'] = strip_tags($_POST['label'],$formatting);
			if(isset($_POST['headline'])) 
				$save['headline'] = strip_tags($_POST['headline'],$formatting);
			if(isset($_POST['dek'])) 
				$save['dek'] = strip_tags($_POST['dek'],$formatting);
			if(isset($_POST['link'])) 
				$save['link'] = filter_var($_POST['link'],FILTER_SANITIZE_URL);
			if(isset($_POST['bgcol']) && $this->is_hex($_POST['bgcol']))
				$save['bgcol'] = filter_var($_POST['bgcol'],FILTER_SANITIZE_STRING);
			if(isset($_POST['bgimg'])) 
				$save['bgimg'] = filter_var($_POST['bgimg'],FILTER_SANITIZE_URL);
			if(isset($_POST['css'])) 
				$save['css'] = filter_var($_POST['css'],FILTER_SANITIZE_STRING);
			$save['on'] = isset($_POST['on']) && $_POST['on'] == 'on';
			
			
			update_option('hub_banner',$save);
		}else{$save = get_option('hub_banner');}
		
		wp_enqueue_script('media-upload');
		wp_enqueue_script('thickbox');
		wp_enqueue_script('toist_banner_admin',plugins_url('banner-admin.js',__FILE__),array('jquery','media-upload','thickbox'));
		wp_enqueue_style('thickbox');
		wp_enqueue_style('toist_banner_admin',plugins_url('banner-admin.css',__FILE__));
		wp_localize_script('toist_banner_admin','toistBanner',array(
			'target'					=>	admin_url('admin-ajax.php')
			));
		?>
		<div class="wrap">
			<h2>Coverage Hub Banner</h2>
			<div id="preview"><?php echo $this->hub_banner_render(true); ?></div>
			<form action="" method="POST">
				<p>
					<label for="label">Label</label>
					<input id="label" name="label" type="text" <?php if(isset($save['label'])) printf('value="%s"',stripslashes($save['label'])); ?> />
				</p>
				<p>
					<label for="headline">Headline</label>
					<input id="headline" name="headline" type="text" <?php if(isset($save['headline'])) printf('value="%s"',stripslashes($save['headline'])); ?> />
				</p>
				<p>
					<label for="dek">Dek</label>
					<input id="dek" name="dek" type="text" <?php if(isset($save['dek'])) printf('value="%s"',stripslashes($save['dek'])); ?> />
				</p>
				<p>
					<label for="link">Link</label>
					<input id="dek" name="link" type="text" <?php if(isset($save['link'])) printf('value="%s"',stripslashes($save['link'])); ?> />
				</p>
				<p>
					<label for="background-colour">Background colour</label>
					<input id="background-colour" name="bgcol" type="text" <?php if(isset($save['bgcol'])) printf('value="%s"',stripslashes($save['bgcol'])); ?> />
				</p>
				<p>
					<label for="background-image">Background image</label>
					<input id="background-image" name="bgimg" type="text" <?php if(isset($save['bgimg'])) printf('value="%s"',$save['bgimg']); ?> />
					<?php //if set, allow clear; show image ?>
				</p>
				<p>
					<label for="css">CSS</label>
					<textarea name="css" id="css" ><?php if(isset($save['css'])) echo stripslashes($save['css']); ?></textarea>
				</p>
				<p>
					<input type="checkbox" id="hub_banner_on" name="on" value="on" <?php
						if($save['on']) echo 'checked="checked"';
					
					?> />
					<label for="hub_banner_on">Banner active</label>
				</p>
				<?php wp_nonce_field('bannerNonce','nonce'); ?>
				<input type="submit" class="button button-primary" value="Save settings" />
			</form>
		</div>
		
		<?php
	}
	
	function hub_banner_render($preview = false,$show_override = false){
		$return = '';
		if($preview && is_array($preview)){
			$cfg = $preview;
		}else{
			$cfg = get_option('hub_banner');
			if($show_override) $cfg['on'] = true;
		}
		if($preview === false && (!isset($cfg['on']) || $cfg['on'] !== true)) return false;
		
		$return .= sprintf('<a href="%s" id="hub_banner"><div>',
			isset($cfg['link']) ? $cfg['link'] : '#'
			);
		if(isset($cfg['label'])) $return .= '<p class="label">'.stripslashes($cfg['label']).'</p>';
		if(isset($cfg['headline'])) $return .= '<h1>'.stripslashes($cfg['headline']).'</h1>';
		if(isset($cfg['dek'])) $return .= '<p class="dek">'.stripslashes($cfg['dek']).'</p>';
		$return .= '</div></a>';
		
		$return .= '<style type="text/css">';
		if(isset($cfg['bgcol'])) $return .= sprintf('a#hub_banner div{background-color:%s}',$cfg['bgcol']);
		if(isset($cfg['bgimg'])) $return .= sprintf('a#hub_banner{background-image:url("%s")}',$cfg['bgimg']);
		$return .= stripslashes($cfg['css']);
		$return .= '</style>';
		return $return;
	}
	
	function preview_banner(){
		$settings = $_POST['banner'];
		$banner = $this->hub_banner_render($settings);
		if($banner){
			echo $banner;
		}else{
			//error;
		}
		exit;
	}
	
	function ajax_validate(){
		if(
			!current_user_can('edit_posts') 
			|| !isset($_POST['nonce'])
			|| !wp_verify_nonce($_POST['nonce'],'torontoist_hub_admin')
			) die('Not authorized');
	}
	
	function form_textfield($args = array()){
		$args = wp_parse_args($args,
			array(
			 	'type' => 'text', 'value'=>'', 'placeholder' => '','label_for'=>'',
				 'size'=>false, 'min' => false, 'max' => false, 'style'=>false, 'echo'=>true,
				)
			);		

		$id = ( !empty($args['id']) ? $args['id'] : $args['label_for']);
		$name = $id;
		$value = $args['value'];
		$type = $args['type'];
		$class = isset($args['class']) ? esc_attr($args['class'])  : '';

		$set = get_option($args['id']);
		if($set) $value = $set;

		$min = (  !empty($args['min']) ?  sprintf('min="%d"', $args['min']) : '' );
		$max = (  !empty($args['max']) ?  sprintf('max="%d"', $args['max']) : '' );
		$size = (  !empty($args['size']) ?  sprintf('size="%d"', $args['size']) : '' );
		$style = (  !empty($args['style']) ?  sprintf('style="%s"', $args['style']) : '' );
		$placeholder = ( !empty($args['placeholder']) ? sprintf('placeholder="%s"', $args['placeholder']) : '');
		$disabled = ( !empty($args['disabled']) ? 'disabled="disabled"' : '' );
		$attributes = array_filter(array($min,$max,$size,$placeholder,$disabled, $style));

		$html = sprintf('<input type="%s" name="%s" class="%s" regular-text ltr" id="%s" value="%s" autocomplete="off" %s />',
			esc_attr($type),
			esc_attr($name),
			sanitize_html_class($class),
			esc_attr($id),
			esc_attr($value),
			implode(' ', $attributes)
		);

		if( isset($args['help']) ){
			$html .= '<p class="description">'.$args['help'].'</p>';
		}

		echo $html;
	}
	
	function form_checkbox($args){
		$args = wp_parse_args($args,
			array(
			 	'type' => 'checkbox', 'value'=>'', 'placeholder' => '','label_for'=>'',
				 'size'=>false, 'min' => false, 'max' => false, 'style'=>false, 'echo'=>true,
				)
			);
	
		$options = $args['options'];
		$html = '';
		$set = get_option($args['id']);
		
		if(is_array($options)) foreach($options as $value	=> $name):
			$attr = '';
			if($set == $value) $attr .= ' checked="checked" ';
			$html .= sprintf('<input type="checkbox" name="%s" value="%s" id="%s" %s /><label for="%s">%s</label>',
				$args['id'],
				$value,
				$args['id'].'-'.$value,
				$attr,
				$args['id'].'-'.$value,
				$name
				);
		endforeach;
	
		echo $html;
	}
}
$toist_hub = new Toist_Hub;

add_shortcode('special_topic',array('Toist_Hub','make_blocks'));

function the_hub_banner($show_override){
	global $toist_hub;
	if($banner = $toist_hub->hub_banner_render(false,$show_override)){
		echo $banner;
	}
}
?>

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
		
		add_action('admin_init',array($this,'options'));
		add_action('admin_menu',array($this,'hub_banner_page'));
	}
	
	function add_hub_button($context){
		return $context . '<a class="button" id="toist_hub_button">Add Hub</a>';
	}
	
	function queue_scripts($hook){
		if($hook == "post-new.php" || $hook == "post.php"){
			wp_enqueue_style('torontoist_hub_admin',plugins_url('hub-admin.css',__FILE__));
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
		
	function make_block(){
	
	}
		
	function make_blocks(){
		//at some point, we'll want to get hub id from the shortcode attr
		$numbers = array("zero", "one", "two", "three", "four", "five", "six", "seven", "eight", "nine", "ten", "eleven", "twelve");
		$return = '';
		
		$return = get_transient('toist_hub_'.get_the_ID());
		if($return === false){
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
			$block_format = '<article class="%1$s"%6$s><h1><a href="%2$s">%3$s</a></h1><div class="excerpt">%5$s</div>%4$s</article>';
			$block_container = '<div class="%1$s"%3$s>%2$s</div>';
			$subblock_format = '<article class="%5$s">%3$s<h1><a href="%1$s">%2$s</a></h1><div class="excerpt">%4$s</div></article>';
		
			$return .= '<div class="hub-container"><div class="row">';
			$grid_cols = 0;
			foreach($hub as $block){
				$block_class = '';
				if($grid_cols + $block->columns > 12){
					$grid_cols = $block->columns;
					$return .= '</div><div class="row">';
				}else{$grid_cols += $block->columns;}
						
				if($block->columns) $block_class .= $numbers[$block->columns]." columns ";
				if($block->rows) $block_class .= "r".$block->rows." rows ";
				if($block->scroll=='true') $block_class .= "scroll ";
				if(intval($block->columns) > 3 ){$block_class .= "long ";}			
				else{$block_class .= "tall ";}
			
				if(!strpos($block->ids,',')){
					if($block->title == 'alt_title'){
						$title = $post_list[$block->ids]['alt_title'];
					}else{
						$title = $post_list[$block->ids]['title'];
						}
					//$title = $post_list[$block->ids]['alt_title'] ?: $post_list[$block->ids]['title'];
					switch($block->text){
						case "dek": $content = $post_list[$block->ids]['dek']; break;
						case "alt_dek": $content = $post_list[$block->ids]['alt_dek']; break;
						case "custom": $content = stripslashes($block->customtext); break;
						default: $content = $post_list[$block->ids]['content']; break;
					}
					//$dek = $post_list[$block->ids]['alt_dek'] ?: $post_list[$block->ids]['dek'];
					//$content = $post_list[$block->ids]['content'];
					//if(intval($block->columns) <= 5 ){$content = $dek ?: $content;}
				
					$thumbnail = get_the_post_thumbnail($block->ids,'medium');
					if($thumbnail == ''){
						preg_match('|<img[^>]+>|',$post_list[$block->ids]['content'],$matches);
						if(is_array($matches)) $thumbnail = $matches[0];
					}
					if($thumbnail) $block_class .= "has_thumb ";
				
					if($block->bg){
						$bg = sprintf(' style="background:%s" ',$block->bg);
						$block_class .= "has_bg ";
					}else{$bg='';}
								
					$return .= sprintf($block_format,
						$block_class,
						$post_list[$block->ids]['permalink'],
						$title,
						$thumbnail,
						strip_shortcodes($content),
						$bg
						);
				}else{
					$ids = explode(',',$block->ids);
					$subblock = '';
					foreach($ids as $id){
						$class = array();
						$title = $post_list[$block->ids]['alt_title'] ?: $post_list[$block->ids]['title'];
						//$dek = $post_list[$block->ids]['alt_dek'] ?: $post_list[$block->ids]['dek'];
						$thumbnail = get_the_post_thumbnail($id,'medium');
						if($thumbnail == ''){
							preg_match('|<img[^>]+>|',$post_list[$block->ids]['content'],$matches);
							if(is_array($matches)) $thumbnail = $matches[0];
						}
						if($thumbnail) $class[] = 'has_thumb';
						switch($block->text){
							case "dek": $content = $post_list[$id]['dek']; break;
							case "alt_dek": $content = $post_list[$id]['alt_dek']; break;
							case "custom": $content = strip_tags($block->customtext); break;
							default: $content = $post_list[$id]['content']; break;
						}
						if($block->title == 'title'){
							$title = $post_list[$id]['title'];
						}else{$title = $post_list[$id]['alt_title'];}
						if($block->bg){
							$bg = sprintf(' style="background:%s" ',$block->bg);
						}else{$bg='';}
						$subblock .= sprintf($subblock_format,
							$post_list[$id]['permalink'],
							$title,
							$thumbnail,
							strip_shortcodes($content),
							join(' ',$class)
							);
					}
					$return .= sprintf($block_container,
						$block_class,
						$subblock,
						$bg
						);
				}
			}
		
			$return .= '</div><hr /></div>'; //end .hub-container
			set_transient('toist_hub_'.get_the_ID(),$return,15 * MINUTE_IN_SECONDS);
		}
		return $return;
	}
	
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
		global $pagenow;		
		if(isset($_POST['nonce']) && wp_verify_nonce($_POST['nonce'],'hubNonce')){
			if(isset($_POST['hub_banner_on'])){
				update_option('hub_banner_on',true);
			}else{
			update_option('hub_banner_on',false);
			}
			if(isset($_POST['hub_banner_code'])){
				update_option('hub_banner_code',$_POST['hub_banner_code']);
			}
			if(isset($_POST['hub_banner_link'])){
				update_option('hub_banner_link',$_POST['hub_banner_link']);
			}
			if(isset($_POST['hub_banner_css'])){
				update_option('hub_banner_css',$_POST['hub_banner_css']);
			}
		}
		
		?>
		<div class="wrap">
			<?php screen_icon('options-general'); ?>
			<h2>Coverage Hub Banner</h2>
			<form action="" method="POST">
				<p>
					<label for="hub_banner_code">HTML</label>
					<textarea name="hub_banner_code" id="hub_banner_code" style="display:block;width:500px"><?php echo stripslashes(get_option('hub_banner_code')); ?></textarea>
				</p>
				<p>
					<label for="hub_banner_css">CSS</label>
					<textarea name="hub_banner_css" id="hub_banner_css" style="display:block;width:500px"><?php echo stripslashes(get_option('hub_banner_css')); ?></textarea>
				</p>
				<p>
					<label for="hub_banner_link">Link</label>
					<input type="" name="hub_banner_link" id="hub_banner_link" value="<?php echo stripslashes(get_option('hub_banner_link')); ?>" />
				</p>
				<p>
					<input type="checkbox" id="hub_banner_on" name="hub_banner_on" value="on" <?php
						if(get_option('hub_banner_on')) echo 'checked="checked"';
					
					?> />
					<label for="hub_banner_on">Banner active</label>
				</p>
				<?php wp_nonce_field('hubNonce','nonce'); ?>
				<input type="submit" class="button button-primary" value="Save settings" />
			</form>
		</div>
		
		<?php
		
		/*
		?>
		<div class="wrap">
			<?php screen_icon('options-general'); ?>
			<h2>Coverage Hub Banner</h2>
			<form action="options.php" method="POST">
				<?php settings_fields('hub-banner'); ?>
				<?php do_settings_sections('hub-banner'); ?>
				<?php submit_button(); ?>
			</form>
		</div>
		<?php
		*/
	}
	
	function options(){
	/*
		add_settings_section(
			'toist_banner_opts',
			'Special Coverage Banner',
			array($this,'generic_form'),
			'toist_banner'
			);
		add_settings_field(
			'toist_banner_on',
			'Bit.ly token',
			array($this,'form_checkbox'),
			'toist_banner',
			'toist_banner_opts',
			array(
				'id'=>'toist_banner_on'
				)
		);
		add_settings_field(
			'toist_banner_code',
			'Bit.ly token',
			array($this,'form_textfield'),
			'toist_banner',
			'toist_banner_opts',
			array(
				'id'=>'toist_banner_on'
				)
		);
				
		register_setting('toist_banner','toist_banner_on');
		register_setting('toist_banner','toist_banner_code');
		*/
	}
	
	function generic_form(){
	
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
	
	/*
	function closetags($html){
		preg_match_all('#<([a-z]+)(?: .*)?(?<![/|/ ])>#iU', $html, $result);
		$openedtags = $result[1];
		preg_match_all('#</([a-z]+)>#iU', $html, $result);
		$closedtags = $result[1];
		$len_opened = count($openedtags);

		if (count($closedtags) == $len_opened) {
		  return $html;
		}
		$openedtags = array_reverse($openedtags);

		for ($i=0; $i < $len_opened; $i++) {
		  if (!in_array($openedtags[$i], $closedtags)){
		    $html .= '</'.$openedtags[$i].'>';
		  } else {
		    unset($closedtags[array_search($openedtags[$i], $closedtags)]);    }
		}  return $html;
	}
	*/
}
$toist_hub = new Toist_Hub;

add_shortcode('special_topic',array('Toist_Hub','make_blocks'));

function the_hub_banner(){

	if(get_option('hub_banner_on') && get_option('hub_banner_on') == true){
		$banner = get_option('hub_banner_code');
		$css = get_option('hub_banner_css');
		$href = get_option('hub_banner_link');
		
		if($banner && $css && $href){
		printf('<a href="%s" id="hub_banner">%s</a><style type="text/css">%s</style>',
			stripslashes($href),stripslashes($banner),stripslashes($css)
			);
		}
	}
}
?>

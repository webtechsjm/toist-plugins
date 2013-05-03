<?php
/**

@package Torontoist

Plugin Name: Torontoist Newsflash
Plugin URI: http://torontoist.com
Description: Provides an easy-to-use banner
Version: 0.1
Author: Senning Luk
Author URI: http://puppydogtales.ca
License: GPLv2 or later
*/

class Toist_Newsflash{
	function __construct(){
		//add_action('admin_enqueue_scripts',array($this,'queue_scripts'));
		add_action('admin_menu',array($this,'newsflash_page'));
		add_action('admin_enqueue_scripts',array($this,'queue_scripts'));
	}
	

	function newsflash_page(){
		add_theme_page(
			"Newsflash",
			"Newsflash",
			"edit_theme_options",
			"newsflash",
			array($this,"newsflash_admin")
			);
	}
	
	function queue_scripts($hook){
		if($hook == 'appearance_page_newsflash'){
			//queue script and style
			wp_enqueue_style('toist_newsflash_admin',plugins_url('admin.css',__FILE__),array(),'0.2');
			wp_enqueue_script('toist_newsflash_admin',plugins_url('admin.js',__FILE__));
		}
	}
	
	function render(){
		$opts = get_option('newsflash_options');
		extract($opts);
		
		$return = sprintf('<aside class="breaking %s">',$colour);
		$return .= sprintf('<hgroup><h2 class="meta">%s</h2><h1>%s</h1></hgroup><h3>%s</h3>',
			stripslashes($label),
			stripslashes($headline),
			stripslashes($dek)
			);
		$link_row = '';
		foreach($links as $link){
			$link_row .= sprintf(
				'<a href="%s">%s</a>',
				stripslashes($link['url']),
				stripslashes($link['label'])
				);
		}
		$return .= sprintf('<p class="links">%s</p>',$link_row);
		$return .='</aside>';
						
		if($accent && $background && $colour='Custom'){
			$return .= sprintf('<style>.breaking.Custom{background-color:%1$s}.breaking.Custom h2{border-color:%2$s;}.breaking .links a:link{color:%2$s;}</style>',$background,$accent);
		}
		return $return;
	}
	
	function is_hex($string){
		$ret = preg_match('/^#[a-fA-F0-9]{3}$|^#[a-fA-F0-9]{6}$/',$string);
		return $ret == 1;
	}
	
	function newsflash_admin(){
		global $pagenow;
		$colours = array("Blue","Green","Purple","Salmon","Brown","Grey","Orange","Custom");

		if(isset($_POST['nonce']) && wp_verify_nonce($_POST['nonce'],'hubNonce')){
			$nf = $_POST['newsflash'];
			$cfg = array();
			if(isset($nf['label'])){
				$cfg['label'] = filter_var($nf['label'],FILTER_SANITIZE_STRING);
				}
			if(isset($nf['headline'])){
				$cfg['headline'] = filter_var($nf['headline'],FILTER_SANITIZE_STRING);
				}
			if(isset($nf['dek'])){
				$cfg['dek'] = filter_var($nf['dek'],FILTER_SANITIZE_STRING);
				}
			foreach($nf['link']['label'] as $key=>$label){
				if($label && $nf['link']['url'][$key]){
					$cfg['links'][] = array(
						'label'	=>	filter_var($label,FILTER_SANITIZE_STRING),
						'url'	=>	filter_var($nf['link']['url'][$key],FILTER_SANITIZE_URL)
					);
				}
			}
			if(isset($nf['colour']) && in_array($nf['colour'],$colours)){
				$cfg['colour'] = $nf['colour'];
			}
			
			if(isset($nf['accent']) && $this->is_hex($nf['accent'])){
				$cfg['accent'] = filter_var($nf['accent'],FILTER_SANITIZE_STRING);
			}
			if(isset($nf['background']) && $this->is_hex($nf['background'])){
				$cfg['background'] = filter_var($nf['background'],FILTER_SANITIZE_STRING);
			}
			update_option('newsflash_options',$cfg);
			
			if(isset($nf['on'])){
				update_option('newsflash_on',true);
				}else{update_option('newsflash_on',false);}
			
		}
		
		?>
		<div class="wrap">
			<?php 
			screen_icon('options-general'); 
			$opts = get_option('newsflash_options');
			?>
			<h2>Newsflash</h2>
			<div id="preview">
				<?php echo $this->render(); ?>
			</div>
			<form action="" method="POST">
				<h3>Labels</h3>
				<p>
					<label for="newsflash_label">Label</label>
					<input type="text" name="newsflash[label]" id="newsflash_label" value="<?php echo isset($opts['label']) ? stripslashes($opts['label']) : 'Breaking News'; ?>" />
				</p>
				<p>
					<label for="newsflash_headline">Headline</label>
					<input type="text" name="newsflash[headline]" id="newsflash_headline" value="<?php echo isset($opts['headline']) ? stripslashes($opts['headline']) : ''; ?>" />
				</p>
				<p>
					<label for="hub_banner_link">Dek</label>
					<input type="text" name="newsflash[dek]" id="newsflash_dek" value="<?php echo isset($opts['dek']) ? stripslashes($opts['dek']) : ''; ?>" />
				</p>
				<h3>Links</h3>
				<table id="links">
					<thead>
						<th width="47%">Name</th>
						<th width="47%">URL</th>
						<th width="6%">Remove</th>
					</thead>
					<tbody>
					<?php	
					$i = 0;
					foreach($opts['links'] as $link): 
					?>
						<tr>
							<td><input type="text" name="newsflash[link][label][]" value="<?php echo isset($link['label']) ? stripslashes($link['label']) : ''; ?>" /></td>
							<td><input type="text" name="newsflash[link][url][]" value="<?php echo isset($link['url']) ? stripslashes($link['url']) : ''; ?>" /></td>
							<td><a href="#" class="button">-</a></td>
						</tr>
					<?php	
					$i++;
					endforeach;	
					if(!isset($opts['links']) || count($opts['links']) == 0):
					?>
						<tr>
							<td><input type="text" name="newsflash[link][label][]" /></td>
							<td><input type="text" name="newsflash[link][url][]" /></td>
							<td><a href="#" class="button">-</a></td>
						</tr>
					<?php endif; ?>
					</tbody>
					<tfoot>
						<td><a href="#" class="button">+ Add link</a></td>
					</tfoot>
				</table>
				
				<h3>Colours</h3>
				<p>
				<?php
					$set_colour = $opts['colour']; 
					
					foreach($colours as $colour){
						printf('<input type="radio" name="newsflash[colour]" id="%1$s" value="%1$s"%2$s/><label for="%1$s">%1$s</label>',
						$colour,
						$colour == $set_colour ? 'checked="checked"' : ''
						);
					}
					?>
				</p>
				<p>
					<label for="newsflash_accent" class="long">Newsflash accent</label>
					<input type="text" id="newsflash_accent" name="newsflash[accent]" 
					value="<?php echo stripslashes($opts['accent']); ?>" />
					<label for="newsflash_background" class="long">Newsflash background</label>
					<input type="text" id="newsflash_background" name="newsflash[background]" 
					value="<?php echo stripslashes($opts['background']); ?>" />
				</p>
				<h3>Activate</h3>
				<p>
					<input type="checkbox" id="newsflash_on" name="newsflash[on]" value="on" <?php
						if(get_option('newsflash_on') == true) echo 'checked="checked"';
					?> />
					<label for="newsflash_on" class="long">Banner active</label>
				</p>
				
				<?php wp_nonce_field('hubNonce','nonce'); ?>
				<input type="submit" class="button button-primary" value="Save banner" />
			</form>
		</div>
		
		<?php
	}
	
	/*
	function options(){
	
	}
	
	function generic_form(){
	
	}
	*/
	
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
$toist_newsflash = new Toist_Newsflash;

function the_newsflash(){
	if(get_option('newsflash_on') && get_option('newsflash_on') == true){
		global $toist_newsflash;
		echo $toist_newsflash->render();
	}
}
?>

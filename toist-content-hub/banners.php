<?php
/**

@package Torontoist

Plugin Name: Torontoist Culture Pillar Banner
Plugin URI: http://torontoist.com
Description: A copy of the hub banner specific to the culture pillar
Version: 0.2
Author: Senning Luk
Author URI: http://puppydogtales.ca
License: GPLv2 or later
*/
class Toist_Banners{
	//We can lazily generalize this by just reinstantiating this every time we need a new banner.
	//Accept a label, apply it to admin setup
	function __construct(){	
		add_action('admin_menu',array($this,'banner_admin_setup'));
		add_action('wp_ajax_page_banner_preview',array($this,'preview_banner'));
	}	
	
	function is_hex($string){
		$ret = preg_match('/^#[a-fA-F0-9]{3}$|^#[a-fA-F0-9]{6}$/',$string);
		return $ret == 1;
	}

	
	function banner_admin_setup(){
		add_theme_page(
			"Customize Banner",
			"Culture Banner",
			"edit_theme_options",
			"culture-banner",
			array($this,"banner_admin")
			);
	}

	function banner_admin(){
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
			
			
			update_option('culture_banner',$save);
		}else{$save = get_option('culture_banner');}
		
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
			<h2>Culture Banner</h2>
			<div id="preview"><?php echo $this->banner_render(true); ?></div>
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
	
	function banner_render($preview = false,$show_override = false){
		$return = '';
		if($preview && is_array($preview)){
			$cfg = $preview;
		}else{
			$cfg = get_option('culture_banner');
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
		$banner = $this->banner_render($settings);
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
}
$culture_banner = new Toist_Banners();

function culture_banner($show_override){
	global $culture_banner;
	if($banner = $culture_banner->banner_render(false,$show_override)){
		echo $banner;
	}
}

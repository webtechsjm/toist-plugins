<?php
/*
Plugin Name: Torontoist Gallery Fixes
Plugin URI:
Version: 0.1
Description: Fixes the gallery in WordPress 3.5 so attachment pages get passed the [ids] attribute as a list
Author: Senning Luk
Author URI: http://puppydogtales.ca
*/

//We're replacing the gallery shortcode so we can customize it. Let's turn this off as soon as possible
remove_shortcode('gallery');
add_shortcode('gallery','toist_gallery_shortcode');
add_filter('attachment_fields_to_edit','toist_gallery_image_id',10,2);

function toist_gallery_shortcode($attr) {
	$post = get_post();

	static $instance = 0;
	$instance++;

	if ( ! empty( $attr['ids'] ) ) {
		// 'ids' is explicitly ordered, unless you specify otherwise.
		if ( empty( $attr['orderby'] ) )
			$attr['orderby'] = 'post__in';
		$attr['include'] = $attr['ids'];
	}

	// Allow plugins/themes to override the default gallery template.
	$output = apply_filters('post_gallery', '', $attr);
	if ( $output != '' )
		return $output;

	// We're trusting author input, so let's at least make sure it looks like a valid orderby statement
	if ( isset( $attr['orderby'] ) ) {
		$attr['orderby'] = sanitize_sql_orderby( $attr['orderby'] );
		if ( !$attr['orderby'] )
			unset( $attr['orderby'] );
	}
	
	extract(shortcode_atts(array(
		'order'      => 'ASC',
		'orderby'    => 'menu_order ID',
		'id'         => $post->ID,
		'itemtag'    => 'dl',
		'icontag'    => 'dt',
		'captiontag' => 'dd',
		'columns'    => 3,
		'size'       => 'thumbnail',
		'include'    => '',
		'exclude'    => '',
		'feature'		 =>	''
	), $attr));

	$id = intval($id);
	if ( 'RAND' == $order )
		$orderby = 'none';

	if(is_attachment()){
		if($_GET['include']){
			//SANITIZE ME
			$include = $_GET['include'];
		}
	}

	if ( !empty($include) ) {
		$_attachments = get_posts( array('include' => $include, 'post_status' => 'inherit', 'post_type' => 'attachment', 'post_mime_type' => 'image', 'order' => $order, 'orderby' => $orderby) );

		$attachments = array();
		foreach ( $_attachments as $key => $val ) {
			$attachments[$val->ID] = $_attachments[$key];
		}
			
		$include_query = "include=".$include;
		add_filter('attachment_link',function($link,$id = false) use (&$include_query){
			if(!strpos($link,"include=")){
				if(strpos($link,'?')){
					return $link.'&'.$include_query;
				}else{
					return $link.'?'.$include_query;
				}
			}else{return $link;}
		});
				
	} elseif ( !empty($exclude) ) {
		$attachments = get_children( array('post_parent' => $id, 'exclude' => $exclude, 'post_status' => 'inherit', 'post_type' => 'attachment', 'post_mime_type' => 'image', 'order' => $order, 'orderby' => $orderby) );
	} else {
		$attachments = get_children( array('post_parent' => $id, 'post_status' => 'inherit', 'post_type' => 'attachment', 'post_mime_type' => 'image', 'order' => $order, 'orderby' => $orderby) );
	}


	if ( empty($attachments) )
		return '';

	if ( is_feed() ) {
		$output = "\n";
		foreach ( $attachments as $att_id => $attachment )
			$output .= wp_get_attachment_link($att_id, $size, true) . "\n";
		return $output;
	}

	$itemtag = tag_escape($itemtag);
	$captiontag = tag_escape($captiontag);
	$columns = intval($columns);
	$itemwidth = $columns > 0 ? floor(100/$columns) : 100;
	$float = is_rtl() ? 'right' : 'left';

	$selector = "gallery-{$instance}";

	$gallery_style = $gallery_div = '';
	if ( apply_filters( 'use_default_gallery_style', true ) )
		$gallery_style = "
		<style type='text/css'>
			#{$selector} {
				margin: auto;
			}
			#{$selector} .gallery-item {
				float: {$float};
				margin-top: 10px;
				text-align: center;
				width: {$itemwidth}%;
			}
			#{$selector} .gallery-caption {
				margin-left: 0;
			}
		</style>
		<!-- see gallery_shortcode() in wp-includes/media.php -->";
	$size_class = sanitize_html_class( $size );
	$gallery_div = "<div id='$selector' class='gallery galleryid-{$id} gallery-columns-{$columns} gallery-size-{$size_class}'>";
	$output = apply_filters( 'gallery_style', $gallery_style . "\n\t\t" . $gallery_div );

	//add featured image
	if(isset($feature) && $feature != '' && get_post_type() != 'event'){
		$post = get_post($feature);
				
		if(!empty($include)){
			$images = explode(',',$include);
			$url = get_attachment_link($images[0]);
		}else{
			$url = get_attachment_link(get_post_thumbnail_id());
		}
				
		$output .= sprintf('<div id="attachment_%s" class="aligncenter"><a href="%s"><img src="%s" title="%s" /></a></div>',
			$feature,
			$url,
			$post->guid,
			$post->post_name
			);
		
	}
	
	$i = 0;
	foreach ( $attachments as $id => $attachment ) {
		$link = isset($attr['link']) && 'file' == $attr['link'] ? wp_get_attachment_link($id, $size, false, false) : wp_get_attachment_link($id, $size, true, false);

		$output .= "<{$itemtag} class='gallery-item'>";
		$output .= "
			<{$icontag} class='gallery-icon'>
				$link
			</{$icontag}>";
		if ( $captiontag && trim($attachment->post_excerpt) ) {
			$output .= "
				<{$captiontag} class='wp-caption-text gallery-caption'>
				" . wptexturize($attachment->post_excerpt) . "
				</{$captiontag}>";
		}
		$output .= "</{$itemtag}>";
		if ( $columns > 0 && ++$i % $columns == 0 )
			$output .= '<br style="clear: both" />';
	}

	$output .= "
			<br style='clear: both;' />
		</div>\n";

	//clean up the filter
	global $wp_filter;
	/*
	var_dump(end($wp_filter['attachment_link']));
	
	$end = count($wp_filter['attachment_link']);
	for($i = $end; $i > 0; $i--){
		echo gettype($wp_filter['attachment_link'][$i]);
	}
	*/
	unset($wp_filter['attachment_link']);

	return $output;
}

function toist_gallery_image_id($form_fields,$post){
	$form_fields['image_id'] = array(
		'label'		=>	'Image ID',
		'input'		=>	'text',
		'value'		=>	$post->ID,
		'helps'		=>	'Building a gallery? Add <code>feature="<strong>'.$post->ID.'</strong>"</code> between the square brackets and this will be the big, top image.'
		);
	
	return $form_fields;
}

?>

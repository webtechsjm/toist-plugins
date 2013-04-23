<?php
/*
Plugin Name: Featured Media
Plugin URI:
Version: 0.1
Description: A single function to feature embedded media and captioned featured images.
Author: Senning Luk
Author URI: http://puppydogtales.ca
*/

/*
add_action( 'pre_get_posts', 'toist_eo_hide_past_events',7);
function toist_eo_hide_past_events( $query ){
	//if( $query->is_main_query() && 'event' == $query->get('post_type') ){
	if(is_tax(array('event-category','event-tag','event-venue'))){
		$query->set('showpastevents',0);
	}
	return $query;
}
*/

add_action('add_meta_boxes','featured_media_metabox');
add_action('save_post','featured_media_save_postdata');

function featured_media_metabox(){
	$types = array('event');
	
	foreach($types as $type){
		add_meta_box(
			'featured_media',
			__('Featured media','torontoist'),
			'featured_media_box',
			$type,
			'side'
		);
	}
}

function featured_media_box($post){
	wp_nonce_field(plugin_basename(__FILE__),'featured_media_nonce');
	
	$val = get_post_meta($post->ID,'_featured_media_url',true);
	printf('<label for="featured_media_url">Media URL:</label><input type="text" id="featured_media_ur" name="featured_media_url" value="%s"><p><em>The actual URL, not the one provided from an embed code iframe. <a href="http://codex.wordpress.org/Embeds">Read more</a></em></p>',
		$val);
}

function featured_media_save_postdata($post_id){
	if(defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
	if(!isset($_POST['featured_media_nonce']) 
		|| !wp_verify_nonce(
			$_POST['featured_media_nonce'], 
			plugin_basename(__FILE__)
			)
		) return;
	$url = sanitize_text_field($_POST['featured_media_url']);	
	update_post_meta($post_id,'_featured_media_url',$url);
}

function the_featured_media($size = 'full'){
	global $post;
	$featured_embed = get_post_meta($post->ID,'_featured_media_url',true);
	if($featured_embed && $embed = wp_oembed_get($featured_embed)){
		echo $embed;
	}elseif(has_post_thumbnail($post->ID)){
							
		the_post_thumbnail($size); 
		$thumb_id = get_post_thumbnail_id($post->ID);
		$attachment =& get_post($thumb_id);
		if($attachment->post_excerpt){
			printf(
				'<p class="wp-caption-text">%s</p>',
				apply_filters('img_caption_shortcode',$attachment->post_excerpt)
				);
		}
	}
}

?>

<?php
/*
@package Torontoist

Plugin Name: Torontoist Featured Tag fixer
Plugin URI: http://torontoist.com
Description: Finds posts with the feature_tag postmeta and changes them to featured_tag
Version: 0.1
Author: Senning Luk
Author URI: http://puppydogtales.ca
License: GPLv2 or later
*/

add_action('admin_menu',function(){
	add_submenu_page(
		'edit.php',
		'Feature tag fixer',
		'Feature tag fixer',
		'edit_posts',
		'feature_fixer',
		'feature_fix_page'
	);

});

function feature_fix_page(){

	$posts = new WP_Query(array(
		"post_type"					=> array('event','post','page'),
		"post_status"				=>	'any',
		"posts_per_page"		=>	100,
		"meta_query"	=>	array(
			array("key"		=>	"feature_tag")
		)
	));
	
	if($posts->have_posts()) while($posts->have_posts()): $posts->the_post();
		$id = get_the_ID();
		$val = get_post_meta($id,'feature_tag',true);
		update_post_meta($id,'featured_tag',$val);
		delete_post_meta($id,'feature_tag');
		
		echo get_permalink()."<br />";
		
	endwhile;
	
	printf("<strong>%d posts fixed</strong>",
		count($posts->posts)
	);
	
}

?>

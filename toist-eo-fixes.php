<?php
/*
Plugin Name: Torontoist Event Organiser Fixes
Plugin URI:
Version: 0.1
Description: Adds a button to the editor to generate the Event Organiser shortcode
Author: Senning Luk
Author URI: http://puppydogtales.ca
*/

add_action( 'pre_get_posts', 'toist_eo_hide_past_events',7);
function toist_eo_hide_past_events( $query ){
	//if( $query->is_main_query() && 'event' == $query->get('post_type') ){
	if(is_tax(array('event-category','event-tag','event-venue'))){
		$query->set('showpastevents',0);
	}
	return $query;
}


function toist_eo_hybrid_query_vars( $qvars ){
	//Add these query variables
	$qvars[] = 'reviews';
	return $qvars;
}
add_filter('query_vars', 'toist_eo_hybrid_query_vars' );

function toist_eo_hybrid_archive_pre_get_posts($query){
	if(!empty($query->query_vars['reviews'])){
		$query->set('meta_key','_include_in_feed');
		$query->set('event_end_after','today');
	}
}
add_filter('pre_get_posts','toist_eo_hybrid_archive_pre_get_posts');

?>

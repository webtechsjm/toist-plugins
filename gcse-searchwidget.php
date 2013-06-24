<?php
/**
 * @package Torontoist
 */
/*
Plugin Name: GCSE Search Widget
Plugin URI: http://torontoist.com
Description: A Google custom search widget
Version: 0.1
Author: Senning Luk
Author URI: http://puppydogtales.ca
License: GPLv2 or later
*/
class GCSearch_Widget extends WP_Widget {

	function __construct() {
		$widget_ops = array('classname' => 'widget_gcsearch', 'description' => __( "A Google custom search form") );
		parent::__construct('gcsearch', __('Google Search'), $widget_ops);
	}

	function widget( $args, $instance ) {
		extract($args);
		$title = apply_filters( 'widget_title', empty( $instance['title'] ) ? '' : $instance['title'], $instance, $this->id_base );

		echo $before_widget;
		if ( $title )
			echo $before_title . $title . $after_title;

		// Use current theme search form if it exists
		?>
		<form method="GET" action="<?php echo site_url(); ?>/search/">
			<input type="search" id="search" placeholder="SEARCH TORONTOIST" name="q" />
			<input type="image" glass="go" src="<?php echo get_stylesheet_directory_uri() ?>/images/graphics/search-btn-grey.png" value="Search" />
		</form>
		<?php

		echo $after_widget;
	}

	function form( $instance ) {
		$instance = wp_parse_args( (array) $instance, array( 'title' => '') );
		$title = $instance['title'];
?>
		<p><label for="<?php echo $this->get_field_id('title'); ?>"><?php _e('Title:'); ?> <input class="widefat" id="<?php echo $this->get_field_id('title'); ?>" name="<?php echo $this->get_field_name('title'); ?>" type="text" value="<?php echo esc_attr($title); ?>" /></label></p>
<?php
	}

	function update( $new_instance, $old_instance ) {
		$instance = $old_instance;
		$new_instance = wp_parse_args((array) $new_instance, array( 'title' => ''));
		$instance['title'] = strip_tags($new_instance['title']);
		return $instance;
	}
}
add_action('widgets_init',function(){
	return register_widget('GCSearch_Widget');
});
?>

<?php

/**
 * @package Torontoist
 */
/*
Plugin Name: Torontoist Picks and Tagged widgets
Plugin URI: http://torontoist.com
Description: Widgets for Editor's Picks and Special Events
Version: 0.1
Author: Senning Luk
Author URI: http://puppydogtales.ca
License: GPLv2 or later
*/

class Toist_Editors_Picks extends WP_Widget {

	function __construct() {
		$widget_ops = array('classname' => 'widget_editors_picks', 'description' => __( 'List the picked posts' ) );
		parent::__construct('toist-editors-picks', __('Torontoist Editor\'s Picks'), $widget_ops);
		$this->alt_option_name = 'toist_editors_picks';
	}

	function widget( $args, $instance ) {
		global $comments, $comment;
		extract($args, EXTR_SKIP);
		
		//check if we have this cached
		//$picks = get_transient('toist-editors-picks');
		$picks = false;
		if($picks === false){
			//remove_filter('pre_get_posts','noindex_remover');
			global $wp_filter;
			$filters = $wp_filter['pre_get_posts'];
			$wp_filter['pre_get_posts'] = array();
			$q = new WP_Query(array(
				'post_type'	=> 'any',
				'posts_per_page'	=>	$instance['number'],
				//'tag'	=>	'editors-pick',
				'tax_query'	=>	array(
					'relation'		=>	'OR',
					array(
						'taxonomy'	=>	'post_tag',
						'field'			=>	'slug',
						'terms'			=>	'editors-pick',
						'include_children'	=>	false
					),
					array(
						'taxonomy'	=>	'event-tag',
						'field'			=>	'slug',
						'terms'			=>	'editors-pick',
						'include_children'	=> false
					)
				),
				'suppress_filters'	=>	'true'
			));
			//add_filter('pre_get_posts','noindex_remover');
			$wp_filter['pre_get_posts'] = $filters;
			$picks = $q->posts;
			set_transient('toist-editors-picks',$picks,15 * MINUTE_IN_SECONDS);
		}
				
		if($picks && count($picks) > 0): 
		echo $before_widget;
		global $post;
		$count = 1;
		$rowcount = 1;
		
		$title = apply_filters('widget_title',$instance['title']);
		?>
		<section id="picks">
			<?php echo $before_title.$title.$after_title; ?>
			<table>
			<?php foreach($picks as $post):
		
			if ( $count&1 ) { echo "<tr>"; } ?>
				<td>
					<?php edit_post_link('[Edit]','',' '); ?>   
					<a href="<?php the_permalink(); ?>" class="title">
					<?php if ($post_alt_title = get_post_meta($post->ID, 'alt_title', true)) {
						echo $post_alt_title;
						}
						else {
							the_title();
							}
					?>
					</a>
					<?php if (($count+$rowcount)&1) : ?>
						<a href="<?php the_permalink() ?>" class="image">
							<?php if ($post_alt_image = get_post_meta($post->ID, 'alt_image', true)) {
								echo $post_alt_image;
								}
								else {
									the_post_thumbnail('large_thumb');
									}
									?>
						</a>
					<? else: ?>
						<a href="<?php the_permalink(); ?>" class="dek">
							<?php if ($post_alt_dek = get_post_meta($post->ID, 'alt_dek', true)) {
								echo $post_alt_dek;
								} 
								elseif ($post_dek = get_post_meta($post->ID, 'dek', true)) {
									echo $post_dek;
								}
								?>
						</a>
					<? endif; ?>
				</td>
      <?php $count ++; 
		  if ( $count&1 ) { echo "</tr>"; $rowcount ++;}
		endforeach; ?>
		</table>
	</section>
	<?php
	endif;
	echo $after_widget;
	}

	function update( $new_instance, $old_instance ) {
		$instance = $old_instance;
		$instance['title'] = strip_tags($new_instance['title']);
		$instance['number'] = absint( $new_instance['number'] );

		return $instance;
	}

	function form( $instance ) {
		$title = isset($instance['title']) ? esc_attr($instance['title']) : '';
		$number = isset($instance['number']) ? absint($instance['number']) : 5;
?>
		<p><label for="<?php echo $this->get_field_id('title'); ?>"><?php _e('Title:'); ?></label>
		<input class="widefat" id="<?php echo $this->get_field_id('title'); ?>" name="<?php echo $this->get_field_name('title'); ?>" type="text" value="<?php echo $title; ?>" /></p>

		<p><label for="<?php echo $this->get_field_id('number'); ?>"><?php _e('Number of comments to show:'); ?></label>
		<input id="<?php echo $this->get_field_id('number'); ?>" name="<?php echo $this->get_field_name('number'); ?>" type="text" value="<?php echo $number; ?>" size="3" /></p>
<?php
	}
}
add_action('widgets_init',function(){
	return register_widget('Toist_Editors_Picks');
});


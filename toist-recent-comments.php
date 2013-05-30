<?php

/**
 * @package Torontoist
 */
/*
Plugin Name: Torontoist Comments Widgets
Plugin URI: http://torontoist.com
Description: Widgets for recent comments and most commented posts
Version: 0.1
Author: Senning Luk
Author URI: http://puppydogtales.ca
License: GPLv2 or later
*/

class Toist_Recent_Comments extends WP_Widget {

	function __construct() {
		$widget_ops = array('classname' => 'widget_recent_comments', 'description' => __( 'The most recent comments' ) );
		parent::__construct('toist-recent-comments', __('Torontoist Recent Comments'), $widget_ops);
		$this->alt_option_name = 'widget_recent_comments';

		if ( is_active_widget(false, false, $this->id_base) )
			add_action( 'wp_head', array($this, 'recent_comments_style') );

		add_action( 'comment_post', array($this, 'flush_widget_cache') );
		add_action( 'transition_comment_status', array($this, 'flush_widget_cache') );
	}

	function recent_comments_style() {
		if ( ! current_theme_supports( 'widgets' ) // Temp hack #14876
			|| ! apply_filters( 'show_recent_comments_widget_style', true, $this->id_base ) )
			return;
		?>
	<style type="text/css">.recentcomments a{display:inline !important;padding:0 !important;margin:0 !important;}</style>
<?php
	}

	function flush_widget_cache() {
		wp_cache_delete('widget_recent_comments', 'widget');
	}

	function widget( $args, $instance ) {
		global $comments, $comment;

		$cache = wp_cache_get('widget_recent_comments', 'widget');

		if ( ! is_array( $cache ) )
			$cache = array();

		if ( ! isset( $args['widget_id'] ) )
			$args['widget_id'] = $this->id;

		if ( isset( $cache[ $args['widget_id'] ] ) ) {
			echo $cache[ $args['widget_id'] ];
			return;
		}

 		extract($args, EXTR_SKIP);
 		$output = '';
		$title = apply_filters( 'widget_title', empty( $instance['title'] ) ? __( 'Recent Comments' ) : $instance['title'], $instance, $this->id_base );

		if ( empty( $instance['number'] ) || ! $number = absint( $instance['number'] ) )
 			$number = 5;

		$comments = get_comments( apply_filters( 'widget_comments_args', array( 'number' => $number, 'status' => 'approve', 'post_status' => 'publish' ) ) );
		$output .= $before_widget;
		if ( $title )
			$output .= $before_title . $title . $after_title;

		$output .= '<ul id="recentcomments">';
		if ( $comments ) {
			// Prime cache for associated posts. (Prime post term cache if we need it for permalinks.)
			$post_ids = array_unique( wp_list_pluck( $comments, 'comment_post_ID' ) );
			_prime_post_caches( $post_ids, strpos( get_option( 'permalink_structure' ), '%category%' ), false );

			foreach ( (array) $comments as $comment) {
				//var_dump($comment);
				$cutoff = 130;
				
				$comment_link = esc_url(get_comment_link($comment->comment_ID));
				
				if(strlen($comment->comment_content) > $cutoff){
					$content = substr($comment->comment_content,0,$cutoff-14)
						.'&hellip; <a class="more-link" href="'.$comment_link.'">Keep reading</a>';
				}else{$content = $comment->comment_content;}
				
				$output .=  '<li class="comment">' . /* translators: comments widget: 1: comment author, 2: post link */ sprintf(_x('%1$s on %2$s', 'widgets'), get_comment_author(), '<a href="' . $comment_link . '" rel="nofollow">' . get_the_title($comment->comment_post_ID) . '</a>') .'<blockquote>'.$content.'</blockquote>'.'</li>';
			}
 		}
		$output .= '</ul>';
		$output .= $after_widget;

		echo $output;
		$cache[$args['widget_id']] = $output;
		wp_cache_set('widget_recent_comments', $cache, 'widget');
	}

	function update( $new_instance, $old_instance ) {
		$instance = $old_instance;
		$instance['title'] = strip_tags($new_instance['title']);
		$instance['number'] = absint( $new_instance['number'] );
		$this->flush_widget_cache();

		$alloptions = wp_cache_get( 'alloptions', 'options' );
		if ( isset($alloptions['widget_recent_comments']) )
			delete_option('widget_recent_comments');

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
	return register_widget('Toist_Recent_Comments');
});

class Toist_Most_Commented extends WP_Widget{
	public function __construct(){
		parent::__construct(
			'toist_most_commented',
			'Most Commented',
			array(
				'description'	=>	__('Shows the most commented','toistmostread')
			)
		);
		/* REMOVE ME
		$this->timespans = array(
			86400		=>	'Past day',
			604800	=>	'Past week',
			2592000	=>	'Past 30 days',
			7776000	=>	'Past 90 days',
			31557600=>	'Past year'
		);
		*/
	}
	public function form($instance){
		if(isset($instance['title'])){
			$title = $instance['title'];
		}else{
			$title = __('Most Commented','toistmostread');
		}
		
		$schedules = wp_get_schedules();
		?>
		<p>
			<label for="<?php echo $this->get_field_id('title'); ?>">Title:</label>
			<input id="<?php echo $this->get_field_id('title'); ?>" name="<?php echo $this->get_field_name('title'); ?>" type="text" value="<?php echo esc_attr($title) ?>" />
		</p>
		<p>
			<label for="<?php echo $this->get_field_id('posts_num'); ?>">Number of posts to show:</label>
			<input id="<?php echo $this->get_field_id('posts_num'); ?>" name="<?php echo $this->get_field_name('posts_num'); ?>" type="number" value="<?php echo esc_attr($instance['posts_num']) ?>" />
		</p>
		<p>
			<label for="<?php echo $this->get_field_id('num_days'); ?>">Days of comments to compile:</label>
			<input id="<?php echo $this->get_field_id('num_days'); ?>" name="<?php echo $this->get_field_name('num_days'); ?>" type="number" value="<?php echo esc_attr($instance['num_days']) ?>" />
		</p>
		
		
		<?php 
	
	}
	public function update($new_instance,$old_instance){
		if($new_instance['post_num'] != intval($new_instance['post_num']))
			$new_instance['post_num'] = 0;
		if($new_instance['num_days'] != intval($new_instance['num_days']))
			$new_instance['num_days'] = 1;
	
		return $new_instance;
	}
	public function widget($args,$instance){
		global $wpdb;
		if(!isset($instance['post_num'])) $instance['post_num'] = 1;
		$discussed_transient = 'toist-most-commented-'.$instance['post_num'];
		$commentnum_transient = 'toist-commented-posts-'.$instance['post_num'];		
		
		extract($args, EXTR_SKIP);
		$title = apply_filters( 'widget_title', empty( $instance['title'] ) ? __( 'Most Commented' ) : $instance['title'], $instance, $this->id_base );
		
		echo $before_widget;
		echo $before_title.$title.$after_title;
		
		$num_days = !empty($instance['num_days'])? $instance['num_days'] : 1;
		
		$discussed = get_transient($discussed_transient);
		$post_comments = get_transient($commentnum_transient);
				
		if($discussed === false){
			$limit = $wpdb->prepare('LIMIT 0,%d',$instance['posts_num']);
			$days = $wpdb->prepare('DATE_SUB(NOW(),INTERVAL %d DAY)',$num_days);
		
			$res = $wpdb->get_results(
				"SELECT comment_post_ID,COUNT(*) as comments FROM $wpdb->comments WHERE comment_date >= $days AND comment_approved = 1 AND comment_type= '' GROUP BY comment_post_ID ORDER BY comments DESC $limit",
				ARRAY_A
			);
				
			$post_comments = array();
			foreach($res as $post){
				$post_comments[$post['comment_post_ID']] = $post['comments'];
			}
			
			set_transient($commentnum_transient,$post_comments,15*MINUTE_IN_SECONDS);
		
			$args = array(
				'post__in'		=>	array_keys($post_comments),
				'orderby'			=>	'post__in'
			);
			if($instance['posts_num']) $args['posts_per_page'] = $instance['posts_num'];
		
			$discussed = new WP_Query($args);
			set_transient($discussed_transient,$discussed,15 * MINUTE_IN_SECONDS);
		}
		
		if($discussed->have_posts()):?>
		<div id="most-commented">
		<?php
		while($discussed->have_posts()): $discussed->the_post(); ?>
			<article>
				<a href="<?php the_permalink(); ?>" rel="nofollow">
					<p>
						<?php if(isset($post_comments) && is_array($post_comments)){ ?>
						<span class="comments"><?php echo $post_comments[get_the_ID()]; ?></span>
						<span class="assistive-text">comments on</span>
						<?php } ?>
					</p>
					<h1><?php the_title(); ?></h1>
				</a>
			</article>
		<?php endwhile;?>		
		</div><?php 
		
		endif;
		echo $after_widget;

	}
}
add_action('widgets_init',function(){
	return register_widget('Toist_Most_Commented');
});
?>

<?php

/**
 * @package Torontoist
 */
/*
Plugin Name: Days To and Since
Plugin URI: http://torontoist.com
Description: A widget to display the days to or since a given date.
Version: 0.1
Author: Senning Luk
Author URI: http://puppydogtales.ca
License: GPLv2 or later
*/

class PDT_Days_To extends WP_Widget {

	function __construct() {
		$widget_ops = array('classname' => 'widget_days_to', 'description' => __( 'Shows the days to or since a given date' ) );
		parent::__construct('pdt-days-to', __('Days To'), $widget_ops);
		$this->alt_option_name = 'pdt_days_to';
	}

	function widget( $args, $instance ) {

		echo $before_widget;
		
		$title = apply_filters('widget_title',$instance['title']);
		$timezone = new DateTimeZone('America/Toronto');
		$target = new DateTime($instance['date'],$timezone);
		$today = new DateTime('now',$timezone);
		$diff = $target->diff($today);
						
		if(
			($instance['since'] && !$diff->invert && $diff->days) 
			|| (!$instance['since'] && $diff->invert && $diff->days) 
			):
		?>
		<hr />
		<section id="counter">
			<hgroup>
				<?php 
					if($instance['label']) printf('<h1>%s</h1>',$instance['label']); 
					if($instance['title']) printf('<h2>%s</h2>',$instance['title']);
					?>
			</hgroup>
			<div class="days"><?php echo $diff->format($instance['date_format']) ?></div>
			<?php if ($instance['link_url'] && $instance['link_text']): ?>
			<p class="link"><a href="<?php echo $instance['link_url']; ?>"><img src="<?php echo bloginfo('stylesheet_directory'); ?>/images/graphics/_.gif" class="sprite" /><?php echo $instance['link_text']; ?></a></p>
			<?php endif; ?>
	</section>
	<?php
	endif;
	echo $after_widget;
	}

	function update( $new_instance, $old_instance ) {
		$instance = $old_instance;
		$instance['label'] = esc_attr($new_instance['label']);
		$instance['title'] = esc_attr($new_instance['title']);
		$instance['since'] = $new_instance['since'] === 'true';
		$instance['date'] = esc_attr($new_instance['date']);
		$instance['date_format'] = $new_instance['date_format'];
		$instance['link_url'] = $new_instance['link_url'];
		$instance['link_text'] = $new_instance['link_text'];
		return $instance;
	}

	function form( $instance ) {
		$label = isset($instance['label']) ? esc_attr($instance['label']) : '';
		$title = isset($instance['title']) ? esc_attr($instance['title']) : '';
		$since = isset($instance['since']) === true ?: false;
		$date = esc_attr($instance['date']);
		$date_format = isset($instance['date_format']) ? $instance['date_format'] : '%d';
		$link_url = isset($instance['link_url']) ? $instance['link_url'] : '';
		$link_text = isset($instance['link_text']) ? $instance['link_text'] : '';
?>
		<p><label for="<?php echo $this->get_field_id('label'); ?>"><?php _e('Label:'); ?></label>
		<input class="widefat" id="<?php echo $this->get_field_id('label'); ?>" name="<?php echo $this->get_field_name('label'); ?>" type="text" value="<?php echo $label; ?>" /></p>
		<p><label for="<?php echo $this->get_field_id('title'); ?>"><?php _e('Title:'); ?></label>
		<input class="widefat" id="<?php echo $this->get_field_id('title'); ?>" name="<?php echo $this->get_field_name('title'); ?>" type="text" value="<?php echo $title; ?>" /></p>

		<p>
			<label for="<?php echo $this->get_field_id('days_since'); ?>"><?php _e('Days since'); ?></label>
			<input type="radio" name="<?php echo $this->get_field_name('since') ?>" value="true" id="<?php echo $this->get_field_id('days_since'); ?>" <?php echo $since ? 'checked="checked"' : ''; ?> />
			<label for="<?php echo $this->get_field_id('days_to'); ?>"><?php _e('Days to'); ?></label>
			<input type="radio" name="<?php echo $this->get_field_name('since') ?>" value="false" id="<?php echo $this->get_field_id('days_to'); ?>" <?php $since ? '' : 'checked="checked"'; ?> />
		</p>

		<p><label for="<?php echo $this->get_field_id('date'); ?>"><?php _e('Target date:'); ?></label>
		<input id="<?php echo $this->get_field_id('date'); ?>" name="<?php echo $this->get_field_name('date'); ?>" type="text" value="<?php echo $date; ?>" /></p>
		
		<p><label for="<?php echo $this->get_field_id('date_format'); ?>"><?php _e('Date format:'); ?></label>
		<input id="<?php echo $this->get_field_id('date_format'); ?>" name="<?php echo $this->get_field_name('date_format'); ?>" type="text" value="<?php echo $date_format; ?>" /></p>

		<p><label for="<?php echo $this->get_field_id('link_url'); ?>"><?php _e('Link URL:'); ?></label>
		<input id="<?php echo $this->get_field_id('link_url'); ?>" name="<?php echo $this->get_field_name('link_url'); ?>" type="text" value="<?php echo $link_url; ?>" /></p>
		
		<p><label for="<?php echo $this->get_field_id('link_text'); ?>"><?php _e('Link text:'); ?></label>
		<input id="<?php echo $this->get_field_id('link_text'); ?>" name="<?php echo $this->get_field_name('link_text'); ?>" type="text" value="<?php echo $link_text; ?>" /></p>
<?php
	}
}
add_action('widgets_init',function(){
	return register_widget('PDT_Days_To');
});

